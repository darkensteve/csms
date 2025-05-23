<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: ../auth/login_admin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];

// Database connection
$conn = mysqli_connect("localhost", "root", "", "csms");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// Create upload directory if it doesn't exist
$upload_dir = "../../uploads/resources/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Initialize variables
$title = $description = $resource_link = '';
$category_id = 0;
$resource_type = 'link';
$message = '';
$message_type = '';

// Check if tables exist, if not redirect to create_tables.php
$result = $conn->query("SHOW TABLES LIKE 'lab_resource_categories'");
if ($result->num_rows == 0) {
    header("Location: create_tables.php");
    exit;
}

// Process form submission for adding a new resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category_id = intval($_POST['category_id']);
    $resource_type = mysqli_real_escape_string($conn, $_POST['resource_type']);
    
    // Validate input
    if (empty($title) || $category_id <= 0) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } else {
        $resource_link = null;
        $file_path = null;
        
        if ($resource_type === 'link') {
            // Handle links
            $resource_link = mysqli_real_escape_string($conn, $_POST['resource_link']);
            if (empty($resource_link)) {
                $message = "Please provide a resource link.";
                $message_type = "error";
            }
        } else {
            // Handle file uploads
            if (!empty($_FILES['resource_file']['name'])) {
                $file_name = basename($_FILES['resource_file']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $new_file_name = uniqid() . '_' . $file_name;
                $target_file = $upload_dir . $new_file_name;
                
                // Check file size (max 10MB)
                if ($_FILES['resource_file']['size'] > 10000000) {
                    $message = "File is too large. Maximum size is 10MB.";
                    $message_type = "error";
                } else {
                    if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_file)) {
                        $file_path = "uploads/resources/" . $new_file_name;
                    } else {
                        $message = "Failed to upload file.";
                        $message_type = "error";
                    }
                }
            } else {
                $message = "Please select a file to upload.";
                $message_type = "error";
            }
        }
        
        if (empty($message)) {
            // Insert into database
            $query = "INSERT INTO lab_resources 
                     (title, description, category_id, resource_type, resource_link, file_path, admin_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssisssi", $title, $description, $category_id, $resource_type, $resource_link, $file_path, $admin_id);
            
            if ($stmt->execute()) {
                $message = "Resource added successfully!";
                $message_type = "success";
                // Reset form
                $title = $description = $resource_link = '';
                $category_id = 0;
                $resource_type = 'link';
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = "error";
            }
        }
    }
}

// Delete resource
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $resource_id = intval($_GET['delete']);
    
    // First, check if there's a file to delete
    $file_query = "SELECT file_path FROM lab_resources WHERE resource_id = ?";
    $stmt = $conn->prepare($file_query);
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['file_path'])) {
            $file_to_delete = "../../" . $row['file_path'];
            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }
        }
    }
    
    // Delete from database
    $delete_query = "DELETE FROM lab_resources WHERE resource_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $resource_id);
    
    if ($stmt->execute()) {
        $message = "Resource deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting resource: " . $stmt->error;
        $message_type = "error";
    }
}

// Get categories for dropdown
$categories = [];
$category_query = "SELECT * FROM lab_resource_categories ORDER BY category_name";
$category_result = $conn->query($category_query);
if ($category_result->num_rows > 0) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get resources with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filter by category if specified
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$where_clause = $category_filter > 0 ? "WHERE r.category_id = $category_filter" : "";

// Count total resources for pagination
$count_query = "SELECT COUNT(*) as total FROM lab_resources r $where_clause";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);

// Get resources with category name and admin username
$resources_query = "SELECT r.*, c.category_name, a.username as admin_username 
                   FROM lab_resources r 
                   LEFT JOIN lab_resource_categories c ON r.category_id = c.category_id 
                   LEFT JOIN admin a ON r.admin_id = a.admin_id 
                   $where_clause
                   ORDER BY r.created_at DESC 
                   LIMIT $offset, $items_per_page";
$resources_result = $conn->query($resources_query);
$resources = [];

