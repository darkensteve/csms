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

// Get top 5 students by points
$top_points_query = "SELECT u.IDNO as student_id, CONCAT(u.FIRSTNAME, ' ', u.LASTNAME) as student_name, 
                    u.points, u.COURSE as course, u.YEARLEVEL as year_level
                FROM users u
                WHERE u.points > 0
                ORDER BY u.points DESC
                LIMIT 5";
$top_points_result = $conn->query($top_points_query);
$top_points = [];

if ($top_points_result && $top_points_result->num_rows > 0) {
    while ($row = $top_points_result->fetch_assoc()) {
        $top_points[] = $row;
    }
}

// Get top 5 students by number of sessions
$top_sessions_query = "SELECT 
                    s.student_id,
                    s.student_name,
                    COUNT(s.session_id) as total_sessions,
                    u.COURSE as course,
                    u.YEARLEVEL as year_level
                FROM 
                    sit_in_sessions s
                LEFT JOIN users u ON s.student_id = u.IDNO
                WHERE 
                    s.status = 'inactive'
                GROUP BY 
                    s.student_id, s.student_name
                ORDER BY 
                    total_sessions DESC
                LIMIT 5";
$top_sessions_result = $conn->query($top_sessions_query);
$top_sessions = [];

if ($top_sessions_result && $top_sessions_result->num_rows > 0) {
    while ($row = $top_sessions_result->fetch_assoc()) {
        $top_sessions[] = $row;
    }
}

// Get the current user's rankings
$user_info_query = "SELECT IDNO, FIRSTNAME, LASTNAME, points FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_info_query);
$stmt->bind_param("i", $loggedInUserId);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();
$stmt->close();

$user_points_rank = 0;
$user_sessions_rank = 0;

