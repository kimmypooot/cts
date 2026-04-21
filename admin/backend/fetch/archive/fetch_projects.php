<?php

/**
 * backend/fetch/fetch_projects.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Dual-mode fetch endpoint:
 *
 *   GET ?            → returns all projects (used by DataTable + access_management.js)
 *   GET ?id=<int>    → returns one project plus its imis_access_roles rows (edit modal)
 *
 * Auth:  superadmin session required.
 * Type:  GET, AJAX only.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/connect.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit();
}

// ── AJAX-only ─────────────────────────────────────────────────────────────────
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// ── Dispatch ──────────────────────────────────────────────────────────────────
try {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id !== null) {
        fetchOne($conn, $id);
    } else {
        fetchAll($conn);
    }
} catch (PDOException $e) {
    error_log('[fetch_projects] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
exit();

// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns all projects, ordered by project_name.
 * Consumed by both the manage_projects DataTable and access_management.js.
 */
function fetchAll(PDO $conn): void
{
    $stmt = $conn->query(
        'SELECT id, code_name, project_name, description,
                guest_url, requires_setup, is_active
         FROM   imis_projects
         ORDER  BY project_name ASC'
    );
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * Returns a single project row plus all its imis_access_roles.
 * Consumed by the edit modal.
 */
function fetchOne(PDO $conn, int $id): void
{
    $stmt = $conn->prepare(
        'SELECT id, code_name, project_name, description,
                guest_url, requires_setup, is_active
         FROM   imis_projects
         WHERE  id = :id
         LIMIT  1'
    );
    $stmt->execute([':id' => $id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        return;
    }

    $rStmt = $conn->prepare(
        'SELECT id, name, project_url, is_external
         FROM   imis_access_roles
         WHERE  project_id = :pid
         ORDER  BY name ASC'
    );
    $rStmt->execute([':pid' => $id]);
    $roles = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'project' => $project, 'roles' => $roles]);
}
