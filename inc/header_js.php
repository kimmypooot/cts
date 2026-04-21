<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('DEBUG_MODE', false); // Set to false in production

// Function to detect if we're on a subdomain or main domain
function is_subdomain() {
    $host = $_SERVER['HTTP_HOST'];
    
    if (DEBUG_MODE) {
        error_log("DEBUG: Host is: " . $host);
    }
    
    // Check for imis subdomain patterns
    $is_sub = (
        strpos($host, 'imis.') === 0 || 
        preg_match('/^imis\./', $host) || 
        $host === 'imis.cscro8.com' ||
        strpos($host, 'imis.cscro8.com') !== false
    );
    
    if (DEBUG_MODE) {
        error_log("DEBUG: Is subdomain: " . ($is_sub ? 'YES' : 'NO'));
    }
    
    return $is_sub;
}

// Function to get base path
if (!function_exists('imis_get_base_path')) {
    function imis_get_base_path() {
        if (is_subdomain()) {
            return '';
        }
        
        $current_dir = dirname($_SERVER['SCRIPT_NAME']);
        $path_parts = explode('/', trim($current_dir, '/'));
        $imis_index = array_search('imis', $path_parts);
        
        if ($imis_index !== false) {
            $depth = count($path_parts) - $imis_index - 1;
            return str_repeat('../', $depth);
        }
        
        return './';
    }
}

// Check if user is logged in
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['name']) && $current_page !== 'login.php') {
    $base_path = is_subdomain() ? '/' : imis_get_base_path() . '/';
    $login_url = $base_path . 'login.php';
    header('Location: ' . $login_url);
    exit();
}

// Function to get the correct path relative to current page location
function get_imis_relative_path() {
    $is_sub = is_subdomain();
    
    if (DEBUG_MODE) {
        error_log("DEBUG: get_imis_relative_path - is_subdomain: " . ($is_sub ? 'YES' : 'NO'));
    }
    
    if ($is_sub) {
        if (DEBUG_MODE) {
            error_log("DEBUG: Returning root path for subdomain");
        }
        return '/';
    }
    
    $current_dir = dirname($_SERVER['SCRIPT_NAME']);
    if (DEBUG_MODE) {
        error_log("DEBUG: Current dir: " . $current_dir);
    }
    
    $path_parts = explode('/', trim($current_dir, '/'));
    $imis_index = array_search('imis', $path_parts);
    
    if ($imis_index !== false) {
        $depth = count($path_parts) - $imis_index - 1;
        $path = str_repeat('../', $depth);
        if (DEBUG_DEBUG) {
            error_log("DEBUG: Calculated path: " . $path);
        }
        return $path;
    }
    
    if (DEBUG_MODE) {
        error_log("DEBUG: Using fallback path");
    }
    return './';
}

// Function to get profile image path
function get_profile_image_path() {
    $base_path = get_imis_relative_path();
    
    if (!isset($_SESSION['profile']) || empty($_SESSION['profile'])) {
        return $base_path . 'assets/img/default-avatar.png';
    }
    
    $profile_filename = $_SESSION['profile'];
    
    // Define possible profile image locations
    $possible_paths = [
        'admin/uploads/' . $profile_filename,
        'uploads/' . $profile_filename,
        'assets/uploads/' . $profile_filename,
        'img/profile/' . $profile_filename,
        'assets/img/profile/' . $profile_filename
    ];
    
    // Check each possible path
    foreach ($possible_paths as $relative_path) {
        if (is_subdomain()) {
            $full_path = '/' . $relative_path;
            $server_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $relative_path;
        } else {
            $full_path = $base_path . $relative_path;
            // Fix: Calculate proper server path
            $document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            $script_dir = dirname($_SERVER['SCRIPT_NAME']);
            $server_path = $document_root . $script_dir . '/' . $relative_path;
        }
        
        if (file_exists($server_path)) {
            return $full_path;
        }
    }
    
    return $base_path . 'assets/img/default-avatar.png';
}

