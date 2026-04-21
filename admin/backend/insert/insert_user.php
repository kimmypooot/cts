<?php

/**
 * backend/insert/insert_user.php
 * Creates a new user account.
 *
 * Method: POST (multipart/form-data)
 * Fields: fname, lname, mname, email, sex, birthday, type, position, role, profilePic (file, optional)
 *
 * Auto-generates:
 *   - username  : first_initial(fname) + first_initial(mname) + lowercase(lname)
 *                 Appended with a numeric suffix if the username already exists.
 *   - password  : bcrypt hash of birthday in MMDDYYYY format (user must change on first login)
 *   - minitial  : first letter of mname + '.'
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

// ── Collect and validate input ────────────────────────────────────────────────
$fname    = input('fname');
$lname    = input('lname');
$mname    = input('mname');
$email    = filter_var(input('email'), FILTER_VALIDATE_EMAIL);
$sex      = input('sex');
$birthday = input('birthday');
$type     = input('type');
$position = input('position');
$role     = input('role');

$allowedRoles = ['superadmin', 'admin', 'user'];
$allowedTypes = ['ord', 'esd', 'msd', 'hrd', 'pald', 'psed', 'lsd', 'lfoi', 'lfoii', 'esfo', 'sfo', 'bfo', 'slfo', 'nsfo', 'wlso'];

if (!$fname || !$lname || !$email || !$birthday || !$type || !$position || !$role) {
    json_error('All required fields must be filled in.');
}
if (!$email) {
    json_error('Invalid email address.');
}
if (!in_array($role, $allowedRoles, true)) {
    json_error('Invalid role specified.');
}
if (!in_array($type, $allowedTypes, true)) {
    json_error('Invalid division/office specified.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || !strtotime($birthday)) {
    json_error('Invalid birthday format. Expected YYYY-MM-DD.');
}

// ── Derive computed fields ────────────────────────────────────────────────────
$minitial = $mname ? strtoupper(mb_substr($mname, 0, 1)) . '.' : '';

// Username: first letter of fname (strip spaces first) + first letter of mname + lname
$fnameClean = preg_replace('/\s+/', '', $fname);
$baseUsername = strtolower(
    mb_substr($fnameClean, 0, 1) .
        ($mname ? mb_substr(preg_replace('/\s+/', '', $mname), 0, 1) : '') .
        preg_replace('/\s+/', '', $lname)
);

// ── Derive fo_rsu label from type ─────────────────────────────────────────────
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
$foRsu = $divisionMap[$type] ?? $type;

try {
    // ── Check email uniqueness ────────────────────────────────────────────────
    $emailChk = $conn->prepare("SELECT id FROM users_cscro8 WHERE email = ? LIMIT 1");
    $emailChk->execute([$email]);
    if ($emailChk->fetch()) {
        json_error('A user with that email already exists.');
    }

    // ── Resolve unique username ───────────────────────────────────────────────
    $username = $baseUsername;
    $suffix   = 1;
    $uniqChk  = $conn->prepare("SELECT id FROM users_cscro8 WHERE username = ? LIMIT 1");
    while (true) {
        $uniqChk->execute([$username]);
        if (!$uniqChk->fetch()) break;
        $username = $baseUsername . $suffix;
        $suffix++;
    }

    // ── Hash default password (birthday MMDDYYYY) ─────────────────────────────
    $bdate           = new DateTime($birthday);
    $defaultPassword = $bdate->format('m') . $bdate->format('d') . $bdate->format('Y');
    $hashedPassword  = password_hash($defaultPassword, PASSWORD_BCRYPT);

    // ── Handle optional profile picture ──────────────────────────────────────
    $profileFilename = '';
    if (!empty($_FILES['profilePic']['tmp_name'])) {
        $file     = $_FILES['profilePic'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowed, true)) {
            json_error('Invalid file type. Only JPG, PNG, GIF and WEBP are allowed.');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            json_error('Profile picture must be 2 MB or smaller.');
        }

        $ext             = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Placeholder filename — we will rename after we get the new user ID
        $tempFilename    = 'temp_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        $uploadDir       = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $tempFilename)) {
            json_error('Failed to save profile picture.');
        }
    }

    // ── Insert user ───────────────────────────────────────────────────────────
    $insert = $conn->prepare(
        "INSERT INTO users_cscro8
            (fname, lname, mname, minitial, sex, username, password, email,
             position, fo_rsu, type, birthday, status, role, profile, first_day, exam_fo_id, itg_id)
         VALUES
            (:fname, :lname, :mname, :minitial, :sex, :username, :password, :email,
             :position, :fo_rsu, :type, :birthday, 'Active', :role, '', '0000-00-00', 0, 0)"
    );
    $insert->execute([
        ':fname'    => $fname,
        ':lname'    => $lname,
        ':mname'    => $mname,
        ':minitial' => $minitial,
        ':sex'      => $sex,
        ':username' => $username,
        ':password' => $hashedPassword,
        ':email'    => $email,
        ':position' => $position,
        ':fo_rsu'   => $foRsu,
        ':type'     => $type,
        ':birthday' => $birthday,
        ':role'     => $role,
    ]);

    $newId = (int) $conn->lastInsertId();

    // ── Rename photo with real user ID ────────────────────────────────────────
    if (!empty($tempFilename)) {
        $ext             = pathinfo($tempFilename, PATHINFO_EXTENSION);
        $profileFilename = "user_{$newId}_" . bin2hex(random_bytes(5)) . '.' . $ext;
        rename($uploadDir . $tempFilename, $uploadDir . $profileFilename);

        $conn->prepare("UPDATE users_cscro8 SET profile = ? WHERE id = ?")
            ->execute([$profileFilename, $newId]);
    }

    json_success(
        ['user_id' => $newId, 'username' => $username],
        "User '{$username}' created successfully. Default password is their birthday (MMDDYYYY)."
    );
} catch (PDOException $e) {
    error_log('[insert_user] ' . $e->getMessage());
    // Clean up orphaned temp file if DB failed
    if (!empty($tempFilename) && file_exists($uploadDir . $tempFilename)) {
        unlink($uploadDir . $tempFilename);
    }
    json_error('Failed to create user.', 500);
}
