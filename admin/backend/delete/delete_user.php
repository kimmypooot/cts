<?php

/**
 * backend/delete/delete_user.php
 * Permanently deletes a user account.
 *
 * Method: POST
 * Fields: id (int)
 *
 * Cascade: imis_system_access rows are removed automatically via FK ON DELETE CASCADE.
 * Guard:   Cannot delete the currently logged-in superadmin account.
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$id = input_int('id');

if ($id <= 0) {
    json_error('Invalid user ID.');
}

// Prevent self-deletion
try {
    $selfStmt = $conn->prepare("SELECT id FROM users_cscro8 WHERE username = ? LIMIT 1");
    $selfStmt->execute([$_SESSION['username']]);
    $self = $selfStmt->fetch(PDO::FETCH_ASSOC);

    if ($self && (int) $self['id'] === $id) {
        json_error('You cannot delete your own account.');
    }
} catch (PDOException $e) {
    error_log('[delete_user] self-check: ' . $e->getMessage());
    json_error('Server error.', 500);
}

try {
    // Fetch profile filename before deletion so we can clean up the file
    $profileStmt = $conn->prepare("SELECT profile FROM users_cscro8 WHERE id = ? LIMIT 1");
    $profileStmt->execute([$id]);
    $row = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('User not found.', 404);
    }

    $profileFile = $row['profile'];

    // Delete user — FK CASCADE handles imis_system_access automatically
    $del = $conn->prepare("DELETE FROM users_cscro8 WHERE id = ?");
    $del->execute([$id]);

    if ($del->rowCount() === 0) {
        json_error('User could not be deleted.');
    }

    // Remove profile photo (silent failure — file may already be missing)
    if ($profileFile) {
        $uploadDir = __DIR__ . '/../../uploads/';
        $filePath  = $uploadDir . $profileFile;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    json_success(null, 'User deleted successfully.');
} catch (PDOException $e) {
    error_log('[delete_user] ' . $e->getMessage());
    json_error('Failed to delete user.', 500);
}
