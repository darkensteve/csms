<?php
/**
 * Notification functions for the SitIn system
 * This file contains functions for creating, reading, and managing notifications
 */

// Database connection is required
require_once 'db_connect.php';

/**
 * Ensure that notification tables exist
 */
function ensure_notification_tables_exist() {
    global $conn;
    
    // Check if notifications table exists
    $tables_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if ($tables_check->num_rows == 0) {
        // Load and execute the SQL from create_notifications_tables.sql
        $sql_path = dirname(__FILE__) . '/../create_notifications_tables.sql';
        if (file_exists($sql_path)) {
            $sql = file_get_contents($sql_path);
            // Split the SQL by delimiter statements
            $sql_parts = explode('DELIMITER', $sql);
            
            // Execute the first part (tables creation)
            $conn->multi_query($sql_parts[0]);
            while ($conn->more_results() && $conn->next_result()) {
                // Clear any result sets
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            }
            
            // Now handle the function creation with proper delimiter
            if (count($sql_parts) > 1) {
                $function_sql = trim(substr($sql_parts[1], strpos($sql_parts[1], 'CREATE FUNCTION')));
                $function_sql = substr($function_sql, 0, strrpos($function_sql, 'END//'));
                $function_sql .= 'END';
                $conn->query($function_sql);
            }
            
            error_log("Notification tables created successfully");
        } else {
            error_log("Could not find create_notifications_tables.sql file");
        }
    }
}

// Ensure notification tables exist when this file is included
ensure_notification_tables_exist();

/**
 * Create a new notification
 * 
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @param int $sender_id ID of the sender (optional)
 * @param string $sender_type Type of sender ('admin', 'user', or 'system')
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Link to related content (optional)
 * @return int|false The new notification ID or false on failure
 */
function create_notification($recipient_id, $recipient_type, $sender_id, $sender_type, $type, $title, $message, $link = null) {
    global $conn;
    
    $query = "INSERT INTO notifications (
                recipient_id, 
                recipient_type, 
                sender_id, 
                sender_type, 
                type, 
                title, 
                message, 
                link
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('isisisss', 
        $recipient_id, 
        $recipient_type, 
        $sender_id, 
        $sender_type, 
        $type, 
        $title, 
        $message, 
        $link
    );
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        return false;
    }
}

/**
 * Get notifications for a specific recipient
 * 
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @param bool $unread_only Get only unread notifications
 * @param int $limit Maximum number of notifications to return
 * @return array Notifications
 */
