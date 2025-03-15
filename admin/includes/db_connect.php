<?php
// Database connection settings
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "csms";

// Set timezone to Philippine time (GMT+8)
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone for MySQL connection to ensure all timestamps are in GMT+8 (Manila/Asia)
$conn->query("SET time_zone = '+08:00'");
?>
