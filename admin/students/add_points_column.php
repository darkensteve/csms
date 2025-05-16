<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login_admin.php');
    exit();
}

// Check if the points column exists in the users table
$column_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'points'");
if ($column_check && $column_check->num_rows == 0) {
    // Add points column if it doesn't exist
    $add_column = $conn->query("ALTER TABLE `users` ADD COLUMN `points` INT NOT NULL DEFAULT 0");
    if ($add_column) {
        echo "<p>Points column added successfully to the users table.</p>";
    } else {
        echo "<p>Error adding points column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Points column already exists in the users table.</p>";
}

// Create points_log table if it doesn't exist
$table_check = $conn->query("SHOW TABLES LIKE 'points_log'");
if ($table_check->num_rows == 0) {
    // Create points_log table
    $create_table = $conn->query("CREATE TABLE `points_log` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` varchar(50) NOT NULL,
        `points_added` int(11) NOT NULL,
        `reason` text DEFAULT NULL,
        `admin_id` int(11) NOT NULL,
        `admin_username` varchar(100) NOT NULL,
        `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    if ($create_table) {
        echo "<p>Points log table created successfully.</p>";
    } else {
        echo "<p>Error creating points log table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Points log table already exists.</p>";
}

echo "<p><a href='student.php'>Return to Student Management</a></p>";
?> 