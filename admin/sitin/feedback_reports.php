<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if user is logged in (admin only for reports)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Set timezone to match system-wide setting
date_default_timezone_set('Asia/Singapore');

// Utility function to format dates with the correct timezone
function formatDateTime($dateTime, $format = 'M d, Y g:i A') {
    return date($format, strtotime($dateTime));
}

// Initialize variables
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Check if the table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'sit_in_feedback'");
if ($table_check->num_rows == 0) {
    // Create the feedback table
    $create_table_sql = "CREATE TABLE sit_in_feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL,
        feedback TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($create_table_sql)) {
        die("Could not create feedback table: " . $conn->error);
    }
}

// First get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM sit_in_feedback f
                JOIN sit_in_sessions s ON f.session_id = s.session_id
                JOIN users u ON f.user_id = u.user_id
                JOIN labs l ON s.lab_id = l.lab_id
                WHERE 1=1";
$count_params = [];
$count_types = "";

if ($filter_rating > 0) {
    $count_query .= " AND f.rating = ?";
    $count_params[] = $filter_rating;
    $count_types .= "i";
}

if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $count_query .= " AND DATE(f.submitted_at) BETWEEN ? AND ?";
    $count_params[] = $filter_date_from;
    $count_params[] = $filter_date_to;
    $count_types .= "ss";
}

if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $count_query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.idNo LIKE ? OR f.feedback LIKE ?)";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "ssss";
}

$total_records = 0;
$stmt = $conn->prepare($count_query);
if ($stmt) {
    if (!empty($count_params)) {
        $stmt->bind_param($count_types, ...$count_params);
    }
    $stmt->execute();
    $count_result = $stmt->get_result();
    if ($count_result && $row = $count_result->fetch_assoc()) {
        $total_records = $row['total'];
    }
    $stmt->close();
}

$total_pages = ceil($total_records / $items_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $items_per_page;
}

// Get feedback data with filters and pagination
$query = "SELECT f.*, s.purpose, s.check_in_time, s.check_out_time, 
          u.firstName, u.lastName, u.idNo, l.lab_name
          FROM sit_in_feedback f
          JOIN sit_in_sessions s ON f.session_id = s.session_id
          JOIN users u ON f.user_id = u.user_id
          JOIN labs l ON s.lab_id = l.lab_id
          WHERE 1=1";
$params = [];
$types = "";

if ($filter_rating > 0) {
    $query .= " AND f.rating = ?";
    $params[] = $filter_rating;
    $types .= "i";
}

if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $query .= " AND DATE(f.submitted_at) BETWEEN ? AND ?";
    $params[] = $filter_date_from;
    $params[] = $filter_date_to;
    $types .= "ss";
}

if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.idNo LIKE ? OR f.feedback LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$query .= " ORDER BY f.submitted_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the query
$feedback_data = [];
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $feedback_data[] = $row;
        }
    }
    $stmt->close();
}

// Calculate rating statistics
$rating_stats = [
    'total' => count($feedback_data),
    'average' => 0,
    'ratings' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
];

foreach ($feedback_data as $feedback) {
    $rating = (int)$feedback['rating'];
    if ($rating >= 1 && $rating <= 5) {
        $rating_stats['ratings'][$rating]++;
    }
}

