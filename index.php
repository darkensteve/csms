<?php
session_start();
require_once('db.php');

$error = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the user exists
    if ($result->num_rows > 0) {
        // Fetch the user data
        $user = $result->fetch_assoc();

        // Set the session variables
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Redirect to the dashboard with a success message
        echo "<script>
        alert('You have successfully logged in!');
        window.location.href='dashboard.php';
        </script>";
    } else {
        $error = "Invalid username or password";
    }

    // Close the statement
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            color: red;
            margin-top: 10px;
        }
    </style>
    <script>
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('message') && urlParams.get('message') === 'logout') {
                alert('Successfully Logged Out');
            }
        };
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="uclogo.jpg" alt="Left Logo" class="logo">
            <h1 class="title">CCS SITIN MONITORING SYSTEM</h1>
            <img src="ccs.png" alt="Right Logo" class="logo">
        </div>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <?php if ($error): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>

            <button type="submit" name="login" class="login-btn">Login</button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>