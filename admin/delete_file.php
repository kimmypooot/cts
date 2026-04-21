<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_POST['file'];
    $directory = "db_backup"; // Replace with the actual directory path

    $filePath = $directory . '/' . $file;

    if (is_file($filePath)) {
        if (unlink($filePath)) {
            echo "File deleted successfully.";
        } else {
            echo "Error deleting the file.";
        }
    } else {
        echo "File not found.";
    }
} else {
    echo "Invalid request.";
}
?>
