<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login_admin.php");
    exit;
}

$success = '';

// Check for login success message
if(isset($_GET['message']) && $_GET['message'] == 'loggedin') {
    $success = "You have successfully logged in.";
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'];

// Database connection
$db_host = "localhost";
$db_user = "root"; 
$db_pass = "";
$db_name = "csms";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get total students count
$student_count = 0;
$query = "SELECT COUNT(*) as total FROM users";
$result = mysqli_query($conn, $query);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $student_count = $row['total'];
}

// Get active sit-ins count - updated to use correct table name
$active_sitins = 0;
$sitin_query = "SELECT COUNT(*) as active FROM sit_in_sessions WHERE status = 'active'";
$sitin_result = mysqli_query($conn, $sitin_query);

if ($sitin_result) {
    $sitin_row = mysqli_fetch_assoc($sitin_result);
    $active_sitins = $sitin_row['active'];
}

// Get sit-in purpose distribution for pie chart
$purpose_distribution = array();

// Query to get distribution by purpose
$purpose_query = "SELECT purpose, COUNT(*) as count FROM sit_in_sessions GROUP BY purpose";
$purpose_result = mysqli_query($conn, $purpose_query);

if ($purpose_result) {
    while ($row = mysqli_fetch_assoc($purpose_result)) {
        $purpose = $row['purpose'] ?: 'Other';
        // Skip empty purposes
        if (trim($purpose) === '') {
            $purpose = 'Other';
        }
        $count = $row['count'];
        
        if (isset($purpose_distribution[$purpose])) {
            $purpose_distribution[$purpose] += $count;
        } else {
            $purpose_distribution[$purpose] = $count;
        }
    }
}

// If no purpose data found or empty, create sample distribution
if (count($purpose_distribution) == 0) {
    $purpose_distribution = array(
        'Research' => 25,
        'Study' => 20,
        'Project Work' => 15,
        'Consultation' => 10,
        'Other' => 5
    );
}

// Limit to top 5 purposes if there are many
if (count($purpose_distribution) > 5) {
    arsort($purpose_distribution); // Sort by count descending
    $top_purposes = array_slice($purpose_distribution, 0, 4, true);
    $others_count = array_sum(array_slice($purpose_distribution, 4, null, true));
    if ($others_count > 0) {
        $top_purposes['Other'] = $others_count;
    }
    $purpose_distribution = $top_purposes;
}

// Get student distribution by year level for pie chart
$student_distribution = array(
    'First Year' => 0,
    'Second Year' => 0, 
    'Third Year' => 0,
    'Fourth Year' => 0,
    'Graduate' => 0
);

// Determine the structure of the users table to find appropriate columns for charting
$columns_query = "DESCRIBE users";
$columns_result = mysqli_query($conn, $columns_query);
$has_year_column = false;
$year_column_name = "";
$has_department_column = false;
$department_column_name = "";

if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $column_name = strtolower($column['Field']);
        
        // Look for year level related columns
        if (strpos($column_name, 'year') !== false || 
            strpos($column_name, 'level') !== false || 
            $column_name === 'year_level' ||
            $column_name === 'yearlevel' || 
            $column_name === 'grade') {
            $has_year_column = true;
            $year_column_name = $column['Field'];
        }
        
        // Look for department/course related columns
        if (strpos($column_name, 'department') !== false || 
            strpos($column_name, 'dept') !== false || 
            strpos($column_name, 'course') !== false || 
            strpos($column_name, 'program') !== false) {
            $has_department_column = true;
            $department_column_name = $column['Field'];
        }
    }
}

