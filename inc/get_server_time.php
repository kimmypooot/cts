<?php
// imis/inc/get_server_time.php - Optimized for performance and reduced memory usage
// Optimized server time endpoint
header("Content-Type: application/json");
header("Cache-Control: public, max-age=300"); // Cache for 5 minutes
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Simple response - removed unnecessary fields
echo json_encode([
    'server_time' => time(),
    'timezone' => 'Asia/Manila'
]);

// Immediate exit to reduce memory usage
exit();
