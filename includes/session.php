<?php
// imis/includes/session.php
//
// ── Session hardening ─────────────────────────────────────────────────────────
// These ini_set calls are safe to call even if a caller has already set them.
// They must be executed BEFORE session_start().
ini_set('session.cookie_httponly',  '1');
ini_set('session.use_strict_mode',  '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite',  'Strict');
ini_set('session.cache_limiter',    'nocache');
ini_set('session.gc_maxlifetime',   '3600'); // 60 min server-side TTL
ini_set('session.cookie_lifetime',  '0');    // Session cookie — expires on browser close

// Only transmit over HTTPS when available
$_is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
ini_set('session.cookie_secure', $_is_https ? '1' : '0');

// ── Single session_start() guard ──────────────────────────────────────────────
// Prevents the double-start warning that occurred when login.php called
// session_start() before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection (provides $conn)
require_once __DIR__ . '/connect.php';

// ── Session fingerprint — hijacking prevention ────────────────────────────────
// On first access after login, record a fingerprint of the user-agent.
// On every subsequent request, verify it has not changed.
// IP address is intentionally excluded — it can legitimately change (mobile/proxies).
function _session_make_fingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ua);
}

if (isset($_SESSION['username'])) {
    if (!isset($_SESSION['_fp'])) {
        // First request after login — record the fingerprint
        $_SESSION['_fp'] = _session_make_fingerprint();
    } elseif ($_SESSION['_fp'] !== _session_make_fingerprint()) {
        // Fingerprint mismatch — possible session hijack; destroy and redirect
        session_unset();
        session_destroy();
        $protocol = $_is_https ? 'https' : 'http';
        header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . '/login?reason=security');
        exit();
    }
}

// ── Session ID rotation every 15 minutes ──────────────────────────────────────
// Regenerating the ID periodically limits the window a stolen token is usable.
// The delete-old-session flag (true) removes the previous session file immediately.
if (!isset($_SESSION['_rotated_at'])) {
    $_SESSION['_rotated_at'] = time();
} elseif (time() - $_SESSION['_rotated_at'] > 900) {
    session_regenerate_id(true);
    $_SESSION['_rotated_at'] = time();
}

// ── Absolute idle timeout ─────────────────────────────────────────────────────
// Belt-and-suspenders server-side check that mirrors the JS idle manager.
// Enforced only for authenticated users; 60-minute threshold.
const SESSION_IDLE_SECONDS = 3600;

if (isset($_SESSION['username'])) {
    $now           = time();
    $last_activity = $_SESSION['_last_activity'] ?? $now;

    if ($now - $last_activity > SESSION_IDLE_SECONDS) {
        // Mark the login log row as timed-out before destroying
        _session_mark_timeout();
        session_unset();
        session_destroy();
        $protocol = $_is_https ? 'https' : 'http';
        header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . '/login?reason=idle');
        exit();
    }

    // Refresh the activity timestamp on every request
    $_SESSION['_last_activity'] = $now;
}

// ── Public-page detection ─────────────────────────────────────────────────────
$_current_page = basename($_SERVER['PHP_SELF']);
$_request_uri  = $_SERVER['REQUEST_URI'];

$_public_pages = ['login', 'index'];

$_is_public_page =
    in_array($_current_page, $_public_pages, true) ||
    str_contains($_request_uri, '/login')           ||
    str_contains($_request_uri, '/index');

// ── Authentication gate ───────────────────────────────────────────────────────
if (!$_is_public_page) {
    $valid_roles = ['user', 'admin', 'superadmin'];

    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $valid_roles, true)) {
        $protocol = $_is_https ? 'https' : 'http';
        header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . '/login');
        exit();
    }
}

