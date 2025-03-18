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

// Create reservations table if it doesn't exist
$reservations_table = "
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    lab_id INT(11) NOT NULL,
    reservation_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    purpose TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Add location column to labs table if it doesn't exist
$check_location_column = $conn->query("SHOW COLUMNS FROM labs LIKE 'location'");
if ($check_location_column->num_rows == 0) {
    $add_location_column = "ALTER TABLE labs ADD COLUMN location VARCHAR(255) NOT NULL DEFAULT 'Main Building'";
    if ($conn->query($add_location_column) === TRUE) {
        echo "<p>Location column added to labs table successfully</p>";
    } else {
        echo "<p>Error adding location column: " . $conn->error . "</p>";
    }
}

if ($conn->query($reservations_table) === TRUE) {
    echo "<p>Reservations table created successfully</p>";
} else {
    echo "<p>Error creating reservations table: " . $conn->error . "</p>";
}

$conn->close();

echo "<p>Database setup completed. <a href='reservation.php'>Return to Reservation Page</a></p>";
?>
