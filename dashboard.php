<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            overflow: hidden; /* Prevent scrolling */
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
        .dashboard-header nav ul li.dashboard-link {
            margin-right: auto; /* Separate Dashboard from other items */
        }
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
        /* Main Dashboard Area */
        .dashboard-main {
            padding: 100px 20px 20px; /* Ensure content isn't hidden under the fixed header */
            height: calc(100vh - 100px); /* Adjust height to fit viewport */
            display: flex;
            flex-direction: column;
        }
        .dashboard-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            flex: 1;
        }
        .student-info-section img,
        .dashboard-section img {
            max-width: 150px;
            margin-bottom: 10px;
            border-radius: 50%;
            display: block;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border: 3px solid #1e4a82;
        }
        .student-info-section .card-title,
        .card-title {
            text-align: center;
            color: #1e4a82;
        }
        .student-info-section .card-text,
        .card-text {
            font-size: 1rem;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .student-info-section .card-text i,
        .card-text i {
            margin-right: 10px;
            color: #1e4a82;
        }
        .announcement-section .card-body,
        .rules-section .card-body {
            max-height: 300px; /* Set max height */
            overflow-y: auto; /* Make scrollable */
        }
        .rules-section ul {
            padding-left: 20px;
        }
        .rules-section ul li {
            margin-bottom: 10px;
        }
        .rules-section h6 {
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Navigation Bar -->
        <header class="dashboard-header">
            <nav class="dashboard-nav">
                <ul class="d-flex align-items-center">
                    <!-- Separate the Dashboard link -->
                    <li class="dashboard-link"><a href="dashboard.php">Dashboard</a></li>
                    <li><a class="dropdown-toggle" href="#" id="notificationDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Notification</a>
                        <div class="dropdown-menu" aria-labelledby="notificationDropdown">
                            <a class="dropdown-item" href="#">Action 1</a>
                            <a class="dropdown-item" href="#">Action 2</a>
                            <a class="dropdown-item" href="#">Action 3</a>
                        </div>
                    </li>
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
            <div class="row">
                <!-- Student Information Card -->
                <div class="col-md-4 col-sm-6 mb-4">
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
                            $sql = "SELECT firstName, lastName, course, yearLevel, email, address, profile_picture FROM users WHERE user_id=1";
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
                </div>

                <!-- Announcement Section -->
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="dashboard-section card announcement-section">
                        <div class="card-body overflow-auto">
                            <h5 class="card-title">Announcement</h5>
                            <p class="card-text">2025-Feb-03: Announcement 1 from CCS Admin</p>
                            <hr>
                            <p>Reminder to all students: The deadline for submission of requirements for the upcoming internship is on February 28, 2025. Please coordinate with your respective departments.</p>
                            <p class="card-text">2024-May-08: Announcement 2 from CCS Admin</p>
                            <hr>
                            <p>The College of Computer Studies will be holding a seminar on 'AI and the Future of Technology' on March 10, 2025. All students are encouraged to attend. Registration is free!</p>
                        </div>
                    </div>
                </div>

                <!-- Rules and Regulation Section -->
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="dashboard-section card rules-section">
                        <div class="card-body overflow-auto">
                            <h5 class="card-title">Rules and Regulations</h5>
                            <ul>
                                <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                                <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                                <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                                <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                                <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                                <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                                <li>Observe proper decorum while inside the laboratory.</li>
                                <ul>
                                    <li>Do not get inside the lab unless the instructor is present.</li>
                                    <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                                    <li>Follow the seating arrangement of your instructor.</li>
                                    <li>At the end of class, all software programs must be closed.</li>
                                    <li>Return all chairs to their proper places after using.</li>
                                </ul>
                                <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                                <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                                <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                                <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                                <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
                            </ul>
                            <h6>DISCIPLINARY ACTION</h6>
                            <ul>
                                <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                                <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for Bootstrap and additional scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
