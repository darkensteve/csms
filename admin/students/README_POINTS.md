# Student Points System

This feature allows administrators to grant points to students for good behavior. Once a student accumulates 3 points, the system automatically converts those points into 1 additional session.

## Setup Instructions

1. Run the setup script to add the necessary database components:
   ```
   http://your-site.com/admin/students/setup_points.php
   ```

   This script will:
   - Add a 'points' column to the users table
   - Create a trigger to automatically convert points to sessions
   - Create a points_log table to track point additions

## How It Works

1. **Adding Points**:
   - In the student management page, click the star icon next to a student's record
   - Enter the number of points (1-10) and an optional reason
   - Click "Add Points" to grant the points to the student

2. **Points Conversion**:
   - For every 3 points a student accumulates, the system automatically:
     - Adds 1 session to their remaining_sessions count
     - Subtracts 3 points from their points total

3. **Viewing Points Log**:
   - Click on "Points Log" in the student management page
   - View a complete history of all points awarded to students
   - Search by student ID or admin username

## Technical Details

- The points conversion is handled by a MySQL trigger called `convert_points_to_sessions`
- Points are stored in the `points` column of the `users` table
- All point transactions are logged in the `points_log` table

## Files

- `setup_points.php`: Sets up the database components
- `add_points.php`: Handles the point addition logic
- `add_points_modal.php`: Contains the modal for adding points
- `points_log.php`: Displays the points log 