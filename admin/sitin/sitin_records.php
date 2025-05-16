<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login_admin.php");
    exit;
}

// Set timezone to GMT+8 (Philippines/Manila)
date_default_timezone_set('Asia/Manila');

// Include datetime helper
require_once '../includes/datetime_helper.php';

// Include data sync helper for potential updates
require_once '../includes/data_sync_helper.php';

// Function to ensure times are formatted in GMT+8a/Asia timezone (GMT+8)
function format_time_gmt8($datetime_string) {
    if (empty($datetime_string)) return '';
    
    // Force conversion to Manila timezone regardless of stored format
    $dt = new DateTime($datetime_string);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    
    // Format time in 12-hour format with AM/PM in Manila local time
    return $dt->format('h:i A');
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "csms";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Make sure timezone is set for MySQL connection
$conn->query("SET time_zone = '+08:00'");

// Get admin username for display
$admin_username = $_SESSION['admin_username'];

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get filters from URL parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'today'; // Default to today's records

// Get today's date in Y-m-d format for SQL comparison (using the set timezone)
$today = date('Y-m-d');

// Build the query based on the actual table structure
$query = "SELECT s.*, 
          s.student_id as student_id, 
          s.student_name as student_name,  
          SUBSTRING_INDEX(s.student_name, ',', 1) as last_name,
          TRIM(SUBSTRING(s.student_name, LOCATE(',', s.student_name) + 1)) as first_name,
          l.lab_name
          FROM sit_in_sessions s 
          LEFT JOIN labs l ON s.lab_id = l.lab_id
          WHERE 1=1";

// Apply date filter - ensure consistent date comparison using DATE() function
if ($date_filter === 'today') {
    $query .= " AND DATE(s.check_in_time) = '$today'";
}

// Apply search filter
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (s.student_id LIKE '%$search%' 
                OR s.student_name LIKE '%$search%' 
                OR s.purpose LIKE '%$search%')";
}

// Apply status filter with optimized case-insensitive comparison
if ($status !== 'all') {
    $status = mysqli_real_escape_string($conn, $status);
    // Use case-insensitive comparison to ensure reliable filtering
    $query .= " AND (s.status = '$status' OR LOWER(s.status) = LOWER('$status'))";
}

// Apply sorting
switch ($sort) {
    case 'date_asc':
        $query .= " ORDER BY s.check_in_time ASC";
        break;
    case 'name_asc':
        $query .= " ORDER BY s.student_name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY s.student_name DESC";
        break;
    case 'date_desc':
    default:
        $query .= " ORDER BY s.check_in_time DESC";
        break;
}

// Count total records for pagination (before applying LIMIT)
$count_query = $query;
$count_result = mysqli_query($conn, $count_query);
$total_records = $count_result ? mysqli_num_rows($count_result) : 0;
$total_pages = ceil($total_records / $records_per_page);

// Apply pagination limit
$query .= " LIMIT $offset, $records_per_page";

$result = mysqli_query($conn, $query);
if (!$result) {
    // Simple error message without debug details
    echo '<div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
            There was an error processing your request. Please try again or contact the administrator.
          </div>';
}

// Create filter description for no results message
$filter_description = '';
if ($status !== 'all') {
    $filter_description .= "status: " . ucfirst($status);
}
if (!empty($search)) {
    $filter_description .= (!empty($filter_description) ? ", " : "") . "search: '$search'";
}
if ($date_filter === 'today') {
    $filter_description .= (!empty($filter_description) ? ", " : "") . "date: Today only";
}

// Get data for purpose distribution chart
$purpose_query = "SELECT purpose, COUNT(*) as count FROM sit_in_sessions";

// Build WHERE clause for purpose query
$purpose_where_clauses = array();

if ($date_filter === 'today') {
    $purpose_where_clauses[] = "DATE(check_in_time) = '$today'";
}

// Apply status filter to purpose chart data
if ($status !== 'all') {
    $purpose_where_clauses[] = "(status = '$status' OR LOWER(status) = LOWER('$status'))";
}

// Add WHERE clause if we have conditions
if (!empty($purpose_where_clauses)) {
    $purpose_query .= " WHERE " . implode(" AND ", $purpose_where_clauses);
}

$purpose_query .= " GROUP BY purpose ORDER BY count DESC";
$purpose_result = mysqli_query($conn, $purpose_query);
$purpose_data = array();
$purpose_labels = array();
$purpose_counts = array();

if ($purpose_result) {
    while ($row = mysqli_fetch_assoc($purpose_result)) {
        $purpose = !empty($row['purpose']) ? $row['purpose'] : 'Other';
        $purpose_labels[] = $purpose;
        $purpose_counts[] = $row['count'];
    }
}

// Get data for lab distribution chart
$lab_query = "SELECT l.lab_name, COUNT(*) as count 
              FROM sit_in_sessions s
              LEFT JOIN labs l ON s.lab_id = l.lab_id";