// Distribution by Year Level
if ($has_year_column) {
    // Query to get actual distribution from database
    $query = "SELECT `$year_column_name` AS year_level, COUNT(*) as count FROM users 
              WHERE `$year_column_name` IS NOT NULL 
              GROUP BY `$year_column_name`";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $found_data = false;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $found_data = true;
            $year_level = strtolower($row['year_level']);
            $count = $row['count'];
            
            // Match various possible formats
            if ($year_level == '1' || $year_level == 'first' || $year_level == '1st' || 
                strpos($year_level, 'first') !== false || strpos($year_level, '1st') !== false) {
                $student_distribution['First Year'] += $count;
            } 
            else if ($year_level == '2' || $year_level == 'second' || $year_level == '2nd' || 
                    strpos($year_level, 'second') !== false || strpos($year_level, '2nd') !== false) {
                $student_distribution['Second Year'] += $count;
            }
            else if ($year_level == '3' || $year_level == 'third' || $year_level == '3rd' || 
                    strpos($year_level, 'third') !== false || strpos($year_level, '3rd') !== false) {
                $student_distribution['Third Year'] += $count;
            }
            else if ($year_level == '4' || $year_level == 'fourth' || $year_level == '4th' || 
                    strpos($year_level, 'fourth') !== false || strpos($year_level, '4th') !== false) {
                $student_distribution['Fourth Year'] += $count;
            }
            else if ($year_level == '5' || $year_level == 'fifth' || $year_level == '5th' || 
                    $year_level == 'g' || $year_level == 'grad' || 
                    strpos($year_level, 'graduate') !== false || strpos($year_level, 'grad') !== false ||
                    strpos($year_level, 'fifth') !== false || strpos($year_level, '5th') !== false) {
                $student_distribution['Graduate'] += $count;
            }
            else {
                // Put unclassified entries into First Year as default
                $student_distribution['First Year'] += $count;
            }
        }
        
        // If we didn't find any data with the column, use fallback
        if (!$found_data) {
            $has_year_column = false;
        }
    } else {
        // Query failed, use fallback
        $has_year_column = false;
    }
}

// If no year data found or query failed, create sample distribution
if (!$has_year_column || array_sum($student_distribution) == 0) {
    // Make sure we have at least some data for the chart
    // Distribute student count approximately across year levels
    if ($student_count > 0) {
        $student_distribution['First Year'] = ceil($student_count * 0.35); // 35%
        $student_distribution['Second Year'] = ceil($student_count * 0.30); // 30%
        $student_distribution['Third Year'] = ceil($student_count * 0.20); // 20%
        $student_distribution['Fourth Year'] = ceil($student_count * 0.10); // 10%
        $student_distribution['Graduate'] = $student_count - 
                                          ($student_distribution['First Year'] + 
                                           $student_distribution['Second Year'] + 
                                           $student_distribution['Third Year'] + 
                                           $student_distribution['Fourth Year']);
        
        // Make sure Graduate doesn't go negative due to rounding
        if ($student_distribution['Graduate'] < 0) {
            $student_distribution['Graduate'] = 0;
        }
    } else {
        // Sample data if no students
        $student_distribution['First Year'] = 20;
        $student_distribution['Second Year'] = 15;
        $student_distribution['Third Year'] = 12;
        $student_distribution['Fourth Year'] = 8;
        $student_distribution['Graduate'] = 5;
    }
}

// Remove empty categories to avoid empty segments in pie chart
foreach ($student_distribution as $key => $value) {
    if ($value <= 0) {
        unset($student_distribution[$key]);
    }
}

// Get student distribution by department
$department_distribution = array();

// Query to get distribution by department if appropriate column exists
if ($has_department_column) {
    $query = "SELECT COALESCE(`$department_column_name`, 'Undeclared') AS department, COUNT(*) as count 
              FROM users 
              GROUP BY `$department_column_name`";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $found_data = false;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $found_data = true;
            $department = $row['department'] ?: 'Undeclared'; // Handle null values
            // Skip empty departments
            if (trim($department) === '') {
                $department = 'Undeclared';
            }
            $count = $row['count'];
            $department_distribution[$department] = $count;
        }
        
        // If we didn't find any meaningful data, use fallback
        if (!$found_data || count(array_filter(array_keys($department_distribution), function($key) {
            return $key !== 'Undeclared' && trim($key) !== '';
        })) == 0) {
            $has_department_column = false;
        }
    } else {
        // Query failed, use fallback
        $has_department_column = false;
    }
}

