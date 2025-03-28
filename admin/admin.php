<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: auth/login_admin.php");
    exit;
}

$success = isset($_GET['message']) && $_GET['message'] == 'loggedin' ? "You have successfully logged in." : '';
$admin_username = $_SESSION['admin_username'];

// Database connection
$conn = mysqli_connect("localhost", "root", "", "csms");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// Get counts from database in a single function
function getCountFromDB($conn, $table, $condition = '') {
    $where = $condition ? "WHERE $condition" : '';
    $query = "SELECT COUNT(*) as total FROM $table $where";
    $result = mysqli_query($conn, $query);
    return ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;
}

// Get all necessary counts
$student_count = getCountFromDB($conn, 'users');
$active_sitins = getCountFromDB($conn, 'sit_in_sessions', "status = 'active'");
$pending_approvals = getCountFromDB($conn, 'sit_in_sessions', "status = 'pending'");

// Get sit-in purpose distribution
$purpose_distribution = [];
$purpose_query = "SELECT COALESCE(purpose, 'Other') AS purpose, COUNT(*) as count FROM sit_in_sessions 
                GROUP BY purpose";
$purpose_result = mysqli_query($conn, $purpose_query);

if ($purpose_result) {
    while ($row = mysqli_fetch_assoc($purpose_result)) {
        $purpose = trim($row['purpose']) === '' ? 'Other' : $row['purpose'];
        $purpose_distribution[$purpose] = ($purpose_distribution[$purpose] ?? 0) + $row['count'];
    }
}

// If no purpose data found or empty, create sample distribution
if (empty($purpose_distribution)) {
    $purpose_distribution = [
        'Research' => 25, 'Study' => 20, 'Project Work' => 15, 
        'Consultation' => 10, 'Other' => 5
    ];
}

// Limit to top 5 purposes if there are many
if (count($purpose_distribution) > 5) {
    arsort($purpose_distribution);
    $top_purposes = array_slice($purpose_distribution, 0, 4, true);
    $others_count = array_sum(array_slice($purpose_distribution, 4, null, true));
    if ($others_count > 0) $top_purposes['Other'] = $others_count;
    $purpose_distribution = $top_purposes;
}

// Determine table structure and get distribution data
$columns_query = "DESCRIBE users";
$columns_result = mysqli_query($conn, $columns_query);
$has_year_column = false;
$year_column_name = "";
$has_department_column = false;
$department_column_name = "";

if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $column_name = strtolower($column['Field']);
        
        // Check for year level column
        if (strpos($column_name, 'year') !== false || 
            strpos($column_name, 'level') !== false || 
            in_array($column_name, ['year_level', 'yearlevel', 'grade'])) {
            $has_year_column = true;
            $year_column_name = $column['Field'];
        }
        
        // Check for department column
        if (strpos($column_name, 'department') !== false || 
            strpos($column_name, 'dept') !== false || 
            strpos($column_name, 'course') !== false || 
            strpos($column_name, 'program') !== false) {
            $has_department_column = true;
            $department_column_name = $column['Field'];
        }
    }
}

// Get student distribution by year level
$student_distribution = [
    'First Year' => 0, 'Second Year' => 0, 'Third Year' => 0,
    'Fourth Year' => 0, 'Graduate' => 0
];

// Distribution by Year Level
if ($has_year_column) {
    $query = "SELECT `$year_column_name` AS year_level, COUNT(*) as count FROM users 
              WHERE `$year_column_name` IS NOT NULL GROUP BY `$year_column_name`";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $found_data = false;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $found_data = true;
            $year_level = strtolower($row['year_level']);
            $count = $row['count'];
            
            // Classify based on year level text
            if (preg_match('/(^1$|first|1st)/i', $year_level)) {
                $student_distribution['First Year'] += $count;
            } elseif (preg_match('/(^2$|second|2nd)/i', $year_level)) {
                $student_distribution['Second Year'] += $count;
            } elseif (preg_match('/(^3$|third|3rd)/i', $year_level)) {
                $student_distribution['Third Year'] += $count;
            } elseif (preg_match('/(^4$|fourth|4th)/i', $year_level)) {
                $student_distribution['Fourth Year'] += $count;
            } elseif (preg_match('/(^5$|fifth|5th|graduate|grad)/i', $year_level)) {
                $student_distribution['Graduate'] += $count;
            } else {
                $student_distribution['First Year'] += $count; // Default
            }
        }
        
        if (!$found_data) $has_year_column = false;
    } else {
        $has_year_column = false;
    }
}

