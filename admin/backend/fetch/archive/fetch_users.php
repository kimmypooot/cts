<?php
require '../connect.php'; // Ensure this points to your DB connection

// Fetch all records from the "data" table
$query = $conn->query("SELECT * FROM users_cscro8");
$data = $query->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
echo json_encode(["data" => $data]);
?>
