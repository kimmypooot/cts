<?php

include 'connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// Initialize variables
$statusMsg = '';
$errorMsg = '';

try {
    $conn = new PDO('mysql:host=' . $servername . ';dbname=' . $dbname, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_POST['forgotPassword'])) {
        // Sanitize and validate input
        $inputUsername = trim($_POST['forgot-username'] ?? '');
        $inputEmail = trim($_POST['forgot-email'] ?? '');

        // Server-side validation
        if (empty($inputUsername)) {
            $statusMsg = '<script>
                Swal.fire({
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonColor: "#dc3545",
                    confirmButtonText: "Try again",
                    title: "Validation Error",
                    html: "Username is required. Please enter your username.",
                    icon: "error"
                });
            </script>';
        } elseif (empty($inputEmail)) {
            $statusMsg = '<script>
                Swal.fire({
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonColor: "#dc3545",
                    confirmButtonText: "Try again",
                    title: "Validation Error",
                    html: "Email is required. Please enter your email address.",
                    icon: "error"
                });
            </script>';
        } elseif (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
            $statusMsg = '<script>
                Swal.fire({
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonColor: "#dc3545",
                    confirmButtonText: "Try again",
                    title: "Validation Error",
                    html: "Please enter a valid email address.",
                    icon: "error"
                });
            </script>';
        } else {
            // Check if user exists with both username and email
            $stmt = $conn->prepare("SELECT id, username, email, name FROM users_seis WHERE LOWER(username) = LOWER(:username) AND LOWER(email) = LOWER(:email)");
            $stmt->bindParam(':username', $inputUsername, PDO::PARAM_STR);
            $stmt->bindParam(':email', $inputEmail, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Generate new password
                $newPassword = generateRandomPassword(12); // Increased length for security

                // Update the user's password in the database
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users_seis SET password = :password WHERE id = :id");
                $updateStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
                $updateStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    // Send the new password via email
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'cscro8.eams@gmail.com';
                        $mail->Password = 'hneqxzuvvhvqcoqf';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->SMTPDebug = 0; // Set to 2 for debugging

                        $mail->setFrom('cscro8.eams@gmail.com', 'CSC RO VIII - ERSS');
                        $mail->addAddress($inputEmail, $row['name']);

                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset - ERSS';
                        $mail->Body = '
                        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;">
                            <h2 style="color: #0077b6; text-align: center;">Password Reset - ERSS</h2>
                            <p>Good day from <strong>CSC RO VIII - Examination Related Services System</strong></p>
                            <p>We hope this email finds you well. As per your request, we have generated a new password for your account.</p>
                            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p><strong>Account Details:</strong></p>
                                <p><strong>Username:</strong> ' . htmlspecialchars($row['username']) . '</p>
                                <p><strong>New Password:</strong> <span style="background-color: #0077b6; color: white; padding: 5px 10px; border-radius: 3px; font-family: monospace;">' . htmlspecialchars($newPassword) . '</span></p>
                            </div>
                            <p><strong>Important Security Notice:</strong></p>
                            <ul>
                                <li>Please change your password immediately after logging in</li>
                                <li>Keep your password confidential and do not share it with anyone</li>
                                <li>Use a strong, unique password for your account</li>
                            </ul>
                            <p>If you did not request this password reset, please contact our IT support immediately.</p>
                            <hr style="margin: 20px 0;">
                            <p style="font-size: 12px; color: #666;">
                                Best regards,<br>
                                <strong>CSC RO VIII - ERSS</strong><br>
                                Information Technology Group
                            </p>
                        </div>';

                        if ($mail->send()) {
                            $statusMsg = '<script>
                                Swal.fire({
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    confirmButtonColor: "#0077b6",
                                    confirmButtonText: "OK",
                                    title: "Password Reset Successful",
                                    html: "Your new password has been sent to:<br><strong>' . htmlspecialchars($inputEmail) . '</strong><br><br>Please check your email and login with your new password.",
                                    icon: "success"
                                }).then(() => {
                                    window.location.href = "login";
                                });
                            </script>';
                        } else {
                            $statusMsg = '<script>
                                Swal.fire({
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    confirmButtonColor: "#dc3545",
                                    confirmButtonText: "Try again",
                                    title: "Email Sending Failed",
                                    html: "Failed to send the email. Please contact IT support or try again later.",
                                    icon: "error"
                                });
                            </script>';
                        }
                    } catch (Exception $e) {
                        $statusMsg = '<script>
                            Swal.fire({
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                confirmButtonColor: "#dc3545",
                                confirmButtonText: "Try again",
                                title: "Email Configuration Error",
                                html: "There was an issue with the email configuration. Please contact IT support.",
                                icon: "error"
                            });
                        </script>';
                        
                        // Log the error for debugging (remove in production)
                        error_log("PHPMailer Error: " . $e->getMessage());
                    }
                } else {
                    $statusMsg = '<script>
                        Swal.fire({
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            confirmButtonColor: "#dc3545",
                            confirmButtonText: "Try again",
                            title: "Database Error",
                            html: "Failed to update password. Please try again later.",
                            icon: "error"
                        });
                    </script>';
                }
            } else {
                // Check if username exists (for better error messaging)
                $checkUsername = $conn->prepare("SELECT username FROM users_seis WHERE LOWER(username) = LOWER(:username)");
                $checkUsername->bindParam(':username', $inputUsername, PDO::PARAM_STR);
                $checkUsername->execute();
                
                // Check if email exists
                $checkEmail = $conn->prepare("SELECT email FROM users_seis WHERE LOWER(email) = LOWER(:email)");
                $checkEmail->bindParam(':email', $inputEmail, PDO::PARAM_STR);
                $checkEmail->execute();
                
                if (!$checkUsername->fetch()) {
                    $errorMessage = "Username '<strong>" . htmlspecialchars($inputUsername) . "</strong>' not found in our database.";
                } elseif (!$checkEmail->fetch()) {
                    $errorMessage = "Email '<strong>" . htmlspecialchars($inputEmail) . "</strong>' not found in our database.";
                } else {
                    $errorMessage = "The username and email combination does not match our records.";
                }
                
                $statusMsg = '<script>
                    Swal.fire({
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        confirmButtonColor: "#dc3545",
                        confirmButtonText: "Try again",
                        title: "Account Not Found",
                        html: "' . $errorMessage . '<br><br>Please verify your credentials and try again.",
                        icon: "error"
                    });
                </script>';
            }
        }
    }
} catch (PDOException $error) {
    $statusMsg = '<script>
        Swal.fire({
            allowOutsideClick: false,
            allowEscapeKey: false,
            confirmButtonColor: "#dc3545",
            confirmButtonText: "Try again",
            title: "System Error",
            html: "A system error occurred. Please contact IT support if this persists.",
            icon: "error"
        });
    </script>';
    
    // Log the error for debugging (remove in production)
    error_log("Database Error: " . $error->getMessage());
}

