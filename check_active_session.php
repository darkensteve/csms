<?php
// Include database connection
require_once 'includes/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'has_active_session' => false,
    'message' => ''
];

// Check if student ID is provided
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Prepare query to check for active sessions
    $query = "SELECT COUNT(*) as count FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            $response['success'] = true;
            $response['has_active_session'] = ($row['count'] > 0);
            $response['message'] = $response['has_active_session'] ? 
                'Student already has an active sit-in session.' : 
                'No active sit-in sessions found for this student.';
        } else {
            $response['message'] = 'Error executing query: ' . $conn->error;
        }
        
        $stmt->close();
    } else {
        $response['message'] = 'Error preparing query: ' . $conn->error;
    }
} else {
    $response['message'] = 'Student ID not provided.';
}

// Send the JSON response
echo json_encode($response);
exit;
