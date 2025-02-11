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
        .dashboard-header {
            background-color: #343a40;
            color: white;
            padding: 20px;
        }
        .dashboard-header h1 {
            margin: 0;
        }
        .dashboard-header nav ul {
            list-style: none;
            padding: 0;
        }
        .dashboard-header nav ul li {
            display: inline;
            margin-right: 10px;
        }
        .dashboard-header nav ul li a {
            color: white;
            text-decoration: none;
        }
        .dashboard-main {
            padding: 20px;
        }
        .dashboard-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Welcome to the Dashboard</h1>
            <nav>
                <ul>
                    <li><a href="profile.html">Profile</a></li>
                    <li><a href="settings.html">Settings</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        <main class="dashboard-main">
            <section class="dashboard-section">
                <h2>Section Title</h2>
                <p>Content goes here...</p>
            </section>
            <!-- Add more sections as needed -->
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
