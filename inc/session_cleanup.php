<?php

/**
 * inc/session_cleanup.php
 *
 * Marks abandoned sessions (browser-closed / crashed) as timed out.
 * Can be run three ways:
 *
 *   1. CLI cron (recommended):
 *      * * * * * php /var/www/html/imis/inc/session_cleanup.php >> /var/log/imis_cleanup.log 2>&1
 *
 *   2. HTTP with secret key (for hosts without cron):
 *      GET /inc/session_cleanup.php?key=<CLEANUP_SECRET>
 *
 *   3. Probabilistically from includes/session.php (built-in fallback, no config needed)
 *
 * Logic:
 *   • Active rows where last_heartbeat stopped > 90 s ago → timed out
 *   • Active rows with no heartbeat older than SESSION_IDLE_TIMEOUT + 60 s → timed out
 */

declare(strict_types=1);

// ── Auth guard (HTTP mode only) ───────────────────────────────────────────────
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    // Hardcode a secret in your .env or define it in a config file.
    // This prevents the endpoint from being called by random visitors.
    $secret = getenv('IMIS_CLEANUP_SECRET') ?: 'change-me-in-production';
    if (($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// ── Bootstrap (DB connection + cleanupStaleSessions()) ───────────────────────
// We only need the DB and the cleanup function, not a full session.
require_once __DIR__ . '/../includes/connect.php';   // $conn
require_once __DIR__ . '/../config/session.php';     // cleanupStaleSessions()

// ── Run cleanup ───────────────────────────────────────────────────────────────
$start = microtime(true);
cleanupStaleSessions();
$elapsed = round((microtime(true) - $start) * 1000, 1);

$msg = sprintf(
    '[%s] session_cleanup.php completed in %s ms%s',
    date('Y-m-d H:i:s'),
    $elapsed,
    PHP_EOL
);

if ($isCli) {
    echo $msg;
} else {
    header('Content-Type: text/plain');
    echo $msg;
}
