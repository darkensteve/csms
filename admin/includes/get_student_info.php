<?php
// Include database connection
require_once 'db_connect.php';

// Initialize response
$response = [
    'success' => false,
    'studentName' => '',
    'remainingSessions' => 0
];

// Check if request has student ID
if (isset($_POST['studentId'])) {
    $student_id = trim($_POST['studentId']);
    
    // Query to get student details
    $query = "SELECT username, name, firstname, lastname, remaining_sessions 
              FROM users WHERE idNo = ? OR id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("ss", $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $response['success'] = true;
            
            // Determine student name from available fields
            if (!empty($student['name'])) {
                $response['studentName'] = $student['name'];
            } elseif (!empty($student['firstname']) && !empty($student['lastname'])) {
                $response['studentName'] = $student['firstname'] . ' ' . $student['lastname'];
            } elseif (!empty($student['username'])) {
                $response['studentName'] = $student['username'];
            }
            
            // Get remaining sessions if available
            $response['remainingSessions'] = isset($student['remaining_sessions']) ? (int)$student['remaining_sessions'] : 30;
        }
        
        $stmt->close();
    } else {
        // Fall back to a simpler query if the first one fails (different table structure)
        $query = "SELECT * FROM users WHERE idNo = ? OR id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("ss", $student_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $student = $result->fetch_assoc();
                $response['success'] = true;
                
                // Try to find name fields using different common column names
                foreach (['name', 'fullname', 'full_name', 'username'] as $nameField) {
                    if (isset($student[$nameField]) && !empty($student[$nameField])) {
                        $response['studentName'] = $student[$nameField];
                        break;
                    }
                }
                
                // Get remaining sessions if available
                if (isset($student['remaining_sessions'])) {
                    $response['remainingSessions'] = (int)$student['remaining_sessions'];
                }
            }
            
            $stmt->close();
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
