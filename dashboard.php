<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
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
            padding: 80px 20px 20px; /* Ensure content isn't hidden under the fixed header */
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
            max-width: 100px;
            margin-bottom: 10px;
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
                    <li><a href="home.php">Home</a></li>
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
                <div class="dashboard-section card col-md-4">
                    <div class="card-body">
                        <h5 class="card-title">Student Information</h5>
                        <img src="student_photo.jpg" alt="Student Photo" class="img-fluid">
                        <p class="card-text">Name: John Doe</p>
                        <p class="card-text">Course: Computer Science</p>
                        <p class="card-text">Year: 3rd Year</p>
                        <p class="card-text">Email: john.doe@example.com</p>
                        <p class="card-text">Address: 123 Main St, City, Country</p>
                    </div>
                </div>

                <!-- Announcement Section -->
                <div class="dashboard-section card col-md-4">
                    <div class="card-body">
                        <h5 class="card-title">Announcement</h5>
                        <p class="card-text">2025-Feb-03: Announcement 1 from CCS Admin</p>
                        <p class="card-text">2024-May-08: Announcement 2 from CCS Admin</p>
                    </div>
                </div>

                <!-- Rules and Regulation Section -->
                <div class="dashboard-section card col-md-4">
                    <div class="card-body">
                        <h5 class="card-title">Rules and Regulation</h5>
                        <p class="card-text"><strong>University of COLLEGE OF INFORMATION & LABORATORY RULES AND REGULATION</strong></p>
                        <ul>
                            <li>No eating or drinking in the lab.</li>
                            <li>Maintain silence at all times.</li>
                            <li>Handle equipment with care.</li>
                            <li>Report any malfunctions to the lab supervisor.</li>
                            <li>Adhere to the dress code.</li>
                        </ul>
                    </div>
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