// ── Cache prevention headers ──────────────────────────────────────────────────
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// ── Auto-load permissions on first access ─────────────────────────────────────
if (isset($_SESSION['id']) && empty($_SESSION['_perms_loaded'])) {
    loadUserSystemPermissions((int) $_SESSION['id']);
    $_SESSION['_perms_loaded'] = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// PERMISSION FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Load user system permissions from the normalized tables into $_SESSION.
 *
 * Reads imis_system_access → imis_access_roles → imis_projects and writes:
 *   $_SESSION[strtolower(code_name)] = role_name
 *
 * e.g. $_SESSION['otrs'] = 'Admin', $_SESSION['eris'] = 'User'
 *
 * @param  int         $userId
 * @return array|false  Map of [ code_name => role_name ], or false on failure.
 */
function loadUserSystemPermissions(int $userId): array|false
{
    global $conn;

    try {
        $sql = '
            SELECT  p.code_name,
                    ar.name AS role_name
            FROM    imis_system_access  sa
            JOIN    imis_projects       p  ON p.id  = sa.project_id
            JOIN    imis_access_roles   ar ON ar.id = sa.role_id
            WHERE   sa.user_id = :userId
              AND   p.is_active = 1
        ';

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return false;
        }

        $permissions = [];
        foreach ($rows as $row) {
            $key                = strtolower($row['code_name']);
            $permissions[$key]  = $row['role_name'];
            $_SESSION[$key]     = $row['role_name'];
        }

        return $permissions;

    } catch (PDOException $e) {
        error_log('[session.php] loadUserSystemPermissions error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Force a fresh permission load from the database, clearing any cached values.
 *
 * @param  int         $userId
 * @return array|false
 */
function refreshUserPermissions(int $userId): array|false
{
    // Clear every known session permission key so stale values cannot linger
    try {
        global $conn;
        $stmt = $conn->prepare('SELECT LOWER(code_name) AS k FROM imis_projects WHERE is_active = 1');
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            unset($_SESSION[$key]);
        }
    } catch (PDOException $e) {
        error_log('[session.php] refreshUserPermissions cleanup error: ' . $e->getMessage());
    }

    unset($_SESSION['_perms_loaded']);
    return loadUserSystemPermissions($userId);
}

/**
 * Return the permission level string for a system for the current user.
 *
 * @param  string  $system_name  Lowercase code_name (e.g. 'eris', 'otrs')
 * @return string  'None' | 'User' | 'Admin' | 'SuperAdmin'
 */
function getUserSystemPermission(string $system_name): string
{
    $key = strtolower($system_name);

    if (!isset($_SESSION[$key])) {
        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId > 0) {
            loadUserSystemPermissions($userId);
        }
    }

    $value = $_SESSION[$key] ?? 'None';

    // Normalise legacy flat-column values ('Admin'/'User' are already correct;
    // catch 'admin', 'user', 'superadmin' from old data)
    $normalised = [
        'superadmin' => 'SuperAdmin',
        'admin'      => 'Admin',
        'user'       => 'User',
        'none'       => 'None',
        'SuperAdmin' => 'SuperAdmin',
        'Admin'      => 'Admin',
        'User'       => 'User',
        'None'       => 'None',
    ];

    return $normalised[$value] ?? 'None';
}

/**
 * Returns true if the current user meets the minimum permission level.
 *
 * @param  string  $system_name
 * @param  string  $required_level  'User' | 'Admin' | 'SuperAdmin'
 */
function hasSystemPermission(string $system_name, string $required_level = 'User'): bool
{
    $hierarchy = ['None' => 0, 'User' => 1, 'Admin' => 2, 'SuperAdmin' => 3];

    $userLevel     = $hierarchy[getUserSystemPermission($system_name)] ?? 0;
    $requiredLevel = $hierarchy[$required_level] ?? 0;

    return $userLevel >= $requiredLevel;
}

/**
 * Enforce access control for a system page.
 * Redirects to /dashboard or the system index if the user lacks permission.
 *
 * @param  string  $system_name
 * @param  string  $required_level  'User' | 'Admin' | 'SuperAdmin'
 * @return true    (only returns if access is granted)
 */
function checkSystemAccess(string $system_name, string $required_level = 'User'): true
{
    if (empty($_SESSION['username'])) {
        header('Location: /login');
        exit();
    }

    $permission  = getUserSystemPermission($system_name);
    $hierarchy   = ['None' => 0, 'User' => 1, 'Admin' => 2, 'SuperAdmin' => 3];
    $userLevel   = $hierarchy[$permission]      ?? 0;
    $reqLevel    = $hierarchy[$required_level]  ?? 0;

    if ($userLevel === 0) {
        header('Location: /dashboard');
        exit();
    }

    if ($userLevel < $reqLevel) {
        $base = strtolower($system_name);
        header("Location: /{$base}/index");
        exit();
    }

    return true;
}

/**
 * Returns an associative array of [ system_key => role_name ] for all systems
 * where the current user has at least 'User' level access.
 *
 * @return array<string, string>
 */
function getUserAccessibleSystems(): array
{
    $userId = (int) ($_SESSION['id'] ?? 0);
    if ($userId === 0) {
        return [];
    }

    // Ensure permissions are loaded
    if (empty($_SESSION['_perms_loaded'])) {
        loadUserSystemPermissions($userId);
        $_SESSION['_perms_loaded'] = true;
    }

    global $conn;
    $accessible = [];

    try {
        $stmt = $conn->prepare(
            'SELECT LOWER(p.code_name) AS k
               FROM imis_projects p
              WHERE p.is_active = 1'
        );
        $stmt->execute();
        $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($keys as $key) {
            $perm = $_SESSION[$key] ?? 'None';
            if ($perm !== 'None') {
                $accessible[$key] = $perm;
            }
        }
    } catch (PDOException $e) {
        error_log('[session.php] getUserAccessibleSystems error: ' . $e->getMessage());
    }

    return $accessible;
}

/**
 * Unified access control — call at the top of any system page.
 * Auto-detects the system and required level from the URL if not provided.
 */
function enforceSystemAccess(?string $system_name = null, ?string $required_level = null): true
{
    $system_name    ??= getCurrentSystem();
    $required_level ??= isAdminPage() ? 'Admin' : 'User';

    if ($system_name === null) {
        return true; // Main dashboard — no system restriction
    }

    return checkSystemAccess($system_name, $required_level);
}

/**
 * Detect the current system from the URL path.
 *
 * Tries all active project code_names so new systems are picked up automatically
 * without code changes.
 */
function getCurrentSystem(): ?string
{
    $path = strtolower($_SERVER['REQUEST_URI']);

    // Try DB-driven detection first
    try {
        global $conn;
        $stmt = $conn->query('SELECT LOWER(code_name) AS k FROM imis_projects WHERE is_active = 1');
        $systems = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Fallback to hardcoded list if DB is unavailable
        $systems = ['eris','lms','otrs','ors','psed','cdl','ictsrts','rooms',
                    'procurement','pms','rfcs','dvs','cts','fts','iis',
                    'comexams','jportal','msdeserve','gad-corner'];
    }

    foreach ($systems as $system) {
        if (str_contains($path, "/{$system}/")) {
            return $system;
        }
    }

    return null;
}

/**
 * Returns true if the current URL path contains /admin/.
 */
function isAdminPage(): bool
{
    return str_contains($_SERVER['REQUEST_URI'], '/admin/');
}

/**
 * Returns true (and clears the flag) if a login-success message should be shown.
 */
function handleLoginSuccess(): bool
{
    if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
        unset($_SESSION['login_success']);
        return true;
    }

    return false;
}

