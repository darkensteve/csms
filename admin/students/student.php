<?php
// Include database connection
require_once '../../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Get admin username for display
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Set debug mode to false for production environment
$debug_mode = false; // Change to true only for development/debugging
$debug_info = '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get search term if provided
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Check if we have a table for students
$tables_in_db = [];
$tables_result = $conn->query("SHOW TABLES");
if ($tables_result) {
    while($table_row = $tables_result->fetch_row()) {
        $tables_in_db[] = $table_row[0];
    }
}

// Try to find the student table
$table_name = '';
$potential_tables = ['users', 'students', 'student'];
            
foreach ($potential_tables as $table) {
    if (in_array($table, $tables_in_db)) {
        $table_name = $table;
        $debug_info .= "Using table: {$table_name}. ";
        break;
    }
}

// If no known student tables found, try the first table
if (empty($table_name) && !empty($tables_in_db)) {
    $table_name = $tables_in_db[0];
    $debug_info .= "No recognized student table found. Using first table: {$table_name}. ";
}

// Initialize variables
$students = [];
$total_records = 0;
$total_pages = 1;
$error_message = '';

if (!empty($table_name)) {
    // Check if remaining_sessions column exists in the table
    $column_check = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE 'remaining_sessions'");
    
    if ($column_check && $column_check->num_rows == 0) {
        // Add remaining_sessions column if it doesn't exist
        $conn->query("ALTER TABLE `{$table_name}` ADD COLUMN remaining_sessions INT(11) NOT NULL DEFAULT 30");
        $debug_info .= "Added remaining_sessions column to {$table_name} table. ";
    }
    
    // Get columns for the selected table
    $columns = [];
    $col_result = $conn->query("SHOW COLUMNS FROM `{$table_name}`");
    if ($col_result) {
        while($col = $col_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    // Define priority columns for display
    $display_columns = []; 
    $excluded_terms = ['password', 'pass', 'passwd', 'hash', 'salt', 'user_id', 'userid', 'username', 'user_name', 'remaining_sessions'];
    
    // Define priority columns that should be shown first if they exist
    $priority_columns = ['id', 'student_id', 'name', 'firstname', 'first_name', 'middlename', 'middle_name', 'mi', 
                         'lastname', 'last_name', 'year_level', 'year', 'level', 'course', 'department', 'email'];
    
    // First add priority columns that exist in the table
    foreach ($priority_columns as $priority_col) {
        foreach ($columns as $col) {
            $col_lower = strtolower($col);
            
            if (strpos($col_lower, $priority_col) !== false) {
                // Skip excluded columns
                $exclude = false;
                foreach ($excluded_terms as $term) {
                    if (strpos($col_lower, $term) !== false) {
                        $exclude = true;
                        break;
                    }
                }
                
                if (!$exclude && !in_array($col, $display_columns)) {
                    $display_columns[] = $col;
                }
            }
        }
    }
    
    // Then add remaining columns
    foreach ($columns as $col) {
        $col_lower = strtolower($col);
        
        // Skip excluded columns
        $exclude = false;
        foreach ($excluded_terms as $term) {
            if (strpos($col_lower, $term) !== false) {
                $exclude = true;
                break;
            }
        }
        
        if (!$exclude && !in_array($col, $display_columns)) {
            $display_columns[] = $col;
        }
    }
    
    // Get identified name and ID columns for search
    $search_fields = [];
    foreach ($columns as $col) {
        $col_lower = strtolower($col);
        if (strpos($col_lower, 'id') !== false || 
            strpos($col_lower, 'student') !== false || 
            strpos($col_lower, 'name') !== false || 
            strpos($col_lower, 'first') !== false || 
            strpos($col_lower, 'last') !== false) {
            $search_fields[] = $col;
        }
    }
    
    // Build search condition for the query
    $search_condition = '';
    if (!empty($search_term) && !empty($search_fields)) {
        $search_conditions = [];
        foreach ($search_fields as $field) {
            $search_conditions[] = "`{$field}` LIKE '%" . $conn->real_escape_string($search_term) . "%'";
        }
        $search_condition = "WHERE " . implode(" OR ", $search_conditions);
    }
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM `{$table_name}` " . $search_condition;
    $count_result = $conn->query($count_query);
    
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
        
        // Adjust page if out of bounds
        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        $offset = ($page - 1) * $records_per_page;
    }
    
    // Fetch student records with pagination and search
    $query = "SELECT *, IFNULL(remaining_sessions, 30) as remaining_sessions FROM `{$table_name}` " . 
             $search_condition . " LIMIT $offset, $records_per_page";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        $error_message = "Error fetching student records: " . $conn->error;
    }
} else {
    $error_message = "No student table found in the database.";
}

