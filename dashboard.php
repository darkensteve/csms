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

        <!-- Main Content Area -->
        <main class="dashboard-main">
            <section class="dashboard-section">
                <h2>Section Title</h2>
                <p>Content goes here...</p>
            </section>
            <!-- Add more sections as needed -->
        </main>
    </div>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
