<?php
// Include database connection
require_once '../includes/db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: auth/login_admin.php');
    exit();
}

// Include notification functions
require_once '../includes/notification_functions.php';

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Get all notifications for the admin
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count of notifications
$total = 0;
$count_query = "SELECT COUNT(*) as total FROM notifications WHERE recipient_id = ? AND recipient_type = 'admin'";
$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    $count_stmt->bind_param('i', $admin_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
}

$total_pages = ceil($total / $per_page);

// Get filtered notifications
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "WHERE recipient_id = ? AND recipient_type = 'admin'";

if ($filter === 'unread') {
    $where_clause .= " AND is_read = 0";
} elseif ($filter !== 'all') {
    // Filter by type
    $where_clause .= " AND type = ?";
}

$query = "SELECT * FROM notifications $where_clause ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    if ($filter !== 'all' && $filter !== 'unread') {
        $stmt->bind_param('isii', $admin_id, $filter, $offset, $per_page);
    } else {
        $stmt->bind_param('iii', $admin_id, $offset, $per_page);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = get_notification_details($row);
    }
}

// Get notification types for filter dropdown
$types_query = "SELECT DISTINCT type FROM notifications WHERE recipient_id = ? AND recipient_type = 'admin'";
$types_stmt = $conn->prepare($types_query);
$notification_types = [];

if ($types_stmt) {
    $types_stmt->bind_param('i', $admin_id);
    $types_stmt->execute();
    $types_result = $types_stmt->get_result();
    
    while ($type = $types_result->fetch_assoc()) {
        $notification_types[] = $type['type'];
    }
}

// Mark all as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] === '1') {
    mark_all_notifications_read($admin_id, 'admin');
    header('Location: notifications.php?marked_read=1');
    exit();
}

// Delete a notification if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    delete_notification($notification_id, $admin_id, 'admin');
    header('Location: notifications.php?deleted=1');
    exit();
}

// Delete all notifications if requested
if (isset($_GET['delete_all']) && $_GET['delete_all'] === '1') {
    delete_all_notifications($admin_id, 'admin');
    header('Location: notifications.php?all_deleted=1');
    exit();
}

