<?php
// Script to verify and fix timezone settings for Manila/Asia (GMT+8)

// Set PHP timezone
date_default_timezone_set('Asia/Manila');
echo "<h2>Timezone Configuration Tool</h2>";
echo "<p>Current PHP timezone: " . date_default_timezone_get() . "</p>";
echo "<p>Current PHP time: " . date('Y-m-d H:i:s') . "</p>";

// Include database connection
if (file_exists('includes/db_connect.php')) {
    require_once 'includes/db_connect.php';
    echo "<p>Database connection included successfully.</p>";
} else {
    die("<p>Error: Database connection file not found.</p>");
}

// Run SQL to set timezone
try {
    // Set session timezone
    $conn->query("SET time_zone = '+08:00'");
    echo "<p>Successfully set session timezone to +08:00 (Manila/Asia).</p>";
    
    // Verify MySQL time
    $result = $conn->query("SELECT NOW() as mysql_time");
    $row = $result->fetch_assoc();
    echo "<p>Current MySQL time: " . $row['mysql_time'] . "</p>";
    
    // Create test timestamp in database
    $conn->query("DROP TABLE IF EXISTS timezone_test");
    $conn->query("CREATE TEMPORARY TABLE timezone_test (id INT AUTO_INCREMENT PRIMARY KEY, test_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("INSERT INTO timezone_test (id) VALUES (NULL)");
    
    // Retrieve the timestamp
    $result = $conn->query("SELECT test_time FROM timezone_test");
    $row = $result->fetch_assoc();
    echo "<p>Test timestamp from database: " . $row['test_time'] . "</p>";
    
    // Show conversion example
    $db_time = new DateTime($row['test_time']);
    $db_time->setTimezone(new DateTimeZone('Asia/Manila'));
    echo "<p>Converted to Manila time: " . $db_time->format('Y-m-d H:i:s') . "</p>";
    
    // Check if there's a mismatch
    $php_time = new DateTime();
    $diff_minutes = ($php_time->getTimestamp() - $db_time->getTimestamp()) / 60;
    
    if (abs($diff_minutes) > 5) {
        echo "<div style='color:red; font-weight:bold;'>
                <p>WARNING: There appears to be a significant time difference between PHP and MySQL.</p>
                <p>Difference: approximately " . round($diff_minutes) . " minutes</p>
              </div>";
    } else {
        echo "<p style='color:green; font-weight:bold;'>✓ PHP and MySQL times are synchronized correctly.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// Fix options
echo "<h3>Fix Options:</h3>";
echo "<p>1. To run the SQL script to update your database timezone settings, visit: <a href='run_timezone_update.php'>run_timezone_update.php</a></p>";
echo "<p>2. To update timestamps in existing records (make backup first!):</p>";
echo "<pre>
UPDATE `sit_in_sessions` 
   SET `check_in_time` = DATE_ADD(`check_in_time`, INTERVAL 8 HOUR),
       `check_out_time` = DATE_ADD(`check_out_time`, INTERVAL 8 HOUR)
 WHERE `check_in_time` IS NOT NULL;
</pre>";

// Explain timezone process
echo "<h3>How timezone handling works in this system:</h3>";
echo "<ol>
        <li>PHP datetime: We set PHP's timezone to 'Asia/Manila'</li>
        <li>MySQL connection: We set the session timezone to '+08:00' (GMT+8)</li>
        <li>Data display: We force all displayed dates to Manila timezone using DateTime and setTimezone</li>
      </ol>";

// Testing format_time_gmt8 function 
echo "<h3>Function Test:</h3>";

function test_format_time_gmt8($datetime_string) {
    if (empty($datetime_string)) return '';
    
    // Force conversion to Manila timezone regardless of stored format
    $dt = new DateTime($datetime_string);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    
    // Format time in 12-hour format with AM/PM in Manila local time
    return $dt->format('h:i A');
}

$now = date('Y-m-d H:i:s');
echo "<p>Current time: $now</p>";
echo "<p>Formatted with format_time_gmt8: " . test_format_time_gmt8($now) . "</p>";
?>

<p><a href="#" onclick="window.location.reload(); return false;" style="display:inline-block; padding:8px 16px; background-color:#0284c7; color:white; text-decoration:none; border-radius:4px;">Refresh Page</a></p>
