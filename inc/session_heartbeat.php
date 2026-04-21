<?php

/**
 * imis/inc/session_heartbeat.php
 *
 * Lightweight AJAX endpoint called by idle_session_manager.js every ~30 s
 * to keep the PHP session alive while the user is still active.
 *
 * Returns a JSON object:
 *   { "alive": true,  "remaining": <seconds until server-side expiry> }
 *   { "alive": false, "reason": "timeout" | "unauthenticated" | … }
 *
 * ── KEY FIX vs. previous version ────────────────────────────────────────────
 *   The old file depended on bootstrap.php (deleted).  Without it:
 *     • SESSION_IDLE_TIMEOUT was undefined → PHP evaluated it as 0
 *     • $elapsed >= 0 was ALWAYS true → every ping instantly logged out users
 *   This file now includes config/session.php directly, which defines the
 *   constant correctly (3 600 s = 60 min) before any check runs.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ── 1. Load session configuration (replaces bootstrap.php) ───────────────────
//    $is_public_page = true  tells imis_start_session() to skip the auth gate;
//    we perform our own stricter auth check below.
$is_public_page = true;
require_once __DIR__ . '/../config/session.php';
imis_start_session(true);   // explicit flag; does NOT enforce the auth gate

// ── 2. JSON-only response helper ──────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/**
 * Emit a JSON response and terminate.
 *
 * @param array<string, mixed> $data
 * @param int                  $status  HTTP status code
 * @return never
 */
function json_exit(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit();
}

// ── 3. Only accept POST requests ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['alive' => false, 'reason' => 'method_not_allowed'], 405);
}

// ── 4. CSRF validation ────────────────────────────────────────────────────────
//    JS sends the token in the X-CSRF-Token header; fall back to POST body.
$csrfHeader = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
$csrfPost   = trim((string) ($_POST['_csrf'] ?? ''));
$submitted  = $csrfHeader !== '' ? $csrfHeader : $csrfPost;

if (!validateCsrfToken($submitted)) {
    json_exit(['alive' => false, 'reason' => 'invalid_token'], 403);
}

// ── 5. Authentication check ───────────────────────────────────────────────────
if (!imis_is_authenticated()) {
    json_exit(['alive' => false, 'reason' => 'unauthenticated'], 401);
}

// ── 6. Server-side idle check ─────────────────────────────────────────────────
//
//    WHY THIS IS THE AUTHORITATIVE CHECK
//    ─────────────────────────────────────
//    The JS client tracks idle time in the browser, but the server's
//    $_SESSION['_idle_since'] is the ground truth:
//      • It is set by imis_start_session() on first load.
//      • Every normal (non-heartbeat) page request refreshes it via
//        imis_start_session() → server-side idle enforcement block.
//      • This endpoint refreshes it on each heartbeat ping.
//
//    If $_SESSION['_idle_since'] is 0 or unset, the session was just created
//    (e.g., login page set it moments ago) — treat elapsed as 0.
//
$idleSince = (int) ($_SESSION['_idle_since'] ?? 0);
$now       = time();
$elapsed   = ($idleSince > 0) ? ($now - $idleSince) : 0;

if ($elapsed >= SESSION_IDLE_TIMEOUT) {
    recordLogoutEvent('timeout');
    imis_destroy_session();
    json_exit(['alive' => false, 'reason' => 'timeout'], 401);
}

// ── 7. Keep the session alive ─────────────────────────────────────────────────
//    Refresh the idle timestamp so the 60-minute window resets from NOW.
$_SESSION['_idle_since'] = $now;

// ── 8. Respond with alive status and remaining seconds ───────────────────────
//    Returning the exact remaining time lets the JS client sync its countdown
//    to the server clock, preventing drift between browser and server.
$remaining = SESSION_IDLE_TIMEOUT - $elapsed;

json_exit([
    'alive'     => true,
    'remaining' => max(0, $remaining),
]);
