<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sitin_db';

// First, connect without selecting a database
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists, if not create it
$dbcheck = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
if ($dbcheck->num_rows == 0) {
    // Create the database
    $sql = "CREATE DATABASE $db_name";
    if ($conn->query($sql) === TRUE) {
        // Database created successfully
    } else {
        die("Error creating database: " . $conn->error);
    }
}

// Select the database
$conn->select_db($db_name);

// Check if admin table exists
$tablecheck = $conn->query("SHOW TABLES LIKE 'admin'");
if ($tablecheck->num_rows == 0) {
    // Create admin table
    $sql = "CREATE TABLE `admin` (
        `admin_id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `email` varchar(100) NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`admin_id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        // Table created successfully
        
        // Insert default admin user (password: admin123)
        $default_username = 'admin';
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $default_email = 'admin@example.com';
        
        $sql = "INSERT INTO `admin` (`username`, `password`, `email`) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $default_username, $default_password, $default_email);
        $stmt->execute();
        $stmt->close();
    } else {
        die("Error creating table: " . $conn->error);
    }
}
?>
