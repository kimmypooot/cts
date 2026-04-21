<?php
require '../connect.php';

$response = ['success' => false, 'message' => 'Something went wrong.'];

try {
    $conn->beginTransaction();

    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $mname = $_POST['mname'];
    $minitial = strtoupper($mname[0]);
    $email = $_POST['email'];
    $position = $_POST['position'];
    $fo_rsu = $_POST['division'];
    $birthday = $_POST['birthdate'];
    $status = "Active";
    $type = $_POST['type'];
    $role = $_POST['role'];
    $username = strstr($email, '@', true);
    $password = '12345';

    // Handle image upload
    $photoPath = '';
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
        $targetDir = "../../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $ext = pathinfo($_FILES['profilePic']['name'], PATHINFO_EXTENSION);
        $uniqueFileName = uniqid() . "." . $ext;
        $targetFile = $targetDir . $uniqueFileName;

        if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $targetFile)) {
            $photoPath = $uniqueFileName;
        } else {
            throw new Exception("Failed to upload photo.");
        }
    }

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users_cscro8 
        (fname, lname, mname, minitial, username, password, email, position, fo_rsu, birthday, status, type, role, profile) 
        VALUES 
        (:fname, :lname, :mname, :minitial, :username, :password, :email, :position, :fo_rsu, :birthday, :status, :type, :role, :profile)");

    $stmt->execute([
        ':fname' => $fname,
        ':lname' => $lname,
        ':mname' => $mname,
        ':minitial' => $minitial,
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':email' => $email,
        ':position' => $position,
        ':fo_rsu' => $fo_rsu,
        ':birthday' => $birthday,
        ':status' => $status,
        ':type' => $type,
        ':role' => $role,
        ':profile' => $photoPath
    ]);

    $userId = $conn->lastInsertId();

    // Insert default system access
    $stmt = $conn->prepare("INSERT INTO system_access 
        (user, otrs, eris, ors, cdl, iis, rfcs, dvs, cts, lms) 
        VALUES 
        (?, 'None', 'None', 'None', 'None', 'None', 'None', 'None', 'None', 'None')");
    $stmt->execute([$userId]);

    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'User added successfully!';
} catch (Exception $e) {
    $conn->rollBack();
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
