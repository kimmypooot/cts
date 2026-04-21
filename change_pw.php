<?php
session_start();
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['currentPassword']) && isset($_POST['newPassword']) && isset($_POST['confirmPassword'])) {
        // Get the current password, new password, and confirm password from the form
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];

        // Check if the new password and confirm password match
        if ($newPassword === $confirmPassword) {
            // Retrieve the user's username from the session
            $username = $_SESSION['username'];

            // Retrieve the user's hashed password from the database
            $stmt = $conn->prepare("SELECT password FROM users_cscro8 WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $hashedPassword = $row['password'];

            // Verify if the current password matches the hashed password
            if (password_verify($currentPassword, $hashedPassword)) {
                // Generate a new hashed password
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the user's password in the database
                $stmt = $conn->prepare("UPDATE users_cscro8 SET password = :newPassword WHERE username = :username");
                $stmt->bindParam(':newPassword', $newHashedPassword);
                $stmt->bindParam(':username', $username);
                $stmt->execute();

                // Display a success message
                echo json_encode(array('success' => true, 'message' => 'Password successfully changed.'));
                exit;
            } else {
                // Display an error message if the current password is incorrect
                echo json_encode(array('success' => false, 'message' => 'Current password is incorrect.'));
                exit;
            }
        } else {
            // Display an error message if the new password and confirm password don't match
            echo json_encode(array('success' => false, 'message' => 'New password and confirm password do not match.'));
            exit;
        }
    } else {
        // Display an error message if any of the fields are missing
        echo json_encode(array('success' => false, 'message' => 'Please fill in all fields.'));
        exit;
    }
}
?>
