<?php
// Start the session at the beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Get the logged-in user's ID
$loggedInUserId = $_SESSION['id'];

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$messageType = '';
$setupNeeded = false;

// Check if reservations table exists
$table_check = $conn->query("SHOW TABLES LIKE 'reservations'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, include the table creation script
    if (file_exists('../admin/setup/create_tables.php')) {
        include_once '../admin/setup/create_tables.php';
    } else {
        // Create the table directly if the script isn't found
        $create_table = "CREATE TABLE IF NOT EXISTS `reservations` (
            `reservation_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `lab_id` INT(11) NOT NULL,
            `computer_id` INT(11) DEFAULT NULL,
            `reservation_date` DATE NOT NULL,
            `time_slot` VARCHAR(50) NOT NULL,
            `purpose` TEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`reservation_id`),
            INDEX (`user_id`),
            INDEX (`computer_id`),
            CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_res_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_res_computer` FOREIGN KEY (`computer_id`) REFERENCES `computers` (`computer_id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if (!$conn->query($create_table)) {
            // If the query fails (possibly due to foreign key constraints), try without constraints
            $create_table_simple = "CREATE TABLE IF NOT EXISTS `reservations` (
                `reservation_id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `lab_id` INT(11) NOT NULL,
                `computer_id` INT(11) DEFAULT NULL,
                `reservation_date` DATE NOT NULL,
                `time_slot` VARCHAR(50) NOT NULL,
                `purpose` TEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`reservation_id`),
                INDEX (`user_id`),
                INDEX (`computer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            $conn->query($create_table_simple);
        }
        
        // Set a message to inform the user
        $message = "Reservation system has been set up. You can now make reservations.";
        $messageType = "success";
    }
}

// Fetch user details for form pre-fill
$stmt = $conn->prepare("SELECT idNo, firstName, lastName, middleName, course, yearLevel, remaining_sessions FROM users WHERE user_id = ?");
$stmt->bind_param("i", $loggedInUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $idNo = $user['idNo'];
    $firstName = $user['firstName'];
    $lastName = $user['lastName'];
    $middleName = $user['middleName'];
    $course = $user['course'];
    $yearLevel = $user['yearLevel'];
    $remainingSessions = $user['remaining_sessions'] ?? 30;
} else {
    $idNo = $firstName = $lastName = $middleName = $course = $yearLevel = '';
    $remainingSessions = 30;
}

// Check if user has remaining sessions
$canReserve = $remainingSessions > 0;

// Handle reservation cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // First get the computer_id associated with this reservation
    $getComputer = $conn->prepare("SELECT computer_id FROM reservations WHERE reservation_id = ? AND user_id = ? AND status = 'pending'");
    $getComputer->bind_param("ii", $reservation_id, $loggedInUserId);
    $getComputer->execute();
    $computerResult = $getComputer->get_result();
    
    if ($computerResult->num_rows > 0) {
        $computerId = $computerResult->fetch_assoc()['computer_id'];
        
        // Update the reservation status to cancelled
        $cancelStmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ? AND user_id = ?");
        $cancelStmt->bind_param("ii", $reservation_id, $loggedInUserId);
        
        if ($cancelStmt->execute()) {
            // Update the computer status back to available
            if ($computerId) {
                $updateComputer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_id = ?");
                $updateComputer->bind_param("i", $computerId);
                $updateComputer->execute();
                $messageType = "success";
                $message = "Reservation cancelled successfully.";
            }
        } else {
            $messageType = "error";
            $message = "Error cancelling reservation.";
        }
    } else {
        $messageType = "error";
        $message = "Reservation not found or already processed.";
    }
}

// Form processing for new reservation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reservation'])) {
    // Check if user has remaining sessions
    if (!$canReserve) {
        $message = "You don't have any remaining sessions. Please contact the administrator.";
        $messageType = "error";
    } else {
        // Get form data
        $labId = $_POST['lab_id'];
        $computerId = isset($_POST['computer_id']) ? $_POST['computer_id'] : null;
        
        // Always use today's date
        $date = date('Y-m-d');
        
        // Get custom time - only start time now
        $startTime = $_POST['start_time'];
        
        // Format the time slot to show only the start time
        $timeSlot = $startTime;
        
        $purpose = $_POST['purpose'];
        
        // Get current datetime for created_at field
        $createdAt = date('Y-m-d H:i:s');
        
        // Default status is 'pending'
        $status = 'pending';
        
        // Additional validation for time
        $isTimeValid = true;
        $timeErrorMessage = "";
        
        // Convert time to 24-hour format for comparison
        $startTime24 = date('H:i', strtotime($startTime));
        
        // Check if the time slot is within lab operation hours (8 AM - 6 PM)
        $openTime = '08:00';
        $closeTime = '18:00';
        
        if ($startTime24 < $openTime || $startTime24 >= $closeTime) {
            $isTimeValid = false;
            $timeErrorMessage = "Start time must be between 8 AM and 6 PM.";
        }
        
        // Validate computer selection
        $isComputerValid = true;
        if (empty($computerId)) {
            $isComputerValid = false;
            $computerErrorMessage = "Please select a computer.";
        } else {
            // Check if the computer is still available
            $checkComputer = $conn->prepare("SELECT status FROM computers WHERE computer_id = ? AND lab_id = ?");
            $checkComputer->bind_param("ii", $computerId, $labId);
            $checkComputer->execute();
            $computerResult = $checkComputer->get_result();
            
            if ($computerResult->num_rows == 0 || $computerResult->fetch_assoc()['status'] !== 'available') {
                $isComputerValid = false;
                $computerErrorMessage = "The selected computer is no longer available. Please choose another.";
            }
        }
        
        // Check if user already has an active reservation for today
        $hasActiveReservation = false;
        $activeReservationQuery = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ? AND reservation_date = ? AND status IN ('pending', 'approved')");
        $activeReservationQuery->bind_param("is", $loggedInUserId, $date);
        $activeReservationQuery->execute();
        $activeReservationResult = $activeReservationQuery->get_result();
        
        if ($activeReservationResult->fetch_assoc()['count'] > 0) {
            $hasActiveReservation = true;
        }
        
        if ($hasActiveReservation) {
            $message = "You already have an active reservation for today. You can only have one active reservation per day.";
            $messageType = "error";
        } elseif (!$isTimeValid) {
            $message = "Invalid time: " . $timeErrorMessage;
            $messageType = "error";
        } elseif (!$isComputerValid) {
            $message = $computerErrorMessage;
            $messageType = "error";
        } else {
            // Update computer status to reserved immediately when reservation request is made
            $update_computer_query = "UPDATE computers SET status = 'reserved' WHERE computer_id = ?";
            $stmt = $conn->prepare($update_computer_query);
            if ($stmt) {
                $stmt->bind_param("i", $computerId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insert reservation
            $stmt = $conn->prepare("INSERT INTO reservations (user_id, lab_id, computer_id, reservation_date, time_slot, purpose, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisssss", $loggedInUserId, $labId, $computerId, $date, $timeSlot, $purpose, $status, $createdAt);
            
            if ($stmt->execute()) {
                // If successful, mark the computer as pending (not reserved yet)
                $updateComputer = $conn->prepare("UPDATE computers SET status = 'pending' WHERE computer_id = ?");
                $updateComputer->bind_param("i", $computerId);
                $updateComputer->execute();
                
                $message = "Your reservation request has been submitted successfully. Please wait for approval.";
                $messageType = "success";
            } else {
                $message = "Error: " . $stmt->error;
                $messageType = "error";
            }
        }
    }
}

// Get all labs for dropdown
$labs = [];
$labsResult = $conn->query("SELECT * FROM labs ORDER BY lab_name");
if ($labsResult && $labsResult->num_rows > 0) {
    while($row = $labsResult->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Add error handling in case the labs table doesn't exist or has other issues
if (empty($labs)) {
    // Create a default lab entry if none exist
    $labs[] = [
        'lab_id' => 1,
        'lab_name' => 'Computer Laboratory 1',
        'location' => 'Main Building'
    ];
}

// Get user's pending reservations
$pendingReservations = [];
if (!$setupNeeded) {
    try {
        $stmt = $conn->prepare("SELECT r.*, l.lab_name, c.computer_name, c.status as computer_status
                            FROM reservations r 
                            JOIN labs l ON r.lab_id = l.lab_id 
                            LEFT JOIN computers c ON r.computer_id = c.computer_id
                            WHERE r.user_id = ? AND r.status IN ('pending', 'approved') 
                            ORDER BY r.reservation_date ASC, r.time_slot ASC");
        $stmt->bind_param("i", $loggedInUserId);
        $stmt->execute();
        $pendingResult = $stmt->get_result();

        if ($pendingResult && $pendingResult->num_rows > 0) {
            while($row = $pendingResult->fetch_assoc()) {
                $pendingReservations[] = $row;
            }
        }
    } catch (Exception $e) {
        // Silently handle the error - we already show a setup message if needed
    }
}

// Define programming purposes
$programmingPurposes = [
    'C Programming',
    'Java Programming',
    'C# Programming',
    'PHP Programming',
    'ASP.net Programming',
];

// Check for available slots - add error handling
function isSlotAvailable($labId, $date, $timeSlot, $conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE lab_id = ? AND reservation_date = ? AND time_slot = ? AND status IN ('pending', 'approved')");
        $stmt->bind_param("iss", $labId, $date, $timeSlot);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Get lab capacity
        $capacityStmt = $conn->prepare("SELECT capacity FROM labs WHERE lab_id = ?");
        $capacityStmt->bind_param("i", $labId);
        $capacityStmt->execute();
        $capacityResult = $capacityStmt->get_result();
        $capacityRow = $capacityResult->fetch_assoc();
        $capacity = $capacityRow['capacity'] ?? 30;
        
        return $row['count'] < $capacity;
    } catch (Exception $e) {
        // Default to showing slot as available if there's a database error
        return true;
    }
}

// AJAX handler for getting available computers
if (isset($_GET['get_computers']) && isset($_GET['lab_id'])) {
    $lab_id = (int)$_GET['lab_id'];
    $availableComputers = [];
    
    try {
        // Updated query to use computer_number for ordering
        $stmt = $conn->prepare("SELECT computer_id, computer_name FROM computers 
                               WHERE lab_id = ? AND status = 'available' 
                               ORDER BY computer_number ASC");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $availableComputers[] = $row;
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($availableComputers);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation - SitIn System</title>
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
        .date-disabled {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-used {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .status-cancelled {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .reservation-card {
            transition: all 0.3s ease;
        }
        .reservation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
    <script>
        // Toggle mobile menu
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            document.getElementById('mobile-menu-button')?.addEventListener('click', function() {
                document.getElementById('mobile-menu').classList.toggle('hidden');
            });

            // Toggle mobile dropdown menu
            document.querySelectorAll('.mobile-dropdown-button').forEach(button => {
                button.addEventListener('click', function() {
                    this.nextElementSibling.classList.toggle('hidden');
                });
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

        document.addEventListener('DOMContentLoaded', function() {
            // Form validation before submission
            const reservationForm = document.getElementById('reservationForm');
            if (reservationForm) {
                reservationForm.addEventListener('submit', function(e) {
                    const startTime = document.getElementById('start_time').value;
                    if (startTime) {
                        // Check if times are within lab hours (8 AM - 6 PM)
                        const startHour = parseInt(startTime.split(':')[0]);
                        const startMinute = parseInt(startTime.split(':')[1]);
                        if (startHour < 8 || startHour >= 18) {
                            e.preventDefault();
                            showNotification("Lab hours are from 8:00 AM to 6:00 PM.", "error");
                        }
                    }
                });
            }

            // Check if notification should be shown (from PHP)
            <?php if (!empty($message)): ?>
            showNotification("<?php echo addslashes($message); ?>", "<?php echo $messageType; ?>");
            <?php endif; ?>

            // Load available computers when lab is selected
            const labSelector = document.getElementById('lab_id');
            if (labSelector) {
                labSelector.addEventListener('change', function() {
                    const labId = this.value;
                    const computerSelect = document.getElementById('computer_id');
                    const loadingMessage = document.getElementById('computer-loading');
                    const noComputersMessage = document.getElementById('no-computers-message');
                    
                    // Reset computer dropdown
                    computerSelect.innerHTML = '<option value="">Loading computers...</option>';
                    computerSelect.disabled = true;
                    
                    // Show loading indicator
                    loadingMessage.classList.remove('hidden');
                    noComputersMessage.classList.add('hidden');
                    
                    if (labId) {
                        // Fetch available computers for selected lab using fetch API
                        fetch(`reservation.php?get_computers=1&lab_id=${labId}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                loadingMessage.classList.add('hidden');
                                computerSelect.disabled = false;
                                computerSelect.innerHTML = '';
                                
                                // Add default option
                                const defaultOption = document.createElement('option');
                                defaultOption.value = '';
                                defaultOption.textContent = 'Select a computer';
                                computerSelect.appendChild(defaultOption);
                                
                                if (data && data.length > 0) {
                                    // Add options for each available computer
                                    data.forEach(computer => {
                                        const option = document.createElement('option');
                                        option.value = computer.computer_id;
                                        option.textContent = computer.computer_name;
                                        computerSelect.appendChild(option);
                                    });
                                    console.log(`Loaded ${data.length} computers`);
                                } else {
                                    // Show message if no computers available
                                    noComputersMessage.classList.remove('hidden');
                                    defaultOption.textContent = 'No computers available';
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching computers:', error);
                                loadingMessage.classList.add('hidden');
                                computerSelect.disabled = false;
                                computerSelect.innerHTML = '<option value="">Error loading computers</option>';
                                noComputersMessage.classList.remove('hidden');
                            });
                    } else {
                        // Reset if no lab selected
                        loadingMessage.classList.add('hidden');
                        computerSelect.disabled = false;
                        computerSelect.innerHTML = '<option value="">Please select a lab first</option>';
                    }
                });
            }
        });
    </script>
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
                        <a href="history.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">History</a>
                        <a href="reservation.php" class="px-3 py-2 rounded bg-primary-800 transition">Reservation</a>
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
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
        <a href="reservation.php" class="block px-4 py-2 text-white bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 flex-grow">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Lab SitIn Reservation</h1>
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

        <!-- Only show the main content if setup is not needed -->
        <?php if (!$setupNeeded): ?>
        <!-- Remaining Sessions Info Card -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-<?php echo $canReserve ? 'green' : 'red'; ?>-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-ticket-alt text-<?php echo $canReserve ? 'green' : 'red'; ?>-600"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-800">Remaining Sessions: <span class="text-<?php echo $canReserve ? 'green' : 'red'; ?>-600"><?php echo $remainingSessions; ?></span></h2>
                    <p class="text-sm text-gray-600">
                        <?php if ($canReserve): ?>
                            You can make reservations for lab sit-ins. Each reservation consumes one session.
                        <?php else: ?>
                            You've used all your sessions. Please contact the administrator to reset your session count.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Reservation Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Make a Reservation</h2>
                    <?php if (!$canReserve): ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        You don't have any remaining sessions. Please contact the administrator to request more sessions.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form layout modification to make fields more compact -->
                    <form action="reservation.php" method="POST" id="reservationForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Student Information (Read-only) -->
                            <div>
                                <label for="idno" class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                                <input type="text" id="idno" value="<?php echo $idNo; ?>" class="bg-gray-50 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-500" readonly>
                            </div>
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" id="name" value="<?php echo "$firstName $middleName $lastName"; ?>" class="bg-gray-50 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-500" readonly>
                            </div>
                            <div>
                                <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                <input type="text" id="course" value="<?php echo $course; ?>" class="bg-gray-50 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-500" readonly>
                            </div>
                            <div>
                                <label for="yearlevel" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <input type="text" id="yearlevel" value="<?php echo $yearLevel; ?>" class="bg-gray-50 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-500" readonly>
                            </div>
                        </div>

                        <!-- Reorganized form fields in a more compact layout -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Select Laboratory -->
                            <div>
                                <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Select Laboratory</label>
                                <select id="lab_id" name="lab_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" <?php echo !$canReserve ? 'disabled' : ''; ?> required>
                                    <option value="">Select a laboratory</option>
                                    <?php foreach ($labs as $lab): ?>
                                    <option value="<?php echo $lab['lab_id']; ?>"><?php echo $lab['lab_name'] . (isset($lab['location']) ? ' (' . $lab['location'] . ')' : ''); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Select a lab to see available computers</p>
                            </div>
                            <!-- Select Available Computer -->
                            <div>
                                <label for="computer_id" class="block text-sm font-medium text-gray-700 mb-1">Select Available Computer</label>
                                <select id="computer_id" name="computer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" <?php echo !$canReserve ? 'disabled' : ''; ?> required>
                                    <option value="">Please select a lab first</option>
                                </select>
                                <div id="computer-loading" class="text-sm text-gray-500 mt-1 hidden">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Loading available computers...
                                </div>
                                <p id="no-computers-message" class="text-xs text-red-500 mt-1 hidden">
                                    No available computers in this lab. Please select another lab.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Date (Today Only) -->
                            <div>
                                <label for="reservation_date_display" class="block text-sm font-medium text-gray-700 mb-1">Date (Today Only)</label>
                                <input type="text" id="reservation_date_display" value="<?php echo date('Y-m-d'); ?>" class="bg-gray-50 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-500" readonly>
                                <p class="text-xs text-gray-500 mt-1">Reservations are only for today's date.</p>
                            </div>
                            <!-- Start Time -->
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                <input type="time" id="start_time" name="start_time" min="08:00" max="17:59" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" <?php echo !$canReserve ? 'disabled' : ''; ?> required>
                                <p class="text-xs text-gray-500 mt-1">Admin will record when your session ends.</p>
                            </div>
                        </div>

                        <!-- Purpose of SitIn as part of the grid layout -->
                        <div class="grid grid-cols-1 mb-6">
                            <div>
                                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose of SitIn</label>
                                <select id="purpose" name="purpose" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" <?php echo !$canReserve ? 'disabled' : ''; ?> required>
                                    <option value="" selected disabled>Select purpose</option>
                                    <?php foreach ($programmingPurposes as $purpose): ?>
                                    <option value="<?php echo $purpose; ?>"><?php echo $purpose; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" name="submit_reservation" class="px-6 py-2.5 bg-primary-600 text-white font-medium rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors" <?php echo !$canReserve ? 'disabled' : ''; ?>>
                                <i class="fas fa-calendar-plus mr-2"></i> Submit Reservation
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Active Reservations -->
            <div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Active Reservations</h2>
                    <?php if (empty($pendingReservations)): ?>
                        <div class="text-center py-4">
                            <div class="text-gray-400 mb-2">
                                <i class="fas fa-calendar-times text-4xl"></i>
                            </div>
                            <p class="text-gray-600">You don't have any active reservations.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($pendingReservations as $reservation): ?>
                                <div class="border border-gray-200 rounded-md p-4 hover:bg-gray-50 transition reservation-card">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-medium text-primary-700"><?php echo $reservation['lab_name']; ?></span>
                                        <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div class="flex">
                                            <i class="fas fa-calendar-day w-5 text-gray-500"></i>
                                            <span><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></span>
                                        </div>
                                        <div class="flex">
                                            <i class="fas fa-clock w-5 text-gray-500"></i>
                                            <span><?php echo $reservation['time_slot']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <i class="fas fa-desktop w-5 text-gray-500"></i>
                                            <span>Computer <?php echo $reservation['computer_name'] ?? 'Not assigned'; ?></span>
                                        </div>
                                        <div class="flex">
                                            <i class="fas fa-comment w-5 text-gray-500"></i>
                                            <span class="line-clamp-1"><?php echo substr($reservation['purpose'], 0, 40) . (strlen($reservation['purpose']) > 40 ? '...' : ''); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($reservation['status'] === 'pending'): ?>
                                        <form action="reservation.php" method="POST" class="mt-4">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <button type="submit" name="cancel_reservation" class="px-4 py-2 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                                <i class="fas fa-times mr-1"></i> Cancel Reservation
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="history.php" class="text-primary-600 hover:text-primary-800 font-medium text-sm flex items-center">
                            <i class="fas fa-history mr-2"></i> View Reservation History
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div class="text-center py-8">
                <div class="text-amber-500 mb-4">
                    <i class="fas fa-tools text-6xl"></i>
                </div>
                <h2 class="text-xl font-bold mb-2">Reservation System Setup Required</h2>
                <p class="text-gray-600 mb-4">The reservation system needs to be set up before you can make reservations.</p>
                <a href="create_tables.php" class="px-4 py-2 bg-primary-600 text-white font-medium rounded-md hover:bg-primary-700 transition-colors">
                    Set Up Reservation System
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>
</body>
</html>