if ($rating_stats['total'] > 0) {
    $sum = 0;
    foreach ($rating_stats['ratings'] as $rating => $count) {
        $sum += $rating * $count;
    }
    $rating_stats['average'] = round($sum / $rating_stats['total'], 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback Reports | Admin Dashboard</title>
    <!-- Add Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        .star-rating .star {
            color: #cbd5e1;
        }
        .star-rating .star.filled {
            color: #f59e0b;
        }
        
        /* Dropdown menu styles */
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
        
        /* Enhanced nav buttons */
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
                        <a href="../students/search_student.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="../students/student.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        
                        <!-- Sit-In dropdown menu -->
                        <div class="relative inline-block dropdown-container" id="sitInDropdown">
                            <button class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center" id="sitInMenuButton">
                                <i class="fas fa-user-check mr-1"></i> Sit-In
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="dropdown-menu" id="sitInDropdownMenu">
                                <a href="current_sitin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
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
                        
                        <a href="feedback_reports.php" class="nav-button px-3 py-2 bg-primary-800 rounded transition flex items-center">
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
        
        <!-- Mobile Sit-In dropdown with toggle -->
        <div class="relative">
            <button id="mobile-sitin-dropdown" class="w-full text-left block px-4 py-2 text-white hover:bg-primary-900 flex justify-between items-center">
                <span><i class="fas fa-user-check mr-2"></i> Sit-In</span>
                <i class="fas fa-chevron-down text-xs"></i>
            </button>
            <div id="mobile-sitin-menu" class="hidden bg-primary-950 py-2">
                <a href="current_sitin.php" class="block px-6 py-2 text-white hover:bg-primary-900">
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
        
        <a href="feedback_reports.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-6 overflow-auto">
        <!-- Page Title -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                <h2 class="text-xl font-semibold">Student Feedback Reports</h2>
            </div>
            
            <div class="p-6">
                <!-- Filter and Search Section -->
                <div class="mb-6">
                    <form action="" method="GET" class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="flex flex-wrap items-end gap-3">
                            <!-- Rating Filter -->
                            <div class="w-full sm:w-auto">
                                <label class="text-xs text-gray-600 block mb-1">Rating</label>
                                <select name="rating" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                                    <option value="0" <?php echo $filter_rating === 0 ? 'selected' : ''; ?>>All Ratings</option>
                                    <option value="5" <?php echo $filter_rating === 5 ? 'selected' : ''; ?>>5 Stars</option>
                                    <option value="4" <?php echo $filter_rating === 4 ? 'selected' : ''; ?>>4 Stars</option>
                                    <option value="3" <?php echo $filter_rating === 3 ? 'selected' : ''; ?>>3 Stars</option>
                                    <option value="2" <?php echo $filter_rating === 2 ? 'selected' : ''; ?>>2 Stars</option>
                                    <option value="1" <?php echo $filter_rating === 1 ? 'selected' : ''; ?>>1 Star</option>
                                </select>
                            </div>
                            
                            <!-- Date Range -->
                            <div class="flex items-end gap-2">
                                <div>
                                    <label class="text-xs text-gray-600 block mb-1">From</label>
                                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" 
                                        class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600 block mb-1">To</label>
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" 
                                        class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                                </div>
                            </div>
                            
                            <!-- Search Field -->
                            <div class="flex-1 min-w-[180px]">
                                <label class="text-xs text-gray-600 block mb-1">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>"
                                    placeholder="Name, ID, or feedback..."
                                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition flex items-center text-sm">
                                    <i class="fas fa-filter mr-1"></i> Apply
                                </button>
                                <?php if (!empty($filter_rating) || !empty($search_term) || $filter_date_from != date('Y-m-d', strtotime('-30 days')) || $filter_date_to != date('Y-m-d')): ?>
                                <a href="feedback_reports.php" class="px-3 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition flex items-center text-sm">
                                    <i class="fas fa-times mr-1"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Feedback Statistics -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-5">
                        <h3 class="text-lg font-semibold mb-4">Feedback Statistics</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Rating Distribution -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-medium text-gray-700">Rating Distribution</h4>
                                    <div class="text-xl font-bold text-primary-700">
                                        <?php echo $rating_stats['average']; ?> <span class="text-sm text-gray-500">/ 5</span>
                                        <span class="ml-2 text-yellow-500">
                                            <i class="fas fa-star text-sm"></i>
                                        </span>
                                    </div>
                                </div>
                                <?php for ($i = 5; $i >= 1; $i--): 
                                    $count = $rating_stats['ratings'][$i];
                                    $percent = $rating_stats['total'] > 0 ? ($count / $rating_stats['total']) * 100 : 0; 
                                ?>
                                <div class="mb-2">
                                    <div class="flex items-center">
                                        <div class="w-16 text-sm">
                                            <?php echo $i; ?> <span class="text-yellow-500"><i class="fas fa-star text-xs"></i></span>
                                        </div>
                                        <div class="flex-1 mx-2">
                                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="w-16 text-right text-sm text-gray-600">
                                            <?php echo $count; ?> (<?php echo round($percent); ?>%)
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Summary Stats -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-700 mb-3">Feedback Summary</h4>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-white p-3 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Total Feedback</div>
                                        <div class="text-2xl font-bold text-primary-700"><?php echo $rating_stats['total']; ?></div>
                                    </div>
                                    <div class="bg-white p-3 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Date Range</div>
                                        <div class="text-sm font-medium text-primary-700">
                                            <?php echo date('M d', strtotime($filter_date_from)); ?> - 
                                            <?php echo date('M d, Y', strtotime($filter_date_to)); ?>
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    // Calculate top rating percentage
                                    $top_ratings = $rating_stats['ratings'][5] + $rating_stats['ratings'][4];
                                    $top_percent = $rating_stats['total'] > 0 ? round(($top_ratings / $rating_stats['total']) * 100) : 0;
                                    ?>
                                    <div class="bg-white p-3 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Top Ratings (4-5★)</div>
                                        <div class="text-2xl font-bold <?php echo $top_percent >= 70 ? 'text-green-600' : ($top_percent >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                            <?php echo $top_percent; ?>%
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    // Calculate negative rating percentage
                                    $negative_ratings = $rating_stats['ratings'][1] + $rating_stats['ratings'][2];
                                    $negative_percent = $rating_stats['total'] > 0 ? round(($negative_ratings / $rating_stats['total']) * 100) : 0;
                                    ?>
                                    <div class="bg-white p-3 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Negative (1-2★)</div>
                                        <div class="text-2xl font-bold <?php echo $negative_percent <= 10 ? 'text-green-600' : ($negative_percent <= 20 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                            <?php echo $negative_percent; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Feedback List</h3>
                            <?php if ($total_records > 0): ?>
                            <div class="text-sm text-gray-500">
                                Showing <?php echo min(($offset + 1), $total_records); ?> - 
                                <?php echo min(($offset + $items_per_page), $total_records); ?> 
                                of <?php echo $total_records; ?> feedback
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (count($feedback_data) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($feedback_data as $feedback): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition">
                                    <div class="flex flex-wrap justify-between mb-2">
                                        <div>
                                            <div class="flex items-center">
                                                <div class="star-rating mr-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">
                                                            <i class="fas fa-star"></i>
                                                        </span>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="text-sm font-medium">
                                                    <?php echo htmlspecialchars($feedback['firstName'] . ' ' . $feedback['lastName']); ?>
                                                    <span class="text-gray-500">(<?php echo htmlspecialchars($feedback['idNo']); ?>)</span>
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Submitted on <?php echo formatDateTime($feedback['submitted_at']); ?>
                                            </div>
                                        </div>
                                        <div class="text-sm">
                                            <div class="flex items-center text-gray-600 mb-1">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <span><?php echo htmlspecialchars($feedback['lab_name']); ?></span>
                                            </div>
                                            <div class="flex items-center text-gray-600 text-xs">
                                                <i class="far fa-clock mr-1"></i>
                                                <span><?php echo formatDateTime($feedback['check_in_time'], 'M d, Y'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs text-gray-500 mb-1">Purpose</div>
                                        <div class="text-sm text-gray-700"><?php echo htmlspecialchars($feedback['purpose']); ?></div>
                                    </div>
                                    <div class="mt-3 p-3 bg-primary-50 rounded-lg text-gray-700">
                                        <div class="text-xs text-primary-800 mb-1">Feedback</div>
                                        <p class="text-sm"><?php echo nl2br(htmlspecialchars($feedback['feedback'])); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <?php if ($total_pages > 1): ?>
                            <div class="mt-6 flex justify-center">
                                <nav class="flex items-center space-x-1">
                                    <?php 
                                    // Preserve all GET parameters except 'page'
                                    $query_params = $_GET;
                                    unset($query_params['page']);
                                    $query_string = http_build_query($query_params);
                                    $query_string = !empty($query_string) ? '&' . $query_string : '';
                                    
                                    // Previous page link
                                    if ($page > 1): 
                                    ?>
                                    <a href="?page=<?php echo ($page - 1) . $query_string; ?>" 
                                       class="px-3 py-2 rounded border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 transition">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 rounded border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Determine which page numbers to show
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $start_page + 4);
                                    if ($end_page - $start_page < 4) {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                    
                                    // Page number links
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="?page=<?php echo $i . $query_string; ?>" 
                                       class="px-3 py-2 rounded border <?php echo $i === $page ? 
                                                'border-primary-500 bg-primary-50 text-primary-600' : 
                                                'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'; ?> transition">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <!-- Next page link -->
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1) . $query_string; ?>" 
                                       class="px-3 py-2 rounded border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 transition">
                                        <i class="fas fa-chevron-right text-xs"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 rounded border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                                        <i class="fas fa-chevron-right text-xs"></i>
                                    </span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-10 bg-gray-50 rounded-lg">
                                <div class="text-gray-400 text-4xl mb-3">
                                    <i class="far fa-comment-dots"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No feedback found</h3>
                                <p class="text-gray-500 text-sm">
                                    <?php if (!empty($search_term) || $filter_rating > 0): ?>
                                        No feedback matches your search criteria. Try changing your filters.
                                    <?php else: ?>
                                        No student feedback has been submitted yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
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
    </script>
</body>
</html>
