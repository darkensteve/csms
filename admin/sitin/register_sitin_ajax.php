<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to register a sit-in.']);
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'sitin_id' => 0
];

// Check if form submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input data
    $student_id = trim($_POST['studentId'] ?? '');
    $student_name = trim($_POST['studentName'] ?? '');
    $lab_id = intval($_POST['lab'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validate required fields
    if (empty($student_id) || empty($student_name) || empty($lab_id) || empty($purpose)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit();
    }
    
    // Check if student already has an active sit-in
    $check_query = "SELECT session_id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    
    if ($check_stmt) {
        $check_stmt->bind_param("s", $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $response['message'] = 'This student already has an active sit-in session.';
            echo json_encode($response);
            exit();
        }
        $check_stmt->close();
    }
    
    // Check if student has an approved reservation
    $reservation_id = null;
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
            $reservation_id = $reservation_data['reservation_id'];
            
            // Use the reservation purpose if none provided
            if (empty($purpose) && !empty($reservation_data['purpose'])) {
                $purpose = $reservation_data['purpose'];
            }
        }
    }
    
    // Get current timestamp for check-in
    $check_in_time = date('Y-m-d H:i:s');
    
    // Get admin ID if admin is logged in
    $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
    
    // Insert the new sit-in record
    $insert_query = "INSERT INTO sit_in_sessions (student_id, student_name, lab_id, purpose, check_in_time, status, admin_id, computer_id) 
                     VALUES (?, ?, ?, ?, ?, 'active', ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    
    if ($insert_stmt) {
        $computer_id = isset($_POST['computer_id']) ? $_POST['computer_id'] : null;
        $insert_stmt->bind_param("ssissii", $student_id, $student_name, $lab_id, $purpose, $check_in_time, $admin_id, $computer_id);
        
        if ($insert_stmt->execute()) {
            $new_sitin_id = $insert_stmt->insert_id;
            
            // If a computer is assigned, update its status to 'used'
            if ($computer_id) {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'used' WHERE computer_id = ?");
                if ($update_computer) {
                    $update_computer->bind_param("i", $computer_id);
                    $update_computer->execute();
                    $update_computer->close();
                }
                
                // If this was from a reservation, mark the reservation as completed
                if ($reservation_id) {
                    $update_reservation = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ?");
                    if ($update_reservation) {
                        $update_reservation->bind_param("i", $reservation_id);
                        $update_reservation->execute();
                        $update_reservation->close();
                        
                        $response['message'] = 'Sit-in registered successfully based on approved reservation!';
                    }
                }
            }
            
            // We no longer deduct a session here when registering
            // The deduction will happen only when the student logs out
            
            $response['success'] = true;
            $response['message'] = 'Sit-in registered successfully!';
            $response['sitin_id'] = $new_sitin_id;
        } else {
            $response['message'] = 'Error registering sit-in: ' . $insert_stmt->error;
        }
        
        $insert_stmt->close();
    } else {
        $response['message'] = 'Error preparing database query: ' . $conn->error;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Return JSON response
echo json_encode($response);
exit();
?>
