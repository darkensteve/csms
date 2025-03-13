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

-- If your timestamps were stored as UTC time but should have been Manila time,
-- you can convert them with this (uncomment if needed)
-- UPDATE `sit_in_sessions` 
--    SET `check_in_time` = DATE_ADD(`check_in_time`, INTERVAL 8 HOUR),
--        `check_out_time` = DATE_ADD(`check_out_time`, INTERVAL 8 HOUR)
--  WHERE `check_in_time` IS NOT NULL;
