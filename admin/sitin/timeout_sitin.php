<?php
// Include database connection
require_once '../includes/db_connect.php';
// Include notification functions
require_once '../../includes/notification_functions.php';
session_start();

// Create a log file for debugging
function debug_log($message) {
    $log_file = __DIR__ . '/timeout_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Fallback for getallheaders() which may not be available in all environments
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Log start of script execution
debug_log("Script started. POST data: " . json_encode($_POST));
debug_log("Request method: " . $_SERVER['REQUEST_METHOD']);
debug_log("Headers: " . json_encode(getallheaders()));

// Check if this is a direct submit request (the new, simpler approach)
$is_direct_submit = isset($_POST['direct_submit']) && $_POST['direct_submit'] == '1';
debug_log("Is direct submit: " . ($is_direct_submit ? "true" : "false"));

// For backward compatibility, also check manual fallback
$is_manual_fallback = isset($_POST['manual_fallback']) && $_POST['manual_fallback'] == '1';
debug_log("Is manual fallback: " . ($is_manual_fallback ? "true" : "false"));

// Determine if this is an AJAX request
$is_ajax = false;
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $is_ajax = true;
}

// If it's a direct submit or manual fallback, treat as a normal form submission
if ($is_direct_submit || $is_manual_fallback) {
    $is_ajax = false;
    debug_log("Using direct form submission method");
}

debug_log("Is AJAX request: " . ($is_ajax ? "true" : "false"));

// Check if user is logged in (either admin or regular user)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    debug_log("Unauthorized access: No valid session found");
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    } else {
        $_SESSION['sitin_message'] = "Unauthorized access";
        $_SESSION['sitin_status'] = 'error';
        header('Location: current_sitin.php');
        exit();
    }
    exit();
}

