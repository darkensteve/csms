<?php
// Set default timezone for PHP
date_default_timezone_set('Asia/Manila');

/**
 * Format a database datetime string to a user-friendly format in Manila timezone
 * 
 * @param string $datetime_str The database datetime string
 * @param string $format The desired output format (defaults to 'M d, Y h:i A')
 * @return string Formatted datetime
 */
function format_datetime($datetime_str, $format = 'M d, Y h:i A') {
    if (empty($datetime_str)) {
        return '';
    }
    
    try {
        $dt = new DateTime($datetime_str);
        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
        return $dt->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

/**
 * Get current datetime in Manila timezone
 * 
 * @param string $format The desired output format
 * @return string Current datetime
 */
function get_current_datetime($format = 'Y-m-d H:i:s') {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $now->format($format);
}

/**
 * Format a date only from database datetime string
 * 
 * @param string $datetime_str The database datetime string
 * @param string $format The desired output format (defaults to 'M d, Y')
 * @return string Formatted date
 */
function format_date($datetime_str, $format = 'M d, Y') {
    return format_datetime($datetime_str, $format);
}

/**
 * Format a time only from database datetime string
 * 
 * @param string $datetime_str The database datetime string
 * @param string $format The desired output format (defaults to 'h:i A')
 * @return string Formatted time
 */
function format_time($datetime_str, $format = 'h:i A') {
    return format_datetime($datetime_str, $format);
}
?>
