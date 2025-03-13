<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';

// Include datetime helper
require_once 'includes/datetime_helper.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get username for display
$username = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'User');
$is_admin = isset($_SESSION['admin_id']);

// Get current page for nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check for highlight parameter (user_id or sitin_id)
$highlight_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$highlight_sitin_id = isset($_GET['sitin_id']) ? intval($_GET['sitin_id']) : null;

// Get search term if provided
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get student name if user_id is specified
$student_name = '';
if ($highlight_user_id) {
    // Try to get student name from sit_in_sessions first if available
    $user_query = "SELECT student_name FROM sit_in_sessions WHERE student_id = ? LIMIT 1";
    $user_stmt = $conn->prepare($user_query);
    if ($user_stmt) {
        $highlight_user_id_string = (string)$highlight_user_id;
        $user_stmt->bind_param("s", $highlight_user_id_string);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $student_name = $user_data['student_name'];
        }
        $user_stmt->close();
    }
    
    // If not found in sit_in_sessions, try users table as fallback
    if (empty($student_name)) {
        $user_query = "SELECT username FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_query);
        if ($user_stmt) {
            $user_stmt->bind_param("i", $highlight_user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $student_name = $user_data['username']; 
            }
            $user_stmt->close();
        }
    }
}

// Check if sit_in_sessions table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'sit_in_sessions'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `sit_in_sessions` (
        `session_id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` VARCHAR(50) NOT NULL,
        `student_name` VARCHAR(255) NOT NULL,
        `lab_id` INT NOT NULL,
        `purpose` VARCHAR(255) NOT NULL,
        `check_in_time` DATETIME NOT NULL,
        `check_out_time` DATETIME NULL,
        `status` VARCHAR(50) NOT NULL,
        `admin_id` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->query($create_table_sql);
}

