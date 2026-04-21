<?php

/**
 * auth/login.php — Server-side login handler
 * ─────────────────────────────────────────────────────────────────────────────
 * Accepts a POST from the login form (via jQuery $.ajax).
 * ALL authentication logic lives here — no credentials ever leave the server.
 *
 * Flow:
 * 1. Method + AJAX header guard
 * 2. Session bootstrap (ini settings → session_start)
 * 3. Input sanitisation
 * 4. Turnstile gate  — checks $_SESSION['turnstile_verified'] only.
 * The actual token verification happens earlier, in
 * auth/turnstile/verify.php, called the moment the
 * widget resolves so the token is still fresh.
 * 5. PDO user lookup
 * 6. Account-status check
 * 7. password_verify() — bcrypt comparison, server-side only
 * 8. Role guard for superadmin mode
 * 9. Session regeneration + session write
 * 10. Async login log (non-blocking — never fails the login)
 * 11. JSON response with redirect URL
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

// ── Response headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ── Session bootstrap ─────────────────────────────────────────────────────────
// IMPORTANT: These settings MUST be identical across login.php,
// auth/login.php, and auth/turnstile/verify.php.
// Any mismatch (especially cookie_secure) causes PHP to issue a different
// session cookie for AJAX calls, making cross-request session data invisible.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   '1');   // always '1' — the site runs on HTTPS
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/connect.php';

// ── Helper: JSON response + exit ──────────────────────────────────────────────
function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

// ── AJAX-only guard ───────────────────────────────────────────────────────────
$xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($xrw) !== 'xmlhttprequest') {
    http_response_code(400);
    respond(false, 'Invalid request.');
}

// ── Input collection ──────────────────────────────────────────────────────────
$username   = trim($_POST['username']  ?? '');
$password   = $_POST['password']       ?? '';   // never trim passwords
$rawMode    = $_POST['login_mode']     ?? 'admin';
$loginMode  = in_array($rawMode, ['admin', 'superadmin'], true) ? $rawMode : 'admin';

// ── Basic presence validation ─────────────────────────────────────────────────
if ($username === '' || $password === '') {
    respond(false, 'All fields are required.');
}

// ── Length sanity checks ──────────────────────────────────────────────────────
if (strlen($username) > 100 || strlen($password) > 255) {
    respond(false, 'Invalid input length.');
}

// ══════════════════════════════════════════════════════════════════════════════
// STEP 4 — CLOUDFLARE TURNSTILE GATE
// ──────────────────────────────────────────────────────────────────────────────
// We do NOT call Cloudflare's siteverify API here any more.
//
// WHY: Turnstile tokens are one-time-use. By the time the user fills the
// form and submits, the token may be spent (first failed attempt) or expired.
// Re-verifying the same token fails with "already used" or "timeout-or-duplicate".
//
// FIX: auth/turnstile/verify.php now verifies the token the instant the
// widget resolves — while it's brand-new — and writes the result into
// $_SESSION['turnstile_verified']. We only read that flag here.
// ══════════════════════════════════════════════════════════════════════════════
if (empty($_SESSION['turnstile_verified'])) {
    // Session flag is absent — user either bypassed the security-check card
    // or their session expired between verification and login submission.
    respond(false, 'Security verification required. Please reload the page and complete the security check.');
}
// ── End Turnstile gate ────────────────────────────────────────────────────────


// ── PDO user lookup ───────────────────────────────────────────────────────────
try {
    $stmt = $conn->prepare(
        "SELECT u.*,
                (SELECT COUNT(*) FROM itg_tbl WHERE id = u.id) AS is_itg_member
         FROM   users_cscro8 u
         WHERE  u.username = :username
         LIMIT  1"
    );
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[auth/login.php] DB lookup failed: ' . $e->getMessage());
    respond(false, 'A system error occurred. Please try again later.');
}

// ── User not found ────────────────────────────────────────────────────────────
// Deliberately vague — never reveal whether a username exists.
if (!$user) {
    respond(false, 'Invalid username or password.');
}

// ── Data integrity check ──────────────────────────────────────────────────────
if (empty($user['id']) || empty($user['username']) || empty($user['fname']) || empty($user['lname'])) {
    error_log('[auth/login.php] Incomplete user record for username: ' . $username);
    respond(false, 'A system error occurred. Please contact support.');
}

// ── Account status check (before expensive hash work) ────────────────────────
if (!empty($user['status']) && strtolower($user['status']) === 'inactive') {
    respond(false, 'ACCOUNT_INACTIVE');
}

// ── Password verification (server-side bcrypt) ────────────────────────────────
if (!password_verify($password, $user['password'])) {
    respond(false, 'Invalid username or password.');
}

// ── Superadmin role guard ─────────────────────────────────────────────────────
if ($loginMode === 'superadmin' && ($user['role'] ?? '') !== 'superadmin') {
    respond(false, 'UNAUTHORIZED');
}

// ── Session regeneration (prevents session fixation) ─────────────────────────
session_regenerate_id(true);

// ── Write session ─────────────────────────────────────────────────────────────
$name = trim(
    $user['fname'] . ' ' .
        (!empty($user['minitial']) ? $user['minitial'] . ' ' : '') .
        $user['lname']
);

$_SESSION['id']            = $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['name']          = $name;
$_SESSION['fname']         = $user['fname'];
$_SESSION['lname']         = $user['lname'];
$_SESSION['minitial']      = $user['minitial']  ?? '';
$_SESSION['fullname']      = $name;
$_SESSION['role']          = $user['role']      ?? '';
$_SESSION['email']         = $user['email']     ?? '';
$_SESSION['position']      = $user['position']  ?? '';
$_SESSION['profile']       = $user['profile']   ?? '';
$_SESSION['type']          = $user['type']      ?? '';
$_SESSION['fo_rsu']        = $user['fo_rsu']    ?? '';
$_SESSION['itg']           = $user['itg']       ?? '';
$_SESSION['user_group']    = $user['fo_rsu']    ?? '';
$_SESSION['login_user']    = $user['id'];
$_SESSION['is_itg_member'] = (int) ($user['is_itg_member'] ?? 0) > 0;
$_SESSION['login_success'] = true;
$_SESSION['created']       = time();

// ── Real IP (for the log) ─────────────────────────────────────────────────────
$userIp = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

if (str_contains($userIp, ',')) {
    $userIp = trim(explode(',', $userIp)[0]);
}

// ── Login log → imis_history_login_logs (non-blocking) ───────────────────────
// Also marks any previous active sessions for this user as logged_out (concurrent
// login prevention), then inserts a fresh active row and stores the new row's ID
// in $_SESSION['login_log_id'] so logout.php can update it precisely.
try {
    // 1. Close any dangling active sessions for this user
    $closeStmt = $conn->prepare(
        "UPDATE imis_history_login_logs
            SET status      = 'logged_out',
                logout_time = NOW()
          WHERE user_id = :uid
            AND status  = 'active'
            AND session_id != :sid"
    );
    $closeStmt->execute([
        ':uid' => $user['id'],
        ':sid' => session_id(),
    ]);

    // 2. Insert the new login record
    $logStmt = $conn->prepare(
        "INSERT INTO imis_history_login_logs
             (user_id, username, ip_address, login_time, user_agent, status, session_id)
         VALUES
             (:user_id, :username, :ip, NOW(), :ua, 'active', :session_id)"
    );
    $logStmt->execute([
        ':user_id'    => $user['id'],
        ':username'   => $user['username'],
        ':ip'         => $userIp ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ':ua'         => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ':session_id' => session_id(),
    ]);

    // 3. Store the row ID so logout.php can update it directly
    $_SESSION['login_log_id'] = (int) $conn->lastInsertId();
} catch (PDOException $e) {
    error_log('[auth/login.php] Login log insert failed: ' . $e->getMessage());
}

// ── Determine redirect URL ────────────────────────────────────────────────────
$redirect = ($loginMode === 'superadmin')
    ? '/admin/index_users_management'
    : '/dashboard';

// ── Success response ──────────────────────────────────────────────────────────
respond(true, 'Login successful.', [
    'redirect'   => $redirect,
    'name'       => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'login_mode' => $loginMode,
]);
