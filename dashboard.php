<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
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
            display: flex;
            justify-content: space-between;
        }
        .dashboard-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px; /* Add space between rows */
            width: 100%; /* Adjust width */
            height: 100%; /* Adjust height */
        }
        .student-info-section {
            position: absolute;
            left: 110px; /* Move to the left */
            top: 100px; /* Adjust top position to avoid header */
            max-width: 360px; /* Set max width */
            height: 77.5%; /* Adjust height */
            padding: 20px; /* Add padding */
            background-color: #f1f1f1; /* Light background color */
            border: 1px solid #ddd; /* Border */
            border-radius: 10px; /* Rounded corners */
        }
        .announcement-section {
            flex: 2; /* Make the announcement section larger */
            margin: 0 auto; /* Center the announcement section */
            max-width: 600px; /* Set max width */
            height: 100%; /* Adjust height to match student info section */
            margin-bottom: 95px;
            position: relative;
            left: 50%;
            transform: translateX(-80%);
        }
        .rules-section {
            flex: 1;
            left: 140px; /* Add space to the left */
            max-width: 400px; /* Set max width */
            height: 100%; /* Adjust height to match student info section */
            padding: 20px; /* Add padding */
            background-color: #f1f1f1; /* Light background color */
            border: 1px solid #ddd; /* Border */
            border-radius: 10px; /* Rounded corners */
            font-size: 1.1rem; /* Increase font size */
            line-height: 1.6; /* Increase line height for readability */
            overflow: hidden; /* Hide overflow */
        }
        .rules-section .card-body {
            max-height: 100%; /* Set max height */
            overflow-y: auto; /* Make scrollable */
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
        .student-info-section img {
            max-width: 150px; /* Increase size for better visibility */
            margin-bottom: 10px;
            border-radius: 50%; /* Circular image */
            display: block;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for a professional look */
            border: 3px solid #1e4a82; /* Add border to match theme */
        }
        .student-info-section .card-title {
            text-align: center; /* Center align title */
            color: #1e4a82; /* Match theme color */
        }
        .student-info-section .card-text {
            font-size: 1rem; /* Adjust font size */
            color: #333; /* Text color */
            margin-bottom: 15px; /* Space between text */
            line-height: 1.5; /* Line height for better readability */
        }
        .student-info-section .card-text i {
            margin-right: 10px;
            color: #1e4a82; /* Icon color */
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
        .card img {
            max-width: 150px; /* Increase size for better visibility */
            margin-bottom: 10px;
            border-radius: 100%; /* Circular image */
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
    <div class="container-fluid">
        <!-- Navigation Bar -->
        <header class="dashboard-header">
            <nav class="dashboard-nav">
                <ul>
                    <!-- Separate the Dashboard link -->
                    <li class="dashboard-link"><a href="dashboard.php">Dashboard</a></li>
                    <!-- Keep other links close to each other -->
                    <li><a class="dropdown-toggle" href="#" id="notificationDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Notification
                        </a>
                        <div class="dropdown-menu" aria-labelledby="notificationDropdown">
                            <a class="dropdown-item" href="#">Action 1</a>
                            <a class="dropdown-item" href="#">Action 2</a>
                            <a class="dropdown-item" href="#">Action 3</a>
                        </div></li>
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

        <!-- Dashboard Main Content -->
        <div class="dashboard-main container">
            <!-- Student Information Card -->
            <div class="dashboard-section card student-info-section">
                <div class="card-body">
                    <h5 class="card-title">Student Information</h5>
                    <?php
                    // Database connection
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "csms";

                    $conn = new mysqli($servername, $username, $password, $dbname);

                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }

                    // Fetch user details from the database
                    $sql = "SELECT firstName, lastName, course, yearLevel, email, address, profile_picture FROM users WHERE user_id=1"; // Adjust user_id as necessary
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $firstName = $row['firstName'];
                        $lastName = $row['lastName'];
                        $course = $row['course'];
                        $year = $row['yearLevel'];
                        $email = $row['email'];
                        $address = $row['address'];
                        $profilePicture = $row['profile_picture'];
                    } else {
                        echo "No user found.";
                    }

                    $conn->close();
                    ?>
                    <img src="<?php echo $profilePicture; ?>" alt="Student Photo" class="img-fluid" onerror="this.onerror=null;this.src='placeholder.jpg';">
                    <hr>
                    <p class="card-text"><i class="fas fa-user"></i> Name: <?php echo $firstName . ' ' . $lastName; ?></p>
                    <p class="card-text"><i class="fas fa-book"></i> Course: <?php echo $course; ?></p>
                    <p class="card-text"><i class="fas fa-graduation-cap"></i> Year: <?php echo $year; ?></p>
                    <p class="card-text"><i class="fas fa-envelope"></i> Email: <?php echo $email; ?></p>
                    <p class="card-text"><i class="fas fa-map-marker-alt"></i> Address: <?php echo $address; ?></p>
                </div>
            </div>

            <!-- Announcement Section -->
            <div class="dashboard-section card announcement-section">
                <div class="card-body">
                    <h5 class="card-title">Announcement</h5>
                    <p class="card-text">2025-Feb-03: Announcement 1 from CCS Admin</p>
                    <hr>
                    <p>Reminder to all students: The deadline for submission of requirements for the upcoming internship is on February 28, 2025. Please coordinate with your respective departments.</p>
                    <p class="card-text">2024-May-08: Announcement 2 from CCS Admin</p>
                    <hr>
                    <p>Reminder to all students: The deadline for submission of requirements for the upcoming internship is on February 28, 2025. Please coordinate with your respective departments.</p>
                    <p class="card-text">CCS Admin | 2025-Jan-25</p>
                    <hr>
                    <p>The College of Computer Studies will be holding a seminar on 'AI and the Future of Technology' on March 10, 2025. All students are encouraged to attend. Registration is free!</p>
                    <hr>
                    <p class="card-text">CCS Admin | 2025-Feb-20</p>
                    <hr>
                    <p>The University will be celebrating its 50th Founding Anniversary on April 15, 2025. Various activities and events are planned. Stay tuned for more details!</p>
                </div>
            </div>

            <!-- Rules and Regulation Section -->
            <div class="dashboard-section card rules-section">
                <div class="card-body">
                    <h5 class="card-title">Rules and Regulation</h5>
                    <p class="card-text"><strong>University of COLLEGE OF INFORMATION & LABORATORY RULES AND REGULATION</strong></p>
                    <ul>
                        <li>Students are allowed to enter the computer lab only during their scheduled class or assigned lab hours. Access outside these hours must be approved by the lab supervisor.</li>
                        <li>All computer lab equipment, including computers, peripherals, and furniture, should be handled with care. Any damage caused will be the student's responsibility and may result in penalties.</li>
                        <li>No food or drinks are allowed inside the computer laboratory to avoid spills that can damage equipment.</li>
                        <li>Report any malfunctions to the lab supervisor.</li>
                        <li>Adhere to the dress code.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