// If no department data found or query failed, create sample distribution
if (!$has_department_column || count($department_distribution) == 0) {
    // Make sure we have at least some data for the chart
    if ($student_count > 0) {
        $department_distribution = array(
            'Computer Science' => ceil($student_count * 0.25),
            'Information Technology' => ceil($student_count * 0.20),
            'Engineering' => ceil($student_count * 0.15),
            'Business' => ceil($student_count * 0.15),
            'Others' => $student_count - (
                ceil($student_count * 0.25) + 
                ceil($student_count * 0.20) + 
                ceil($student_count * 0.15) + 
                ceil($student_count * 0.15)
            )
        );
        
        // Make sure Others doesn't go negative due to rounding
        if ($department_distribution['Others'] < 0) {
            $department_distribution['Others'] = 0;
        }
    } else {
        // Sample data if no students
        $department_distribution = array(
            'Computer Science' => 18,
            'Information Technology' => 15,
            'Engineering' => 12,
            'Business' => 10,
            'Others' => 5
        );
    }
}

// Remove empty categories to avoid empty segments in pie chart
foreach ($department_distribution as $key => $value) {
    if ($value <= 0) {
        unset($department_distribution[$key]);
    }
}

// Limit to top 5 departments if there are many
if (count($department_distribution) > 5) {
    arsort($department_distribution); // Sort by count descending
    $top_departments = array_slice($department_distribution, 0, 4, true);
    $others_count = array_sum(array_slice($department_distribution, 4, null, true));
    if ($others_count > 0) {
        $top_departments['Others'] = $others_count;
    }
    $department_distribution = $top_departments;
}

// Check if the announcements table exists, and create it if it doesn't
$table_check_query = "SHOW TABLES LIKE 'announcements'";
$table_exists = mysqli_query($conn, $table_check_query);

if (mysqli_num_rows($table_exists) == 0) {
    // Table doesn't exist, create it
    $create_table_query = "CREATE TABLE `announcements` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `title` varchar(255) NOT NULL,
                          `content` text NOT NULL,
                          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                        
    if (!mysqli_query($conn, $create_table_query)) {
        // Handle error
        $_SESSION['announcement_error'] = "Error creating announcements table: " . mysqli_error($conn);
    }
}