// Handle deletion if requested
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_column = isset($_GET['id_col']) ? $_GET['id_col'] : 'id';
    $id_value = $_GET['id'];
    
    // First check if this student has any active sit-in sessions
    $check_sitin_query = "SELECT COUNT(*) as count FROM sit_in_sessions WHERE student_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_sitin_query);
    
    if ($check_stmt) { 0;
        $check_stmt->bind_param('s', $id_value);
        $check_stmt->bind_param('s', $id_value);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $active_sessions = $row['count'];
        $check_stmt->close();
    }
    
    // If student has active sessions, prevent deletion
    if ($active_sessions > 0) {
        // Don't allow deletion, redirect with error message
        $_SESSION['error_message'] = "Cannot delete student with active sit-in sessions. Please time out the student first.";
        $_SESSION['student_id'] = $id_value;
        header("Location: student.php?error=active_session");
        exit();
    }
    
    // Proceed with deletion only if no active sessions
    $delete_query = "DELETE FROM `{$table_name}` WHERE `{$id_column}` = ?";
    $stmt = $conn->prepare($delete_query);
    
    if ($stmt) {
        $stmt->bind_param('s', $id_value);
        if ($stmt->execute()) {
            // Also delete any historical sit-in records for this student
            $delete_history_query = "DELETE FROM sit_in_sessions WHERE student_id = ?";
            $history_stmt = $conn->prepare($delete_history_query);
            if ($history_stmt) {
                $history_stmt->bind_param('s', $id_value);
                $history_stmt->execute();
                $history_stmt->close();
            }
            
            header("Location: student.php?deleted=1");
            exit();
        } else {
            $error_message = "Error deleting record: " . $stmt->error;
        }
    } else {
        $error_message = "Error preparing delete statement: " . $conn->error;
    }
}

// Check for session error message
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    $error_student_id = $_SESSION['student_id'] ?? '';
    unset($_SESSION['error_message']);
    unset($_SESSION['student_id']);
}

// Success message after deletion
$success_message = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success_message = "Student record successfully deleted.";
}

// Add success message for record updates
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = "Student record successfully updated.";
}

