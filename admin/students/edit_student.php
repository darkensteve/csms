<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login_admin.php");
    exit;
}

// Include database connection
require_once '../../includes/db_connect.php';

// Include data sync helper - Fix the incorrect path
require_once '../includes/data_sync_helper.php';

// Initialize variables
$id_column = isset($_GET['id_col']) ? $_GET['id_col'] : 'id';
$id_value = isset($_GET['id']) ? $_GET['id'] : '';
$student = null;
$error_message = '';
$success_message = '';
$table_name = '';
$redirect_url = 'student.php';

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Try to find the appropriate student table
$tables_result = $conn->query("SHOW TABLES");
$tables_in_db = [];

if ($tables_result) {
    while($table_row = $tables_result->fetch_row()) {
        $tables_in_db[] = $table_row[0];
    }
}

// Look for potential student tables
$potential_tables = ['users', 'students', 'student'];
foreach ($potential_tables as $table) {
    if (in_array($table, $tables_in_db)) {
        $table_name = $table;
        break;
    }
}

// If no known student tables found, try the first table
if (empty($table_name) && !empty($tables_in_db)) {
    $table_name = $tables_in_db[0];
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    try {
        // Get form data
        $updates = [];
        $types = '';
        $values = [];
        
        // Get the columns in the table
        $columns_result = $conn->query("DESCRIBE `{$table_name}`");
        $columns = [];
        
        if ($columns_result) {
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
        }
        
        // Build the update query dynamically
        foreach ($_POST as $field => $value) {
            if ($field !== 'update_student' && $field !== $id_column && in_array($field, $columns)) {
                $updates[] = "`{$field}` = ?";
                $types .= 's'; // Assume all fields are strings
                $values[] = $value;
            }
        }
        
        // Add the ID value at the end for the WHERE clause
        $types .= 's';
        $values[] = $id_value;
        
        if (!empty($updates)) {
            $update_query = "UPDATE `{$table_name}` SET " . implode(", ", $updates) . " WHERE `{$id_column}` = ?";
            
            $stmt = $conn->prepare($update_query);
            
            if ($stmt) {
                // Bind parameters dynamically
                $stmt->bind_param($types, ...$values);
                
                if ($stmt->execute()) {
                    // Now sync the student data
                    $student_id = $_POST['student_id'] ?? $id_value; // Use appropriate ID field
                    $student_name = '';
                    
                    // Try to find the student name from the form data
                    if (isset($_POST['student_name'])) {
                        $student_name = $_POST['student_name'];
                    } elseif (isset($_POST['name'])) {
                        $student_name = $_POST['name'];
                    } elseif (isset($_POST['firstname']) && isset($_POST['lastname'])) {
                        $student_name = $_POST['lastname'] . ', ' . $_POST['firstname'];
                        if (isset($_POST['middlename']) && !empty($_POST['middlename'])) {
                            $student_name .= ' ' . $_POST['middlename'];
                        }
                    }
                    
                    // Only sync if we have a student name
                    if (!empty($student_name)) {
                        $sync_result = sync_student_data($conn, $student_id, $student_name);
                        $success_message = "Student updated successfully. " . $sync_result['message'];
                    } else {
                        $success_message = "Student updated successfully, but couldn't sync with sit-in records (name field not found).";
                    }
                    
                    // Redirect back to student list
                    header("Location: student.php?updated=1");
                    exit;
                } else {
                    $error_message = "Error updating student: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else {
            $error_message = "No valid fields to update.";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get student data for the form
if (!empty($table_name) && !empty($id_value)) {
    $query = "SELECT * FROM `{$table_name}` WHERE `{$id_column}` = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param('s', $id_value);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $student = $result->fetch_assoc();
            } else {
                $error_message = "Student not found.";
            }
        } else {
            $error_message = "Error fetching student data: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | Sit-In Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .notification.success {
            background-color: #10b981;
        }
        
        .notification.error {
            background-color: #ef4444;
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Success/Error Notifications -->
    <?php if (!empty($success_message)): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="admin.php" class="text-xl font-bold">Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="../admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="student.php" class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <a href="../sitin/current_sitin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-user-check mr-1"></i> Sit-In
                        </a>
                        <a href="../sitin/sitin_records.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-list mr-1"></i> Records
                        </a>
                        <a href="../sitin/sitin_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i> Reports
                        </a>
                        <a href="../sitin/feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <a href="../reservation/reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="newp.jpg" alt="Admin" class="w-full h-full object-cover">
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg overflow-hidden z-20">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Profile
                                </a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <a href="logout_admin.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Mobile Navigation Menu (hidden by default) -->
    <div id="mobile-menu" class="md:hidden bg-primary-800 hidden">
        <a href="../admin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-home mr-2"></i> Home
        </a>
        <a href="search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="student.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <a href="../sitin/current_sitin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="../sitin/sitin_records.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-list mr-2"></i> Records
        </a>
        <a href="../sitin/sitin_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
        <a href="../sitin/feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Edit Student Form Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Edit Student</h2>
                </div>
                <div class="p-6">
                    <?php if ($student): ?>
                        <form method="POST" action="" class="space-y-6">
                            <?php foreach ($student as $field => $value): ?>
                                <?php if ($field === $id_column): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($field); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <div class="grid grid-cols-1 gap-6 mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $field))); ?></label>
                                            <input type="text" class="px-4 py-2 border border-gray-300 rounded-md w-full bg-gray-100" value="<?php echo htmlspecialchars($value); ?>" readonly>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 gap-6 mb-4">
                                        <div>
                                            <label for="<?php echo htmlspecialchars($field); ?>" class="block text-sm font-medium text-gray-700 mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $field))); ?></label>
                                            <?php if (strpos(strtolower($field), 'password') !== false): ?>
                                                <input type="password" id="<?php echo htmlspecialchars($field); ?>" name="<?php echo htmlspecialchars($field); ?>" class="px-4 py-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-primary-500" value="">
                                                <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current password</p>
                                            <?php else: ?>
                                                <input type="text" id="<?php echo htmlspecialchars($field); ?>" name="<?php echo htmlspecialchars($field); ?>" class="px-4 py-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo htmlspecialchars($value); ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <div class="flex justify-between items-center pt-4">
                                <a href="student.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                                    <i class="fas fa-arrow-left mr-2"></i> Back
                                </a>
                                <button type="submit" name="update_student" class="px-6 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-save mr-2"></i> Update Student
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <?php echo empty($error_message) ? "Student not found." : $error_message; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="student.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors inline-block">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Students
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-3">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System - Admin Dashboard. All rights reserved.
        </div>
    </footer>

    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // User dropdown toggle
        function toggleUserDropdown() {
            document.getElementById('userMenu').classList.toggle('hidden');
        }

        // Close user dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!document.getElementById('userDropdown').contains(e.target)) {
                document.getElementById('userMenu').classList.add('hidden');
            }
        });

        // Auto hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>