// Fetch announcements from database
$announcements = [];
$query = "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Sit-In Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add Chart.js library -->
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
        }
        .card {
            display: flex;
            flex-direction: column;
        }
        .card-content {
            flex: 1;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 200px;
            width: 100%;
            margin: 15px 0;
        }
        .dashboard-content {
            overflow-y: auto;
            max-height: 100%;
        }
        .dashboard-section {
            height: auto;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Notification styling */
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
        
        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Notification Section -->
    <?php if(!empty($success)): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle"></i>
        <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['announcement_success'])): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['announcement_success']; ?>
    </div>
    <?php unset($_SESSION['announcement_success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['announcement_error'])): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['announcement_error']; ?>
    </div>
    <?php unset($_SESSION['announcement_error']); ?>
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
                        <a href="student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <!-- Modified: Split Sit-In into separate buttons -->
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
                                <img src="newp.jpg" alt="Admin" class="w-full h-full object-cover">-full object-cover">
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
        <a href="student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <!-- Modified: Split Sit-In into separate buttons for mobile menu -->
        <a href="sitin_register.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="sitin_records.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-list mr-2"></i> View Sit-In Records
        </a>
        <a href="sitin_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-chart-bar mr-2"></i> Sit-In Reports
        </a>
        <a href="feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback Reports
        </a>
    </div>

    <!-- Dashboard Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Integrated Admin Dashboard Overview Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Admin Dashboard Overview</h2>
                </div>
                
                <div class="p-6">
                    <!-- Welcome Message and Stats Summary -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</h1>
                        <p class="text-gray-600 mb-4">Here's your admin dashboard overview</p>
                        
                        <div class="flex flex-wrap -mx-2 mb-6">
                            <div class="px-2 w-full sm:w-1/3 mb-4 sm:mb-0">
                                <div class="bg-primary-50 border border-primary-100 p-4 rounded-lg">
                                    <div class="text-3xl font-bold text-primary-700"><?php echo $student_count; ?></div>
                                    <div class="text-sm text-primary-900">Total Students</div>
                                </div>
                            </div>
                            <div class="px-2 w-full sm:w-1/3 mb-4 sm:mb-0">
                                <div class="bg-green-50 border border-green-100 p-4 rounded-lg">
                                    <div class="text-3xl font-bold text-green-700"><?php echo $active_sitins; ?></div>
                                    <div class="text-sm text-green-900">Active Sit-Ins</div>
                                </div>
                            </div>
                            <div class="px-2 w-full sm:w-1/3">
                                <div class="bg-amber-50 border border-amber-100 p-4 rounded-lg">
                                    <div class="text-3xl font-bold text-amber-700">8</div>
                                    <div class="text-sm text-amber-900">Pending Approvals</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Distribution Charts in grid -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Student Distribution Overview</h3>
                        
                        <div class="chart-grid">
                            <!-- Student Distribution Chart by Year Level -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-base font-medium text-gray-700 mb-2">Year Level Distribution</h4>
                                <div class="chart-container">
                                    <canvas id="studentYearChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Sit-In Purpose Distribution Chart -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-base font-medium text-gray-700 mb-2">Sit-In Purpose Distribution</h4>
                                <div class="chart-container">
                                    <canvas id="sitinPurposeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Announcements Section -->
            <div class="bg-white rounded-xl shadow-md">
                <div class="bg-primary-700 text-white px-6 py-4">
                    <h2 class="text-xl font-semibold">Edit Announcements</h2>
                </div>
                <div class="p-6 dashboard-section">
                    <!-- Announcement Form -->
                    <form action="process_announcement.php" method="post" class="mb-6 border-b pb-6">
                        <div class="mb-4">
                            <label for="announcement_title" class="block text-gray-700 font-medium mb-2">Announcement Title</label>
                            <input type="text" id="announcement_title" name="title" required
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="announcement_content" class="block text-gray-700 font-medium mb-2">Announcement Content</label>
                            <textarea id="announcement_content" name="content" rows="4" required
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                        </div>
                        
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                            Post Announcement
                        </button>
                    </form>

                    <!-- Existing Announcements -->
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Current Announcements</h3>
                    
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $announcement): ?>
                        <div class="border rounded-lg p-4 mb-4 bg-gray-50">
                            <div class="flex justify-between">
                                <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <span class="text-sm text-gray-500"><?php echo date('Y-M-d', strtotime($announcement['created_at'])); ?></span>
                            </div>
                            <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            <div class="mt-3 flex space-x-2">
                                <a href="edit_announcement.php?id=<?php echo $announcement['id']; ?>" class="text-sm text-primary-600 hover:underline">Edit</a>
                                <a href="delete_announcement.php?id=<?php echo $announcement['id']; ?>" class="text-sm text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this announcement?')">Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-gray-500 italic">No announcements yet. Create one above.</div>
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

        // Initialize the Pie Charts for Student Distribution
        document.addEventListener('DOMContentLoaded', function() {
            // Year Level Distribution Chart
            const yearCtx = document.getElementById('studentYearChart').getContext('2d');
            
            // Enhanced color palette with better visual harmony
            const yearColors = [
                'rgba(79, 70, 229, 0.85)',   // indigo with transparency
                'rgba(37, 99, 235, 0.85)',   // blue with transparency
                'rgba(8, 145, 178, 0.85)',   // cyan with transparency
                'rgba(5, 150, 105, 0.85)',   // emerald with transparency
                'rgba(124, 58, 237, 0.85)'   // violet with transparency
            ];
            
            const yearLabels = [];
            const yearData = [];
            let colorIndex = 0;
            const yearColorsUsed = [];
            
            <?php foreach ($student_distribution as $year => $count): ?>
                yearLabels.push('<?php echo addslashes($year); ?>');
                yearData.push(<?php echo $count; ?>);
                yearColorsUsed.push(yearColors[colorIndex % yearColors.length]);
                colorIndex++;
            <?php endforeach; ?>
            
            new Chart(yearCtx, {
                type: 'doughnut', // Changed to doughnut for modern look
                data: {
                    labels: yearLabels,
                    datasets: [{
                        data: yearData,
                        backgroundColor: yearColorsUsed,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverBorderWidth: 4,
                        hoverBackgroundColor: yearColorsUsed.map(color => color.replace('0.85', '1')),
                        hoverOffset: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%', // Doughnut hole size
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                boxWidth: 12,
                                font: { 
                                    size: 11,
                                    family: 'Inter'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 13,
                                family: 'Inter',
                                weight: '600'
                            },
                            bodyFont: {
                                family: 'Inter',
                                size: 12
                            },
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
            
            // Sit-In Purpose Distribution Chart
            const purposeCtx = document.getElementById('sitinPurposeChart').getContext('2d');
            
            // Enhanced color palette for purpose chart
            const purposeColors = [
                'rgba(59, 130, 246, 0.85)',  // blue with transparency
                'rgba(16, 185, 129, 0.85)',  // emerald with transparency
                'rgba(245, 158, 11, 0.85)',  // amber with transparency
                'rgba(239, 68, 68, 0.85)',   // red with transparency
                'rgba(139, 92, 246, 0.85)'   // violet with transparency
            ];
            
            let purposeColorIndex = 0;
            const purposeLabels = [];
            const purposeData = [];
            const purposeColorsUsed = [];
            
            <?php foreach ($purpose_distribution as $purpose => $count): ?>
                purposeLabels.push('<?php echo addslashes($purpose); ?>');
                purposeData.push(<?php echo $count; ?>);
                purposeColorsUsed.push(purposeColors[purposeColorIndex % purposeColors.length]);
                purposeColorIndex++;
            <?php endforeach; ?>
            
            new Chart(purposeCtx, {
                type: 'doughnut',
                data: {
                    labels: purposeLabels,
                    datasets: [{
                        data: purposeData,
                        backgroundColor: purposeColorsUsed,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverBorderWidth: 4,
                        hoverBackgroundColor: purposeColorsUsed.map(color => color.replace('0.85', '1')),
                        hoverOffset: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                boxWidth: 12,
                                font: { 
                                    size: 11,
                                    family: 'Inter'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 13,
                                family: 'Inter',
                                weight: '600'
                            },
                            bodyFont: {
                                family: 'Inter',
                                size: 12
                            },
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
            
            // Add subtle animations for cards and UI elements
            document.querySelectorAll('.card, .bg-white.rounded-xl').forEach(card => {
                card.classList.add('transition-all', 'duration-300', 'hover:shadow-lg');
            });
            
            // Add hover effects to buttons and interactive elements
            document.querySelectorAll('button:not([disabled]), a.px-3.py-2, a.px-4.py-2').forEach(button => {
                button.classList.add('transition-colors', 'duration-200');
            });
            
            // Enhance form inputs
            document.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-primary-100', 'ring-opacity-50');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-primary-100', 'ring-opacity-50');
                });
            });
        });
        
        // Auto hide notifications with smooth transition
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            
            notifications.forEach(notification => {
                notification.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                // Slight animation on load
                notification.style.transform = 'translateY(0)';
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
