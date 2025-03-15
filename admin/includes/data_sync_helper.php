<?php
/**
 * Helper functions to synchronize student data across database tables
 */

/**
 * Update student information across all related tables when changes are made
 * 
 * @param mysqli $conn Database connection
 * @param string $student_id Student ID
 * @param string $new_student_name New student name
 * @param array $additional_fields Additional fields to update (optional)
 * @return array Result with success status and message
 */
function sync_student_data($conn, $student_id, $new_student_name, $additional_fields = []) {
    // Initialize result
    $result = [
        'success' => false,
        'message' => '',
        'updated_records' => 0
    ];
    
    if (empty($student_id) || empty($new_student_name)) {
        $result['message'] = "Missing required student information for sync";
        return $result;
    }
    
    // Update sit_in_sessions table with new student name
    $update_query = "UPDATE sit_in_sessions 
                    SET student_name = ? 
                    WHERE student_id = ?";
    
    $stmt = $conn->prepare($update_query);
    
    if (!$stmt) {
        $result['message'] = "Error preparing statement: " . $conn->error;
        return $result;
    }
    
    $stmt->bind_param("ss", $new_student_name, $student_id);
    
    if (!$stmt->execute()) {
        $result['message'] = "Error updating sit-in records: " . $stmt->error;
        return $result;
    }
    
    $result['updated_records'] = $stmt->affected_rows;
    $stmt->close();
    
    // Add any future table updates here if needed
    // For example, if other tables store student information
    
    $result['success'] = true;
    $result['message'] = "Successfully synchronized student data across " . $result['updated_records'] . " records";
    
    return $result;
}
?>
