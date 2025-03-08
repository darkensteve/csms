<?php
session_start();
require_once 'config.php';

// Check if already logged in
if(isset($_SESSION['admin_id'])) {
    header("Location: admin.php?message=loggedin");
    exit;
}

$error = '';
$success = '';

// Check for logout message
if(isset($_GET['message']) && $_GET['message'] == 'logout') {
    $success = "You have been successfully logged out.";
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if(empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check the admin credentials in the database
        $sql = "SELECT admin_id, username, password FROM admin WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if(password_verify($password, $admin['password'])) {
                // Password is correct, start a new session
                session_regenerate_id();
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['is_admin'] = true;
                
                header("Location: admin.php?message=loggedin");
                exit;
            } else {
                $error = "The password you entered is incorrect.";
            }
        } else {
            $error = "No account found with that username.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Sit-In Management System</title>
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
                <h1 class="text-3xl font-bold text-gray-800">Admin Login</h1>
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
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-user text-primary-500"></i>
                        </div>
                        <input type="text" id="username" name="username" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter your username">
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-lock text-primary-500"></i>
                        </div>
                        <input type="password" id="password" name="password" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter your password">
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" type="checkbox" class="h-4 w-4 text-primary-500 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-primary-500 hover:text-accent hover:underline transition duration-200">Forgot password?</a>
                </div>
                <button type="submit" class="w-full bg-primary-500 hover:bg-accent text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition duration-200 transform hover:-translate-y-0.5">
                    Login
                </button>
            </form>
            
            <div class="mt-8 text-center border-t border-gray-200 pt-6">
                <a href="index.php" class="text-sm text-primary-500 hover:text-accent flex items-center justify-center gap-2 hover:underline transition duration-200">
                    <i class="fas fa-arrow-left"></i> Back to Main Site
                </a>
            </div>
        </div>
    </div>
</body>
</html>
