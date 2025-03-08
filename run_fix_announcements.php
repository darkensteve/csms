<?php
session_start();

// Check if user is admin (for security)
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Announcements Table | SitIn System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-md w-full max-w-2xl p-6">
        <h1 class="text-2xl font-bold mb-4 text-blue-700 flex items-center">
            <i class="fas fa-tools mr-2"></i> Announcements Table Repair Tool
        </h1>
        
        <?php if (!$is_admin): ?>
        <div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i> 
            Note: It's recommended to run this utility while logged in as an administrator.
        </div>
        <?php endif; ?>
        
        <div class="bg-gray-100 p-4 rounded mb-4">
            <h2 class="font-bold mb-2">What this tool does:</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>Checks if the announcements table exists in the database</li>
                <li>Creates the table if it doesn't exist</li>
                <li>Adds missing columns required for proper functionality</li>
            </ul>
        </div>
        
        <div class="border-t border-gray-200 my-4 pt-4">
            <h2 class="font-bold mb-2">Repair Results:</h2>
            <div class="bg-gray-100 p-4 rounded text-sm font-mono overflow-auto max-h-64">
                <?php 
                // Include the fix script and capture its output
                ob_start();
                include 'fix_announcements_table.php';
                $output = ob_get_clean();
                
                echo nl2br(htmlspecialchars($output));
                ?>
            </div>
        </div>
        
        <div class="flex justify-between mt-4">
            <a href="admin.php" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                <i class="fas fa-arrow-left mr-1"></i> Return to Admin Panel
            </a>
            <button onclick="window.location.reload()" class="bg-gray-600 text-white py-2 px-4 rounded hover:bg-gray-700">
                <i class="fas fa-sync-alt mr-1"></i> Run Again
            </button>
        </div>
    </div>
    
    <p class="mt-4 text-gray-500 text-sm text-center">
        &copy; 2024 SitIn System. This utility helps resolve database structure issues.
    </p>
</body>
</html>
