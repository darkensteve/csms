<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

echo "<h1>Points Log Table Repair Tool</h1>";

// Check if points_log table exists
$table_check = $conn->query("SHOW TABLES LIKE 'points_log'");
if ($table_check->num_rows == 0) {
    echo "<p>Points log table doesn't exist. Creating it now...</p>";
    
    // Create points_log table with all required columns
    $create_table = $conn->query("CREATE TABLE `points_log` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` varchar(50) NOT NULL,
        `points_added` int(11) NOT NULL,
        `reason` text DEFAULT NULL,
        `admin_id` int(11) NOT NULL,
        `admin_username` varchar(100) NOT NULL DEFAULT 'admin',
        `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    if ($create_table) {
        echo "<p style='color: green;'>Points log table created successfully.</p>";
    } else {
        echo "<p style='color: red;'>Error creating points log table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Points log table exists. Checking columns...</p>";
    
    // Check if admin_username column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `points_log` LIKE 'admin_username'");
    if ($column_check && $column_check->num_rows == 0) {
        echo "<p>Adding missing admin_username column...</p>";
        $add_column = $conn->query("ALTER TABLE `points_log` ADD COLUMN `admin_username` varchar(100) NOT NULL DEFAULT 'admin' AFTER `admin_id`");
        if ($add_column) {
            echo "<p style='color: green;'>admin_username column added successfully.</p>";
        } else {
            echo "<p style='color: red;'>Error adding admin_username column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>admin_username column exists.</p>";
    }
    
    // Check if added_at column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `points_log` LIKE 'added_at'");
    if ($column_check && $column_check->num_rows == 0) {
        echo "<p>Adding missing added_at column...</p>";
        $add_column = $conn->query("ALTER TABLE `points_log` ADD COLUMN `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
        if ($add_column) {
            echo "<p style='color: green;'>added_at column added successfully.</p>";
        } else {
            echo "<p style='color: red;'>Error adding added_at column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>added_at column exists.</p>";
    }
}

// Show table structure
echo "<h2>Current Points Log Table Structure:</h2>";
$columns = $conn->query("SHOW COLUMNS FROM `points_log`");
if ($columns && $columns->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($column = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error retrieving table structure: " . $conn->error . "</p>";
}

// Show a link to test the points system
echo "<p><a href='test_points.php'>Test Points System</a></p>";
echo "<p><a href='admin/students/student.php'>Go to Student Management</a></p>";
?> 