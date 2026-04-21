<?php
// Temporary diagnostic file - remove after checking
echo "<h3>Current PHP Upload Settings:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";

echo "<h3>Recommended Settings for 5MB files:</h3>";
echo "upload_max_filesize = 5M<br>";
echo "post_max_size = 6M<br>";
echo "max_execution_time = 120<br>";
echo "memory_limit = 64M<br>";

// Convert current settings to bytes for comparison
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int) $value;
    switch($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    return $value;
}

$uploadMax = convertToBytes(ini_get('upload_max_filesize'));
$postMax = convertToBytes(ini_get('post_max_size'));
$targetSize = 5 * 1024 * 1024; // 5MB in bytes

echo "<h3>Analysis:</h3>";
if ($uploadMax >= $targetSize) {
    echo "✅ upload_max_filesize is sufficient<br>";
} else {
    echo "❌ upload_max_filesize is too small (need at least 5M)<br>";
}

if ($postMax >= $targetSize) {
    echo "✅ post_max_size is sufficient<br>";
} else {
    echo "❌ post_max_size is too small (need at least 6M)<br>";
}

if (ini_get('file_uploads')) {
    echo "✅ File uploads are enabled<br>";
} else {
    echo "❌ File uploads are disabled<br>";
}
?>