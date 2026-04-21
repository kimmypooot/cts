<?php
require '../connect.php';
$id = $_GET['id'];
$stmt = $conn->prepare("
    SELECT * FROM users_cscro8 WHERE id = ?");

$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($data ? ['success' => true, 'data' => $data] : ['success' => false, 'error' => 'No record found']);
?>