<?php

/**
 * backend/fetch/fetch_users.php
 * Returns all users for the User Management DataTable.
 *
 * Method: GET
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

try {
    $stmt = $conn->query(
        "SELECT
            id,
            fname,
            lname,
            mname,
            minitial,
            username,
            fo_rsu,
            position,
            status,
            role,
            profile
         FROM users_cscro8
         ORDER BY lname ASC, fname ASC"
    );

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$u) {
        $u['id'] = (int) $u['id'];
    }
    unset($u);

    json_success(['data' => $users]);
} catch (PDOException $e) {
    error_log('[fetch_users] ' . $e->getMessage());
    json_error('Failed to load users.', 500);
}
