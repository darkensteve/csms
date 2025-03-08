<?php
// Database connection
$db_host = "localhost";
$db_user = "root"; 
$db_pass = "";
$db_name = "csms";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// First, check if the table exists and if so, drop it
$check_table = "SHOW TABLES LIKE 'announcements'";
$result = mysqli_query($conn, $check_table);
if (mysqli_num_rows($result) > 0) {
    $drop_table = "DROP TABLE announcements";
    if (mysqli_query($conn, $drop_table)) {
        echo "Existing announcements table dropped.<br>";
    } else {
        echo "Error dropping table: " . mysqli_error($conn) . "<br>";
    }
}

// SQL to create announcements table with proper fields
$sql = "CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    admin_id INT,
    admin_username VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "Announcements table created successfully!";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
