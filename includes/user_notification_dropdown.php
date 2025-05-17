<?php
// Include the notification functions if not already included
if (!function_exists('get_notifications')) {
    require_once __DIR__ . '/notification_functions.php';
}

// Get the user ID from the session - check for both user_id and id keys for compatibility
$user_id = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? 0);

// Only process if user is logged in
if ($user_id) {
    // Get unread notifications count
    $unread_count = count_unread_notifications($user_id, 'user');
    
    // Get recent notifications (limit to 5)
    $notifications = get_notifications($user_id, 'user', false, 5);
    
    // Process notifications to add additional details
    $processed_notifications = [];
    foreach ($notifications as $notification) {
        $processed_notifications[] = get_notification_details($notification);
    }
}
?>

<!-- Notification Dropdown Component -->
<div class="relative" id="notificationDropdown">
    <button class="relative flex items-center text-white focus:outline-none" id="notificationButton" onclick="toggleNotificationDropdown()">
        <i class="fas fa-bell text-lg"></i>
        <?php if (isset($unread_count) && $unread_count > 0): ?>
        <span class="absolute top-0 right-0 -mt-1 -mr-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
            <?php echo $unread_count <= 9 ? $unread_count : '9+'; ?>
        </span>
        <?php endif; ?>
    </button>
    
    <div id="notificationMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg overflow-hidden z-50 max-h-96 overflow-y-auto">
        <div class="py-2">
            <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                <button onclick="markAllAsRead()" class="text-xs text-blue-500 hover:text-blue-700 focus:outline-none">
                    Mark all as read
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (isset($processed_notifications) && !empty($processed_notifications)): ?>
                <?php foreach ($processed_notifications as $notification): ?>
                <a href="<?php echo htmlspecialchars($notification['link'] ?? '#'); ?>" 
                   class="block px-4 py-3 hover:bg-gray-50 transition duration-150 border-b border-gray-100 notification-item <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>"
                   data-id="<?php echo $notification['notification_id']; ?>"
                   onclick="markAsRead(<?php echo $notification['notification_id']; ?>, event)">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-3">
                            <div class="h-8 w-8 rounded-full flex items-center justify-center text-white" style="background-color: <?php echo $notification['color']; ?>">
                                <i class="fas <?php echo $notification['icon']; ?> text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <div class="flex justify-between items-start">
                                <p class="text-sm font-medium text-gray-900 truncate mb-1">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </p>
                                <span class="text-xs text-gray-500 whitespace-nowrap ml-2">
                                    <?php echo $notification['time_ago']; ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 line-clamp-2">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            <?php if (isset($notification['sender_name'])): ?>
                            <div class="flex items-center mt-1">
                                <div class="w-4 h-4 rounded-full overflow-hidden mr-1">
                                    <img src="<?php echo isset($notification['sender_pic']) ? htmlspecialchars($notification['sender_pic']) : 'uploads/profile_pics/profile.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($notification['sender_name']); ?>" 
                                         class="w-full h-full object-cover">
                                </div>
                                <span class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($notification['sender_name']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="px-4 py-6 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                    <p class="text-sm">No notifications yet</p>
                </div>
            <?php endif; ?>
            
            <div class="px-4 py-2 border-t border-gray-100 text-center">
                <a href="notifications.php" class="text-xs text-blue-500 hover:text-blue-700">View all notifications</a>
            </div>
        </div>
    </div>
</div>

<script>
// Add this script only once per page
if (!window.userNotificationScriptLoaded) {
    window.userNotificationScriptLoaded = true;
    
    function toggleNotificationDropdown() {
        const menu = document.getElementById('notificationMenu');
        menu.classList.toggle('hidden');
    }
    
    function markAsRead(notificationId, event) {
        // Prevent navigation if button was clicked
        if (event.target.tagName === 'BUTTON') {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Send AJAX request to mark notification as read
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo isset($notification_ajax_url) ? $notification_ajax_url : '/includes/mark_notification.php'; ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Update UI to mark notification as read
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('bg-blue-50');
                }
                
                // Update unread count
                const countElement = document.querySelector('#notificationButton span');
                if (countElement) {
                    let count = parseInt(countElement.textContent);
                    if (!isNaN(count) && count > 0) {
                        count--;
                        if (count === 0) {
                            countElement.remove();
                        } else {
                            countElement.textContent = count <= 9 ? count : '9+';
                        }
                    }
                }
            }
        };
        xhr.send(`notification_id=${notificationId}&type=user`);
    }
    
    function markAllAsRead() {
        // Send AJAX request to mark all notifications as read
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo isset($notification_ajax_url) ? $notification_ajax_url : '/includes/mark_notification.php'; ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Update UI to mark all notifications as read
                const notificationItems = document.querySelectorAll('.notification-item');
                notificationItems.forEach(item => {
                    item.classList.remove('bg-blue-50');
                });
                
                // Remove unread count badge
                const countElement = document.querySelector('#notificationButton span');
                if (countElement) {
                    countElement.remove();
                }
            }
        };
        xhr.send('mark_all=1&type=user');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        const menu = document.getElementById('notificationMenu');
        
        if (dropdown && menu && !dropdown.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
}
</script> 