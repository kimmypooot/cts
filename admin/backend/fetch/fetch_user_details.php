<?php

/**
 * backend/fetch/fetch_user_details.php
 * Returns the full profile of a single user for the edit offcanvas.
 *
 * Method: GET
 * Params: id (int, required)
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$userId = input_int('id', 0, 'GET');
if ($userId <= 0) {
    json_error('Invalid or missing user id.');
}

try {
    $stmt = $conn->prepare(
        "SELECT
            id,
            fname,
            lname,
            mname,
            minitial,
            sex,
            username,
            email,
            position,
            fo_rsu,
            type,
            birthday,
            status,
            role,
            profile
         FROM users_cscro8
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_error('User not found.', 404);
    }

    $user['id'] = (int) $user['id'];

    json_success(['data' => $user]);
} catch (PDOException $e) {
    error_log('[fetch_user_details] ' . $e->getMessage());
    json_error('Failed to load user details.', 500);
}
