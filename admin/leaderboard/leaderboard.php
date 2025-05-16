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

// Initialize filter variables
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'month';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'sessions';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Validate limit
if ($limit < 5 || $limit > 100) {
    $limit = 10;
}

// Define period conditions
$period_conditions = [
    'week' => 'AND check_in_time >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
    'month' => 'AND check_in_time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
    'semester' => 'AND check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)',
    'year' => 'AND check_in_time >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
    'all' => ''
];

$period_condition = $period_conditions[$filter_period] ?? $period_conditions['month'];

// First, check if the users table exists
$check_table = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_table && $check_table->num_rows == 0) {
    // If users table doesn't exist, we'll proceed without extra columns
    $using_users_table = false;
} else {
    $using_users_table = true;
    
    // Check the schema to determine column names
    $check_columns_query = "DESCRIBE users";
    $columns_result = $conn->query($check_columns_query);
    $columns = [];
    
    if ($columns_result) {
        while ($row = $columns_result->fetch_assoc()) {
            $columns[strtoupper($row['Field'])] = $row['Field']; // Store with uppercase key for case-insensitive lookup
        }
    }
}

// Get top 5 students by sessions for the highlight leaderboard
$top5_sessions = [];
if ($using_users_table) {
    // Build query based on available columns (similar to existing code)
    $year_field = '';
    $course_field = '';
    $join_clause = '';
    
    // Check for year column variations
    if (isset($columns['YEAR'])) {
        $year_field = "u.{$columns['YEAR']} as year_level";
    } elseif (isset($columns['YEAR_LEVEL'])) {
        $year_field = "u.{$columns['YEAR_LEVEL']} as year_level";
    } elseif (isset($columns['YEARLEVEL'])) {
        $year_field = "u.{$columns['YEARLEVEL']} as year_level";
    } else {
        $year_field = "'' as year_level";
    }
    
    // Check for course column variations
    if (isset($columns['COURSE'])) {
        $course_field = "u.{$columns['COURSE']} as course";
    } elseif (isset($columns['COURSE_CODE'])) {
        $course_field = "u.{$columns['COURSE_CODE']} as course";
    } else {
        $course_field = "'' as course";
    }
    
    // Determine user ID field for joining
    $user_id_field = 'IDNO';
    if (isset($columns['IDNO'])) {
        $user_id_field = $columns['IDNO'];
    } elseif (isset($columns['ID'])) {
        $user_id_field = $columns['ID'];
    } elseif (isset($columns['USER_ID'])) {
        $user_id_field = $columns['USER_ID'];
    } elseif (isset($columns['USERID'])) {
        $user_id_field = $columns['USERID'];
    }
    
    $join_clause = "LEFT JOIN users u ON s.student_id = u.{$user_id_field}";
    
    $top5_sessions_query = "
        SELECT 
            s.student_id,
            s.student_name,
            {$year_field},
            {$course_field},
            COUNT(s.session_id) as total_sessions
        FROM 
            sit_in_sessions s
        {$join_clause}
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_sessions DESC
        LIMIT 5
    ";
} else {
    // Simplified query without users table join
    $top5_sessions_query = "
        SELECT 
            s.student_id,
            s.student_name,
            '' as year_level,
            '' as course,
            COUNT(s.session_id) as total_sessions
        FROM 
            sit_in_sessions s
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_sessions DESC
        LIMIT 5
    ";
}

$result = $conn->query($top5_sessions_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $top5_sessions[] = $row;
    }
}

// Get top 5 students by time spent for the highlight leaderboard
$top5_time_spent = [];
if ($using_users_table) {
    // Use the same column variables already defined above
    $top5_time_query = "
        SELECT 
            s.student_id,
            s.student_name,
            {$year_field},
            {$course_field},
            SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
        FROM 
            sit_in_sessions s
        {$join_clause}
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_minutes DESC
        LIMIT 5
    ";
} else {
    // Simplified query without users table join
    $top5_time_query = "
        SELECT 
            s.student_id,
            s.student_name,
            '' as year_level,
            '' as course,
            SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
        FROM 
            sit_in_sessions s
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_minutes DESC
        LIMIT 5
    ";
}

$result = $conn->query($top5_time_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $top5_time_spent[] = $row;
    }
}

// Points calculation formula - add this after the existing initialization section
$points_per_session = 10; // Base points per session
$points_per_hour = 5;    // Points per hour spent