// Function to determine preloader message based on current page
function get_preloader_message() {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $request_uri = $_SERVER['REQUEST_URI'];
    
    if ($current_page === 'index_dashboard' || strpos($request_uri, 'index_dashboard') !== false) {
        return [
            'title' => 'Loading IMIS...',
            'subtitle' => 'Please wait while we prepare your dashboard'
        ];
    }
    
    return [
        'title' => 'Loading IMIS...',
        'subtitle' => 'Please wait while the content is loading'
    ];
}

$imis_path = get_imis_relative_path();
$preloader_message = get_preloader_message();
$profile_image_path = get_profile_image_path();

// Standardize image paths
$logo_path = $imis_path . 'assets/img/cscro8logo.png';
$header_logo_path = $imis_path . 'assets/img/csclogo.png';
$fallback_logo_path = $imis_path . 'assets/img/logo.png';
$fallback_avatar_path = $imis_path . 'assets/img/default-avatar.png';
?>

<!-- Critical CSS - Inline to prevent FOUC -->
<style>
    /* CRITICAL: Prevent FOUC - This must be inline and load first */
    html, body {
        margin: 0;
        padding: 0;
        /*background-color: #0077b6;*/
    }
    
    /* PRELOADER-SPECIFIC STYLES - Isolated to prevent Bootstrap conflicts */
    
    /* Only hide overflow while loading */
    body.loading {
        overflow: hidden;
        position: fixed;
        width: 100%;
        height: 100%;
    }
    
    /* Hide all content initially - Exclude ALL Bootstrap modal-related elements */
    body.loading > *:not(#preloader):not(script):not(style):not(.modal):not(.modal-backdrop):not([class*="modal"]) {
        visibility: hidden;
        opacity: 0;
    }
    
    /* Preloader container - High z-index but will be lowered when hidden */
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #0077b6 0%, #005577 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.3s ease-out;
    }
    
    /* When preloader is hidden, ensure it doesn't interfere with anything */
    #preloader.preloader-hidden {
        pointer-events: none;
        z-index: -1;
        visibility: hidden;
    }

    /* Preloader content animations */
    .preloader-content {
        text-align: center;
        color: white;
        opacity: 0;
        animation: fadeInContent 0.3s ease-out 0.1s forwards;
    }

    @keyframes fadeInContent {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .logo-container {
        animation: logoFloat 2s ease-in-out infinite 1.1s;
        margin-bottom: 1rem;
    }

    .preloader-logo {
        height: 80px;
        width: auto;
        transition: all 0.3s ease;
    }

    @keyframes logoFloat {
        0%, 100% {
            transform: translateY(0px);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .preloader-content .spinner-border {
        width: 3rem;
        height: 3rem;
        border: 0.3em solid rgba(255, 255, 255, 0.2);
        border-top: 0.3em solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .loading-text h5 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: white;
        font-size: 1.2rem;
        margin-top: 1rem;
    }

    .loading-text p {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    /* Content reveal - Only apply to non-modal elements and avoid transitions */
    body.content-loaded > *:not(#preloader):not(.modal):not(.modal-backdrop):not([class*="modal"]) {
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Remove loading constraints when content is loaded */
    body.content-loaded {
        /* background-color: initial; */
        overflow: auto !important;
        position: static !important;
        width: auto !important;
        height: auto !important;
    }
    
    /* DO NOT override Bootstrap's modal-open class - let Bootstrap handle it naturally */
    
    /* Additional Styles (loaded after critical CSS)
    /* Additional component styles */
    .swal2-popup {
        font-family: 'Poppins', sans-serif;
        font-size: 0.90rem !important;
    }

    label,
    input[type="password"] {
        font-size: 14px;
    }

    .small-swal-modal .swal2-modal {
        max-width: 325px;
    }

    .profile-img {
        width: 36px;
        height: 36px;
        object-fit: cover;
    }

    .header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .logo img {
        height: 40px;
        width: auto;
    }

    .nav-profile img {
        border: 2px solid #dee2e6;
        transition: border-color 0.3s ease;
    }

    .nav-profile:hover img {
        border-color: #0d6efd;
    }

    /* Ensure smooth transitions for all elements */
    * {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
</style>

<!-- Preloader Structure -->
<div id="preloader">
    <div class="preloader-content">
        <div class="logo-container">
            <img src="<?php echo $logo_path; ?>" alt="CSC Logo" class="preloader-logo" onerror="this.src='<?php echo $fallback_logo_path; ?>'">
        </div>
        <div class="spinner-border" role="status">
            <span class="visually-hidden"></span>
        </div>
        <div class="loading-text">
            <h5><?php echo $preloader_message['title']; ?></h5>
            <p><?php echo $preloader_message['subtitle']; ?></p>
        </div>
    </div>
</div>

<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="<?php echo $imis_path; ?>" class="logo d-flex align-items-center">
            <img src="<?php echo $header_logo_path; ?>" alt="CSC Logo" onerror="this.src='<?php echo $fallback_logo_path; ?>'">
            <span class="d-none d-lg-block">&nbsp;CSC RO VIII</span>
        </a>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">
            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <img src="<?php echo $profile_image_path; ?>" 
                         alt="Profile" 
                         class="rounded-circle profile-img"
                         onerror="this.src='<?php echo $fallback_avatar_path; ?>'">
                    <span class="d-none d-md-block dropdown-toggle ps-2">
                        <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?>
                    </span>
                </a>
                
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h6>
                        <span><?php echo isset($_SESSION['fo_rsu']) ? htmlspecialchars($_SESSION['fo_rsu']) : ''; ?></span><br>
                        <span><?php echo isset($_SESSION['position']) ? htmlspecialchars($_SESSION['position']) : ''; ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
                        <a href="#" id="switchToSuperAdmin" class="dropdown-item d-flex align-items-center">
                            <i class="bi bi-person-gear me-2"></i>
                            <span>Super Administrator Mode</span>
                        </a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <a type="button" class="dropdown-item d-flex align-items-center" id="changepw">
                            <i class="bi bi-shield-lock"></i>
                            <span>Change Password</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" id="logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Sign Out</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</header>

<!-- Include SweetAlert2 and Idle Session Manager -->
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $imis_path; ?>js/idle_session_manager.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const switchBtn = document.getElementById('switchToSuperAdmin');
    if (switchBtn) {
        switchBtn.addEventListener('click', function (e) {
            e.preventDefault();

            Swal.fire({
                title: 'Switch to Super Administrator?',
                text: 'You are about to enter Super Administrator Mode.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "https://imis.cscro8.com/admin/index";
                }
            });
        });
    }
});
</script>
<script>
// Configuration
const LOGOUT_URL = 'inc/logout';
const CHANGE_PASSWORD_URL = 'change_pw.php';

// Dynamic path detection
function getImisPath() {
    const host = window.location.hostname;
    const isSubdomain = host.startsWith('imis.') || /^imis\./.test(host) || host === 'imis.cscro8.com';
    
    if (isSubdomain) {
        return '/';
    }
    
    const currentPath = window.location.pathname;
    const pathParts = currentPath.split('/');
    const imisIndex = pathParts.indexOf('imis');
    
    if (imisIndex !== -1) {
        const depth = pathParts.length - imisIndex - 2;
        return '../'.repeat(Math.max(0, depth));
    }
    
    return './';
}

// Enhanced preloader functionality with FOUC prevention
document.addEventListener('DOMContentLoaded', function() {
    // Add loading class to body immediately
    document.body.classList.add('loading');
    
    // Ensure preloader is visible immediately
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.style.display = 'flex';
    }
    
    // Handle complete page load
    function hidePreloader() {
        setTimeout(function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.style.opacity = '0';
                // Use specific preloader class to avoid conflicts
                preloader.classList.add('preloader-hidden');
                setTimeout(function() {
                    preloader.style.display = 'none';
                    document.body.classList.remove('loading');
                    document.body.classList.add('content-loaded');
                }, 500);
            }
        }, 1500); // Minimum 1.5 seconds display time for proper layout rendering
    }
    
    // Multiple triggers to ensure preloader hides
    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader);
        // Fallback timeout - increased to ensure proper layout display
        setTimeout(hidePreloader, 8000);
    }
});

