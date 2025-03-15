<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Check if student_id parameter is provided
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    $_SESSION['sitin_message'] = "Invalid request. Student ID is required.";
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

// Get the student ID
$student_id = $_GET['student_id'];
$redirect_page = isset($_GET['redirect']) ? $_GET['redirect'] : 'current_sitin.php';

// Get current remaining sessions
$current_sessions = 0;
$student_query = "SELECT user_id, remaining_sessions FROM users WHERE idNo = ?";
$stmt_user = $conn->prepare($student_query);

if ($stmt_user) {
    $stmt_user->bind_param("s", $student_id);
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $user_id = $user_data['user_id'];
        $current_sessions = $user_data['remaining_sessions'] ?? 0;
        
        // Check if the student already has 30 sessions
        if ($current_sessions >= 30) {
            $_SESSION['sitin_message'] = "This student already has 30 or more sessions. No reset needed.";
            $_SESSION['sitin_status'] = "error";
            header("Location: $redirect_page");
            exit();
        }
        
        // Reset sessions to 30
        $new_sessions = 30;
        $update_query = "UPDATE users SET remaining_sessions = ? WHERE user_id = ?";
        $stmt_update = $conn->prepare($update_query);
        
        if ($stmt_update) {
            $stmt_update->bind_param("ii", $new_sessions, $user_id);
            
            if ($stmt_update->execute()) {
                $_SESSION['sitin_message'] = "Successfully reset student's sessions to 30.";
                $_SESSION['sitin_status'] = "success";
            } else {
                $_SESSION['sitin_message'] = "Failed to update sessions: " . $stmt_update->error;
                $_SESSION['sitin_status'] = "error";
            }
            
            $stmt_update->close();
        } else {
            $_SESSION['sitin_message'] = "Database error: " . $conn->error;
            $_SESSION['sitin_status'] = "error";
        }
    } else {
        $_SESSION['sitin_message'] = "Student not found in the database.";
        $_SESSION['sitin_status'] = "error";
    }
    
    $stmt_user->close();
} else {
    $_SESSION['sitin_message'] = "Database error: " . $conn->error;
    $_SESSION['sitin_status'] = "error";
}

// Redirect back to the referring page
header("Location: $redirect_page");
exit();
?>
