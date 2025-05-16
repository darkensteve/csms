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

// Initialize variables
$resource_id = 0;
$title = $description = $resource_link = '';
$category_id = 0;
$resource_type = 'link';
$file_path = '';
$message = '';
$message_type = '';

// Check if resource ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$resource_id = intval($_GET['id']);

// Get resource details
$query = "SELECT * FROM lab_resources WHERE resource_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $resource_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$resource = $result->fetch_assoc();
$title = $resource['title'];
$description = $resource['description'];
$category_id = $resource['category_id'];
$resource_type = $resource['resource_type'];
$resource_link = $resource['resource_link'];
$file_path = $resource['file_path'];
$original_file_path = $file_path;

// Process form submission for updating resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resource'])) {
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
        
        if ($resource_type === 'link') {
            // Handle links
            $resource_link = mysqli_real_escape_string($conn, $_POST['resource_link']);
            if (empty($resource_link)) {
                $message = "Please provide a resource link.";
                $message_type = "error";
            }
            
            // If switching from file to link, clear file path
            if ($resource['resource_type'] === 'file') {
                $file_path = null;
            }
        } else {
            // Handle file uploads
            if (!empty($_FILES['resource_file']['name'])) {
                $upload_dir = "../../uploads/resources/";
                $file_name = basename($_FILES['resource_file']['name']);
                
                // Generate unique filename
                $new_file_name = uniqid() . '_' . $file_name;
                $target_file = $upload_dir . $new_file_name;
                
                // Check file size (max 10MB)
                if ($_FILES['resource_file']['size'] > 10000000) {
                    $message = "File is too large. Maximum size is 10MB.";
                    $message_type = "error";
                } else {
                    if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_file)) {
                        // Delete old file if exists
                        if (!empty($original_file_path)) {
                            $old_file = "../../" . $original_file_path;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        $file_path = "uploads/resources/" . $new_file_name;
                        $resource_link = null; // Clear link when uploading file
                    } else {
                        $message = "Failed to upload file.";
                        $message_type = "error";
                    }
                }
            } else {
                // If no new file uploaded, keep existing file if switching from link to file
                if ($resource['resource_type'] === 'link' && empty($original_file_path)) {
                    $message = "Please select a file to upload.";
                    $message_type = "error";
                } else {
                    // Keep original file path
                    $file_path = $original_file_path;
                    $resource_link = null; // Clear link when maintaining file
                }
            }
        }
        
        if (empty($message)) {
            // Update database
            $query = "UPDATE lab_resources 
                     SET title = ?, description = ?, category_id = ?, 
                     resource_type = ?, resource_link = ?, file_path = ? 
                     WHERE resource_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssisssi", $title, $description, $category_id, $resource_type, $resource_link, $file_path, $resource_id);
            
            if ($stmt->execute()) {
                $message = "Resource updated successfully!";
                $message_type = "success";
                
                // Reload the resource data after update
                $query = "SELECT * FROM lab_resources WHERE resource_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $resource_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $resource = $result->fetch_assoc();
                
                $title = $resource['title'];
                $description = $resource['description'];
                $category_id = $resource['category_id'];
                $resource_type = $resource['resource_type'];
                $resource_link = $resource['resource_link'];
                $file_path = $resource['file_path'];
                $original_file_path = $file_path;
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = "error";
            }
        }
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

// Get the current file name if it exists
$current_file_name = '';
if (!empty($file_path)) {
    $current_file_name = basename($file_path);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - Lab Resources Management</title>
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
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: white;
            transition: all 0.2s;
        }
        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Main Navigation Bar -->
    <header class="header-bg text-white shadow-md mb-6">
        <div class="container mx-auto">
            <div class="flex justify-between items-center px-4 py-3">
                <h1 class="text-xl font-bold">Dashboard</h1>
                <div class="flex items-center space-x-2">
                    <a href="../admin.php" class="flex items-center space-x-1 bg-white/10 hover:bg-white/20 px-3 py-2 rounded">
                        <span><?= htmlspecialchars($admin_username) ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </a>
                </div>
            </div>
            <nav class="flex items-center justify-between flex-wrap">
                <div class="flex items-center justify-start overflow-x-auto w-full py-2">
                    <a href="../admin.php" class="nav-item">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                    <a href="../students/search_student.php" class="nav-item">
                        <i class="fas fa-search mr-2"></i> Search
                    </a>
                    <a href="../students/student.php" class="nav-item">
                        <i class="fas fa-users mr-2"></i> Students
                    </a>
                    <a href="../sitin/current_sitin.php" class="nav-item">
                        <i class="fas fa-user-check mr-2"></i> Sit-In
                    </a>
                    <a href="index.php" class="nav-item nav-active">
                        <i class="fas fa-book mr-2"></i> Lab Resources
                    </a>
                    <a href="../sitin/feedback_reports.php" class="nav-item">
                        <i class="fas fa-comment mr-2"></i> Feedback
                    </a>
                    <a href="../reservation/reservation.php" class="nav-item">
                        <i class="fas fa-calendar-check mr-2"></i> Reservation
                    </a>
                    <a href="../lab_schedule/index.php" class="nav-item">
                        <i class="fas fa-laptop mr-2"></i> Lab Schedule
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <div class="container mx-auto px-4 pb-12">
        <!-- Page Title -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Edit Resource</h1>
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Resources
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
        
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-blue-800 mb-4">Edit Resource</h2>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div>
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
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($description) ?></textarea>
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
                        <input type="url" id="resource_link" name="resource_link" value="<?= htmlspecialchars($resource_link ?? '') ?>" placeholder="https://example.com/resource" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Enter a valid URL (including https://)</p>
                    </div>
                    
                    <div id="file-input" class="mb-4 <?= $resource_type === 'file' ? '' : 'hidden' ?>">
                        <label for="resource_file" class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
                        
                        <?php if (!empty($file_path)): ?>
                            <div class="flex items-center mb-2 p-2 bg-gray-50 rounded-md">
                                <i class="fas fa-file text-blue-600 mr-2"></i>
                                <span class="text-sm"><?= htmlspecialchars($current_file_name) ?></span>
                                <a href="../../<?= htmlspecialchars($file_path) ?>" target="_blank" class="ml-auto text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                            <p class="mb-2 text-xs text-gray-500">Upload a new file to replace the current one (optional)</p>
                        <?php endif; ?>
                        
                        <label class="file-input-label w-full">
                            <span id="file-name"><?= empty($file_path) ? 'Choose a file' : 'Choose a new file (optional)' ?></span>
                            <input type="file" id="resource_file" name="resource_file" class="hidden" onchange="updateFileName()">
                        </label>
                        <p class="mt-1 text-xs text-gray-500">Max file size: 10MB</p>
                    </div>
                    
                    <div class="flex items-center justify-between mt-6">
                        <a href="index.php" class="bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" name="update_resource" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                            <i class="fas fa-save mr-2"></i> Update Resource
                        </button>
                    </div>
                </form>
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
                fileName.textContent = <?= empty($file_path) ? "'Choose a file'" : "'Choose a new file (optional)'" ?>;
            }
        }
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?> 