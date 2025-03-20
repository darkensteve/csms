<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: ../auth/login_admin.php");
    exit;
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Database connection
require_once '../includes/db_connect.php';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Initialize messages array
$messages = [];

// Handle computer status toggle (reserve/unreserve)
if (isset($_POST['toggle_computer'])) {
    $computer_id = $_POST['computer_id'];
    $new_status = $_POST['new_status'];
    $lab_id = $_POST['lab_id'];
    
    // Update the computer status
    $update_query = "UPDATE computers SET status = ? WHERE computer_id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $computer_id);
        if ($stmt->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => "Computer #$computer_id has been " . ($new_status == 'available' ? 'unreserved' : 'reserved')
            ];
        } else {
            $messages[] = [
                'type' => 'error',
                'text' => "Failed to update computer status: " . $stmt->error
            ];
        }
        $stmt->close();
    }
}

// Handle reservation approval/rejection
if (isset($_POST['update_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action'];
    $new_status = ($action == 'approve') ? 'approved' : 'rejected';
    
    // Get the computer_id associated with this reservation
    $get_computer_query = "SELECT computer_id, lab_id FROM reservations WHERE reservation_id = ?";
    $stmt_get = $conn->prepare($get_computer_query);
    $computer_id = null;
    $lab_id = null;
    
    if ($stmt_get) {
        $stmt_get->bind_param("i", $reservation_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        if ($row = $result->fetch_assoc()) {
            $computer_id = $row['computer_id'];
            $lab_id = $row['lab_id'];
        }
        $stmt_get->close();
    }
    
    // Update the reservation status
    $update_query = "UPDATE reservations SET status = ? WHERE reservation_id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $reservation_id);
        if ($stmt->execute()) {
            // If approved, update the computer status to 'used'
            if ($action == 'approve' && $computer_id) {
                $computer_status = 'used';
                $update_computer_query = "UPDATE computers SET status = ? WHERE computer_id = ?";
                $stmt_computer = $conn->prepare($update_computer_query);
                if ($stmt_computer) {
                    $stmt_computer->bind_param("si", $computer_status, $computer_id);
                    $stmt_computer->execute();
                    $stmt_computer->close();
                }
            } else if ($action == 'reject' && $computer_id) {
                // If rejected, set the computer back to 'available'
                $computer_status = 'available';
                $update_computer_query = "UPDATE computers SET status = ? WHERE computer_id = ?";
                $stmt_computer = $conn->prepare($update_computer_query);
                if ($stmt_computer) {
                    $stmt_computer->bind_param("si", $computer_status, $computer_id);
                    $stmt_computer->execute();
                    $stmt_computer->close();
                }
            }
            
            $messages[] = [
                'type' => 'success',
                'text' => "Reservation #$reservation_id has been $new_status"
            ];
        } else {
            $messages[] = [
                'type' => 'error',
                'text' => "Failed to update reservation: " . $stmt->error
            ];
        }
        $stmt->close();
    }
}

// Fetch labs
$labs = [];
$labs_query = "SELECT * FROM labs ORDER BY lab_name";
$labs_result = $conn->query($labs_query);
if ($labs_result && $labs_result->num_rows > 0) {
    while ($lab = $labs_result->fetch_assoc()) {
        $labs[$lab['lab_id']] = $lab;
    }
}

// Check if computers table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'computers'");
if ($table_check->num_rows == 0) {
    // Create computers table with 'used' status option
    $create_computers_table = "CREATE TABLE `computers` (
        `computer_id` int(11) NOT NULL AUTO_INCREMENT,
        `lab_id` int(11) NOT NULL,
        `computer_number` int(11) NOT NULL,
        `status` enum('available','reserved','used','maintenance') NOT NULL DEFAULT 'available',
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`computer_id`),
        KEY `lab_id` (`lab_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_computers_table);
    
    // Seed some initial data if labs exist
    if (count($labs) > 0) {
        foreach ($labs as $lab) {
            $lab_id = $lab['lab_id'];
            $capacity = $lab['capacity'] ?? 30;
            
            for ($i = 1; $i <= $capacity; $i++) {
                $insert_query = "INSERT INTO computers (lab_id, computer_number, status) VALUES (?, ?, 'available')";
                $stmt = $conn->prepare($insert_query);
                if ($stmt) {
                    $stmt->bind_param("ii", $lab_id, $i);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
} else {
    // Check if 'used' status exists in the enum, if not alter the table
    $check_enum = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
    if ($check_enum && $check_enum->num_rows > 0) {
        $enum_info = $check_enum->fetch_assoc();
        if (strpos($enum_info['Type'], 'used') === false) {
            // Add 'used' to the enum
            $conn->query("ALTER TABLE computers MODIFY status ENUM('available','reserved','used','maintenance') NOT NULL DEFAULT 'available'");
        }
    }
}

// Check if reservations table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'reservations'");
if ($table_check->num_rows == 0) {
    // Create reservations table - make sure it matches the structure in create_tables.php
    $create_reservations_table = "CREATE TABLE `reservations` (
        `reservation_id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `lab_id` INT(11) NOT NULL,
        `computer_id` INT(11) DEFAULT NULL,
        `reservation_date` DATE NOT NULL,
        `time_slot` VARCHAR(50) NOT NULL,
        `purpose` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`reservation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_reservations_table);
} else {
    // Check if computer_id column exists, if not add it
    $check_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'computer_id'");
    if ($check_column->num_rows == 0) {
        // Add computer_id column
        $conn->query("ALTER TABLE reservations ADD COLUMN computer_id INT(11) DEFAULT NULL AFTER lab_id");
    }
}

// Filter parameters for logs
$filter_lab = isset($_GET['filter_lab']) ? (int)$_GET['filter_lab'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

// Selected lab for computer control
$selected_lab_id = isset($_GET['lab_id']) ? (int)$_GET['lab_id'] : (isset($labs[array_key_first($labs)]) ? array_key_first($labs) : 0);

// Fetch computers for the selected lab
$computers = [];
if ($selected_lab_id > 0) {
    $computers_query = "SELECT * FROM computers WHERE lab_id = ? ORDER BY computer_number";
    $stmt = $conn->prepare($computers_query);
    if ($stmt) {
        $stmt->bind_param("i", $selected_lab_id);
        $stmt->execute();
        $computers_result = $stmt->get_result();
        if ($computers_result && $computers_result->num_rows > 0) {
            while ($computer = $computers_result->fetch_assoc()) {
                $computers[] = $computer;
            }
        }
        $stmt->close();
    }
}

// Fetch pending reservation requests with computer info
$pending_reservations = [];
$pending_query = "SELECT r.*, l.lab_name, u.IDNO as student_id, CONCAT(u.FIRSTNAME, ' ', u.MIDDLENAME, ' ', u.LASTNAME) as student_name, 
                 c.computer_number, c.status as computer_status
                 FROM reservations r 
                 JOIN labs l ON r.lab_id = l.lab_id
                 JOIN users u ON r.user_id = u.USER_ID 
                 LEFT JOIN computers c ON r.computer_id = c.computer_id
                 WHERE r.status = 'pending' 
                 ORDER BY r.created_at DESC";
$pending_result = $conn->query($pending_query);
if ($pending_result && $pending_result->num_rows > 0) {
    while ($reservation = $pending_result->fetch_assoc()) {
        $pending_reservations[] = $reservation;
    }
}

// Fetch reservation logs with filtering - Add JOIN with users table
$logs = [];
$logs_query = "SELECT r.*, l.lab_name, u.IDNO as student_id, CONCAT(u.FIRSTNAME, ' ', u.MIDDLENAME, ' ', u.LASTNAME) as student_name,
               c.computer_number 
               FROM reservations r 
               JOIN labs l ON r.lab_id = l.lab_id
               JOIN users u ON r.user_id = u.USER_ID
               LEFT JOIN computers c ON r.computer_id = c.computer_id
               WHERE 1=1";

// Apply filters
if ($filter_lab > 0) {
    $logs_query .= " AND r.lab_id = $filter_lab";
}
if (!empty($filter_status)) {
    $logs_query .= " AND r.status = '$filter_status'";
}
if (!empty($filter_date)) {
    $logs_query .= " AND r.reservation_date = '$filter_date'";
}

$logs_query .= " ORDER BY r.created_at DESC LIMIT 100";
$logs_result = $conn->query($logs_query);
if ($logs_result && $logs_result->num_rows > 0) {
    while ($log = $logs_result->fetch_assoc()) {
        $logs[] = $log;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management | Admin Dashboard</title>
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
            font-family: 'Inter', sans-serif;
        }
        
        .computer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
        }
        
        .computer-item {
            border-radius: 0.5rem;
            padding: 0.75rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .computer-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .computer-available {
            background-color: #d1fae5;
            border: 1px solid #10b981;
        }
        
        .computer-reserved {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
        }
        
        .computer-used {
            background-color: #dbeafe;
            border: 1px solid #3b82f6;
        }
        
        .computer-maintenance {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .notification.success {
            background-color: #10b981;
        }
        
        .notification.error {
            background-color: #ef4444;
        }
        
        .section-card {
            height: calc(100vh - 200px);
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Custom scrollbar */
        .section-card::-webkit-scrollbar {
            width: 6px;
        }
        
        .section-card::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .section-card::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        .section-card::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        @media (max-width: 1280px) {
            .section-card {
                height: auto;
                max-height: 600px;
            }
        }
        
        /* Enhanced professional styling */
        .section-card {
            height: calc(100vh - 200px);
            overflow-y: auto;
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .section-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            position: sticky;
            top: 0;
            z-index: 10;
            border-radius: 0.75rem 0.75rem 0 0;
        }
        
        /* Enhanced table styling */
        .data-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .data-table th {
            background-color: #f9fafb;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table td {
            padding: 0.875rem 1rem;
            vertical-align: top;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .data-table tr:hover {
            background-color: #f9fafb;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Improved action buttons */
        .action-btn {
            padding: 0.5rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        .action-btn-approve {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .action-btn-approve:hover {
            background-color: #a7f3d0;
        }
        
        .action-btn-reject {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .action-btn-reject:hover {
            background-color: #fecaca;
        }
        
        .action-btn-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .action-btn-info:hover {
            background-color: #bfdbfe;
        }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="../admin.php" class="text-xl font-bold">Admin Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="../admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="../students/search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="../students/student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
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
                        <a href="reservation.php" class="bg-primary-800 px-3 py-2 rounded transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="../newp.jpg" alt="Admin" class="w-full h-full object-cover">
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
        <a href="../students/search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="../students/student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
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
        <a href="reservation.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
    </div>
    
    <!-- Notification messages -->
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $message): ?>
            <div class="notification <?php echo $message['type']; ?> show">
                <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message['text']; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <!-- Dashboard Header -->
        <div class="container mx-auto mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Reservation Management</h1>
            <p class="text-gray-600">Manage computer reservations, requests, and view activity logs</p>
        </div>
        
        <!-- Adjusted grid layout with different column spans -->
        <div class="container mx-auto grid grid-cols-1 xl:grid-cols-5 gap-6">
            <!-- Computer Control Section - 2 columns -->
            <div class="xl:col-span-2 bg-white rounded-xl shadow-md section-card">
                <div class="section-header bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Computer Control</h2>
                    <span class="bg-white bg-opacity-30 text-xs font-medium py-1 px-2 rounded-full">
                        <i class="fas fa-desktop mr-1"></i> Manage PCs
                    </span>
                </div>
                <div class="p-5">
                    <!-- Laboratory Selection -->
                    <div class="mb-5">
                        <form action="reservation.php" method="GET" class="flex flex-wrap gap-3">
                            <div class="flex-1">
                                <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Select Laboratory</label>
                                <select id="lab_id" name="lab_id" onchange="this.form.submit()" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-sm">
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($selected_lab_id == $lab['lab_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lab['lab_name']); ?> 
                                            (<?php echo $lab['capacity']; ?> PCs)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition text-sm">
                                    <i class="fas fa-sync-alt mr-1"></i> Update
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Computer Grid -->
                    <div id="computer-grid">
                        <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-map-marker-alt text-primary-600 mr-2"></i>
                            <?php echo (isset($labs[$selected_lab_id])) ? htmlspecialchars($labs[$selected_lab_id]['lab_name']) : 'Select Laboratory'; ?>
                        </h3>
                        
                        <?php if (count($computers) > 0): ?>
                            <div class="computer-grid">
                                <?php foreach ($computers as $computer): ?>
                                    <div class="computer-item computer-<?php echo $computer['status']; ?>" 
                                         data-id="<?php echo $computer['computer_id']; ?>"
                                         data-status="<?php echo $computer['status']; ?>"
                                         onclick="toggleComputerStatus(
                                             <?php echo $computer['computer_id']; ?>, 
                                             '<?php echo $computer['status']; ?>', 
                                             <?php echo $selected_lab_id; ?>,
                                             <?php echo $computer['computer_number']; ?>
                                         )">
                                        <div class="text-xl">
                                            <?php if ($computer['status'] == 'available'): ?>
                                                <i class="fas fa-desktop text-green-600"></i>
                                            <?php elseif ($computer['status'] == 'reserved'): ?>
                                                <i class="fas fa-desktop text-red-600"></i>
                                            <?php elseif ($computer['status'] == 'used'): ?>
                                                <i class="fas fa-desktop text-blue-600"></i>
                                            <?php else: ?>
                                                <i class="fas fa-tools text-amber-600"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="font-medium text-sm mt-1">PC #<?php echo $computer['computer_number']; ?></div>
                                        <div class="text-xs mt-0.5 capitalize text-gray-600"><?php echo $computer['status']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 bg-gray-50 rounded-lg">
                                <div class="text-gray-400 text-4xl mb-3">
                                    <i class="fas fa-laptop"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No computers found</h3>
                                <p class="text-gray-500 text-sm">Select a laboratory or add computers</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Legend -->
                    <div class="flex flex-wrap gap-3 justify-center mt-5 pt-4 border-t border-gray-100">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-200 border border-green-600 rounded-full mr-1"></div>
                            <span class="text-xs">Available</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-red-200 border border-red-600 rounded-full mr-1"></div>
                            <span class="text-xs">Reserved</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-200 border border-blue-600 rounded-full mr-1"></div>
                            <span class="text-xs">In Use</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-amber-200 border border-amber-600 rounded-full mr-1"></div>
                            <span class="text-xs">Maintenance</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reservation Requests Section - now spans 2 columns -->
            <div class="xl:col-span-2 bg-white rounded-xl shadow-md section-card">
                <div class="section-header bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Reservation Requests</h2>
                    <span class="bg-white bg-opacity-30 text-xs font-medium py-1 px-2 rounded-full">
                        <i class="fas fa-clock mr-1"></i> Pending: <?php echo count($pending_reservations); ?>
                    </span>
                </div>
                <div class="p-5">
                    <?php if (count($pending_reservations) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="data-table min-w-full">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC Request</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_reservations as $reservation): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($reservation['student_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($reservation['student_id']); ?></div>
                                                <div class="text-xs text-primary-600 mt-1"><?php echo htmlspecialchars($reservation['lab_name']); ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-700"><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $reservation['time_slot']; ?></div>
                                                <div class="text-xs text-gray-400 mt-1 line-clamp-1 italic">"<?php echo htmlspecialchars($reservation['purpose']); ?>"</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col">
                                                    <span class="text-xs bg-gray-100 py-1 px-2 rounded inline-block w-fit mb-1">Res #<?php echo $reservation['reservation_id']; ?></span>
                                                    <?php
                                                    // Check for available computers in the requested lab
                                                    $avail_query = "SELECT COUNT(*) as available FROM computers 
                                                                    WHERE lab_id = ? AND status = 'available'";
                                                    $stmt = $conn->prepare($avail_query);
                                                    if ($stmt) {
                                                        $stmt->bind_param("i", $reservation['lab_id']);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();
                                                        $available = $result->fetch_assoc()['available'];
                                                        $stmt->close();
                                                        
                                                        if ($available > 0) {
                                                            echo "<span class='text-xs text-green-600 flex items-center'><i class='fas fa-check-circle mr-1'></i> {$available} PCs available</span>";
                                                        } else {
                                                            echo "<span class='text-xs text-red-600 flex items-center'><i class='fas fa-exclamation-circle mr-1'></i> No PCs available</span>";
                                                        }
                                                    }
                                                    ?>
                                                    <button onclick="viewAvailablePCs(<?php echo $reservation['lab_id']; ?>)" 
                                                            class="text-xs text-blue-600 hover:underline mt-2 flex items-center">
                                                        <i class="fas fa-desktop mr-1"></i> View available PCs
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex gap-2">
                                                    <form action="reservation.php" method="POST">
                                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" name="update_reservation" class="action-btn action-btn-approve" title="Approve Request">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form action="reservation.php" method="POST">
                                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" name="update_reservation" class="action-btn action-btn-reject" title="Reject Request">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                    <button class="action-btn action-btn-info" title="View Details" onclick="alert('Purpose: <?php echo addslashes($reservation['purpose']); ?>')">
                                                        <i class="fas fa-info"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg">
                            <div class="text-gray-400 text-4xl mb-3">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="text-base font-medium text-gray-900 mb-1">No pending requests</h3>
                            <p class="text-gray-500 text-sm">All reservation requests have been processed</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reservation Logs Section - 1 column -->
            <div class="xl:col-span-1 bg-white rounded-xl shadow-md section-card">
                <div class="section-header bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Activity Logs</h2>
                    <button onclick="document.getElementById('filter-form').classList.toggle('hidden')" class="bg-white bg-opacity-30 hover:bg-opacity-40 transition text-xs font-medium py-1 px-2 rounded-full">
                        <i class="fas fa-filter mr-1"></i> Filters
                    </button>
                </div>
                <div class="p-5">
                    <!-- Filters - Hidden by Default -->
                    <div id="filter-form" class="mb-5 hidden bg-gray-50 p-3 rounded-lg">
                        <form action="reservation.php" method="GET" class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="filter_lab" class="block text-xs font-medium text-gray-700 mb-1">Laboratory</label>
                                <select id="filter_lab" name="filter_lab" class="block w-full px-2 py-1.5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-sm">
                                    <option value="0">All Laboratories</option>
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($filter_lab == $lab['lab_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lab['lab_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="filter_status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                    <select id="filter_status" name="filter_status" class="block w-full px-2 py-1.5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-sm">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo ($filter_status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="filter_date" class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                                    <input type="date" id="filter_date" name="filter_date" value="<?php echo $filter_date; ?>" class="block w-full px-2 py-1.5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-sm">
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="w-full py-1.5 bg-primary-600 text-white rounded hover:bg-primary-700 transition text-sm">
                                    <i class="fas fa-filter mr-1"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Logs List - Enhanced styling -->
                    <?php if (count($logs) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($logs as $log): ?>
                                <div class="border border-gray-200 rounded-lg p-3 hover:shadow-sm transition bg-white">
                                    <div class="flex justify-between items-start mb-1">
                                        <div>
                                            <span class="font-medium text-sm text-gray-900">#<?php echo $log['reservation_id']; ?></span>
                                            <span class="text-xs text-gray-500 ml-2"><?php echo date('M d, Y', strtotime($log['reservation_date'])); ?></span>
                                        </div>
                                        <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($log['status']) {
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'cancelled': echo 'bg-gray-100 text-gray-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="text-xs font-semibold text-primary-700"><?php echo htmlspecialchars($log['lab_name']); ?></div>
                                        <div class="text-xs text-gray-500 mx-2">•</div>
                                        <div class="text-xs text-gray-500"><?php echo $log['time_slot']; ?></div>
                                    </div>
                                    <div class="flex items-center mt-2 bg-gray-50 p-1.5 rounded">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 text-xs">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="ml-2 text-sm truncate">
                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($log['student_name']); ?></span>
                                            <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($log['student_id']); ?></span>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-600 line-clamp-1 bg-gray-50 p-1.5 rounded">
                                        <span class="font-medium">Purpose:</span> <?php echo htmlspecialchars($log['purpose']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg">
                            <div class="text-gray-400 text-4xl mb-3">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h3 class="text-base font-medium text-gray-900 mb-1">No logs found</h3>
                            <p class="text-gray-500 text-sm">No reservations match your criteria</p>
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
        
        // Toggle computer status
        function toggleComputerStatus(computerId, currentStatus, labId, computerNumber) {
            let newStatus;
            
            // Cycle through the three states: available → reserved → maintenance → available
            if (currentStatus === 'available') {
                newStatus = 'reserved';
            } else if (currentStatus === 'reserved') {
                newStatus = 'maintenance';
            } else {
                newStatus = 'available';
            }
            
            if (confirm(`Do you want to change PC #${computerNumber} to ${newStatus}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'reservation.php';
                
                const computerIdInput = document.createElement('input');
                computerIdInput.type = 'hidden';
                computerIdInput.name = 'toggle_computer';
                computerIdInput.value = 'true';
                form.appendChild(computerIdInput);
                
                const computerIdField = document.createElement('input');
                computerIdField.type = 'hidden';
                computerIdField.name = 'computer_id';
                computerIdField.value = computerId;
                form.appendChild(computerIdField);
                
                const newStatusInput = document.createElement('input');
                newStatusInput.type = 'hidden';
                newStatusInput.name = 'new_status';
                newStatusInput.value = newStatus;
                form.appendChild(newStatusInput);
                
                const labIdInput = document.createElement('input');
                labIdInput.type = 'hidden';
                labIdInput.name = 'lab_id';
                labIdInput.value = labId;
                form.appendChild(labIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // View available PCs in a lab
        function viewAvailablePCs(labId) {
            // Load available PCs for this lab
            // This could be implemented with AJAX, but for simplicity we'll just redirect
            window.location.href = `reservation.php?lab_id=${labId}#computer-grid`;
        }
        
        // Additional script for responsive behavior
        window.addEventListener('resize', adjustSectionHeight);
        
        function adjustSectionHeight() {
            if (window.innerWidth < 1280) {
                document.querySelectorAll('.section-card').forEach(card => {
                    card.style.height = 'auto';
                });
            } else {
                document.querySelectorAll('.section-card').forEach(card => {
                    card.style.height = 'calc(100vh - 200px)';
                });
            }
        }
        
        // Run on page load
        adjustSectionHeight();
    </script>
</body>
</html>
