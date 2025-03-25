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
        // Create the sit_in_sessions table if it doesn't exist - now including computer_id column
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
            computer_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table_sql)) {
            $_SESSION['sitin_message'] = "Failed to create database table: " . $conn->error;
            $_SESSION['sitin_status'] = "error";
            header('Location: ../students/search_student.php');
            exit();
        }
    } else {
        // Check if computer_id column exists in the table
        $column_check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'computer_id'");
        if ($column_check->num_rows == 0) {
            // Add computer_id column if it doesn't exist
            $alter_table_sql = "ALTER TABLE sit_in_sessions ADD COLUMN computer_id INT NULL";
            if (!$conn->query($alter_table_sql)) {
                $_SESSION['sitin_message'] = "Failed to update database table: " . $conn->error;
                $_SESSION['sitin_status'] = "error";
                header('Location: ../students/search_student.php');
                exit();
            }
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
            
            // We no longer decrease remaining sessions here
            // It will be decreased only when the student is timed out
        }
        $stmt_user->close();
    }
    
    // Check if student already has an active sit-in session
    $check_active = $conn->prepare("SELECT COUNT(*) as count FROM sit_in_sessions WHERE student_id = ? AND status = 'active'");
    $check_active->bind_param("s", $student_id);
    $check_active->execute();
    $result = $check_active->get_result();
    $active_count = $result->fetch_assoc()['count'];
    
    if ($active_count > 0) {
        $_SESSION['sitin_message'] = "This student already has an active sit-in session.";
        $_SESSION['sitin_status'] = 'error';
        
        // Redirect back
        if ($redirect_to_current) {
            header('Location: current_sitin.php');
        } else {
            header('Location: ../students/search_student.php');
        }
        exit();
    }
    
    // Check for approved reservations for this student
    $computer_id = null;
    $check_reservation = $conn->prepare("
        SELECT r.reservation_id, r.computer_id, r.lab_id, r.purpose
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        WHERE u.idNo = ? AND r.status = 'approved' AND DATE(r.reservation_date) = CURDATE()
        LIMIT 1
    ");
    
    if ($check_reservation) {
        $check_reservation->bind_param("s", $student_id);
        $check_reservation->execute();
        $res_result = $check_reservation->get_result();
        
        if ($res_result->num_rows > 0) {
            $reservation_data = $res_result->fetch_assoc();
            
            // Use the reserved computer and lab
            $computer_id = $reservation_data['computer_id'];
            $lab_id = $reservation_data['lab_id'];
            
            // Optionally use the reservation purpose if none provided
            if (empty($purpose) && !empty($reservation_data['purpose'])) {
                $purpose = $reservation_data['purpose'];
            }
            
            // Update computer status from 'reserved' to 'used'
            if ($computer_id) {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'used' WHERE computer_id = ?");
                $update_computer->bind_param("i", $computer_id);
                $update_computer->execute();
                
                // Also update the reservation status to 'completed'
                $update_reservation = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ?");
                $update_reservation->bind_param("i", $reservation_data['reservation_id']);
                $update_reservation->execute();
                
                $_SESSION['sitin_message'] = "Student has been checked in based on an approved reservation. Computer status updated to 'Used'.";
                $_SESSION['sitin_status'] = "success";
            }
        }
    }
    
    // Prepare the INSERT query with proper handling of computer_id
    // Use a variable to store the query and parameters to handle NULL values correctly
    if ($computer_id === null) {
        $query = "INSERT INTO sit_in_sessions (student_id, student_name, lab_id, purpose, check_in_time, status, admin_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisssi", $student_id, $student_name, $lab_id, $purpose, $check_in_time, $status, $_SESSION['admin_id']);
    } else {
        $query = "INSERT INTO sit_in_sessions (student_id, student_name, lab_id, purpose, check_in_time, status, admin_id, computer_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisssii", $student_id, $student_name, $lab_id, $purpose, $check_in_time, $status, $_SESSION['admin_id'], $computer_id);
    }
    
    if ($stmt) {
        if ($stmt->execute()) {
            $sitin_id = $conn->insert_id; // Get the newly created sit-in ID
            
            // If a computer is assigned, update its status to 'used'
            if ($computer_id) {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'used' WHERE computer_id = ?");
                if ($update_computer) {
                    $update_computer->bind_param("i", $computer_id);
                    $update_computer->execute();
                    $update_computer->close();
                }
            }
            
            // Success message
            $_SESSION['sitin_message'] = $computer_id 
                ? "Student has been checked in based on an approved reservation. Computer status updated to 'Used'." 
                : "Student has been checked in successfully.";
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
