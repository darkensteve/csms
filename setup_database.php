<?php
require_once 'includes/db_connection.php';

// SQL to create the students table
$students_table = "
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// SQL to create the departments table
$departments_table = "
CREATE TABLE IF NOT EXISTS `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  PRIMARY KEY (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute the SQL statements
if ($conn->query($students_table) === TRUE) {
    echo "Students table created successfully<br>";
} else {
    echo "Error creating students table: " . $conn->error . "<br>";
}

if ($conn->query($departments_table) === TRUE) {
    echo "Departments table created successfully<br>";
} else {
    echo "Error creating departments table: " . $conn->error . "<br>";
}

echo "<p>Note: Run this file once to set up the database structure.</p>";
echo "<p><a href='admin/search_student.php'>Go to Student Search</a></p>";

$conn->close();
?>
