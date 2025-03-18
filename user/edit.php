<?php
// Start session at the beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Get the logged-in user's ID
$loggedInUserId = $_SESSION['id'];

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

    // Get form data - use null coalescing operator to avoid undefined array key warnings
    $idNo = $_POST['idNo'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $firstName = $_POST['firstName'] ?? '';
    $middleName = $_POST['middleName'] ?? '';
    $course = $_POST['course'] ?? '';
    $year = $_POST['yearLevel'] ?? ''; // Changed from 'year' to 'yearLevel' to match your form field
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Password change fields
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Password change handling - check if user is trying to change password
    $passwordChangeRequested = (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword));
    $passwordChangeSuccess = false;
    $passwordChangeError = '';
    
    if ($passwordChangeRequested) {
        // Check if all required password fields are filled
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $passwordChangeError = "All password fields are required to change your password.";
        } elseif ($newPassword !== $confirmPassword) {
            $passwordChangeError = "New password and confirmation password do not match.";
        } else {
            // Verify current password from database
            $passwordSql = "SELECT password FROM users WHERE user_id = ?";
            $passwordStmt = $conn->prepare($passwordSql);
            $passwordStmt->bind_param("i", $loggedInUserId);
            $passwordStmt->execute();
            $passwordResult = $passwordStmt->get_result();
            
            if ($passwordResult->num_rows > 0) {
                $userData = $passwordResult->fetch_assoc();
                $storedPassword = $userData['password'];
                
                // Check if the current password is correct
                $passwordCorrect = false;
                
                // First try to verify with password_verify in case it's a hash
                if (password_verify($currentPassword, $storedPassword)) {
                    $passwordCorrect = true;
                } 
                // Fallback to direct comparison for legacy plain-text passwords
                elseif ($currentPassword === $storedPassword) {
                    $passwordCorrect = true;
                }
                
                if ($passwordCorrect) {
                    // Hash the new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update the password in the database
                    $updatePasswordSql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $updatePasswordStmt = $conn->prepare($updatePasswordSql);
                    $updatePasswordStmt->bind_param("si", $hashedPassword, $loggedInUserId);
                    
                    if ($updatePasswordStmt->execute()) {
                        $passwordChangeSuccess = true;
                    } else {
                        $passwordChangeError = "Error updating password: " . $updatePasswordStmt->error;
                    }
                    
                    $updatePasswordStmt->close();
                } else {
                    $passwordChangeError = "Current password is incorrect.";
                }
            } else {
                $passwordChangeError = "User data could not be retrieved.";
            }
            
            $passwordStmt->close();
        }
    }

    // Check if profile picture was uploaded
    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['name'] != '') {
        $profilePicture = $_FILES['profilePicture']['name'];
        
        // Handle file upload
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($profilePicture);
        move_uploaded_file($_FILES['profilePicture']['tmp_name'], $target_file);
    } else {
        // Fetch existing profile picture if no new one is uploaded
        $sql = "SELECT profile_picture FROM users WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $loggedInUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $target_file = $row['profile_picture'];
        } else {
            $target_file = "profile.jpg"; // Default profile picture
        }
        $stmt->close();
    }

    // Check which fields are available in the form and build the update SQL accordingly
    $updateFields = [];
    
    if ($lastName !== '') $updateFields[] = "lastName=?";
    if ($firstName !== '') $updateFields[] = "firstName=?";
    if ($idNo !== '') $updateFields[] = "idNo=?";
    if ($middleName !== '') $updateFields[] = "middleName=?";
    if ($course !== '') $updateFields[] = "course=?";
    if ($year !== '') $updateFields[] = "yearLevel=?";
    if ($username !== '') $updateFields[] = "username=?";
    if ($email !== '') $updateFields[] = "email=?";
    if ($address !== '') $updateFields[] = "address=?";
    $updateFields[] = "profile_picture=?";

    if (!empty($updateFields)) {
        // Prepare update statement with placeholders
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id=?";
        
        // Create parameter binding string and parameter array
        $bindTypes = str_repeat("s", count($updateFields)) . "i"; // all string fields + integer for user_id
        $bindParams = [];
        
        // Add parameters in the same order as placeholders
        if ($lastName !== '') $bindParams[] = $lastName;
        if ($firstName !== '') $bindParams[] = $firstName;
        if ($idNo !== '') $bindParams[] = $idNo;
        if ($middleName !== '') $bindParams[] = $middleName;
        if ($course !== '') $bindParams[] = $course;
        if ($year !== '') $bindParams[] = $year;
        if ($username !== '') $bindParams[] = $username;
        if ($email !== '') $bindParams[] = $email;
        if ($address !== '') $bindParams[] = $address;
        $bindParams[] = $target_file; // profile_picture
        $bindParams[] = $loggedInUserId; // Add user_id as last parameter
        
        // Prepare and execute statement
        $stmt = $conn->prepare($sql);
        
        // Use call_user_func_array to bind parameters dynamically
        $bindParamsRef = [];
        $bindParamsRef[] = &$bindTypes;
        foreach ($bindParams as $key => $value) {
            $bindParamsRef[] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParamsRef);
        
        if ($stmt->execute()) {
            echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                    Profile updated successfully!
                  </div>";
        } else {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                    Error updating profile: " . $stmt->error . "
                  </div>";
        }
        
        $stmt->close();
    }

    // Display password change results
    if ($passwordChangeRequested) {
        if ($passwordChangeSuccess) {
            echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                    Password successfully updated!
                  </div>";
        } elseif (!empty($passwordChangeError)) {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                    {$passwordChangeError}
                  </div>";
        }
    }

    $conn->close();
}

