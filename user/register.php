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
    <title>Register - CCS SITIN Monitoring System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <link rel="stylesheet" href="styles.css">
    <script>
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out -translate-y-2 opacity-0 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white flex items-center`;
            notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('-translate-y-2', 'opacity-0');
                setTimeout(() => {
                    notification.classList.add('-translate-y-2', 'opacity-0');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }, 100);
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen flex items-center justify-center p-4 font-sans text-gray-800 overflow-hidden">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl transition-all duration-200 ease-in-out hover:shadow-xl max-h-[95vh] overflow-y-auto scrollbar-thin">
        <div class="p-6 sm:p-8">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="flex justify-center space-x-12 mb-4">
                    <img src="../uclogo.jpg" alt="UC Logo" class="w-12 h-12 object-contain filter drop-shadow transition duration-300 hover:scale-105">
                    <img src="../ccs.png" alt="CCS Logo" class="w-12 h-12 object-contain filter drop-shadow transition duration-300 hover:scale-105">
                </div>
                <h1 class="text-xl font-bold text-primary-800 tracking-wide uppercase">CCS SITIN Monitoring System</h1>
                <p class="text-sm text-gray-500 mt-1">Create your account to get started</p>
            </div>

            <!-- Form -->
            <form action="register.php" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div class="relative">
                            <label for="idno" class="block text-xs font-medium text-gray-700 mb-1">ID Number</label>
                            <input type="text" id="idno" name="idno" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Enter your ID number" required>
                        </div>
                        
                        <div class="relative">
                            <label for="lastname" class="block text-xs font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="lastname" name="lastname" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Enter your last name" required>
                        </div>
                        
                        <div class="relative">
                            <label for="firstname" class="block text-xs font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="firstname" name="firstname" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Enter your first name" required>
                        </div>
                        
                        <div class="relative">
                            <label for="middlename" class="block text-xs font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" id="middlename" name="middlename" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Enter your middle name">
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="relative">
                            <label for="course" class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                            <select id="course" name="course" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                required>
                                <option value="" disabled selected>Select your course</option>
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
                        
                        <div class="relative">
                            <label for="yearlevel" class="block text-xs font-medium text-gray-700 mb-1">Year Level</label>
                            <select id="yearlevel" name="yearlevel" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                required>
                                <option value="" disabled selected>Select your year level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        
                        <div class="relative">
                            <label for="username" class="block text-xs font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="username" name="username" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Create a username" required>
                        </div>
                        
                        <div class="relative">
                            <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" 
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:form-input-focus transition-all duration-200"
                                placeholder="Create a password" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" 
                    class="w-full mt-6 bg-primary-700 hover:bg-primary-600 text-white py-2.5 rounded-md text-sm font-medium uppercase tracking-wide transition-all duration-200 flex justify-center items-center shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-opacity-50 hover:translate-y-px active:translate-y-0">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            </form>

            <div class="mt-5 pt-4 text-center border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    Already have an account? <a href="index.php" class="text-primary-600 font-medium hover:text-primary-800 hover:underline transition-colors">Sign in here</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($message)) {
            echo "showNotification('$message', 'error');";
        }
        ?>
    </script>
</body>
</html>