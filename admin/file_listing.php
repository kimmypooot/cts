<?php
$directory = "db_backup"; // Replace with the actual directory path
$data = [];

if (is_dir($directory)) {
    $contents = scandir($directory);

    foreach ($contents as $item) {
        if ($item != '.' && $item != '..') {
            $itemPath = $directory . '/' . $item;

            if (is_file($itemPath) && pathinfo($itemPath, PATHINFO_EXTENSION) == 'zip') {
                // File information
                $modifiedDate = date("Y-m-d H:i:s", filemtime($itemPath));
                $downloadLink = $directory . '/' . $item;

                $data[] = [
                    'file' => $item,
                    'modified' => $modifiedDate,
                    'download' => $downloadLink
                ];
            }
        }
    }
}

echo json_encode(['data' => $data]);
?>