if ($resources_result->num_rows > 0) {
    while ($row = $resources_result->fetch_assoc()) {
        $resources[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Resources Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-bg {
            background-color: #0369a1; /* Match the blue color in the image */
            color: white;
        }
        .file-input-label {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-input-label:hover {
            background-color: #e5e7eb;
        }
        
        /* Navbar styles */
        .nav-active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Navigation buttons */
        .nav-button {
            transition: all 0.2s ease;
            position: relative;
        }
        
        .nav-button:hover {
            background-color: rgba(7, 89, 133, 0.8);
        }
        
        /* TailwindCSS primary colors for consistency */
        .bg-primary-700 {
            background-color: #0369a1;
        }
        .bg-primary-800 {
            background-color: #075985;
        }
        .bg-primary-900 {
            background-color: #0c4a6e;
        }
        .bg-primary-950 {
            background-color: #082f49;
        }
        .hover\:bg-primary-800:hover {
            background-color: #075985;
        }
        .hover\:bg-primary-900:hover {
            background-color: #0c4a6e;
        }
        
        /* Blue theme for buttons and UI elements */
        .btn-primary {
            background-color: #0284c7;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
        .text-theme {
            color: #0284c7;
        }
        .bg-theme-50 {
            background-color: #f0f9ff;
        }
        .text-theme-800 {
            color: #075985;
        }
        .border-theme {
            border-color: #0284c7;
        }
        
        /* Dropdown menu styles */
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 10;
            min-width: 12rem;
            padding: 0.5rem 0;
            margin-top: 0.5rem; /* Add slight margin to prevent accidental mouseleave */
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(229, 231, 235, 1);
            top: 100%; /* Position right below the button */
            left: 0;
        }
        
        /* Create an accessible hover area between button and dropdown */
        .dropdown-container {
            position: relative;
        }
        
        /* Add this pseudo-element to create an invisible bridge */
        .dropdown-container:after {
            content: '';
            position: absolute;
            height: 15px; /* Height of the bridge */
            width: 100%;
            bottom: -15px; /* Position it just below the button */
            left: 0;
            z-index: 5; /* Below the menu but above other elements */
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
<body class="bg-gray-50 min-h-screen">
    <!-- Main Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg mb-6">
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
                        
                        <a href="index.php" class="nav-button px-3 py-2 bg-primary-800 rounded transition flex items-center">
                            <i class="fas fa-book mr-1"></i> Lab Resources
                        </a>
                        <a href="../sitin/feedback_reports.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                        <a href="../reservation/reservation.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-calendar-check mr-1"></i> Reservation
                        </a>
                        <a href="../lab_schedule/index.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-laptop mr-1"></i> Lab Schedule
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
                            <span class="hidden sm:inline-block"><?= htmlspecialchars($admin_username) ?></span>
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
        
        <a href="index.php" class="block px-4 py-2 text-white bg-primary-900">
            <i class="fas fa-book mr-2"></i> Lab Resources
        </a>
        <a href="../sitin/feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
        <a href="../reservation/reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-calendar-check mr-2"></i> Reservation
        </a>
        <a href="../lab_schedule/index.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-laptop mr-2"></i> Lab Schedule
        </a>
    </div>
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
        <!-- Page Title -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Lab Resources Management</h1>
                <a href="../admin.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Notification Message -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    </div>
                    <div class="ml-3">
                        <p><?= $message ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add Resource Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-blue-800 mb-4">Add New Resource</h2>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($description) ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <select id="category_id" name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>" <?= $category_id == $category['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resource Type *</label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="resource_type" value="link" <?= $resource_type === 'link' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500" onclick="toggleResourceType('link')">
                                    <span class="ml-2">Link</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="resource_type" value="file" <?= $resource_type === 'file' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500" onclick="toggleResourceType('file')">
                                    <span class="ml-2">File</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="link-input" class="mb-4 <?= $resource_type === 'link' ? '' : 'hidden' ?>">
                            <label for="resource_link" class="block text-sm font-medium text-gray-700 mb-1">Resource Link *</label>
                            <input type="url" id="resource_link" name="resource_link" value="<?= htmlspecialchars($resource_link) ?>" placeholder="https://example.com/resource" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Enter a valid URL (including https://)</p>
                        </div>
                        
                        <div id="file-input" class="mb-4 <?= $resource_type === 'file' ? '' : 'hidden' ?>">
                            <label for="resource_file" class="block text-sm font-medium text-gray-700 mb-1">Upload File *</label>
                            <label class="file-input-label w-full">
                                <span id="file-name">Choose a file</span>
                                <input type="file" id="resource_file" name="resource_file" class="hidden" onchange="updateFileName()">
                            </label>
                            <p class="mt-1 text-xs text-gray-500">Max file size: 10MB</p>
                        </div>
                        
                        <button type="submit" name="add_resource" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                            <i class="fas fa-plus-circle mr-2"></i> Add Resource
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Resources List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <h2 class="text-xl font-semibold text-blue-800 mb-3 md:mb-0">Lab Resources</h2>
                        
                        <!-- Filter by category -->
                        <div class="w-full md:w-auto">
                            <form action="" method="GET" class="flex">
                                <select name="category" class="px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>" <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-md hover:bg-blue-700">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Resources Table -->
                    <?php if (count($resources) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($resources as $resource): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($resource['title']) ?></div>
                                                <div class="text-sm text-gray-500 truncate max-w-xs"><?= htmlspecialchars($resource['description']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?= htmlspecialchars($resource['category_name']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php if ($resource['resource_type'] === 'link'): ?>
                                                    <span class="text-blue-600"><i class="fas fa-link mr-1"></i> Link</span>
                                                <?php else: ?>
                                                    <span class="text-green-600"><i class="fas fa-file mr-1"></i> File</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M d, Y', strtotime($resource['created_at'])) ?>
                                                <div class="text-xs text-gray-400">by <?= htmlspecialchars($resource['admin_username']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <?php if ($resource['resource_type'] === 'link' && !empty($resource['resource_link'])): ?>
                                                        <a href="<?= htmlspecialchars($resource['resource_link']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="Visit Link">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php elseif ($resource['resource_type'] === 'file' && !empty($resource['file_path'])): ?>
                                                        <a href="../../<?= htmlspecialchars($resource['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="Download File">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="edit.php?id=<?= $resource['resource_id'] ?>" class="text-blue-600 hover:text-blue-900" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <a href="#" onclick="confirmDelete(<?= $resource['resource_id'] ?>, '<?= htmlspecialchars(addslashes($resource['title'])) ?>')" class="text-red-600 hover:text-red-900" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-4 flex justify-center">
                                <nav class="inline-flex rounded-md shadow">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" class="px-3 py-2 bg-white text-gray-500 hover:bg-gray-50 rounded-l-md border border-gray-300">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?page=<?= $i ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" class="px-3 py-2 <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' ?> border-t border-b border-gray-300">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?= $page + 1 ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" class="px-3 py-2 bg-white text-gray-500 hover:bg-gray-50 rounded-r-md border border-gray-300">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-8">
                            <img src="../../assets/empty.svg" alt="No resources found" class="w-32 h-32 mx-auto mb-4 opacity-50">
                            <h3 class="text-lg font-medium text-gray-600 mb-1">No resources found</h3>
                            <p class="text-gray-500 mb-4">
                                <?= $category_filter > 0 ? 'No resources found in this category.' : 'Start by adding your first resource!' ?>
                            </p>
                            <?php if ($category_filter > 0): ?>
                                <a href="index.php" class="text-blue-600 font-medium hover:text-blue-800">
                                    <i class="fas fa-times-circle mr-1"></i> Clear filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>
    
    <script>
        function toggleResourceType(type) {
            if (type === 'link') {
                document.getElementById('link-input').classList.remove('hidden');
                document.getElementById('file-input').classList.add('hidden');
            } else {
                document.getElementById('link-input').classList.add('hidden');
                document.getElementById('file-input').classList.remove('hidden');
            }
        }
        
        function updateFileName() {
            const fileInput = document.getElementById('resource_file');
            const fileName = document.getElementById('file-name');
            
            if (fileInput.files.length > 0) {
                fileName.textContent = fileInput.files[0].name;
            } else {
                fileName.textContent = 'Choose a file';
            }
        }
        
        function confirmDelete(id, title) {
            if (confirm(`Are you sure you want to delete "${title}"?`)) {
                window.location.href = `index.php?delete=${id}`;
            }
        }
        
        // Toggle mobile menu
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
        
        // Toggle user dropdown
        function toggleUserDropdown() {
            const userMenu = document.getElementById('userMenu');
            if (userMenu) {
                userMenu.classList.toggle('hidden');
            }
        }
        
        // Mobile Sit-In dropdown toggle
        const mobileSitInButton = document.getElementById('mobile-sitin-dropdown');
        const mobileSitInMenu = document.getElementById('mobile-sitin-menu');
        
        if (mobileSitInButton && mobileSitInMenu) {
            mobileSitInButton.addEventListener('click', function() {
                mobileSitInMenu.classList.toggle('hidden');
            });
        }
        
        // Sit-In dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sitInDropdown = document.getElementById('sitInDropdown');
            const sitInMenuButton = document.getElementById('sitInMenuButton');
            const sitInDropdownMenu = document.getElementById('sitInDropdownMenu');
            
            if (sitInDropdown && sitInMenuButton && sitInDropdownMenu) {
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
        });
        
        // Close dropdowns when clicking outside
        window.addEventListener('click', function(e) {
            const sitInDropdown = document.getElementById('sitInDropdown');
            const sitInDropdownMenu = document.getElementById('sitInDropdownMenu');
            const userDropdown = document.getElementById('userDropdown');
            const userMenu = document.getElementById('userMenu');
            
            if (sitInDropdown && sitInDropdownMenu && !sitInDropdown.contains(e.target)) {
                sitInDropdownMenu.classList.remove('show');
            }
            
            if (userDropdown && userMenu && !userDropdown.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?> 