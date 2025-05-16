<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: ../auth/login_admin.php");
    exit;
}

$admin_username = $_SESSION['admin_username'];

// Database connection
$conn = mysqli_connect("localhost", "root", "", "csms");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// Create lab_resource_categories table
$categories_table = "CREATE TABLE IF NOT EXISTS `lab_resource_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($categories_table) === TRUE) {
    $message_categories = "Table lab_resource_categories created successfully.";
} else {
    $message_categories = "Error creating table lab_resource_categories: " . $conn->error;
}

// Create lab_resources table
$resources_table = "CREATE TABLE IF NOT EXISTS `lab_resources` (
  `resource_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT(11) NOT NULL,
  `resource_type` ENUM('link', 'file') NOT NULL DEFAULT 'link',
  `resource_link` VARCHAR(512),
  `file_path` VARCHAR(512),
  `admin_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resource_id`),
  FOREIGN KEY (`category_id`) REFERENCES `lab_resource_categories`(`category_id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($resources_table) === TRUE) {
    $message_resources = "Table lab_resources created successfully.";
} else {
    $message_resources = "Error creating table lab_resources: " . $conn->error;
}

// Insert default categories if they don't exist
$default_categories = [
    'Programming',
    'Database',
    'Networking',
    'Web Development',
    'Software Engineering',
    'Cybersecurity',
    'Data Science',
    'Other'
];

$categories_added = [];
foreach ($default_categories as $category) {
    $check_query = "SELECT * FROM lab_resource_categories WHERE category_name = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $insert_query = "INSERT INTO lab_resource_categories (category_name) VALUES (?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("s", $category);
        if ($insert_stmt->execute()) {
            $categories_added[] = $category;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Lab Resources Tables - SitIn System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-bg {
            background-color: #0369a1; /* Match the blue color in the image */
            color: white;
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
                <h1 class="text-2xl font-bold text-gray-800">Lab Resources Setup</h1>
                <a href="../admin.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-blue-800 mb-6">Database Setup Results</h2>
            
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-2">Tables Creation</h3>
                
                <div class="p-3 mb-2 rounded-md <?= strpos($message_categories, 'Error') !== false ? 'bg-red-100' : 'bg-green-100' ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?= strpos($message_categories, 'Error') !== false ? 'fa-times-circle text-red-500' : 'fa-check-circle text-green-500' ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="<?= strpos($message_categories, 'Error') !== false ? 'text-red-700' : 'text-green-700' ?>"><?= $message_categories ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="p-3 mb-2 rounded-md <?= strpos($message_resources, 'Error') !== false ? 'bg-red-100' : 'bg-green-100' ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?= strpos($message_resources, 'Error') !== false ? 'fa-times-circle text-red-500' : 'fa-check-circle text-green-500' ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="<?= strpos($message_resources, 'Error') !== false ? 'text-red-700' : 'text-green-700' ?>"><?= $message_resources ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($categories_added)): ?>
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-2">Default Categories Added</h3>
                <div class="p-3 bg-blue-50 rounded-md">
                    <ul class="list-disc pl-5 text-blue-800">
                        <?php foreach ($categories_added as $category): ?>
                            <li><?= htmlspecialchars($category) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-8 text-center">
                <p class="text-green-700 font-medium mb-4">Setup complete! You can now start using Lab Resources.</p>
                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-right mr-2"></i> Go to Lab Resources
                </a>
            </div>
        </div>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>
</body>
</html>

<?php
// Close connection
$conn->close();
?> 