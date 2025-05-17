<?php
// Test file to debug notification functions include
session_start();
$_SESSION['admin_id'] = 1; 
$_SESSION['is_admin'] = true;
$_SESSION['admin_username'] = 'Test Admin';

// Database connection
echo "Attempting to include db_connect.php from ../includes/db_connect.php<br>";
$include_path1 = "../includes/db_connect.php";
if (file_exists($include_path1)) {
    echo "File exists at path: $include_path1<br>";
    require_once $include_path1;
    echo "DB connection included successfully<br>";
} else {
    echo "File DOES NOT exist at path: $include_path1<br>";
}

echo "<hr>";

// Try from root folder
echo "Attempting to include db_connect.php from ../../includes/db_connect.php<br>";
$include_path2 = "../../includes/db_connect.php";
if (file_exists($include_path2)) {
    echo "File exists at path: $include_path2<br>";
} else {
    echo "File DOES NOT exist at path: $include_path2<br>";
}

echo "<hr>";

// Include notification functions
echo "Attempting to include notification_functions.php from ../includes/notification_functions.php<br>";
$include_path3 = "../includes/notification_functions.php";
if (file_exists($include_path3)) {
    echo "File exists at path: $include_path3<br>";
    require_once $include_path3;
    echo "Notification functions included successfully<br>";
    
    echo "Testing notification_functions:<br>";
    
    // Try to create a test notification
    $notify_result = notify_reservation_status(
        1, // user_id
        123, // reservation_id
        'test', // status
        1, // admin_id
        'Test Admin' // admin_username
    );
    
    echo "Notification creation result: " . ($notify_result ? "Success! ID: $notify_result" : "Failed") . "<br>";
    
} else {
    echo "File DOES NOT exist at path: $include_path3<br>";
}

echo "<hr>";

// Try from root folder
echo "Attempting to include notification_functions.php from ../../includes/notification_functions.php<br>";
$include_path4 = "../../includes/notification_functions.php";
if (file_exists($include_path4)) {
    echo "File exists at path: $include_path4<br>";
} else {
    echo "File DOES NOT exist at path: $include_path4<br>";
}

// Try direct PHP path
echo "<hr>";
echo "Server document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current file: " . __FILE__ . "<br>";
echo "Current directory: " . __DIR__ . "<br>";

?> 