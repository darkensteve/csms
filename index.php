<?php
session_start();
require_once('db.php');

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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e4b7a;
            --secondary-color: #f0f7fc;
            --accent-color: #3573b1;
            --text-color: #333333;
            --error-color: #d32f2f;
            --success-color: #388e3c;
            --neutral-light: #f5f5f5;
            --neutral-mid: #e0e0e0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            width: 90%;
            max-width: 400px;
            padding: 30px;
            transition: all 0.2s ease;
        }
        
        .login-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.15);
        }
        
        .login-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .logo {
            height: 55px;
            width: auto;
            transition: transform 0.2s ease;
        }
        
        .title {
            color: var(--primary-color);
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
            margin: 0;
            letter-spacing: 0.5px;
        }
        
        .subtitle {
            color: #555;
            font-size: 0.95rem;
            text-align: center;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 16px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(53, 115, 177, 0.2);
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 38px;
            cursor: pointer;
            color: #777;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group input {
            margin-right: 6px;
        }
        
        .forgot-link {
            color: var(--accent-color);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: var(--primary-color);
        }
        
        .login-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            letter-spacing: 0.5px;
        }
        
        .login-btn:hover {
            background-color: var(--accent-color);
        }
        
        .error-message {
            color: var(--error-color);
            background-color: rgba(211, 47, 47, 0.08);
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 13px;
            display: flex;
            align-items: center;
            border-left: 3px solid var(--error-color);
        }
        
        .error-message i {
            margin-right: 8px;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #555;
            font-size: 0.85rem;
        }
        
        .register-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .register-link a:hover {
            color: var(--primary-color);
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
                width: 85%;
            }
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 15px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
            max-width: 350px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-size: 13px;
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
            margin-right: 8px;
            font-size: 16px;
        }
        
        .admin-login-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--neutral-mid);
        }
        
        .admin-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .admin-btn:hover {
            background-color: rgba(30, 75, 122, 0.08);
            color: var(--accent-color);
        }
        
        .admin-btn i {
            margin-right: 8px;
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
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logos">
                <img src="uclogo.jpg" alt="UC Logo" class="logo">
                <img src="ccs.png" alt="CCS Logo" class="logo">
            </div>
            <h1 class="title">CCS SITIN MONITORING SYSTEM</h1>
        </div>

        <form action="index.php" method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <span class="password-toggle"><i class="far fa-eye"></i></span>
            </div>

            <div class="remember-forgot">
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <button type="submit" name="login" class="login-btn">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Login
            </button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
        
        <div class="admin-login-link">
            <a href="login_admin.php" class="admin-btn">
                <i class="fas fa-user-shield"></i> Administrator Login
            </a>
        </div>
    </div>
    
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
            max-width: 350px;
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
</body>
</html>