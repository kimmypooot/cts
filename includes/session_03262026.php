<?php
// includes/session.php

// ── Must be set BEFORE session_start() ──
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cache_limiter', 'nocache');

session_start();

// Include database connection
require_once __DIR__ . '/connect.php';

// Regenerate session ID every 10 minutes
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 600) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Get current page/script name
$current_page = basename($_SERVER['PHP_SELF']);
$request_uri = $_SERVER['REQUEST_URI'];

// Pages that don't require authentication
$public_pages = ['login', 'index'];

// Check if current page is public or if we're already on login page
$is_public_page = in_array($current_page, $public_pages) ||
    strpos($request_uri, '/login') !== false ||
    strpos($request_uri, '/index') !== false;

// Only check roles if not on a public page
if (!$is_public_page) {
    $valid_roles = ['user', 'admin', 'superadmin'];
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $valid_roles)) {
        // Determine the correct redirect URL based on subdomain usage
        $host = $_SERVER['HTTP_HOST'];
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

        // Check if we're using a subdomain (contains imis. at the beginning)
        if (strpos($host, 'imis.') === 0) {
            // Subdomain case: redirect to root/login
            $redirect_url = $protocol . '://' . $host . '/login';
        } else {
            // Main domain case: redirect to /imis/login
            $redirect_url = $protocol . '://' . $host . '/login';
        }

        header("Location: " . $redirect_url);
        exit();
    }
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * Load user system permissions from database
 */
function loadUserSystemPermissions($userId)
{
    global $conn; // Assuming PDO connection from connect.php

    try {
        $stmt = $conn->prepare("SELECT * FROM system_access WHERE user = :userId");
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $permissions = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permissions) {
            // Store permissions in session for quick access
            $systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement', 'leave_cscro8', 'pms'];
            foreach ($systems as $system) {
                if (isset($permissions[$system])) {
                    $_SESSION[$system] = $permissions[$system];
                }
            }
            return $permissions;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Database error in loadUserSystemPermissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced System Access Control with Database Integration
 */
function checkSystemAccess($system_name, $required_level = 'User')
{
    // Validate system name
    $valid_systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement', 'leave_cscro8', 'pms'];
    if (!in_array($system_name, $valid_systems)) {
        header('Location: /index_dashboard');
        exit();
    }

    // Check if user is logged in
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        header('Location: /login');
        exit();
    }

    // Load permissions from database if not already loaded
    if (!isset($_SESSION[$system_name])) {
        $userId = $_SESSION['id'] ?? null; // Assuming user ID is stored in session
        if ($userId) {
            loadUserSystemPermissions($userId);
        }
    }

    // Check system-specific permissions
    $user_permission = $_SESSION[$system_name] ?? 'None';

    // Handle different permission levels
    switch ($user_permission) {
        case 'None':
            // User has no access to this system
            header('Location: /index_dashboard');
            exit();
            break;

        case 'User':
            // User has basic access
            if ($required_level === 'Admin' || $required_level === 'SuperAdmin') {
                // Redirect to user dashboard for this system
                header("Location: /{$system_name}/index");
                exit();
            }
            break;

        case 'Admin':
            // Admin has access to admin and user areas
            if ($required_level === 'SuperAdmin') {
                // Redirect to admin dashboard
                header("Location: /{$system_name}/admin/index");
                exit();
            }
            break;

        case 'SuperAdmin':
            // SuperAdmin has access to everything
            break;

        default:
            // Invalid permission level
            header('Location: /index_dashboard');
            exit();
    }

    return true;
}

/**
 * Get user's permission level for a specific system
 */
function getUserSystemPermission($system_name)
{
    $valid_systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement', 'leave_cscro8', 'pms'];
    if (!in_array($system_name, $valid_systems)) {
        return 'None';
    }

    // Load permissions from database if not already loaded
    if (!isset($_SESSION[$system_name])) {
        $userId = $_SESSION['id'] ?? null;
        if ($userId) {
            loadUserSystemPermissions($userId);
        }
    }

    return $_SESSION[$system_name] ?? 'None';
}

/**
 * Check if user has minimum required permission for a system
 */
function hasSystemPermission($system_name, $required_level = 'User')
{
    $user_permission = getUserSystemPermission($system_name);

    $permission_hierarchy = [
        'None' => 0,
        'User' => 1,
        'Admin' => 2,
        'SuperAdmin' => 3
    ];

    $user_level = $permission_hierarchy[$user_permission] ?? 0;
    $required_level_num = $permission_hierarchy[$required_level] ?? 0;

    return $user_level >= $required_level_num;
}

/**
 * Get list of systems user has access to
 */
function getUserAccessibleSystems()
{
    $systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement', 'leave_cscro8', 'pms'];
    $accessible = [];

    foreach ($systems as $system) {
        $permission = getUserSystemPermission($system);
        if ($permission !== 'None') {
            $accessible[$system] = $permission;
        }
    }

    return $accessible;
}

/**
 * Handle login success notification (execute only once)
 */
function handleLoginSuccess()
{
    $loginSuccess = false;
    if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
        $loginSuccess = true;
        unset($_SESSION['login_success']); // Show only once
    }
    return $loginSuccess;
}

