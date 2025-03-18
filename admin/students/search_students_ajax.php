<?php
// Include database connection
require_once '../includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'students' => []
];

// Check if search term is provided
if (isset($_POST['search_term']) && !empty($_POST['search_term'])) {
    $search_term = trim($_POST['search_term']);
    
    // Validate that search term only contains alphabetic characters and spaces
    if (preg_match('/[^a-zA-Z\s]/', $search_term)) {
        $response['message'] = "Please enter only alphabetic characters for student names. Numbers and special characters are not allowed.";
    } else {
        // Try to find the student table
        $potential_tables = ['users', 'students', 'student'];
        $table_check_query = "SHOW TABLES";
        $table_result = $conn->query($table_check_query);
        
        $tables_in_db = [];
        if ($table_result) {
            while($table_row = $table_result->fetch_row()) {
                $tables_in_db[] = $table_row[0];
            }
        }
        
        $table_name = '';
        foreach ($potential_tables as $table) {
            if (in_array($table, $tables_in_db)) {
                $table_name = $table;
                break;
            }
        }
        
        if (empty($table_name) && !empty($tables_in_db)) {
            // If no known student tables found, try the first table
            $table_name = $tables_in_db[0];
        }
        
        if (!empty($table_name)) {
            // Get the column structure of the selected table
            $columns = [];
            $col_result = $conn->query("SHOW COLUMNS FROM `{$table_name}`");
            if ($col_result) {
                while($col = $col_result->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }
            
            // Define priority columns for searching (case insensitive)
            $priority_columns = [];
            $secondary_columns = [];
            
            // Columns to completely exclude from search
            $excluded_terms = ['password', 'pass', 'passwd', 'profile', 'picture', 'image', 'photo'];
            
            // Categorize columns by priority
            foreach ($columns as $col) {
                $col_lower = strtolower($col);
                
                // Skip excluded columns
                $exclude = false;
                foreach ($excluded_terms as $term) {
                    if (strpos($col_lower, $term) !== false) {
                        $exclude = true;
                        break;
                    }
                }
                if ($exclude) continue;
                
                // High priority columns for search
                if (strpos($col_lower, 'name') !== false || 
                    strpos($col_lower, 'id') !== false || 
                    strpos($col_lower, 'email') !== false ||
                    strpos($col_lower, 'user') !== false) {
                    $priority_columns[] = $col;
                } else {
                    $secondary_columns[] = $col;
                }
            }
            
            // Build search conditions with priority columns first
            $search_conditions = [];
            $bind_values = [];
            $bind_types = "";
            
            // Add conditions for priority columns
            foreach ($priority_columns as $col) {
                $search_conditions[] = "`{$col}` LIKE ?";
                $bind_values[] = "%{$search_term}%";
                $bind_types .= "s";
            }
            
            // Split the search term for multi-word searches
            $term_parts = explode(" ", $search_term);
            if (count($term_parts) > 1) {
                // For each part of the name, search in appropriate columns
                $first_name_cols = [];
                $last_name_cols = [];
                
                foreach ($priority_columns as $col) {
                    $col_lower = strtolower($col);
                    if (strpos($col_lower, 'first') !== false || 
                        strpos($col_lower, 'fname') !== false) {
                        $first_name_cols[] = $col;
                    } 
                    else if (strpos($col_lower, 'last') !== false || 
                        strpos($col_lower, 'lname') !== false) {
                        $last_name_cols[] = $col;
                    }
                }
                
                // Try matching different parts of the name with appropriate columns
                if (count($first_name_cols) > 0 && count($last_name_cols) > 0) {
                    foreach ($term_parts as $i => $part) {
                        if (strlen($part) > 1) {
                            // Try first part as first name and second part as last name
                            if ($i == 0) {
                                foreach ($first_name_cols as $col) {
                                    $search_conditions[] = "`{$col}` LIKE ?";
                                    $bind_values[] = "%{$part}%";
                                    $bind_types .= "s";
                                }
                            } else {
                                foreach ($last_name_cols as $col) {
                                    $search_conditions[] = "`{$col}` LIKE ?";
                                    $bind_values[] = "%{$part}%";
                                    $bind_types .= "s";
                                }
                            }
                        }
                    }
                    
                    // Also try the reverse (last name first, first name second)
                    if (count($term_parts) >= 2) {
                        foreach ($last_name_cols as $col) {
                            $search_conditions[] = "`{$col}` LIKE ?";
                            $bind_values[] = "%{$term_parts[0]}%";
                            $bind_types .= "s";
                        }
                        foreach ($first_name_cols as $col) {
                            $search_conditions[] = "`{$col}` LIKE ?";
                            $bind_values[] = "%{$term_parts[1]}%";
                            $bind_types .= "s";
                        }
                    }
                }
            }
            
            // Create the query
            $query = "SELECT * FROM `{$table_name}` WHERE " . implode(" OR ", $search_conditions);
            
            // Create a prepared statement
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                // Create array of references for bind_param
                $bind_params = array();
                $bind_params[] = &$bind_types;
                
                foreach ($bind_values as $key => $value) {
                    $bind_params[] = &$bind_values[$key];
                }
                
                // Bind parameters and execute
                call_user_func_array(array($stmt, 'bind_param'), $bind_params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Fetch all students and add to response
                while ($row = $result->fetch_assoc()) {
                    $response['students'][] = $row;
                }
                
                $stmt->close();
                
                if (count($response['students']) > 0) {
                    $response['success'] = true;
                    $response['message'] = count($response['students']) . ' student(s) found';
                } else {
                    $response['message'] = 'No students found matching your search.';
                }
            } else {
                $response['message'] = "Statement preparation failed: " . $conn->error;
            }
        } else {
            $response['message'] = "No tables found in database to search.";
        }
    }
} else {
    $response['message'] = 'Search term is required';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
