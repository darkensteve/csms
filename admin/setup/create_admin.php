<?php
// Include database connection
require_once '../includes/db_connect.php';

// Check if form submitted or direct access
$mode = isset($_POST['submit']) ? 'form_submit' : 'direct_access';
$message = '';
$error = '';
$success = '';

// Function to create admin account
function createAdminAccount($conn, $username, $password, $email) {
    // Check if admin table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'admin'");
    
    if ($table_check->num_rows == 0) {
        // Create admin table if it doesn't exist
        $create_table = "CREATE TABLE `admin` (
            `admin_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `username` varchar(50) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL UNIQUE,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table)) {
            return "Failed to create admin table: " . $conn->error;
        }
    }
    
    // Check if admin already exists
    $check_admin = "SELECT * FROM admin WHERE username = ?";
    $stmt = $conn->prepare($check_admin);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing admin
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_query = "UPDATE admin SET password = ?, email = ? WHERE username = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sss", $hashed_password, $email, $username);
        
        if ($update_stmt->execute()) {
            return "success_update";
        } else {
            return "Failed to update admin account: " . $conn->error;
        }
    } else {
        // Create new admin
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO admin (username, password, email) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sss", $username, $hashed_password, $email);
        
        if ($insert_stmt->execute()) {
            return "success_create";
        } else {
            return "Failed to create admin account: " . $conn->error;
        }
    }
}

// Process form submission
if ($mode === 'form_submit') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    
    if (empty($username) || empty($password) || empty($email)) {
        $error = "All fields are required.";
    } else {
        $result = createAdminAccount($conn, $username, $password, $email);
        
        if ($result === "success_create") {
            $success = "Admin account created successfully. You can now login.";
        } else if ($result === "success_update") {
            $success = "Admin account updated successfully. You can now login with the new password.";
        } else {
            $error = $result;
        }
    }
} else if ($mode === 'direct_access') {
    // Default admin credentials
    $default_username = "admin";
    $default_password = "admin123";
    $default_email = "admin@example.com";
    
    // Try to create default admin
    $result = createAdminAccount($conn, $default_username, $default_password, $default_email);
    
    if ($result === "success_create") {
        $message = "Default admin account created successfully with the following credentials:<br>
                   Username: <strong>admin</strong><br>
                   Password: <strong>admin123</strong><br><br>
                   Please login and change the password immediately.";
    } else if ($result === "success_update") {
        $message = "Default admin account password reset to:<br>
                   Username: <strong>admin</strong><br>
                   Password: <strong>admin123</strong><br><br>
                   Please login and change the password immediately.";
    } else {
        $error = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account | Sit-In Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7fc',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#1e4b7a',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        accent: '#3573b1',
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-background {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .card-shadow {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4 gradient-background">
        <div class="bg-white p-8 rounded-xl card-shadow w-full max-w-md border-t-4 border-primary-500">
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="bg-primary-500 text-white p-4 rounded-full">
                        <i class="fas fa-user-shield text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-gray-800">Create Admin Account</h1>
                <p class="text-gray-600 mt-2">Sit-In Management System</p>
            </div>
            
            <?php if(!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?php echo $success; ?></p>
                            <a href="../auth/login_admin.php" class="mt-2 inline-block font-medium text-green-800 hover:underline">
                                <i class="fas fa-sign-in-alt mr-1"></i> Go to Login Page
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($message)): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?php echo $message; ?></p>
                            <a href="../auth/login_admin.php" class="mt-2 inline-block font-medium text-blue-800 hover:underline">
                                <i class="fas fa-sign-in-alt mr-1"></i> Go to Login Page
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(empty($success) && empty($message)): ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-user text-primary-500"></i>
                        </div>
                        <input type="text" id="username" name="username" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter admin username">
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-lock text-primary-500"></i>
                        </div>
                        <input type="password" id="password" name="password" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter admin password">
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-envelope text-primary-500"></i>
                        </div>
                        <input type="email" id="email" name="email" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter admin email">
                    </div>
                </div>
                <button type="submit" name="submit" class="w-full bg-primary-500 hover:bg-accent text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition duration-200 transform hover:-translate-y-0.5">
                    Create Admin Account
                </button>
            </form>
            <?php endif; ?>
            
            <div class="mt-8 text-center border-t border-gray-200 pt-6">
                <a href="../auth/login_admin.php" class="text-sm text-primary-500 hover:text-accent flex items-center justify-center gap-2 hover:underline transition duration-200">
                    <i class="fas fa-arrow-left"></i> Back to Login Page
                </a>
            </div>
        </div>
    </div>
</body>
</html>
