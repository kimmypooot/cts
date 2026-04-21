<?php
// Start the session
session_start();

// Clear the session variables
$_SESSION = array();
unset($_SESSION['id']);
unset($_SESSION['username']); 
unset($_SESSION['role']);
unset($_SESSION['name']);
unset($_SESSION['type']);

session_unset();

// Destroy the session
session_destroy();

// Redirect the user to the login page
header("Location: ../../index");

exit;
?>