<?php
$servername = "localhost";
$username = "u390694310_cscro8";
$password = "civilService@ro08";
$db_name = "u390694310_cscro8";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>