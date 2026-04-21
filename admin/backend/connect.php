<?php

$servername = 'localhost';
$username = 'u390694310_cscro8';
$password = 'civilService@ro08';
$dbname = 'u390694310_cscro8';


// Create a PDO connection
try {
  $conn = new PDO('mysql:host='.$servername.';dbname='.$dbname.';charset=utf8mb4', $username, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Connection failed: " . $e->getMessage());
}
?>
