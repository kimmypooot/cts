<?php

/**
 * imis/includes/connect.php
 *
 * Database connection
 * ───────────────────
 * Reads credentials from environment (via env_loader.php).
 * Bootstraps env_loader.php automatically if env() is not yet available.
 *
 * Hardened PDO options:
 *  - ERRMODE_EXCEPTION   : throws PDOException on DB errors
 *  - EMULATE_PREPARES=0  : real prepared statements (SQL injection prevention)
 *  - FETCH_ASSOC default : consistent result format
 *  - PERSISTENT=false    : no persistent connections (avoids stale state)
 */

declare(strict_types=1);

if (defined('IMIS_CONNECT_LOADED')) {
    return;
}
define('IMIS_CONNECT_LOADED', true);

// ── Ensure env() is available ────────────────────────────────────────────────
if (!function_exists('env')) {
    require_once __DIR__ . '/env_loader.php';
}

// ── Build DSN from environment ───────────────────────────────────────────────
$__host    = (string) env('DB_HOST',     'localhost');
$__port    = (string) env('DB_PORT',     '3306');
$__dbname  = (string) env('DB_NAME',     'u390694310_cscro8');
$__user    = (string) env('DB_USER',     'u390694310_cscro8');
$__pass    = (string) env('DB_PASSWORD', 'civilService@ro08');
$__charset = (string) env('DB_CHARSET',  'utf8mb4');

try {
    $conn = new PDO(
        "mysql:host={$__host};port={$__port};dbname={$__dbname};charset={$__charset}",
        $__user,
        $__pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]
    );
} catch (PDOException $e) {
    // Log the real error; never expose credentials or DB internals to the browser
    error_log('[IMIS connect.php] DB Connection Error: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 60');
    }
    // Simple, information-free error page
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<title>Service Unavailable</title></head><body>'
        . '<h1>503 — Service Temporarily Unavailable</h1>'
        . '<p>We are experiencing a technical issue. Please try again in a moment.</p>'
        . '</body></html>';
    exit();
} finally {
    // Scrub credential variables from memory immediately
    unset($__host, $__port, $__dbname, $__user, $__pass, $__charset);
}
