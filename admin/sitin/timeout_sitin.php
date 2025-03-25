<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if user is logged in (either admin or regular user)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if sit-in ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Sit-in ID is required']);
    exit();
}

$sitin_id = intval($_POST['id']);

// Get the current time in Philippine timezone
date_default_timezone_set('Asia/Manila');
$current_time = date('Y-m-d H:i:s');

// Start transaction
$conn->begin_transaction();

try {
    // First get the student_id and computer_id for the sit-in session to update their remaining sessions
    $get_student_query = "SELECT student_id, computer_id FROM sit_in_sessions WHERE session_id = ? AND status = 'active'";
    $stmt_get = $conn->prepare($get_student_query);
    $stmt_get->bind_param("i", $sitin_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
        $student_id = $student_data['student_id'];
        $computer_id = $student_data['computer_id'];
        
        // Update sit-in session status to inactive and set check_out_time
        $update_query = "UPDATE sit_in_sessions SET status = 'inactive', check_out_time = ? WHERE session_id = ? AND status = 'active'";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $current_time, $sitin_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Now deduct one session from student's remaining sessions
            $update_sessions_query = "UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE idNo = ? AND remaining_sessions > 0";
            $stmt_sessions = $conn->prepare($update_sessions_query);
            $stmt_sessions->bind_param("s", $student_id);
            $stmt_sessions->execute();
            
            // If there's a computer associated with this session, set it back to available
            if ($computer_id) {
                $update_computer_query = "UPDATE computers SET status = 'available' WHERE computer_id = ?";
                $stmt_computer = $conn->prepare($update_computer_query);
                $stmt_computer->bind_param("i", $computer_id);
                $stmt_computer->execute();
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Student successfully timed out and session deducted.']);
        } else {
            // No rows updated - might be already inactive or ID not found
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'No active sit-in session found with that ID.']);
        }
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No active sit-in session found with that ID.']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
