<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => 'Invalid request', 'remaining_sessions' => 0];

// Get student ID from the GET parameter
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // First, try to identify the student table
    $potential_tables = ['users', 'students', 'student'];
    $table_name = '';
    
    // Get tables in the database
    $tables_in_db = [];
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        while($table_row = $tables_result->fetch_row()) {
            $tables_in_db[] = $table_row[0];
        }
    }
    
    // Find the student table
    foreach ($potential_tables as $table) {
        if (in_array($table, $tables_in_db)) {
            $table_name = $table;
            break;
        }
    }
    
    // If no known student tables found, try the first table
    if (empty($table_name) && !empty($tables_in_db)) {
        $table_name = $tables_in_db[0];
    }
    
    if (!empty($table_name)) {
        // Check if the remaining_sessions column exists in the table
        $column_check = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE 'remaining_sessions'");
        
        if ($column_check && $column_check->num_rows > 0) {
            // Look for the student in the student table
            $query = "SELECT remaining_sessions FROM `{$table_name}` WHERE ";
            
            // Try to determine the ID column
            $id_columns = ['IDNO', 'id', 'student_id', 'user_id', 'USER_ID'];
            $id_clause = [];
            
            foreach ($id_columns as $col) {
                $column_exists = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE '{$col}'");
                if ($column_exists && $column_exists->num_rows > 0) {
                    $id_clause[] = "`{$col}` = ?";
                }
            }
            
            if (!empty($id_clause)) {
                $query .= implode(" OR ", $id_clause);
                $stmt = $conn->prepare($query);
                
                if ($stmt) {
                    // Bind the student ID to all possible columns
                    $types = str_repeat("s", count($id_clause));
                    $params = array_fill(0, count($id_clause), $student_id);
                    
                    // Create array of references for bind_param
                    $bind_params = array($types);
                    foreach ($params as $key => $value) {
                        $bind_params[] = &$params[$key];
                    }
                    
                    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Student found in the student table
                        $row = $result->fetch_assoc();
                        $response = [
                            'success' => true,
                            'remaining_sessions' => intval($row['remaining_sessions']),
                            'source' => 'student_table'
                        ];
                    } else {
                        // Try sit_in_quota table as fallback
                        $quota_check = $conn->query("SHOW TABLES LIKE 'sit_in_quota'");
                        if ($quota_check && $quota_check->num_rows > 0) {
                            $quota_query = "SELECT remaining_sessions FROM sit_in_quota WHERE student_id = ?";
                            $quota_stmt = $conn->prepare($quota_query);
                            
                            if ($quota_stmt) {
                                $quota_stmt->bind_param("s", $student_id);
                                $quota_stmt->execute();
                                $quota_result = $quota_stmt->get_result();
                                
                                if ($quota_result->num_rows > 0) {
                                    // Student found in quota table
                                    $quota_row = $quota_result->fetch_assoc();
                                    $response = [
                                        'success' => true,
                                        'remaining_sessions' => intval($quota_row['remaining_sessions']),
                                        'source' => 'quota_table'
                                    ];
                                } else {
                                    // Default to 30 sessions as in student.php
                                    $response = [
                                        'success' => true,
                                        'remaining_sessions' => 30,
                                        'message' => 'Using default quota',
                                        'source' => 'default'
                                    ];
                                }
                                $quota_stmt->close();
                            }
                        } else {
                            // Default to 30 sessions as in student.php
                            $response = [
                                'success' => true,
                                'remaining_sessions' => 30,
                                'message' => 'Using default quota',
                                'source' => 'default'
                            ];
                        }
                    }
                    $stmt->close();
                } else {
                    $response['message'] = 'Database query error';
                }
            } else {
                $response['message'] = 'Could not determine ID column';
            }
        } else {
            // The remaining_sessions column doesn't exist in the student table
            // Default to 30 sessions as in student.php
            $response = [
                'success' => true,
                'remaining_sessions' => 30,
                'message' => 'Column not found in table, using default',
                'source' => 'default'
            ];
        }
    } else {
        $response['message'] = 'Student table not found';
    }
} else {
    $response['message'] = 'Missing student ID';
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