// Get top 5 students by combined score (sessions + time points)
$top5_combined = [];
if ($using_users_table) {
    // Build query based on available columns
    $year_field = '';
    $course_field = '';
    $join_clause = '';
    
    // Check for year column variations
    if (isset($columns['YEAR'])) {
        $year_field = "u.{$columns['YEAR']} as year_level";
    } elseif (isset($columns['YEAR_LEVEL'])) {
        $year_field = "u.{$columns['YEAR_LEVEL']} as year_level";
    } elseif (isset($columns['YEARLEVEL'])) {
        $year_field = "u.{$columns['YEARLEVEL']} as year_level";
    } else {
        $year_field = "'' as year_level";
    }
    
    // Check for course column variations
    if (isset($columns['COURSE'])) {
        $course_field = "u.{$columns['COURSE']} as course";
    } elseif (isset($columns['COURSE_CODE'])) {
        $course_field = "u.{$columns['COURSE_CODE']} as course";
    } else {
        $course_field = "'' as course";
    }
    
    // Determine user ID field for joining
    $user_id_field = 'IDNO';
    if (isset($columns['IDNO'])) {
        $user_id_field = $columns['IDNO'];
    } elseif (isset($columns['ID'])) {
        $user_id_field = $columns['ID'];
    } elseif (isset($columns['USER_ID'])) {
        $user_id_field = $columns['USER_ID'];
    } elseif (isset($columns['USERID'])) {
        $user_id_field = $columns['USERID'];
    }
    
    $join_clause = "LEFT JOIN users u ON s.student_id = u.{$user_id_field}";
    
    $top5_combined_query = "
        SELECT 
            s.student_id,
            s.student_name,
            {$year_field},
            {$course_field},
            COUNT(s.session_id) as total_sessions,
            SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes,
            (COUNT(s.session_id) * {$points_per_session}) + 
            (SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) / 60 * {$points_per_hour}) as total_points
        FROM 
            sit_in_sessions s
        {$join_clause}
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_points DESC
        LIMIT 5
    ";
} else {
    // Simplified query without users table join
    $top5_combined_query = "
        SELECT 
            s.student_id,
            s.student_name,
            '' as year_level,
            '' as course,
            COUNT(s.session_id) as total_sessions,
            SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes,
            (COUNT(s.session_id) * {$points_per_session}) + 
            (SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) / 60 * {$points_per_hour}) as total_points
        FROM 
            sit_in_sessions s
        WHERE 
            s.status = 'inactive'
            {$period_condition}
        GROUP BY 
            s.student_id, s.student_name
        ORDER BY 
            total_points DESC
        LIMIT 5
    ";
}

$result = $conn->query($top5_combined_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Round points to whole number
        $row['total_points'] = round($row['total_points']);
        $top5_combined[] = $row;
    }
}

// Get top students by sessions
$top_sessions = [];
if ($filter_type == 'sessions' || $filter_type == 'all') {
    
    if ($using_users_table) {
        // Build query based on available columns
        $year_field = '';
        $course_field = '';
        $join_clause = '';
        
        // Check for year column variations
        if (isset($columns['YEAR'])) {
            $year_field = "u.{$columns['YEAR']} as year_level";
        } elseif (isset($columns['YEAR_LEVEL'])) {
            $year_field = "u.{$columns['YEAR_LEVEL']} as year_level";
        } elseif (isset($columns['YEARLEVEL'])) {
            $year_field = "u.{$columns['YEARLEVEL']} as year_level";
        } else {
            $year_field = "'' as year_level";
        }
        
        // Check for course column variations
        if (isset($columns['COURSE'])) {
            $course_field = "u.{$columns['COURSE']} as course";
        } elseif (isset($columns['COURSE_CODE'])) {
            $course_field = "u.{$columns['COURSE_CODE']} as course";
        } else {
            $course_field = "'' as course";
        }
        
        // Determine user ID field for joining
        $user_id_field = 'IDNO';
        if (isset($columns['IDNO'])) {
            $user_id_field = $columns['IDNO'];
        } elseif (isset($columns['ID'])) {
            $user_id_field = $columns['ID'];
        } elseif (isset($columns['USER_ID'])) {
            $user_id_field = $columns['USER_ID'];
        } elseif (isset($columns['USERID'])) {
            $user_id_field = $columns['USERID'];
        }
        
        $join_clause = "LEFT JOIN users u ON s.student_id = u.{$user_id_field}";
        
        $sessions_query = "
            SELECT 
                s.student_id,
                s.student_name,
                {$year_field},
                {$course_field},
                COUNT(s.session_id) as total_sessions,
                SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
            FROM 
                sit_in_sessions s
            {$join_clause}
            WHERE 
                s.status = 'inactive'
                {$period_condition}
            GROUP BY 
                s.student_id, s.student_name
            ORDER BY 
                total_sessions DESC, total_minutes DESC
            LIMIT {$limit}
        ";
    } else {
        // Simplified query without users table join
        $sessions_query = "
            SELECT 
                s.student_id,
                s.student_name,
                '' as year_level,
                '' as course,
                COUNT(s.session_id) as total_sessions,
                SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
            FROM 
                sit_in_sessions s
            WHERE 
                s.status = 'inactive'
                {$period_condition}
            GROUP BY 
                s.student_id, s.student_name
            ORDER BY 
                total_sessions DESC, total_minutes DESC
            LIMIT {$limit}
        ";
    }
    
    $result = $conn->query($sessions_query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $top_sessions[] = $row;
        }
    }
}

