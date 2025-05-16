<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login_admin.php');
    exit();
}

// Get parameters from either GET or POST
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : (isset($_POST['student_id']) ? $_POST['student_id'] : null);
$id_column = isset($_GET['id_col']) ? $_GET['id_col'] : (isset($_POST['id_col']) ? $_POST['id_col'] : null);
$points_to_add = isset($_POST['points']) ? (int)$_POST['points'] : null;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Check if student ID and points are provided
if (!$student_id || !$id_column || !$points_to_add) {
    $_SESSION['error_message'] = "Missing required parameters. Please try again.";
    header('Location: student.php');
    exit();
}

// Validate points
if ($points_to_add <= 0 || $points_to_add > 10) {
    $_SESSION['error_message'] = "Points must be between 1 and 10.";
    header('Location: student.php');
    exit();
}

// First check if the points column exists in the users table
$column_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'points'");
if ($column_check && $column_check->num_rows == 0) {
    // Add points column if it doesn't exist
    $conn->query("ALTER TABLE `users` ADD COLUMN `points` INT NOT NULL DEFAULT 0");
}

// Check if points_log table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'points_log'");
if ($table_check->num_rows == 0) {
    // Create points_log table with all required columns
    $conn->query("CREATE TABLE `points_log` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` varchar(50) NOT NULL,
        `points_added` int(11) NOT NULL,
        `reason` text DEFAULT NULL,
        `admin_id` int(11) NOT NULL,
        `admin_username` varchar(100) NOT NULL DEFAULT 'admin',
        `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} else {
    // Check if admin_username column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `points_log` LIKE 'admin_username'");
    if ($column_check && $column_check->num_rows == 0) {
        // Add admin_username column if it doesn't exist
        $conn->query("ALTER TABLE `points_log` ADD COLUMN `admin_username` varchar(100) NOT NULL DEFAULT 'admin' AFTER `admin_id`");
    }
    
    // Check if added_at column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `points_log` LIKE 'added_at'");
    if ($column_check && $column_check->num_rows == 0) {
        // Add added_at column if it doesn't exist
        $conn->query("ALTER TABLE `points_log` ADD COLUMN `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

// Start a transaction to ensure all operations are atomic
$conn->begin_transaction();

try {
    // 1. First get current points
    $get_points_query = "SELECT `points`, `remaining_sessions` FROM `users` WHERE `{$id_column}` = ?";
    $points_stmt = $conn->prepare($get_points_query);
    $points_stmt->bind_param('s', $student_id);
    $points_stmt->execute();
    $result = $points_stmt->get_result();
    $user_data = $result->fetch_assoc();
    $current_points = $user_data['points'] ?? 0;
    $current_sessions = $user_data['remaining_sessions'] ?? 0;
    
    // 2. Add the new points
    $new_points = $current_points + $points_to_add;
    
    // 3. Calculate points to convert to sessions
    $points_to_convert = floor($new_points / 3) * 3;
    $sessions_to_add = floor($new_points / 3);
    $remaining_points = $new_points - $points_to_convert;
    
    // 4. Update user record with new points and sessions
    if ($points_to_convert > 0) {
        $update_query = "UPDATE `users` SET 
                        `points` = ?,
                        `remaining_sessions` = `remaining_sessions` + ?
                        WHERE `{$id_column}` = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iis', $remaining_points, $sessions_to_add, $student_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = "Added {$points_to_add} points. {$points_to_convert} points were converted to {$sessions_to_add} additional session(s).";
    } else {
        $update_query = "UPDATE `users` SET `points` = ? WHERE `{$id_column}` = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('is', $new_points, $student_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = "Added {$points_to_add} points. Current points: {$new_points}";
    }
    
    // 5. Log the points addition
    $log_query = "INSERT INTO `points_log` (`student_id`, `points_added`, `reason`, `admin_id`, `admin_username`) 
                 VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $admin_id = $_SESSION['admin_id'];
    $admin_username = $_SESSION['admin_username'] ?? 'admin'; // Use default if not set
    
    if ($log_stmt) {
        $log_stmt->bind_param('sisss', $student_id, $points_to_add, $reason, $admin_id, $admin_username);
        $log_stmt->execute();
    }
    
    // Commit the transaction
    $conn->commit();
    
    header('Location: student.php?updated=1');
    exit();
} catch (Exception $e) {
    // Roll back the transaction in case of error
    $conn->rollback();
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header('Location: student.php');
    exit();
}
?> 