// Use sample data if needed
if (!$has_year_column || array_sum($student_distribution) == 0) {
    if ($student_count > 0) {
        $student_distribution = [
            'First Year' => ceil($student_count * 0.35),
            'Second Year' => ceil($student_count * 0.30),
            'Third Year' => ceil($student_count * 0.20),
            'Fourth Year' => ceil($student_count * 0.10),
            'Graduate' => $student_count - ceil($student_count * 0.95)
        ];
        if ($student_distribution['Graduate'] < 0) $student_distribution['Graduate'] = 0;
    } else {
        $student_distribution = [
            'First Year' => 20, 'Second Year' => 15, 'Third Year' => 12,
            'Fourth Year' => 8, 'Graduate' => 5
        ];
    }
}

// Remove empty categories
$student_distribution = array_filter($student_distribution, function($value) { return $value > 0; });

// Get department distribution - similar approach to year level
$department_distribution = [];

if ($has_department_column) {
    $query = "SELECT COALESCE(`$department_column_name`, 'Undeclared') AS department, COUNT(*) as count 
              FROM users GROUP BY `$department_column_name`";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $found_data = false;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $found_data = true;
            $department = trim($row['department']) ? $row['department'] : 'Undeclared';
            $department_distribution[$department] = $row['count'];
        }
        
        if (!$found_data) $has_department_column = false;
    } else {
        $has_department_column = false;
    }
}

// Use sample data if needed
if (!$has_department_column || empty($department_distribution)) {
    if ($student_count > 0) {
        $department_distribution = [
            'Computer Science' => ceil($student_count * 0.25),
            'Information Technology' => ceil($student_count * 0.20),
            'Engineering' => ceil($student_count * 0.15),
            'Business' => ceil($student_count * 0.15),
            'Others' => $student_count - ceil($student_count * 0.75)
        ];
        if ($department_distribution['Others'] < 0) $department_distribution['Others'] = 0;
    } else {
        $department_distribution = [
            'Computer Science' => 18, 'Information Technology' => 15,
            'Engineering' => 12, 'Business' => 10, 'Others' => 5
        ];
    }
}

// Remove empty categories and limit to top 5
$department_distribution = array_filter($department_distribution, function($value) { return $value > 0; });

if (count($department_distribution) > 5) {
    arsort($department_distribution);
    $top_departments = array_slice($department_distribution, 0, 4, true);
    $others_count = array_sum(array_slice($department_distribution, 4, null, true));
    if ($others_count > 0) $top_departments['Others'] = $others_count;
    $department_distribution = $top_departments;
}

// Check and create announcements table if needed
$table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if (mysqli_num_rows($table_exists) == 0) {
    require_once 'setup/fix_announcements_table.php';
}

