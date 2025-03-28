<?php
session_start();
require_once('../includes/db_connect.php');

// Initialize these variables early to prevent the "undefined array key" warnings
$id = $_SESSION['id'] ?? '';
$username = $_SESSION['username'] ?? '';

$error = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare and bind - only check username first for security
    $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the user exists
    if ($result->num_rows > 0) {
        // Fetch the user data
        $user = $result->fetch_assoc();
        
        // Check if passwords are stored using password_hash
        if (password_verify($password, $user['password'])) {
            // If using password_hash, use this code
            $_SESSION['id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            $_SESSION['login_success'] = true;
            
            // Redirect to the dashboard with a success message
            header("Location: dashboard.php?message=login");
            exit;
        } elseif ($user['password'] === $password) {
            // TEMPORARY FALLBACK: Direct comparison (less secure)
            // This should be removed once all passwords are properly hashed
            $_SESSION['id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            $_SESSION['login_success'] = true;
            
            // Redirect to the dashboard with a success message
            header("Location: dashboard.php?message=login");
            exit;
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }

    // Close the statement
    $stmt->close();
}

// If you're using these variables for authentication checks, you might want to add:
if (isset($_SESSION['id']) && isset($_SESSION['username'])) {
    // If user is already logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CCS SITIN Monitoring System</title>
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
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="far fa-eye"></i>' : '<i class="far fa-eye-slash"></i>';
            });
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
                    <img src="../uclogo.jpg" alt="UC Logo" class="logo">
                    <img src="../ccs.png" alt="CCS Logo" class="logo">
                </div>
                <h1 class="text-3xl font-bold text-gray-800">STUDENT LOGIN</h1>
                <p class="text-gray-600 mt-2">CCS SITIN MONITORING SYSTEM</p>
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
            
            <form method="post" action="index.php" class="space-y-6" autocomplete="off">
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
                    <a href="forgot_password.php" class="text-sm text-primary-500 hover:text-accent hover:underline transition duration-200">Forgot password?</a>
                </div>
                
                <button type="submit" name="login" class="w-full bg-primary-500 hover:bg-accent text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition duration-200 transform hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </button>
            </form>
            
            <div class="mt-6 text-center border-t border-gray-200 pt-4">
                <p class="text-sm text-gray-600">Don't have an account? <a href="register.php" class="text-primary-500 hover:text-accent hover:underline font-medium">Register here</a></p>
            </div>
            
            <div class="mt-4 text-center">
                <a href="../admin/auth/login_admin.php" class="inline-flex items-center justify-center px-4 py-2 border border-primary-500 text-primary-500 hover:bg-primary-50 rounded-lg transition duration-200">
                    <i class="fas fa-user-shield mr-2"></i> Administrator Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>