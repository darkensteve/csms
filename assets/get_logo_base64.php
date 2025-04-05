<?php
// Set headers to ensure we're returning the right content type
header('Content-Type: application/json');

// Path to the logo relative to this file
$logoPath = 'uclogo.jpg';

// Check if file exists
if (file_exists($logoPath)) {
    // Read the file and convert to base64
    $imageData = base64_encode(file_get_contents($logoPath));
    
    // Create the data URL format needed for PDFMake
    $base64Image = 'data:image/jpeg;base64,' . $imageData;
    
    // Return as JSON
    echo json_encode(['success' => true, 'data' => $base64Image]);
} else {
    // Return error if file not found
    echo json_encode(['success' => false, 'message' => 'Logo file not found']);
}
?>