// Get top students by time spent
$top_time_spent = [];
if ($filter_type == 'time' || $filter_type == 'all') {
    if ($using_users_table) {
        // Build query based on available columns
        $year_field = '';
        $course_field = '';
        $join_clause = '';
        
        // Check for year column variations
        if (isset($columns['YEAR'])) {
            $year_field = "u.{$columns['YEAR']} as year_level";
        } elseif (isset($columns['YEAR_LEVEL'])) {
            $year_field = "u.{$columns['YEAR_LEVEL']} as year_level";
        } elseif (isset($columns['YEARLEVEL'])) {
            $year_field = "u.{$columns['YEARLEVEL']} as year_level";
        } else {
            $year_field = "'' as year_level";
        }
        
        // Check for course column variations
        if (isset($columns['COURSE'])) {
            $course_field = "u.{$columns['COURSE']} as course";
        } elseif (isset($columns['COURSE_CODE'])) {
            $course_field = "u.{$columns['COURSE_CODE']} as course";
        } else {
            $course_field = "'' as course";
        }
        
        // Determine user ID field for joining
        $user_id_field = 'IDNO';
        if (isset($columns['IDNO'])) {
            $user_id_field = $columns['IDNO'];
        } elseif (isset($columns['ID'])) {
            $user_id_field = $columns['ID'];
        } elseif (isset($columns['USER_ID'])) {
            $user_id_field = $columns['USER_ID'];
        } elseif (isset($columns['USERID'])) {
            $user_id_field = $columns['USERID'];
        }
        
        $join_clause = "LEFT JOIN users u ON s.student_id = u.{$user_id_field}";
        
        $time_query = "
            SELECT 
                s.student_id,
                s.student_name,
                {$year_field},
                {$course_field},
                COUNT(s.session_id) as total_sessions,
                SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
            FROM 
                sit_in_sessions s
            {$join_clause}
            WHERE 
                s.status = 'inactive'
                {$period_condition}
            GROUP BY 
                s.student_id, s.student_name
            ORDER BY 
                total_minutes DESC, total_sessions DESC
            LIMIT {$limit}
        ";
    } else {
        // Simplified query without users table join
        $time_query = "
            SELECT 
                s.student_id,
                s.student_name,
                '' as year_level,
                '' as course,
                COUNT(s.session_id) as total_sessions,
                SUM(TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW()))) as total_minutes
            FROM 
                sit_in_sessions s
            WHERE 
                s.status = 'inactive'
                {$period_condition}
            GROUP BY 
                s.student_id, s.student_name
            ORDER BY 
                total_minutes DESC, total_sessions DESC
            LIMIT {$limit}
        ";
    }
    
    $result = $conn->query($time_query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $top_time_spent[] = $row;
        }
    }
}

