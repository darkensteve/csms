<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../includes/db_connect.php';

// Include datetime helper
require_once '../includes/datetime_helper.php';

// Include data sync helper for potential updates
require_once '../includes/data_sync_helper.php';

// Function to ensure times are formatted in Manila/Asia timezone (GMT+8)
function format_datetime_gmt8($datetime_string) {
    if (empty($datetime_string)) return '';
    
    // Force conversion to Manila timezone regardless of stored format
    $dt = new DateTime($datetime_string);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    
    // Format datetime in Manila local time (GMT+8)
    return $dt->format('M d, Y h:i A');
}

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
        `computer_id` INT,
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

// Check if system_logs table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `system_logs` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` VARCHAR(50),
        `action` VARCHAR(255) NOT NULL,
        `action_type` VARCHAR(50) NOT NULL,
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `user_agent` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table_sql);
}

// Pagination settings for current sit-ins
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Initialize sit_ins as empty array
$sit_ins = [];
$total_records = 0;
$total_pages = 1;

// Try to query current sit-ins
try {
    // Setup MySQL session to ensure times are interpreted correctly
    $conn->query("SET time_zone = '+08:00'");
    
    $query = "SELECT s.session_id as id, s.student_id as user_id, s.purpose, s.check_in_time as start_time, 
              IFNULL(s.check_out_time, DATE_ADD(s.check_in_time, INTERVAL 3 HOUR)) as end_time, 
              s.lab_id, s.student_name as user_name, s.status, l.lab_name, c.computer_name,
              (SELECT COUNT(*) FROM sit_in_sessions WHERE student_id = s.student_id AND status = 'active') AS session_count,
              r.reservation_id
              FROM sit_in_sessions s 
              LEFT JOIN labs l ON s.lab_id = l.lab_id
              LEFT JOIN computers c ON s.computer_id = c.computer_id
              LEFT JOIN reservations r ON s.student_id = r.user_id AND r.status = 'approved' AND DATE(r.reservation_date) = CURDATE()
              WHERE s.status = 'active'";
    
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
    
    // First get the total count for pagination
    $count_result = $conn->query($query);
    if ($count_result) {
        $total_records = $count_result->num_rows;
        $total_pages = ceil($total_records / $records_per_page);
        
        // Adjust page if out of bounds
        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        $offset = ($page - 1) * $records_per_page;
    }
    
    // Add ordering and pagination
    $query .= " ORDER BY s.check_in_time ASC LIMIT $offset, $records_per_page";
    
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

// Check for and process approved reservations when student sits in
if (isset($_POST['process_sitin']) && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    
    // Check if student has an approved reservation
    $check_reservation = $conn->prepare("
        SELECT r.reservation_id, r.computer_id, r.lab_id 
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        WHERE u.idNo = ? AND r.status = 'approved' AND DATE(r.reservation_date) = CURDATE()
        LIMIT 1
    ");
    
    if ($check_reservation) {
        $check_reservation->bind_param("s", $student_id);
        $check_reservation->execute();
        $res_result = $check_reservation->get_result();
        
        if ($res_result->num_rows > 0) {
            $reservation_data = $res_result->fetch_assoc();
            
            // Update the computer status from 'reserved' to 'used'
            if ($reservation_data['computer_id']) {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'used' WHERE computer_id = ?");
                $update_computer->bind_param("i", $reservation_data['computer_id']);
                $update_computer->execute();
                
                // Also update the reservation status to 'completed'
                $update_reservation = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ?");
                $update_reservation->bind_param("i", $reservation_data['reservation_id']);
                $update_reservation->execute();
                
                // Set success message
                $_SESSION['sitin_message'] = "Student has been checked in based on an approved reservation. Computer status updated to 'Used'.";
                $_SESSION['sitin_status'] = 'success';
            }
        }
    }
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
        
        /* Enhanced Modal Styles */
        .modal-container {
            transition: all 0.3s ease;
        }
        
        .modal-content {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .modal-container.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        
        .modal-header {
            background-image: linear-gradient(to right, var(--tw-gradient-stops));
            --tw-gradient-from: #0369a1;
            --tw-gradient-stops: var(--tw-gradient-from), #075985, var(--tw-gradient-to, rgba(7, 89, 133, 0));
            --tw-gradient-to: #0c4a6e;
        }
        
        .input-field {
            transition: all 0.2s ease;
            border-radius: 0.375rem;
        }
        
        .input-field:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }
        
        .btn {
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        /* Table styles */
        .search-results-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .search-results-table th {
            background-color: #f3f4f6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .search-results-table th, 
        .search-results-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .search-results-table tr:last-child td {
            border-bottom: none;
        }
        
        .search-results-table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        /* Dropdown menu styles - updated */
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 10;
            min-width: 12rem;
            padding: 0.5rem 0;
            margin-top: 0; /* Remove margin to eliminate gap */
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(229, 231, 235, 1);
            top: 100%; /* Position right below the button */
            left: 0;
        }
        
        /* Add this pseudo-element to create an invisible bridge */
        .dropdown-container:before {
            content: '';
            position: absolute;
            height: 10px; /* Height of the bridge */
            width: 100%;
            bottom: -10px; /* Position it just below the button */
            left: 0;
            z-index: 9; /* Below the menu but above other elements */
        }
        
        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <a href="<?php echo $is_admin ? '../admin.php' : 'dashboard.php'; ?>" class="text-xl font-bold">
                        <?php echo $is_admin ? 'Admin Dashboard' : 'Dashboard'; ?>
                    </a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="<?php echo $is_admin ? '../admin.php' : 'dashboard.php'; ?>" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="../students/search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="../students/student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <!-- Sit-In dropdown menu - updated structure -->
                        <div class="relative inline-block dropdown-container" id="sitInDropdown">
                            <button class="px-3 py-2 bg-primary-800 rounded transition flex items-center" id="sitInMenuButton">
                                <i class="fas fa-user-check mr-1"></i> Sit-In
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="dropdown-menu" id="sitInDropdownMenu">
                                <a href="current_sitin.php" class="block px-4 py-2 text-sm bg-gray-100 text-primary-700 font-medium hover:bg-gray-200">
                                    <i class="fas fa-user-check mr-1"></i> Current Sit-In
                                </a>
                                <a href="sitin_records.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-list mr-1"></i> Sit-In Records
                                </a>
                                <a href="sitin_reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-chart-bar mr-1"></i> Sit-In Reports
                                </a>
                            </div>
                        </div>
                        <a href="../sitin/feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <a href="../reservation/reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                        <a href="../leaderboard/leaderboard.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-trophy mr-1"></i> Leaderboard
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="../newp.jpg" alt="<?php echo $is_admin ? 'Admin' : 'User'; ?>" class="w-full h-full object-cover">
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($username); ?></span>
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
        <a href="<?php echo $is_admin ? '../admin.php' : 'dashboard.php'; ?>" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-home mr-2"></i> Home
        </a>
        <?php if ($is_admin): ?>
        <a href="../students/search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="../students/student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <!-- Mobile Sit-In dropdown with toggle -->
        <div class="relative">
            <button id="mobile-sitin-dropdown" class="w-full text-left block px-4 py-2 text-white bg-primary-900 flex justify-between items-center">
                <span><i class="fas fa-user-check mr-2"></i> Sit-In</span>
                <i class="fas fa-chevron-down text-xs"></i>
            </button>
            <div id="mobile-sitin-menu" class="hidden bg-primary-950 py-2">
                <a href="current_sitin.php" class="block px-6 py-2 text-white bg-primary-800">
                    <i class="fas fa-user-check mr-2"></i> Current Sit-In
                </a>
                <a href="sitin_records.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-list mr-2"></i> Sit-In Records
                </a>
                <a href="sitin_reports.php" class="block px-6 py-2 text-white hover:bg-primary-900">
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
        <a href="../leaderboard/leaderboard.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-trophy mr-2"></i> Leaderboard
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
                                <a href="current_sitin.php" class="text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-times-circle"></i> Clear search
                                </a>
                            </div>
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
                    
                    <?php if (count($sit_ins) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="sitinsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sit Lab</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Computer</th>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sit_in['computer_name'] ?? 'N/A'); ?></td>
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
                                            <?php echo format_datetime_gmt8($sit_in['start_time']); ?>
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
                    <?php if ($total_pages > 1): ?>
                    <!-- Pagination Controls -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4">
                        <div class="flex-1 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div class="mb-4 sm:mb-0">
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $total_records; ?></span>
                                    active sit-ins
                                </p>
                            </div>
                            <div class="flex justify-between sm:justify-end">
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm space-x-2" aria-label="Pagination">
                                    <!-- Previous Page Button -->
                                    <a href="<?php echo $page > 1 ? '?page=' . ($page - 1) . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') : '#'; ?>" 
                                       class="<?php echo $page > 1 ? 'hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500' : 'opacity-50 cursor-not-allowed'; ?> 
                                       relative inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        <i class="fas fa-chevron-left mr-1 sm:mr-2"></i>
                                        <span class="hidden sm:inline">Previous</span>
                                    </a>
                                    <!-- Page Info - Mobile Friendly -->
                                    <span class="sm:hidden relative inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                    </span>
                                    <!-- Page Numbers - Visible only on larger screens -->
                                    <div class="hidden sm:inline-flex">
                                        <?php 
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): 
                                        ?>
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                                           class="<?php echo $i == $page ? 'bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> 
                                           relative inline-flex items-center px-4 py-2 border text-sm font-medium mx-1">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php endfor; ?>
                                    </div>
                                    <!-- Next Page Button -->
                                    <a href="<?php echo $page < $total_pages ? '?page=' . ($page + 1) . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') : '#'; ?>" 
                                       class="<?php echo $page < $total_pages ? 'hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500' : 'opacity-50 cursor-not-allowed'; ?> 
                                       relative inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        <span class="hidden sm:inline">Next</span>
                                        <i class="fas fa-chevron-right ml-1 sm:ml-2"></i>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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
                                <a href="javascript:void(0)" id="registerSitinBtn" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors inline-block">
                                    <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                </a>
                            <?php else: ?>
                                <div class="text-gray-400 text-5xl mb-4">
                                    <i class="fas fa-mug-hot"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No active sit-ins at the moment</h3>
                                <p class="text-gray-500 mb-6">Create a new sit-in to get started</p>
                                <a href="javascript:void(0)" id="registerSitinBtn" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Sit-in Registration Modal - Step 1 (Search) -->
    <div id="searchStudentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-container">
        <div class="bg-white w-full max-w-2xl mx-4 modal-content">
            <div class="modal-header text-white px-4 py-3 flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-search mr-2"></i> Search Student
                </h3>
                <button id="closeSearchModal" class="text-white hover:text-gray-200 focus:outline-none transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="studentSearchForm" class="mb-5">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-medium mb-2" for="searchStudentName">
                            Student Name
                        </label>
                        <div class="flex">
                            <input type="text" id="searchStudentName" name="searchStudentName" 
                                class="flex-grow px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field mr-2" 
                                placeholder="Enter student name">
                            <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700 flex items-center btn">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 flex items-center">
                            <i class="fas fa-info-circle mr-1 text-primary-400"></i> Enter full or partial name to search
                        </p>
                    </div>
                </form>
                <h4 class="text-lg font-medium text-gray-700 flex items-center mb-3">
                    <i class="fas fa-list mr-2 text-primary-500"></i> Search Results
                    <span id="resultCount" class="ml-2 text-sm font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full"></span>
                </h4>
                <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 search-results-table" id="searchResultsTable">
                        <thead>
                            <tr>
                                <th scope="col" class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th scope="col" class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                <th scope="col" class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <!-- Search results will be inserted here -->
                        </tbody>
                    </table>
                    <div id="noResultsMessage" class="hidden py-5 text-center text-gray-500 bg-gray-50 rounded-md mt-3">
                        <i class="fas fa-exclamation-circle text-xl mb-2 text-gray-400"></i>
                        <p>No students found matching your search.</p>
                        <p class="text-sm mt-1">Try using a different name or spelling.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" id="cancelSearchBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 btn">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Sit-in Registration Modal - Step 2 (Registration Form) -->
    <div id="sitinModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-container">
        <div class="bg-white w-full max-w-md mx-4 modal-content">
            <div class="modal-header text-white px-4 py-3 flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-user-check mr-2"></i> Register New Sit-In
                </h3>
                <button id="closeModal" class="text-white hover:text-gray-200 focus:outline-none transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="sitinForm" action="process_sitin.php" method="POST">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="student_id">
                                Student ID
                            </label>
                            <input type="text" id="student_id" name="student_id" 
                                class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field" 
                                placeholder="Student ID" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="student_name">
                                Student Name
                            </label>
                            <input type="text" id="student_name" name="student_name" 
                                class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field" 
                                placeholder="Student name" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="remaining_session">
                                <i class="fas fa-ticket-alt mr-1 text-primary-500"></i> Remaining Session
                            </label>
                            <input type="text" id="remaining_session" name="remaining_session" readonly
                                class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="purpose">
                                <i class="fas fa-tasks mr-1 text-primary-500"></i> Purpose
                            </label>
                            <select id="purpose" name="purpose" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field" required>
                                <option value="" selected disabled>Select purpose</option>
                                <option value="C Programming">C Programming</option>
                                <option value="Java Programming">Java Programming</option>
                                <option value="C# Programming">C# Programming</option>
                                <option value="PHP Programming">PHP Programming</option>
                                <option value="ASP.net Programming">ASP.net Programming</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div id="othersContainer" class="hidden">
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="other_purpose">
                                Specify Purpose
                            </label>
                            <input type="text" id="other_purpose" name="other_purpose" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field" 
                                placeholder="Please specify your purpose">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2" for="lab_id">
                                <i class="fas fa-laptop-code mr-1 text-primary-500"></i> Laboratory
                            </label>
                            <select id="lab_id" name="lab_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 input-field" required>
                                <option value="">Select Laboratory</option>
                                <?php
                                // Fetch labs from database
                                $labs_query = "SELECT * FROM labs ORDER BY lab_name";
                                $labs_result = $conn->query($labs_query);
                                if ($labs_result && $labs_result->num_rows > 0) {
                                    while ($lab = $labs_result->fetch_assoc()) {
                                        echo '<option value="' . $lab['lab_id'] . '">' . htmlspecialchars($lab['lab_name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <!-- Add redirect parameter -->
                    <input type="hidden" name="redirect_to_current" value="1">
                    
                    <div id="formMessage" class="mt-4 p-3 rounded-md border hidden"></div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" id="backToSearchBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 btn flex items-center">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </button>
                        <button type="submit" id="submitSitIn" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 btn flex items-center">
                            <i class="fas fa-check-circle mr-1"></i> Register Sit-In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Already Sitting In Warning Modal -->
    <div id="alreadySittingInModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-container">
        <div class="bg-white w-full max-w-md mx-4 modal-content">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold text-red-600 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Student Already Sitting In
                </h3>
                <button class="closeWarningModal text-gray-400 hover:text-gray-500 focus:outline-none transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 rounded-md mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium" id="warningStudentName">
                                This student already has an active sit-in session.
                            </p>
                            <p class="text-xs mt-1">
                                Students cannot have multiple active sit-in sessions simultaneously.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a id="viewActiveSessionBtn" href="#" class="flex-1 px-4 py-2 bg-primary-600 text-white text-center rounded-md hover:bg-primary-700 transition btn flex items-center justify-center">
                        <i class="fas fa-eye mr-2"></i> View Active Session
                    </a>
                    <button class="closeWarningModal flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition btn flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
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

        // Toggle mobile dropdown menu
        document.querySelectorAll('.mobile-dropdown-button').forEach(button => {
            button.addEventListener('click', function() {
                this.nextElementSibling.classList.toggle('hidden');
            });
        });

        // User dropdown toggle
        function toggleUserDropdown() {
            document.getElementById('userMenu').classList.toggle('hidden');
        }

        // Desktop Sit-In dropdown toggle - improved implementation
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
        
        // Close dropdowns when clicking outside
        window.addEventListener('click', function(e) {
            // User menu dropdown
            if (!document.getElementById('userDropdown')?.contains(e.target)) {
                document.getElementById('userMenu')?.classList.add('hidden');
            }
            
            // Sit-In dropdown
            if (sitInDropdownMenu && !sitInDropdown?.contains(e.target)) {
                sitInDropdownMenu.classList.remove('show');
            }
        });

        // Mobile Sit-In dropdown toggle
        document.getElementById('mobile-sitin-dropdown').addEventListener('click', function() {
            document.getElementById('mobile-sitin-menu').classList.toggle('hidden');
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

        // Sit-In Modal Functionality
        const searchModal = document.getElementById('searchStudentModal');
        const sitinModal = document.getElementById('sitinModal');
        const warningModal = document.getElementById('alreadySittingInModal');
        const closeSearchBtn = document.getElementById('closeSearchModal');
        const closeBtn = document.getElementById('closeModal');
        const cancelSearchBtn = document.getElementById('cancelSearchBtn');
        const backToSearchBtn = document.getElementById('backToSearchBtn');
        const warningCloseBtns = document.querySelectorAll('.closeWarningModal');
        const purposeSelect = document.getElementById('purpose');
        const othersContainer = document.getElementById('othersContainer');
        const searchResultsContainer = document.getElementById('searchResults');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const resultCount = document.getElementById('resultCount');

        // Show search modal on button click - make sure all register buttons go to search first
        document.querySelectorAll('#registerSitinBtn, [id^=registerSitinBtn]').forEach(btn => {
            btn.addEventListener('click', function() {
                searchModal.classList.remove('hidden');
                searchModal.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
                document.getElementById('searchStudentName').focus();
                
                // Make sure the sit-in modal is hidden
                sitinModal.classList.add('hidden');
                sitinModal.classList.remove('show');
            });
        });

        // Close search modal buttons
        closeSearchBtn.addEventListener('click', function() {
            searchModal.classList.remove('show');
            setTimeout(() => {
                searchModal.classList.add('hidden');
                document.body.style.overflow = '';
                resetSearchForm();
            }, 300);
        });
        cancelSearchBtn.addEventListener('click', function() {
            searchModal.classList.remove('show');
            setTimeout(() => {
                searchModal.classList.add('hidden');
                document.body.style.overflow = '';
                resetSearchForm();
            }, 300);
        });

        // Close search modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === searchModal) {
                searchModal.classList.remove('show');
                setTimeout(() => {
                    searchModal.classList.add('hidden');
                    document.body.style.overflow = '';
                    resetSearchForm();
                }, 300);
            }
        });

        // Reset search form
        function resetSearchForm() {
            document.getElementById('studentSearchForm').reset();
            searchResultsContainer.classList.add('hidden');
            const resultsTable = document.querySelector('#searchResultsTable tbody');
            resultsTable.innerHTML = '';
            noResultsMessage.classList.add('hidden');
        }

        // Handle student search form submission
        document.getElementById('studentSearchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const searchName = document.getElementById('searchStudentName').value.trim();
            if (searchName.length < 2) {
                alert('Please enter at least 2 characters to search.');
                return;
            }

            // Fix: searchResultsContainer is not defined - use the table instead
            const resultsTable = document.querySelector('#searchResultsTable tbody');
            resultsTable.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="flex items-center justify-center"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Searching...</span></div></td></tr>';

            // Fetch search results
            fetch('../students/search_students_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'search_term=' + encodeURIComponent(searchName)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Search response:', data); // Debug output
                resultsTable.innerHTML = '';
                
                if (data.success && data.students && data.students.length > 0) {
                    // Update result count
                    resultCount.textContent = data.students.length + ' found';
                    
                    // Display search results
                    data.students.forEach(student => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';
                        
                        // Improved name formatting to include middle name/initial
                        let studentName = '';
                        if (student.name) {
                            // If name is already formatted, use it
                            studentName = student.name;
                        } else if (student.LASTNAME && student.FIRSTNAME) {
                            // Format as LASTNAME, FIRSTNAME MI.
                            studentName = student.LASTNAME + ', ' + student.FIRSTNAME;
                            // Add middle initial if available
                            if (student.MIDDLENAME) {
                                // If middle name exists, get first letter and add period
                                const middleInitial = student.MIDDLENAME.charAt(0) + '.';
                                studentName += ' ' + middleInitial;
                            } else if (student.MI) {
                                // If MI exists directly, add it with period if it doesn't have one 
                                const mi = student.MI.endsWith('.') ? student.MI : student.MI + '.';
                                studentName += ' ' + mi;
                            }
                        } else if (student.LastName && student.FirstName) {
                            // Alternative format for some database structures
                            studentName = student.LastName + ', ' + student.FirstName;
                            if (student.MiddleName) {
                                const middleInitial = student.MiddleName.charAt(0) + '.';
                                studentName += ' ' + middleInitial;
                            }
                        } else {
                            // Fallback if name fields are in different format
                            studentName = student.fullname || student.username || 'Unknown';
                        }

                        // Get student ID with fallback
                        const studentId = student.id || student.IDNO || student.idNo || student.student_id || 'N/A';

                        // Create cells
                        const idCell = document.createElement('td');
                        idCell.className = 'px-4 py-2 text-sm text-gray-900 font-medium';
                        idCell.textContent = studentId;
                        
                        const nameCell = document.createElement('td');
                        nameCell.className = 'px-4 py-2 text-sm text-gray-700';
                        nameCell.textContent = studentName;
                        
                        const courseCell = document.createElement('td');
                        courseCell.className = 'px-4 py-2 text-sm text-gray-700';
                        courseCell.textContent = student.COURSE || student.course || 'N/A';
                        
                        const yearCell = document.createElement('td');
                        yearCell.className = 'px-4 py-2 text-sm text-gray-700';
                        yearCell.textContent = student.YEARLEVEL || student.year_level || 'N/A';
                        
                        const emailCell = document.createElement('td');
                        emailCell.className = 'px-4 py-2 text-sm text-gray-700';
                        emailCell.textContent = student.EMAIL || student.email || 'N/A';
                        
                        const actionCell = document.createElement('td');
                        actionCell.className = 'px-4 py-2 text-center';
                        const selectBtn = document.createElement('button');
                        selectBtn.type = 'button';
                        selectBtn.className = 'px-3 py-1 bg-primary-600 text-white text-sm rounded hover:bg-primary-700 transition btn';
                        selectBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Select';
                        selectBtn.addEventListener('click', function() {
                            checkAndOpenModal(studentId, studentName);
                        });
                        actionCell.appendChild(selectBtn);
                        
                        // Append cells to row
                        row.appendChild(idCell);
                        row.appendChild(nameCell);
                        row.appendChild(courseCell);
                        row.appendChild(yearCell);
                        row.appendChild(emailCell);
                        row.appendChild(actionCell);
                        
                        // Append row to table
                        resultsTable.appendChild(row);
                    });
                    
                    noResultsMessage.classList.add('hidden');
                } else {
                    // Show no results message
                    resultCount.textContent = '0 found';
                    noResultsMessage.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error searching students:', error);
                resultsTable.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> Error searching students. Please try again.</td></tr>';
                resultCount.textContent = 'Error';
                noResultsMessage.classList.add('hidden');
            });
        });

        // Function to check if student is already sitting in
        function checkAndOpenModal(studentId, studentName) {
            // First check if student already has an active sit-in session
            fetch('check_active_session.php?student_id=' + encodeURIComponent(studentId))
                .then(response => response.json())
                .then(data => {
                    if (data.has_active_session) {
                        // Show warning modal
                        searchModal.classList.remove('show');
                        setTimeout(() => {
                            searchModal.classList.add('hidden');
                            document.body.style.overflow = '';
                            document.getElementById('warningStudentName').textContent = 
                                studentName + " already has an active sit-in session.";
                            document.getElementById('viewActiveSessionBtn').href = 
                                'current_sitin.php?search=' + encodeURIComponent(studentId);
                            warningModal.classList.remove('hidden');
                            warningModal.classList.add('show');
                        }, 300);
                    } else {
                        // Check remaining sessions before proceeding
                        fetch('../students/get_remaining_sessions.php?student_id=' + encodeURIComponent(studentId))
                            .then(response => response.json())
                            .then(sessionsData => {
                                if (sessionsData.success && sessionsData.remaining_sessions <= 0) {
                                    // Show no sessions warning
                                    searchModal.classList.remove('show');
                                    setTimeout(() => {
                                        searchModal.classList.add('hidden');
                                        document.body.style.overflow = '';
                                        document.getElementById('warningStudentName').textContent = 
                                            studentName + " has no remaining sit-in sessions. Please reset their sessions first.";
                                        document.getElementById('viewActiveSessionBtn').href = 
                                            '../students/reset_sessions.php?student_id=' + encodeURIComponent(studentId) + '&redirect=current_sitin.php';
                                        document.getElementById('viewActiveSessionBtn').innerHTML = 
                                            '<i class="fas fa-redo-alt mr-2"></i> Reset Sessions';
                                        warningModal.classList.remove('hidden');
                                        warningModal.classList.add('show');
                                    }, 300);
                                } else {
                                    // Proceed with opening sit-in modal
                                    openSitInModal(studentId, studentName, sessionsData.remaining_sessions);
                                }
                            })
                            .catch(error => {
                                console.error('Error checking remaining sessions:', error);
                                // If error, allow sit-in registration to continue with default value
                                openSitInModal(studentId, studentName, 30);
                            });
                    }
                })
                .catch(error => {
                    console.error('Error checking active session:', error);
                    // If error, proceed to check remaining sessions
                    fetch('../students/get_remaining_sessions.php?student_id=' + encodeURIComponent(studentId))
                        .then(response => response.json())
                        .then(sessionsData => {
                            openSitInModal(studentId, studentName, sessionsData.remaining_sessions);
                        })
                        .catch(error => {
                            console.error('Error fetching remaining sessions:', error);
                            // If error, allow sit-in registration to continue with default value
                            openSitInModal(studentId, studentName, 30);
                        });
                });
        }

        // Open sit-in modal function - Updated to accept remaining sessions parameter
        function openSitInModal(studentId, studentName, remainingSessions = null) {
            document.getElementById('student_id').value = studentId;
            document.getElementById('student_name').value = studentName;

            // Set loading indicator if remaining sessions not provided
            if (remainingSessions === null) {
                document.getElementById('remaining_session').value = "Loading..."; 
                
                // Fetch remaining sessions for this student
                fetch('../students/get_remaining_sessions.php?student_id=' + encodeURIComponent(studentId))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('remaining_session').value = data.remaining_sessions;
                            
                            // If using default value, show with indicator
                            if (data.default) {
                                document.getElementById('remaining_session').value = data.remaining_sessions + " (default)";
                            }
                        } else {
                            document.getElementById('remaining_session').value = "30 (default)";
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching remaining sessions:', error);
                        document.getElementById('remaining_session').value = "30 (default)";
                    });
            } else {
                // Use the provided remaining sessions value
                document.getElementById('remaining_session').value = remainingSessions;
            }

            // Hide search modal and show sit-in form modal
            searchModal.classList.remove('show');
            setTimeout(() => {
                searchModal.classList.add('hidden');
                sitinModal.classList.remove('hidden');
                sitinModal.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
            }, 300);
        }

        // Back to search buttons
        backToSearchBtn.addEventListener('click', function() {
            sitinModal.classList.remove('show');
            setTimeout(() => {
                sitinModal.classList.add('hidden');
                searchModal.classList.remove('hidden');
                searchModal.classList.add('show');
            }, 300);
        });

        // Close warning modal
        warningCloseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                warningModal.classList.remove('show');
                setTimeout(() => {
                    warningModal.classList.add('hidden');
                    document.body.style.overflow = ''; // Re-enable scrolling
                }, 300);
            });
        });

        // Close modal functions
        closeBtn.addEventListener('click', closeSitInModal);
        window.addEventListener('click', function(e) {
            if (e.target === sitinModal) {
                closeSitInModal();
            }
            if (e.target === warningModal) {
                warningModal.classList.remove('show');
                setTimeout(() => {
                    warningModal.classList.add('hidden');
                    document.body.style.overflow = ''; // Re-enable scrolling
                }, 300);
            }
        });

        // Show/hide other purpose input based on selection
        purposeSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                othersContainer.classList.remove('hidden');
            } else {
                othersContainer.classList.add('hidden');
            }
        });

        // Function to close the sit-in modal
        function closeSitInModal() {
            sitinModal.classList.remove('show');
            setTimeout(() => {
                sitinModal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable scrolling
                resetSitInForm();
            }, 300);
        }

        // Function to reset the sit-in form
        function resetSitInForm() {
            document.getElementById('sitinForm').reset();
            othersContainer.classList.add('hidden');
            document.getElementById('formMessage').innerHTML = '';
            document.getElementById('formMessage').classList.add('hidden');
        }

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
                                            <a href="javascript:void(0)" id="registerSitinBtn" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                                                <i class="fas fa-plus mr-2"></i> Register New Sit-In
                                            </a>
                                        </div>
                                    `);
                                    // Reattach event listener to the newly created button
                                    $('#registerSitinBtn').on('click', function() {
                                        $('#searchStudentModal').removeClass('hidden').addClass('show');
                                        document.body.style.overflow = 'hidden';
                                        $('#searchStudentName').focus();
                                    });
                                }
                            });

                            // Show success notification
                            $('#notificationContainer').append(`
                                <div class="notification success">
                                    <i class="fas fa-check-circle mr-2"></i> ${response.message}
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
                        } else {
                            // Show error notification
                            $('#notificationContainer').append(`
                                <div class="notification error">
                                    <i class="fas fa-exclamation-circle mr-2"></i> ${response.message}
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

        // Mobile dropdown toggle for Sit-In menu
        document.getElementById('mobile-sitin-dropdown').addEventListener('click', function() {
            document.getElementById('mobile-sitin-menu').classList.toggle('hidden');
        });
    </script>
</body>
</html>