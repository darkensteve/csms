<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to register a sit-in.']);
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'sitin_id' => 0
];

// Check if form submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input data
    $student_id = trim($_POST['studentId'] ?? '');
    $student_name = trim($_POST['studentName'] ?? '');
    $lab_id = intval($_POST['lab'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validate required fields
    if (empty($student_id) || empty($student_name) || empty($lab_id) || empty($purpose)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit();
    }
    
    // Check if student already has an active sit-in
    $check_query = "SELECT session_id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    
    if ($check_stmt) {
        $check_stmt->bind_param("s", $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $response['message'] = 'This student already has an active sit-in session.';
            echo json_encode($response);
            exit();
        }
        $check_stmt->close();
    }
    
    // Get current timestamp for check-in
    $check_in_time = date('Y-m-d H:i:s');
    
    // Get admin ID if admin is logged in
    $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
    
    // Insert the new sit-in record
    $insert_query = "INSERT INTO sit_in_sessions (student_id, student_name, lab_id, purpose, check_in_time, status, admin_id) 
                     VALUES (?, ?, ?, ?, ?, 'active', ?)";
    $insert_stmt = $conn->prepare($insert_query);
    
    if ($insert_stmt) {
        $insert_stmt->bind_param("ssissi", $student_id, $student_name, $lab_id, $purpose, $check_in_time, $admin_id);
        
        if ($insert_stmt->execute()) {
            $new_sitin_id = $insert_stmt->insert_id;
            
            // Deduct one session from student's remaining sessions if the table has this column
            $update_query = "UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE idNo = ? AND remaining_sessions > 0";
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt) {
                $update_stmt->bind_param("s", $student_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $response['success'] = true;
            $response['message'] = 'Sit-in registered successfully!';
            $response['sitin_id'] = $new_sitin_id;
        } else {
            $response['message'] = 'Error registering sit-in: ' . $insert_stmt->error;
        }
        
        $insert_stmt->close();
    } else {
        $response['message'] = 'Error preparing database query: ' . $conn->error;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Return JSON response
echo json_encode($response);
exit();
?>
