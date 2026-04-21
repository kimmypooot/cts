<?php
session_start(); // Start session

if (!isset($_SESSION['username'])) { // Check if session 'id' is NOT set
    header('Location: login'); // Redirect to login page
    exit(); // Stop further execution
}
// else {
//     header('Location: admin/dashboard.php');
// }
?>