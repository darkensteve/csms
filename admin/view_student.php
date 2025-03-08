<?php
session_start();
include('../includes/db_connection.php');

// Check if user is logged in as admin
if(!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../login_admin.php");
    exit;
}

// Initialize variables
$student = null;
$errorMessage = '';
$successMessage = '';

// Check if ID parameter exists
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $errorMessage = "No student ID provided.";
} else {
    $studentId = intval($_GET['id']);

    // Check if tables exist
    function tableExists($conn, $tableName) {
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        return $result->num_rows > 0;
    }

    $studentsTableExists = tableExists($conn, 'students');
    $usersTableExists = tableExists($conn, 'users');

    try {
        // Try to get data from students table first
        if($studentsTableExists) {
            $stmt = $conn->prepare("SELECT s.*, d.department_name 
                                  FROM students s 
                                  LEFT JOIN departments d ON s.department_id = d.department_id 
                                  WHERE s.student_id = ?");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                $student = $result->fetch_assoc();
            }
            $stmt->close();
        }
        
        // If student not found and users table exists, try that
        if(!$student && $usersTableExists) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // Map user fields to student structure
                $student = [
                    'student_id' => $user['id'],
                    'first_name' => $user['firstname'] ?? '',
                    'last_name' => $user['lastname'] ?? '',
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'department_name' => $user['course'] ?? '',
                    'enrollment_date' => $user['created_at'] ?? '',
                    'status' => $user['status'] ?? ''
                ];
            }
            $stmt->close();
        }
        
        if(!$student) {
            $errorMessage = "Student not found.";
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - Admin Dashboard</title>
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
<body class="font-sans min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="../admin.php" class="text-xl font-bold">SitIn Admin Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="../admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Home</a>
                        <a href="search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Search</a>
                        <a href="#" class="px-3 py-2 rounded hover:bg-primary-800 transition">Students</a>
                        <div class="relative group">
                            <button class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                                Sit-In <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">View Sit-In Records</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Sit-In Reports</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Feedback Reports</a>
                            </div>
                        </div>
                        <a href="#" class="px-3 py-2 rounded hover:bg-primary-800 transition">Reservation</a>
                    </div>
                    
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                                <span class="font-medium text-sm"><?php echo substr($admin_username, 0, 1); ?></span>
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
                                <a href="../logout_admin.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-8">
        <div class="mb-4 flex items-center">
            <a href="search_student.php" class="text-primary-600 hover:text-primary-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Search
            </a>
        </div>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-md mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo $errorMessage; ?></span>
                </div>
            </div>
        <?php elseif ($student): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-primary-700 text-white flex justify-between items-center">
                    <h1 class="text-xl font-bold">Student Details</h1>
                    <div class="flex items-center space-x-2">
                        <a href="edit_student.php?id=<?php echo $student['student_id']; ?>" class="px-3 py-1 bg-white text-primary-700 rounded-md hover:bg-gray-100 transition text-sm">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        <a href="delete_student.php?id=<?php echo $student['student_id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this student?');"
                           class="px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 transition text-sm">
                            <i class="fas fa-trash mr-1"></i> Delete
                        </a>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-6">
                                <h2 class="text-xl font-semibold text-gray-800 mb-4">Personal Information</h2>
                                <div class="space-y-3">
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Student ID:</span>
                                        <span class="font-medium"><?php echo $student['student_id']; ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">First Name:</span>
                                        <span><?php echo htmlspecialchars($student['first_name']); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Last Name:</span>
                                        <span><?php echo htmlspecialchars($student['last_name']); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Email:</span>
                                        <span><?php echo htmlspecialchars($student['email']); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Phone:</span>
                                        <span><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Status:</span>
                                        <span>
                                            <?php if(isset($student['status']) && $student['status'] == 'active'): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Active</span>
                                            <?php elseif(isset($student['status'])): ?>
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Inactive</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">Unknown</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800 mb-4">Academic Information</h2>
                                <div class="space-y-3">
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Department:</span>
                                        <span><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-gray-500 w-32">Enrolled:</span>
                                        <span><?php echo isset($student['enrollment_date']) ? date("F j, Y", strtotime($student['enrollment_date'])) : 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-500 italic">No recent activities found.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-3 mt-8">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System - Admin Dashboard. All rights reserved.
        </div>
    </footer>
    
    <script>
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
    </script>
</body>
</html>
