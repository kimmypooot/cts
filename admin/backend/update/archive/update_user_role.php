<?php
header("Content-Type: application/json");
require '../connect.php'; // Your database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get POST data
    $id = $_POST['id']; // assuming you include the ID when sending
    $status = $_POST['status'];
    $role = $_POST['role'];

    try {
        $stmt = $conn->prepare("UPDATE users_cscro8
                                SET role = ?, status = ?
                                WHERE id = ?");
        $stmt->execute([$role, $status, $id]);

        echo json_encode(["success" => true, "message" => "User updated successfully."]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
