<?php
// Use a relative path that works regardless of where this file is included from
$db_path = realpath(dirname(__FILE__) . '/../../includes/db_connect.php');
require_once $db_path;

// Function to check if column exists
function column_exists($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

// Function to output message and log
function log_message($message) {
    // For AJAX requests, don't output anything
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        error_log($message);
        return;
    }
    echo $message . "<br>";
    error_log($message);
}

// Begin the fix process - using silent operation for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // In AJAX mode, don't output messages
    ob_start();
}

// First check if system_logs table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");

if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it with all required columns
    log_message("Creating system_logs table from scratch...");
    
    $create_table_sql = "CREATE TABLE `system_logs` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` VARCHAR(50),
        `action` VARCHAR(255) NOT NULL,
        `action_type` VARCHAR(50) NOT NULL DEFAULT 'general',
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `user_agent` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($create_table_sql)) {
        log_message("system_logs table created successfully!");
    } else {
        log_message("Error creating system_logs table: " . $conn->error);
    }
} else {
    log_message("system_logs table exists, checking for required columns...");
    
    // Check if action_type column exists
    if (!column_exists($conn, 'system_logs', 'action_type')) {
        log_message("Adding missing column 'action_type'...");
        
        // Add action_type column
        $add_column_sql = "ALTER TABLE `system_logs` 
                           ADD COLUMN `action_type` VARCHAR(50) NOT NULL DEFAULT 'general' AFTER `action`";
        
        if ($conn->query($add_column_sql)) {
            log_message("Column 'action_type' added successfully!");
        } else {
            log_message("Error adding column: " . $conn->error);
        }
    } else {
        log_message("Column 'action_type' already exists.");
    }
}

log_message("Fix process completed!");

// Clean up output buffer for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    ob_end_clean();
}

// Only redirect if being accessed directly (not through require/include)
if (isset($_SERVER['HTTP_REFERER']) && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    log_message("Redirecting back to referring page...");
    echo '<script>window.location.href = "' . $_SERVER['HTTP_REFERER'] . '";</script>';
}
?>