// Database connection for displaying current user data
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "csms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables with default empty values
$idNo = '';
$firstName = '';
$lastName = '';
$middleName = '';
$course = '';
$year = '';
$username = '';
$email = '';
$address = '';
$profilePicture = 'profile.jpg'; // Default profile image

// Fetch user details from the database using prepared statement
$sql = "SELECT idNo, lastName, firstName, middleName, course, yearLevel, username, email, address, profile_picture FROM users WHERE user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loggedInUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $idNo = $row['idNo'] ?? '';
    $lastName = $row['lastName'] ?? '';
    $firstName = $row['firstName'] ?? '';
    $middleName = $row['middleName'] ?? '';
    $course = $row['course'] ?? '';
    $year = $row['yearLevel'] ?? '';
    $username = $row['username'] ?? '';
    $email = $row['email'] ?? '';
    $address = $row['address'] ?? '';
    $profilePicture = $row['profile_picture'] ?? 'profile.jpg';
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="font-sans bg-gray-50 h-screen flex flex-col overflow-hidden">
    <!-- Navigation Bar -->
    <header class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto">
            <nav class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-bold">SitIn Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Home</a>
                        <div class="relative group">
                            <button class="px-3 py-2 rounded hover:bg-primary-800 transition flex items-center">
                                Notification <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 1</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 2</a>
                                <a href="#" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Action 3</a>
                            </div>
                        </div>
                        <a href="edit.php" class="px-3 py-2 rounded bg-primary-800 transition">Edit Profile</a>
                        <a href="history.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">History</a>
                        <a href="reservation.php" class="px-3 py-2 rounded hover:bg-primary-800 transition">Reservation</a>
                    </div>
                    
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium px-4 py-2 rounded transition">
                        Log out
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Mobile Navigation Menu (hidden by default) -->
    <div id="mobile-menu" class="md:hidden bg-primary-800 hidden">
        <a href="dashboard.php" class="block px-4 py-2 text-white hover:bg-primary-900">Home</a>
        <button class="mobile-dropdown-button w-full text-left px-4 py-2 text-white hover:bg-primary-900 flex justify-between items-center">
            Notification <i class="fas fa-chevron-down ml-1"></i>
        </button>
        <div class="mobile-dropdown-content hidden bg-primary-900 px-4 py-2">
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 1</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 2</a>
            <a href="#" class="block py-1 text-white hover:text-gray-300">Action 3</a>
        </div>
        <a href="edit.php" class="block px-4 py-2 text-white bg-primary-900">Edit Profile</a>
        <a href="history.php" class="block px-4 py-2 text-white hover:bg-primary-900">History</a>
        <a href="reservation.php" class="block px-4 py-2 text-white hover:bg-primary-900">Reservation</a>
    </div>

    <!-- Edit Profile Main Content -->
    <div class="flex-1 overflow-y-auto px-4 py-8 md:px-8">
        <div class="container mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Profile</h1>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Profile Preview Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col items-center">
                            <h2 class="text-xl font-bold text-primary-700 mb-4">Profile Preview</h2>
                            <div class="w-28 h-28 mb-3 rounded-full border-4 border-primary-100 overflow-hidden">
                                <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" class="w-full h-full object-cover" 
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($firstName . ' ' . $lastName); ?>&background=0369a1&color=fff&size=128';">
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo $firstName . ' ' . $lastName; ?></h3>
                            <p class="text-sm text-gray-500 mb-4"><?php echo $course; ?> - Year <?php echo $year; ?></p>
                            
                            <div class="w-full space-y-3">
                                <?php if(!empty($username)): ?>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Username</p>
                                        <p class="font-medium"><?php echo $username; ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($email)): ?>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-envelope text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="font-medium"><?php echo $email; ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($address)): ?>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-primary-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-500">Address</p>
                                        <p class="font-medium"><?php echo $address; ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden md:col-span-2">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-primary-700 mb-6">Update Your Information</h2>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <!-- ID Number -->
                                <div>
                                    <label for="idNo" class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                                    <input type="text" name="idNo" id="idNo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $idNo; ?>">
                                </div>
                                
                                <!-- Username -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                    <input type="text" name="username" id="username" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $username; ?>">
                                </div>
                                
                                <!-- First Name -->
                                <div>
                                    <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" name="firstName" id="firstName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $firstName; ?>" required>
                                </div>
                                
                                <!-- Last Name -->
                                <div>
                                    <label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" name="lastName" id="lastName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $lastName; ?>" required>
                                </div>
                                
                                <!-- Middle Name -->
                                <div>
                                    <label for="middleName" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                    <input type="text" name="middleName" id="middleName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $middleName; ?>">
                                </div>
                                
                                <!-- Course -->
                                <div>
                                    <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                    <select name="course" id="course" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <option value="BSIT" <?php if($course == 'BSIT') echo 'selected'; ?>>Bachelor of Science in Information Technology</option>
                                        <option value="BSCS" <?php if($course == 'BSCS') echo 'selected'; ?>>Bachelor of Science in Computer Science</option>
                                        <option value="BSIS" <?php if($course == 'BSIS') echo 'selected'; ?>>Bachelor of Science in Information Systems</option>
                                    </select>
                                </div>
                                
                                <!-- Year Level -->
                                <div>
                                    <label for="yearLevel" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                    <select name="yearLevel" id="yearLevel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <option value="1" <?php if($year == '1') echo 'selected'; ?>>First Year</option>
                                        <option value="2" <?php if($year == '2') echo 'selected'; ?>>Second Year</option>
                                        <option value="3" <?php if($year == '3') echo 'selected'; ?>>Third Year</option>
                                        <option value="4" <?php if($year == '4') echo 'selected'; ?>>Fourth Year</option>
                                    </select>
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" id="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $email; ?>" required>
                                </div>
                                
                                <!-- Address -->
                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                    <input type="text" name="address" id="address" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" value="<?php echo $address; ?>">
                                </div>
                            </div>
                            
                            <!-- Profile Picture -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                                <div class="flex items-center">
                                    <div class="w-20 h-20 rounded-full bg-gray-100 mr-4 overflow-hidden">
                                        <img id="previewImage" src="<?php echo $profilePicture; ?>" alt="Preview" class="w-full h-full object-cover"
                                            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($firstName . ' ' . $lastName); ?>&background=0369a1&color=fff&size=128';">
                                    </div>
                                    <label class="cursor-pointer bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Choose new image
                                        <input type="file" name="profilePicture" id="profilePicture" class="hidden" onchange="previewFile()">
                                    </label>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">JPG, JPEG, PNG or GIF (Max. 5MB)</p>
                            </div>
                            
                            <!-- Password Update Section -->
                            <div class="border-t border-gray-200 pt-6 mt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                        <input type="password" name="currentPassword" id="currentPassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    </div>
                                    <div></div>
                                    <div>
                                        <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                        <input type="password" name="newPassword" id="newPassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    </div>
                                    <div>
                                        <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                        <input type="password" name="confirmPassword" id="confirmPassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-md transition">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            &copy; 2024 SitIn System. All rights reserved.
        </div>
    </footer>
    
    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        
        // Toggle mobile dropdown menus
        document.querySelectorAll('.mobile-dropdown-button').forEach(button => {
            button.addEventListener('click', function() {
                this.nextElementSibling.classList.toggle('hidden');
            });
        });
        
        // Preview selected image file
        function previewFile() {
            const preview = document.getElementById('previewImage');
            const file = document.querySelector('input[type=file]').files[0];
            const reader = new FileReader();
            
            reader.onloadend = function() {
                preview.src = reader.result;
            }
            
            if (file) {
                reader.readAsDataURL(file);
            } else {
                preview.src = "<?php echo $profilePicture; ?>";
            }
        }
    </script>
</body>
</html>