if ($user_info) {
    // Get user's rank in points
    $points_rank_query = "SELECT COUNT(*) + 1 as rank FROM users WHERE points > ?";
    $stmt = $conn->prepare($points_rank_query);
    $stmt->bind_param("i", $user_info['points']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $user_points_rank = $row['rank'];
    $stmt->close();
    
    // Get user's rank in sessions
    $sessions_rank_query = "SELECT COUNT(*) + 1 as rank FROM (
                            SELECT student_id, COUNT(*) as sessions 
                            FROM sit_in_sessions 
                            WHERE status = 'inactive' 
                            GROUP BY student_id
                        ) as session_counts
                        INNER JOIN (
                            SELECT student_id, COUNT(*) as user_sessions 
                            FROM sit_in_sessions 
                            WHERE student_id = ? AND status = 'inactive'
                            GROUP BY student_id
                        ) as user_count
                        WHERE session_counts.sessions > user_count.user_sessions";
    $stmt = $conn->prepare($sessions_rank_query);
    $stmt->bind_param("s", $user_info['IDNO']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_sessions_rank = $row['rank'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Leaderboard</title>
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
        
        .leaderboard-card {
            transition: all 0.3s ease;
        }
        
        .leaderboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .rank-1 {
            background-color: #fef3c7;
            color: #d97706;
            border: 2px solid #f59e0b;
        }
        
        .rank-2 {
            background-color: #f1f5f9;
            color: #64748b;
            border: 2px solid #94a3b8;
        }
        
        .rank-3 {
            background-color: #fff7ed;
            color: #c2410c;
            border: 2px solid #ea580c;
        }
        
        .card-header {
            background: linear-gradient(to right, #0369a1, #075985);
        }
        
        .user-highlight {
            background-color: #e0f2fe;
            border: 2px solid #38bdf8;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(14, 165, 233, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0);
            }
        }
    </style>
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
                        <a href="leaderboard.php" class="px-3 py-2 bg-primary-800 rounded transition">Leaderboard</a>
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
        <a href="leaderboard.php" class="block px-4 py-2 text-white bg-primary-900">Leaderboard</a>
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <div class="container mx-auto p-4 md:p-8 flex-1 overflow-y-auto">
            <!-- Your Rank Card -->
            <?php if (isset($user_info)): ?>
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="card-header text-white px-6 py-4 rounded-t-xl">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-medal mr-2"></i> Your Ranking
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-primary-50 rounded-lg p-4 border border-primary-200">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-star text-amber-500 text-xl mr-2"></i>
                                <h3 class="text-lg font-semibold text-primary-800">Points Ranking</h3>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-3xl font-bold text-primary-700">#<?php echo $user_points_rank; ?></p>
                                    <p class="text-gray-500"><?php echo $user_info['points'] ?? 0; ?> points</p>
                                </div>
                                <div class="bg-white rounded-full p-3 shadow-md">
                                    <i class="fas fa-trophy text-amber-500 text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-primary-50 rounded-lg p-4 border border-primary-200">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-user-clock text-blue-500 text-xl mr-2"></i>
                                <h3 class="text-lg font-semibold text-primary-800">Sessions Ranking</h3>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-3xl font-bold text-primary-700">#<?php echo $user_sessions_rank; ?></p>
                                    <p class="text-gray-500">Sit-in sessions completed</p>
                                </div>
                                <div class="bg-white rounded-full p-3 shadow-md">
                                    <i class="fas fa-award text-blue-500 text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Leaderboard Tabs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Points Leaderboard -->
                <div class="bg-white rounded-xl shadow-md mb-6">
                    <div class="card-header text-white px-6 py-4 rounded-t-xl">
                        <h2 class="text-xl font-semibold flex items-center">
                            <i class="fas fa-star mr-2"></i> Top 5 by Points
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (count($top_points) > 0): ?>
                            <div class="space-y-3">
                                <?php foreach ($top_points as $index => $student): ?>
                                    <div class="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition 
                                    <?php echo (isset($user_info) && $student['student_id'] === $user_info['IDNO']) ? 'user-highlight' : ''; ?>">
                                        <div class="<?php echo $index < 3 ? "rank-badge rank-" . ($index + 1) : "bg-gray-200 text-gray-700 rank-badge"; ?> mr-3">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div class="flex-grow">
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                            <div class="text-xs text-gray-500 flex items-center">
                                                <span class="mr-2"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                                <?php if (!empty($student['course'])): ?>
                                                    <span class="mr-2">|</span>
                                                    <span><?php echo htmlspecialchars($student['course']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($student['year_level'])): ?>
                                                    <span class="mr-2">|</span>
                                                    <span>Year <?php echo htmlspecialchars($student['year_level']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-amber-600"><?php echo number_format($student['points']); ?></div>
                                            <div class="text-xs text-gray-500">points</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-500 italic flex items-center justify-center p-6 border border-dashed border-gray-300 rounded-lg">
                                <i class="fas fa-info-circle mr-2"></i> No points data available yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sessions Leaderboard -->
                <div class="bg-white rounded-xl shadow-md mb-6">
                    <div class="card-header text-white px-6 py-4 rounded-t-xl">
                        <h2 class="text-xl font-semibold flex items-center">
                            <i class="fas fa-user-check mr-2"></i> Top 5 by Sessions
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (count($top_sessions) > 0): ?>
                            <div class="space-y-3">
                                <?php foreach ($top_sessions as $index => $student): ?>
                                    <div class="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition
                                    <?php echo (isset($user_info) && $student['student_id'] === $user_info['IDNO']) ? 'user-highlight' : ''; ?>">
                                        <div class="<?php echo $index < 3 ? "rank-badge rank-" . ($index + 1) : "bg-gray-200 text-gray-700 rank-badge"; ?> mr-3">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div class="flex-grow">
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                            <div class="text-xs text-gray-500 flex items-center">
                                                <span class="mr-2"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                                <?php if (!empty($student['course'])): ?>
                                                    <span class="mr-2">|</span>
                                                    <span><?php echo htmlspecialchars($student['course']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($student['year_level'])): ?>
                                                    <span class="mr-2">|</span>
                                                    <span>Year <?php echo htmlspecialchars($student['year_level']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-blue-600"><?php echo number_format($student['total_sessions']); ?></div>
                                            <div class="text-xs text-gray-500">sessions</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-500 italic flex items-center justify-center p-6 border border-dashed border-gray-300 rounded-lg">
                                <i class="fas fa-info-circle mr-2"></i> No session data available yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-4">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>

    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        
        // Mobile dropdown toggles
        document.querySelectorAll('.mobile-dropdown-button').forEach(button => {
            button.addEventListener('click', function() {
                const content = this.nextElementSibling;
                content.classList.toggle('hidden');
            });
        });
    </script>
</body>
</html> 