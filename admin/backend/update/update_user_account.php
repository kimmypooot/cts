<?php

/**
 * backend/update/update_user_account.php
 * Updates a user's IMIS role and account status (Account tab).
 *
 * Method: POST
 * Fields: id (int), role (string), status ('Active'|'Inactive')
 *
 * Business rule: if status = 'Inactive', role is forced to 'none'.
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$id     = input_int('id');
$role   = input('role');
$status = input('status');

$allowedRoles    = ['superadmin', 'admin', 'user', 'none'];
$allowedStatuses = ['Active', 'Inactive'];

if ($id <= 0)                                       json_error('Invalid user ID.');
if (!in_array($role, $allowedRoles, true))          json_error('Invalid role.');
if (!in_array($status, $allowedStatuses, true))     json_error('Invalid status.');

// Enforce business rule
if ($status === 'Inactive') {
    $role = 'none';
}

try {
    $chk = $conn->prepare("SELECT id FROM users_cscro8 WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) json_error('User not found.', 404);

    $stmt = $conn->prepare(
        "UPDATE users_cscro8
         SET role   = :role,
             status = :status
         WHERE id   = :id"
    );
    $stmt->execute([':role' => $role, ':status' => $status, ':id' => $id]);

    json_success(null, 'Account settings updated successfully.');
} catch (PDOException $e) {
    error_log('[update_user_account] ' . $e->getMessage());
    json_error('Failed to update account settings.', 500);
}
