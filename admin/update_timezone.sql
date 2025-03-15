-- Script to update database timezone settings for Asia/Manila (GMT+8)

-- Set session timezone to Asia/Manila (GMT+8)
SET time_zone = '+08:00';

-- Try to set global timezone (requires privileges)
SET GLOBAL time_zone = '+08:00';

-- Create timezone initialization event to ensure timezone is set when MySQL restarts
-- This requires EVENT privileges
DELIMITER //
CREATE EVENT IF NOT EXISTS set_timezone_event
ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY
ON COMPLETION PRESERVE
DO
BEGIN
    SET GLOBAL time_zone = '+08:00';
END//
DELIMITER ;

-- Modify the sit_in_sessions table columns to ensure they handle timezone information properly
ALTER TABLE `sit_in_sessions` 
    MODIFY COLUMN `check_in_time` DATETIME NOT NULL,
    MODIFY COLUMN `check_out_time` DATETIME NULL,
    MODIFY COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing timestamps to Manila time if they were stored as UTC
-- IMPORTANT: ONLY run this once! Otherwise timestamps will be shifted multiple times
UPDATE `sit_in_sessions` 
   SET `check_in_time` = CONVERT_TZ(`check_in_time`, '+00:00', '+08:00'),
       `check_out_time` = CONVERT_TZ(`check_out_time`, '+00:00', '+08:00')
 WHERE `check_in_time` IS NOT NULL;
