<?php
// Include database connection
require_once 'includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Set debug mode to false for production environment
$debug_mode = false; // Change to true only for development/debugging

// Initialize variables
$search_term = '';
$students = [];
$search_performed = false;
$debug_info = '';
$sql_query = '';
$error_message = ''; // Added for error handling

// Direct database structure check - only run in debug mode
if ($debug_mode) {
    $tables_in_db = [];
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        while($table_row = $tables_result->fetch_row()) {
            $tables_in_db[] = $table_row[0];
        }
        $debug_info .= "Tables in database: " . implode(", ", $tables_in_db) . ". ";
    }
} else {
    // In production, just get table names without logging them
    $tables_in_db = [];
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        while($table_row = $tables_result->fetch_row()) {
            $tables_in_db[] = $table_row[0];
        }
    }
}

// Process search when form is submitted
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search_term']);
    $search_performed = true;
    
    if (!empty($search_term)) {
        // Validate that search term only contains alphabetic characters and spaces
        if (preg_match('/[^a-zA-Z\s]/', $search_term)) {
            $error_message = "Please enter only alphabetic characters for student names. Numbers and special characters are not allowed.";
        } else {
            // Try each potential student table
            $potential_tables = ['users', 'students', 'student'];
            $table_name = '';
            
            foreach ($potential_tables as $table) {
                if (in_array($table, $tables_in_db)) {
                    $table_name = $table;
                    $debug_info .= "Using table: {$table_name}. ";
                    break;
                }
            }
            
            if (empty($table_name) && !empty($tables_in_db)) {
                // If no known student tables found, try the first table
                $table_name = $tables_in_db[0];
                $debug_info .= "No recognized student table found. Using first table: {$table_name}. ";
            }
            
            if (!empty($table_name)) {
                // Get the column structure of the selected table
                $columns = [];
                $col_result = $conn->query("SHOW COLUMNS FROM `{$table_name}`");
                if ($col_result) {
                    while($col = $col_result->fetch_assoc()) {
                        $columns[] = $col['Field'];
                    }
                    $debug_info .= "Columns in {$table_name}: " . implode(", ", $columns) . ". ";
                }
                
                // Define priority columns for searching (case insensitive)
                $priority_columns = [];
                $secondary_columns = [];
                
                // Columns to completely exclude from search
                $excluded_terms = ['password', 'pass', 'passwd', 'profile', 'picture', 'image', 'photo'];
                
                // Categorize columns by priority
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
                    if ($exclude) continue;
                    
                    // High priority columns for search
                    if (strpos($col_lower, 'name') !== false || 
                        strpos($col_lower, 'id') !== false || 
                        strpos($col_lower, 'email') !== false ||
                        strpos($col_lower, 'user') !== false) {
                        $priority_columns[] = $col;
                    } else {
                        $secondary_columns[] = $col;
                    }
                }
                
                // Build search conditions with priority columns first
                $search_conditions = [];
                $bind_values = [];
                $bind_types = "";
                
                // Add conditions for priority columns
                foreach ($priority_columns as $col) {
                    $search_conditions[] = "`{$col}` LIKE ?";
                    $bind_values[] = "%{$search_term}%";
                    $bind_types .= "s";
                }
                
                // Split the search term for multi-word searches
                $term_parts = explode(" ", $search_term);
                if (count($term_parts) > 1) {
                    // For each part of the name, search in appropriate columns
                    $first_name_cols = [];
                    $last_name_cols = [];
                    
                    foreach ($priority_columns as $col) {
                        $col_lower = strtolower($col);
                        if (strpos($col_lower, 'first') !== false || 
                            strpos($col_lower, 'fname') !== false) {
                            $first_name_cols[] = $col;
                        } 
                        else if (strpos($col_lower, 'last') !== false || 
                            strpos($col_lower, 'lname') !== false) {
                            $last_name_cols[] = $col;
                        }
                    }
                    
                    // Try matching different parts of the name with appropriate columns
                    if (count($first_name_cols) > 0 && count($last_name_cols) > 0) {
                        foreach ($term_parts as $i => $part) {
                            if (strlen($part) > 1) {
                                // Try first part as first name and second part as last name
                                if ($i == 0) {
                                    foreach ($first_name_cols as $col) {
                                        $search_conditions[] = "`{$col}` LIKE ?";
                                        $bind_values[] = "%{$part}%";
                                        $bind_types .= "s";
                                    }
                                } else {
                                    foreach ($last_name_cols as $col) {
                                        $search_conditions[] = "`{$col}` LIKE ?";
                                        $bind_values[] = "%{$part}%";
                                        $bind_types .= "s";
                                    }
                                }
                            }
                        }
                        
                        // Also try the reverse (last name first, first name second)
                        if (count($term_parts) >= 2) {
                            foreach ($last_name_cols as $col) {
                                $search_conditions[] = "`{$col}` LIKE ?";
                                $bind_values[] = "%{$term_parts[0]}%";
                                $bind_types .= "s";
                            }
                            foreach ($first_name_cols as $col) {
                                $search_conditions[] = "`{$col}` LIKE ?";
                                $bind_values[] = "%{$term_parts[1]}%";
                                $bind_types .= "s";
                            }
                        }
                    }
                }
                
                // Create the query
                $query = "SELECT * FROM `{$table_name}` WHERE " . implode(" OR ", $search_conditions);
                $sql_query = $query; // Save for debug display
                
                // Create a prepared statement
                $stmt = $conn->prepare($query);
                
                if ($stmt) {
                    // Create array of references for bind_param
                    $bind_params = array();
                    $bind_params[] = &$bind_types;
                    
                    foreach ($bind_values as $key => $value) {
                        $bind_params[] = &$bind_values[$key];
                    }
                    
                    // Bind parameters and execute
                    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $students[] = $row;
                    }
                    
                    $stmt->close();
                    
                    $debug_info .= "Search complete. Found " . count($students) . " results.";
                    $debug_info .= " First few bind values: " . implode(", ", array_slice($bind_values, 0, 3)) . "...";
                } else {
                    $debug_info .= "Statement preparation failed: " . $conn->error;
                }
            } else {
                $debug_info .= "No tables found in database to search.";
            }
        }
    }
}

