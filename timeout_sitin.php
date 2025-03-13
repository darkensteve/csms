<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    }
    // Regular redirect for direct access
    header('Location: login.php');
    exit();
}

// Define response array (for AJAX)
$response = [
    'success' => false,
    'message' => '',
];

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check for sit-in ID
$sitin_id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($sitin_id <= 0) {
    $response['message'] = 'Invalid sit-in ID';
    
    if ($is_ajax) {
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['sitin_message'] = $response['message'];
        $_SESSION['sitin_status'] = 'error';
        header('Location: current_sitin.php');
        exit();
    }
}

// Get current time for checkout
$checkout_time = date('Y-m-d H:i:s');

// Update the sit-in session
$query = "UPDATE sit_in_sessions SET status = 'inactive', check_out_time = ? WHERE session_id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("si", $checkout_time, $sitin_id);
    $result = $stmt->execute();
    
    if ($result) {
        // Get student ID for the message
        $student_query = "SELECT student_id, student_name FROM sit_in_sessions WHERE session_id = ?";
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $sitin_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $student_data = $student_result->fetch_assoc();
        
        // Decrement remaining sessions if applicable
        if ($student_data) {
            $student_id = $student_data['student_id'];
            $student_name = $student_data['student_name'];
            
            // Update the user's remaining sessions
            $update_query = "UPDATE users SET remaining_sessions = GREATEST(remaining_sessions - 1, 0) WHERE idNo = ?";
            $update_stmt = $conn->prepare($update_query);
            if ($update_stmt) {
                $update_stmt->bind_param("s", $student_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $response['success'] = true;
            $response['message'] = "Student " . htmlspecialchars($student_name) . " has been timed out successfully.";
        } else {
            $response['success'] = true;
            $response['message'] = "Student has been timed out successfully.";
        }
        
        $student_stmt->close();
    } else {
        $response['message'] = "Error updating sit-in: " . $stmt->error;
    }
    
    $stmt->close();
} else {
    $response['message'] = "Database error: " . $conn->error;
}

// Return JSON for AJAX requests
if ($is_ajax) {
    echo json_encode($response);
    exit;
}

// For regular form submissions, set session message and redirect
$_SESSION['sitin_message'] = $response['message'];
$_SESSION['sitin_status'] = $response['success'] ? 'success' : 'error';
header('Location: current_sitin.php');
exit();
?>
