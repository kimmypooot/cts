<?php

/**
 * backend/update/update_user_photo.php
 * Replaces a user's profile photo.
 *
 * Method: POST (multipart/form-data)
 * Fields: id (int), profilePic (file — image/jpeg, image/png, image/gif, image/webp; max 2 MB)
 *
 * Saves to:  admin/uploads/user_{id}_{random}.{ext}
 * Returns:   { success, message, filename }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$id = input_int('id');

if ($id <= 0) {
    json_error('Invalid user ID.');
}
if (empty($_FILES['profilePic']['tmp_name'])) {
    json_error('No file was uploaded.');
}

$file         = $_FILES['profilePic'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxBytes     = 2 * 1024 * 1024; // 2 MB

// MIME check using fileinfo (never trust $_FILES['type'])
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMimes, true)) {
    json_error('Invalid file type. Allowed: JPG, PNG, GIF, WEBP.');
}
if ($file['size'] > $maxBytes) {
    json_error('File is too large. Maximum size is 2 MB.');
}

$uploadDir = __DIR__ . '/../../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // Fetch current profile filename so we can delete it after a successful upload
    $chk = $conn->prepare("SELECT profile FROM users_cscro8 WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('User not found.', 404);
    }

    $oldProfile = $row['profile'];

    // Build a safe filename
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFilename = "user_{$id}_" . bin2hex(random_bytes(7)) . '.' . $ext;
    $destination = $uploadDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_error('Failed to move uploaded file.', 500);
    }

    // Update the DB
    $upd = $conn->prepare("UPDATE users_cscro8 SET profile = ? WHERE id = ?");
    $upd->execute([$newFilename, $id]);

    // Remove old file (silent — don't fail the request if deletion fails)
    if ($oldProfile && $oldProfile !== $newFilename) {
        $oldPath = $uploadDir . $oldProfile;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    json_success(['filename' => $newFilename], 'Profile photo updated successfully.');
} catch (PDOException $e) {
    error_log('[update_user_photo] ' . $e->getMessage());
    // Remove newly uploaded file if DB update failed
    if (!empty($destination) && is_file($destination)) {
        @unlink($destination);
    }
    json_error('Failed to update profile photo.', 500);
}