/**
 * Auto-detect current system based on URL path
 */
function getCurrentSystem()
{
    $path = $_SERVER['REQUEST_URI'];
    $systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement'];

    foreach ($systems as $system) {
        if (strpos($path, "/{$system}/") !== false) {
            return $system;
        }
    }

    return null;
}

/**
 * Auto-detect if current page requires admin access
 */
function isAdminPage()
{
    $path = $_SERVER['REQUEST_URI'];
    return strpos($path, '/admin/') !== false;
}

/**
 * Enhanced redirect function with database permission checking
 */
function redirectToSystemWithPermissionCheck($system, $userId)
{
    // Load user permissions
    $permissions = loadUserSystemPermissions($userId);

    if (!$permissions) {
        // No permissions found, redirect to dashboard
        header('Location: /index_dashboard');
        exit();
    }

    // Map system names to database columns
    $systemMap = [
        'ERIS' => 'eris',
        'LMS' => 'lms',
        'OTRS' => 'otrs',
        'ORS' => 'ors',
        'PSED' => 'psed',
        'CDL' => 'cdl',
        'ICTSRTS' => 'ictsrts',
        'ROOMS' => 'rooms',
        'PROCUREMENT' => 'procurement',
        'LEAVE_CSCRO8' => 'leave_cscro8',
        'PMS' => 'pms'
    ];

    $dbColumn = $systemMap[$system] ?? strtolower($system);
    $role = $permissions[$dbColumn] ?? 'None';

    // Check if user has access
    if ($role === 'None') {
        header('Location: /index_dashboard');
        exit();
    }

    // Redirect based on system and role
    switch ($system) {
        case 'ERIS':
            if ($role === 'Admin') {
                header('Location: /eris/esd/index');
            } else {
                header('Location: /eris/index');
            }
            break;
        case 'LEAVE_CSCRO8':
            if ($role === 'Admin') {
                header('Location: /leave/admin');
            } else {
                header('Location: /leave/index');
            }
            break;
        case 'LMS':
            if ($role === 'Admin') {
                header('Location: /lms/hrd/index');
            } else {
                header('Location: /lms/index');
            }
            break;

        case 'OTRS':
            if ($role === 'Admin') {
                header('Location: /otrs/hrd/index');
            } else {
                header('Location: /otrs/index');
            }
            break;

        case 'ORS':
            if ($role === 'Admin') {
                header('Location: /ors/admin/index');
            } else {
                header('Location: /ors/index');
            }
            break;

        case 'PMS':
            if ($role === 'Admin') {
                header('Location: /ipcrf/admin/index');
            } else {
                header('Location: /ipcrf/index');
            }
            break;

        case 'PSED':
            header('Location: /psed/index');
            break;

        case 'CDL':
            header('Location: /cdl/index_clients');
            break;

        case 'ICTSRTS':
            if ($role === 'Admin') {
                header('Location: /ict-srts/admin/index');
            } else {
                header('Location: /ict-srts/user/index');
            }
            break;

        case 'ROOMS':
            header('Location: /rooms/index');
            break;

        case 'PROCUREMENT':
            header('Location: /procurement/index');
            break;

        default:
            header('Location: /index_dashboard');
    }

    exit();
}

/**
 * Unified access control - call this at the beginning of any system page
 */
function enforceSystemAccess($system_name = null, $required_level = null)
{
    // Auto-detect system if not provided
    if ($system_name === null) {
        $system_name = getCurrentSystem();
    }

    // Auto-detect required level if not provided
    if ($required_level === null) {
        $required_level = isAdminPage() ? 'Admin' : 'User';
    }

    // If no system detected, allow access (for main dashboard, etc.)
    if ($system_name === null) {
        return true;
    }

    return checkSystemAccess($system_name, $required_level);
}

/**
 * Refresh user permissions from database
 */
function refreshUserPermissions($userId)
{
    // Clear existing permissions from session
    $systems = ['eris', 'lms', 'otrs', 'ors', 'psed', 'cdl', 'ictsrts', 'rooms', 'procurement', 'leave_cscro8', 'pms'];
    foreach ($systems as $system) {
        unset($_SESSION[$system]);
    }

    // Reload permissions
    return loadUserSystemPermissions($userId);
}

// Optional: Auto-enforce access control if IMIS_AUTO_ENFORCE is set
// if (defined('IMIS_AUTO_ENFORCE') && IMIS_AUTO_ENFORCE === true) {
//     enforceSystemAccess();
// }

// Load user permissions on session start if user is logged in
if (isset($_SESSION['id']) && !isset($_SESSION['permissions_loaded'])) {
    loadUserSystemPermissions($_SESSION['id']);
    $_SESSION['permissions_loaded'] = true;
}
