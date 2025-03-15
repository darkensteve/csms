<?php
// Include database connection
require_once 'includes/db_connect.php';
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

// Process search when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_term'])) {
    $search_term = trim($_POST['search_term']);
    
    if (!empty($search_term)) {
        // Get tables in the database
        $tables_in_db = [];
        $tables_result = $conn->query("SHOW TABLES");
        if ($tables_result) {
            while($table_row = $tables_result->fetch_row()) {
                $tables_in_db[] = $table_row[0];
            }
        }
        
        // Try each potential student table
        $potential_tables = ['users', 'students', 'student'];
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
            
            // Define columns to search in
            $search_columns = [];
            
            // Identify name and ID columns for searching
            foreach ($columns as $col) {
                $col_lower = strtolower($col);
                if (strpos($col_lower, 'name') !== false || 
                    strpos($col_lower, 'first') !== false || 
                    strpos($col_lower, 'last') !== false ||
                    strpos($col_lower, 'id') !== false) {
                    $search_columns[] = $col;
                }
            }
            
            // Build search conditions
            $search_conditions = [];
            $bind_values = [];
            $bind_types = "";
            
            // Add conditions for each search column
            foreach ($search_columns as $col) {
                $search_conditions[] = "`{$col}` LIKE ?";
                $bind_values[] = "%{$search_term}%";
                $bind_types .= "s";
            }
            
            if (!empty($search_conditions)) {
                // Create the query with limit to prevent too many results
                $query = "SELECT * FROM `{$table_name}` WHERE " . implode(" OR ", $search_conditions) . " LIMIT 10";
                
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
                    
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $response['students'][] = $row;
                        }
                        $response['success'] = true;
                    }
                    
                    $stmt->close();
                }
            }
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