// REMOVED: Enhanced modal handling that interfered with Bootstrap
// Bootstrap 5.3.3 handles all modal transitions and z-index management automatically
// No custom modal event listeners needed

// Enhanced logout functionality with idle session integration
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            // Destroy idle session manager if it exists
            if (window.idleSessionManager) {
                window.idleSessionManager.destroy();
            }
            
            Swal.fire({
                title: 'Are you sure you want to logout?',
                text: "You will be redirected to the login page.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="bi bi-box-arrow-right"></i> Confirm',
                cancelButtonText: '<i class="bi bi-x-circle"></i> Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    let timerInterval;
                    Swal.fire({
                        title: 'You have been successfully logged out.',
                        icon: 'success',
                        html: 'Redirecting to the login page in <strong></strong> second/s.<br/><br/>',
                        timer: 3500,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                            timerInterval = setInterval(() => {
                                const timeLeft = Swal.getTimerLeft();
                                if (timeLeft) {
                                    Swal.getHtmlContainer().querySelector('strong')
                                        .textContent = (timeLeft / 1000).toFixed(0);
                                }
                            }, 100);
                        },
                        willClose: () => {
                            clearInterval(timerInterval);
                            const imisPath = getImisPath();
                            const reason = window.idleSessionManager ? '?reason=manual' : '';
                            window.location.href = imisPath + LOGOUT_URL + reason;
                        }
                    });
                }
            });
        });
    }
});

