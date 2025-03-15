<?php
// Include database connection
require_once 'includes/db_connect.php';

// Initialize response
$response = [
    'has_active_session' => false,
    'message' => ''
];

// Check if student_id is provided
if (isset($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);
    
    // Query to check if student has an active sit-in session
    $query = "SELECT session_id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $response['has_active_session'] = true;
            $response['message'] = 'Student has an active sit-in session';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error preparing database query';
    }
} else {
    $response['message'] = 'No student ID provided';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
