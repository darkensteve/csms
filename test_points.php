<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if we're in debug mode
$debug = isset($_GET['debug']) && $_GET['debug'] == 1;

// Check if the trigger exists (it should be gone now)
$trigger_check = $conn->query("SHOW TRIGGERS LIKE 'convert_points_to_sessions'");
if ($trigger_check && $trigger_check->num_rows > 0) {
    echo "<p style='color: red;'>WARNING: The trigger still exists! Please run check_triggers.php to remove it.</p>";
} else {
    echo "<p style='color: green;'>GOOD: No trigger found.</p>";
}

// Function to simulate adding points
function simulateAddPoints($conn, $student_id, $points_to_add, $id_column = 'USER_ID') {
    echo "<h3>Simulating adding $points_to_add points to student $student_id (using column $id_column)</h3>";
    
    // Get current points
    $get_points_query = "SELECT `points`, `remaining_sessions` FROM `users` WHERE `$id_column` = ?";
    $points_stmt = $conn->prepare($get_points_query);
    $points_stmt->bind_param('s', $student_id);
    $points_stmt->execute();
    $result = $points_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p style='color: red;'>ERROR: Student with $id_column = $student_id not found!</p>";
        return null;
    }
    
    $user_data = $result->fetch_assoc();
    $current_points = $user_data['points'] ?? 0;
    $current_sessions = $user_data['remaining_sessions'] ?? 0;
    
    echo "<p>Current points: $current_points</p>";
    echo "<p>Current sessions: $current_sessions</p>";
    
    // Add the new points
    $new_points = $current_points + $points_to_add;
    
    // Calculate points to convert to sessions
    $points_to_convert = floor($new_points / 3) * 3;
    $sessions_to_add = floor($new_points / 3);
    $remaining_points = $new_points - $points_to_convert;
    
    echo "<p>New points total: $new_points</p>";
    echo "<p>Points to convert: $points_to_convert</p>";
    echo "<p>Sessions to add: $sessions_to_add</p>";
    echo "<p>Remaining points: $remaining_points</p>";
    
    // No actual database changes in simulation mode
    return [
        'current_points' => $current_points,
        'current_sessions' => $current_sessions,
        'new_points' => $new_points,
        'points_to_convert' => $points_to_convert,
        'sessions_to_add' => $sessions_to_add,
        'remaining_points' => $remaining_points,
        'id_column' => $id_column
    ];
}

// Get all possible ID columns from the users table
$columns = $conn->query("SHOW COLUMNS FROM `users`");
$id_columns = [];
while ($column = $columns->fetch_assoc()) {
    if (strpos(strtoupper($column['Field']), 'ID') !== false) {
        $id_columns[] = $column['Field'];
    }
}

// Display a test form
echo "<h2>Test Points Conversion Logic</h2>";
echo "<form method='post'>";
echo "<label for='student_id'>Student ID:</label>";
echo "<input type='text' name='student_id' value='1' required><br>";

echo "<label for='id_column'>ID Column:</label>";
echo "<select name='id_column'>";
foreach ($id_columns as $column) {
    $selected = ($column == 'USER_ID') ? 'selected' : '';
    echo "<option value='$column' $selected>$column</option>";
}
echo "</select><br>";

echo "<label for='points'>Points to add:</label>";
echo "<input type='number' name='points' value='3' min='1' max='10' required><br>";
echo "<input type='submit' name='test' value='Test Conversion Logic'>";
echo "</form>";

// Process form submission
if (isset($_POST['test'])) {
    $student_id = $_POST['student_id'];
    $id_column = $_POST['id_column'];
    $points = (int)$_POST['points'];
    
    // Run the simulation
    $result = simulateAddPoints($conn, $student_id, $points, $id_column);
    
    if ($result) {
        // Show the actual code that would run
        echo "<h3>PHP Code for Points Conversion:</h3>";
        echo "<pre>";
        echo "// Get current points\n";
        echo "\$current_points = {$result['current_points']};\n";
        echo "\$current_sessions = {$result['current_sessions']};\n\n";
        echo "// Add new points\n";
        echo "\$new_points = \$current_points + $points; // = {$result['new_points']}\n\n";
        echo "// Calculate conversions\n";
        echo "\$points_to_convert = floor(\$new_points / 3) * 3; // = {$result['points_to_convert']}\n";
        echo "\$sessions_to_add = floor(\$new_points / 3); // = {$result['sessions_to_add']}\n";
        echo "\$remaining_points = \$new_points - \$points_to_convert; // = {$result['remaining_points']}\n\n";
        echo "// Update database\n";
        if ($result['points_to_convert'] > 0) {
            echo "UPDATE users SET \n";
            echo "    points = {$result['remaining_points']},\n";
            echo "    remaining_sessions = remaining_sessions + {$result['sessions_to_add']}\n";
            echo "WHERE {$result['id_column']} = '$student_id';\n";
        } else {
            echo "UPDATE users SET points = {$result['new_points']} WHERE {$result['id_column']} = '$student_id';\n";
        }
        echo "</pre>";
        
        // If debug mode is on, actually update the database
        if ($debug) {
            echo "<h3>DEBUG MODE: Actually updating the database</h3>";
            
            try {
                $conn->begin_transaction();
                
                if ($result['points_to_convert'] > 0) {
                    $update_query = "UPDATE `users` SET 
                                    `points` = ?,
                                    `remaining_sessions` = `remaining_sessions` + ?
                                    WHERE `{$result['id_column']}` = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param('iis', $result['remaining_points'], $result['sessions_to_add'], $student_id);
                    $stmt->execute();
                    
                    echo "<p style='color: green;'>Updated database: {$result['points_to_convert']} points were converted to {$result['sessions_to_add']} sessions.</p>";
                } else {
                    $update_query = "UPDATE `users` SET `points` = ? WHERE `{$result['id_column']}` = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param('is', $result['new_points'], $student_id);
                    $stmt->execute();
                    
                    echo "<p style='color: green;'>Updated database: Added $points points. Current points: {$result['new_points']}</p>";
                }
                
                $conn->commit();
                
                // Log the points addition
                $log_query = "INSERT INTO `points_log` (`student_id`, `points_added`, `reason`, `admin_id`, `admin_username`) 
                             VALUES (?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $admin_id = $_SESSION['admin_id'] ?? 1;
                $admin_username = $_SESSION['admin_username'] ?? 'admin';
                $reason = "Test points addition";
                
                if ($log_stmt) {
                    $log_stmt->bind_param('sisss', $student_id, $points, $reason, $admin_id, $admin_username);
                    $log_stmt->execute();
                    echo "<p style='color: green;'>Points log entry created successfully.</p>";
                }
            } catch (Exception $e) {
                $conn->rollback();
                echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
            }
        }
    }
}

echo "<p><a href='fix_points_log.php'>Fix Points Log Table</a></p>";
echo "<p><a href='admin/students/student.php'>Go to Student Management</a></p>";
?> 