// Get admin username for display if available
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Check for sit-in messages from session
$sitin_message = '';
$sitin_status = '';

if (isset($_SESSION['sitin_message']) && isset($_SESSION['sitin_status'])) {
    $sitin_message = $_SESSION['sitin_message'];
    $sitin_status = $_SESSION['sitin_status'];
    
    // Clear the message after retrieving it
    unset($_SESSION['sitin_message']);
    unset($_SESSION['sitin_status']);
}

// Get available labs - with table existence check
$labs = [];
$table_check = $conn->query("SHOW TABLES LIKE 'labs'");
if ($table_check->num_rows > 0) {
    // Labs table exists, fetch labs
    $labs_query = "SELECT * FROM labs ORDER BY lab_name";
    $labs_result = $conn->query($labs_query);
    if ($labs_result && $labs_result->num_rows > 0) {
        while ($lab = $labs_result->fetch_assoc()) {
            $labs[] = $lab;
        }
    }
}

// If no labs found (table doesn't exist or is empty), use default values
if (empty($labs)) {
    $labs = [
        ['lab_id' => 1, 'lab_name' => 'Laboratory 524'],
        ['lab_id' => 2, 'lab_name' => 'Laboratory 526'],
        ['lab_id' => 3, 'lab_name' => 'Laboratory 528'],
        ['lab_id' => 4, 'lab_name' => 'Laboratory 530'],
        ['lab_id' => 5, 'lab_name' => 'Laboratory 542'],
        ['lab_id' => 6, 'lab_name' => 'Mac Laboratory']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students | Sit-In Management System</title>
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
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            overflow: auto;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        /* Alert styles */
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        
        .alert-icon {
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="admin.php" class="text-xl font-bold">Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Home</a>
                        <a href="search_student.php" class="px-3 py-2 bg-primary-800 rounded transition">Search</a>
                        <a href="student.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Students</a>
                        <div class="relative group">
                            <button class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                                Sit-In <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">View Sit-In Records</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Sit-In Reports</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Feedback Reports</a>
                            </div>
                        </div>
                        <a href="#" class="px-3 py-2 rounded hover:bg-primary-800 transition">Reservation</a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                                <span class="font-medium text-sm"><?php echo substr($admin_username, 0, 1); ?></span>
                            </div>
                            <span class="hidden sm:inline-block"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg overflow-hidden z-20">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Profile
                                </a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Settings
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
        <a href="admin.php" class="block px-4 py-2 text-white hover:bg-primary-900">Home</a>
        <a href="search_student.php" class="block px-4 py-2 text-white bg-primary-900">Search</a>
        <a href="#" class="block px-4 py-2 text-white hover:bg-primary-900">Students</a>
        <button class="mobile-dropdown-button w-full text-left px-4 py-2 text-white hover:bg-primary-900 flex justify-between items-center">
            Sit-In <i class="fas fa-chevron-down ml-1"></i>
        </button>
        <div class="mobile-dropdown-content hidden bg-primary-900 px-4 py-2">
            <a href="#" class="block py-1 text-white hover:text-gray-300">View Sit-In Records</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Sit-In Reports</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Feedback Reports</a>
        </div>
        <a href="#" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Search Students Section -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold">Search Students</h2>
                </div>
                
                <div class="p-6">
                    <!-- Display sit-in messages -->
                    <?php if (!empty($sitin_message)): ?>
                        <div class="alert <?php echo $sitin_status === 'success' ? 'alert-success' : 'alert-error'; ?> mb-4">
                            <div class="alert-icon">
                                <?php if ($sitin_status === 'success'): ?>
                                    <i class="fas fa-check-circle text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-lg"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($sitin_status === 'success'): ?>
                                    <p><strong>Success!</strong> Sit-in registration successful! Remaining sessions have been updated.</p>
                                <?php else: ?>
                                    <p><?php echo htmlspecialchars($sitin_message); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Search Form -->
                    <form method="GET" action="" class="mb-6">
                        <div class="flex flex-col md:flex-row gap-4">
                            <input type="text" name="search_term" placeholder="Enter student name" 
                                   class="flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                            <button type="submit" name="search" 
                                    class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                        </div>
                        <?php if (!empty($error_message)): ?>
                            <div class="mt-3 text-red-600 text-sm">
                                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-2 text-gray-500 text-sm">
                            <i class="fas fa-info-circle mr-1"></i> Search by student name only. Use only letters and spaces.
                        </div>
                    </form>
                    
                    <?php if ($search_performed && empty($error_message)): ?>
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-800">Search Results</h3>
                                <?php if (!empty($search_term)): ?>
                                    <span class="text-sm text-gray-500">Showing results for: "<?php echo htmlspecialchars($search_term); ?>"</span>
                                <?php endif; ?>
                            </div>
                            
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
                                        <?php if (!empty($sql_query)): ?>
                                        <p class="text-xs text-blue-600 mt-1 font-mono">
                                            Query: <?php echo htmlspecialchars($sql_query); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (count($students) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Name</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Course</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Year Level</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Email</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <?php foreach ($students as $student): ?>
                                                <?php
                                                // Format name as LASTNAME, FIRSTNAME MI.
                                                $name = '';
                                                if (isset($student['LASTNAME']) && isset($student['FIRSTNAME'])) {
                                                    $name = $student['LASTNAME'] . ', ' . $student['FIRSTNAME'];
                                                    if (!empty($student['MIDDLENAME'])) {
                                                        $middle_initial = substr($student['MIDDLENAME'], 0, 1);
                                                        $name .= ' ' . $middle_initial . '.';
                                                    }
                                                }
                                                $student_id = isset($student['IDNO']) ? $student['IDNO'] : ($student['USER_ID'] ?? 'N/A');
                                                ?>
                                                <tr class="hover:bg-gray-50 cursor-pointer student-row" 
                                                    data-student-id="<?php echo htmlspecialchars($student_id); ?>"
                                                    data-student-name="<?php echo htmlspecialchars($name); ?>">
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($student_id); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($student['COURSE'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($student['YEARLEVEL'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($student['EMAIL'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <button class="px-3 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 sit-in-btn"
                                                                data-student-id="<?php echo htmlspecialchars($student_id); ?>"
                                                                data-student-name="<?php echo htmlspecialchars($name); ?>">
                                                            Sit In
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-circle text-yellow-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                User not registered in the system.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sit-In Form Modal -->
    <div id="sitInModal" class="modal">
        <div class="modal-content p-6 mx-4">
            <div class="mb-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Register Sit-In</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="sitInForm" action="process_sitin.php" method="POST" class="space-y-4">
                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700">ID Number</label>
                    <input type="text" id="student_id" name="student_id" readonly
                           class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div>
                    <label for="student_name" class="block text-sm font-medium text-gray-700">Student Name</label>
                    <input type="text" id="student_name" name="student_name" readonly
                           class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div>
                    <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                    <select id="purpose" name="purpose" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
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
                    <label for="other_purpose" class="block text-sm font-medium text-gray-700">Specify Purpose</label>
                    <input type="text" id="other_purpose" name="other_purpose" 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div>
                    <label for="lab" class="block text-sm font-medium text-gray-700">Lab</label>
                    <select id="lab" name="lab_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="" selected disabled>Select lab</option>
                        <?php foreach ($labs as $lab): ?>
                        <option value="<?php echo htmlspecialchars($lab['lab_id']); ?>"><?php echo htmlspecialchars($lab['lab_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="remaining_sessions" class="block text-sm font-medium text-gray-700">Remaining Sessions</label>
                    <input type="number" id="remaining_sessions" name="remaining_sessions" min="1" max="10" value="1" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    <p class="text-sm text-gray-500 mt-1">Enter the number of sessions the student is allowed.</p>
                </div>
                
                <div class="flex space-x-3 pt-4">
                    <button type="button" id="cancelSitIn" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition">
                        Close
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition">
                        Register Sit In
                    </button>
                </div>
            </form>
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
        
        // Sit-In Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('sitInModal');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelSitIn');
            const purposeSelect = document.getElementById('purpose');
            const othersContainer = document.getElementById('othersContainer');
            
            // Open the modal when clicking on a student row or sit-in button
            document.querySelectorAll('.student-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Prevent triggering when clicking on the button column
                    if (!e.target.closest('.sit-in-btn')) {
                        openSitInModal(this.dataset.studentId, this.dataset.studentName);
                    }
                });
            });
            
            // Open modal on sit-in button click
            document.querySelectorAll('.sit-in-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openSitInModal(this.dataset.studentId, this.dataset.studentName);
                });
            });
            
            // Close modal functions
            closeBtn.addEventListener('click', closeSitInModal);
            cancelBtn.addEventListener('click', closeSitInModal);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeSitInModal();
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
            
            function openSitInModal(studentId, studentName) {
                document.getElementById('student_id').value = studentId;
                document.getElementById('student_name').value = studentName;
                modal.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
            }
            
            function closeSitInModal() {
                modal.classList.remove('show');
                document.body.style.overflow = ''; // Re-enable scrolling
                // Reset form
                document.getElementById('sitInForm').reset();
                othersContainer.classList.add('hidden');
            }

            // Add auto-hide for alert messages after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 1s ease';
                        setTimeout(() => {
                            alert.remove();
                        }, 1000);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>