// Function to generate a random password
function generateRandomPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $password .= $characters[$index];
    }
    return $password;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="author" content="CSCRO8-ITG">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CSC RO VIII - ERSS | Password Recovery</title>
    
    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/preloader.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0077b6;
            --primary-hover: #005a8a;
            --primary-light: rgba(0, 119, 182, 0.1);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-heavy: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: url('img/cscbglogin.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(0, 119, 182, 0.1) 0%, 
                rgba(0, 119, 182, 0.05) 50%, 
                rgba(255, 255, 255, 0.1) 100%);
            z-index: -1;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .card-container {
            max-width: 480px;
            width: 100%;
            position: relative;
        }

        .forgot-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            box-shadow: var(--shadow-heavy);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .forgot-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
            z-index: 1;
        }

        .header-icon i {
            font-size: 28px;
            color: white;
        }

        .card-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            position: relative;
            z-index: 1;
        }

        .card-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .card-body {
            padding: 2.5rem 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            height: 58px;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem var(--primary-light);
            background: white;
        }

        .form-floating > .form-control:not(:placeholder-shown) {
            background: white;
        }

        .form-floating > label {
            color: var(--text-secondary);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border: none;
            border-radius: 16px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover), #004666);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 119, 182, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-outline-secondary {
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: 16px;
            padding: 1rem 2rem;
            font-weight: 500;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .btn-outline-secondary:hover {
            background: var(--text-secondary);
            border-color: var(--text-secondary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .button-group .btn {
            flex: 1;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .swal2-popup {
            font-family: 'Inter', sans-serif !important;
            border-radius: 20px !important;
            box-shadow: var(--shadow-heavy) !important;
        }

        .swal2-title {
            font-weight: 600 !important;
        }

        .swal2-confirm {
            border-radius: 12px !important;
            padding: 0.75rem 1.5rem !important;
            font-weight: 600 !important;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .main-container {
                padding: 1rem 0.5rem;
            }

            .card-header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .card-body {
                padding: 2rem 1.5rem;
            }

            .card-title {
                font-size: 1.5rem;
            }

            .button-group {
                flex-direction: column;
            }

            .button-group .btn {
                margin-bottom: 0.5rem;
            }
        }

        /* Accessibility */
        .form-control:focus {
            outline: none;
        }

        .btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Animation for form appearance */
        .forgot-card {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Preloader enhancement */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preloader-content {
            text-align: center;
            color: white;
        }

        .preloader-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto 1rem;
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="preloader-content">
            <div class="preloader-spinner"></div>
            <h5>Loading...</h5>
        </div>
    </div>

    <div class="main-container">
        <div class="card-container">
            <div class="forgot-card">
                <div class="card-header">
                    <div class="header-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h1 class="card-title">Forgot Password</h1>
                    <p class="card-subtitle">Enter your credentials to receive a new password</p>
                </div>

                <div class="card-body">
                    <!-- Display status message -->
                    <?php if (!empty($statusMsg)) { ?>
                        <div class="alert-container">
                            <?php echo $statusMsg; ?>
                        </div>
                    <?php } ?>

                    <form method="POST" id="forgotPasswordForm" novalidate>
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control" 
                                   name="forgot-username" 
                                   id="forgot-username" 
                                   placeholder="Enter your username"
                                   required 
                                   autocomplete="username"
                                   autofocus>
                            <label for="forgot-username">
                                <i class="bi bi-person me-2"></i>Username
                            </label>
                            <div class="invalid-feedback">
                                Please enter your username.
                            </div>
                        </div>

                        <div class="form-floating">
                            <input type="email" 
                                   class="form-control" 
                                   name="forgot-email" 
                                   id="forgot-email" 
                                   placeholder="Enter your email"
                                   required
                                   autocomplete="email">
                            <label for="forgot-email">
                                <i class="bi bi-envelope me-2"></i>Email Address
                            </label>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" 
                                    class="btn btn-primary" 
                                    name="forgotPassword" 
                                    id="forgotPasswordButton">
                                <span class="button-content">
                                    <i class="bi bi-send me-2"></i>
                                    Reset Password
                                </span>
                                <span class="loading-content d-none">
                                    <div class="loading-spinner"></div>
                                    Sending...
                                </span>
                            </button>
                            
                            <a href="login" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>
                                Back to Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/preloader.js"></script>
    
    <script>
        // Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Form validation and submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(event) {
            const form = this;
            const usernameInput = document.getElementById('forgot-username');
            const emailInput = document.getElementById('forgot-email');
            const button = document.getElementById('forgotPasswordButton');
            const buttonContent = button.querySelector('.button-content');
            const loadingContent = button.querySelector('.loading-content');
            
            // Reset validation states
            form.classList.remove('was-validated');
            usernameInput.classList.remove('is-invalid');
            emailInput.classList.remove('is-invalid');
            
            let isValid = true;
            
            // Validate username
            if (!usernameInput.value.trim()) {
                usernameInput.classList.add('is-invalid');
                isValid = false;
            } else if (usernameInput.value.trim().length < 3) {
                usernameInput.classList.add('is-invalid');
                usernameInput.nextElementSibling.nextElementSibling.textContent = 'Username must be at least 3 characters long.';
                isValid = false;
            }
            
            // Validate email
            if (!emailInput.value.trim()) {
                emailInput.classList.add('is-invalid');
                emailInput.nextElementSibling.nextElementSibling.textContent = 'Please enter your email address.';
                isValid = false;
            } else if (!isValidEmail(emailInput.value.trim())) {
                emailInput.classList.add('is-invalid');
                emailInput.nextElementSibling.nextElementSibling.textContent = 'Please enter a valid email address.';
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
                form.classList.add('was-validated');
                
                // Show validation error
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please correct the errors in the form and try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading state
            event.preventDefault();
            button.disabled = true;
            buttonContent.classList.add('d-none');
            loadingContent.classList.remove('d-none');
            
            // Disable form inputs
            usernameInput.disabled = true;
            emailInput.disabled = true;
            
            // Submit form after showing loading state
            setTimeout(() => {
                // Create a new form submission since we prevented the default
                const formData = new FormData(form);
                
                // Use fetch to submit the form
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    return response.text();
                }).then(html => {
                    // Replace the current page with the response
                    document.open();
                    document.write(html);
                    document.close();
                }).catch(error => {
                    console.error('Error:', error);
                    
                    // Reset button state on error
                    button.disabled = false;
                    buttonContent.classList.remove('d-none');
                    loadingContent.classList.add('d-none');
                    usernameInput.disabled = false;
                    emailInput.disabled = false;
                    
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'There was a problem connecting to the server. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'OK'
                    });
                });
            }, 800);
        });
        
        // Email validation function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Real-time validation
        document.getElementById('forgot-username').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
        
        document.getElementById('forgot-email').addEventListener('input', function() {
            if (this.value.trim() && isValidEmail(this.value)) {
                this.classList.remove('is-invalid');
            }
        });
        
        // Enhanced preloader
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                setTimeout(() => {
                    preloader.style.opacity = '0';
                    preloader.style.visibility = 'hidden';
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 300);
                }, 1000);
            }
        });
    </script>
</body>
</html>