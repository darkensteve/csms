<?php
session_start();

// Check if user is not logged in as admin
if(!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login_admin.php");
    exit;
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    if(empty($_POST['title']) || empty($_POST['content'])) {
        $_SESSION['announcement_error'] = "Title and content are required.";
        header("Location: admin.php");
        exit;
    }
    
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
    
    // Sanitize input
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $admin_id = $_SESSION['admin_id'] ?? null;
    $admin_username = isset($_SESSION['admin_username']) ? mysqli_real_escape_string($conn, $_SESSION['admin_username']) : 'Admin';
    
    // First, let's check the structure of the announcements table
    $fields_exist = [];
    $fields_to_check = ['admin_id', 'admin_username'];
    
    foreach ($fields_to_check as $field) {
        $check_query = "SHOW COLUMNS FROM announcements LIKE '$field'";
        $result = mysqli_query($conn, $check_query);
        $fields_exist[$field] = mysqli_num_rows($result) > 0;
    }
    
    // Create the appropriate query based on the fields that exist
    if ($fields_exist['admin_id'] && $fields_exist['admin_username']) {
        // Both fields exist
        $query = "INSERT INTO announcements (title, content, admin_id, admin_username) 
                  VALUES ('$title', '$content', " . ($admin_id ? $admin_id : "NULL") . ", '$admin_username')";
    } 
    elseif ($fields_exist['admin_id']) {
        // Only admin_id exists
        $query = "INSERT INTO announcements (title, content, admin_id) 
                  VALUES ('$title', '$content', " . ($admin_id ? $admin_id : "NULL") . ")";
    }
    elseif ($fields_exist['admin_username']) {
        // Only admin_username exists
        $query = "INSERT INTO announcements (title, content, admin_username) 
                  VALUES ('$title', '$content', '$admin_username')";
    }
    else {
        // Neither field exists
        $query = "INSERT INTO announcements (title, content) 
                  VALUES ('$title', '$content')";
    }
    
    // Execute the query
    if (mysqli_query($conn, $query)) {
        $_SESSION['announcement_success'] = "Announcement posted successfully!";
    } else {
        $_SESSION['announcement_error'] = "Error posting announcement: " . mysqli_error($conn);
        
        // If we hit an error, let's try to fix the table and then try again with basic fields
        if (strpos(mysqli_error($conn), "Unknown column") !== false) {
            // Run a quick fix to add missing columns
            require_once('fix_announcements_table.php');
            
            // Try a simplified insert with just title and content
            $basic_query = "INSERT INTO announcements (title, content) VALUES ('$title', '$content')";
            if (mysqli_query($conn, $basic_query)) {
                $_SESSION['announcement_success'] = "Announcement posted successfully! (Note: The database was updated)";
                $_SESSION['announcement_error'] = null;
            }
        }
    }
    
    mysqli_close($conn);
    
    // Redirect back to admin page
    header("Location: admin.php");
    exit;
} else {
    // If not a POST request, redirect to admin page
    header("Location: admin.php");
    exit;
}
?>
