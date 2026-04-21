<?php
header("Content-Type: application/json");
require '../connect.php'; // Your database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get POST data
    $id        = $_POST['id']; // assuming you include the ID when sending
    $fname     = $_POST['fname'];
    $mname     = $_POST['mname'];
    $lname     = $_POST['lname'];
    $username  = $_POST['username'];
    $email     = $_POST['email'];
    $position  = $_POST['position'];
    $fo_rsu    = $_POST['fo_rsu'];
    $type      = $_POST['type'];
    $birthday  = $_POST['birthday'];

    try {
        $stmt = $conn->prepare("UPDATE users_cscro8
                                SET fname = ?, mname = ?, lname = ?, minitial = ?, username = ?, email = ?, position = ?, fo_rsu = ?, type = ?, birthday = ?
                                WHERE id = ?");
        $stmt->execute([$fname, $mname, $lname, !empty($mname) ? strtoupper($mname[0]) . '.' : null, $username, $email, $position, $fo_rsu, $type, $birthday, $id]);

        echo json_encode(["success" => true, "message" => "User updated successfully."]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
