<?php
// imis/includes/connect.php

// ── Timezone (ROOT FIX) ───────────────────────────────────────────────────────
// Set once here so every file that does require_once 'connect.php' automatically
// inherits Asia/Manila for all PHP date/time functions.
date_default_timezone_set('Asia/Manila');

// ── Credentials ───────────────────────────────────────────────────────────────
$servername = 'localhost';
$username = 'u390694310_cscro8';
$password = 'civilService@ro08';
$dbname = 'u390694310_cscro8';

// ── Connection ────────────────────────────────────────────────────────────────
try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // ── MySQL session timezone (ROOT FIX) ─────────────────────────────────────
    // Aligns MySQL's NOW() / CURRENT_TIMESTAMP with PHP's Asia/Manila (UTC+8).
    // Without this line, any SQL that calls NOW() stores timestamps in UTC,
    // which is why login_time was always recorded 8 hours behind local time.
    $conn->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());

    // Return a clean JSON error for AJAX callers instead of a bare die().
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Database unavailable']);
        exit();
    }

    die('Database connection failed.');
}
