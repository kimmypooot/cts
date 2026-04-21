<?php
include '../connect.php';

$data = json_decode($_POST['updates'], true);
$system = $_POST['system']; // dynamic column

foreach ($data as $entry) {
    $stmt = $conn->prepare("UPDATE system_access SET `$system` = :role WHERE id = :employee");
    $stmt->execute([
        ':role' => $entry['role'],
        ':employee' => $entry['employee']
    ]);
}

echo json_encode(['success' => true]);
?>