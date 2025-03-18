<?php
// Start the session to access session variables
session_start();

// Store logout success message in a temporary session variable
// This will be displayed on the login page after redirect
$_SESSION['temp_success_message'] = "You have been successfully logged out.";

// Clear all other session variables
$_SESSION = array('temp_success_message' => $_SESSION['temp_success_message']);

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
// We'll use the message=logout parameter for backward compatibility
header("Location: login_admin.php?message=logout");
exit();
?>
