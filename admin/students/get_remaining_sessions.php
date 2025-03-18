<?php
// Include database connection
require_once '../includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'remaining_sessions' => 0
];

// Check if student ID is provided
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);
    
    // First try with the users table and idNo column
    $query = "SELECT remaining_sessions FROM users WHERE idNo = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $response['success'] = true;
            $response['remaining_sessions'] = $user['remaining_sessions'];
            $response['message'] = 'Remaining sessions retrieved successfully';
        } else {
            // Try alternative columns if first attempt failed
            $stmt->close();
            
            // Try with id column if idNo failed
            $query = "SELECT remaining_sessions FROM users WHERE id = ? OR student_id = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("ss", $student_id, $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $response['success'] = true;
                    $response['remaining_sessions'] = $user['remaining_sessions'];
                    $response['message'] = 'Remaining sessions retrieved successfully';
                } else {
                    // Try students table if users table failed
                    $stmt->close();
                    
                    // Check if students table exists and has remaining_sessions column
                    $table_check = $conn->query("SHOW TABLES LIKE 'students'");
                    if ($table_check->num_rows > 0) {
                        $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'remaining_sessions'");
                        
                        if ($column_check->num_rows > 0) {
                            $query = "SELECT remaining_sessions FROM students WHERE id = ? OR student_id = ? OR idNo = ?";
                            $stmt = $conn->prepare($query);
                            
                            if ($stmt) {
                                $stmt->bind_param("sss", $student_id, $student_id, $student_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result && $result->num_rows > 0) {
                                    $user = $result->fetch_assoc();
                                    $response['success'] = true;
                                    $response['remaining_sessions'] = $user['remaining_sessions'];
                                    $response['message'] = 'Remaining sessions retrieved from students table';
                                }
                                $stmt->close();
                            }
                        }
                    }
                    
                    // If still not found, return default value
                    if (!$response['success']) {
                        $response['message'] = 'Student not found, using default value';
                        $response['remaining_sessions'] = 30; // Default value
                        $response['default'] = true;
                    }
                }
            }
        }
        
        if ($stmt) {
            $stmt->close();
        }
    } else {
        $response['message'] = 'Database query error';
        $response['remaining_sessions'] = 30; // Default value in case of error
        $response['default'] = true;
    }
} else {
    $response['message'] = 'Missing student ID';
    $response['remaining_sessions'] = 30; // Default value if no ID provided
    $response['default'] = true;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
