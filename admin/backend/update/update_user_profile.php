<?php

/**
 * backend/update/update_user_profile.php
 * Updates a user's personal information (Profile tab in the offcanvas).
 * Does NOT touch role, status, or password.
 *
 * Method: POST
 * Fields: id, fname, lname, mname, email, sex, birthday, type, position
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

$id       = input_int('id');
$fname    = input('fname');
$lname    = input('lname');
$mname    = input('mname');
$email    = filter_var(input('email'), FILTER_VALIDATE_EMAIL);
$sex      = input('sex');
$birthday = input('birthday');
$type     = input('type');
$position = input('position');

$allowedTypes = ['ord', 'esd', 'msd', 'hrd', 'pald', 'psed', 'lsd', 'lfoi', 'lfoii', 'esfo', 'sfo', 'bfo', 'slfo', 'nsfo', 'wlso'];

// ── Validate ──────────────────────────────────────────────────────────────────
if ($id <= 0)                                   json_error('Invalid user ID.');
if (!$fname || !$lname)                          json_error('First name and last name are required.');
if (!$email)                                     json_error('Invalid email address.');
if (!$birthday || !strtotime($birthday))         json_error('Invalid birthday.');
if (!in_array($type, $allowedTypes, true))       json_error('Invalid division/office.');
if (!$position)                                  json_error('Position is required.');

$divisionMap = [
    'ord'   => 'Office of the Regional Director',
    'esd'   => 'Examination Services Division',
    'msd'   => 'Management Services Division',
    'hrd'   => 'Human Resource Division',
    'pald'  => 'Public Assistance and Liaison Division',
    'psed'  => 'Policies and Systems Evaluation Division',
    'lsd'   => 'Legal Services Division',
    'lfoi'  => 'CSC Field Office - Leyte I',
    'lfoii' => 'CSC Field Office - Leyte II',
    'esfo'  => 'CSC Field Office - Eastern Samar',
    'sfo'   => 'CSC Field Office - Samar',
    'bfo'   => 'CSC Field Office - Biliran',
    'slfo'  => 'CSC Field Office - Southern Leyte',
    'nsfo'  => 'CSC Field Office - Northern Samar',
    'wlso'  => 'CSC Satellite Office - Western Leyte',
];
$foRsu    = $divisionMap[$type];
$minitial = $mname ? strtoupper(mb_substr($mname, 0, 1)) . '.' : '';

try {
    // Verify user exists
    $chk = $conn->prepare("SELECT id FROM users_cscro8 WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) json_error('User not found.', 404);

    // Check email uniqueness (allow same user to keep their email)
    $emailChk = $conn->prepare("SELECT id FROM users_cscro8 WHERE email = ? AND id != ? LIMIT 1");
    $emailChk->execute([$email, $id]);
    if ($emailChk->fetch()) json_error('That email is already used by another account.');

    $stmt = $conn->prepare(
        "UPDATE users_cscro8
         SET fname    = :fname,
             lname    = :lname,
             mname    = :mname,
             minitial = :minitial,
             sex      = :sex,
             email    = :email,
             position = :position,
             fo_rsu   = :fo_rsu,
             type     = :type,
             birthday = :birthday
         WHERE id = :id"
    );
    $stmt->execute([
        ':fname'    => $fname,
        ':lname'    => $lname,
        ':mname'    => $mname,
        ':minitial' => $minitial,
        ':sex'      => $sex,
        ':email'    => $email,
        ':position' => $position,
        ':fo_rsu'   => $foRsu,
        ':type'     => $type,
        ':birthday' => $birthday,
        ':id'       => $id,
    ]);

    json_success(null, 'Profile updated successfully.');
} catch (PDOException $e) {
    error_log('[update_user_profile] ' . $e->getMessage());
    json_error('Failed to update profile.', 500);
}
