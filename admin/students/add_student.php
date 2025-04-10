<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $course = $_POST['course'];
    $yearlevel = $_POST['yearlevel'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Check if student ID already exists
    $check_sql = "SELECT * FROM users WHERE idno = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $idno);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error_message = "Student ID already exists in the system!";
    } else {
        // Insert new student
        $sql = "INSERT INTO users (idno, firstname, middlename, lastname, course, yearlevel, username, password, remaining_sessions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 30)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssss', $idno, $firstname, $middlename, $lastname, $course, $yearlevel, $username, $password);

        if ($stmt->execute()) {
            // Set success message and redirect
            $_SESSION['success_message'] = "Student added successfully!";
            header("Location: student.php");
            exit();
        } else {
            $error_message = "Failed to add student: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student | Sit-In Management System</title>
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
        
        /* Dropdown menu styles */
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 10;
            min-width: 12rem;
            padding: 0.5rem 0;
            margin-top: 0;
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(229, 231, 235, 1);
            top: 100%;
            left: 0;
        }
        
        .dropdown-container:before {
            content: '';
            position: absolute;
            height: 10px;
            width: 100%;
            bottom: -10px;
            left: 0;
            z-index: 9;
        }
        
        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .nav-button {
            transition: all 0.2s ease;
            position: relative;
        }
        
        .nav-button:hover {
            background-color: rgba(7, 89, 133, 0.8);
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Error Notification -->
    <?php if (isset($error_message)): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="../admin.php" class="text-xl font-bold">Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="../admin.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="search_student.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="student.php" class="nav-button px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        
                        <!-- Sit-In dropdown menu -->
                        <div class="relative inline-block dropdown-container" id="sitInDropdown">
                            <button class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center" id="sitInMenuButton">
                                <i class="fas fa-user-check mr-1"></i> Sit-In
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="dropdown-menu" id="sitInDropdownMenu">
                                <a href="../sitin/current_sitin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-check mr-1"></i> Current Sit-In
                                </a>
                                <a href="../sitin/sitin_records.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-list mr-1"></i> Sit-In Records
                                </a>
                                <a href="../sitin/sitin_reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-chart-bar mr-1"></i> Sit-In Reports
                                </a>
                            </div>
                        </div>
                        
                        <a href="../sitin/feedback_reports.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <a href="../reservation/reservation.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="../../newp.jpg" alt="Admin" class="w-full h-full object-cover">
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg overflow-hidden z-20">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Profile
                                </a>
                                <a href="../edit_admin_profile.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-edit mr-2"></i> Edit Profile
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <a href="../auth/logout_admin.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
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
        
        <!-- Mobile Sit-In dropdown with toggle -->
        <div class="relative">
            <button id="mobile-sitin-dropdown" class="w-full text-left block px-4 py-2 text-white hover:bg-primary-900 flex justify-between items-center">
                <span><i class="fas fa-user-check mr-2"></i> Sit-In</span>
                <i class="fas fa-chevron-down text-xs"></i>
            </button>
            <div id="mobile-sitin-menu" class="hidden bg-primary-950 py-2">
                <a href="../sitin/current_sitin.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-user-check mr-2"></i> Current Sit-In
                </a>
                <a href="../sitin/sitin_records.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-list mr-2"></i> Sit-In Records
                </a>
                <a href="../sitin/sitin_reports.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-chart-bar mr-2"></i> Sit-In Reports
                </a>
            </div>
        </div>
        
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
            <!-- Add Student Form -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Add New Student</h2>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <a href="student.php" class="text-primary-600 hover:text-primary-800 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Students List
                        </a>
                    </div>
                    
                    <form action="add_student.php" method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div class="relative">
                                    <label for="idno" class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                                    <input type="text" id="idno" name="idno" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Enter student ID number" required>
                                </div>
                                
                                <div class="relative">
                                    <label for="lastname" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" id="lastname" name="lastname" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Enter student last name" required>
                                </div>
                                
                                <div class="relative">
                                    <label for="firstname" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" id="firstname" name="firstname" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Enter student first name" required>
                                </div>
                                
                                <div class="relative">
                                    <label for="middlename" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                    <input type="text" id="middlename" name="middlename" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Enter student middle name">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div class="relative">
                                    <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                    <select id="course" name="course" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        required>
                                        <option value="" disabled selected>Select course</option>
                                        <option value="BSCS">BSCS</option>
                                        <option value="BSIT">BSIT</option>
                                        <option value="ACT">ACT</option>
                                        <option value="COE">COE</option>
                                        <option value="CPE">CPE</option>
                                        <option value="BSIS">BSIS</option>
                                        <option value="BSA">BSA</option>
                                        <option value="BSBA">BSBA</option>
                                        <option value="BSHRM">BSHRM</option>
                                        <option value="BSHM">BSHM</option>
                                        <option value="BSN">BSN</option>
                                        <option value="BSMT">BSMT</option>
                                    </select>
                                </div>
                                
                                <div class="relative">
                                    <label for="yearlevel" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                    <select id="yearlevel" name="yearlevel" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        required>
                                        <option value="" disabled selected>Select year level</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                </div>
                                
                                <div class="relative">
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                    <input type="text" id="username" name="username" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Create username for student" required>
                                </div>
                                
                                <div class="relative">
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" id="password" name="password" 
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300"
                                        placeholder="Create password for student" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <a href="student.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Cancel
                            </a>
                            <button type="submit" 
                                class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                <i class="fas fa-save mr-1"></i> Add Student
                            </button>
                        </div>
                    </form>
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
        
        // Desktop Sit-In dropdown toggle implementation
        const sitInDropdown = document.getElementById('sitInDropdown');
        const sitInMenuButton = document.getElementById('sitInMenuButton');
        const sitInDropdownMenu = document.getElementById('sitInDropdownMenu');
        
        if (sitInMenuButton && sitInDropdownMenu) {
            // Variable to track if we should keep the menu open
            let isMouseOverDropdown = false;
            let menuTimeout = null;
            
            // Button click handler
            sitInMenuButton.addEventListener('click', function(event) {
                event.stopPropagation();
                sitInDropdownMenu.classList.toggle('show');
            });
            
            // Mouse enter/leave for the entire dropdown container
            sitInDropdown.addEventListener('mouseenter', function() {
                isMouseOverDropdown = true;
                clearTimeout(menuTimeout);
                
                if (window.innerWidth >= 768) { // Only on desktop
                    sitInDropdownMenu.classList.add('show');
                }
            });
            
            sitInDropdown.addEventListener('mouseleave', function() {
                isMouseOverDropdown = false;
                
                // Small delay before hiding to improve UX
                menuTimeout = setTimeout(() => {
                    if (!isMouseOverDropdown && window.innerWidth >= 768) {
                        sitInDropdownMenu.classList.remove('show');
                    }
                }, 150);
            });
            
            // Additional handlers for the menu itself
            sitInDropdownMenu.addEventListener('mouseenter', function() {
                isMouseOverDropdown = true;
                clearTimeout(menuTimeout);
            });
            
            sitInDropdownMenu.addEventListener('mouseleave', function() {
                isMouseOverDropdown = false;
                
                if (window.innerWidth >= 768) {
                    menuTimeout = setTimeout(() => {
                        if (!isMouseOverDropdown) {
                            sitInDropdownMenu.classList.remove('show');
                        }
                    }, 150);
                }
            });
        }
        
        // Mobile Sit-In dropdown toggle
        const mobileSitInDropdown = document.getElementById('mobile-sitin-dropdown');
        const mobileSitInMenu = document.getElementById('mobile-sitin-menu');
        
        if (mobileSitInDropdown && mobileSitInMenu) {
            mobileSitInDropdown.addEventListener('click', function() {
                mobileSitInMenu.classList.toggle('hidden');
            });
        }
        
        // Close dropdowns when clicking outside
        window.addEventListener('click', function(e) {
            if (!document.getElementById('userDropdown')?.contains(e.target)) {
                document.getElementById('userMenu')?.classList.add('hidden');
            }
            
            if (sitInDropdownMenu && !sitInDropdown?.contains(e.target)) {
                sitInDropdownMenu.classList.remove('show');
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
