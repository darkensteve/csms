<?php
/**
 * Helper functions for handling timezone conversions
 * Specifically for ensuring Manila/Asia (GMT+8) timezone is displayed correctly
 */

// Always ensure Manila timezone is set
date_default_timezone_set('Asia/Manila');

/**
 * Formats a datetime string to Manila timezone (GMT+8)
 * 
 * @param string $datetime_string The datetime string to format
 * @param string $format The format to use (default: 'Y-m-d h:i A')
 * @return string Formatted datetime string
 */
function format_manila_time($datetime_string, $format = 'Y-m-d h:i A') {
    if (empty($datetime_string)) return '';
    
    // Create DateTime object and ensure it's in Manila timezone
    $dt = new DateTime($datetime_string);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    
    return $dt->format($format);
}

/**
 * Formats a time string only (no date) to Manila timezone (GMT+8)
 *
 * @param string $datetime_string The datetime string to format
 * @return string Time in h:i A format
 */
function format_time_manila($datetime_string) {
    return format_manila_time($datetime_string, 'h:i A');
}

/**
 * Formats a date string only (no time) from a datetime
 *
 * @param string $datetime_string The datetime string to format
 * @return string Date in Y-m-d format
 */
function format_date_manila($datetime_string) {
    return format_manila_time($datetime_string, 'Y-m-d');
}

/**
 * Gets current datetime in Manila timezone
 *
 * @param string $format The format to use
 * @return string Current datetime in specified format
 */
function manila_now($format = 'Y-m-d H:i:s') {
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}

/**
 * Ensures a MySQL connection is set to Manila timezone
 *
 * @param object $conn MySQL connection object
 * @return bool True if successful, false if not
 */
function set_connection_timezone($conn) {
    try {
        $conn->query("SET time_zone = '+08:00'");
        return true;
    } catch (Exception $e) {
        error_log("Failed to set MySQL timezone: " . $e->getMessage());
        return false;
    }
}
?>
