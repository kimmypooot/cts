<?php

/**
 * backend/toggle/toggle_project.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Flips the is_active flag on an imis_projects row.
 * Deactivating a project hides it from routing without deleting any data.
 *
 * Auth:    superadmin session + matching CSRF token required.
 * Method:  POST, JSON body, AJAX only.
 * Returns: { success: true|false, message: string, is_active: int }
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/connect.php';

// ── Guards ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

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
$data = json_decode((string) file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid project ID.']);
    exit();
}

// ── Toggle ────────────────────────────────────────────────────────────────────
try {
    // Fetch current state
    $sel = $conn->prepare('SELECT is_active, project_name FROM imis_projects WHERE id = :id LIMIT 1');
    $sel->execute([':id' => $id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        exit();
    }

    $newState = $row['is_active'] ? 0 : 1;
    $label    = $newState ? 'activated' : 'deactivated';

    $upd = $conn->prepare('UPDATE imis_projects SET is_active = :state WHERE id = :id');
    $upd->execute([':state' => $newState, ':id' => $id]);

    echo json_encode([
        'success'   => true,
        'message'   => "'{$row['project_name']}' has been {$label}.",
        'is_active' => $newState,
    ]);
} catch (PDOException $e) {
    error_log('[toggle_project] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
exit();
