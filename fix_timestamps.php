<?php
// Script to fix timestamps in the database that may have been stored in wrong timezone

// Set the timezone
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'includes/db_connect.php';
require_once 'includes/datetime_helper.php';

// Check if admin is logged in for security (comment out for quick fix)
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo "You must be logged in as an admin to run this script.";
    exit;
}

echo "<h1>Timestamp Fix Utility</h1>";

// Get the current offset
$now = new DateTime();
$offset_hours = $now->getOffset() / 3600;
echo "<p>Your current PHP timezone is set to: " . date_default_timezone_get() . " (GMT" . ($offset_hours >= 0 ? '+' : '') . $offset_hours . ")</p>";

// Check if form submitted
$action_taken = false;
$converted = 0;

if (isset($_POST['confirm_fix'])) {
    // Setup MySQL session timezone
    $conn->query("SET time_zone = '+08:00'");
    
    // Check if we're adding or subtracting hours
    $adjustment = (int)$_POST['adjustment'];
    
    if ($adjustment != 0) {
        // Fix check_in_time
        $update_query = "UPDATE sit_in_sessions 
                         SET check_in_time = DATE_ADD(check_in_time, INTERVAL ? HOUR) 
                         WHERE check_in_time IS NOT NULL";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $adjustment);
        $stmt->execute();
        $affected1 = $stmt->affected_rows;
        
        // Fix check_out_time
        $update_query = "UPDATE sit_in_sessions 
                         SET check_out_time = DATE_ADD(check_out_time, INTERVAL ? HOUR) 
                         WHERE check_out_time IS NOT NULL";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $adjustment);
        $stmt->execute();
        $affected2 = $stmt->affected_rows;
        
        $converted = $affected1 + $affected2;
        $action_taken = true;
        
        echo "<div style='padding:10px; background-color:#d1fae5; border-left:4px solid #10b981; margin:20px 0;'>
            <p><strong>Success!</strong> Applied {$adjustment} hour adjustment to {$converted} timestamp records.</p>
        </div>";
    }
}

// Get a sample of records
$sample_query = "SELECT session_id, check_in_time, check_out_time FROM sit_in_sessions ORDER BY check_in_time DESC LIMIT 10";
$sample_result = $conn->query($sample_query);

?>

<h2>Sample Current Timestamp Records</h2>

<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">
    <tr>
        <th>ID</th>
        <th>Check In (DB)</th>
        <th>Check In (Manila)</th>
        <th>Check Out (DB)</th>
        <th>Check Out (Manila)</th>
    </tr>
    
    <?php if ($sample_result->num_rows > 0): ?>
        <?php while ($row = $sample_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['session_id']; ?></td>
                <td><?php echo $row['check_in_time']; ?></td>
                <td><?php echo format_datetime($row['check_in_time']); ?></td>
                <td><?php echo $row['check_out_time'] ?? 'N/A'; ?></td>
                <td><?php echo !empty($row['check_out_time']) ? format_datetime($row['check_out_time']) : 'N/A'; ?></td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">No records found</td>
        </tr>
    <?php endif; ?>
</table>

<?php if (!$action_taken): ?>
<h2>Fix Timestamps</h2>
<p>If the timestamps shown above are incorrect (for example, off by several hours), you can adjust them here:</p>

<form method="post" onsubmit="return confirm('Are you sure you want to modify all timestamps? This cannot be undone!');">
    <p>
        <label>
            <input type="radio" name="adjustment" value="8"> 
            Add 8 hours (Convert from UTC to Manila)
        </label>
    </p>
    <p>
        <label>
            <input type="radio" name="adjustment" value="-8"> 
            Subtract 8 hours (Convert from Manila to UTC)
        </label>
    </p>
    <p>
        <label>
            <input type="radio" name="adjustment" value="0" checked> 
            No adjustment needed
        </label>
    </p>
    <p style="margin-top: 20px;">
        <button type="submit" name="confirm_fix" style="padding: 10px 15px; background-color: #0ea5e9; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Adjust Timestamps
        </button>
    </p>
</form>
<?php else: ?>
<p>
    <a href="fix_timestamps.php" style="padding: 10px 15px; background-color: #0ea5e9; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px;">
        Run Another Fix
    </a>
</p>
<?php endif; ?>

<p style="margin-top: 30px;">
    <a href="admin.php" style="color: #0ea5e9;">Return to Admin Dashboard</a>
</p>
