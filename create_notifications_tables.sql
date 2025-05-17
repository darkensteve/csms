-- Create notifications table for storing all notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` int(11) NOT NULL AUTO_INCREMENT,
    `recipient_id` int(11) NOT NULL,
    `recipient_type` enum('admin', 'user') NOT NULL,
    `sender_id` int(11) DEFAULT NULL,
    `sender_type` enum('admin', 'user', 'system') NOT NULL DEFAULT 'system',
    `type` varchar(50) NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `link` varchar(255) DEFAULT NULL,
    `is_read` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `read_at` datetime DEFAULT NULL,
    PRIMARY KEY (`notification_id`),
    KEY `recipient_idx` (`recipient_id`, `recipient_type`),
    KEY `is_read_idx` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create notification types table for predefined notification types
CREATE TABLE IF NOT EXISTS `notification_types` (
    `type_id` int(11) NOT NULL AUTO_INCREMENT,
    `type_name` varchar(50) NOT NULL,
    `description` varchar(255) NOT NULL,
    `icon` varchar(50) NOT NULL,
    `color` varchar(20) NOT NULL,
    PRIMARY KEY (`type_id`),
    UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert predefined notification types
INSERT INTO `notification_types` (`type_name`, `description`, `icon`, `color`) VALUES
('points_added', 'Points added to student account', 'fa-star', '#f59e0b'),
('reservation_status', 'Reservation status updated', 'fa-calendar-check', '#3b82f6'),
('new_reservation', 'New reservation request', 'fa-calendar-plus', '#10b981'),
('sit_in_started', 'Sit-in session started', 'fa-user-check', '#10b981'),
('sit_in_ended', 'Sit-in session ended', 'fa-user-clock', '#6366f1'),
('admin_action', 'Admin performed an action', 'fa-user-shield', '#8b5cf6'),
('system', 'System notification', 'fa-info-circle', '#6b7280');

-- Create a function to create notifications
DELIMITER //
CREATE FUNCTION IF NOT EXISTS create_notification(
    p_recipient_id INT,
    p_recipient_type VARCHAR(10),
    p_sender_id INT,
    p_sender_type VARCHAR(10),
    p_type VARCHAR(50),
    p_title VARCHAR(255),
    p_message TEXT,
    p_link VARCHAR(255)
) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE new_notification_id INT;
    
    INSERT INTO notifications (
        recipient_id,
        recipient_type,
        sender_id,
        sender_type,
        type,
        title,
        message,
        link
    ) VALUES (
        p_recipient_id,
        p_recipient_type,
        p_sender_id,
        p_sender_type,
        p_type,
        p_title,
        p_message,
        p_link
    );
    
    SET new_notification_id = LAST_INSERT_ID();
    
    RETURN new_notification_id;
END//
DELIMITER ; 