-- Add points column to users table
ALTER TABLE `users` ADD COLUMN `points` INT NOT NULL DEFAULT 0;

-- Note: We previously had a trigger here to automatically convert points to sessions
-- However, MySQL doesn't allow updating the same table within a trigger that was invoked by that table
-- The points conversion is now handled directly in PHP code (see admin/students/add_points.php)

-- Create points_log table if it doesn't exist
CREATE TABLE IF NOT EXISTS `points_log` (
    `log_id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` varchar(50) NOT NULL,
    `points_added` int(11) NOT NULL,
    `reason` text DEFAULT NULL,
    `admin_id` int(11) NOT NULL,
    `admin_username` varchar(100) NOT NULL,
    `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
