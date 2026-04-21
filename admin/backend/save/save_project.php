<?php

/**
 * backend/save/save_project.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Creates or updates an imis_projects row and its imis_access_roles.
 *
 * Behaviour
 *   payload.id == null  → INSERT project, INSERT all roles
 *   payload.id != null  → UPDATE project fields; for each role:
 *       role.id != null → UPDATE the existing role
 *       role.id == null → INSERT as a new role
 *     Any existing role NOT in the payload is DELETED (full replacement of the
 *     role set for that project is the intended UX from the modal).
 *
 * Auth:    superadmin session + matching CSRF token required.
 * Method:  POST, JSON body, AJAX only.
 * Returns: { success: true|false, message: string [, field: string] }
 *          field is set when the error maps to a specific form control.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/connect.php';

// ── Method guard ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

// ── AJAX-only ─────────────────────────────────────────────────────────────────
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh and try again.']);
    exit();
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty request body.']);
    exit();
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit();
}

// ── Helper ────────────────────────────────────────────────────────────────────
function jsonExit(bool $success, string $message, ?string $field = null, int $status = 200): never
{
    http_response_code($status);
    $out = ['success' => $success, 'message' => $message];
    if ($field !== null) $out['field'] = $field;
    echo json_encode($out);
    exit();
}

// ── Sanitise & validate ───────────────────────────────────────────────────────
$projectId    = isset($data['id']) && $data['id'] !== null ? (int) $data['id'] : null;
$codeName     = strtoupper(trim((string) ($data['code_name']     ?? '')));
$projectName  = trim((string) ($data['project_name']  ?? ''));
$description  = trim((string) ($data['description']   ?? ''));
$guestUrl     = trim((string) ($data['guest_url']     ?? '')) ?: null;
$requiresSetup = (int) (bool) ($data['requires_setup'] ?? 0);
$isActive     = (int) (bool) ($data['is_active']      ?? 1);
$roles        = is_array($data['roles'] ?? null) ? $data['roles'] : [];

// Required fields
if ($codeName === '') {
    jsonExit(false, 'System code is required.', 'code_name', 422);
}
if (!preg_match('/^[A-Z0-9_-]{1,100}$/', $codeName)) {
    jsonExit(false, 'System code may only contain uppercase letters, numbers, hyphens and underscores.', 'code_name', 422);
}
if ($projectName === '') {
    jsonExit(false, 'Project name is required.', 'project_name', 422);
}
if ($description === '') {
    jsonExit(false, 'Description is required.', 'description', 422);
}

// Validate role rows
$cleanRoles = [];
foreach ($roles as $i => $role) {
    $rId       = isset($role['id']) && $role['id'] !== null ? (int) $role['id'] : null;
    $rName     = trim((string) ($role['name']        ?? ''));
    $rUrl      = trim((string) ($role['project_url'] ?? ''));
    $rExternal = (int) (bool) ($role['is_external']  ?? 0);

    if ($rName === '') {
        jsonExit(false, 'Role name is required on row ' . ($i + 1) . '.', null, 422);
    }
    if ($rUrl === '') {
        jsonExit(false, 'Redirect URL is required on row ' . ($i + 1) . '.', null, 422);
    }

    $cleanRoles[] = [
        'id'          => $rId,
        'name'        => $rName,
        'project_url' => $rUrl,
        'is_external' => $rExternal,
    ];
}

// ── Execute within a transaction ─────────────────────────────────────────────
try {
    $conn->beginTransaction();

    if ($projectId === null) {
        // ── CREATE ────────────────────────────────────────────────────────────
        // Check code_name uniqueness
        $chk = $conn->prepare('SELECT id FROM imis_projects WHERE code_name = :cn LIMIT 1');
        $chk->execute([':cn' => $codeName]);
        if ($chk->fetch()) {
            $conn->rollBack();
            jsonExit(false, "System code '{$codeName}' is already in use.", 'code_name', 422);
        }

        $ins = $conn->prepare(
            'INSERT INTO imis_projects
                (code_name, project_name, description, guest_url, requires_setup, is_active)
             VALUES
                (:cn, :pn, :desc, :gu, :rs, :ia)'
        );
        $ins->execute([
            ':cn'   => $codeName,
            ':pn'   => $projectName,
            ':desc' => $description,
            ':gu'   => $guestUrl,
            ':rs'   => $requiresSetup,
            ':ia'   => $isActive,
        ]);
        $projectId = (int) $conn->lastInsertId();

        // Insert all roles
        insertRoles($conn, $projectId, $cleanRoles);

        $conn->commit();
        jsonExit(true, "Project '{$projectName}' created successfully.");
    } else {
        // ── UPDATE ────────────────────────────────────────────────────────────
        // Confirm project exists
        $exists = $conn->prepare('SELECT id FROM imis_projects WHERE id = :id LIMIT 1');
        $exists->execute([':id' => $projectId]);
        if (!$exists->fetch()) {
            $conn->rollBack();
            jsonExit(false, 'Project not found.', null, 404);
        }

        // code_name is immutable — skip update on that field
        $upd = $conn->prepare(
            'UPDATE imis_projects
             SET    project_name  = :pn,
                    description   = :desc,
                    guest_url     = :gu,
                    requires_setup = :rs,
                    is_active     = :ia
             WHERE  id = :id'
        );
        $upd->execute([
            ':pn'   => $projectName,
            ':desc' => $description,
            ':gu'   => $guestUrl,
            ':rs'   => $requiresSetup,
            ':ia'   => $isActive,
            ':id'   => $projectId,
        ]);

        // Sync roles: upsert submitted rows, delete omitted ones
        syncRoles($conn, $projectId, $cleanRoles);

        $conn->commit();
        jsonExit(true, "Project '{$projectName}' updated successfully.");
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();

    if ($e->getCode() === '23000') {
        if (str_contains($e->getMessage(), 'uq_projects_code_name')) {
            jsonExit(false, "System code '{$codeName}' is already in use.", 'code_name', 422);
        }
        if (str_contains($e->getMessage(), 'uq_access_roles_project_name')) {
            jsonExit(false, 'Duplicate role names are not allowed within the same project.', null, 422);
        }
        // FK violation — a role being removed is still assigned to at least one user
        jsonExit(false, 'One or more roles cannot be removed because they are still assigned to users. '
            . 'Please revoke those assignments in System Access Management first.', null, 422);
    }

    error_log('[save_project] DB error: ' . $e->getMessage());
    jsonExit(false, 'A database error occurred. Please try again.', null, 500);
}

// ─────────────────────────────────────────────────────────────────────────────

/** Bulk-inserts role rows for a newly created project. */
function insertRoles(PDO $conn, int $projectId, array $roles): void
{
    if (empty($roles)) return;

    $stmt = $conn->prepare(
        'INSERT INTO imis_access_roles (project_id, name, project_url, is_external)
         VALUES (:pid, :name, :url, :ext)'
    );
    foreach ($roles as $role) {
        $stmt->execute([
            ':pid'  => $projectId,
            ':name' => $role['name'],
            ':url'  => $role['project_url'],
            ':ext'  => $role['is_external'],
        ]);
    }
}

