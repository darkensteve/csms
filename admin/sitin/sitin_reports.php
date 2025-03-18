<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if user is logged in (admin only for reports)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Initialize variables
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$lab_filter = isset($_GET['lab_filter']) ? $_GET['lab_filter'] : '';
$purpose_filter = isset($_GET['purpose_filter']) ? $_GET['purpose_filter'] : '';

// Function to get daily report - updated to include purpose
function getDailyReport($conn, $date, $lab_filter = '', $purpose_filter = '') {
    $query = "SELECT s.*, 
                     u.FIRSTNAME, u.LASTNAME, u.IDNO,
                     l.lab_name,
                     TIMEDIFF(IFNULL(s.check_out_time, NOW()), s.check_in_time) as duration
              FROM sit_in_sessions s
              LEFT JOIN users u ON s.student_id = u.IDNO
              LEFT JOIN labs l ON s.lab_id = l.lab_id
              WHERE DATE(s.check_in_time) = ?";
    
    // Add additional filters if provided
    $params = array($date);
    $types = "s";
    
    if (!empty($lab_filter)) {
        $query .= " AND s.lab_id = ?";
        $params[] = $lab_filter;
        $types .= "i";
    }
    
    if (!empty($purpose_filter)) {
        // Change to exact match instead of LIKE for more accurate filtering
        $query .= " AND s.purpose = ?";
        $params[] = $purpose_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.check_in_time DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get weekly report - modified to return individual sessions instead of aggregates
function getWeeklyReport($conn, $start_date, $end_date, $lab_filter = '', $purpose_filter = '') {
    $query = "SELECT s.*, 
                     u.FIRSTNAME, u.LASTNAME, u.IDNO,
                     l.lab_name,
                     TIMEDIFF(IFNULL(s.check_out_time, NOW()), s.check_in_time) as duration
              FROM sit_in_sessions s
              LEFT JOIN users u ON s.student_id = u.IDNO
              LEFT JOIN labs l ON s.lab_id = l.lab_id
              WHERE s.check_in_time BETWEEN ? AND ?";
    
    // Add additional filters if provided
    $params = array($start_date, $end_date . ' 23:59:59');
    $types = "ss";
    
    if (!empty($lab_filter)) {
        $query .= " AND s.lab_id = ?";
        $params[] = $lab_filter;
        $types .= "i";
    }
    
    if (!empty($purpose_filter)) {
        // Change to exact match instead of LIKE for more accurate filtering
        $query .= " AND s.purpose = ?";
        $params[] = $purpose_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.check_in_time DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get monthly report - modified to return individual sessions instead of aggregates
function getMonthlyReport($conn, $start_date, $end_date, $lab_filter = '', $purpose_filter = '') {
    $query = "SELECT s.*, 
                     u.FIRSTNAME, u.LASTNAME, u.IDNO,
                     l.lab_name,
                     TIMEDIFF(IFNULL(s.check_out_time, NOW()), s.check_in_time) as duration
              FROM sit_in_sessions s
              LEFT JOIN users u ON s.student_id = u.IDNO
              LEFT JOIN labs l ON s.lab_id = l.lab_id
              WHERE s.check_in_time BETWEEN ? AND ?";
    
    // Add additional filters if provided
    $params = array($start_date, $end_date . ' 23:59:59');
    $types = "ss";
    
    if (!empty($lab_filter)) {
        $query .= " AND s.lab_id = ?";
        $params[] = $lab_filter;
        $types .= "i";
    }
    
    if (!empty($purpose_filter)) {
        // Change to exact match instead of LIKE for more accurate filtering
        $query .= " AND s.purpose = ?";
        $params[] = $purpose_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.check_in_time DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get available labs for filtering
function getLabs($conn) {
    $query = "SELECT lab_id, lab_name FROM labs ORDER BY lab_name";
    $result = $conn->query($query);
    return $result;
}

// Define standard purpose options to match search_student.php (without "Others")
$purposeOptions = [
    'C Programming',
    'Java Programming',
    'C# Programming',
    'PHP Programming',
    'ASP.net Programming'
];

// Function to format duration
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $mins);
}

// Get labs for filter dropdown
$labs = getLabs($conn);

// Get report data based on type
$report_data = null;
if ($report_type == 'daily') {
    $report_data = getDailyReport($conn, $start_date, $lab_filter, $purpose_filter);
} elseif ($report_type == 'weekly') {
    $report_data = getWeeklyReport($conn, $start_date, $end_date, $lab_filter, $purpose_filter);
} elseif ($report_type == 'monthly') {
    $report_data = getMonthlyReport($conn, $start_date, $end_date, $lab_filter, $purpose_filter);
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add Flatpickr for better date picking -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Add DataTables CSS for better tables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
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
        .report-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .filter-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .report-table {
            width: 100%;
            margin-top: 20px;
        }
        .tab-buttons {
            margin-bottom: 20px;
        }
        .export-btn {
            margin-left: 10px;
        }
        .table-container { 
            overflow-x: auto; 
        }
        /* DataTables custom styling */
        .dataTables_wrapper .dt-buttons button {
            background-color: #0ea5e9 !important;
            color: white !important;
            border: none !important;
            border-radius: 0.25rem !important;
            padding: 0.5rem 1rem !important;
            margin-right: 0.5rem !important;
            margin-bottom: 1rem !important;
        }
        .dataTables_wrapper .dt-buttons button:hover {
            background-color: #0284c7 !important;
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="../admin.php" class="text-xl font-bold">Dashboard</a>
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
                        <a href="current_sitin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-user-check mr-1"></i> Sit-In
                        </a>
                        <a href="sitin_records.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-list mr-1"></i> Records
                        </a>
                        <a href="sitin_reports.php" class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i> Reports
                        </a>
                        <a href="feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
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
        <a href="current_sitin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="sitin_records.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-list mr-2"></i> Records
        </a>
        <a href="sitin_reports.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
        <a href="feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
    </div>
    
    <div class="flex-1 container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                <h2 class="text-xl font-semibold"><i class="fas fa-chart-bar mr-2"></i> Sit-In Reports</h2>
            </div>
            
            <div class="p-6">
                <!-- Report Type Tabs -->
                <div class="tab-buttons">
                    <a href="?report_type=daily&start_date=<?= date('Y-m-d') ?>" 
                       class="btn <?= $report_type == 'daily' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' ?> px-4 py-2 rounded hover:opacity-90">
                        Daily Report
                    </a>
                    <a href="?report_type=weekly&start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>" 
                       class="btn <?= $report_type == 'weekly' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' ?> px-4 py-2 rounded hover:opacity-90 ml-2">
                        Weekly Report
                    </a>
                    <a href="?report_type=monthly&start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" 
                       class="btn <?= $report_type == 'monthly' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' ?> px-4 py-2 rounded hover:opacity-90 ml-2">
                        Monthly Report
                    </a>
                </div>
                
                <!-- Enhanced Filter Form -->
                <div class="filter-form">
                    <form method="GET" action="" class="flex flex-wrap gap-4">
                        <input type="hidden" name="report_type" value="<?= $report_type ?>">
                        <div class="flex flex-wrap gap-4">
                            <?php if ($report_type == 'daily'): ?>
                                <div class="w-full sm:w-auto">
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Date:</label>
                                    <input type="text" class="form-control date-picker px-3 py-2 border rounded" id="start_date" name="start_date" value="<?= $start_date ?>">
                                </div>
                            <?php else: ?>
                                <div class="w-full sm:w-auto">
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                                    <input type="text" class="form-control date-picker px-3 py-2 border rounded" id="start_date" name="start_date" value="<?= $start_date ?>">
                                </div>
                                <div class="w-full sm:w-auto">
                                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                                    <input type="text" class="form-control date-picker px-3 py-2 border rounded" id="end_date" name="end_date" value="<?= $end_date ?>">
                                </div>
                            <?php endif; ?>
                            
                            <!-- Laboratory Filter -->
                            <div class="w-full sm:w-auto">
                                <label for="lab_filter" class="block text-sm font-medium text-gray-700 mb-1">Laboratory:</label>
                                <select name="lab_filter" id="lab_filter" class="px-3 py-2 border rounded w-full">
                                    <option value="">All Laboratories</option>
                                    <?php if($labs): while($lab = $labs->fetch_assoc()): ?>
                                        <option value="<?= $lab['lab_id'] ?>" <?= $lab_filter == $lab['lab_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lab['lab_name']) ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            
                            <!-- Purpose Filter - Updated to use predefined options -->
                            <div class="w-full sm:w-auto">
                                <label for="purpose_filter" class="block text-sm font-medium text-gray-700 mb-1">Purpose:</label>
                                <select name="purpose_filter" id="purpose_filter" class="px-3 py-2 border rounded w-full">
                                    <option value="">All Purposes</option>
                                    <?php foreach ($purposeOptions as $purpose): ?>
                                        <option value="<?= $purpose ?>" <?= $purpose_filter == $purpose ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($purpose) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="w-full sm:w-auto self-end">
                                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                                    <i class="fas fa-filter mr-1"></i> Apply Filter
                                </button>
                                <a href="?report_type=<?= $report_type ?>" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 ml-2">
                                    <i class="fas fa-redo mr-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Report Content -->
                <div class="report-container">
                    <?php if ($report_type == 'daily'): ?>
                        <h3 class="text-xl font-semibold mb-4">Daily Report: <?= date('F d, Y', strtotime($start_date)) ?></h3>
                    <?php elseif ($report_type == 'weekly'): ?>
                        <h3 class="text-xl font-semibold mb-4">Weekly Report: <?= date('M d', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></h3>
                    <?php elseif ($report_type == 'monthly'): ?>
                        <h3 class="text-xl font-semibold mb-4">Monthly Report: <?= date('F Y', strtotime($start_date)) ?></h3>
                    <?php endif; ?>
                    
                    <?php if ($report_data && $report_data->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="min-w-full bg-white border border-gray-200 report-table" id="reportTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Laboratory</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time-In</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time-Out</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php while ($row = $report_data->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <div><?= htmlspecialchars($row['FIRSTNAME'] . ' ' . $row['LASTNAME']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['student_id']) ?></div>
                                            </td>
                                            <td class="px-4 py-2"><?= htmlspecialchars($row['purpose'] ?? 'N/A') ?></td>
                                            <td class="px-4 py-2"><?= htmlspecialchars($row['lab_name'] ?? 'Lab ' . $row['lab_id']) ?></td>
                                            <td class="px-4 py-2"><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                                            <td class="px-4 py-2">
                                                <?= $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : 'Still Active' ?>
                                            </td>
                                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($row['check_in_time'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 text-yellow-700">
                            <?php
                            $message = "No sit-in records found for the selected period";
                            if (!empty($lab_filter) || !empty($purpose_filter)) {
                                $message .= " with the applied filters:";
                                if (!empty($lab_filter)) {
                                    $lab_name = "";
                                    $labs->data_seek(0);
                                    while ($lab = $labs->fetch_assoc()) {
                                        if ($lab['lab_id'] == $lab_filter) {
                                            $lab_name = $lab['lab_name'];
                                            break;
                                        }
                                    }
                                    $message .= "<br>• Laboratory: " . htmlspecialchars($lab_name);
                                }
                                if (!empty($purpose_filter)) {
                                    $message .= "<br>• Purpose: " . htmlspecialchars($purpose_filter);
                                }
                            } else {
                                $message .= ".";
                            }
                            echo $message;
                            ?>
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
        // Initialize Flatpickr for better date selection
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".date-picker", {
                dateFormat: "Y-m-d",
                allowInput: true,
            });
            // Initialize DataTables with export buttons
            if (document.getElementById('reportTable')) {
                $('#reportTable').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                            className: 'bg-green-600 hover:bg-green-700'
                        },
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                            className: 'bg-green-600 hover:bg-green-700'
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                            className: 'bg-red-600 hover:bg-red-700'
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print mr-1"></i> Print',
                            className: 'bg-blue-600 hover:bg-blue-700'
                        }
                    ],
                    "pageLength": 25
                });
            }
        });
    </script>
</body>
</html>