<?php

/**
 * backend/api_guard.php
 *
 * Include this at the top of every backend endpoint.
 * Handles:
 *  - Session validation (superadmin only)
 *  - JSON response helpers
 *  - Shared PDO connection via $pdo
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/connect.php';   // Must provide $pdo (PDO instance)

header('Content-Type: application/json; charset=utf-8');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    json_error('Unauthorized.', 403);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Terminate with a JSON success response.
 *
 * @param  mixed       $data    Optional payload (array or null).
 * @param  string      $message Optional message.
 * @param  int         $status  HTTP status code.
 */
function json_success($data = null, string $message = 'OK', int $status = 200): void
{
    http_response_code($status);
    $body = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $body = array_merge($body, (array) $data);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Terminate with a JSON error response.
 *
 * @param  string  $message Human-readable error.
 * @param  int     $status  HTTP status code.
 */
function json_error(string $message = 'An error occurred.', int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Return a trimmed string from $_POST or $_GET, or $default if missing/empty.
 */
function input(string $key, string $default = '', string $source = 'POST'): string
{
    $bag = $source === 'GET' ? $_GET : $_POST;
    return isset($bag[$key]) ? trim((string) $bag[$key]) : $default;
}

/**
 * Return a sanitised integer from $_POST or $_GET, or 0 if missing/invalid.
 */
function input_int(string $key, int $default = 0, string $source = 'POST'): int
{
    $bag = $source === 'GET' ? $_GET : $_POST;
    return isset($bag[$key]) ? (int) $bag[$key] : $default;
}
