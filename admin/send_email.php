<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// Get the file and email from the request
$file = $_POST['file'];
$email = $_POST['email'];
  // File path
$directory = "db_backup"; // Replace with the actual directory path
$filePath = $directory . '/' . $file;

// Instantiate PHPMailer
$mail = new PHPMailer\PHPMailer\PHPMailer();

// Set up SMTP configuration (replace with your own)
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';  // Replace with your SMTP host
$mail->SMTPAuth = true;
$mail->Username = 'cscro8.eams@gmail.com';  // Replace with your SMTP username
$mail->Password = 'hneqxzuvvhvqcoqf';  // Replace with your SMTP password
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

// Set email content
$mail->setFrom('cscro8.eams@gmail.com', 'CSC RO VIII - eAMS');  // Replace with your email address and name
$mail->addAddress($email);  // Add recipient email address

// Email content
$currentDate = date('m-d-Y');
$mail->Subject = 'eSEIS Database Backup as of ' . $currentDate;
$mail->Body = 'Attached is the backup file of the eSEIS database.';

// Attach the file
$mail->addAttachment($filePath, $file);

// Send the email
if ($mail->send()) {
  echo 'success';
} else {
  echo 'error';
}