// Change password functionality
document.addEventListener('DOMContentLoaded', function() {
    const changePasswordBtn = document.getElementById('changepw');
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Confirmation',
                text: 'Are you sure you want to change the password?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    showChangePasswordForm();
                }
            });
        });
    }
});

function showChangePasswordForm() {
    Swal.fire({
        title: 'Change Password',
        html: generatePasswordForm(),
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-lock"></i> Update',
        cancelButtonText: '<i class="bi bi-x-circle"></i> Close',
        customClass: {
            container: 'small-swal-modal'
        },
        preConfirm: handlePasswordChange
    });
}

function generatePasswordForm() {
    return `
        <form id="changePasswordForm">
            <div class="mb-3">
                <label for="currentPassword" class="form-label"><b>Current Password:</b></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('currentPassword')">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <label for="newPassword" class="form-label"><b>New Password:</b></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('newPassword')">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <label for="confirmPassword" class="form-label"><b>Confirm Password:</b></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirmPassword')">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </form>
    `;
}

function handlePasswordChange() {
    const currentPassword = Swal.getPopup().querySelector('#currentPassword').value;
    const newPassword = Swal.getPopup().querySelector('#newPassword').value;
    const confirmPassword = Swal.getPopup().querySelector('#confirmPassword').value;

    if (!currentPassword || !newPassword || !confirmPassword) {
        Swal.showValidationMessage('Please fill in all fields');
        return false;
    }

    if (newPassword !== confirmPassword) {
        Swal.showValidationMessage('New passwords do not match');
        return false;
    }

    if (newPassword.length < 6) {
        Swal.showValidationMessage('New password must be at least 6 characters long');
        return false;
    }

    const imisPath = getImisPath();
    
    return fetch(imisPath + CHANGE_PASSWORD_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            currentPassword: currentPassword,
            newPassword: newPassword,
            confirmPassword: confirmPassword
        }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success',
                text: data.message,
                icon: 'success'
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message,
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while changing the password.',
            icon: 'error'
        });
    });
}

// Password visibility toggle function
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;

    if (input && button) {
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = 'password';
            button.innerHTML = '<i class="bi bi-eye"></i>';
        }
    }
}

// Session management functions
function checkSessionStatus() {
    if (window.idleSessionManager) {
        const remaining = window.idleSessionManager.getRemainingTime();
        const isActive = window.idleSessionManager.isSessionActive();
        
        return {
            remainingTime: remaining,
            isActive: isActive
        };
    }
    return null;
}

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (window.idleSessionManager && !document.hidden) {
        // Page became visible - this counts as activity
        console.log('Page visible - activity detected');
    }
});

// Handle image loading errors
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('error', function() {
            if (!this.dataset.fallbackApplied) {
                this.dataset.fallbackApplied = 'true';
                const basePath = getImisPath();
                this.src = basePath + 'assets/img/default-avatar.png';
            }
        });
    });
});

// Export for debugging
window.checkSessionStatus = checkSessionStatus;
</script>