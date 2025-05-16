<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create lab_schedules table if it doesn't exist
$lab_schedules_table = "
CREATE TABLE IF NOT EXISTS `lab_schedules` (
    `schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
    `lab_id` INT(11) NOT NULL,
    `day_of_week` VARCHAR(20) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` ENUM('available', 'occupied', 'maintenance', 'reserved') NOT NULL DEFAULT 'available',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`schedule_id`),
    INDEX (`lab_id`),
    INDEX (`day_of_week`),
    CONSTRAINT `fk_schedule_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Check if lab_schedules table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'lab_schedules'");
$table_created = false;

if ($table_exists->num_rows == 0) {
    if ($conn->query($lab_schedules_table) === TRUE) {
        echo "<p>Lab schedules table created successfully</p>";
        $table_created = true;
    } else {
        echo "<p>Error creating lab schedules table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Lab schedules table already exists</p>";
}

// Add some default schedules for each lab if the table was just created
if ($table_created) {
    // Get all labs
    $labs_result = $conn->query("SELECT lab_id FROM labs");
    
    if ($labs_result && $labs_result->num_rows > 0) {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $times = [
            ['08:00:00', '10:00:00'],
            ['10:00:00', '12:00:00'],
            ['13:00:00', '15:00:00'],
            ['15:00:00', '17:00:00']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO lab_schedules (lab_id, day_of_week, start_time, end_time, status) VALUES (?, ?, ?, ?, 'available')");
        
        while ($lab = $labs_result->fetch_assoc()) {
            $lab_id = $lab['lab_id'];
            
            foreach ($days as $day) {
                foreach ($times as $time) {
                    $start_time = $time[0];
                    $end_time = $time[1];
                    
                    $insert_stmt->bind_param("isss", $lab_id, $day, $start_time, $end_time);
                    $insert_stmt->execute();
                }
            }
        }
        
        echo "<p>Default lab schedules created</p>";
    }
}

$conn->close();

echo "<p>Database setup completed. <a href='../admin.php'>Return to Admin Dashboard</a></p>";
?> 