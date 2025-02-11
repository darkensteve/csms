<?php

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $course = $_POST['course'];
    $yearlevel = $_POST['yearlevel'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "INSERT INTO users (idno, firstname, middlename, lastname, course, yearlevel, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssssss', $idno, $firstname, $middlename, $lastname, $course, $yearlevel, $username, $password);

    if ($stmt->execute()) {
        // Display a more pleasing and presentable success message
        echo "<script>
        alert('Congratulations! You have successfully registered.');
        window.location.href='index.php';
        </script>";
    } else {
        $message = "Failed to register!";
        echo "<script>
        alert('$message');
        </script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-image: url('uc.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .login-container {
            background-color: #fff;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 350px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 10px;
        }
        .login-header .logo {
            width: 40px;
            height: auto;
        }
        .title {
            font-size: 1.2em;
            margin: 5px 0;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 3px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }   
        .login-btn {
            width: 100%;
            padding: 8px;
            background-color: #007bff;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 1em;
            cursor: pointer;
        }
        .login-btn:hover {
            background-color: #0056b3;
        }
        .register-link {
            text-align: center;
            margin-top: 10px;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="uclogo.jpg" alt="Left Logo" class="logo">
            <h1 class="title">CCS SITIN MONITORING SYSTEM</h1>
            <img src="ccs.png" alt="Right Logo" class="logo">
        </div>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="idno">IDNO:</label>
                <input type="text" id="idno" name="idno" placeholder="ID Number" required>
            </div>

            <div class="form-group">
                <label for="lastname">Last Name:</label>
                <input type="text" id="lastname" name="lastname" placeholder="Last Name" required>
            </div>

            <div class="form-group">
                <label for="firstname">First Name:</label>
                <input type="text" id="firstname" name="firstname" placeholder="First Name" required>
            </div>

            <div class="form-group">
                <label for="middlename">Middle Name:</label>
                <input type="text" id="middlename" name="middlename" placeholder="Middle Name">
            </div>

            <div class="form-group">
                <label for="course">Course:</label>
                <select id="course" name="course" required>
                    <option value="" disabled selected>Select Course</option>
                    <option value="BSCS">BSCS</option>
                    <option value="BSIT">BSIT</option>
                    <option value="ACT">ACT</option>
                    <option value="COE">COE</option>
                    <option value="CPE">CPE</option>
                    <option value="BSIS">BSIS</option>
                    <option value="BSA">BSA</option>
                    <option value="BSBA">BSBA</option>
                    <option value="BSHRM">BSHRM</option>
                    <option value="BSHM">BSHM</option>
                    <option value="BSN">BSN</option>
                    <option value="BSMT">BSMT</option>
                </select>
            </div>

            <div class="form-group">
                <label for="yearlevel">Year Level:</label>
                <select id="yearlevel" name="yearlevel" required>
                    <option value="" disabled selected>Year Level</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="login-btn">Register</button>
        </form>

        <div class="register-link">
            <p>Already have an account? <a href="index.php">Login here</a></p>
        </div>
    </div>
</body>
</html>