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

// Check if lab_schedules table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'lab_schedules'");
if ($table_exists->num_rows == 0) {
    // Redirect to create tables script
    header("Location: create_tables.php");
    exit;
}

// Get all labs
$labs_query = "SELECT * FROM labs ORDER BY lab_name";
$labs_result = mysqli_query($conn, $labs_query);
$labs = [];

if ($labs_result && mysqli_num_rows($labs_result) > 0) {
    while ($row = mysqli_fetch_assoc($labs_result)) {
        $labs[] = $row;
    }
}

// Get selected lab or default to first lab
$selected_lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : (isset($labs[0]) ? $labs[0]['lab_id'] : 0);

// Get schedules for selected lab
$schedules = [];
if ($selected_lab_id > 0) {
    $schedules_query = "SELECT ls.*, l.lab_name 
                        FROM lab_schedules ls
                        JOIN labs l ON ls.lab_id = l.lab_id
                        WHERE ls.lab_id = ?
                        ORDER BY FIELD(ls.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
                                ls.start_time";
    
    $stmt = $conn->prepare($schedules_query);
    $stmt->bind_param("i", $selected_lab_id);
    $stmt->execute();
    $schedules_result = $stmt->get_result();
    
    if ($schedules_result && $schedules_result->num_rows > 0) {
        while ($row = $schedules_result->fetch_assoc()) {
            $schedules[] = $row;
        }
    }
}

// Process status update if submitted
$status_updated = false;
$update_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    if ($schedule_id > 0 && in_array($new_status, ['available', 'occupied', 'maintenance', 'reserved'])) {
        $update_query = "UPDATE lab_schedules SET status = ?, notes = ? WHERE schedule_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssi", $new_status, $notes, $schedule_id);
        
        if ($update_stmt->execute()) {
            $status_updated = true;
            $update_message = "Schedule status updated successfully.";
            
            // Refresh schedules
            $stmt->execute();
            $schedules_result = $stmt->get_result();
            $schedules = [];
            
            if ($schedules_result && $schedules_result->num_rows > 0) {
                while ($row = $schedules_result->fetch_assoc()) {
                    $schedules[] = $row;
                }
            }
        } else {
            $update_message = "Error updating schedule status: " . $conn->error;
        }
    }
}

// Group schedules by day
$schedules_by_day = [];
foreach ($schedules as $schedule) {
    $day = $schedule['day_of_week'];
    if (!isset($schedules_by_day[$day])) {
        $schedules_by_day[$day] = [];
    }
    $schedules_by_day[$day][] = $schedule;
}

// Days of the week in order
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule Management</title>
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
    <style>
        .status-available {
            background-color: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .status-occupied {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .status-maintenance {
            background-color: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .status-reserved {
            background-color: #e0f2fe;
            border-color: #3b82f6;
            color: #1e40af;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-primary-700 text-white shadow-md">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Lab Schedule Management</h1>
                <div>
                    <a href="../admin.php" class="bg-primary-800 hover:bg-primary-900 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mx-auto px-4 py-6">
        <?php if ($status_updated): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $update_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Select Laboratory</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <?php foreach ($labs as $lab): ?>
                    <a href="?lab_id=<?php echo $lab['lab_id']; ?>" 
                       class="block text-center py-3 px-4 rounded-lg border-2 transition
                              <?php echo ($selected_lab_id == $lab['lab_id']) 
                                    ? 'bg-primary-100 border-primary-500 text-primary-800' 
                                    : 'bg-white border-gray-200 hover:bg-gray-50'; ?>">
                        <?php echo htmlspecialchars($lab['lab_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($selected_lab_id > 0): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">
                        Schedule for <?php echo htmlspecialchars($labs[array_search($selected_lab_id, array_column($labs, 'lab_id'))]['lab_name']); ?>
                    </h2>
                    <a href="edit_schedule.php?lab_id=<?php echo $selected_lab_id; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded transition">
                        <i class="fas fa-plus mr-2"></i> Add Schedule
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($schedules)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 italic">
                                        No schedules found for this laboratory.
                                        <a href="create_tables.php" class="text-primary-600 hover:underline">Click here to create default schedules</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($days_of_week as $day): ?>
                                    <?php if (isset($schedules_by_day[$day])): ?>
                                        <?php foreach ($schedules_by_day[$day] as $schedule): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $schedule['day_of_week']; ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php 
                                                        echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                                             date('h:i A', strtotime($schedule['end_time'])); 
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-<?php echo $schedule['status']; ?>">
                                                        <?php echo ucfirst($schedule['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                                        <?php echo !empty($schedule['notes']) ? htmlspecialchars($schedule['notes']) : '-'; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openModal(<?php echo $schedule['schedule_id']; ?>, '<?php echo $schedule['status']; ?>', '<?php echo addslashes($schedule['notes'] ?? ''); ?>')" 
                                                            class="text-primary-600 hover:text-primary-900 mr-3">
                                                        <i class="fas fa-edit"></i> Update Status
                                                    </button>
                                                    <a href="edit_schedule.php?id=<?php echo $schedule['schedule_id']; ?>" class="text-gray-600 hover:text-gray-900">
                                                        <i class="fas fa-cog"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Please select a laboratory to view and manage its schedule.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Update Schedule Status</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="schedule_id" id="modal_schedule_id" value="">
                <div class="p-4">
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="modal_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="modal_notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 flex justify-end">
                    <button type="button" onclick="closeModal()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md mr-2 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="update_status" class="bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(scheduleId, status, notes) {
            document.getElementById('modal_schedule_id').value = scheduleId;
            document.getElementById('modal_status').value = status;
            document.getElementById('modal_notes').value = notes;
            document.getElementById('statusModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
    </script>
</body>
</html> 