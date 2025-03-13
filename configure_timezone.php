<?php
// Script to configure and check timezone settings

// Define desired timezone
$desired_timezone = 'Asia/Manila';

// 1. Configure PHP timezone
date_default_timezone_set($desired_timezone);

// 2. Configure MySQL timezone via connection
require_once 'includes/db_connect.php';

// Set session timezone to Asia/Manila (GMT+8)
$conn->query("SET time_zone = '+08:00'");

// Check MySQL timezone settings
$result = $conn->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as mysql_time");
$row = $result->fetch_assoc();

echo "<h1>Timezone Configuration</h1>";

echo "<h2>PHP Settings</h2>";
echo "<p>PHP timezone: " . date_default_timezone_get() . "</p>";
echo "<p>Current PHP time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>MySQL Settings</h2>";
echo "<p>MySQL Global timezone: " . $row['global_tz'] . "</p>";
echo "<p>MySQL Session timezone: " . $row['session_tz'] . "</p>";
echo "<p>Current MySQL time: " . $row['mysql_time'] . "</p>";

echo "<h2>PHP DateTime Test</h2>";
$now = new DateTime('now', new DateTimeZone($desired_timezone));
echo "<p>DateTime 'now' with explicit timezone: " . $now->format('Y-m-d H:i:s P') . "</p>";

// Create a test record
$conn->query("CREATE TABLE IF NOT EXISTS timezone_test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_time DATETIME,
    test_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("INSERT INTO timezone_test (test_time) VALUES (NOW())");

$test_result = $conn->query("SELECT * FROM timezone_test ORDER BY id DESC LIMIT 1");
$test_row = $test_result->fetch_assoc();

echo "<h2>Database Time Test</h2>";
echo "<p>Stored DATETIME: " . $test_row['test_time'] . "</p>";
echo "<p>Stored TIMESTAMP: " . $test_row['test_timestamp'] . "</p>";

// Verify our timezone handling works properly
$db_time = new DateTime($test_row['test_time']);
$db_time->setTimezone(new DateTimeZone($desired_timezone));

echo "<p>PHP interpretation of DB DATETIME (Manila): " . $db_time->format('Y-m-d H:i:s P') . "</p>";

echo "<h2>Fix Instructions</h2>";
echo "<p>If MySQL timezone is not correctly set to +08:00, run the update_timezone.sql script.</p>";
echo "<p>If times in your application still appear wrong, check if the db_connect.php file is setting timezones.</p>";
?>
