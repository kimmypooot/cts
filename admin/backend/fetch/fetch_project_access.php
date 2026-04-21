<?php

/**
 * backend/fetch/fetch_project_access.php
 * Returns all active users and their current role for a given project.
 * Also returns the available roles for that project.
 *
 * Method : GET
 * Params : project_id (int, required)
 *
 * Response:
 * {
 *   success : true,
 *   users   : [{ user_id, name, fo_rsu, position, role_id }],
 *   roles   : [{ id, name }]
 * }
 *
 * Notes:
 *  - Users with role = 'none' (disabled accounts) are excluded.
 *  - role_id is null when a user has no assignment for this project.
 *  - Users are ordered by division then surname then first name.
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

/* ── Validate input ──────────────────────────────────────────────────────── */
$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

if ($projectId <= 0) {
    json_error('Invalid or missing project_id.');
}

/* ── Verify project exists ───────────────────────────────────────────────── */
try {
    $chk = $conn->prepare("SELECT id FROM imis_projects WHERE id = ? LIMIT 1");
    $chk->execute([$projectId]);
    if (!$chk->fetch()) {
        json_error('Project not found.', 404);
    }
} catch (PDOException $e) {
    error_log('[fetch_project_access] project check: ' . $e->getMessage());
    json_error('Server error.', 500);
}

/* ── Fetch users with their current role for this project ────────────────── */
try {
    $usersStmt = $conn->prepare(
        "SELECT
             u.id                                               AS user_id,
             CONCAT(u.fname, ' ', u.minitial, ' ', u.lname)   AS name,
             u.fo_rsu,
             u.position,
             sa.role_id
         FROM users_cscro8 u
         LEFT JOIN imis_system_access sa
                ON sa.user_id    = u.id
               AND sa.project_id = :project_id
         WHERE u.status = 'Active'
           AND u.role  != 'none'
         ORDER BY
             u.fo_rsu  ASC,
             u.lname   ASC,
             u.fname   ASC"
    );
    $usersStmt->execute([':project_id' => $projectId]);
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    /* Cast types for clean JSON */
    foreach ($users as &$user) {
        $user['user_id'] = (int) $user['user_id'];
        $user['role_id'] = $user['role_id'] !== null ? (int) $user['role_id'] : null;
        $user['fo_rsu']  = $user['fo_rsu']  ?: 'Unassigned';
        $user['position'] = $user['position'] ?: '';
    }
    unset($user);
} catch (PDOException $e) {
    error_log('[fetch_project_access] users query: ' . $e->getMessage());
    json_error('Failed to load users.', 500);
}

/* ── Fetch available roles for this project ──────────────────────────────── */
try {
    $rolesStmt = $conn->prepare(
        "SELECT id, name
         FROM imis_access_roles
         WHERE project_id = ?
         ORDER BY id ASC"
    );
    $rolesStmt->execute([$projectId]);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($roles as &$role) {
        $role['id'] = (int) $role['id'];
    }
    unset($role);
} catch (PDOException $e) {
    error_log('[fetch_project_access] roles query: ' . $e->getMessage());
    json_error('Failed to load roles.', 500);
}

/* ── Respond ─────────────────────────────────────────────────────────────── */
json_success(['users' => $users, 'roles' => $roles]);
