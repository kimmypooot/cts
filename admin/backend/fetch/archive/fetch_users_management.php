<?php
require '../connect.php';

$sql = "SELECT CONCAT(u.fname, ' ', u.lname) as name, s.*, u.fo_rsu, u.position FROM system_access s INNER JOIN users_cscro8 u ON u.id = s.user";

$stmt = $conn->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["data" => $data]);
?>
