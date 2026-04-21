<?php
require '../connect.php';

$response = ['success' => false, 'message' => 'Something went wrong.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profileImage'], $_POST['userId'])) {
    try {
        $conn->beginTransaction();

        $userId = intval($_POST['userId']);
        $file = $_FILES['profileImage'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate image extension
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid file type.");
        }

        // Set upload target
        $targetDir = realpath(__DIR__ . '/../../uploads/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $targetDir .= '/';

        // Generate new filename
        $newFileName = uniqid("user_" . $userId . "_") . "." . $ext;
        $targetPath = $targetDir . $newFileName;

        // Fetch old image
        $stmt = $conn->prepare("SELECT profile FROM users_cscro8 WHERE id = ?");
        $stmt->execute([$userId]);
        $oldImage = $stmt->fetchColumn();

        // Move new image
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to upload image.");
        }

        // Delete old image if not default and exists
        if ($oldImage && $oldImage !== 'default.jpg' && file_exists($targetDir . $oldImage)) {
            unlink($targetDir . $oldImage);
        }

        // Update DB with new image
        $stmt = $conn->prepare("UPDATE users_cscro8 SET profile = ? WHERE id = ?");
        $stmt->execute([$newFileName, $userId]);

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Profile image updated successfully!';
        $response['filename'] = $newFileName;
    } catch (Exception $e) {
        $conn->rollBack();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);
?>