// Format duration function
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%d hr %d min", $hours, $mins);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Leaderboard | Admin Dashboard</title>
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
        
        .leaderboard-card {
            transition: all 0.3s ease;
        }
        
        .leaderboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .rank-1 {
            background-color: #fef3c7;
            color: #d97706;
            border: 2px solid #f59e0b;
        }
        
        .rank-2 {
            background-color: #f1f5f9;
            color: #64748b;
            border: 2px solid #94a3b8;
        }
        
        .rank-3 {
            background-color: #fff7ed;
            color: #c2410c;
            border: 2px solid #ea580c;
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
        
        /* New ribbon and trophy styles */
        .trophy-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #fbbf24;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 2px solid white;
        }
        
        .podium {
            position: relative;
            height: 180px;
            display: flex;
            align-items: flex-end;
        }
        
        .podium-place {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .first-place {
            height: 120px;
            z-index: 3;
            width: 34%;
        }
        
        .second-place {
            height: 90px;
            z-index: 2;
            width: 33%;
        }
        
        .third-place {
            height: 60px;
            z-index: 1;
            width: 33%;
        }
        
        .podium-block {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .podium-base {
            width: 90%;
            max-width: 150px;
            border-radius: 8px 8px 0 0;
        }
        
        .first-base {
            background: linear-gradient(180deg, #ffd700 0%, #e6b800 100%);
            height: 100%;
        }
        
        .second-base {
            background: linear-gradient(180deg, #c0c0c0 0%, #a0a0a0 100%);
            height: 100%;
        }
        
        .third-base {
            background: linear-gradient(180deg, #cd7f32 0%, #a05a1f 100%);
            height: 100%;
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid white;
            margin-bottom: -25px;
            z-index: 10;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .student-details {
            text-align: center;
            margin-top: 8px;
        }
        
        .progress-container {
            width: 100%;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #0ea5e9;
        }
        
        /* Points badge styles */
        .points-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #0ea5e9;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid white;
        }
        
        .achievement-card {
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .achievement-card:hover {
            transform: translateY(-5px);
        }
        
        .achievement-ribbon {
            position: absolute;
            top: 10px;
            right: -30px;
            width: 120px;
            background-color: #10b981;
            color: white;
            text-align: center;
            padding: 5px 0;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
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
                        <a href="leaderboard.php" class="nav-button px-3 py-2 bg-primary-800 rounded transition flex items-center">
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
        <a href="leaderboard.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-trophy mr-2"></i> Leaderboard
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-6">
        <!-- Hero Leaderboard - Top 5 Combined Points -->
        <div class="bg-white rounded-xl shadow-md mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white px-6 py-4 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold flex items-center">
                        <i class="fas fa-trophy mr-2 text-yellow-300"></i> Top Student Leaderboard
                    </h2>
                    <p class="text-xs text-primary-100 mt-1">Combined score based on total sessions and time spent</p>
                </div>
                <div class="bg-white bg-opacity-20 backdrop-blur-sm px-3 py-1 rounded-lg text-sm">
                    <span class="text-yellow-200">
                        <i class="fas fa-calculator mr-1"></i> 
                        <?php echo $points_per_session; ?> pts per session + <?php echo $points_per_hour; ?> pts per hour
                    </span>
                </div>
            </div>
            
            <div class="p-6 bg-gradient-to-b from-gray-50 to-white">
                <!-- Top 3 Podium -->
                <?php if (count($top5_combined) >= 3): ?>
                <div class="podium mb-8">
                    <!-- Second Place -->
                    <div class="podium-place second-place">
                        <div class="avatar bg-gray-200 border-gray-300 text-gray-600">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="podium-block">
                            <div class="podium-base second-base relative">
                                <span class="points-badge"><?php echo $top5_combined[1]['total_points']; ?> pts</span>
                            </div>
                        </div>
                        <div class="student-details">
                            <div class="font-bold text-sm text-gray-700 truncate max-w-[100px]"><?php echo htmlspecialchars($top5_combined[1]['student_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo $top5_combined[1]['total_sessions']; ?> sessions</div>
                        </div>
                    </div>
                    
                    <!-- First Place -->
                    <div class="podium-place first-place">
                        <div class="avatar bg-yellow-100 border-yellow-400 text-yellow-600">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="trophy-badge">
                            <i class="fas fa-trophy text-white"></i>
                        </div>
                        <div class="podium-block">
                            <div class="podium-base first-base relative">
                                <span class="points-badge bg-yellow-500"><?php echo $top5_combined[0]['total_points']; ?> pts</span>
                            </div>
                        </div>
                        <div class="student-details">
                            <div class="font-bold text-gray-800 truncate max-w-[120px]"><?php echo htmlspecialchars($top5_combined[0]['student_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo $top5_combined[0]['total_sessions']; ?> sessions</div>
                        </div>
                    </div>
                    
                    <!-- Third Place -->
                    <div class="podium-place third-place">
                        <div class="avatar bg-amber-100 border-amber-300 text-amber-600">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="podium-block">
                            <div class="podium-base third-base relative">
                                <span class="points-badge bg-amber-500"><?php echo $top5_combined[2]['total_points']; ?> pts</span>
                            </div>
                        </div>
                        <div class="student-details">
                            <div class="font-bold text-sm text-gray-700 truncate max-w-[100px]"><?php echo htmlspecialchars($top5_combined[2]['student_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo $top5_combined[2]['total_sessions']; ?> sessions</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Top 5 Leaderboard Table -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-700 flex items-center">
                            <i class="fas fa-list-ol mr-2 text-primary-600"></i>
                            Top 5 Students Overall
                        </h3>
                    </div>
                    
                    <div class="overflow-hidden">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-50 text-gray-700 text-xs uppercase font-medium">
                                    <th class="py-2 px-4 text-center">#</th>
                                    <th class="py-2 px-4 text-left">Student</th>
                                    <th class="py-2 px-4 text-center">Sessions</th>
                                    <th class="py-2 px-4 text-center">Time</th>
                                    <th class="py-2 px-4 text-center">Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($top5_combined as $index => $student): ?>
                                <tr class="<?php echo $index < 3 ? 'bg-'.($index === 0 ? 'yellow' : ($index === 1 ? 'gray' : 'amber')).'-50' : ''; ?> hover:bg-gray-50 transition-colors">
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($index === 0): ?>
                                            <span class="text-yellow-500 font-bold"><i class="fas fa-trophy"></i> 1</span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="text-gray-500 font-bold"><i class="fas fa-medal"></i> 2</span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="text-amber-600 font-bold"><i class="fas fa-medal"></i> 3</span>
                                        <?php else: ?>
                                            <span class="text-gray-600 font-medium"><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 border border-primary-200">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                <div class="text-xs text-gray-500 flex items-center mt-0.5">
                                                    <span class="mr-2"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                                    <?php if (!empty($student['course'])): ?>
                                                        <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-xs">
                                                            <?php echo htmlspecialchars($student['course']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-medium text-gray-800"><?php echo $student['total_sessions']; ?></span>
                                        <span class="text-xs text-gray-500 block">sessions</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-medium text-gray-800"><?php echo formatDuration($student['total_minutes']); ?></span>
                                        <span class="text-xs text-gray-500 block">total time</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-block px-3 py-1 leading-none text-center whitespace-nowrap align-baseline font-bold bg-primary-100 text-primary-700 rounded">
                                            <?php echo $student['total_points']; ?> pts
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (count($top5_combined) === 0): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center">
                                        <div class="text-gray-400 text-4xl mb-3">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No leaderboard data</h3>
                                        <p class="text-gray-500 text-sm">There are no completed sessions in the system yet</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Student Achievement Cards -->
                <?php if (count($top5_combined) > 0): ?>
                <div class="mt-8">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center">
                        <i class="fas fa-award mr-2 text-primary-600"></i>
                        Student Achievements
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Most Sessions -->
                        <div class="achievement-card bg-white border border-yellow-200 rounded-lg p-5 shadow-sm relative">
                            <div class="achievement-ribbon bg-yellow-500">Most Sessions</div>
                            <div class="flex items-center">
                                <div class="w-12 h-12 flex-shrink-0 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 mr-4">
                                    <i class="fas fa-calendar-check text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($top5_sessions[0]['student_name']); ?></h4>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($top5_sessions[0]['student_id']); ?></span>
                                        <span class="text-sm font-bold text-yellow-600"><?php echo $top5_sessions[0]['total_sessions']; ?> sessions</span>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-container mt-3">
                                <div class="progress-bar bg-yellow-500" style="width: 100%;"></div>
                            </div>
                        </div>
                        
                        <!-- Most Hours -->
                        <div class="achievement-card bg-white border border-blue-200 rounded-lg p-5 shadow-sm relative">
                            <div class="achievement-ribbon bg-blue-500">Most Time</div>
                            <div class="flex items-center">
                                <div class="w-12 h-12 flex-shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4">
                                    <i class="fas fa-clock text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($top5_time_spent[0]['student_name']); ?></h4>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($top5_time_spent[0]['student_id']); ?></span>
                                        <span class="text-sm font-bold text-blue-600"><?php echo formatDuration($top5_time_spent[0]['total_minutes']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-container mt-3">
                                <div class="progress-bar bg-blue-500" style="width: 100%;"></div>
                            </div>
                        </div>
                        
                        <!-- Most Consistent -->
                        <div class="achievement-card bg-white border border-green-200 rounded-lg p-5 shadow-sm relative">
                            <div class="achievement-ribbon bg-green-500">Most Points</div>
                            <div class="flex items-center">
                                <div class="w-12 h-12 flex-shrink-0 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-4">
                                    <i class="fas fa-star text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($top5_combined[0]['student_name']); ?></h4>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($top5_combined[0]['student_id']); ?></span>
                                        <span class="text-sm font-bold text-green-600"><?php echo $top5_combined[0]['total_points']; ?> points</span>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-container mt-3">
                                <div class="progress-bar bg-green-500" style="width: 100%;"></div>
                            </div>
                        </div>
                        
                        <!-- Longest Average Session -->
                        <div class="achievement-card bg-white border border-purple-200 rounded-lg p-5 shadow-sm relative">
                            <div class="achievement-ribbon bg-purple-500">Rising Star</div>
                            <?php 
                            // Find the student with highest points-to-sessions ratio (new student with good performance)
                            $rising_star = $top5_combined[0]; // Default to top student
                            foreach ($top5_combined as $student) {
                                if ($student['total_sessions'] <= 10 && $student['total_points'] >= $rising_star['total_points'] * 0.7) {
                                    $rising_star = $student;
                                    break;
                                }
                            }
                            ?>
                            <div class="flex items-center">
                                <div class="w-12 h-12 flex-shrink-0 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 mr-4">
                                    <i class="fas fa-rocket text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($rising_star['student_name']); ?></h4>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($rising_star['student_id']); ?></span>
                                        <span class="text-sm font-bold text-purple-600"><?php echo $rising_star['total_sessions']; ?> sessions</span>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-container mt-3">
                                <div class="progress-bar bg-purple-500" style="width: <?php echo min(100, $rising_star['total_sessions'] * 10); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Page Title and Filters Section -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl flex justify-between items-center">
                <h2 class="text-xl font-semibold">Student Leaderboard</h2>
                <div class="text-xs bg-white bg-opacity-30 px-3 py-1 rounded-full">
                    <i class="fas fa-trophy mr-1"></i> Top Performers
                </div>
            </div>
            
            <div class="p-6">
                <!-- Filters -->
                <form action="" method="GET" class="mb-6 flex flex-wrap gap-4 bg-gray-50 p-4 rounded-lg">
                    <div>
                        <label for="period" class="block text-xs text-gray-600 mb-1">Time Period</label>
                        <select id="period" name="period" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm" onchange="this.form.submit()">
                            <option value="week" <?php echo $filter_period == 'week' ? 'selected' : ''; ?>>Past Week</option>
                            <option value="month" <?php echo $filter_period == 'month' ? 'selected' : ''; ?>>Past Month</option>
                            <option value="semester" <?php echo $filter_period == 'semester' ? 'selected' : ''; ?>>Past Semester</option>
                            <option value="year" <?php echo $filter_period == 'year' ? 'selected' : ''; ?>>Past Year</option>
                            <option value="all" <?php echo $filter_period == 'all' ? 'selected' : ''; ?>>All Time</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="type" class="block text-xs text-gray-600 mb-1">Ranking Type</label>
                        <select id="type" name="type" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm" onchange="this.form.submit()">
                            <option value="sessions" <?php echo $filter_type == 'sessions' ? 'selected' : ''; ?>>Total Sessions</option>
                            <option value="time" <?php echo $filter_type == 'time' ? 'selected' : ''; ?>>Total Time Spent</option>
                            <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>Both</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="limit" class="block text-xs text-gray-600 mb-1">Show Top</label>
                        <select id="limit" name="limit" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm" onchange="this.form.submit()">
                            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>Top 5</option>
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>Top 10</option>
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>Top 20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>Top 50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>Top 100</option>
                        </select>
                    </div>
                </form>

                <!-- ...existing Leaderboards Section... -->
            </div>
        </div>

        <!-- ...existing code... -->

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