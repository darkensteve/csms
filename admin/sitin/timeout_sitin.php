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
    // Update sit-in session status to inactive and set check_out_time
    $update_query = "UPDATE sit_in_sessions SET status = 'inactive', check_out_time = ? WHERE session_id = ? AND status = 'active'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $current_time, $sitin_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Student successfully timed out.']);
    } else {
        // No rows updated - might be already inactive or ID not found
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No active sit-in session found with that ID.']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
