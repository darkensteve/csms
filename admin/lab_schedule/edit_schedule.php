<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: ../auth/login_admin.php");
    exit;
}

$admin_username = $_SESSION['admin_username'];

// Database connection
$conn = mysqli_connect("localhost", "root", "", "csms");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// Check if it's an edit or add operation
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;
$is_edit = ($schedule_id > 0);
$page_title = $is_edit ? 'Edit Schedule' : 'Add Schedule';

// Get all labs for dropdown
$labs = [];
$labs_query = "SELECT * FROM labs ORDER BY lab_name";
$labs_result = mysqli_query($conn, $labs_query);

if ($labs_result && mysqli_num_rows($labs_result) > 0) {
    while ($row = mysqli_fetch_assoc($labs_result)) {
        $labs[] = $row;
    }
}

// Initialize default values
$schedule = [
    'lab_id' => $lab_id,
    'day_of_week' => 'Monday',
    'start_time' => '08:00:00',
    'end_time' => '10:00:00',
    'status' => 'available',
    'notes' => ''
];

// If editing, get existing schedule data
if ($is_edit && $schedule_id > 0) {
    $schedule_query = "SELECT * FROM lab_schedules WHERE schedule_id = ?";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $schedule_result = $stmt->get_result();
    
    if ($schedule_result && $schedule_result->num_rows > 0) {
        $schedule = $schedule_result->fetch_assoc();
        $lab_id = $schedule['lab_id'];
    } else {
        // Schedule not found, redirect back
        header("Location: index.php");
        exit;
    }
}

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $new_lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    $day_of_week = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : '';
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'available';
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Basic validation
    $errors = [];
    
    if ($new_lab_id <= 0) {
        $errors[] = "Please select a valid laboratory.";
    }
    
    if (!in_array($day_of_week, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])) {
        $errors[] = "Please select a valid day of the week.";
    }
    
    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start time and end time are required.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = "End time must be after start time.";
    }
    
    if (!in_array($status, ['available', 'occupied', 'maintenance', 'reserved'])) {
        $errors[] = "Please select a valid status.";
    }
    
    // If no errors, proceed with database operation
    if (empty($errors)) {
        if ($is_edit) {
            // Update existing schedule
            $update_query = "UPDATE lab_schedules SET lab_id = ?, day_of_week = ?, start_time = ?, end_time = ?, status = ?, notes = ? WHERE schedule_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("isssssi", $new_lab_id, $day_of_week, $start_time, $end_time, $status, $notes, $schedule_id);
            
            if ($stmt->execute()) {
                $message = "Schedule updated successfully!";
                $message_type = "success";
                // Redirect to schedule list after brief delay
                header("Refresh: 1; URL=index.php?lab_id=" . $new_lab_id);
            } else {
                $message = "Error updating schedule: " . $conn->error;
                $message_type = "error";
            }
        } else {
            // Add new schedule
            $insert_query = "INSERT INTO lab_schedules (lab_id, day_of_week, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("isssss", $new_lab_id, $day_of_week, $start_time, $end_time, $status, $notes);
            
            if ($stmt->execute()) {
                $message = "New schedule added successfully!";
                $message_type = "success";
                // Redirect to schedule list after brief delay
                header("Refresh: 1; URL=index.php?lab_id=" . $new_lab_id);
            } else {
                $message = "Error adding schedule: " . $conn->error;
                $message_type = "error";
            }
        }
    } else {
        $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-primary-700 text-white shadow-md">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold"><?php echo $page_title; ?></h1>
                <div>
                    <a href="index.php<?php echo $lab_id ? "?lab_id=$lab_id" : ""; ?>" class="bg-primary-800 hover:bg-primary-900 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mx-auto px-4 py-6">
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                        <select id="lab_id" name="lab_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" required>
                            <option value="">-- Select Laboratory --</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($schedule['lab_id'] == $lab['lab_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab['lab_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="day_of_week" class="block text-sm font-medium text-gray-700 mb-1">Day of Week</label>
                        <select id="day_of_week" name="day_of_week" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" required>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <option value="<?php echo $day; ?>" <?php echo ($schedule['day_of_week'] == $day) ? 'selected' : ''; ?>>
                                    <?php echo $day; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo date('H:i', strtotime($schedule['start_time'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo date('H:i', strtotime($schedule['end_time'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500" required>
                            <?php foreach (['available', 'occupied', 'maintenance', 'reserved'] as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo ($schedule['status'] == $status) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"><?php echo htmlspecialchars($schedule['notes'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Optional: Add any additional information about this schedule.</p>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <a href="index.php<?php echo $lab_id ? "?lab_id=$lab_id" : ""; ?>" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md mr-2 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700">
                        <?php echo $is_edit ? 'Update Schedule' : 'Add Schedule'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 