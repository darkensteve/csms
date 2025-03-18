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
    'success' => true,
    'has_active_session' => false,
    'message' => ''
];

// Check if student ID is provided via GET or POST
$student_id = '';
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);
} elseif (isset($_POST['studentId']) && !empty($_POST['studentId'])) {
    $student_id = trim($_POST['studentId']);
}

if (!empty($student_id)) {
    // Query to check for active sit-in sessions
    $query = "SELECT session_id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If we found any active sessions
        if ($result && $result->num_rows > 0) {
            $response['has_active_session'] = true;
            $response['message'] = 'Student has an active sit-in session';
        } else {
            $response['message'] = 'No active sit-in sessions found';
        }
        
        $stmt->close();
    } else {
        $response['success'] = false;
        $response['message'] = 'Database query error';
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Missing student ID';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
