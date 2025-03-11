<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'An unknown error occurred'
];

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = $_POST['student_id'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $lab_id = $_POST['lab_id'] ?? '';
    $remaining_sessions = $_POST['remaining_sessions'] ?? 1;
    
    // Handle "Others" purpose
    if ($purpose === 'Others' && isset($_POST['other_purpose']) && !empty($_POST['other_purpose'])) {
        $purpose = htmlspecialchars($_POST['other_purpose']);
    }
    
    // Validate required fields
    if (empty($student_id) || empty($purpose) || empty($lab_id)) {
        $response = [
            'status' => 'error',
            'message' => 'All required fields must be filled out'
        ];
    } else {
        // Get current date and time
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $admin_id = $_SESSION['admin_id'];
        
        // First, check if sit_in table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'sit_in'");
        $table_exists = ($table_check->num_rows > 0);
        
        if (!$table_exists) {
            // Create sit_in table
            $create_table_sql = "CREATE TABLE sit_in (
                sit_in_id INT(11) NOT NULL AUTO_INCREMENT,
                student_id VARCHAR(50) NOT NULL,
                student_name VARCHAR(255) NOT NULL,
                purpose VARCHAR(255) NOT NULL,
                lab_id INT(11) NOT NULL,
                admin_id INT(11) NOT NULL,
                date_registered DATE NOT NULL,
                time_registered TIME NOT NULL,
                remaining_sessions INT(11) NOT NULL DEFAULT 1,
                status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (sit_in_id)
            )";
            
            $conn->query($create_table_sql);
            
            // After creating the table, we don't need to check for existing records
            $has_active_session = false;
        } else {
            // Check if the student has any existing active sit-in records
            $check_sql = "SELECT * FROM sit_in WHERE student_id = ? AND status = 'active'";
            $check_stmt = $conn->prepare($check_sql);
            
            if ($check_stmt) {
                $check_stmt->bind_param("s", $student_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $has_active_session = ($result->num_rows > 0);
                $check_stmt->close();
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to check existing sit-in records: ' . $conn->error
                ];
                $has_active_session = false; // Default to false if query fails
            }
        }
        
        if ($has_active_session) {
            // Student already has an active sit-in record
            $response = [
                'status' => 'error',
                'message' => 'Student already has an active sit-in session'
            ];
        } else {
            // Create sit-in record
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Insert the sit-in record
                $sql = "INSERT INTO sit_in (student_id, student_name, purpose, lab_id, admin_id, date_registered, time_registered, remaining_sessions, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiissi", $student_id, $student_name, $purpose, $lab_id, $admin_id, $current_date, $current_time, $remaining_sessions);
                
                if ($stmt->execute()) {
                    $sit_in_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Update the user's remaining sessions in the users table
                    // First, check if remaining_sessions column exists in users table
                    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'remaining_sessions'");
                    
                    if ($column_check->num_rows == 0) {
                        // Add remaining_sessions column if it doesn't exist
                        $conn->query("ALTER TABLE users ADD COLUMN remaining_sessions INT(11) NOT NULL DEFAULT 30");
                    }
                    
                    // Update the remaining sessions (decrease by 1)
                    $update_sql = "UPDATE users SET remaining_sessions = GREATEST(remaining_sessions - 1, 0) WHERE idNo = ? OR user_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    
                    if ($update_stmt) {
                        $update_stmt->bind_param("ss", $student_id, $student_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        // Commit the transaction
                        $conn->commit();
                        
                        $response = [
                            'status' => 'success',
                            'message' => 'Sit-in registration successful',
                            'sit_in_id' => $sit_in_id
                        ];
                        
                        // Log the activity
                        logActivity($conn, $admin_id, 'sit_in_registration', "Registered sit-in for student ID: $student_id");
                    } else {
                        // Rollback on failure
                        $conn->rollback();
                        $response = [
                            'status' => 'error',
                            'message' => 'Failed to update remaining sessions: ' . $conn->error
                        ];
                    }
                } else {
                    $conn->rollback();
                    $response = [
                        'status' => 'error',
                        'message' => 'Database error: ' . $stmt->error
                    ];
                    $stmt->close();
                }
            } catch (Exception $e) {
                // Rollback on exception
                if ($conn->connect_errno == 0) {
                    $conn->rollback();
                }
                $response = [
                    'status' => 'error',
                    'message' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
    }
}

// Function to log admin activity
function logActivity($conn, $admin_id, $action_type, $description) {
    // Check if admin_logs table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($table_check->num_rows == 0) {
        // Create admin_logs table
        $create_table_sql = "CREATE TABLE admin_logs (
            log_id INT(11) NOT NULL AUTO_INCREMENT,
            admin_id INT(11) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id)
        )";
        
        $conn->query($create_table_sql);
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $log_sql = "INSERT INTO admin_logs (admin_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    
    if ($log_stmt) {
        $log_stmt->bind_param("isss", $admin_id, $action_type, $description, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// Determine if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Set session message for regular form submissions
    $_SESSION['sitin_message'] = $response['message'];
    $_SESSION['sitin_status'] = $response['status'];
    
    // Redirect back to search page
    header('Location: search_student.php');
    exit;
}
?>