// Check and fix system_logs table structure inline
$table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `system_logs` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` VARCHAR(50),
        `action` VARCHAR(255) NOT NULL,
        `action_type` VARCHAR(50) NOT NULL DEFAULT 'general',
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `user_agent` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table_sql);
} else {
    // Check if action_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `system_logs` LIKE 'action_type'");
    if ($column_check->num_rows == 0) {
        // Column doesn't exist, add it
        $add_column_sql = "ALTER TABLE `system_logs` 
                          ADD COLUMN `action_type` VARCHAR(50) NOT NULL DEFAULT 'general' AFTER `action`";
        $conn->query($add_column_sql);
    }
}

// Get the sit-in ID from either GET or POST
$sitin_id = 0;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $sitin_id = intval($_GET['id']);
    debug_log("Processing timeout from GET request for sitin_id: $sitin_id");
} elseif (isset($_POST['id']) && !empty($_POST['id'])) {
    $sitin_id = intval($_POST['id']);
    debug_log("Processing timeout from POST request for sitin_id: $sitin_id");
} else {
    debug_log("Error: No sitin ID provided in request data");
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Sit-in ID is required']);
    } else {
        $_SESSION['sitin_message'] = "Error: No sit-in ID provided. Cannot process timeout.";
        $_SESSION['sitin_status'] = 'error';
        header('Location: current_sitin.php');
        exit();
    }
    exit();
}

debug_log("Processing timeout for sitin_id: $sitin_id");

// Get the current time in Philippine timezone
date_default_timezone_set('Asia/Manila');
$current_time = date('Y-m-d H:i:s');

// Start transaction
$conn->begin_transaction();
debug_log("Transaction started");

try {
    // First get the student_id and computer_id for the sit-in session to update their remaining sessions
    $get_student_query = "SELECT student_id, computer_id FROM sit_in_sessions WHERE session_id = ? AND status = 'active'";
    debug_log("Executing query: $get_student_query with id: $sitin_id");
    $stmt_get = $conn->prepare($get_student_query);
    $stmt_get->bind_param("i", $sitin_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
        $student_id = $student_data['student_id'];
        $computer_id = $student_data['computer_id'];
        debug_log("Found active session for student_id: $student_id, computer_id: " . ($computer_id ?? 'null'));
        
        // Update sit-in session status to inactive and set check_out_time
        $update_query = "UPDATE sit_in_sessions SET status = 'inactive', check_out_time = ? WHERE session_id = ? AND status = 'active'";
        debug_log("Executing update query: $update_query");
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $current_time, $sitin_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            debug_log("Successfully updated sit-in session status to inactive");
            // Now deduct one session from student's remaining sessions - THIS IS THE ONLY PLACE
            // WHERE WE SHOULD DEDUCT A SESSION
            $update_sessions_query = "UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE idNo = ? AND remaining_sessions > 0";
            debug_log("Executing sessions update query for student_id: $student_id");
            $stmt_sessions = $conn->prepare($update_sessions_query);
            $stmt_sessions->bind_param("s", $student_id);
            $stmt_sessions->execute();
            
            // If there's a computer associated with this session, set it back to available
            if ($computer_id) {
                $update_computer_query = "UPDATE computers SET status = 'available' WHERE computer_id = ?";
                debug_log("Updating computer status to available for computer_id: $computer_id");
                $stmt_computer = $conn->prepare($update_computer_query);
                $stmt_computer->bind_param("i", $computer_id);
                $stmt_computer->execute();
            }
            
            // Log the timeout action
            $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $actor_id = $admin_id ?: $user_id;
            debug_log("Actor ID for system log: $actor_id");

            // Use the correct column names that match the system_logs table structure
            $log_query = "INSERT INTO system_logs (user_id, action, action_type, details, ip_address) 
                          VALUES (?, 'timeout_sitin', 'sit_in', ?, ?)";
            $log_details = "Sit-in session ID: $sitin_id was timed out";
            $ip_address = $_SERVER['REMOTE_ADDR'];

            debug_log("Logging timeout action to system_logs");
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("sss", $actor_id, $log_details, $ip_address);
            $log_stmt->execute();
            
            // Send notification to the student about timeOut
            $admin_username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
            
            // If we have the student ID, get their user_id for notification
            if ($student_id) {
                // Get the user_id
                $get_user_query = "SELECT user_id, USER_ID FROM users WHERE idNo = ? LIMIT 1";
                $user_stmt = $conn->prepare($get_user_query);
                if ($user_stmt) {
                    $user_stmt->bind_param("s", $student_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    
                    if ($user_result->num_rows > 0) {
                        $user_data = $user_result->fetch_assoc();
                        $user_id_to_notify = $user_data['user_id'] ?? $user_data['USER_ID'] ?? null;
                        
                        if ($user_id_to_notify) {
                            debug_log("Sending timeout notification to user_id: $user_id_to_notify");
                            $notify_result = notify_sitin_timeout(
                                $sitin_id,
                                $user_id_to_notify,
                                $admin_id,
                                $admin_username
                            );
                            debug_log("Notification result: " . ($notify_result ? "Success ($notify_result)" : "Failed"));
                        } else {
                            debug_log("Could not find valid user_id for notification");
                        }
                    } else {
                        debug_log("User record not found for student_id: $student_id");
                    }
                    $user_stmt->close();
                }
            }
            
            $conn->commit();
            debug_log("Transaction committed successfully");
            
            // Prepare success message
            $message = "Student successfully timed out and session deducted.";
            
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                // For direct form submission, set session message and redirect
                $_SESSION['sitin_message'] = $message;
                $_SESSION['sitin_status'] = 'success';
                
                // Check if we're coming from GET (our direct link)
                if (isset($_GET['action']) && $_GET['action'] == 'timeout') {
                    // Add a timestamp and success parameter for nicer UX
                    $redirect_url = 'current_sitin.php?timeout_success=1&t=' . time();
                } else {
                    // Regular redirect for form submissions
                    $redirect_url = 'current_sitin.php?t=' . time();
                }
                
                debug_log("Redirecting to: " . $redirect_url);
                header("Location: $redirect_url");
                exit();
            }
        } else {
            // No rows updated - might be already inactive or ID not found
            debug_log("No rows updated - session might be already inactive");
            $conn->rollback();
            
            $error_message = "No active sit-in session found with that ID.";
            
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => $error_message]);
            } else {
                $_SESSION['sitin_message'] = $error_message;
                $_SESSION['sitin_status'] = 'error';
                
                // Add a timestamp parameter to prevent caching
                $redirect_url = 'current_sitin.php?t=' . time();
                
                debug_log("Redirecting to: " . $redirect_url);
                header("Location: $redirect_url");
                exit();
            }
        }
    } else {
        debug_log("No active sit-in session found with ID: $sitin_id");
        $conn->rollback();
        
        $error_message = "No active sit-in session found with that ID.";
        
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => $error_message]);
        } else {
            $_SESSION['sitin_message'] = $error_message;
            $_SESSION['sitin_status'] = 'error';
            
            // Add a timestamp parameter to prevent caching
            $redirect_url = 'current_sitin.php?t=' . time();
            
            debug_log("Redirecting to: " . $redirect_url);
            header("Location: $redirect_url");
            exit();
        }
    }
} catch (Exception $e) {
    debug_log("Exception occurred: " . $e->getMessage());
    $conn->rollback();
    
    $error_message = "Error processing timeout: " . $e->getMessage();
    
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => $error_message]);
    } else {
        $_SESSION['sitin_message'] = $error_message;
        $_SESSION['sitin_status'] = 'error';
        
        // Add a timestamp parameter to prevent caching
        $redirect_url = 'current_sitin.php?t=' . time();
        
        debug_log("Redirecting to: " . $redirect_url);
        header("Location: $redirect_url");
        exit();
    }
}

debug_log("Script execution completed");
?>