/**
 * Syncs the submitted role set with what exists in the DB for a project.
 *   - Roles with an id → UPDATE
 *   - Roles without an id → INSERT
 *   - Existing roles whose id is NOT in the payload → DELETE
 *
 * Protects against deletion when live imis_system_access rows reference the role
 * (FK ON DELETE RESTRICT will throw; caught by the caller's try/catch).
 */
function syncRoles(PDO $conn, int $projectId, array $roles): void
{
    $submittedIds = array_filter(array_column($roles, 'id'));

    // Delete roles removed from the form (not in submitted set)
    if (!empty($submittedIds)) {
        $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
        $del = $conn->prepare(
            "DELETE FROM imis_access_roles
             WHERE project_id = ? AND id NOT IN ({$placeholders})"
        );
        $del->execute([$projectId, ...$submittedIds]);
    } else {
        // All roles were removed from the form → delete all (FK will protect assigned ones)
        $conn->prepare('DELETE FROM imis_access_roles WHERE project_id = ?')
            ->execute([$projectId]);
    }

    $upd = $conn->prepare(
        'UPDATE imis_access_roles
         SET name = :name, project_url = :url, is_external = :ext
         WHERE id = :id AND project_id = :pid'
    );
    $ins = $conn->prepare(
        'INSERT INTO imis_access_roles (project_id, name, project_url, is_external)
         VALUES (:pid, :name, :url, :ext)'
    );

    foreach ($roles as $role) {
        if ($role['id'] !== null) {
            $upd->execute([
                ':name' => $role['name'],
                ':url'  => $role['project_url'],
                ':ext'  => $role['is_external'],
                ':id'   => $role['id'],
                ':pid'  => $projectId,
            ]);
        } else {
            $ins->execute([
                ':pid'  => $projectId,
                ':name' => $role['name'],
                ':url'  => $role['project_url'],
                ':ext'  => $role['is_external'],
            ]);
        }
    }
}
