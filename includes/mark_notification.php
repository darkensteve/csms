<?php
/**
 * Ajax handler for marking notifications as read
 */

// Start session
session_start();

// Include the notification functions
require_once 'notification_functions.php';

// Check if user is logged in (either admin or user)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Determine recipient type and ID
$recipient_type = isset($_SESSION['admin_id']) ? 'admin' : 'user';
$recipient_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['user_id'];

// Override recipient type if specified in request (for security, verify it matches session)
if (isset($_POST['type']) && ($_POST['type'] === 'admin' || $_POST['type'] === 'user')) {
    // Only allow admin type if admin is logged in
    if ($_POST['type'] === 'admin' && !isset($_SESSION['admin_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized type']);
        exit;
    }
    
    // Only allow user type if user is logged in
    if ($_POST['type'] === 'user' && !isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized type']);
        exit;
    }
    
    $recipient_type = $_POST['type'];
}

// Mark a single notification as read
if (isset($_POST['notification_id']) && is_numeric($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    $result = mark_notification_read($notification_id, $recipient_id, $recipient_type);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all']) && $_POST['mark_all'] == 1) {
    $result = mark_all_notifications_read($recipient_id, $recipient_type);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit;
}

// If we get here, no valid action was specified
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 