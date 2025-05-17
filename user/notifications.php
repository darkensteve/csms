<?php
// Start the session at the beginning
session_start();

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Get the logged-in user's ID
$loggedInUserId = $_SESSION['id'];

// Include notification functions
require_once '../includes/notification_functions.php';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle marking all as read
if (isset($_POST['mark_all_read'])) {
    mark_all_notifications_read($loggedInUserId, 'user');
    
    // Redirect to refresh page without the POST data
    header("Location: notifications.php?action=marked_read");
    exit();
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $loggedInUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get all notifications for this user (no limit)
$notifications = get_notifications($loggedInUserId, 'user', false, 100);

// Process notifications to add additional details
$processed_notifications = [];
foreach ($notifications as $notification) {
    $processed_notifications[] = get_notification_details($notification);
}

// Count unread notifications
$unread_count = count_unread_notifications($loggedInUserId, 'user');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - SitIn System</title>
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
                        },
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
        .notification-item:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-bold">SitIn Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Home</a>
                        <?php include_once '../includes/user_notification_dropdown.php'; ?>
                        <a href="edit.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Edit Profile</a>
                        <a href="history.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">History</a>
                        <a href="lab_schedules.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Lab Schedules</a>
                        <a href="reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Reservation</a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium px-4 py-2 rounded transition">
                        Log out
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Mobile Navigation Menu (hidden by default) -->
    <div id="mobile-menu" class="md:hidden bg-primary-800 hidden">
        <a href="dashboard.php" class="block px-4 py-2 text-white hover:bg-primary-900">Home</a>
        <a href="edit.php" class="block px-4 py-2 text-white hover:bg-primary-900">Edit Profile</a>
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
        <a href="lab_schedules.php" class="block px-4 py-2 text-white hover:bg-primary-900">Lab Schedules</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 flex-grow">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">My Notifications</h1>
                
                <?php if ($unread_count > 0): ?>
                <form method="POST" action="">
                    <button type="submit" name="mark_all_read" class="bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700 transition flex items-center">
                        <i class="fas fa-check-double mr-2"></i> Mark all as read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'marked_read'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p>All notifications have been marked as read.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($processed_notifications)): ?>
            <div class="bg-white p-12 rounded-lg shadow-sm border border-gray-200 text-center">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-bell-slash text-5xl"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-700 mb-2">No Notifications</h2>
                <p class="text-gray-600 mb-6">You don't have any notifications yet.</p>
                <a href="dashboard.php" class="inline-block bg-primary-600 text-white px-6 py-2 rounded-md hover:bg-primary-700 transition">
                    Return to Dashboard
                </a>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="font-semibold text-gray-700">You have <?php echo count($processed_notifications); ?> notification(s)</h2>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach ($processed_notifications as $notification): ?>
                    <div class="p-4 notification-item <?php echo $notification['is_read'] ? 'bg-white' : 'bg-blue-50'; ?>">
                        <div class="flex">
                            <div class="flex-shrink-0 mr-4">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center text-white" style="background-color: <?php echo $notification['color']; ?>">
                                    <i class="fas <?php echo $notification['icon']; ?>"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <?php if (!$notification['is_read']): ?>
                                            <span class="ml-2 bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full">New</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </p>
                                        <?php if (isset($notification['sender_name'])): ?>
                                        <div class="flex items-center mt-2 text-xs text-gray-500">
                                            <span class="mr-2">From:</span>
                                            <div class="w-5 h-5 rounded-full overflow-hidden mr-1">
                                                <img src="<?php echo isset($notification['sender_pic']) ? htmlspecialchars($notification['sender_pic']) : '../uploads/profile_pics/profile.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($notification['sender_name']); ?>" 
                                                     class="w-full h-full object-cover">
                                            </div>
                                            <span><?php echo htmlspecialchars($notification['sender_name']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo $notification['time_ago']; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($notification['link'])): ?>
                                <div class="mt-2">
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                       class="text-primary-600 hover:text-primary-800 text-sm font-medium flex items-center">
                                        <i class="fas fa-external-link-alt mr-1"></i> View Details
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> SitIn System. All rights reserved.
        </div>
    </footer>
    
    <script>
        // Toggle mobile menu
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html> 