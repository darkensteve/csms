<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['sitin_message'] = "No sit-in session specified.";
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

$sitin_id = intval($_GET['id']);
$current_time = date('Y-m-d H:i:s');

// Check if user is authorized (admin or the owner of the sit-in)
$authorized = false;
if (isset($_SESSION['admin_id'])) {
    $authorized = true; // Admin is always authorized
} elseif (isset($_SESSION['user_id'])) {
    // Check if this sit-in belongs to the current user
    $auth_query = "SELECT session_id FROM sit_in_sessions WHERE session_id = ? AND student_id = ?";
    $auth_stmt = $conn->prepare($auth_query);
    if ($auth_stmt) {
        $auth_stmt->bind_param("is", $sitin_id, $_SESSION['user_id']);
        $auth_stmt->execute();
        $auth_result = $auth_stmt->get_result();
        if ($auth_result->num_rows > 0) {
            $authorized = true;
        }
        $auth_stmt->close();
    }
}

if (!$authorized) {
    $_SESSION['sitin_message'] = "You are not authorized to time out this sit-in session.";
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

// Get the student ID before updating the record
$student_id = '';
$query = "SELECT student_id FROM sit_in_sessions WHERE session_id = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $sitin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $student_id = $row['student_id'];
    }
    $stmt->close();
}

// Update the sit-in record to mark it as timed out
$query = "UPDATE sit_in_sessions SET status = 'inactive', check_out_time = ? WHERE session_id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("si", $current_time, $sitin_id);
    
    if ($stmt->execute()) {
        // Update was successful
        $_SESSION['sitin_message'] = "Student successfully timed out.";
        $_SESSION['sitin_status'] = "success";
        
        // If the student has an account, decrement their remaining sessions
        if (!empty($student_id)) {
            $update_sessions = "UPDATE users SET remaining_sessions = GREATEST(remaining_sessions - 1, 0) WHERE idNo = ?";
            $session_stmt = $conn->prepare($update_sessions);
            if ($session_stmt) {
                $session_stmt->bind_param("s", $student_id);
                $session_stmt->execute();
                $session_stmt->close();
            }
        }
        
    } else {
        // Update failed
        $_SESSION['sitin_message'] = "Failed to time out student: " . $stmt->error;
        $_SESSION['sitin_status'] = "error";
    }
    
    $stmt->close();
} else {
    // Statement preparation failed
    $_SESSION['sitin_message'] = "Database error: " . $conn->error;
    $_SESSION['sitin_status'] = "error";
}

// Redirect back to current_sitin.php
header('Location: current_sitin.php' . (empty($student_id) ? '' : '?user_id=' . urlencode($student_id)));
exit();
