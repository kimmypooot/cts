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
        if (DEBUG_MODE) { // Fixed typo from DEBUG_DEBUG
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

$imis_path = get_imis_relative_path();
$profile_image_path = get_profile_image_path();

// Standardize image paths
$logo_path = $imis_path . 'assets/img/cscro8logo.png';
$header_logo_path = $imis_path . 'assets/img/csclogo.png';
$fallback_logo_path = $imis_path . 'assets/img/logo.png';
$fallback_avatar_path = $imis_path . 'assets/img/default-avatar.png';
?>

<style>
    /* ============================================
       GLOBAL STYLES
       ============================================ */
    html, body {
        margin: 0;
        padding: 0;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ============================================
       HEADER & NAVIGATION
       ============================================ */
    .header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .logo img {
        height: 40px;
        width: auto;
    }

    .logo {
        text-decoration: none;
    }

    .nav-profile img {
        border: 2px solid #dee2e6;
        transition: border-color 0.3s ease;
    }

    .nav-profile:hover img {
        border-color: #0d6efd;
    }

    .profile-img {
        width: 36px;
        height: 36px;
        object-fit: cover;
    }

    /* ============================================
       ABOUT US BUTTON
       ============================================ */
    #aboutUsBtn {
        color: #012970;
        transition: all 0.3s ease;
        text-decoration: none;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
    }

    #aboutUsBtn:hover {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.1);
    }

    #aboutUsBtn i {
        transition: transform 0.3s ease;
    }

    #aboutUsBtn:hover i {
        transform: rotate(180deg);
    }

    /* ============================================
       ABOUT US MODAL (SweetAlert2)
       ============================================ */
    .swal2-popup {
        font-family: 'Poppins', sans-serif;
        font-size: 0.90rem !important;
    }

    .swal2-popup.about-us-modal {
        border-radius: 1rem;
        padding: 0;
        overflow: hidden;
    }

    .about-us-modal .swal2-icon {
        display: none !important;
    }

    .about-us-modal .swal2-header {
        background: linear-gradient(135deg, #0077b6 0%, #005577 100%) !important;
        padding: 10rem 1.5rem 1.5rem !important; /* Equal bottom padding */
        margin-top: 0 !important;
        border-bottom: none;
    }

    .about-us-modal hr {
        margin: -1rem 0 1rem 0; /* Centers the HR between header and content */
        border: none;
        border-top: 1px solid #dee2e6;
    }

    .about-us-modal .swal2-title {
        color: white !important;
        font-size: 1.60rem !important;
        font-weight: 600 !important;
        margin: 0 !important;
    }

    .about-us-modal .swal2-html-container {
        margin: 0;
        padding: 2rem 1.5rem;
        text-align: left;
    }

    .about-us-modal .swal2-footer {
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        padding: 1.25rem 1.5rem;
        text-align: left;
        margin-top: 0;
    }

    .about-us-modal .swal2-actions {
        margin-top: 0;
        padding: 1rem 1.5rem;
        border-top: 1px solid #dee2e6;
    }

    .about-us-modal .swal2-confirm {
        background: linear-gradient(135deg, #0077b6 0%, #005577 100%) !important;
        border: none !important;
        padding: 0.65rem 2rem !important;
        font-size: 0.95rem !important;
        font-weight: 500 !important;
        border-radius: 0.5rem !important;
        transition: all 0.3s ease !important;
    }

    .about-us-modal .swal2-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 119, 182, 0.4) !important;
    }

    /* ============================================
       ABOUT SECTION CONTENT
       ============================================ */
    .about-section {
        margin-bottom: 1.5rem;
    }

    .about-section h4 {
        color: #0077b6;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .about-section p {
        color: #495057;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 0.5rem;
    }

    /* Features Grid */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .feature-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        border-left: 3px solid #0077b6;
    }

    .feature-item i {
        color: #0077b6;
        font-size: 1.25rem;
        margin-right: 0.5rem;
    }

    .feature-item strong {
        color: #212529;
        font-size: 0.9rem;
    }

    /* Footer Content */
    .footer-content {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .footer-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #495057;
        font-size: 0.9rem;
    }

    .footer-item i {
        color: #0077b6;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .footer-item strong {
        color: #212529;
        min-width: 90px;
    }

    /* ============================================
       FORM ELEMENTS
       ============================================ */
    label,
    input[type="password"] {
        font-size: 14px;
    }

    /* ============================================
       UTILITY CLASSES
       ============================================ */
    .small-swal-modal .swal2-modal {
        max-width: 325px;
    }

    /* ============================================
       RESPONSIVE DESIGN
       ============================================ */
    @media (max-width: 768px) {
        .about-us-modal .swal2-html-container {
            padding: 1.5rem 1rem;
        }
        
        .features-grid {
            grid-template-columns: 1fr;
        }
        
        .about-us-modal .swal2-footer {
            padding: 1rem;
        }
    }