/**
 * Redirect a user to the correct system entry point based on their DB role.
 * Reads project_url directly from imis_access_roles, so redirect paths stay
 * in the database rather than hardcoded here.
 *
 * @param  string  $system   Matches imis_projects.code_name (case-insensitive)
 * @param  int     $userId
 */
function redirectToSystemWithPermissionCheck(string $system, int $userId): never
{
    global $conn;

    try {
        $sql = '
            SELECT  ar.name       AS role_name,
                    ar.project_url,
                    ar.is_external,
                    p.guest_url,
                    p.requires_setup
            FROM    imis_system_access  sa
            JOIN    imis_projects       p  ON p.id  = sa.project_id
            JOIN    imis_access_roles   ar ON ar.id = sa.role_id
            WHERE   sa.user_id = :userId
              AND   UPPER(p.code_name) = :code
              AND   p.is_active = 1
            LIMIT 1
        ';

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':code', strtoupper($system));
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('[session.php] redirectToSystemWithPermissionCheck error: ' . $e->getMessage());
        $row = null;
    }

    if (empty($row)) {
        header('Location: /dashboard');
        exit();
    }

    // Store the role in session so the target page can use it immediately
    $key = strtolower($system);
    $_SESSION[$key] = $row['role_name'];

    $dest = $row['project_url'] ?: '/dashboard';

    header('Location: ' . $dest);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// INTERNAL HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mark the current session's login log entry as timed-out.
 * Called internally before an idle-timeout session destruction.
 */
function _session_mark_timeout(): void
{
    global $conn;

    $sessionId = session_id();
    if (empty($sessionId)) {
        return;
    }

    try {
        $stmt = $conn->prepare(
            'UPDATE imis_history_login_logs
                SET status       = :status,
                    logout_time  = NOW()
              WHERE session_id   = :sid
                AND status       = :active'
        );
        $stmt->execute([
            ':status' => 'timeout',
            ':sid'    => $sessionId,
            ':active' => 'active',
        ]);
    } catch (PDOException $e) {
        error_log('[session.php] _session_mark_timeout error: ' . $e->getMessage());
    }
}