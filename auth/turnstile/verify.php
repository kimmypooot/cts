<?php

/**
 * auth/turnstile/verify.php — Cloudflare Turnstile Server-Side Verifier
 * ─────────────────────────────────────────────────────────────────────────────
 * Called IMMEDIATELY by onTurnstileSuccess() in login.php the moment the
 * widget resolves — while the token is still fresh and unused.
 *
 * Why a separate endpoint?
 *   Turnstile tokens are one-time-use and expire quickly (~5 min, but
 *   Cloudflare can revoke them sooner). Verifying at login-submit time risks
 *   hitting an already-spent or expired token, especially when the user
 *   takes a moment to fill out the form or a failed attempt already consumed
 *   the token. Verifying here — the instant the challenge resolves — and
 *   caching the result in $_SESSION['turnstile_verified'] eliminates that race.
 *
 * Flow:
 *   1. onTurnstileSuccess(token) fires in the browser
 *   2. Browser POSTs the token to this endpoint
 *   3. We hit Cloudflare's siteverify API (token is brand-new at this point)
 *   4. On success → $_SESSION['turnstile_verified'] = true
 *   5. auth/login.php only checks the session flag — never re-verifies
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ── Only accept POST from our own JS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($xrw) !== 'xmlhttprequest') {
    http_response_code(400);
    exit();
}

// ── Response headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ── Session bootstrap (must match login.php exactly) ─────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Config ────────────────────────────────────────────────────────────────────
// Replace with your real secret from dash.cloudflare.com → Turnstile → Secret Key
// Test secrets (never in production):
//   Always passes  : 1x0000000000000000000000000000000AA
//   Always blocks  : 2x0000000000000000000000000000000AA
define('TURNSTILE_SECRET_KEY',  '0x4AAAAAACxEHmPhFj-vO2ShLMgP44GXb3o'); // ← REPLACE
define('TURNSTILE_VERIFY_URL',  'https://challenges.cloudflare.com/turnstile/v0/siteverify');

// ── Helper: JSON respond + exit ───────────────────────────────────────────────
function respond(bool $success, string $message = ''): never
{
    $payload = ['success' => $success];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    echo json_encode($payload);
    exit();
}

// ── If already verified in this session, skip the network call ────────────────
// (Guards against replays if the user somehow hits this endpoint twice.)
if (!empty($_SESSION['turnstile_verified'])) {
    respond(true);
}

// ── Collect and validate the token ───────────────────────────────────────────
$token = trim($_POST['token'] ?? '');
if ($token === '') {
    error_log('[turnstile/verify.php] Empty token received.');
    respond(false, 'No token provided.');
}

// ── Real IP: honour Cloudflare's forwarding header ────────────────────────────
$userIp = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

if (str_contains($userIp, ',')) {
    $userIp = trim(explode(',', $userIp)[0]);
}

// ── cURL verification ─────────────────────────────────────────────────────────
$payload = ['secret' => TURNSTILE_SECRET_KEY, 'response' => $token];
if ($userIp !== '') {
    $payload['remoteip'] = $userIp;
}

$ch = curl_init(TURNSTILE_VERIFY_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);

$raw      = curl_exec($ch);
$curlErr  = curl_errno($ch);
curl_close($ch);

if ($curlErr || $raw === false) {
    // Network failure contacting Cloudflare — fail closed, log for ops.
    error_log('[turnstile/verify.php] cURL error ' . $curlErr . ': ' . curl_strerror($curlErr));
    respond(false, 'Could not reach the verification service. Please try again.');
}

$data = json_decode($raw, true);

if (!empty($data['error-codes'])) {
    error_log('[turnstile/verify.php] Cloudflare error-codes: ' . implode(', ', $data['error-codes']));
}

// ── Persist result and respond ────────────────────────────────────────────────
if (isset($data['success']) && $data['success'] === true) {
    $_SESSION['turnstile_verified'] = true;
    respond(true);
} else {
    // Token invalid, expired, or already used — browser will re-challenge.
    respond(false, 'Verification failed. Please complete the security check again.');
}
