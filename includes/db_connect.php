<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Check if essential tables exist and create them if needed
$essential_tables = ['labs', 'computers', 'reservations'];
$missing_tables = [];

foreach ($essential_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missing_tables[] = $table;
    }
}

// If any tables are missing, try to create them
if (!empty($missing_tables)) {
    if (file_exists(__DIR__ . '/../admin/setup/create_tables.php')) {
        include_once __DIR__ . '/../admin/setup/create_tables.php';
    }
}
?>
