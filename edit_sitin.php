<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get username for display
$username = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'User');
$is_admin = isset($_SESSION['admin_id']);

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['sitin_message'] = "No sit-in session specified.";
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

$sitin_id = intval($_GET['id']);

// Check if user is authorized (admin or the owner of the sit-in)
$authorized = false;
if (isset($_SESSION['admin_id'])) {
    $authorized = true; // Admin is always authorized
} elseif (isset($_SESSION['user_id'])) {
    // Check if this sit-in belongs to the current user
    $auth_query = "SELECT session_id FROM sit_in_sessions WHERE session_id = ? AND student_id = ?";
    $auth_stmt = $conn->prepare($auth_query);
    if ($auth_stmt) {
        $auth_stmt->bind_param("is", $sitin_id, $_SESSION['user_id']);
        $auth_stmt->execute();
        $auth_result = $auth_stmt->get_result();
        if ($auth_result->num_rows > 0) {
            $authorized = true;
        }
        $auth_stmt->close();
    }
}

if (!$authorized) {
    $_SESSION['sitin_message'] = "You are not authorized to edit this sit-in session.";
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

// Get available labs
$labs = [];
$labs_query = "SELECT lab_id, lab_name FROM labs ORDER BY lab_name";
$labs_result = $conn->query($labs_query);
if ($labs_result && $labs_result->num_rows > 0) {
    while ($lab = $labs_result->fetch_assoc()) {
        $labs[] = $lab;
    }
}

// Get sit-in details
$sitin_data = null;
$query = "SELECT s.session_id, s.student_id, s.student_name, s.lab_id, s.purpose, s.check_in_time, 
          s.status, l.lab_name FROM sit_in_sessions s 
          LEFT JOIN labs l ON s.lab_id = l.lab_id 
          WHERE s.session_id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $sitin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $sitin_data = $result->fetch_assoc();
    } else {
        $_SESSION['sitin_message'] = "Sit-in session not found.";
        $_SESSION['sitin_status'] = "error";
        header('Location: current_sitin.php');
        exit();
    }
    
    $stmt->close();
} else {
    $_SESSION['sitin_message'] = "Database error: " . $conn->error;
    $_SESSION['sitin_status'] = "error";
    header('Location: current_sitin.php');
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_name = trim($_POST['student_name']);
    $purpose = trim($_POST['purpose']);
    $lab_id = intval($_POST['lab_id']);
    $check_in_time = $_POST['check_in_time'];
    
    // Validate form data
    $errors = [];
    
    if (empty($student_name)) {
        $errors[] = "Student name is required.";
    }
    
    if (empty($purpose)) {
        $errors[] = "Purpose is required.";
    }
    
    if ($lab_id <= 0) {
        $errors[] = "Please select a valid laboratory.";
    }
    
    if (empty($check_in_time)) {
        $errors[] = "Check-in time is required.";
    }
    
    // If no errors, update the sit-in session
    if (empty($errors)) {
        $update_query = "UPDATE sit_in_sessions SET student_name = ?, purpose = ?, lab_id = ?, check_in_time = ? WHERE session_id = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if ($update_stmt) {
            $update_stmt->bind_param("ssis", $student_name, $purpose, $lab_id, $check_in_time, $sitin_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['sitin_message'] = "Sit-in session updated successfully.";
                $_SESSION['sitin_status'] = "success";
                header('Location: current_sitin.php?sitin_id=' . $sitin_id);
                exit();
            } else {
                $errors[] = "Failed to update sit-in session: " . $update_stmt->error;
            }
            
            $update_stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sit-in Session | Sit-In Management System</title>
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
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="<?php echo $is_admin ? 'admin.php' : 'dashboard.php'; ?>" class="text-xl font-bold">
                        <?php echo $is_admin ? 'Admin Dashboard' : 'Dashboard'; ?>
                    </a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="<?php echo $is_admin ? 'admin.php' : 'dashboard.php'; ?>" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <?php endif; ?>
                        <a href="current_sitin.php" 
                           class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-user-check mr-1"></i> Sit-In
                        </a>
                        <a href="sitin_records.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-list mr-1"></i> Records
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="sitin_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i> Reports
                        </a>
                        <a href="feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="assets/newp.jpg" alt="User" class="w-full h-full object-cover">
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($username); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg overflow-hidden z-20">
                            <div class="py-2">
                                <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Profile
                                </a>
                                <a href="settings.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <a href="<?php echo $is_admin ? 'logout_admin.php' : 'logout.php'; ?>" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
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
        <a href="<?php echo $is_admin ? 'admin.php' : 'dashboard.php'; ?>" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-home mr-2"></i> Home
        </a>
        <?php if ($is_admin): ?>
        <a href="search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <?php endif; ?>
        <a href="current_sitin.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="sitin_records.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-list mr-2"></i> Records
        </a>
        <?php if ($is_admin): ?>
        <a href="sitin_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
        <a href="feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Breadcrumb -->
            <div class="flex items-center mb-6">
                <a href="current_sitin.php" class="text-primary-600 hover:text-primary-800">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Current Sit-ins
                </a>
            </div>
            
            <!-- Edit Sit-in Form -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex items-center">
                    <h2 class="text-xl font-semibold">Edit Sit-in Session</h2>
                </div>
                
                <div class="p-6">
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-1">
                                <div class="mb-4">
                                    <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
                                    <input type="text" id="student_id" name="student_id" 
                                           value="<?php echo htmlspecialchars($sitin_data['student_id'] ?? ''); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                           readonly>
                                    <p class="text-xs text-gray-500 mt-1">Student ID cannot be changed</p>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-1">Student Name</label>
                                    <input type="text" id="student_name" name="student_name" 
                                           value="<?php echo htmlspecialchars($sitin_data['student_name'] ?? ''); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                           required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                                    <select id="lab_id" name="lab_id" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                            required>
                                        <option value="">-- Select Laboratory --</option>
                                        <?php foreach ($labs as $lab): ?>
                                            <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($sitin_data['lab_id'] == $lab['lab_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lab['lab_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-span-1">
                                <div class="mb-4">
                                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                                    <textarea id="purpose" name="purpose" rows="3" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                              required><?php echo htmlspecialchars($sitin_data['purpose'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="check_in_time" class="block text-sm font-medium text-gray-700 mb-1">Check-in Time</label>
                                    <input type="datetime-local" id="check_in_time" name="check_in_time" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($sitin_data['check_in_time'])); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                           required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <input type="text" id="status" name="status" 
                                           value="<?php echo htmlspecialchars($sitin_data['status'] ?? ''); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                                           readonly>
                                    <p class="text-xs text-gray-500 mt-1">Use the Time Out button to change status</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="current_sitin.php" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Update Sit-in
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-3">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>

    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        
        // Toggle user dropdown
        function toggleUserDropdown() {
            document.getElementById('userMenu').classList.toggle('hidden');
        }
        
        // Close user dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!document.getElementById('userDropdown').contains(e.target)) {
                document.getElementById('userMenu').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
