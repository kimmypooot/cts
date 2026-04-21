<?php

/**
 * imis/includes/security.php
 *
 * IMIS Centralized Security Module
 * ──────────────────────────────────
 * Features
 * ─────────
 *  • CSRF token generation, rotation, and validation
 *  • Rate limiting and brute-force protection (DB-backed, window-based)
 *  • Account lockout with configurable thresholds
 *  • Input/output sanitization helpers (XSS prevention)
 *  • Session fingerprinting (session-hijacking prevention)
 *  • Timing-attack-safe comparisons and forced delays
 *  • Centralized security audit logging
 *  • IP-based threat detection helpers
 *
 * Usage (after bootstrap):
 *   $sec = getSecurity();
 *
 *   // Protect a login form
 *   if (!$sec->checkRateLimit($_POST['username'], 'login')) {
 *       die('Too many attempts. Try again later.');
 *   }
 *
 *   // Validate CSRF on any mutating request
 *   $sec->validateCsrfOrAbort();
 *
 *   // Sanitize user input
 *   $name = ImisSecurityManager::sanitizeString($_POST['name'], 128);
 */

declare(strict_types=1);

if (defined('IMIS_SECURITY_LOADED')) {
    return;
}
define('IMIS_SECURITY_LOADED', true);

// ═════════════════════════════════════════════════════════════════════════════
// ImisSecurityManager
// ═════════════════════════════════════════════════════════════════════════════

final class ImisSecurityManager
{
    private static ?self $instance = null;
    private PDO $db;

    // Configurable limits (loaded from env in constructor)
    private int $rateLimitMaxAttempts;
    private int $rateLimitWindow;
    private int $rateLimitLockout;
    private int $csrfTokenLifetime;

    // ── Constructor (private — use getInstance()) ─────────────────────────────

    private function __construct(PDO $db)
    {
        $this->db = $db;

        $this->rateLimitMaxAttempts = (int) env('RATE_LIMIT_MAX_ATTEMPTS',    5);
        $this->rateLimitWindow      = (int) env('RATE_LIMIT_WINDOW_SECONDS',  900);
        $this->rateLimitLockout     = (int) env('RATE_LIMIT_LOCKOUT_SECONDS', 1800);
        $this->csrfTokenLifetime    = (int) env('CSRF_TOKEN_LIFETIME',        3600);

        $this->initSecurityTables();
    }

    // ── Singleton ─────────────────────────────────────────────────────────────

