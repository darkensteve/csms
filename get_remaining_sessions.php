<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Initialize response
$response = ['success' => false, 'remaining_sessions' => 0, 'message' => ''];

// Get student ID from request
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Check various potential table/column combinations for remaining sessions
    $tables_to_check = ['users', 'students', 'student', 'sitin_users'];
    $remaining_sessions_found = false;

    foreach ($tables_to_check as $table) {
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($table_check->num_rows > 0) {
            // Table exists, now check column structure
            $col_result = $conn->query("SHOW COLUMNS FROM `{$table}`");
            $columns = [];
            
            if ($col_result) {
                while($col = $col_result->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }

            // Look for columns related to remaining sessions
            $session_columns = ['remaining_sessions', 'sessions_remaining', 'sessions_left', 'remaining_time'];
            $found_column = '';
            
            foreach ($session_columns as $col) {
                if (in_array($col, $columns)) {
                    $found_column = $col;
                    break;
                }
            }
            
            if ($found_column) {
                // Found a suitable column, fetch the value
                $id_column = '';
                $potential_id_columns = ['student_id', 'user_id', 'id', 'idno', 'IDNO', 'USER_ID'];
                
                foreach ($potential_id_columns as $col) {
                    if (in_array($col, $columns)) {
                        $id_column = $col;
                        break;
                    }
                }
                
                if ($id_column) {
                    $stmt = $conn->prepare("SELECT `{$found_column}` FROM `{$table}` WHERE `{$id_column}` = ?");
                    $stmt->bind_param('s', $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        $remaining_sessions = $row[$found_column];
                        $response['success'] = true;
                        $response['remaining_sessions'] = $remaining_sessions;
                        $remaining_sessions_found = true;
                        break;
                    }
                }
            }
        }
    }
    
    // If no remaining sessions found in database, return default value
    if (!$remaining_sessions_found) {
        $response['remaining_sessions'] = "0";
        $response['message'] = "No session data found";
        $response['success'] = true;
    }
} else {
    $response['message'] = "No student ID provided";
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