// Build WHERE clause for lab query
$lab_where_clauses = array();

if ($date_filter === 'today') {
    $lab_where_clauses[] = "DATE(s.check_in_time) = '$today'";
}

// Apply status filter to lab chart data
if ($status !== 'all') {
    $lab_where_clauses[] = "(s.status = '$status' OR LOWER(s.status) = LOWER('$status'))";
}

// Add WHERE clause if we have conditions
if (!empty($lab_where_clauses)) {
    $lab_query .= " WHERE " . implode(" AND ", $lab_where_clauses);
}

$lab_query .= " GROUP BY l.lab_name ORDER BY count DESC";
$lab_result = mysqli_query($conn, $lab_query);
$lab_data = array();
$lab_labels = array();
$lab_counts = array();

if ($lab_result) {
    while ($row = mysqli_fetch_assoc($lab_result)) {
        $lab_name = !empty($row['lab_name']) ? $row['lab_name'] : 'Lab ' . $row['lab_id'];
        $lab_labels[] = $lab_name;
        $lab_counts[] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Records | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add Chart.js for the pie charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .table-container { 
            overflow-x: auto; 
        }
        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
        }
        .chart-container {
            position: relative;
            height: 220px;
            margin-top: 20px;
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
                        <!-- Sit-In dropdown menu -->
                        <div class="relative inline-block" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                            <button class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
                                <i class="fas fa-user-check mr-1"></i> Sit-In
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div x-show="open" class="absolute z-10 mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5" style="margin-top: -1px; padding-top: 8px;">
                                <div class="py-1">
                                    <a href="current_sitin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user-check mr-1"></i> Current Sit-In
                                    </a>
                                    <a href="sitin_records.php" class="block px-4 py-2 text-sm bg-gray-100 text-primary-700 font-medium">
                                        <i class="fas fa-list mr-1"></i> Sit-In Records
                                    </a>
                                    <a href="sitin_reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-chart-bar mr-1"></i> Sit-In Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                        <a href="feedback_reports.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <a href="../reservation/reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                        <a href="../leaderboard/leaderboard.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-trophy mr-1"></i> Leaderboard
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
                                <a href="edit_admin_profile.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
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
        <!-- Mobile Sit-In dropdown with toggle -->
        <div class="relative">
            <button id="mobile-sitin-dropdown" class="w-full text-left block px-4 py-2 text-white bg-primary-900 flex justify-between items-center">
                <span><i class="fas fa-user-check mr-2"></i> Sit-In</span>
                <i class="fas fa-chevron-down text-xs"></i>
            </button>
            <div id="mobile-sitin-menu" class="hidden bg-primary-950 py-2">
                <a href="current_sitin.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-user-check mr-2"></i> Current Sit-In
                </a>
                <a href="sitin_records.php" class="block px-6 py-2 text-white bg-primary-800">
                    <i class="fas fa-list mr-2"></i> Sit-In Records
                </a>
                <a href="sitin_reports.php" class="block px-6 py-2 text-white hover:bg-primary-900">
                    <i class="fas fa-chart-bar mr-2"></i> Sit-In Reports
                </a>
            </div>
        </div>
        <a href="feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
        <a href="../leaderboard/leaderboard.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-trophy mr-2"></i> Leaderboard
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-6">
        <!-- Page Title -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                <h2 class="text-xl font-semibold">Sit-In Records</h2>
            </div>
            
            <div class="p-6">
                <!-- Filters and Search -->
                <div class="mb-6 no-print">
                    <form action="" method="GET" class="flex flex-wrap gap-4 bg-white p-4 rounded-lg border border-gray-200">
                        <div class="flex-1">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name, ID, or purpose..."
                                class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        
                        <div>
                            <select name="date_filter" class="px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today's Records</option>
                                <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Records</option>
                            </select>
                        </div>
                        
                        <div>
                            <select name="status" class="px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div>
                            <select name="sort" class="px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Latest First</option>
                                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            </select>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition flex items-center">
                                <i class="fas fa-filter mr-2"></i> Filter
                            </button>
                            
                            <button type="button" onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition flex items-center">
                                <i class="fas fa-print mr-2"></i> Print
                            </button>
                            
                            <a href="sitin_records.php" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Records Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                    <div class="p-4 bg-gray-50 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-700">
                                <?php if ($date_filter === 'today'): ?>
                                    Current Sit-In Records
                                <?php else: ?>
                                    All Sit-In Records
                                <?php endif; ?>
                                <?php if ($status !== 'all'): ?> 
                                    <span class="text-sm font-normal ml-2">(<?php echo ucfirst($status); ?>)</span>
                                <?php endif; ?>
                            </h3>
                            <span class="text-sm text-gray-500">
                                <?php echo $total_records; ?> record<?php echo $total_records !== 1 ? 's' : ''; ?> found
                            </span>
                        </div>
                    </div>
                    
                    <!-- Pie Chart Section - Only show when there are records -->
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <div class="no-print p-4 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Visual Distribution</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Purpose Distribution Chart -->
                            <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                                <h4 class="text-base font-medium text-gray-700 mb-2">Distribution by Purpose</h4>
                                <div class="chart-container">
                                    <canvas id="purposeChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Lab Distribution Chart -->
                            <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                                <h4 class="text-base font-medium text-gray-700 mb-2">Distribution by Laboratory</h4>
                                <div class="chart-container">
                                    <canvas id="labChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="table-container">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php echo format_date($row['check_in_time']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php echo format_time_gmt8($row['check_in_time']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php 
                                                if ($row['check_out_time']) {
                                                    echo format_time_gmt8($row['check_out_time']);
                                                } else {
                                                    echo '<span class="text-yellow-600">Pending</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php echo htmlspecialchars($row['student_id']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php echo htmlspecialchars($row['student_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php echo htmlspecialchars($row['lab_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php echo htmlspecialchars($row['purpose']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo strtolower($row['status']) === 'active' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo ucfirst(strtolower($row['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-10 text-center">
                                            <div class="text-gray-500 flex flex-col items-center">
                                                <i class="fas fa-search text-4xl mb-3 text-gray-400"></i>
                                                <p class="text-lg font-medium">No sit-in records found</p>
                                                <?php if (!empty($filter_description)): ?>
                                                    <p class="text-sm mt-1">No records match the filters: <?php echo $filter_description; ?></p>
                                                <?php endif; ?>  
                                                <a href="sitin_records.php" class="mt-4 px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition no-print">
                                                    <i class="fas fa-sync-alt mr-2"></i> Reset Filters
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <!-- Pagination Controls -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div class="mb-4 sm:mb-0">
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $total_records; ?></span>
                                    results
                                </p>
                            </div>
                            <div class="flex justify-between sm:justify-end">
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm space-x-2" aria-label="Pagination">
                                    <!-- Previous Page Button -->
                                    <a href="<?php echo $page > 1 ? '?page=' . ($page - 1) . (!empty($search) ? '&search=' . urlencode($search) : '') . '&status=' . $status . '&sort=' . $sort . '&date_filter=' . $date_filter : '#'; ?>" 
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
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&date_filter=<?php echo $date_filter; ?>" 
                                           class="<?php echo $i == $page ? 'bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> 
                                           relative inline-flex items-center px-4 py-2 border text-sm font-medium mx-1">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <!-- Next Page Button -->
                                    <a href="<?php echo $page < $total_pages ? '?page=' . ($page + 1) . (!empty($search) ? '&search=' . urlencode($search) : '') . '&status=' . $status . '&sort=' . $sort . '&date_filter=' . $date_filter : '#'; ?>" 
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

        // Enhance form inputs
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-primary-100', 'ring-opacity-50');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-primary-100', 'ring-opacity-50');
            });
        });

        // Mobile dropdown toggle for Sit-In menu
        document.getElementById('mobile-sitin-dropdown').addEventListener('click', function() {
            document.getElementById('mobile-sitin-menu').classList.toggle('hidden');
        });

        // Initialize the pie charts
        document.addEventListener('DOMContentLoaded', function() {
            // Purpose Distribution Chart
            const purposeCtx = document.getElementById('purposeChart').getContext('2d');
            
            // Purpose data from PHP
            const purposeLabels = <?php echo json_encode($purpose_labels); ?>;
            const purposeData = <?php echo json_encode($purpose_counts); ?>;
            
            // Generate colors for the purpose chart
            const purposeColors = [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(75, 85, 99, 0.8)',
                'rgba(14, 165, 233, 0.8)',
                'rgba(236, 72, 153, 0.8)'
            ];
            
            // Create the purpose chart
            new Chart(purposeCtx, {
                type: 'doughnut',
                data: {
                    labels: purposeLabels,
                    datasets: [{
                        data: purposeData,
                        backgroundColor: purposeColors.slice(0, purposeData.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: { 
                                    size: 11,
                                    family: 'Inter'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Lab Distribution Chart
            const labCtx = document.getElementById('labChart').getContext('2d');
            
            // Lab data from PHP
            const labLabels = <?php echo json_encode($lab_labels); ?>;
            const labData = <?php echo json_encode($lab_counts); ?>;
            
            // Generate colors for the lab chart
            const labColors = [
                'rgba(79, 70, 229, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(14, 116, 144, 0.8)',
                'rgba(20, 184, 166, 0.8)',
                'rgba(168, 85, 247, 0.8)'
            ];
            
            // Create the lab chart
            new Chart(labCtx, {
                type: 'doughnut',
                data: {
                    labels: labLabels,
                    datasets: [{
                        data: labData,
                        backgroundColor: labColors.slice(0, labData.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: { 
                                    size: 11,
                                    family: 'Inter'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        });
    </script>
    
    <!-- Add Alpine.js for dropdown functionality -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>