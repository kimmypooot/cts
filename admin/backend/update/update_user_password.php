<?php

/**
 * backend/update/update_user_password.php
 * Sets a new bcrypt-hashed password for a user (Security tab).
 *
 * Method: POST
 * Fields: id (int), password (string, min 8 chars)
 *
 * NOTE: The plain-text password is hashed server-side and never logged.
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$id       = input_int('id');
$password = input('password');

if ($id <= 0) {
    json_error('Invalid user ID.');
}
if (strlen($password) < 8) {
    json_error('Password must be at least 8 characters long.');
}

try {
    $chk = $conn->prepare("SELECT id FROM users_cscro8 WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        json_error('User not found.', 404);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users_cscro8 SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $id]);

    json_success(null, 'Password updated successfully.');
} catch (PDOException $e) {
    error_log('[update_user_password] ' . $e->getMessage());
    json_error('Failed to update password.', 500);
}
