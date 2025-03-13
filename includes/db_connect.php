<?php
// Set timezone for PHP
date_default_timezone_set('Asia/Manila');

// Database connection parameters
$host = 'localhost';     // Usually localhost
$username = 'root';      // Default XAMPP username
$password = '';          // Default XAMPP password is empty
$database = 'csms';     // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone for this MySQL session
$conn->query("SET time_zone = '+08:00'");

// Set charset to ensure proper handling of special characters
$conn->set_charset("utf8mb4");
?>
