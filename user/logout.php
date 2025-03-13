<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with a logout message
header("Location: index.php?message=logout");
exit();
?>