    public static function getInstance(PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE INITIALIZATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ensure the two security tables exist; idempotent (IF NOT EXISTS).
     * @internal
     */
    private function initSecurityTables(): void
    {
        try {
            // Rate limiting / lockout
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS imis_security_rate_limits (
                    id            INT           NOT NULL AUTO_INCREMENT,
                    identifier    VARCHAR(128)  NOT NULL
                        COMMENT "IP address or username being rate-limited",
                    action        VARCHAR(64)   NOT NULL
                        COMMENT "Action context: login, password_reset, etc.",
                    attempt_count INT           NOT NULL DEFAULT 1,
                    window_start  DATETIME      NOT NULL,
                    locked_until  DATETIME      DEFAULT NULL,
                    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_rl_identifier_action (identifier, action),
                    INDEX idx_rl_locked (locked_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            // Security audit log
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS imis_security_audit_logs (
                    id         INT          NOT NULL AUTO_INCREMENT,
                    event_type VARCHAR(64)  NOT NULL,
                    user_id    INT          DEFAULT NULL,
                    username   VARCHAR(64)  DEFAULT NULL,
                    ip_address VARCHAR(45)  NOT NULL,
                    user_agent VARCHAR(500) DEFAULT NULL,
                    details    JSON         DEFAULT NULL,
                    severity   ENUM("info","warning","critical")
                               NOT NULL DEFAULT "info",
                    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_audit_event   (event_type),
                    INDEX idx_audit_ip      (ip_address),
                    INDEX idx_audit_user    (user_id),
                    INDEX idx_audit_created (created_at),
                    INDEX idx_audit_sev     (severity)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $e) {
            // Table creation failure must not crash the application
            error_log('[security.php] initSecurityTables: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSRF
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the current CSRF token, generating or rotating as needed.
     * Always call this from an authenticated page to get the token for forms.
     */
    public function getCsrfToken(): string
    {
        // Generate on first call
        if (empty($_SESSION['_csrf_token'])) {
            $this->rotateCsrfToken();
            return (string) $_SESSION['_csrf_token'];
        }

        // Rotate if the token has exceeded its lifetime
        $created = (int) ($_SESSION['_csrf_created'] ?? 0);
        if ((time() - $created) >= $this->csrfTokenLifetime) {
            $this->rotateCsrfToken();
        }

        return (string) $_SESSION['_csrf_token'];
    }

    /**
     * Generate a fresh CSRF token and persist it in the session.
     */
    public function rotateCsrfToken(): void
    {
        $_SESSION['_csrf_token']   = bin2hex(random_bytes(32));
        $_SESSION['_csrf_created'] = time();
    }

    /**
     * Timing-safe CSRF token validation.
     *
     * @param string $token  Token submitted with the request
     */
    public function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['_csrf_token'])) {
            return false;
        }
        // hash_equals prevents timing attacks
        return hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    /**
     * Validate CSRF and immediately abort with HTTP 403 on failure.
     * Use at the top of every POST/PUT/DELETE endpoint.
     *
     * @param string|null $token  Defaults to $_POST['_csrf_token']
     */
    public function validateCsrfOrAbort(?string $token = null): void
    {
        $token ??= (string) ($_POST['_csrf_token'] ?? '');

        if (!$this->validateCsrfToken($token)) {
            $this->auditLog('csrf_validation_failure', [
                'uri'    => $_SERVER['REQUEST_URI']     ?? '',
                'method' => $_SERVER['REQUEST_METHOD']  ?? '',
                'referer' => $_SERVER['HTTP_REFERER']    ?? '',
            ], 'warning');

            if (!headers_sent()) {
                http_response_code(403);
            }

            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Security token expired or invalid. Please refresh the page.',
                ]);
            } else {
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden</title></head>'
                    . '<body><h1>403 — Forbidden</h1>'
                    . '<p>Your security token has expired or is invalid. '
                    . '<a href="javascript:history.back()">Go back</a> and try again.</p>'
                    . '</body></html>';
            }
            exit();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RATE LIMITING & BRUTE-FORCE PROTECTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether an identifier is allowed to perform an action.
     * Returns true = allowed / false = blocked (rate-limited or locked out).
     *
     * @param string $identifier  IP address or username
     * @param string $action      'login', 'password_reset', 'api', etc.
     */
    public function checkRateLimit(string $identifier, string $action): bool
    {
        if ($this->isLockedOut($identifier, $action)) {
            return false;
        }
        return $this->getAttemptCount($identifier, $action) < $this->rateLimitMaxAttempts;
    }

    /**
     * Returns true if the identifier is currently in an active lockout period.
     */
    public function isLockedOut(string $identifier, string $action): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM imis_security_rate_limits
                 WHERE identifier   = :id
                   AND action       = :action
                   AND locked_until > NOW()
                 LIMIT 1'
            );
            $stmt->execute([':id' => $identifier, ':action' => $action]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[security] isLockedOut: ' . $e->getMessage());
            return false; // Fail-open to prevent legitimate users from being blocked by a DB error
        }
    }

    /**
     * Seconds remaining in the current lockout period (0 if not locked).
     */
    public function getLockoutSecondsRemaining(string $identifier, string $action): int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until))
                 FROM imis_security_rate_limits
                 WHERE identifier = :id AND action = :action
                 LIMIT 1'
            );
            $stmt->execute([':id' => $identifier, ':action' => $action]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (PDOException $e) {
            error_log('[security] getLockoutSecondsRemaining: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record a failed attempt for the identifier+action pair.
     * Automatically applies a lockout when the threshold is reached.
     *
     * @param string $identifier  IP or username
     * @param string $action      Context label
     */
    public function recordFailedAttempt(string $identifier, string $action): void
    {
        // The boundary of the current counting window
        $windowCutoff = date('Y-m-d H:i:s', time() - $this->rateLimitWindow);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO imis_security_rate_limits
                     (identifier, action, attempt_count, window_start)
                 VALUES (:id, :action, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     -- Reset window & counter when outside current window; increment otherwise
                     attempt_count = IF(window_start < :wc, 1, attempt_count + 1),
                     window_start  = IF(window_start < :wc, NOW(), window_start)'
            );
            $stmt->execute([
                ':id'     => $identifier,
                ':action' => $action,
                ':wc'     => $windowCutoff,
            ]);

            // Apply lockout if threshold is now reached
            $count = $this->getAttemptCount($identifier, $action);
            if ($count >= $this->rateLimitMaxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $this->rateLimitLockout);

                $lock = $this->db->prepare(
                    'UPDATE imis_security_rate_limits
                     SET locked_until = :lu
                     WHERE identifier = :id AND action = :action'
                );
                $lock->execute([
                    ':lu'     => $lockedUntil,
                    ':id'     => $identifier,
                    ':action' => $action,
                ]);

                $this->auditLog('lockout_applied', [
                    'identifier'   => $identifier,
                    'action'       => $action,
                    'attempt_count' => $count,
                    'locked_until' => $lockedUntil,
                ], 'warning');
            }
        } catch (PDOException $e) {
            error_log('[security] recordFailedAttempt: ' . $e->getMessage());
        }
    }

    /**
     * Clear rate-limit records for an identifier (e.g., after successful login).
     */
    public function clearAttempts(string $identifier, string $action): void
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM imis_security_rate_limits
                 WHERE identifier = :id AND action = :action'
            );
            $stmt->execute([':id' => $identifier, ':action' => $action]);
        } catch (PDOException $e) {
            error_log('[security] clearAttempts: ' . $e->getMessage());
        }
    }

    /**
     * Current attempt count within the active window for identifier+action.
     * @internal
     */
    private function getAttemptCount(string $identifier, string $action): int
    {
        $windowCutoff = date('Y-m-d H:i:s', time() - $this->rateLimitWindow);

        try {
            $stmt = $this->db->prepare(
                'SELECT attempt_count
                 FROM imis_security_rate_limits
                 WHERE identifier   = :id
                   AND action       = :action
                   AND window_start >= :wc
                 LIMIT 1'
            );
            $stmt->execute([
                ':id'     => $identifier,
                ':action' => $action,
                ':wc'     => $windowCutoff,
            ]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (PDOException $e) {
            error_log('[security] getAttemptCount: ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SESSION FINGERPRINTING (hijack prevention)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create and store a session fingerprint.
     * Must be called immediately after a successful login before any redirect.
     */
    public function createSessionFingerprint(): void
    {
        // Generate a per-session HMAC salt so the fingerprint can't be
        // pre-computed by an attacker who knows the algorithm.
        if (empty($_SESSION['_fp_salt'])) {
            $_SESSION['_fp_salt'] = bin2hex(random_bytes(16));
        }
        $_SESSION['_fp'] = $this->buildFingerprint();
    }

    /**
     * Validate the stored fingerprint against the current request.
     * Destroys the session and returns false on mismatch.
     *
     * @return bool  false = fingerprint invalid (session was terminated)
     */
    public function validateSessionFingerprint(): bool
    {
        if (empty($_SESSION['_fp'])) {
            return true; // No fingerprint set (pre-login page) — nothing to validate
        }

        $current = $this->buildFingerprint();
        if (!hash_equals((string) $_SESSION['_fp'], $current)) {
            $this->auditLog('session_hijack_detected', [
                'stored_fp'  => $_SESSION['_fp'],
                'current_fp' => $current,
                'user_id'    => $_SESSION['id'] ?? null,
            ], 'critical');

            // Terminate the compromised session
            if (function_exists('imis_destroy_session')) {
                imis_destroy_session();
            } else {
                session_destroy();
            }
            return false;
        }
        return true;
    }

    /**
     * Compute the HMAC fingerprint for the current request.
     * @internal
     */
    private function buildFingerprint(): string
    {
        $ua   = $_SERVER['HTTP_USER_AGENT']      ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $salt = (string) ($_SESSION['_fp_salt']  ?? '');
        // Intentionally excludes IP — mobile users move between towers
        return hash_hmac('sha256', $ua . '|' . $lang, $salt);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INPUT/OUTPUT SANITIZATION (XSS prevention)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Escape a value for safe HTML output.
     * Use this on every piece of user-supplied data echoed into HTML.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Strip HTML tags and whitespace; optionally enforce a maximum length.
     *
     * @param string $input
     * @param int    $maxLength  0 = no limit
     */
    public static function sanitizeString(string $input, int $maxLength = 0): string
    {
        $input = trim(strip_tags($input));
        if ($maxLength > 0) {
            $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        }
        return $input;
    }

    /**
     * Validate and cast an integer input.
     *
     * @return int|null  null when the value is not a valid integer
     */
    public static function sanitizeInt(mixed $input): ?int
    {
        $result = filter_var($input, FILTER_VALIDATE_INT);
        return $result !== false ? (int) $result : null;
    }

    /**
     * Validate and normalize an email address.
     *
     * @return string|false  false if the address is syntactically invalid
     */
    public static function sanitizeEmail(string $input): string|false
    {
        return filter_var(trim($input), FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate a URL.
     *
     * @return string|false  false if invalid
     */
    public static function sanitizeUrl(string $input): string|false
    {
        $url = filter_var(trim($input), FILTER_VALIDATE_URL);
        if ($url === false) {
            return false;
        }
        // Block javascript: and data: URIs
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        return $url;
    }

    /**
     * JSON-encode data for safe embedding inside HTML attributes or script blocks.
     * Uses JSON_HEX_* flags to escape angle brackets, quotes, and ampersands.
     *
     * @throws JsonException on encoding error
     */
    public static function jsonEncode(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TIMING ATTACK MITIGATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enforce a minimum constant response time to prevent timing side-channels.
     * Call after a failed authentication check.
     *
     * @param int $minMicros  Minimum microseconds to wait (default 250 ms)
     * @param int $jitter     Random jitter upper bound (default ±50 ms)
     */
    public static function addTimingDelay(int $minMicros = 250_000, int $jitter = 50_000): void
    {
        usleep($minMicros + random_int(0, $jitter));
    }

    /**
     * Timing-safe string comparison (alias for hash_equals, exported for convenience).
     */
    public static function safeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECURITY AUDIT LOGGING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a security event to imis_security_audit_logs.
     * Failures here are logged to the PHP error log and silently swallowed
     * to ensure audit-log errors never crash the application.
     *
     * @param string               $eventType  e.g. 'login_failed', 'csrf_failure'
     * @param array<string, mixed> $details    Arbitrary structured context
     * @param string               $severity   'info' | 'warning' | 'critical'
     */
    public function auditLog(
        string $eventType,
        array  $details  = [],
        string $severity = 'info'
    ): void {
        $allowedSeverities = ['info', 'warning', 'critical'];
        if (!in_array($severity, $allowedSeverities, true)) {
            $severity = 'info';
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO imis_security_audit_logs
                     (event_type, user_id, username, ip_address, user_agent, details, severity)
                 VALUES
                     (:event, :uid, :uname, :ip, :ua, :details, :severity)'
            );
            $stmt->execute([
                ':event'    => substr($eventType, 0, 64),
                ':uid'      => isset($_SESSION['id']) ? (int) $_SESSION['id'] : null,
                ':uname'    => isset($_SESSION['username']) ? substr((string) $_SESSION['username'], 0, 64) : null,
                ':ip'       => self::getClientIp(),
                ':ua'       => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                ':details'  => !empty($details)
                    ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                ':severity' => $severity,
            ]);
        } catch (PDOException $e) {
            error_log('[security] auditLog failed for event "' . $eventType . '": ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IP HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the client's real IP (Hostinger proxy-aware, prefers X-Forwarded-For).
     */
    public static function getClientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            // XFF chain: leftmost = client, rightmost = last known proxy
            $candidates = array_map('trim', explode(',', $xff));
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }

    /**
     * Returns true when the IP is in a private or reserved range.
     * Useful for skipping rate-limiting on internal monitoring tools.
     */
    public static function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MISC HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function isAjaxRequest(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// MODULE INITIALIZATION
// ═════════════════════════════════════════════════════════════════════════════

// Boot the singleton immediately so getSecurity() always returns a valid instance
(function (): void {
    global $conn;
    if (!isset($conn) || !($conn instanceof PDO)) {
        error_log('[security.php] $conn not available — ImisSecurityManager not initialized.');
        return;
    }
    ImisSecurityManager::getInstance($conn);
})();

// ═════════════════════════════════════════════════════════════════════════════
// GLOBAL HELPERS
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Return the ImisSecurityManager singleton.
 * Always use this; never call the constructor directly.
 */
function getSecurity(): ImisSecurityManager
{
    global $conn;
    return ImisSecurityManager::getInstance($conn);
}

/**
 * Return the active PDO database connection.
 *
 * @throws \RuntimeException when the connection is not initialized
 */
function getDatabase(): PDO
{
    global $conn;
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new \RuntimeException('[IMIS] Database connection not initialized. Ensure bootstrap.php is included first.');
    }
    return $conn;
}

/**
 * Redirect to a URL and store a one-shot flash message in the session.
 *
 * @param string $url      Destination (relative or absolute)
 * @param string $message  User-visible message
 * @param string $type     Alert variant: 'success' | 'error' | 'warning' | 'info'
 * @throws never           (always exits)
 */
function redirectWithMessage(string $url, string $message, string $type = 'info'): never
{
    $allowed = ['success', 'error', 'warning', 'info'];
    $_SESSION['_flash'] = [
        'text' => $message,
        'type' => in_array($type, $allowed, true) ? $type : 'info',
    ];
    header('Location: ' . $url);
    exit();
}

/**
 * Consume and return the pending flash message from the session (one-shot).
 *
 * @return array{text: string, type: string}|null
 */
function consumeFlashMessage(): ?array
{
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

/**
 * Escape a string for safe HTML output. Shortcut for ImisSecurityManager::e().
 *
 * @example  echo e($user_supplied_name);
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Output a hidden CSRF input field — paste inside any HTML <form>.
 *
 * @example  <?= csrfField() ?>
 */
function csrfField(): string
{
    $token = getSecurity()->getCsrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Output a <meta> tag carrying the CSRF token for AJAX requests.
 * Place in <head>; read in JS as: document.querySelector('meta[name="csrf-token"]').content
 *
 * @example  <?= csrfMeta() ?>
 */
function csrfMeta(): string
{
    $token = getSecurity()->getCsrfToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF and abort on failure. Thin wrapper for use outside class context.
 *
 * @param string|null $token  Defaults to $_POST['_csrf_token']
 */
function validateCsrfOrAbort(?string $token = null): void
{
    getSecurity()->validateCsrfOrAbort($token);
}
