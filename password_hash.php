<?php
session_start();

include 'connect.php';

try {
    $conn = new PDO('mysql:host='.$servername.';dbname='.$dbname, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the user records from the database
    $stmt = $conn->query("SELECT id, password FROM users_seis");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $id = $user['id'];
        $password = $user['password'];

        // Check if the password is already in the password_hash format
        if (!password_verify('', $password)) {
            // Convert the MD5 hashed password into password_hash format
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update the password value in the database
            $updateStmt = $conn->prepare("UPDATE users_seis SET password = :password WHERE id = :id");
            $updateStmt->bindParam(':password', $hashed_password);
            $updateStmt->bindParam(':id', $id);
            $updateStmt->execute();
        }
    }

    echo 'Password conversion completed successfully.';
} catch (PDOException $error) {
    echo 'Error: '.$error->getMessage();
}
?>