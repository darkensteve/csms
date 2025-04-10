<?php
// Start the session at the beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in user's ID
$loggedInUserId = $_SESSION['id'];

// Also get the user's ID number for SitIn history
$userIdNumber = isset($_SESSION['idNo']) ? $_SESSION['idNo'] : null;

// If idNo is not in session, try to retrieve it from the database
if (!$userIdNumber) {
    $idStmt = $conn->prepare("SELECT idNo FROM users WHERE user_id = ?");
    if ($idStmt) {
        $idStmt->bind_param("i", $loggedInUserId);
        $idStmt->execute();
        $idResult = $idStmt->get_result();
        if ($idResult && $idResult->num_rows > 0) {
            $idRow = $idResult->fetch_assoc();
            $userIdNumber = $idRow['idNo'];
            $_SESSION['idNo'] = $userIdNumber; // Save for future use
        }
        $idStmt->close();
    }
}

// Initialize variables
$message = '';
$messageType = '';

// Fetch user information
$stmt = $conn->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
$stmt->bind_param("i", $loggedInUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userName = $user['firstName'] . ' ' . $user['lastName'];

// Get user's reservation history, ordered by most recent first
$reservations = [];
try {
    $stmt = $conn->prepare("SELECT r.*, l.lab_name, c.computer_name, c.status as computer_status
                        FROM reservations r 
                        JOIN labs l ON r.lab_id = l.lab_id 
                        LEFT JOIN computers c ON r.computer_id = c.computer_id
                        WHERE r.user_id = ? 
                        ORDER BY r.reservation_date DESC, r.time_slot DESC");
    $stmt->bind_param("i", $loggedInUserId);
    $stmt->execute();
    $historyResult = $stmt->get_result();

    if ($historyResult && $historyResult->num_rows > 0) {
        while($row = $historyResult->fetch_assoc()) {
            $reservations[] = $row;
        }
    }
} catch (Exception $e) {
    $message = "Error retrieving reservation history: " . $e->getMessage();
    $messageType = "error";
}

// Group reservations by month and year
$groupedReservations = [];
foreach ($reservations as $reservation) {
    $month = date('F Y', strtotime($reservation['created_at']));
    if (!isset($groupedReservations[$month])) {
        $groupedReservations[$month] = [];
    }
    $groupedReservations[$month][] = $reservation;
}

// Get user's sit-in history
$sitInHistory = [];
try {
    $sitInQuery = $conn->prepare("SELECT s.*, l.lab_name, c.computer_name, 
                            (SELECT COUNT(*) FROM sit_in_feedback WHERE session_id = s.session_id AND user_id = ?) as has_feedback
                            FROM sit_in_sessions s 
                            JOIN labs l ON s.lab_id = l.lab_id 
                            LEFT JOIN computers c ON s.computer_id = c.computer_id
                            WHERE s.student_id = ? AND s.status = 'inactive' 
                            ORDER BY s.check_in_time DESC");
    $sitInQuery->bind_param("is", $loggedInUserId, $userIdNumber);
    $sitInQuery->execute();
    $sitInResult = $sitInQuery->get_result();

    if ($sitInResult && $sitInResult->num_rows > 0) {
        while($row = $sitInResult->fetch_assoc()) {
            $sitInHistory[] = $row;
        }
    }
} catch (Exception $e) {
    $message = "Error retrieving sit-in history: " . $e->getMessage();
    $messageType = "error";
}

// Group sit-in history by month and year
$groupedSitIns = [];
foreach ($sitInHistory as $sitIn) {
    $month = date('F Y', strtotime($sitIn['check_in_time']));
    if (!isset($groupedSitIns[$month])) {
        $groupedSitIns[$month] = [];
    }
    $groupedSitIns[$month][] = $sitIn;
}

// Display feedback form if requested
$feedbackSessionId = isset($_GET['feedback']) ? intval($_GET['feedback']) : 0;
$feedbackSession = null;

if ($feedbackSessionId > 0) {
    // Get the session details for feedback
    foreach ($sitInHistory as $session) {
        if ($session['session_id'] == $feedbackSessionId && $session['has_feedback'] == 0) {
            $feedbackSession = $session;
            break;
        }
    }
}

// Function to get appropriate status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800';
        case 'completed':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Function to get appropriate status icon
function getStatusIcon($status) {
    switch($status) {
        case 'approved':
            return 'fa-check-circle';
        case 'pending':
            return 'fa-clock';
        case 'rejected':
            return 'fa-times-circle';
        case 'cancelled':
            return 'fa-ban';
        case 'completed':
            return 'fa-check-double';
        default:
            return 'fa-info-circle';
    }
}

// Check for feedback success message
if (isset($_SESSION['feedback_message']) && isset($_SESSION['feedback_status'])) {
    $message = $_SESSION['feedback_message'];
    $messageType = $_SESSION['feedback_status'];
    
    // Clear the message after displaying
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_status']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation History - SitIn System</title>
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
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .notification.success {
            background-color: #10b981;
        }
        
        .notification.error {
            background-color: #ef4444;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .timeline-container {
            border-left: 2px solid #e5e7eb;
            margin-left: 1.5rem;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .timeline-dot {
            position: absolute;
            left: -0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #0ea5e9;
            border: 2px solid white;
        }
        
        .timeline-month {
            margin-left: -3.25rem;
            background-color: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 10;
        }
    </style>
</head>
<body class="font-sans bg-gray-50 min-h-screen flex flex-col">
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
                        <div class="relative group">
                            <button class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                                Notification <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 1</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 2</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 3</a>
                            </div>
                        </div>
                        <a href="edit.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Edit Profile</a>
                        <a href="history.php" class="px-3 py-2 rounded bg-primary-800 transition">History</a>
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
        <button class="mobile-dropdown-button w-full text-left px-4 py-2 text-white hover:bg-primary-900 flex justify-between items-center">
            Notification <i class="fas fa-chevron-down ml-1"></i>
        </button>
        <div class="mobile-dropdown-content hidden bg-primary-900 px-4 py-2">
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 1</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 2</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 3</a>
        </div>
        <a href="edit.php" class="block px-4 py-2 text-white hover:bg-primary-900">Edit Profile</a>
        <a href="history.php" class="block px-4 py-2 text-white bg-primary-900">History</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="flex-grow container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">SitIn History</h1>
        
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo $message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-history text-primary-600"></i>
                    </div>
                    <div class="ml-3">
                        <h2 class="text-lg font-semibold text-gray-800">Your SitIn History</h2>
                        <p class="text-sm text-gray-500">Track your computer laboratory usage</p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($sitInHistory)): ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-desktop text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">No SitIn History Found</h3>
                    <p class="text-gray-500 mb-6">You haven't used any laboratory computers yet.</p>
                </div>
            <?php else: ?>
                <!-- SitIn History Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sit-in Purpose</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sitInHistory as $session): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userIdNumber); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($userName); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        <?php echo !empty($session['purpose']) ? htmlspecialchars($session['purpose']) : '<span class="text-gray-400">Not specified</span>'; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo htmlspecialchars($session['lab_name']); ?>
                                        <?php if (!empty($session['computer_name'])): ?>
                                            <span class="text-gray-500 text-xs ml-1">(PC #<?php echo htmlspecialchars($session['computer_name']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo date('h:i A', strtotime($session['check_in_time'])); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo !empty($session['check_out_time']) ? date('h:i A', strtotime($session['check_out_time'])) : '<span class="text-gray-400">N/A</span>'; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo date('M d, Y', strtotime($session['check_in_time'])); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($session['has_feedback'] == 0): ?>
                                            <button onclick="showFeedbackModal(<?php echo $session['session_id']; ?>)" 
                                                    class="text-primary-600 hover:text-primary-900 inline-flex items-center">
                                                <i class="fas fa-comment-alt mr-1"></i> Feedback
                                            </button>
                                        <?php else: ?>
                                            <span class="text-green-600 inline-flex items-center">
                                                <i class="fas fa-check-circle mr-1"></i> Feedback submitted
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reservation History -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar-alt text-primary-600"></i>
                    </div>
                    <div class="ml-3">
                        <h2 class="text-lg font-semibold text-gray-800">Your Reservation History</h2>
                        <p class="text-sm text-gray-500">View all your past reservations</p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($reservations)): ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-calendar-times text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">No Reservation History</h3>
                    <p class="text-gray-500 mb-6">You haven't made any lab reservations yet.</p>
                    <a href="reservation.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition">
                        <i class="fas fa-calendar-plus mr-2"></i> Make a Reservation
                    </a>
                </div>
            <?php else: ?>
                <!-- Reservation Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Computer</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($reservations as $reservation): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $reservation['time_slot']; ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($reservation['lab_name']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $reservation['computer_name'] ? "PC #" . htmlspecialchars($reservation['computer_name']) : "Not assigned"; ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadgeClass($reservation['status']); ?>">
                                            <i class="fas <?php echo getStatusIcon($reservation['status']); ?> mr-1"></i>
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg max-w-md w-full p-6 mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Submit Feedback</h3>
                <button onclick="hideFeedbackModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="feedbackForm" action="../admin/sitin/submit_feedback.php" method="post">
                <input type="hidden" id="session_id" name="session_id" value="">
                
                <div class="mb-4">
                    <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                    <div class="flex space-x-2">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-value="<?php echo $i; ?>">
                                <i class="fas fa-star"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="rating_value" name="rating" value="">
                </div>
                
                <div class="mb-4">
                    <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">Comments</label>
                    <textarea id="feedback" name="feedback" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="hideFeedbackModal()" class="mr-3 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
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
        
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }, 100);
        }
        
        // Feedback modal functions
        function showFeedbackModal(sessionId) {
            document.getElementById('session_id').value = sessionId;
            document.getElementById('feedbackModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function hideFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
            // Reset form
            document.getElementById('feedbackForm').reset();
            document.getElementById('session_id').value = '';
            document.getElementById('rating_value').value = '';
            document.querySelectorAll('.rating-star').forEach(star => {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            });
        }
        
        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    document.getElementById('rating_value').value = value;
                    // Update star appearance
                    stars.forEach(s => {
                        const sValue = s.getAttribute('data-value');
                        if (sValue <= value) {
                            s.classList.add('text-yellow-400');
                            s.classList.remove('text-gray-300');
                        } else {
                            s.classList.add('text-gray-300');
                            s.classList.remove('text-yellow-400');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>