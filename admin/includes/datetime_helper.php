<?php
/**
 * Helper functions for date and time formatting
 */

/**
 * Format a datetime string into a human-friendly format
 * 
 * @param string $datetime The datetime string to format
 * @param string $format Optional custom format
 * @return string Formatted datetime string
 */
function format_datetime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

/**
 * Calculate time difference between two datetimes in a human-readable format
 * 
 * @param string $start_time Start datetime
 * @param string $end_time End datetime (defaults to current time if not provided)
 * @return string Human-readable time difference
 */
function get_time_difference($start_time, $end_time = null) {
    if (empty($start_time)) {
        return 'N/A';
    }
    
    try {
        $start = new DateTime($start_time);
        $end = $end_time ? new DateTime($end_time) : new DateTime('now');
        
        $interval = $start->diff($end);
        
        if ($interval->days > 0) {
            return $interval->format('%d day(s), %h hr, %i min');
        } else if ($interval->h > 0) {
            return $interval->format('%h hr, %i min');
        } else {
            return $interval->format('%i minutes');
        }
    } catch (Exception $e) {
        return 'Invalid time';
    }
}

/**
 * Format a date only (without time)
 * 
 * @param string $datetime The datetime string to format
 * @param string $format Optional custom format
 * @return string Formatted date string
 */
function format_date($datetime, $format = 'M j, Y') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

/**
 * Format time only (without date)
 * 
 * @param string $datetime The datetime string to format
 * @param string $format Optional custom format
 * @return string Formatted time string
 */
function format_time($datetime, $format = 'g:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return 'Invalid time';
    }
}
