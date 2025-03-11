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

// Check for login_success session flag
if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
    $showLoginSuccess = true;
    // Clear the flag so it only shows once
    $_SESSION['login_success'] = false;
} else {
    $showLoginSuccess = false;
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

// Fetch announcements from database
$announcements = [];
$query = "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
        .card {
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        .card-content {
            flex: 1;
            overflow-y: auto;
        }
        /* Add specific style for student info card to prevent scrolling */
        .student-info-card .card-content {
            overflow-y: visible;
            flex: 0 1 auto;
        }
        .student-info-card {
            height: auto;
            min-height: 500px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --success-color: #43a047;
            --error-color: #e53935;
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
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            z-index: 1000;
            max-width: 350px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .notification.success {
            background-color: var(--success-color);
        }
        
        .notification.error {
            background-color: var(--error-color);
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for message parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('message')) {
                const message = urlParams.get('message');
                if (message === 'login') {
                    showNotification('Successfully Logged In!', 'success');
                    
                    // Remove the message parameter from URL without reloading
                    const newUrl = window.location.pathname;
                    history.pushState({}, document.title, newUrl);
                }
            }
            
            // Check for PHP session login_success flag
            const showLoginSuccess = <?php echo $showLoginSuccess ? 'true' : 'false'; ?>;
            if (showLoginSuccess) {
                showNotification('Successfully Logged In!', 'success');
            }
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
    </script>
</head>
<body class="font-sans h-screen flex flex-col overflow-hidden">
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
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Dashboard Main Content - Fixed height, no scroll -->
    <div class="flex-1 flex flex-col overflow-hidden px-4 py-6 md:px-8">
        <div class="container mx-auto flex-1 flex flex-col overflow-hidden">
            
            <!-- Cards section - Scrollable -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 overflow-y-auto flex-1">
                <!-- Student Information Card -->
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition card student-info-card border border-gray-100">
                    <div class="p-6 flex flex-col h-full">
                        <div class="border-b border-gray-100 pb-4 mb-4">
                            <h2 class="text-xl font-bold text-primary-700">Student Information</h2>
                        </div>
                        <?php
                        // Database connection
                        $servername = "localhost";
                        $username = "root";
                        $password = "";
                        $dbname = "csms";

                        $conn = new mysqli($servername, $username, $password, $dbname);

                        if ($conn->connect_error) {
                            die("Connection failed: " . $conn->connect_error);
                        }

                        // Check if remaining_sessions column exists in users table
                        $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'remaining_sessions'");
                        
                        if ($column_check->num_rows == 0) {
                            // Add remaining_sessions column if it doesn't exist
                            $conn->query("ALTER TABLE users ADD COLUMN remaining_sessions INT(11) NOT NULL DEFAULT 30");
                        }

                        // Get user data with remaining sessions
                        $sql = "SELECT idNo, firstName, lastName, middleName, course, yearLevel, email, address, profile_picture, remaining_sessions 
                                FROM users WHERE user_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $loggedInUserId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $idNo = $row['idNo'] ?? '';
                            $firstName = $row['firstName'] ?? '';
                            $lastName = $row['lastName'] ?? '';
                            $middleName = $row['middleName'] ?? '';
                            $course = $row['course'] ?? '';
                            $year = $row['yearLevel'] ?? '';
                            $email = $row['email'] ?? '';
                            $address = $row['address'] ?? '';
                            $profilePicture = $row['profile_picture'] ?? 'profile.jpg';
                            // Get remaining sessions from database or use default
                            $remainingSessions = $row['remaining_sessions'] ?? 30;
                        } else {
                            echo "No user data found.";
                            $firstName = "User";
                            $lastName = "Not Found";
                            $course = "N/A";
                            $year = "N/A";
                            $email = "N/A";
                            $address = "N/A";
                            $profilePicture = "profile.jpg";
                            $remainingSessions = 30;
                        }

                        $stmt->close();
                        $conn->close();
                        ?>
                        
                        <div class="flex flex-col items-center mb-3">
                            <div class="w-28 h-28 mb-2 rounded-full border-4 border-primary-100 overflow-hidden">
                                <img src="<?php echo $profilePicture; ?>" alt="Student Photo" class="w-full h-full object-cover" 
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($firstName . ' ' . $lastName); ?>&background=0369a1&color=fff&size=128';">
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo $firstName . ' ' . $lastName; ?></h3>
                            <?php if (!empty($idNo)): ?>
                            <p class="text-sm text-gray-500"><?php echo $idNo; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-content">
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-book text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Course</p>
                                        <p class="font-medium"><?php echo $course; ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-graduation-cap text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Year Level</p>
                                        <p class="font-medium"><?php echo $year; ?></p>
                                    </div>
                                </div>
                                
                                <!-- New Remaining Sessions -->
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-ticket-alt text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Remaining Sessions</p>
                                        <p class="font-medium"><?php echo $remainingSessions; ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-envelope text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="font-medium"><?php echo $email; ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Address</p>
                                        <p class="font-medium"><?php echo $address; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Announcement Section -->
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition card border border-gray-100">
                    <div class="p-6 flex flex-col h-full">
                        <div class="border-b border-gray-100 pb-4 mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-bullhorn text-blue-600"></i>
                                </div>
                                <h2 class="text-xl font-bold text-gray-800 ml-3">Announcements</h2>
                            </div>
                        </div>
                        
                        <div class="card-content space-y-4 pr-2">
                            <?php if (count($announcements) > 0): ?>
                                <?php foreach ($announcements as $announcement): ?>
                                <div class="bg-blue-50 rounded-lg p-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($announcement['admin_username'] ?? 'CCS Admin'); ?>
                                        </h3>
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('Y-M-d', strtotime($announcement['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-700 mb-2 font-medium"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                    <p class="text-gray-600 text-sm"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bg-blue-50 rounded-lg p-4">
                                    <p class="text-gray-600 text-center">No announcements available at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Rules and Regulations Section -->
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition card border border-gray-100">
                    <div class="p-6 flex flex-col h-full">
                        <div class="border-b border-gray-100 pb-4 mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-gavel text-green-600"></i>
                                </div>
                                <h2 class="text-xl font-bold text-gray-800 ml-3">Rules and Regulations</h2>
                            </div>
                        </div>
                        
                        <div class="card-content pr-2 text-gray-700 text-sm">
                            <ol class="list-decimal space-y-2 ml-5">
                                <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                                <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                                <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                                <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                                <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                                <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                                <li>Observe proper decorum while inside the laboratory.
                                    <ul class="list-disc ml-5 mt-1 space-y-1">
                                        <li>Do not get inside the lab unless the instructor is present.</li>
                                        <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                                        <li>Follow the seating arrangement of your instructor.</li>
                                        <li>At the end of class, all software programs must be closed.</li>
                                        <li>Return all chairs to their proper places after using.</li>
                                    </ul>
                                </li>
                                <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                                <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                                <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                                <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                                <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
                            </ol>
                            
                            <h3 class="font-bold text-green-700 mt-4 mb-2">DISCIPLINARY ACTION</h3>
                            <ul class="list-disc ml-5 space-y-2">
                                <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                                <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-3">
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
    </script>
</body>
</html>
