<?php

/**
 * backend/update/update_system_access.php
 * Bulk-updates role assignments for all users in a given project.
 *
 * Method  : POST (Content-Type: application/json)
 * Body    :
 * {
 *   "project_id"  : int,
 *   "assignments" : [{ "user_id": int, "role_id": int }]
 *                   role_id = 0 → revoke access (DELETE)
 * }
 *
 * Security & data-integrity measures:
 *   1. Every role_id > 0 must belong to the target project (prevents cross-project injection).
 *   2. Every user_id must exist and be Active.
 *   3. The entire update runs inside a single transaction; any failure rolls back all changes.
 *   4. Orphaned imis_system_access rows (role deleted from project but access row lingered)
 *      are cleaned up as a side-effect of the unique constraint + DELETE path.
 *
 * Response: { success: true|false, message: string }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

/* ── Parse JSON body ─────────────────────────────────────────────────────── */
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    json_error('Invalid JSON payload.');
}

$projectId   = isset($payload['project_id'])  ? (int) $payload['project_id']  : 0;
$assignments = isset($payload['assignments']) && is_array($payload['assignments'])
    ? $payload['assignments']
    : [];

/* ── Basic input validation ──────────────────────────────────────────────── */
if ($projectId <= 0) {
    json_error('Invalid project_id.');
}
if (empty($assignments)) {
    json_error('No assignments provided.');
}

/* Normalise and deduplicate by user_id (last entry wins) */
$normalised = [];
foreach ($assignments as $item) {
    $uid = isset($item['user_id']) ? (int) $item['user_id'] : 0;
    $rid = isset($item['role_id']) ? (int) $item['role_id'] : 0;
    if ($uid <= 0) continue;
    $normalised[$uid] = $rid; // dedup; keep last
}

if (empty($normalised)) {
    json_error('No valid user_id values in assignments.');
}

/* ── Verify project exists and is active ─────────────────────────────────── */
try {
    $projStmt = $conn->prepare(
        "SELECT id FROM imis_projects WHERE id = ? LIMIT 1"
    );
    $projStmt->execute([$projectId]);
    if (!$projStmt->fetch()) {
        json_error('Project not found.', 404);
    }
} catch (PDOException $e) {
    error_log('[update_system_access] project check: ' . $e->getMessage());
    json_error('Server error.', 500);
}

/* ── Validate every role_id (> 0) belongs to this project ───────────────── */
$nonZeroRoleIds = array_unique(array_filter(array_values($normalised), fn($r) => $r > 0));

if (!empty($nonZeroRoleIds)) {
    $ph = implode(',', array_fill(0, count($nonZeroRoleIds), '?'));
    try {
        $roleChk = $conn->prepare(
            "SELECT id
             FROM imis_access_roles
             WHERE project_id = ? AND id IN ($ph)"
        );
        $roleChk->execute(array_merge([$projectId], array_values($nonZeroRoleIds)));
        $validRoleIds = array_column($roleChk->fetchAll(PDO::FETCH_ASSOC), 'id');
        $validRoleIds = array_map('intval', $validRoleIds);

        foreach ($nonZeroRoleIds as $rid) {
            if (!in_array($rid, $validRoleIds, true)) {
                json_error("Role ID {$rid} does not belong to project {$projectId}.", 422);
            }
        }
    } catch (PDOException $e) {
        error_log('[update_system_access] role validation: ' . $e->getMessage());
        json_error('Server error during role validation.', 500);
    }
}

/* ── Validate every user_id is Active and exists ─────────────────────────── */
$userIds = array_keys($normalised);
$uh      = implode(',', array_fill(0, count($userIds), '?'));

try {
    $userChk = $conn->prepare(
        "SELECT id
         FROM users_cscro8
         WHERE id IN ($uh) AND status = 'Active' AND role != 'none'"
    );
    $userChk->execute($userIds);
    $validUserIds = array_column($userChk->fetchAll(PDO::FETCH_ASSOC), 'id');
    $validUserIds = array_map('intval', $validUserIds);
} catch (PDOException $e) {
    error_log('[update_system_access] user validation: ' . $e->getMessage());
    json_error('Server error during user validation.', 500);
}

/* Silently skip invalid/inactive users rather than failing the whole request */
$safeAssignments = [];
foreach ($normalised as $uid => $rid) {
    if (in_array($uid, $validUserIds, true)) {
        $safeAssignments[$uid] = $rid;
    }
}

if (empty($safeAssignments)) {
    json_error('None of the provided user IDs are valid active users.', 422);
}

/* ── Execute inside a transaction ────────────────────────────────────────── */
try {
    $conn->beginTransaction();

    $upsert = $conn->prepare(
        "INSERT INTO imis_system_access (user_id, project_id, role_id)
         VALUES (:uid, :pid, :rid)
         ON DUPLICATE KEY UPDATE
             role_id    = VALUES(role_id),
             granted_at = CURRENT_TIMESTAMP"
    );

    $delete = $conn->prepare(
        "DELETE FROM imis_system_access
         WHERE user_id = :uid AND project_id = :pid"
    );

    foreach ($safeAssignments as $uid => $rid) {
        if ($rid === 0) {
            $delete->execute([':uid' => $uid, ':pid' => $projectId]);
        } else {
            $upsert->execute([
                ':uid' => $uid,
                ':pid' => $projectId,
                ':rid' => $rid,
            ]);
        }
    }

    /*
     * Cleanup: remove any lingering imis_system_access rows for this project
     * whose role_id no longer exists in imis_access_roles (orphan guard).
     * This handles the edge case where a role was deleted after assignment.
     */
    $conn->prepare(
        "DELETE sa
         FROM imis_system_access sa
         LEFT JOIN imis_access_roles ar
                ON ar.id = sa.role_id AND ar.project_id = sa.project_id
         WHERE sa.project_id = :pid
           AND ar.id IS NULL"
    )->execute([':pid' => $projectId]);

    $conn->commit();

    json_success(null, 'Access roles updated successfully.');
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[update_system_access] transaction: ' . $e->getMessage());
    json_error('Failed to save access changes.', 500);
}
