<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

// Set debug mode to false for production environment
$debug_mode = false; // Change to true only for development/debugging
$debug_info = '';

// Find the student table
$tables_in_db = [];
$tables_result = $conn->query("SHOW TABLES");
if ($tables_result) {
    while($table_row = $tables_result->fetch_row()) {
        $tables_in_db[] = $table_row[0];
    }
}

// Try to find the student table
$table_name = '';
$potential_tables = ['users', 'students', 'student'];
            
foreach ($potential_tables as $table) {
    if (in_array($table, $tables_in_db)) {
        $table_name = $table;
        $debug_info .= "Using table: {$table_name}. ";
        break;
    }
}

// If no known student tables found, try the first table
if (empty($table_name) && !empty($tables_in_db)) {
    $table_name = $tables_in_db[0];
    $debug_info .= "No recognized student table found. Using first table: {$table_name}. ";
}

// Check if remaining_sessions column exists in the table
if (!empty($table_name)) {
    $column_check = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE 'remaining_sessions'");
    
    if ($column_check && $column_check->num_rows == 0) {
        // Add remaining_sessions column if it doesn't exist
        $conn->query("ALTER TABLE `{$table_name}` ADD COLUMN remaining_sessions INT(11) NOT NULL DEFAULT 30");
        $debug_info .= "Added remaining_sessions column to {$table_name} table. ";
    }
    
    // Reset all students' remaining sessions to 30
    $update_query = "UPDATE `{$table_name}` SET remaining_sessions = 30";
    if ($conn->query($update_query)) {
        $_SESSION['success_message'] = "All students' remaining sessions have been reset to 30.";
        header("Location: student.php?reset_all=1");
        exit();
    } else {
        $_SESSION['error_message'] = "Error resetting sessions: " . $conn->error;
        header("Location: student.php?error=reset_all");
        exit();
    }
} else {
    $_SESSION['error_message'] = "No student table found in the database.";
    header("Location: student.php?error=no_table");
    exit();
}
?>
