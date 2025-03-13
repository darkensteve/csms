<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Initialize variables
$error_message = '';
$success_message = '';
$student_data = [];
$id_column = '';
$id_value = '';
$table_name = '';
$columns = [];

// Check if ID is provided
if (!isset($_GET['id']) || !isset($_GET['id_col'])) {
    header('Location: student.php?error=missing_params');
    exit();
}

// Get ID and ID column from URL
$id_value = $_GET['id'];
$id_column = $_GET['id_col'];

// Find the right table for students
$tables_in_db = [];
$tables_result = $conn->query("SHOW TABLES");
if ($tables_result) {
    while($table_row = $tables_result->fetch_row()) {
        $tables_in_db[] = $table_row[0];
    }
}

// Try to find the student table
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

// Get columns for the selected table
if (!empty($table_name)) {
    $columns = [];
    $col_result = $conn->query("SHOW COLUMNS FROM `{$table_name}`");
    if ($col_result) {
        while($col = $col_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
}

// Process form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    // Build SET part of query dynamically from submitted data
    $set_parts = [];
    $params = [];
    $types = '';
    
    foreach ($_POST as $key => $value) {
        // Skip non-field values and the ID field
        if ($key === 'update_student' || $key === 'id_column' || $key === 'id_value') {
            continue;
        }
        
        // Only update fields that exist in the table
        if (in_array($key, $columns)) {
            $set_parts[] = "`{$key}` = ?";
            $params[] = $value;
            $types .= 's'; // Assuming all fields are strings for simplicity
        }
    }
    
    if (!empty($set_parts)) {
        // Create the update query
        $update_query = "UPDATE `{$table_name}` SET " . implode(', ', $set_parts) . " WHERE `{$id_column}` = ?";
        
        // Add ID value for the WHERE clause
        $params[] = $_POST['id_value'];
        $types .= 's';
        
        // Prepare and execute the statement
        $stmt = $conn->prepare($update_query);
        
        if ($stmt) {
            // Dynamically bind parameters using call_user_func_array
            $bind_params = array_merge([$types], $params);
            $tmp = [];
            foreach ($bind_params as $key => $value) {
                $tmp[$key] = &$bind_params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $tmp);
            
            if ($stmt->execute()) {
                header("Location: student.php?updated=1");
                exit();
            } else {
                $error_message = "Error updating record: " . $stmt->error;
            }
        } else {
            $error_message = "Error preparing statement: " . $conn->error;
        }
    } else {
        $error_message = "No valid fields to update.";
    }
}

// Fetch student data
if (!empty($table_name)) {
    $query = "SELECT * FROM `{$table_name}` WHERE `{$id_column}` = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param('s', $id_value);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $student_data = $result->fetch_assoc();
        } else {
            $error_message = "Student record not found.";
        }
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
} else {
    $error_message = "No student table found in the database.";
}

// Define which fields to exclude from editing
$excluded_fields = ['password', 'pass', 'passwd', 'hash', 'salt', 'user_id', 'userid'];

// Define display names for common fields
$field_display_names = [
    'id' => 'ID',
    'student_id' => 'Student ID',
    'first_name' => 'First Name',
    'firstname' => 'First Name',
    'last_name' => 'Last Name',
    'lastname' => 'Last Name',
    'middle_name' => 'Middle Name',
    'middlename' => 'Middle Name',
    'email' => 'Email Address',
    'phone' => 'Phone Number',
    'course' => 'Course',
    'department' => 'Department',
    'year_level' => 'Year Level',
    'remaining_sessions' => 'Remaining Sessions'
];
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
                        <a href="admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="student.php" class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <a href="current_sitin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-user-check mr-1"></i> Sit-In
                        </a>
                        <a href="sitin_records.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-list mr-1"></i> Records
                        </a>
                        <a href="sitin_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i> Reports
                        </a>
                        <a href="feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="assets/newp.jpg" alt="Admin" class="w-full h-full object-cover">
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
        <a href="admin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-home mr-2"></i> Home
        </a>
        <a href="search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="student.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <a href="current_sitin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="sitin_records.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-list mr-2"></i> Records
        </a>
        <a href="sitin_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
        <a href="feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Breadcrumb -->
            <div class="flex items-center mb-4 text-sm">
                <a href="admin.php" class="text-gray-500 hover:text-primary-600">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="student.php" class="text-gray-500 hover:text-primary-600">Students</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Edit Student</span>
            </div>
            
            <!-- Edit Student Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Edit Student</h2>
                </div>
                
                <div class="p-6">
                    <?php if (!empty($student_data)): ?>
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="id_column" value="<?php echo htmlspecialchars($id_column); ?>">
                        <input type="hidden" name="id_value" value="<?php echo htmlspecialchars($id_value); ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($student_data as $field => $value): ?>
                                <?php 
                                // Skip excluded fields
                                $skip = false;
                                foreach ($excluded_fields as $excluded) {
                                    if (stripos($field, $excluded) !== false) {
                                        $skip = true;
                                        break;
                                    }
                                }
                                if ($skip) continue;
                                
                                // Get display name
                                $display_name = isset($field_display_names[$field]) ? $field_display_names[$field] : ucwords(str_replace('_', ' ', $field));
                                
                                // Make ID fields readonly
                                $is_readonly = (strtolower($field) === strtolower($id_column) || stripos($field, 'id') !== false);
                                ?>
                            
                            <div class="flex flex-col">
                                <label for="<?php echo htmlspecialchars($field); ?>" class="block text-sm font-medium text-gray-700 mb-1">
                                    <?php echo htmlspecialchars($display_name); ?>
                                </label>
                                
                                <?php if ($field === 'remaining_sessions'): ?>
                                <input 
                                    type="number" 
                                    id="<?php echo htmlspecialchars($field); ?>" 
                                    name="<?php echo htmlspecialchars($field); ?>" 
                                    value="<?php echo htmlspecialchars($value); ?>" 
                                    class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 py-2 px-3 border"
                                    min="0"
                                    max="999"
                                >
                                <?php else: ?>
                                <input 
                                    type="text" 
                                    id="<?php echo htmlspecialchars($field); ?>" 
                                    name="<?php echo htmlspecialchars($field); ?>" 
                                    value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo $is_readonly ? 'readonly' : ''; ?>
                                    class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 py-2 px-3 border <?php echo $is_readonly ? 'bg-gray-100' : ''; ?>"
                                >
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="flex justify-between pt-4 border-t border-gray-200">
                            <a href="student.php" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 rounded-md text-gray-700 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" name="update_student" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 rounded-md text-white transition-colors">
                                Update Student
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
                                    No student record found with the provided ID.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="student.php" class="inline-block px-5 py-2 bg-primary-600 hover:bg-primary-700 rounded-md text-white transition-colors">
                            Back to Students
                        </a>
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