</style>

<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="<?php echo $imis_path; ?>" class="logo d-flex align-items-center">
            <img src="<?php echo $header_logo_path; ?>" alt="CSC Logo" onerror="this.src='<?php echo $fallback_logo_path; ?>'">
            <span class="d-none d-lg-block">&nbsp;CSC RO VIII</span>
        </a>
    </div>
<nav class="header-nav ms-auto">
    <ul class="d-flex align-items-center">
        <li class="nav-item me-3">
            <a class="nav-link d-flex align-items-center" href="#" id="aboutUsBtn" title="About IMIS">
                <i class="bi bi-info-circle fs-5"></i>
                <span class="d-none d-md-inline ms-1">About</span>
            </a>
        </li>
        
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
<script>
// About Us Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    const aboutUsBtn = document.getElementById('aboutUsBtn');
    if (aboutUsBtn) {
        aboutUsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAboutUsModal();
        });
    }
});

function showAboutUsModal() {
    Swal.fire({
        title: '<span style="color: #0077b6;">Welcome to Integrated Management Information System</span>',
        html: `
            <hr>
            <div class="about-section">
                <h4><i class="bi bi-info-circle-fill"></i> About IMIS</h4>
                <p style="text-align: justify;">
                    The <strong>Integrated Management Information System (IMIS)</strong> is a comprehensive digital platform 
                    designed to streamline and enhance the operational efficiency of the Civil Service Commission Regional Office VIII.
                </p>
                <p style="text-align: justify;">
                    IMIS serves as a centralized hub that integrates multiple divisional systems, enabling seamless access 
                    to critical services including human resource management, examination services, client assistance, 
                    financial operations, and technical support.
                </p>
            </div>

            <div class="about-section">
                <h4><i class="bi bi-bullseye"></i> Our Mission</h4>
                <p style="text-align: justify;">
                    To deliver innovative digital solutions that promote excellence in public service through efficient 
                    information management, streamlined workflows, and enhanced service delivery to all stakeholders.
                </p>
            </div>

            <div class="about-section">
                <h4><i class="bi bi-star-fill"></i> Key Features</h4>
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="bi bi-people-fill"></i>
                        <strong>Unified Access</strong>
                        <p class="mb-0 mt-1 small">Single sign-on to multiple divisional systems</p>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-shield-check"></i>
                        <strong>Secure Platform</strong>
                        <p class="mb-0 mt-1 small">Role-based access control and data protection</p>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-speedometer2"></i>
                        <strong>Real-time Updates</strong>
                        <p class="mb-0 mt-1 small">Instant access to system information and updates</p>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-graph-up"></i>
                        <strong>Enhanced Efficiency</strong>
                        <p class="mb-0 mt-1 small">Streamlined processes and automated workflows</p>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-phone"></i>
                        <strong>Mobile Responsive</strong>
                        <p class="mb-0 mt-1 small">Access systems anywhere, anytime on any device</p>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-clock-history"></i>
                        <strong>Session Management</strong>
                        <p class="mb-0 mt-1 small">Automatic session timeout and activity tracking</p>
                    </div>
                </div>
            </div>

            <div class="about-section mb-0">
                <h4><i class="bi bi-diagram-3-fill"></i> Integrated Systems</h4>
                <p style="text-align: justify;">
                    IMIS consolidates <strong>15+ specialized systems</strong> across 7 divisions including OTRS, ORS, ERIS, 
                    CDL, LCMMS, CTS, and more, providing a seamless user experience and comprehensive service coverage.
                </p>
            </div>
        `,
        icon: 'info',
        iconColor: 'white',
        confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Got It',
        customClass: {
            popup: 'about-us-modal',
            confirmButton: 'btn-about-confirm'
        },
        width: '850px',
        footer: `
            <div class="footer-content">
                <div class="footer-item">
                    <i class="bi bi-code-slash"></i>
                    <strong>Developed by:</strong>
                    <span>Information Technology Group (ITG)</span>
                </div>
                <div class="footer-item">
                    <i class="bi bi-building"></i>
                    <strong>Agency:</strong>
                    <span>Civil Service Commission - Regional Office VIII</span>
                </div>
                <div class="footer-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <strong>Location:</strong>
                    <span>Government Center, Palo, Leyte</span>
                </div>
                <div class="footer-item">
                    <i class="bi bi-calendar-check"></i>
                    <strong>Version:</strong>
                    <span>IMIS v2.0 - 2025</span>
                </div>
            </div>
        `,
        showCloseButton: true,
        focusConfirm: false,
        allowOutsideClick: true,
        allowEscapeKey: true,
    });
}

// Optional: Add keyboard shortcut (Ctrl+I or Cmd+I) to open About Us
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
        e.preventDefault();
        showAboutUsModal();
    }
});
</script>