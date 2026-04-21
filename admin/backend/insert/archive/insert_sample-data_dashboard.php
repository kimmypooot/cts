<?php
header("Content-Type: application/json");
include '../connect.php'; // Your database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST['name'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Validate required fields
    if (empty($firstName) || empty($age) || empty($status)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO sample_table (name, age, status) VALUES (?, ?, ?)");
        $stmt->execute([$firstName, $age, $status]);

        echo json_encode(["success" => true, "message" => "data successfully created!"]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
