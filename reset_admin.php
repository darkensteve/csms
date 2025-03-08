<?php
// This file resets the admin user with a known password
require_once 'config.php';

// Drop and recreate the admin table
$sql = "DROP TABLE IF EXISTS `admin`";
$conn->query($sql);

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
    echo "Admin table created successfully.<br>";
    
    // Create a known admin user with password "admin123"
    $default_username = 'admin';
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $default_email = 'admin@example.com';
    
    echo "Creating admin user with:<br>";
    echo "Username: $default_username<br>";
    echo "Email: $default_email<br>";
    echo "Password hash: $default_password<br>";
    
    $sql = "INSERT INTO `admin` (`username`, `password`, `email`) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $default_username, $default_password, $default_email);
    
    if($stmt->execute()) {
        echo "Admin user created successfully.<br>";
        echo "You can now log in with:<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error inserting admin user: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "Error creating table: " . $conn->error;
}

echo "<br><a href='login_admin.php'>Go to login page</a>";
$conn->close();
?>