// Check if labs table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'labs'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `labs` (
        `lab_id` int(11) NOT NULL AUTO_INCREMENT,
        `lab_name` varchar(100) NOT NULL,
        `capacity` int(11) NOT NULL DEFAULT 30,
        PRIMARY KEY (`lab_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table_sql);
    
    // Insert default labs
    $conn->query("INSERT INTO `labs` (`lab_name`, `capacity`) VALUES 
        ('Laboratory 524', 30),
        ('Laboratory 526', 30),
        ('Laboratory 528', 30),
        ('Laboratory 530', 30),
        ('Laboratory 542', 30),
        ('Mac Laboratory', 25)");
}

// Initialize sit_ins as empty array
$sit_ins = [];

// Try to query current sit-ins
try {
    // Setup MySQL session to ensure times are interpreted correctly
    $conn->query("SET time_zone = '+08:00'");
    
    $query = "SELECT s.session_id as id, s.student_id as user_id, s.purpose, s.check_in_time as start_time, 
              IFNULL(s.check_out_time, DATE_ADD(s.check_in_time, INTERVAL 3 HOUR)) as end_time, 
              s.lab_id, s.student_name as user_name, s.status, l.lab_name,
              (SELECT COUNT(*) FROM sit_in_sessions WHERE student_id = s.student_id AND status = 'active') AS session_count
              FROM sit_in_sessions s 
              LEFT JOIN labs l ON s.lab_id = l.lab_id
              WHERE s.status = 'active' ";
    
    // Add filter if a specific user_id is provided
    if ($highlight_user_id) {
        $query .= " AND s.student_id = '" . $conn->real_escape_string($highlight_user_id) . "'";
    } 
    // If specific sitin_id is provided but no user_id, include that sit-in
    else if ($highlight_sitin_id) {
        $query .= " OR s.session_id = " . intval($highlight_sitin_id);
    }
    // Add search filter if search term is provided
    else if (!empty($search_term)) {
        $query .= " AND (s.student_id LIKE '%" . $conn->real_escape_string($search_term) . "%' OR 
                         s.student_name LIKE '%" . $conn->real_escape_string($search_term) . "%')";
    }
    
    $query .= " ORDER BY s.check_in_time ASC";

    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sit_ins[] = $row;
        }
    }
} catch (Exception $e) {
    // Just continue - we'll handle empty sit_ins array in the view
    if ($is_admin && $debug_mode ?? false) {
        $error_message = "Query error: " . $e->getMessage();
    }
}

// Check for messages in session
$success_message = '';
$error_message = '';

if (isset($_SESSION['sitin_message']) && isset($_SESSION['sitin_status'])) {
    if ($_SESSION['sitin_status'] === 'success') {
        $success_message = $_SESSION['sitin_message'];
    } else {
        $error_message = $_SESSION['sitin_message'];
    }
    
    // Clear the message after retrieving
    unset($_SESSION['sitin_message']);
    unset($_SESSION['sitin_status']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Sit-ins | Sit-In Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        .highlighted-row {
            background-color: #fef3c7 !important; /* Light amber highlight */
            animation: pulse-highlight 2s ease-in-out;
        }
        
        @keyframes pulse-highlight {
            0%, 100% { background-color: #fef3c7; }
            50% { background-color: #fde68a; }
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Success/Error Notifications -->
    <div id="notificationContainer">
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
    </div>

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
                        <a href="<?php echo $current_page == 'current_sitin.php' ? 'current_sitin.php' : 'current_sitin.php'; ?>" 
                           class="px-3 py-2 <?php echo ($current_page == 'current_sitin.php' || $current_page == 'sitin_register.php') ? 'bg-primary-800' : 'hover:bg-primary-800'; ?> rounded transition flex items-center">
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
        <a href="<?php echo $current_page == 'current_sitin.php' ? 'sitin_register.php' : 'current_sitin.php'; ?>" 
           class="block px-4 py-2 text-white <?php echo ($current_page == 'current_sitin.php' || $current_page == 'sitin_register.php') ? 'bg-primary-900' : 'hover:bg-primary-900'; ?>">
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
            <!-- Current Sit-ins Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex justify-between items-center">
                    <h2 class="text-xl font-semibold">
                        <?php if ($highlight_user_id && !empty($student_name)): ?>
                            Current Sit-ins for <?php echo htmlspecialchars($student_name); ?>
                        <?php elseif ($highlight_user_id): ?>
                            Current Sit-ins for Selected Student
                        <?php else: ?>
                            Current Sit-ins
                        <?php endif; ?>
                    </h2>
                    <div class="flex items-center">
                        <form action="" method="GET" class="flex items-center">
                            <div class="relative rounded-md shadow-sm">
                                <input type="text" name="search" placeholder="Search by ID or Name" 
                                    class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-3 pr-12 py-2 sm:text-sm border-gray-300 rounded-md text-gray-700"
                                    value="<?php echo htmlspecialchars($search_term); ?>">
                                <div class="absolute inset-y-0 right-0 flex items-center">
                                    <button type="submit" class="p-2 px-3 focus:outline-none focus:shadow-outline text-primary-700">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (!empty($search_term) && count($sit_ins) === 0): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-search text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    No active sit-ins found matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>".
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($search_term) && count($sit_ins) > 0): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-search text-blue-400"></i>
                            </div>
                            <div class="ml-3 flex-grow">
                                <p class="text-sm text-blue-700">
                                    Found <?php echo count($sit_ins); ?> active sit-in(s) matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                                </p>
                            </div>
                            <a href="current_sitin.php" class="text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times-circle"></i> Clear search
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($highlight_user_id && count($sit_ins) > 0): ?>
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded mb-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-amber-500"></i>
                            </div>
                            <div class="ml-3 flex-grow">
                                <p class="text-sm font-medium text-amber-800">
                                    Student <strong><?php echo htmlspecialchars($student_name); ?></strong> is currently sitting in.
                                </p>
                                <p class="text-xs text-amber-700 mt-1">
                                    This student already has an active sit-in session and cannot register for another one until they time out.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // If redirected from a search with new sit-in, show a message
                    if ($highlight_sitin_id && !empty($success_message)): ?>
                    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 text-green-700">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">
                                    Successfully registered sit-in! Showing the newly created record below.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div id="sitinTableContainer">
                        <?php if (count($sit_ins) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" id="sitinsTable">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sit Lab</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Session</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($sit_ins as $sit_in): ?>
                                            <tr class="hover:bg-gray-50 <?php echo ($highlight_sitin_id && $sit_in['id'] == $highlight_sitin_id) ? 'highlighted-row' : ''; ?>" id="sitin-row-<?php echo $sit_in['id']; ?>">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sit_in['user_id'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sit_in['user_name'] ?? 'Unknown'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sit_in['purpose'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sit_in['lab_name'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php 
                                                        // Simple approach to get remaining sessions with error handling
                                                        $remaining_sessions = 0;
                                                        
                                                        try {
                                                            // Try with idNo first (most common column name)
                                                            $user_query = "SELECT remaining_sessions FROM users WHERE idNo = ?";
                                                            $user_stmt = $conn->prepare($user_query);
                                                            
                                                            if ($user_stmt) {
                                                                $user_stmt->bind_param("s", $sit_in['user_id']);
                                                                $user_stmt->execute();
                                                                $user_result = $user_stmt->get_result();
                                                                
                                                                if ($user_result && $user_result->num_rows > 0) {
                                                                    $user_data = $user_result->fetch_assoc();
                                                                    $remaining_sessions = $user_data['remaining_sessions'] ?? 0;
                                                                }
                                                                $user_stmt->close();
                                                            }
                                                        } catch (Exception $e) {
                                                            // Silently handle the error and keep default value
                                                        }
                                                        
                                                        echo $remaining_sessions;
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo format_datetime($sit_in['start_time']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php 
                                                    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
                                                    $end = new DateTime($sit_in['end_time'], new DateTimeZone('Asia/Manila'));
                                                    
                                                    if ($sit_in['status'] == 'active') {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>';
                                                    } else {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <?php if ($is_admin || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $sit_in['user_id'])): ?>
                                                        <div class="flex space-x-4">
                                                            <button type="button" 
                                                                class="text-red-600 hover:text-red-900 timeout-btn" 
                                                                title="Time Out Student"
                                                                data-sitin-id="<?php echo $sit_in['id']; ?>">
                                                                <i class="fas fa-sign-out-alt text-lg"></i>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <?php if ($highlight_user_id): ?>
                                    <div class="text-gray-400 text-5xl mb-4">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No active sit-ins for this student</h3>
                                    <p class="text-gray-500 mb-6">The selected student is not currently sitting in</p>
                                <?php elseif (!empty($search_term)): ?>
                                    <div class="text-gray-400 text-5xl mb-4">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No matching sit-ins found</h3>
                                    <p class="text-gray-500 mb-6">No students matching "<?php echo htmlspecialchars($search_term); ?>" are currently sitting in</p>
                                    <a href="current_sitin.php" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors mb-3 inline-block">
                                        <i class="fas fa-times-circle mr-2"></i> Clear Search
                                    </a>
                                    <a href="sitin_register.php" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors inline-block">
                                        <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                    </a>
                                <?php else: ?>
                                    <div class="text-gray-400 text-5xl mb-4">
                                        <i class="fas fa-mug-hot"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No active sit-ins at the moment</h3>
                                    <p class="text-gray-500 mb-6">Create a new sit-in to get started</p>
                                    <a href="sitin_register.php" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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
        
        // Toggle mobile dropdown menus
        document.querySelectorAll('.mobile-dropdown-button').forEach(button => {
            button.addEventListener('click', function() {
                this.nextElementSibling.classList.toggle('hidden');
            });
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
        
        // Scroll to highlighted row if it exists
        document.addEventListener('DOMContentLoaded', function() {
            const highlightedRow = document.querySelector('.highlighted-row');
            if (highlightedRow) {
                highlightedRow.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

        // Handle timeout button click
        $(document).on('click', '.timeout-btn', function() {
            const sitinId = $(this).data('sitin-id');
            if (confirm('Are you sure you want to time out this student? This will mark their status as inactive.')) {
                $.ajax({
                    url: 'timeout_sitin.php',
                    type: 'POST',
                    data: { id: sitinId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove only the specific row
                            $('#sitin-row-' + sitinId).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if there are no more rows
                                if ($('#sitinsTable tbody tr').length === 0) {
                                    // Show the "no sit-ins" message
                                    $('#sitinTableContainer').html(`
                                        <div class="text-center py-8">
                                            <div class="text-gray-400 text-5xl mb-4">
                                                <i class="fas fa-mug-hot"></i>
                                            </div>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No active sit-ins at the moment</h3>
                                            <p class="text-gray-500 mb-6">Create a new sit-in to get started</p>
                                            <a href="sitin_register.php" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                                                <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                            </a>
                                        </div>
                                    `);
                                }
                            });
                            
                            // Show success notification
                            $('#notificationContainer').append(`
                                <div class="notification success">
                                    <i class="fas fa-check-circle mr-2"></i> ${response.message}
                                </div>
                            `);
                        } else {
                            // Show error notification
                            $('#notificationContainer').append(`
                                <div class="notification error">
                                    <i class="fas fa-exclamation-circle mr-2"></i> ${response.message}
                                </div>
                            `);
                        }
                        
                        // Auto hide notifications after 5 seconds
                        const $notification = $('.notification').last();
                        setTimeout(() => {
                            $notification.css('opacity', '0');
                            $notification.css('transition', 'opacity 0.5s ease-out');
                            setTimeout(() => {
                                $notification.remove();
                            }, 500);
                        }, 5000);
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        
                        // Show error notification
                        $('#notificationContainer').append(`
                            <div class="notification error">
                                <i class="fas fa-exclamation-circle mr-2"></i> An error occurred while timing out the student.
                            </div>
                        `);
                        
                        // Auto hide notifications after 5 seconds
                        const $notification = $('.notification').last();
                        setTimeout(() => {
                            $notification.css('opacity', '0');
                            $notification.css('transition', 'opacity 0.5s ease-out');
                            setTimeout(() => {
                                $notification.remove();
                            }, 500);
                        }, 5000);
                    }
                });
            }
        });
    </script>
</body>
</html>
