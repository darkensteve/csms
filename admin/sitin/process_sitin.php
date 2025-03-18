<?php
// Include database connection
require_once '../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login_admin.php');
    exit();
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = $_POST['student_id'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $lab_id = $_POST['lab_id'] ?? '';
    $redirect_to_current = isset($_POST['redirect_to_current']) ? true : false;
    
    // If purpose is Others, use the other_purpose field
    if ($purpose === 'Others' && !empty($_POST['other_purpose'])) {
        $purpose = $_POST['other_purpose'];
    }
    
    // Validate the required fields
    if (empty($student_id) || empty($purpose) || empty($lab_id)) {
        $_SESSION['sitin_message'] = "All fields are required. Please try again.";
        $_SESSION['sitin_status'] = "error";
        header('Location: ../students/search_student.php');
        exit();
    }
    
    // Get current timestamp for check-in
    $check_in_time = date('Y-m-d H:i:s');
    $check_out_time = null; // Will be updated when student checks out
    $status = 'active'; // Default status when checking in
    
    // Check if sit_in_sessions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'sit_in_sessions'");
    if ($table_check->num_rows == 0) {
        // Create the sit_in_sessions table if it doesn't exist
        $create_table_sql = "CREATE TABLE sit_in_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            lab_id INT NOT NULL,
            purpose VARCHAR(255) NOT NULL,
            check_in_time DATETIME NOT NULL,
            check_out_time DATETIME NULL,
            status VARCHAR(50) NOT NULL,
            admin_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table_sql)) {
            $_SESSION['sitin_message'] = "Failed to create database table: " . $conn->error;
            $_SESSION['sitin_status'] = "error";
            header('Location: ../students/search_student.php');
            exit();
        }
    }
    
    // Check if the student has remaining sessions by looking up the user
    $has_remaining_sessions = true;
    $student_query = "SELECT user_id, remaining_sessions FROM users WHERE idNo = ?";
    $stmt_user = $conn->prepare($student_query);
    
    if ($stmt_user) {
        $stmt_user->bind_param("s", $student_id);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $user_id = $user_data['user_id'];
            $remaining_sessions = $user_data['remaining_sessions'] ?? 0;
            
            // Check if student has remaining sessions
            if ($remaining_sessions <= 0) {
                $has_remaining_sessions = false;
                $_SESSION['sitin_message'] = "This student has no remaining sit-in sessions. Please add more sessions before registering.";
                $_SESSION['sitin_status'] = "error";
                header('Location: ../students/search_student.php');
                exit();
            }
            
            // Decrease remaining sessions
            $remaining_sessions--;
            $update_query = "UPDATE users SET remaining_sessions = ? WHERE user_id = ?";
            $stmt_update = $conn->prepare($update_query);
            if ($stmt_update) {
                $stmt_update->bind_param("ii", $remaining_sessions, $user_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }
        $stmt_user->close();
    }
    
    // Insert the sit-in session into the database
    $stmt = $conn->prepare("INSERT INTO sit_in_sessions (student_id, student_name, lab_id, purpose, check_in_time, status, admin_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("ssisssi", $student_id, $student_name, $lab_id, $purpose, $check_in_time, $status, $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            $sitin_id = $conn->insert_id; // Get the newly created sit-in ID
            
            // Success message
            $_SESSION['sitin_message'] = "Student successfully registered for sit-in session.";
            $_SESSION['sitin_status'] = "success";
            
            // Redirect based on the redirect parameter
            if ($redirect_to_current) {
                header('Location: current_sitin.php?sitin_id=' . $sitin_id);
            } else {
                header('Location: ../students/search_student.php');
            }
        } else {
            // Error message
            $_SESSION['sitin_message'] = "Failed to register sit-in session: " . $stmt->error;
            $_SESSION['sitin_status'] = "error";
            header('Location: ../students/search_student.php');
        }
        
        $stmt->close();
    } else {
        $_SESSION['sitin_message'] = "Database error: " . $conn->error;
        $_SESSION['sitin_status'] = "error";
        header('Location: ../students/search_student.php');
    }
} else {
    // If not a POST request, redirect to search page
    header('Location: ../students/search_student.php');
}

$conn->close();
?>
