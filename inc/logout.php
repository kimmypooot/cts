<?php
// inc/logout.php

// ── 1. Boot session ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/connect.php';   // also sets date_default_timezone_set
require_once __DIR__ . '/../includes/LoginLogger.php';

// ── 2. Helpers ────────────────────────────────────────────────────────────────
function is_subdomain(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return str_starts_with($host, 'imis.')
        || str_contains($host, 'imis.cscro8.com');
}

function get_login_redirect_path(): string
{
    if (is_subdomain()) {
        return '/login';
    }

    $currentDir = dirname($_SERVER['SCRIPT_NAME']);
    $parts      = explode('/', trim($currentDir, '/'));
    $imisIdx    = array_search('imis', $parts, true);

    if ($imisIdx !== false) {
        $depth = count($parts) - $imisIdx - 1;
        return str_repeat('../', $depth) . 'login';
    }

    return '../login';
}

// ── 3. Validate logout reason ─────────────────────────────────────────────────
$validReasons = ['manual', 'idle', 'timeout', 'forced'];
$logoutReason = $_GET['reason'] ?? 'manual';
if (!in_array($logoutReason, $validReasons, true)) {
    $logoutReason = 'manual';
}

// ── 4. Capture session data BEFORE wiping anything ───────────────────────────
$userId    = (int)    ($_SESSION['id']           ?? 0);
$username  = (string) ($_SESSION['username']      ?? '');
$logId     = (int)    ($_SESSION['login_log_id']  ?? 0);
$sessionId = session_id();

// ── 5. Write logout record ────────────────────────────────────────────────────
if ($userId && $username) {

    // PHP-generated timestamp — timezone governed by connect.php (Asia/Manila).
    // Using date() here instead of SQL NOW() ensures the stored time is always
    // UTC+8 regardless of the MySQL server's global timezone setting.
    $logoutTime = date('Y-m-d H:i:s');

    $dbStatus = in_array($logoutReason, ['idle', 'timeout'], true)
        ? 'timeout'
        : 'logged_out';

    // Primary path: LoginLogger uses $_SESSION['login_log_id'] for a fast
    // primary-key UPDATE.  Pass $logoutTime so it uses the same PHP timestamp.
    $loggerSucceeded = false;

    try {
        $logger          = new LoginLogger($conn);
        $loggerSucceeded = $logger->logLogout($userId, $username, $logoutReason);
        error_log("logout.php — LoginLogger::logLogout() result=" . ($loggerSucceeded ? 'true' : 'false')
            . " user=$username reason=$logoutReason log_id=$logId time=$logoutTime");
    } catch (Exception $e) {
        error_log('logout.php — LoginLogger::logLogout() threw: ' . $e->getMessage());
    }

    // Safety-net fallback: runs ONLY when LoginLogger reported failure (e.g.,
    // after a server restart that lost $_SESSION['login_log_id']).
    // Uses a PHP-generated :now parameter — NOT SQL NOW() — so the timestamp
    // is always in Asia/Manila regardless of MySQL's global timezone.
    if (!$loggerSucceeded) {
        try {
            $safeStmt = $conn->prepare(
                "UPDATE imis_history_login_logs
                    SET status      = :status,
                        logout_time = :now,
                        updated_at  = CURRENT_TIMESTAMP
                  WHERE user_id    = :uid
                    AND session_id = :sid
                    AND status     = 'active'"
            );
            $safeStmt->execute([
                ':status' => $dbStatus,
                ':now'    => $logoutTime,   // PHP timestamp, Asia/Manila
                ':uid'    => $userId,
                ':sid'    => $sessionId,
            ]);
            error_log("logout.php — Safety-net UPDATE rows={$safeStmt->rowCount()} time=$logoutTime");
        } catch (PDOException $e) {
            error_log('logout.php — Safety-net UPDATE failed: ' . $e->getMessage());
        }
    }
}

// ── 6. Destroy session completely ─────────────────────────────────────────────
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// ── 7. Redirect ───────────────────────────────────────────────────────────────
$redirectUrl  = get_login_redirect_path();
$redirectUrl .= ($logoutReason === 'idle') ? '?logout=idle' : '?logout=manual';

header('Location: ' . $redirectUrl);
exit();
