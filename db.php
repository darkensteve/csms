<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "csms";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add charset setting to maintain consistency with db_connection.php
$conn->set_charset("utf8mb4");
?>