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

// Check if tables exist
$result = $conn->query("SHOW TABLES LIKE 'lab_resources'");
$tablesExist = ($result->num_rows > 0);

// Get filter parameters
$category_filter = isset($_GET['category']) && is_numeric($_GET['category']) ? intval($_GET['category']) : 0;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construct the WHERE clause for filtering
$where_clauses = [];
$params = [];
$types = '';

if ($category_filter > 0) {
    $where_clauses[] = "r.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if ($type_filter === 'link' || $type_filter === 'file') {
    $where_clauses[] = "r.resource_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'ss';
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get categories
$categories = [];
if ($tablesExist) {
    $category_query = "SELECT * FROM lab_resource_categories ORDER BY category_name";
    $category_result = $conn->query($category_query);
    if ($category_result && $category_result->num_rows > 0) {
        while ($row = $category_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Get resources with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

$resources = [];
$total_pages = 0;

if ($tablesExist) {
    // Count total resources for pagination
    $count_query = "SELECT COUNT(*) as total FROM lab_resources r $where_clause";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $total_rows = $count_result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $count_result = $conn->query($count_query);
        $total_rows = $count_result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_rows / $items_per_page);
    
    // Get resources with category name
    $resources_query = "SELECT r.*, c.category_name 
                      FROM lab_resources r 
                      LEFT JOIN lab_resource_categories c ON r.category_id = c.category_id 
                      $where_clause
                      ORDER BY r.created_at DESC 
                      LIMIT ?, ?";
    
    $stmt = $conn->prepare($resources_query);
    if (!empty($params)) {
        $params[] = $offset;
        $params[] = $items_per_page;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $offset, $items_per_page);
    }
    
    $stmt->execute();
    $resources_result = $stmt->get_result();
    
    if ($resources_result && $resources_result->num_rows > 0) {
        while ($row = $resources_result->fetch_assoc()) {
            $resources[] = $row;
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Resources</title>
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
        
        .resource-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease-in-out;
        }
        
        .resource-card:hover {
            transform: translateY(-4px);
        }
        
        .card-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }
        
        .card-footer {
            margin-top: auto;
        }
        
        .skeleton {
            animation: skeleton-loading 1s linear infinite alternate;
        }
        
        @keyframes skeleton-loading {
            0% {
                background-color: #e5e7eb;
            }
            100% {
                background-color: #f3f4f6;
            }
        }
    </style>
</head>

<body class="font-sans min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-bold">SitIn</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Home</a>
                        <a href="lab_schedules.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Lab Schedules</a>
                        <a href="lab_resources.php" class="px-3 py-2 bg-primary-600 rounded transition">Lab Resources</a>
                        <a href="reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Reservation</a>
                        <a href="history.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">History</a>
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
        <a href="lab_schedules.php" class="block px-4 py-2 text-white hover:bg-primary-900">Lab Schedules</a>
        <a href="lab_resources.php" class="block px-4 py-2 bg-primary-700 text-white">Lab Resources</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 container mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Lab Resources</h1>
            <p class="text-gray-600">Browse and download educational resources for your lab work</p>
        </div>
        
        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search Box -->
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search resources..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Type Filter -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select id="type" name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Types</option>
                        <option value="link" <?= $type_filter === 'link' ? 'selected' : '' ?>>Links</option>
                        <option value="file" <?= $type_filter === 'file' ? 'selected' : '' ?>>Files</option>
                    </select>
                </div>
                
                <!-- Filter Button -->
                <div class="flex items-end md:col-span-4">
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-medium px-4 py-2 rounded mr-2 flex items-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    
                    <?php if (!empty($search_query) || $category_filter > 0 || !empty($type_filter)): ?>
                        <a href="lab_resources.php" class="bg-gray-200 text-gray-700 hover:bg-gray-300 font-medium px-4 py-2 rounded flex items-center">
                            <i class="fas fa-times-circle mr-2"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if ($tablesExist): ?>
            <?php if (count($resources) > 0): ?>
                <!-- Resources Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                    <?php foreach ($resources as $resource): ?>
                        <div class="resource-card bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                            <!-- Card Header with Category -->
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex justify-between items-start">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php if ($resource['category_name'] === 'Programming'): ?>
                                            bg-blue-100 text-blue-800
                                        <?php elseif ($resource['category_name'] === 'Database'): ?>
                                            bg-green-100 text-green-800
                                        <?php elseif ($resource['category_name'] === 'Networking'): ?>
                                            bg-purple-100 text-purple-800
                                        <?php elseif ($resource['category_name'] === 'Web Development'): ?>
                                            bg-orange-100 text-orange-800
                                        <?php elseif ($resource['category_name'] === 'Software Engineering'): ?>
                                            bg-red-100 text-red-800
                                        <?php elseif ($resource['category_name'] === 'Cybersecurity'): ?>
                                            bg-yellow-100 text-yellow-800
                                        <?php elseif ($resource['category_name'] === 'Data Science'): ?>
                                            bg-teal-100 text-teal-800
                                        <?php else: ?>
                                            bg-gray-100 text-gray-800
                                        <?php endif; ?>
                                    ">
                                        <?= htmlspecialchars($resource['category_name']) ?>
                                    </span>
                                    
                                    <span class="inline-flex items-center text-xs text-gray-500">
                                        <?php if ($resource['resource_type'] === 'link'): ?>
                                            <i class="fas fa-link text-blue-500 mr-1"></i> Link
                                        <?php else: ?>
                                            <i class="fas fa-file-alt text-green-500 mr-1"></i> File
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="p-4 card-body">
                                <h3 class="font-semibold text-lg text-gray-800 mb-2"><?= htmlspecialchars($resource['title']) ?></h3>
                                
                                <?php if (!empty($resource['description'])): ?>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-3"><?= htmlspecialchars($resource['description']) ?></p>
                                <?php else: ?>
                                    <p class="text-gray-400 text-sm mb-4 italic">No description available</p>
                                <?php endif; ?>
                                
                                <!-- Card Footer -->
                                <div class="mt-auto card-footer">
                                    <div class="flex justify-between items-center mt-4">
                                        <span class="text-xs text-gray-500"><?= date('M d, Y', strtotime($resource['created_at'])) ?></span>
                                        
                                        <?php if ($resource['resource_type'] === 'link' && !empty($resource['resource_link'])): ?>
                                            <a href="<?= htmlspecialchars($resource['resource_link']) ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1.5 rounded flex items-center">
                                                <i class="fas fa-external-link-alt mr-1"></i> Visit
                                            </a>
                                        <?php elseif ($resource['resource_type'] === 'file' && !empty($resource['file_path'])): ?>
                                            <a href="../<?= htmlspecialchars($resource['file_path']) ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded flex items-center">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-8">
                        <nav class="inline-flex rounded-md shadow">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $category_filter ? '&category=' . $category_filter : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>" class="px-3 py-2 bg-white text-gray-500 hover:bg-gray-50 rounded-l-md border border-gray-300">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?><?= $category_filter ? '&category=' . $category_filter : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>" class="px-3 py-2 <?= $i === $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' ?> border-t border-b border-gray-300">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $category_filter ? '&category=' . $category_filter : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>" class="px-3 py-2 bg-white text-gray-500 hover:bg-gray-50 rounded-r-md border border-gray-300">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No Resources Found -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <img src="../assets/empty.svg" alt="No resources found" class="w-32 h-32 mx-auto mb-4 opacity-50">
                    <h3 class="text-lg font-medium text-gray-600 mb-2">No resources found</h3>
                    <p class="text-gray-500 mb-4">
                        <?php if (!empty($search_query) || $category_filter > 0 || !empty($type_filter)): ?>
                            No resources match your search criteria. Try adjusting your filters.
                        <?php else: ?>
                            There are no lab resources available at this time.
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($search_query) || $category_filter > 0 || !empty($type_filter)): ?>
                        <a href="lab_resources.php" class="text-primary-600 font-medium hover:text-primary-800">
                            <i class="fas fa-times-circle mr-1"></i> Clear all filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Tables don't exist yet -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <img src="../assets/setup.svg" alt="Setup needed" class="w-32 h-32 mx-auto mb-4 opacity-50">
                <h3 class="text-lg font-medium text-gray-600 mb-2">Resource system not configured</h3>
                <p class="text-gray-500">The lab resources feature is not set up yet. Please contact an administrator.</p>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?> 