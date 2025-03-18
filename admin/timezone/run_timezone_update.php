<?php
// This script runs the necessary SQL commands to ensure the database is storing times in the correct timezone

// Include database connection
require_once '../includes/db_connect.php';

// Set PHP timezone to Manila
date_default_timezone_set('Asia/Manila');

echo "<h1>Database Timezone Update for Asia/Manila (GMT+8)</h1>";
echo "<p>This script will modify your database to handle timezone information correctly.</p>";

// Check current PHP and MySQL timezone settings
echo "<h2>Current Settings:</h2>";
echo "<p>PHP Timezone: " . date_default_timezone_get() . "</p>";
echo "<p>Current PHP Time: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Get current MySQL timezone
    $result = $conn->query("SELECT @@session.time_zone AS timezone");
    $row = $result->fetch_assoc();
    echo "<p>Current MySQL Timezone: " . $row['timezone'] . "</p>";
} catch(Exception $e) {
    echo "<p>Error checking MySQL timezone: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Set session timezone for MySQL
try {
    $conn->query("SET time_zone = '+08:00'");
    $result = $conn->query("SELECT @@session.time_zone AS timezone");
    $row = $result->fetch_assoc();
    echo "<p>✓ Successfully set session timezone to: " . $row['timezone'] . "</p>";
} catch(Exception $e) {
    echo "<p>❌ Error setting session timezone: " . $e->getMessage() . "</p>";
}

// Try to set global timezone
try {
    $conn->query("SET GLOBAL time_zone = '+08:00'");
    echo "<p>✓ Successfully set global timezone to +08:00 (Manila)</p>";
} catch(Exception $e) {
    echo "<p>⚠️ Could not set global timezone (requires privileges): " . $e->getMessage() . "</p>";
    echo "<p>Your database administrator should run: <code>SET GLOBAL time_zone = '+08:00';</code></p>";
}

// Check if sit_in_sessions table exists
try {
    $result = $conn->query("SHOW TABLES LIKE 'sit_in_sessions'");
    if ($result->num_rows == 0) {
        echo "<p>❌ Table 'sit_in_sessions' does not exist!</p>";
    } else {
        echo "<p>✓ Table 'sit_in_sessions' found.</p>";
        
        // Get current table structure
        echo "<h2>Current Table Structure:</h2>";
        $result = $conn->query("DESCRIBE sit_in_sessions");
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . " " . $row['Null'] . " " . $row['Default'] . "\n";
        }
        echo "</pre>";
        
        // Ask for confirmation to alter table
        echo "<h2>Ready to Update</h2>";
        echo "<p>The next step will alter the 'sit_in_sessions' table to ensure proper timezone handling.</p>";
        echo "<p>It will:</p>";
        echo "<ol>";
        echo "<li>Ensure the check_in_time and check_out_time columns are properly formatted</li>";
        echo "<li>Convert any existing timestamps to Manila time (GMT+8)</li>";
        echo "</ol>";
        
        echo "<div style='margin: 20px 0; padding: 10px; border: 2px solid red; color: red;'>";
        echo "<strong>Warning:</strong> Please make a backup of your database before proceeding. This operation changes your data.";
        echo "</div>";
        
        echo "<form action='run_timezone_update.php?confirm=1' method='post'>";
        echo "<button type='submit' style='background-color: #0284c7; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>Execute Table Update</button>";
        echo "</form>";
        
        // If confirmed, run the update
        if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
            try {
                // Modify table structure
                $conn->query("ALTER TABLE `sit_in_sessions` 
                             MODIFY COLUMN `check_in_time` DATETIME NOT NULL,
                             MODIFY COLUMN `check_out_time` DATETIME NULL");
                echo "<p>✓ Table structure updated successfully</p>";
                
                // Check if we've already executed this update by looking for a flag in the database
                $flagQuery = "SELECT * FROM `system_flags` WHERE `flag_name` = 'timezone_conversion_done' LIMIT 1";
                $result = $conn->query($flagQuery);
                
                if (!$result || $result->num_rows == 0) {
                    // Update timezone values in existing records
                    $conn->query("UPDATE `sit_in_sessions` 
                               SET `check_in_time` = CONVERT_TZ(`check_in_time`, '+00:00', '+08:00'),
                               `check_out_time` = CONVERT_TZ(`check_out_time`, '+00:00', '+08:00')
                             WHERE `check_in_time` IS NOT NULL");
                    
                    // Create flags table if it doesn't exist and set flag to prevent duplicate conversions
                    $conn->query("CREATE TABLE IF NOT EXISTS `system_flags` (
                                `id` INT AUTO_INCREMENT PRIMARY KEY,
                                `flag_name` VARCHAR(100) NOT NULL,
                                `flag_value` VARCHAR(255) NOT NULL,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                              )");
                    
                    $conn->query("INSERT INTO `system_flags` (`flag_name`, `flag_value`) VALUES ('timezone_conversion_done', 'true')");
                    
                    echo "<p>✓ Existing timestamps converted to Manila timezone</p>";
                    echo "<p>✓ Added protection against multiple conversions</p>";
                } else {
                    echo "<p>⚠️ Timestamp conversion already performed previously. Skipping to prevent duplicate timezone shifts.</p>";
                }
                
                echo "<h2>Update Complete!</h2>";
                echo "<p>Your database is now set up correctly for Manila/Asia timezone (GMT+8).</p>";
            } catch(Exception $e) {
                echo "<p>❌ Error updating table: " . $e->getMessage() . "</p>";
            }
        }
    }
} catch(Exception $e) {
    echo "<p>Error checking for table: " . $e->getMessage() . "</p>";
}

// Test the timezone conversion
echo "<h2>Timezone Test</h2>";
$now = date('Y-m-d H:i:s');
echo "<p>Current PHP time: " . $now . "</p>";

try {
    $result = $conn->query("SELECT NOW() as mysql_time");
    $row = $result->fetch_assoc();
    echo "<p>Current MySQL time: " . $row['mysql_time'] . "</p>";
} catch(Exception $e) {
    echo "<p>Error getting MySQL time: " . $e->getMessage() . "</p>";
}
?>