// Fetch announcements
$announcements = [];
$result = mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        body { background-color: #f8fafc; }
        .card { display: flex; flex-direction: column; }
        .card-content { flex: 1; overflow-y: auto; }
        .chart-container { position: relative; height: 200px; width: 100%; margin: 15px 0; }
        .dashboard-content { overflow-y: auto; max-height: 100%; }
        .dashboard-section { height: auto; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) { .chart-grid { grid-template-columns: 1fr; } }
        
        /* Notification styling */
        .notification {
            position: fixed; top: 20px; right: 20px; padding: 15px 20px;
            border-radius: 6px; color: white; font-weight: 500;
            display: flex; align-items: center; z-index: 1000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .notification.success { background-color: #10b981; }
        .notification.error { background-color: #ef4444; }
        .notification i { margin-right: 10px; font-size: 18px; }
        
        /* Professional enhancements */
        .dashboard-card {
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .edit-icon {
            color: #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
        }
        .edit-icon:hover {
            background-color: rgba(59, 130, 246, 0.2);
        }
        .delete-icon {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.1);
        }
        .delete-icon:hover {
            background-color: rgba(239, 68, 68, 0.2);
        }
        .announcement-card {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .announcement-card:hover {
            border-left-color: #0ea5e9;
        }
        .stat-card {
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .chart-container canvas {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Notification Section -->
    <?php if(!empty($success)): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle"></i><?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['announcement_success'])): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle"></i><?php echo $_SESSION['announcement_success']; ?>
    </div>
    <?php unset($_SESSION['announcement_success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['announcement_error'])): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-exclamation-circle"></i><?php echo $_SESSION['announcement_error']; ?>
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
                        <?php 
                        $nav_items = [
                            ['admin.php', 'fa-home', 'Home', true],
                            ['students/search_student.php', 'fa-search', 'Search'],
                            ['students/student.php', 'fa-users', 'Students'],
                            ['sitin/current_sitin.php', 'fa-user-check', 'Sit-In'],
                            ['sitin/sitin_records.php', 'fa-list', 'Records'],
                            ['sitin/sitin_reports.php', 'fa-chart-bar', 'Reports'],
                            ['sitin/feedback_reports.php', 'fa-comment', 'Feedback'],
                            ['reservation/reservation.php', 'fa-calendar-check', 'Reservation'],
                        ];
                        
                        foreach ($nav_items as $item) {
                            $url = $item[0];
                            $icon = $item[1];
                            $label = $item[2];
                            $active = isset($item[3]) && $item[3];
                            $activeClass = $active ? 'bg-primary-800' : 'hover:bg-primary-800';
                            
                            echo "<a href=\"$url\" class=\"px-3 py-2 rounded transition flex items-center $activeClass\">
                                    <i class=\"fas $icon mr-1\"></i> $label
                                  </a>";
                        }
                        ?>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="newp.jpg" alt="Admin" class="w-full h-full object-cover">
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($admin_username ?? 'Admin'); ?></span>
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
                                <a href="auth/logout_admin.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
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
        <?php
        foreach ($nav_items as $item) {
            $url = $item[0];
            $icon = $item[1];
            $label = $item[2];
            $active = isset($item[3]) && $item[3];
            $activeClass = $active ? 'bg-primary-900' : 'hover:bg-primary-900';
            
            echo "<a href=\"$url\" class=\"block px-4 py-2 text-white $activeClass\">
                    <i class=\"fas $icon mr-2\"></i> $label
                  </a>";
        }
        ?>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Dashboard Overview Section -->
            <div class="bg-white rounded-xl shadow-md mb-6 dashboard-card">
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-tachometer-alt mr-2"></i> Admin Dashboard Overview
                    </h2>
                </div>
                
                <div class="p-6">
                    <!-- Welcome Message and Stats Summary -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</h1>
                        <p class="text-gray-600 mb-4">Here's your admin dashboard overview</p>
                        
                        <div class="flex flex-wrap -mx-2 mb-6">
                            <?php
                            $stats = [
                                ['bg-primary-50', 'border-primary-100', 'text-primary-700', 'text-primary-900', $student_count, 'Total Students', 'fa-user-graduate'],
                                ['bg-green-50', 'border-green-100', 'text-green-700', 'text-green-900', $active_sitins, 'Active Sit-Ins', 'fa-user-check'],
                                ['bg-amber-50', 'border-amber-100', 'text-amber-700', 'text-amber-900', $pending_approvals, 'Pending Approvals', 'fa-clock']
                            ];
                            
                            foreach ($stats as $i => $stat) {
                                echo "<div class=\"px-2 w-full sm:w-1/3" . ($i < 2 ? " mb-4 sm:mb-0" : "") . "\">
                                        <div class=\"{$stat[0]} border {$stat[1]} p-4 rounded-lg stat-card\">
                                            <div class=\"flex items-center\">
                                                <div class=\"flex-grow\">
                                                    <div class=\"text-3xl font-bold {$stat[2]}\">{$stat[4]}</div>
                                                    <div class=\"text-sm {$stat[3]}\">{$stat[5]}</div>
                                                </div>
                                                <div class=\"{$stat[2]} opacity-75\">
                                                    <i class=\"fas {$stat[6]} text-2xl\"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Student Distribution Charts -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-pie mr-2 text-primary-600"></i> Student Distribution Overview
                        </h3>
                        
                        <div class="chart-grid">
                            <!-- Year Level Distribution Chart -->
                            <div class="bg-gray-50 p-4 rounded-lg shadow-sm dashboard-card">
                                <h4 class="text-base font-medium text-gray-700 mb-2">Year Level Distribution</h4>
                                <div class="chart-container">
                                    <canvas id="studentYearChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Sit-In Purpose Distribution Chart -->
                            <div class="bg-gray-50 p-4 rounded-lg shadow-sm dashboard-card">
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
            <div class="bg-white rounded-xl shadow-md dashboard-card">
                <div class="bg-primary-700 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-bullhorn mr-2"></i> Edit Announcements
                    </h2>
                </div>
                <div class="p-6 dashboard-section">
                    <!-- Announcement Form -->
                    <form action="announcements/process_announcement.php" method="post" class="mb-6 border-b pb-6">
                        <div class="mb-4">
                            <label for="announcement_title" class="block text-gray-700 font-medium mb-2">Announcement Title</label>
                            <input type="text" id="announcement_title" name="title" required
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 form-input">
                        </div>
                        
                        <div class="mb-4">
                            <label for="announcement_content" class="block text-gray-700 font-medium mb-2">Announcement Content</label>
                            <textarea id="announcement_content" name="content" rows="4" required
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 form-input"></textarea>
                        </div>
                        
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition flex items-center">
                            <i class="fas fa-plus-circle mr-2"></i> Post Announcement
                        </button>
                    </form>

                    <!-- Existing Announcements -->
                    <h3 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-list-alt mr-2 text-primary-600"></i> Current Announcements
                    </h3>
                    
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $announcement): ?>
                        <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50 announcement-card">
                            <div class="flex justify-between items-start">
                                <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-500 mr-3 bg-gray-200 px-2 py-1 rounded-full">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        <?php echo date('Y-M-d', strtotime($announcement['created_at'])); ?>
                                    </span>
                                    <div class="flex space-x-2">
                                        <a href="announcements/edit_announcement.php?id=<?php echo $announcement['id']; ?>" 
                                           class="action-icon edit-icon" title="Edit announcement">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="announcements/delete_announcement.php?id=<?php echo $announcement['id']; ?>" 
                                           class="action-icon delete-icon" 
                                           onclick="return confirm('Are you sure you want to delete this announcement?')"
                                           title="Delete announcement">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-gray-500 italic flex items-center justify-center p-6 border border-dashed border-gray-300 rounded-lg">
                            <i class="fas fa-info-circle mr-2"></i> No announcements yet. Create one above.
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

        // Initialize the Pie Charts for Student Distribution
        document.addEventListener('DOMContentLoaded', function() {
            // Function to create doughnut chart
            function createDoughnutChart(ctx, labels, data, colorSet) {
                const colors = colorSet.map(color => color.replace('1)', '0.85)'));
                const hoverColors = colorSet.map(color => color.replace('0.85)', '1)'));
                
                return new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 4,
                            hoverBackgroundColor: hoverColors,
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
                                    font: { size: 11, family: 'Inter' },
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: { size: 13, family: 'Inter', weight: '600' },
                                bodyFont: { family: 'Inter', size: 12 },
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
            }
            
            // Setup data for the Year Level Chart
            const yearColors = [
                'rgba(79, 70, 229, 1)',   // indigo
                'rgba(37, 99, 235, 1)',   // blue
                'rgba(8, 145, 178, 1)',   // cyan
                'rgba(5, 150, 105, 1)',   // emerald
                'rgba(124, 58, 237, 1)'   // violet
            ];
            
            const yearLabels = [];
            const yearData = [];
            
            <?php foreach ($student_distribution as $year => $count): ?>
                yearLabels.push('<?php echo addslashes($year); ?>');
                yearData.push(<?php echo $count; ?>);
            <?php endforeach; ?>
            
            // Setup data for the Purpose Chart
            const purposeColors = [
                'rgba(59, 130, 246, 1)',  // blue
                'rgba(16, 185, 129, 1)',  // emerald
                'rgba(245, 158, 11, 1)',  // amber
                'rgba(239, 68, 68, 1)',   // red
                'rgba(139, 92, 246, 1)'   // violet
            ];
            
            const purposeLabels = [];
            const purposeData = [];
            
            <?php foreach ($purpose_distribution as $purpose => $count): ?>
                purposeLabels.push('<?php echo addslashes($purpose); ?>');
                purposeData.push(<?php echo $count; ?>);
            <?php endforeach; ?>
            
            // Create charts
            createDoughnutChart(
                document.getElementById('studentYearChart').getContext('2d'),
                yearLabels, yearData, yearColors
            );
            
            createDoughnutChart(
                document.getElementById('sitinPurposeChart').getContext('2d'),
                purposeLabels, purposeData, purposeColors
            );
            
            // Enhanced UI Enhancements for a more professional look
            document.querySelectorAll('.card, .bg-white.rounded-xl').forEach(card => {
                card.classList.add('transition-all', 'duration-300');
            });
            
            document.querySelectorAll('button:not([disabled]), a.px-3.py-2, a.px-4.py-2').forEach(button => {
                button.classList.add('transition-colors', 'duration-200');
            });
            
            document.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-primary-100', 'ring-opacity-50');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-primary-100', 'ring-opacity-50');
                });
            });
            
            // Show tooltips on hover for icons
            document.querySelectorAll('.action-icon').forEach(icon => {
                icon.addEventListener('mouseenter', function() {
                    if (this.getAttribute('title')) {
                        const tooltip = document.createElement('div');
                        tooltip.className = 'bg-gray-800 text-white text-xs rounded py-1 px-2 absolute z-50';
                        tooltip.style.bottom = '125%';
                        tooltip.style.left = '50%';
                        tooltip.style.transform = 'translateX(-50%)';
                        tooltip.style.whiteSpace = 'nowrap';
                        tooltip.textContent = this.getAttribute('title');
                        tooltip.style.opacity = '0';
                        tooltip.style.transition = 'opacity 0.2s';
                        
                        this.style.position = 'relative';
                        this.appendChild(tooltip);
                        
                        setTimeout(() => {
                            tooltip.style.opacity = '1';
                        }, 10);
                    }
                });
                
                icon.addEventListener('mouseleave', function() {
                    const tooltip = this.querySelector('div');
                    if (tooltip) {
                        tooltip.style.opacity = '0';
                        setTimeout(() => {
                            tooltip.remove();
                        }, 200);
                    }
                });
            });
            
            // Auto hide notifications
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
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