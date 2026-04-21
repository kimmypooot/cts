<?php

/**
 * auth/captcha/refresh.php — CAPTCHA refresh endpoint
 * ─────────────────────────────────────────────────────────────────────────────
 * Generates a new 4-digit CAPTCHA, stores it in the PHP session,
 * and returns ONLY the display string to the client.
 *
 * The client never holds the "correct" value — it just displays what
 * the server sends. Validation always happens in auth/login.php against
 * $_SESSION['captcha'].
 *
 * Rate-limited to 10 refreshes per 60-second window (tracked in session).
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

// ── Session bootstrap ─────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── AJAX-only guard ───────────────────────────────────────────────────────────
$xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($xrw) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

// ── Session-based rate limiting ───────────────────────────────────────────────
const RATE_WINDOW   = 60;   // seconds
const MAX_REFRESHES = 10;   // per window

$now = time();

if (!isset($_SESSION['captcha_refreshes']) || !is_array($_SESSION['captcha_refreshes'])) {
    $_SESSION['captcha_refreshes'] = [];
}

// Evict entries outside the rolling window.
$_SESSION['captcha_refreshes'] = array_values(array_filter(
    $_SESSION['captcha_refreshes'],
    static fn(int $t): bool => ($now - $t) < RATE_WINDOW
));

if (count($_SESSION['captcha_refreshes']) >= MAX_REFRESHES) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many refresh requests. Please wait a moment.']);
    exit();
}

// ── Generate & store new CAPTCHA ──────────────────────────────────────────────
$_SESSION['captcha_refreshes'][] = $now;
$_SESSION['captcha'] = rand(1000, 9999);

// Return the display value — this is what the user needs to type in.
// Returning it here is intentional: the CAPTCHA is meant to be visible.
// Security comes from server-side validation in auth/login.php, not from hiding the value.
echo json_encode(['display' => (string) $_SESSION['captcha']]);
exit();
