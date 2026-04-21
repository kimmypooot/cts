<?php
header("Content-Type: application/json");
include '../connect.php'; // Your database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST['nameEdit'] ?? '');
    $age = trim($_POST['ageEdit'] ?? '');
    $status = trim($_POST['statusEdit'] ?? '');
    $id = $_POST['id'];

    // Validate required fields
    if (empty($firstName) || empty($age) || empty($status)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE sample_table SET name = ?, age = ?, status = ? WHERE id = ?");
        $stmt->execute([$firstName, $age, $status, $id]);

        echo json_encode(["success" => true, "message" => "Data Updated!"]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
