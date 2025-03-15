<?php
// Include database connection
require_once 'db_connect.php';

// Initialize response
$response = [
    'hasActiveSession' => false,
    'message' => ''
];

// Check if request has student ID
if (isset($_POST['studentId'])) {
    $student_id = trim($_POST['studentId']);
    
    // Query to check for active sit-in sessions
    $query = "SELECT session_id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If we found any active sessions
        if ($result && $result->num_rows > 0) {
            $response['hasActiveSession'] = true;
            $response['message'] = 'Student has an active sit-in session';
        }
        
        $stmt->close();
    } else {
        $response['message'] = 'Database query error';
    }
} else {
    $response['message'] = 'Missing student ID';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
