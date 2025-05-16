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

// Get all labs
$labs_query = "SELECT * FROM labs ORDER BY lab_name";
$labs_result = $conn->query($labs_query);
$labs = [];

if ($labs_result && $labs_result->num_rows > 0) {
    while ($row = $labs_result->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Get selected lab or default to first lab
$selected_lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : (isset($labs[0]) ? $labs[0]['lab_id'] : 0);

// Get schedules for selected lab
$schedules = [];
if ($selected_lab_id > 0) {
    $schedules_query = "SELECT ls.*, l.lab_name 
                        FROM lab_schedules ls
                        JOIN labs l ON ls.lab_id = l.lab_id
                        WHERE ls.lab_id = ?
                        ORDER BY FIELD(ls.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
                                ls.start_time";
    
    $stmt = $conn->prepare($schedules_query);
    $stmt->bind_param("i", $selected_lab_id);
    $stmt->execute();
    $schedules_result = $stmt->get_result();
    
    if ($schedules_result && $schedules_result->num_rows > 0) {
        while ($row = $schedules_result->fetch_assoc()) {
            $schedules[] = $row;
        }
    }
}

// Group schedules by day
$schedules_by_day = [];
foreach ($schedules as $schedule) {
    $day = $schedule['day_of_week'];
    if (!isset($schedules_by_day[$day])) {
        $schedules_by_day[$day] = [];
    }
    $schedules_by_day[$day][] = $schedule;
}

// Days of the week in order
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get today's day of week
$today = date('l');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedules - SitIn System</title>
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
        .status-available {
            background-color: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .status-occupied {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .status-maintenance {
            background-color: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .status-reserved {
            background-color: #e0f2fe;
            border-color: #3b82f6;
            color: #1e40af;
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
                        <a href="history.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">History</a>
                        <a href="lab_schedules.php" class="px-3 py-2 rounded bg-primary-800 transition">Lab Schedules</a>
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
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
        <a href="lab_schedules.php" class="block px-4 py-2 text-white bg-primary-900">Lab Schedules</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 flex-grow">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Laboratory Schedules</h1>
        
        <!-- Lab Selection -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Select Laboratory</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <?php foreach ($labs as $lab): ?>
                    <a href="?lab_id=<?php echo $lab['lab_id']; ?>" 
                       class="block text-center py-3 px-4 rounded-lg border-2 transition
                              <?php echo ($selected_lab_id == $lab['lab_id']) 
                                    ? 'bg-primary-100 border-primary-500 text-primary-800' 
                                    : 'bg-white border-gray-200 hover:bg-gray-50'; ?>">
                        <?php echo htmlspecialchars($lab['lab_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Schedule information with notes about status colors -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">
                    <?php if ($selected_lab_id > 0): ?>
                        Schedule for <?php echo htmlspecialchars($labs[array_search($selected_lab_id, array_column($labs, 'lab_id'))]['lab_name']); ?>
                    <?php else: ?>
                        Select a laboratory to view its schedule
                    <?php endif; ?>
                </h2>
                
                <div class="flex space-x-2">
                    <a href="reservation.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded transition">
                        <i class="fas fa-calendar-plus mr-1"></i> Make Reservation
                    </a>
                </div>
            </div>
            
            <!-- Status legend -->
            <div class="mb-4 flex flex-wrap gap-3">
                <span class="px-3 py-1 rounded-full text-sm status-available">Available</span>
                <span class="px-3 py-1 rounded-full text-sm status-occupied">Occupied</span>
                <span class="px-3 py-1 rounded-full text-sm status-maintenance">Maintenance</span>
                <span class="px-3 py-1 rounded-full text-sm status-reserved">Reserved</span>
                
                <div class="ml-auto text-sm">
                    <span class="font-semibold">Today:</span> <?php echo $today; ?>
                </div>
            </div>
            
            <!-- Schedule Table -->
            <?php if ($selected_lab_id > 0): ?>
                <?php if (empty($schedules)): ?>
                    <div class="text-center py-8 text-gray-500">
                        No schedule information available for this laboratory.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Day
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Time
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Notes
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($days_of_week as $day): ?>
                                    <?php if (isset($schedules_by_day[$day])): ?>
                                        <?php foreach ($schedules_by_day[$day] as $schedule): ?>
                                            <tr class="<?php echo ($day == $today) ? 'bg-yellow-50' : ''; ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo $schedule['day_of_week']; ?>
                                                        <?php if ($day == $today): ?>
                                                            <span class="ml-2 text-xs font-semibold text-amber-600">(Today)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php 
                                                        echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                                             date('h:i A', strtotime($schedule['end_time'])); 
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-<?php echo $schedule['status']; ?>">
                                                        <?php echo ucfirst($schedule['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo !empty($schedule['notes']) ? htmlspecialchars($schedule['notes']) : '-'; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Please select a laboratory above to view its schedule.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Reservation guidance -->
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">Reservation Information</h3>
                <ul class="list-disc list-inside text-sm text-blue-700 space-y-1">
                    <li>You cannot reserve lab times marked as <span class="font-medium">Occupied</span>, <span class="font-medium">Maintenance</span>, or <span class="font-medium">Reserved</span>.</li>
                    <li>Reservations can only be made for today's date during <span class="font-medium">Available</span> time slots.</li>
                    <li>Each reservation requires approval from laboratory staff.</li>
                    <li>For questions about schedule conflicts, please contact the IT department.</li>
                </ul>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>

    <script>
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
    </script>
</body>
</html> 