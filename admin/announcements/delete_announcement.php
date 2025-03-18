<?php
session_start();

// Check if user is not logged in as admin
if(!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../auth/login_admin.php");
    exit;
}

// Check if ID parameter exists
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['announcement_error'] = "Missing announcement ID.";
    header("Location: ../admin.php");
    exit;
}

// Database connection
$db_host = "localhost";
$db_user = "root"; 
$db_pass = "";
$db_name = "csms";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Sanitize the ID
$announcement_id = mysqli_real_escape_string($conn, $_GET['id']);

// Delete the announcement
$query = "DELETE FROM announcements WHERE id = '$announcement_id'";

if (mysqli_query($conn, $query)) {
    $_SESSION['announcement_success'] = "Announcement deleted successfully!";
} else {
    $_SESSION['announcement_error'] = "Error deleting announcement: " . mysqli_error($conn);
}

mysqli_close($conn);

// Redirect back to admin page
header("Location: ../admin.php");
exit;
?>
