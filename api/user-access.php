<?php

/**
 * api/user-access.php — User system permissions endpoint
 * ─────────────────────────────────────────────────────────────────────────────
 * Returns a flat map of { SYSTEM_CODE: 'RoleName' } for the authenticated user.
 * Used exclusively by the dashboard JS to render enabled/disabled button states
 * and the role badge — it has no involvement in routing.
 *
 * Method:  GET
 * Returns: { success: true,  access: { 'OTRS': 'Admin', 'ERIS': 'User', … } }
 *        | { success: true,  access: {} }    — user has no access rows (empty object, NEVER null)
 *        | { error: '…' }                    — 401 / 400 / 500
 *
 * FIX APPLIED [Bug 1]:
 *   Previously returned `access: null` when the user had no role assignments.
 *   This caused the JS accessCache.ok() check to permanently return false
 *   (null !== null is always false), bypassing the 5-minute cache on every
 *   division open and producing inconsistent UI state across the same session.
 *   Fix: always cast $access to (object) so an empty map serialises as {} not null.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/session.php';

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($xrw) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

require_once __DIR__ . '/../includes/connect.php';

try {
    // Fetch every (project, role) pair assigned to this user.
    // Only active projects are returned.
    $stmt = $conn->prepare(
        'SELECT p.code_name, r.name AS role
         FROM   imis_system_access sa
         JOIN   imis_access_roles r ON r.id  = sa.role_id
         JOIN   imis_projects     p ON p.id  = sa.project_id
         WHERE  sa.user_id  = :userId
           AND  p.is_active = 1'
    );
    $stmt->execute([':userId' => (int) $_SESSION['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build code_name → role map  e.g. ['OTRS' => 'Admin', 'ERIS' => 'User']
    $access = [];
    foreach ($rows as $row) {
        // Normalise to uppercase so JS lookups are case-insensitive
        $access[strtoupper($row['code_name'])] = $row['role'];
    }

    // FIX [Bug 1]: Cast to (object) so an empty PHP array serialises as JSON {}
    // and NOT as JSON []. json_encode([]) = "[]", json_encode((object)[]) = "{}".
    // The JS cache check is `this.data !== null` — a {} value is truthy and
    // cacheable, while null (the old behaviour for zero-role users) is not.
    echo json_encode([
        'success' => true,
        'access'  => (object) $access,
    ]);
} catch (PDOException $e) {
    error_log('[api/user-access.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred.']);
}
exit();
