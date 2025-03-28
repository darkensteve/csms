<?php
session_start();
require_once '../../includes/db_connect.php';  // Update path to database connection

// Check if already logged in
if(isset($_SESSION['admin_id'])) {
    // Update redirection path
    header("Location: ../admin.php?message=loggedin");
    exit;
}

$error = '';
$success = '';
$admin_setup_needed = false;

// Check if admin table exists and has records
$check_admin_table = "SHOW TABLES LIKE 'admin'";
$admin_table_exists = $conn->query($check_admin_table);

if ($admin_table_exists->num_rows == 0) {
    $admin_setup_needed = true;
} else {
    // Check if there are any admin records
    $check_admin_users = "SELECT COUNT(*) as count FROM admin";
    $admin_count_result = $conn->query($check_admin_users);
    if ($admin_count_result && $admin_count_result->fetch_assoc()['count'] == 0) {
        $admin_setup_needed = true;
    }
}

// Check for logout message - add support for session-based messaging
if(isset($_GET['message']) && $_GET['message'] == 'logout') {
    $success = "You have been successfully logged out.";
}

// Check for session-based notifications (will override the URL parameter)
if(isset($_SESSION['temp_success_message'])) {
    $success = $_SESSION['temp_success_message'];
    unset($_SESSION['temp_success_message']);
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
                
                // Update redirection path
                header("Location: ../admin.php?message=loggedin");
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
    <title>Admin Login - CCS SITIN Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .logo {
            height: 55px;
            width: auto;
        }
    </style>
    <script>
        window.onload = function() {
            // Check for logout or login message
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('message')) {
                const message = urlParams.get('message');
                if (message === 'logout') {
                    showNotification('Successfully Logged Out!', 'success');
                } else if (message === 'login') {
                    showNotification('Successfully Logged In!', 'success');
                }
            }
            
            // Add toggle password visibility functionality
            const togglePassword = document.querySelector('.password-toggle');
            const password = document.querySelector('#password');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="far fa-eye"></i>' : '<i class="far fa-eye-slash"></i>';
                });
            }
        };
        
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
<body class="bg-gray-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4 gradient-background">
        <div class="bg-white p-8 rounded-xl card-shadow w-full max-w-md border-t-4 border-primary-500">
            <div class="text-center mb-8">
                <!-- Keep the logo section from original design -->
                <div class="flex justify-center gap-8 mb-4">
                    <img src="../../uclogo.jpg" alt="UC Logo" class="logo">
                    <img src="../../ccs.png" alt="CCS Logo" class="logo">
                </div>
                <h1 class="text-3xl font-bold text-gray-800">ADMINISTRATOR LOGIN</h1>
                <p class="text-gray-600 mt-2">CCS SITIN MONITORING SYSTEM</p>
            </div>
            
            <?php if($admin_setup_needed): ?>
            <div class="bg-amber-100 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">No admin account found!</p>
                        <p class="mt-1">You need to create an admin account first.</p>
                        <a href="../setup/create_admin.php" class="mt-2 inline-block font-medium text-amber-800 hover:underline">
                            <i class="fas fa-user-plus mr-1"></i> Create Admin Account
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6" autocomplete="off">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-user text-primary-500"></i>
                        </div>
                        <input type="text" id="username" name="username" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter your username" autofocus>
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-lock text-primary-500"></i>
                        </div>
                        <input type="password" id="password" name="password" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 transition duration-200" required placeholder="Enter your password">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 password-toggle cursor-pointer">
                            <i class="far fa-eye text-gray-500"></i>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-primary-500 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-primary-500 hover:text-accent hover:underline transition duration-200">Forgot password?</a>
                </div>
                
                <button type="submit" class="w-full bg-primary-500 hover:bg-accent text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition duration-200 transform hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </button>
            </form>
            
            <div class="mt-8 text-center border-t border-gray-200 pt-6">
                <a href="../../user/index.php" class="inline-flex items-center justify-center px-4 py-2 border border-primary-500 text-primary-500 hover:bg-primary-50 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Main Site
                </a>
            </div>
        </div>
    </div>
</body>
</html>
