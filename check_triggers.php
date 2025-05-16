<?php
// Include database connection
require_once 'includes/db_connect.php';

// List all triggers in the database
echo "<h2>All triggers in database:</h2>";
$all_triggers = $conn->query("SHOW TRIGGERS");

if ($all_triggers && $all_triggers->num_rows > 0) {
    echo "<ul>";
    while ($trigger = $all_triggers->fetch_assoc()) {
        echo "<li>Trigger: " . $trigger['Trigger'] . " on table " . $trigger['Table'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No triggers found in the database.</p>";
}

// Drop the convert_points_to_sessions trigger
echo "<h2>Attempting to drop trigger:</h2>";
$drop_trigger = $conn->query("DROP TRIGGER IF EXISTS convert_points_to_sessions");

if ($drop_trigger) {
    echo "<p>SUCCESS: Trigger 'convert_points_to_sessions' has been dropped.</p>";
} else {
    echo "<p>ERROR: Failed to drop trigger: " . $conn->error . "</p>";
}

// Check again to confirm trigger is gone
echo "<h2>Checking if trigger was removed:</h2>";
$verify = $conn->query("SHOW TRIGGERS LIKE 'convert_points_to_sessions'");
if ($verify && $verify->num_rows > 0) {
    echo "<p>WARNING: Trigger still exists!</p>";
} else {
    echo "<p>CONFIRMED: Trigger has been removed successfully.</p>";
}

// Show all triggers again to confirm
echo "<h2>Remaining triggers in database:</h2>";
$all_triggers = $conn->query("SHOW TRIGGERS");

if ($all_triggers && $all_triggers->num_rows > 0) {
    echo "<ul>";
    while ($trigger = $all_triggers->fetch_assoc()) {
        echo "<li>Trigger: " . $trigger['Trigger'] . " on table " . $trigger['Table'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No triggers found in the database.</p>";
}

// Show a link to run add_points.php with debug mode
echo "<p><a href='admin/students/add_points.php?debug=1'>Test add_points.php with debug mode</a></p>";
?> 