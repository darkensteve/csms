<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the user ID from session
$userId = $_SESSION['id'] ?? 0;

// Get form data
$sessionId = $_POST['session_id'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$feedback = $_POST['feedback'] ?? '';
$returnTo = $_POST['return_to'] ?? 'user/history.php';

if (!$userId || !$sessionId || !$rating) {
    // Set error message
    $_SESSION['feedback_message'] = "Missing required information. Please try again.";
    $_SESSION['feedback_status'] = "error";
    
    // Redirect back to history page
    header("Location: ../../" . $returnTo);
    exit();
}

// Check if the sit_in_feedback table exists
$tableExistsQuery = $conn->query("SHOW TABLES LIKE 'sit_in_feedback'");
if ($tableExistsQuery->num_rows == 0) {
    // Create the table if it doesn't exist
    $createTableQuery = "CREATE TABLE sit_in_feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL,
        feedback TEXT,
        submitted_at DATETIME NOT NULL,
        INDEX (session_id),
        INDEX (user_id)
    )";
    
    if (!$conn->query($createTableQuery)) {
        $_SESSION['feedback_message'] = "Error creating feedback table: " . $conn->error;
        $_SESSION['feedback_status'] = "error";
        header("Location: ../../" . $returnTo);
        exit();
    }
} else {
    // Check if feedback column exists
    $columnExistsQuery = $conn->query("SHOW COLUMNS FROM sit_in_feedback LIKE 'feedback'");
    if ($columnExistsQuery->num_rows == 0) {
        // Add the feedback column if it doesn't exist
        $alterTableQuery = "ALTER TABLE sit_in_feedback ADD COLUMN feedback TEXT AFTER rating";
        
        if (!$conn->query($alterTableQuery)) {
            $_SESSION['feedback_message'] = "Error adding feedback column: " . $conn->error;
            $_SESSION['feedback_status'] = "error";
            header("Location: ../../" . $returnTo);
            exit();
        }
    }
}

// Format the current date and time
$currentDateTime = date('Y-m-d H:i:s');

try {
    // Insert feedback into database
    $stmt = $conn->prepare("INSERT INTO sit_in_feedback (session_id, user_id, rating, feedback, submitted_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $sessionId, $userId, $rating, $feedback, $currentDateTime);
    
    if ($stmt->execute()) {
        // Set success message
        $_SESSION['feedback_message'] = "Thank you! Your feedback has been submitted successfully.";
        $_SESSION['feedback_status'] = "success";
    } else {
        // Set error message
        $_SESSION['feedback_message'] = "Error submitting feedback: " . $stmt->error;
        $_SESSION['feedback_status'] = "error";
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['feedback_message'] = "Error: " . $e->getMessage();
    $_SESSION['feedback_status'] = "error";
}

$conn->close();

// Redirect back to the appropriate page based on return_to parameter
header("Location: ../../" . $returnTo);
exit();
?>
