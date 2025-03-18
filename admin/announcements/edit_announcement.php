<?php
session_start();

// Check if user is not logged in as admin
if(!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../auth/login_admin.php");
    exit;
}

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

// Initialize variables
$announcement = null;
$title = '';
$content = '';
$announcement_id = '';

// Check if form was submitted (update announcement)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $announcement_id = mysqli_real_escape_string($conn, $_POST['id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    // Update announcement
    $query = "UPDATE announcements SET title = '$title', content = '$content' WHERE id = '$announcement_id'";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['announcement_success'] = "Announcement updated successfully!";
        header("Location: ../admin.php");
        exit;
    } else {
        $error_message = "Error updating announcement: " . mysqli_error($conn);
    }
} 
// Get announcement details for editing
else if (isset($_GET['id'])) {
    $announcement_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Fetch announcement
    $query = "SELECT * FROM announcements WHERE id = '$announcement_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $announcement = mysqli_fetch_assoc($result);
        $title = $announcement['title'];
        $content = $announcement['content'];
    } else {
        $_SESSION['announcement_error'] = "Announcement not found.";
        header("Location: ../admin.php");
        exit;
    }
} else {
    header("Location: ../admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Announcement | Admin Dashboard</title>
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
    </style>
</head>
<body class="font-sans bg-gray-50 min-h-screen flex flex-col">
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
                    
                    <a href="../admin.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow-md">
            <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-xl">
                <h2 class="text-xl font-semibold">Edit Announcement</h2>
            </div>
            <div class="p-6">
                <?php if (isset($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error_message; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($announcement_id); ?>">
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Announcement Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Announcement Content</label>
                        <textarea id="content" name="content" rows="6" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"><?php echo htmlspecialchars($content); ?></textarea>
                    </div>
                    
                    <div class="flex space-x-4 pt-4">
                        <a href="../admin.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-3">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System - Admin Dashboard. All rights reserved.
        </div>
    </footer>
    
    <script>
        // Toggle mobile menu button - Added for consistency
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobile-menu');
                if (mobileMenu) {
                    mobileMenu.classList.toggle('hidden');
                }
            });
        }
    </script>
</body>
</html>