function get_notifications($recipient_id, $recipient_type, $unread_only = false, $limit = 10) {
    global $conn;
    
    $where_clause = "WHERE recipient_id = ? AND recipient_type = ?";
    if ($unread_only) {
        $where_clause .= " AND is_read = 0";
    }
    
    $query = "SELECT * FROM notifications 
              $where_clause 
              ORDER BY created_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('isi', $recipient_id, $recipient_type, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id ID of the notification
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @return bool Success status
 */
function mark_notification_read($notification_id, $recipient_id, $recipient_type) {
    global $conn;
    
    $query = "UPDATE notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE notification_id = ? AND recipient_id = ? AND recipient_type = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('iis', $notification_id, $recipient_id, $recipient_type);
    
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a recipient
 * 
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @return bool Success status
 */
function mark_all_notifications_read($recipient_id, $recipient_type) {
    global $conn;
    
    $query = "UPDATE notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('is', $recipient_id, $recipient_type);
    
    return $stmt->execute();
}

/**
 * Count unread notifications for a recipient
 * 
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @return int Number of unread notifications
 */
function count_unread_notifications($recipient_id, $recipient_type) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM notifications 
              WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('is', $recipient_id, $recipient_type);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return (int)$row['count'];
}

/**
 * Delete a notification
 * 
 * @param int $notification_id ID of the notification
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @return bool Success status
 */
function delete_notification($notification_id, $recipient_id, $recipient_type) {
    global $conn;
    
    $query = "DELETE FROM notifications 
              WHERE notification_id = ? AND recipient_id = ? AND recipient_type = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('iis', $notification_id, $recipient_id, $recipient_type);
    
    return $stmt->execute();
}

/**
 * Delete all notifications for a recipient
 * 
 * @param int $recipient_id ID of the recipient
 * @param string $recipient_type Type of recipient ('admin' or 'user')
 * @return bool Success status
 */
function delete_all_notifications($recipient_id, $recipient_type) {
    global $conn;
    
    $query = "DELETE FROM notifications 
              WHERE recipient_id = ? AND recipient_type = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('is', $recipient_id, $recipient_type);
    
    return $stmt->execute();
}

/**
 * Get notification details for display, including sender info
 * 
 * @param array $notification Notification data from database
 * @return array Notification with additional details
 */
function get_notification_details($notification) {
    global $conn;
    
    // Get sender information if available
    if (!empty($notification['sender_id'])) {
        if ($notification['sender_type'] === 'admin') {
            $sender_query = "SELECT username, profile_pic FROM admin WHERE admin_id = ?";
        } else {
            $sender_query = "SELECT CONCAT(FIRSTNAME, ' ', LASTNAME) as username, PROFILE_PICTURE as profile_pic FROM users WHERE USER_ID = ?";
        }
        
        $stmt = $conn->prepare($sender_query);
        if ($stmt) {
            $stmt->bind_param('i', $notification['sender_id']);
            $stmt->execute();
            $sender = $stmt->get_result()->fetch_assoc();
            
            if ($sender) {
                $notification['sender_name'] = $sender['username'];
                $notification['sender_pic'] = $sender['profile_pic'];
            }
        }
    }
    
    // Get notification type details
    $type_query = "SELECT icon, color FROM notification_types WHERE type_name = ?";
    $type_stmt = $conn->prepare($type_query);
    
    if ($type_stmt) {
        $type_stmt->bind_param('s', $notification['type']);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        
        if ($type_result->num_rows > 0) {
            $type_data = $type_result->fetch_assoc();
            $notification['icon'] = $type_data['icon'];
            $notification['color'] = $type_data['color'];
        } else {
            // Default icon and color
            $notification['icon'] = 'fa-bell';
            $notification['color'] = '#6b7280';
        }
    }
    
    // Format time ago
    $notification['time_ago'] = time_elapsed_string($notification['created_at']);
    
    return $notification;
}

/**
 * Create a human-readable time elapsed string (e.g., "2 minutes ago")
 * 
 * @param string $datetime Date/time string
 * @return string Human-readable time difference
 */
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Helper function to create a notification when points are added to a student
 * 
 * @param int $student_id Student ID
 * @param int $points Points added
 * @param string $reason Reason for adding points
 * @param int $admin_id Admin ID
 * @param string $admin_username Admin username
 * @return int|false The new notification ID or false on failure
 */
function notify_points_added($student_id, $points, $reason, $admin_id, $admin_username) {
    $title = "Points Added";
    $message = "You received $points points from $admin_username. Reason: $reason";
    $link = "history.php"; // Link to student's history page
    
    return create_notification(
        $student_id,
        'user',
        $admin_id,
        'admin',
        'points_added',
        $title,
        $message,
        $link
    );
}

/**
 * Helper function to create a notification when a reservation status is updated
 * 
 * @param int $student_id Student ID
 * @param int $reservation_id Reservation ID
 * @param string $status New reservation status
 * @param int $admin_id Admin ID
 * @param string $admin_username Admin username
 * @return int|false The new notification ID or false on failure
 */
function notify_reservation_status($student_id, $reservation_id, $status, $admin_id, $admin_username) {
    $status_text = ucfirst($status);
    $title = "Reservation $status_text";
    $message = "Your reservation #$reservation_id has been $status_text by $admin_username.";
    $link = "reservation.php?id=$reservation_id"; // Link to the reservation
    
    return create_notification(
        $student_id,
        'user',
        $admin_id,
        'admin',
        'reservation_status',
        $title,
        $message,
        $link
    );
}

/**
 * Helper function to create a notification when a new reservation is created
 * 
 * @param int $reservation_id Reservation ID
 * @param int $student_id Student ID
 * @param string $student_name Student name
 * @param string $lab_name Lab name
 * @param string $date Reservation date
 * @return int|false The new notification ID or false on failure
 */
function notify_new_reservation($reservation_id, $student_id, $student_name, $lab_name, $date) {
    // Get admin IDs (all admins will receive this notification)
    global $conn;
    $admins = [];
    
    $admin_query = "SELECT admin_id FROM admin";
    $admin_result = $conn->query($admin_query);
    
    if ($admin_result) {
        while ($admin = $admin_result->fetch_assoc()) {
            $admins[] = $admin['admin_id'];
        }
    }
    
    $title = "New Reservation Request";
    $message = "$student_name has requested a reservation for $lab_name on $date.";
    $link = "reservation/reservation.php"; // Link to the reservation management page
    
    $notification_ids = [];
    
    // Create a notification for each admin
    foreach ($admins as $admin_id) {
        $notification_id = create_notification(
            $admin_id,
            'admin',
            $student_id,
            'user',
            'new_reservation',
            $title,
            $message,
            $link
        );
        
        if ($notification_id) {
            $notification_ids[] = $notification_id;
        }
    }
    
    return !empty($notification_ids) ? $notification_ids : false;
}

/**
 * Helper function to create a notification when a sit-in session starts
 * 
 * @param int $session_id Session ID
 * @param int $student_id Student ID
 * @param string $student_name Student name
 * @param string $lab_name Lab name
 * @param int $admin_id Admin ID
 * @param string $admin_username Admin username
 * @return int|false The new notification ID or false on failure
 */
function notify_sitin_started($session_id, $student_id, $student_name, $lab_name, $admin_id, $admin_username) {
    $title = "Sit-In Session Started";
    $message = "Your sit-in session in $lab_name has been started by $admin_username.";
    $link = "history.php"; // Link to student's history page
    
    return create_notification(
        $student_id,
        'user',
        $admin_id,
        'admin',
        'sit_in_started',
        $title,
        $message,
        $link
    );
}
?> 