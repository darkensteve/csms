<?php
// Check if this file is being included from another file
$is_included = !debug_backtrace() ? false : true;

// Database connection
$db_host = "localhost";
$db_user = "root"; 
$db_pass = "";
$db_name = "csms";

// Only create a connection if not already connected (when included from another file)
if ($is_included && isset($conn) && $conn) {
    // Use the existing connection
    $existing_connection = true;
} else {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    $existing_connection = false;
    
    // Check connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// Check if the table exists
$check_table = "SHOW TABLES LIKE 'announcements'";
$result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($result) > 0) {
    // Check if admin_id column exists
    $check_admin_id = "SHOW COLUMNS FROM announcements LIKE 'admin_id'";
    $result = mysqli_query($conn, $check_admin_id);
    
    if (mysqli_num_rows($result) == 0) {
        // Add admin_id column if it doesn't exist
        $alter_table = "ALTER TABLE announcements ADD COLUMN admin_id INT AFTER content";
        if (mysqli_query($conn, $alter_table)) {
            if (!$is_included) echo "Added admin_id column to announcements table.<br>";
        } else {
            if (!$is_included) echo "Error adding admin_id column: " . mysqli_error($conn) . "<br>";
        }
    } else {
        if (!$is_included) echo "admin_id column already exists in announcements table.<br>";
    }
    
    // Check if admin_username column exists
    $check_username = "SHOW COLUMNS FROM announcements LIKE 'admin_username'";
    $result = mysqli_query($conn, $check_username);
    
    if (mysqli_num_rows($result) == 0) {
        // Add admin_username column if it doesn't exist
        $alter_table = "ALTER TABLE announcements ADD COLUMN admin_username VARCHAR(100) AFTER admin_id";
        if (mysqli_query($conn, $alter_table)) {
            if (!$is_included) echo "Added admin_username column to announcements table.<br>";
        } else {
            if (!$is_included) echo "Error adding admin_username column: " . mysqli_error($conn) . "<br>";
        }
    } else {
        if (!$is_included) echo "admin_username column already exists in announcements table.<br>";
    }
    
    if (!$is_included) echo "Announcements table structure has been verified and fixed if needed.";
} else {
    // Create the table if it doesn't exist
    $create_table = "CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        admin_id INT,
        admin_username VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_table)) {
        if (!$is_included) echo "Announcements table created successfully!";
    } else {
        if (!$is_included) echo "Error creating announcements table: " . mysqli_error($conn);
    }
}

// Only close connection if we created it
if (!$existing_connection) {
    mysqli_close($conn);
}
?>
