<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "csms";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get form data
    $idNo = $_POST['idNo'];
    $lastName = $_POST['lastName'];
    $firstName = $_POST['firstName'];
    $middleName = $_POST['middleName'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $profilePicture = $_FILES['profilePicture']['name'];

    // Check if username, email, or ID number already exists
    $checkSql = "SELECT * FROM users WHERE (username='$username' OR email='$email' OR idNo='$idNo') AND user_id != 1"; // Adjust user_id as necessary
    $checkResult = $conn->query($checkSql);

    if ($checkResult->num_rows > 0) {
        echo "<script>alert('Username, Email, or ID number already taken. Please choose another.');</script>";
        echo "<script>setTimeout(function() { window.location.href = 'edit.php'; }, 2000);</script>";
    } else {
        // Handle file upload
        if ($profilePicture) {
            $target_dir = "uploads/";
            $target_file = $target_dir . basename($profilePicture);
            move_uploaded_file($_FILES['profilePicture']['tmp_name'], $target_file);
        } else {
            $target_file = "profile.jpg"; // Default profile picture
        }

        // Update user details in the database
        $sql = "UPDATE users SET idNo='$idNo', lastName='$lastName', firstName='$firstName', middleName='$middleName', course='$course', yearLevel='$year', username='$username', email='$email', address='$address', profile_picture='$target_file' WHERE user_id=1"; // Adjust user_id as necessary

        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('Profile updated successfully!');</script>";
            echo "<script>setTimeout(function() { window.location.href = 'dashboard.php'; }, 2000);</script>";
        } else {
            echo "Error updating profile: " . $conn->error;
        }
    }

    $conn->close();
} else {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "csms";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Initialize variables to avoid undefined variable warnings
    $email = '';
    $address = '';

    // Fetch user details from the database
    $sql = "SELECT idNo, lastName, firstName, middleName, course, yearLevel, username, email, address, profile_picture FROM users WHERE user_id=1"; // Adjust user_id as necessary
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $idNo = $row['idNo'];
        $lastName = $row['lastName'];
        $firstName = $row['firstName'];
        $middleName = $row['middleName'];
        $course = $row['course'];
        $year = $row['yearLevel'];
        $username = $row['username'];
        $email = $row['email'];
        $address = $row['address'];
        $profilePicture = $row['profile_picture'];
    } else {
        echo "No user found.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            overflow: hidden; /* Prevent scrolling */
        }
        .edit-profile-container {
            margin-top: 50px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            overflow: hidden; /* Prevent scrolling */
        }
        .edit-profile-form {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .form-group label {
            font-weight: bold;
            color: #1e4a82;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .btn-save {
            background-color: #1e4a82;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-save:hover {
            background-color: #154c79;
        }
        .edit-profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .edit-profile-header h2 {
            font-size: 2.5rem; /* Increased font size */
            color: white; /* Changed font color to white */
            font-weight: bold;
            background-color: #1e4a82; /* Added background color */
            padding: 15px; /* Increased padding */
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Added shadow for a professional look */
        }
        .profile-picture {
            display: block;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 20px;
            border-radius: 50%;
            width: 150px;
            height: 150px;
            object-fit: cover;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-control-file {
            margin-top: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input[type="file"] {
            display: none;
        }
        .custom-file-upload {
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            background-color: #1e4a82;
            color: white;
            border-radius: 5px;
            font-weight: bold;
        }

        label.custom-file-upload{
            color: white;
        }
        /* Header and Navigation */
        .dashboard-header {
            background-color: #1e4a82; /* Updated to match blue color */
            color: white;
            padding: 10px 20px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        .dashboard-header nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-start; /* Align items to the left */
        }
        /* Dashboard Link */
        .dashboard-header nav ul li.dashboard-link {
            margin-right: auto; /* Separate Dashboard from other items */
        }
        /* Links closer together */
        .dashboard-header nav ul li {
            margin-left: 10px; /* Reduce spacing between links */
        }
        .dashboard-header nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 8px 15px;
        }
        .dashboard-header nav ul li a:hover {
            background-color: #154c79;
            border-radius: 5px;
        }
        /* Page Content */
        .dashboard-main {
            padding: 100px 20px 20px; /* Ensure content isn't hidden under the fixed header */
        }
        .dashboard-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px; /* Add space between rows */
            margin-right: 20px; /* Add space between columns */
        }

        .dashboard-section img {
            max-width: 150px; /* Increase size for better visibility */
            margin-bottom: 10px;
            border-radius: 50%; /* Circular image */
            display: block;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for a professional look */
            border: 3px solid #1e4a82; /* Add border to match theme */
        }
        /* Right side (notification and logout) */
        .nav-right {
            display: flex;
            align-items: center;
        }
        .nav-right li {
            margin-left: 10px;
        }
        /* Logout Button */
        .btn-logout {
            background-color: #ff4b4b;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-logout:hover {
            background-color: #d73a3a;
        }
        .card-deck .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.2s;
            font-family: 'Arial', sans-serif;
        }
        .card-deck .card:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .card img {
            max-width: 150px; /* Increase size for better visibility */
            margin-bottom: 10px;
            border-radius: 50%; /* Circular image */
            display: block;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for a professional look */
        }
        .card img + hr {
            margin-top: 10px;
            margin-bottom: 20px;
            border: 0;
            border-top: 1px solid #ddd;
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e4a82;
            margin-bottom: 15px;
        }
        .card-text {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 10px;
        }
        .card-text i {
            margin-right: 10px;
            color: #1e4a82;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <header class="dashboard-header">
        <nav class="dashboard-nav">
            <ul>
                <li class="dashboard-link"><a href="dashboard.php">Dashboard</a></li>
                <li><a href="dashboard.php">Home</a></li>
                <li><a href="edit.php">Edit Profile</a></li>
                <li><a href="history.php">History</a></li>
                <li><a href="reservation.php">Reservation</a></li>
                <li class="nav-right">
                    <button class="btn-logout" onclick="window.location.href='logout.php'">Log out</button>
                </li>
            </ul>
        </nav>
    </header>

    <div class="container edit-profile-container">
        <form class="edit-profile-form" id="editProfileForm" method="POST" enctype="multipart/form-data">
            <div class="form-group text-center">
                <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" class="profile-picture" id="profilePicturePreview" onerror="this.onerror=null;this.src='placeholder.jpg';">
                <label for="profilePicture" class="custom-file-upload">Edit Picture</label>
                <input type="file" class="form-control-file" id="profilePicture" name="profilePicture" onchange="previewProfilePicture(event)">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="idNo">ID NO</label>
                    <input type="text" class="form-control" id="idNo" name="idNo" placeholder="Enter your ID number" value="<?php echo $idNo; ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="lastName">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Enter your last name" value="<?php echo $lastName; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="firstName">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Enter your first name" value="<?php echo $firstName; ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="middleName">Middle Name</label>
                    <input type="text" class="form-control" id="middleName" name="middleName" placeholder="Enter your middle name" value="<?php echo $middleName; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="course">Course</label>
                    <input type="text" class="form-control" id="course" name="course" placeholder="Enter your course" value="<?php echo $course; ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="year">Year Level</label>
                    <input type="text" class="form-control" id="year" name="year" placeholder="Enter your year level" value="<?php echo $year; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" value="<?php echo $username; ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>">
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" class="form-control" id="address" name="address" placeholder="Enter your address" value="<?php echo $address; ?>">
            </div>
            <button type="submit" class="btn btn-save">Save Changes</button>
        </form>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewProfilePicture(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('profilePicturePreview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</body>
</html>
