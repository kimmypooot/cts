<?php

/**
 * api/system-redirect.php — Unified system access & redirect resolver
 * ─────────────────────────────────────────────────────────────────────────────
 * Resolves the authenticated user's role and redirect URL for a requested
 * system, then logs the access event before returning the redirect payload.
 *
 * Changes from previous version
 * ─────────────────────────────
 * [LOG-1] require SystemAccessLogger.php.
 * [LOG-2] Added p.id AS project_id to the role-resolution SELECT so the
 *         logger has the FK it needs — the only schema-query change.
 * [LOG-3] Instantiate $accessLogger once after the role query succeeds.
 * [LOG-4] Call $accessLogger->logAccess() on every success path, immediately
 *         before jsonExit(true, …).  Logging failure never blocks the redirect.
 *
 * Everything else (auth guards, jsonExit helper, requires_setup switch,
 * guest_url handling) is completely unchanged.
 *
 * Method:  POST
 * Body:    system=<SYSTEM_CODE>
 * Returns: { success: true,  redirect: '/...' [, external: true] }
 *        | { success: false, message: '...' }
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/session.php';

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($xrw) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/SystemAccessLogger.php'; // [LOG-1]

$system = strtoupper(trim($_POST['system'] ?? ''));
$userId = (int) $_SESSION['id'];

// ── Helper ────────────────────────────────────────────────────────────────────
function jsonExit(
    bool    $success,
    ?string $redirect = null,
    ?string $message  = null,
    bool    $external = false
): never {
    $payload = ['success' => $success];
    if ($redirect !== null) $payload['redirect'] = $redirect;
    if ($message  !== null) $payload['message']  = $message;
    if ($external)           $payload['external'] = true;
    echo json_encode($payload);
    exit();
}

// ── Resolve the user's role and URL for this system in one query ───────────────
try {
    $stmt = $conn->prepare(
        'SELECT
             p.id          AS project_id,
             p.requires_setup,
             p.guest_url,
             r.name        AS role_name,
             r.project_url,
             r.is_external
         FROM  imis_projects p
         LEFT JOIN imis_system_access sa
                   ON  sa.project_id = p.id
                   AND sa.user_id    = :userId
         LEFT JOIN imis_access_roles r ON r.id = sa.role_id
         WHERE p.code_name = :system
           AND p.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([':userId' => $userId, ':system' => $system]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[system-redirect] DB error resolving role: ' . $e->getMessage());
    http_response_code(500);
    jsonExit(false, null, 'A system error occurred. Please try again.');
}

// Unknown or inactive system
if (!$row) {
    http_response_code(400);
    jsonExit(false, null, 'Unknown or inactive system: ' . htmlspecialchars($system, ENT_QUOTES, 'UTF-8'));
}

// User has no assigned role for this system
if ($row['role_name'] === null) {
    if (!empty($row['guest_url'])) {
        jsonExit(true, $row['guest_url']);
    }
    jsonExit(false, null, 'You do not have access to this system.');
}

$projectId  = (int)    $row['project_id'];
$roleName   = (string) $row['role_name'];
$url        = (string) $row['project_url'];
$isExternal = (bool)   $row['is_external'];

// [LOG-3] Instantiate once — used on every success path below.
$accessLogger = new SystemAccessLogger($conn);

// ── Simple systems: URL is fully resolved from the DB ────────────────────────
if (!(bool) $row['requires_setup']) {
    if ($system === 'OTRS' && $roleName === 'User') {
        $url .= '?location=' . urlencode($_SESSION['type'] ?? '');
    }

    // [LOG-4] Log before redirecting — failure is non-fatal.
    $accessLogger->logAccess($userId, $projectId, $system, $roleName);

    jsonExit(true, $url, null, $isExternal);
}

// ── Systems requiring secondary DB work before redirect ───────────────────────
try {
    switch ($system) {

        case 'CTS': {
                $stmt = $conn->prepare(
                    'SELECT id, ao_number FROM cts_manage_users WHERE user = :user LIMIT 1'
                );
                $stmt->execute([':user' => $userId]);
                $ctsRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$ctsRow) {
                    jsonExit(false, null, 'Access is limited to Legal Services Division personnel only.');
                }

                $_SESSION['cts_user_id'] = (int)    $ctsRow['id'];
                $_SESSION['ao_number']   = (string) ($ctsRow['ao_number'] ?? '');

                // [LOG-4]
                $accessLogger->logAccess($userId, $projectId, $system, $roleName);

                jsonExit(true, $url);
            }

        case 'RFCS': {
                if ($roleName === 'Admin') {
                    // [LOG-4]
                    $accessLogger->logAccess($userId, $projectId, $system, $roleName);
                    jsonExit(true, $url);
                }

                $stmt = $conn->prepare(
                    "SELECT id FROM trip_drivers
                  WHERE user   = :user
                  AND   status != 'Inactive'
                  LIMIT 1"
                );
                $stmt->execute([':user' => $userId]);
                $driver = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$driver) {
                    jsonExit(false, null, 'No active driver account found for your user.');
                }

                $_SESSION['driver_id'] = (int) $driver['id'];

                // [LOG-4]
                $accessLogger->logAccess($userId, $projectId, $system, $roleName);

                jsonExit(true, $url);
            }

        case 'ICTSRTS': {
                $stmt = $conn->prepare(
                    'SELECT id FROM itg_tbl WHERE id = :userId LIMIT 1'
                );
                $stmt->execute([':userId' => $userId]);
                $_SESSION['is_itg_member'] = (bool) $stmt->fetch();

                // [LOG-4]
                $accessLogger->logAccess($userId, $projectId, $system, $roleName);

                jsonExit(true, $url);
            }

        default:
            error_log('[system-redirect] requires_setup=1 but no handler for system: ' . $system);
            http_response_code(500);
            jsonExit(false, null, 'System is not correctly configured. Contact ITG.');
    }
} catch (PDOException $e) {
    error_log('[system-redirect] DB error during setup for ' . $system . ': ' . $e->getMessage());
    http_response_code(500);
    jsonExit(false, null, 'A system error occurred. Please try again.');
}
