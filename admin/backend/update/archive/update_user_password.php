<?php
header("Content-Type: application/json");
require '../connect.php'; // Your database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get POST data
    $id = $_POST['id']; // assuming you include the ID when sending
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("UPDATE users_cscro8
                                SET password = ?
                                WHERE id = ?");
        $stmt->execute([$hashed_password, $id]);

        echo json_encode(["success" => true, "message" => "User updated successfully."]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