// Add success message for reset all sessions
if (isset($_GET['reset_all']) && $_GET['reset_all'] == '1') {
    $success_message = "All students' remaining sessions have been reset to 30.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Sit-In Management System</title>
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
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Success/Error Notifications -->
    <?php if (!empty($success_message)): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
        <?php if (isset($error_student_id) && !empty($error_student_id)): ?>
        <a href="../sitin/current_sitin.php?search=<?php echo urlencode($error_student_id); ?>" class="ml-3 bg-white text-red-600 px-2 py-1 rounded text-xs">
            View Active Session
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                        <a href="search_student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="student.php" class="px-3 py-2 bg-primary-800 rounded transition flex items-center">
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
        <a href="search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="student.php" class="block px-4 py-2 text-white bg-primary-900">
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
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
            <!-- Student Management Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Student Management</h2>
                </div>
                <div class="p-6">
                    <!-- Enhanced debug info for administrators - only show in debug mode -->
                    <?php if (!empty($debug_info) && $debug_mode): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <?php echo htmlspecialchars($debug_info); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Search and Filter Tools -->
                    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                        <form action="" method="GET" class="w-full md:w-auto mb-3 md:mb-0">
                            <div class="flex">
                                <input type="text" name="search" placeholder="Search by ID or Name..." class="rounded-l-md border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo htmlspecialchars($search_term); ?>">
                                <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-r-md hover:bg-primary-700">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        <div class="flex space-x-2">
                            <select class="border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="">All Years</option>
                                <option value="1">First Year</option>
                                <option value="2">Second Year</option>
                                <option value="3">Third Year</option>
                                <option value="4">Fourth Year</option>
                            </select>
                            <select class="border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="">All Departments</option>
                                <option value="CS">Computer Science</option>
                                <option value="IT">Information Technology</option>
                                <option value="ENG">Engineering</option>
                                <option value="BUS">Business</option>
                            </select>
                            <a href="reset_all_sessions.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition duration-200 flex items-center" onclick="return confirm('Are you sure you want to reset ALL students\' remaining sessions to 30? This action cannot be undone.')">
                                <i class="fas fa-sync-alt mr-2"></i> Reset All Sessions
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($search_term) && count($students) === 0): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-search text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    No students found matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>".
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($search_term) && count($students) > 0): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-search text-blue-400"></i>
                            </div>
                            <div class="ml-3 flex-grow">
                                <p class="text-sm text-blue-700">
                                    Found <?php echo count($students); ?> student(s) matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                                </p>
                            </div>
                            <a href="student.php" class="text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times-circle"></i> Clear search
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (count($students) > 0): ?>
                        <!-- Student Table -->
                        <div class="overflow-x-auto bg-gray-50 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <?php 
                                        // Dynamically generate table headers based on available columns
                                        $id_column = '';
                                        $headers_displayed = 0;
                                        foreach ($display_columns as $col) {
                                            // Skip some columns or limit displayed columns
                                            if ($headers_displayed >= 6) continue;
                                            
                                            $col_lower = strtolower($col);
                                            if (empty($id_column) && (strpos($col_lower, 'id') !== false)) {
                                                $id_column = $col;
                                            }
                                            
                                            echo '<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">' . 
                                                htmlspecialchars($col) . 
                                                '</th>';    
                                            $headers_displayed++;
                                        }
                                        ?>
                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase tracking-wider">Remaining Session</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50">
                                        <?php 
                                        $cols_displayed = 0;
                                        $id_value = '';
                                        foreach ($display_columns as $col) {
                                            // Skip some columns or limit displayed columns
                                            if ($cols_displayed >= 6) continue;
                                            
                                            if ($col === $id_column) {
                                                $id_value = $student[$col];
                                            }
                                            
                                            echo '<td class="px-4 py-3 text-sm text-gray-700">' .
                                                htmlspecialchars($student[$col] ?? 'N/A') .
                                                '</td>';    
                                            $cols_displayed++;
                                        }
                                        ?>
                                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?php echo (int)$student['remaining_sessions']; ?></td>
                                        <td class="px-4 py-3 text-sm text-left space-x-1">
                                            <a href="edit_student.php?id=<?php echo $id_value; ?>&id_col=<?php echo $id_column; ?>" class="text-amber-600 hover:text-amber-800 transition">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="student.php?action=delete&id=<?php echo $id_value; ?>&id_col=<?php echo $id_column; ?>" class="text-red-600 hover:text-red-800 transition" onclick="return confirm('Are you sure you want to delete this student record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php if ((int)$student['remaining_sessions'] < 30): ?>
                                            <a href="reset_sessions.php?student_id=<?php echo urlencode($student[$id_column]); ?>&redirect=student.php" class="text-green-600 hover:text-green-900 transition" title="Reset Sessions to 30" onclick="return confirm('Are you sure you want to reset this student\'s sessions to 30?')">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <?php elseif ((int)$student['remaining_sessions'] >= 30): ?>
                                            <span class="text-gray-400 cursor-not-allowed" title="Student already has 30 sessions">
                                                <i class="fas fa-sync-alt"></i>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <!-- Pagination -->
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
                                        <!-- Previous Page Button - Enhanced -->
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
                                        
                                        <!-- Next Page Button - Enhanced -->
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
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        No student records found.
                                    </p>
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
    </script>
</body>
</html>