// Success message
$success_message = '';
if (isset($_GET['marked_read'])) {
    $success_message = 'All notifications marked as read.';
} elseif (isset($_GET['deleted'])) {
    $success_message = 'Notification deleted successfully.';
} elseif (isset($_GET['all_deleted'])) {
    $success_message = 'All notifications deleted successfully.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Admin Dashboard</title>
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
        
        .nav-button {
            transition: all 0.2s ease;
            position: relative;
        }
        
        .nav-button:hover {
            background-color: rgba(7, 89, 133, 0.8);
        }
    </style>
</head>
<body class="font-sans h-screen flex flex-col">
    <!-- Success Notification -->
    <?php if (!empty($success_message)): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
    </div>
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
                        <a href="admin.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="students/search_student.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-search mr-1"></i> Search
                        </a>
                        <a href="students/student.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-users mr-1"></i> Students
                        </a>
                        <a href="sitin/current_sitin.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-user-check mr-1"></i> Sit-In
                        </a>
                        <a href="lab_resources/index.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-book mr-1"></i> Lab Resources
                        </a>
                        <a href="sitin/feedback_reports.php" class="nav-button px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                            <i class="fas fa-comment mr-1"></i> Feedback
                        </a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <div class="relative">
                        <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                <img src="newp.jpg" alt="Admin" class="w-full h-full object-cover">
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
        <a href="admin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-home mr-2"></i> Home
        </a>
        <a href="students/search_student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-search mr-2"></i> Search
        </a>
        <a href="students/student.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-users mr-2"></i> Students
        </a>
        <a href="sitin/current_sitin.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-user-check mr-2"></i> Sit-In
        </a>
        <a href="lab_resources/index.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-book mr-2"></i> Lab Resources
        </a>
        <a href="sitin/feedback_reports.php" class="block px-4 py-2 text-white hover:bg-primary-900">
            <i class="fas fa-comment mr-2"></i> Feedback
        </a>
    </div>
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col px-4 py-6 md:px-8 bg-gray-50">
        <div class="container mx-auto flex-1 flex flex-col">
            <!-- Page Title -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">Notifications</h1>
                
                <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3">
                    <div class="relative">
                        <select onchange="location = this.value;" class="pl-3 pr-10 py-2 border border-gray-300 rounded-md appearance-none bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="?filter=all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Notifications</option>
                            <option value="?filter=unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                            <?php foreach ($notification_types as $type): ?>
                                <option value="?filter=<?php echo $type; ?>" <?php echo $filter === $type ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="?mark_all_read=1" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition flex items-center justify-center">
                            <i class="fas fa-check-double mr-1"></i> Mark All Read
                        </a>
                        <a href="?delete_all=1" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition flex items-center justify-center" onclick="return confirm('Are you sure you want to delete all notifications? This action cannot be undone.')">
                            <i class="fas fa-trash-alt mr-1"></i> Delete All
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="divide-y divide-gray-200">
                    <?php if (empty($notifications)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-bell-slash text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-1">No notifications</h3>
                            <p class="text-gray-500">
                                <?php
                                    if ($filter === 'unread') {
                                        echo 'You have no unread notifications.';
                                    } elseif ($filter !== 'all') {
                                        echo 'You have no ' . str_replace('_', ' ', $filter) . ' notifications.';
                                    } else {
                                        echo 'You don\'t have any notifications yet.';
                                    }
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="p-4 sm:p-6 hover:bg-gray-50 transition flex <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                <div class="flex-shrink-0 mr-4">
                                    <div class="h-12 w-12 rounded-full flex items-center justify-center text-white" style="background-color: <?php echo $notification['color']; ?>">
                                        <i class="fas <?php echo $notification['icon']; ?> text-lg"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <h3 class="text-lg font-medium text-gray-900">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h3>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm text-gray-500">
                                                <?php echo $notification['time_ago']; ?>
                                            </span>
                                            <div class="flex items-center space-x-2">
                                                <?php if (!$notification['is_read']): ?>
                                                <a href="#" onclick="markAsRead(<?php echo $notification['notification_id']; ?>); return false;" class="text-blue-600 hover:text-blue-800" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $notification['notification_id']; ?>" class="text-red-600 hover:text-red-800" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-gray-600">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <?php if (isset($notification['sender_name'])): ?>
                                    <div class="flex items-center mt-2">
                                        <div class="w-6 h-6 rounded-full overflow-hidden mr-2 border border-gray-200">
                                            <img src="<?php echo isset($notification['sender_pic']) ? htmlspecialchars($notification['sender_pic']) : '../uploads/profile_pics/profile.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($notification['sender_name']); ?>" 
                                                 class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-sm text-gray-600">
                                            From: <?php echo htmlspecialchars($notification['sender_name']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($notification['link'])): ?>
                                    <div class="mt-3">
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:border-primary-700 focus:shadow-outline-primary transition">
                                            <i class="fas fa-external-link-alt mr-1"></i> View Details
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="relative z-0 inline-flex shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    
                    if ($end_page - $start_page < 4 && $start_page > 1) {
                        $start_page = max(1, $end_page - 4);
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?php echo $i === $page ? 'bg-primary-100 text-primary-700 font-bold' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> text-sm">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
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
        
        // Toggle user dropdown
        function toggleUserDropdown() {
            document.getElementById('userMenu').classList.toggle('hidden');
        }
        
        // Close user dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!document.getElementById('userDropdown').contains(e.target)) {
                document.getElementById('userMenu').classList.add('hidden');
            }
        });
        
        // Mark notification as read
        function markAsRead(notificationId) {
            // Send AJAX request to mark notification as read
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../includes/mark_notification.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // Reload page to show changes
                    window.location.reload();
                }
            };
            xhr.send(`notification_id=${notificationId}&type=admin`);
        }
        
        // Auto hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.transition = 'opacity 0.5s ease-out';
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html> 