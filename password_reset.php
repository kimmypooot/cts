<?php
if (isset($_POST['reset_password'])) {
  $email = $_POST['email'];
  $resetCode = $_POST['reset_code'];
  $newPassword = $_POST['new_password'];
  
  try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $query = "SELECT * FROM users_seis WHERE email = :email AND reset_code = :reset_code";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':reset_code', $resetCode, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
      // Update the user's password with the new password
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      
      $updateQuery = "UPDATE users_seis SET password = :password, reset_code = NULL WHERE email = :email";
      
      $updateStmt = $conn->prepare($updateQuery);
      $updateStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
      $updateStmt->bindParam(':email', $email, PDO::PARAM_STR);
      $updateStmt->execute();
      
      echo "Password updated successfully.";
    } else {
      echo "Invalid email or reset code.";
    }
  } catch (PDOException $e) {
    echo "Password update failed: " . $e->getMessage();
  }
}
?>

<!-- Password Reset Form -->
<form method="POST" action="">
  <div class="form-group">
    <label for="email">Email:</label>
    <input type="email" name="email" id="email" required>
  </div>
  <div class="form-group">
    <label for="reset_code">Reset Code:</label>
    <input type="text" name="reset_code" id="reset_code" required>
  </div>
  <div class="form-group">
    <label for="new_password">New Password:</label>
    <input type="password" name="new_password" id="new_password" required>
  </div>
  <div class="form-group">
    <input type="submit" name="reset_password" value="Reset Password">
  </div>
</form>
