<?php
// Start session
session_start();

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if accessed directly
    header("Location: admin.php");
    exit();
}

// Database connection
require_once 'includes/db_connect.php';

// Get the form data
$session_id = $_POST['session_id'] ?? 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$feedback_text = $_POST['feedback'] ?? '';
$user_id = $_SESSION['id'] ?? 0; // From user session

// Validate data
if (empty($session_id) || $rating < 1 || $rating > 5) {
    $_SESSION['feedback_message'] = "Invalid feedback data. Please try again.";
    $_SESSION['feedback_status'] = "error";
    
    // Redirect back to appropriate page
    if (isset($_SESSION['admin_id'])) {
        header("Location: sitin/feedback_reports.php");
    } else {
        header("Location: ../user/history.php");
    }
    exit();
}

// Always try to create the feedback table first to avoid issues
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'sit_in_feedback'");
    if ($table_check->num_rows == 0) {
        // Create the feedback table
        $create_table_sql = "CREATE TABLE sit_in_feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL,
            feedback_text TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Could not create feedback table: " . $conn->error);
        }
    }

    // Check if we need to add the feedback_provided column to sit_in_sessions
    $column_check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'feedback_provided'");
    if ($column_check->num_rows == 0) {
        $add_column_sql = "ALTER TABLE sit_in_sessions ADD COLUMN feedback_provided TINYINT(1) DEFAULT 0";
        if (!$conn->query($add_column_sql)) {
            // Not critical, we can continue
            error_log("Could not add feedback_provided column: " . $conn->error);
        }
    }

    // Check if user has already submitted feedback for this session
    $check_query = "SELECT feedback_id FROM sit_in_feedback WHERE session_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception("Database error preparing statement: " . $conn->error);
    }
    
    $check_stmt->bind_param("ii", $session_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing feedback
        $update_query = "UPDATE sit_in_feedback SET rating = ?, feedback_text = ?, submitted_at = NOW() WHERE session_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Database error preparing update statement: " . $conn->error);
        }
        $stmt->bind_param("isii", $rating, $feedback_text, $session_id, $user_id);
    } else {
        // Insert new feedback
        $insert_query = "INSERT INTO sit_in_feedback (session_id, user_id, rating, feedback_text) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Database error preparing insert statement: " . $conn->error);
        }
        $stmt->bind_param("iiis", $session_id, $user_id, $rating, $feedback_text);
    }
    $check_stmt->close();
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to save feedback: " . $stmt->error);
    }
    
    // Try to update the sit_in_sessions table to mark that feedback was provided
    try {
        $update_session = "UPDATE sit_in_sessions SET feedback_provided = 1 WHERE session_id = ?";
        $update_stmt = $conn->prepare($update_session);
        if ($update_stmt) {
            $update_stmt->bind_param("i", $session_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } catch (Exception $e) {
        // Not critical, log the error but continue
        error_log("Error updating feedback_provided status: " . $e->getMessage());
    }
    
    $_SESSION['feedback_message'] = "Thank you for your feedback!";
    $_SESSION['feedback_status'] = "success";
    
    $stmt->close();

} catch (Exception $e) {
    $_SESSION['feedback_message'] = "Failed to submit feedback: " . $e->getMessage();
    $_SESSION['feedback_status'] = "error";
}

$conn->close();

// Redirect back to the appropriate page
if (isset($_SESSION['admin_id'])) {
    header("Location: sitin/feedback_reports.php");
} else {
    header("Location: ../user/history.php");
}
exit();
?>
