<?php
// includes/LoginLogger.php

/**
 * LoginLogger — login/logout event recorder for imis_history_login_logs.
 *
 * Timezone fixes applied
 * ──────────────────────
 * [TZ-1] date_default_timezone_set() moved into the constructor so it fires
 *        at instantiation time, not at file-include time (more reliable in
 *        frameworks that may include this file early).
 *
 * [TZ-2] Replaced every SQL NOW() call with a PHP-generated datetime string
 *        bound as a :now parameter.  This means the timestamp is always
 *        controlled by PHP's explicitly-set Asia/Manila timezone rather than
 *        depending on MySQL's session timezone being correct.  MySQL session
 *        timezone (SET time_zone) is kept as a defensive net, but correctness
 *        no longer relies on it.
 *
 * [TZ-3] SET time_zone = '+08:00' is kept in the constructor as a safety net
 *        for any raw NOW() / CURRENT_TIMESTAMP that may exist elsewhere in the
 *        application on the same connection.
 *
 * [IP-1] Removed FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE from
 *        getClientIP().  Those flags silently discarded 192.168.x.x / 10.x.x.x
 *        addresses, so every intranet machine logged as 127.0.0.1.
 *        Private IPs are perfectly valid to log for an internal government
 *        system like CSC RO VIII IMIS.
 */

class LoginLogger
{
    private PDO $conn;

    public function __construct(PDO $dbConnection)
    {
        // [TZ-1] Set PHP timezone at construction time.
        date_default_timezone_set('Asia/Manila');

        $this->conn = $dbConnection;

        // Test the connection first.
        try {
            $this->conn->query('SELECT 1');
        } catch (PDOException $e) {
            throw new RuntimeException(
                'LoginLogger: Database connection test failed — ' . $e->getMessage()
            );
        }

        // [TZ-3] Align MySQL session timezone with PHP as a safety net.
        // Even if a query slips through using NOW() it will still land in +08:00.
        $this->conn->exec("SET time_zone = '+08:00'");

        // Ensure PDO throws on errors so nothing fails silently.
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * [IP-1] Resolve the real client IP, walking proxy headers in priority order.
     * Private/reserved ranges are intentionally accepted so intranet machines
     * are logged with their actual IP instead of 127.0.0.1.
     */
    private function getClientIP(): string
    {
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            $value = $_SERVER[$key] ?? '';
            if ($value === '') {
                continue;
            }

            // X-Forwarded-For can be "client, proxy1, proxy2" — take the first.
            $ip = trim(explode(',', $value)[0]);

            // Validate as any IP (including private/reserved for intranet use).
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }

    /**
     * [TZ-2] Return the current Asia/Manila datetime string.
     * Using PHP date() guarantees the timezone regardless of MySQL configuration.
     */
    private function now(): string
    {
        return date('Y-m-d H:i:s');   // Asia/Manila, set in constructor
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Log a successful login event.
     */
    public function logLogin(int|string $userId, string $username): bool
    {
        try {
            if (empty($userId) || trim($username) === '') {
                error_log("LoginLogger::logLogin — Invalid input userId='{$userId}' username='{$username}'");
                return false;
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Close any other active sessions for this user before inserting.
            $this->markPreviousSessionsAsLoggedOut((int) $userId);

            // [TZ-2] Timestamp generated in PHP, not MySQL.
            $now = $this->now();

            $sql = "INSERT INTO imis_history_login_logs
                        (user_id, username, ip_address, login_time, user_agent, status, session_id)
                    VALUES
                        (:user_id, :username, :ip_address, :now, :user_agent, 'active', :session_id)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_id'    => (int) $userId,
                ':username'   => trim($username),
                ':ip_address' => $this->getClientIP(),
                ':now'        => $now,                    // [TZ-2] PHP-controlled
                ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
                ':session_id' => session_id(),
            ]);

            $logId = (int) $this->conn->lastInsertId();

            if ($logId === 0) {
                error_log('LoginLogger::logLogin — INSERT succeeded but lastInsertId() returned 0');
                return false;
            }

            $_SESSION['login_log_id'] = $logId;
            error_log("LoginLogger::logLogin — Recorded log_id={$logId}, user={$username}, time={$now}");

            return true;
        } catch (PDOException $e) {
            error_log('LoginLogger::logLogin — PDOException: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            error_log('LoginLogger::logLogin — Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a logout event.
     *
     * @param int|string|null $userId
     * @param string|null     $username  (kept for signature compatibility; not needed for PK path)
     * @param string          $reason    'manual' | 'timeout' | 'idle'
     */
    public function logLogout(
        int|string|null $userId   = null,
        ?string         $username = null,
        string          $reason   = 'manual'
    ): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // [TZ-2] PHP-generated timestamp for the logout time.
            $now    = $this->now();
            $status = in_array($reason, ['timeout', 'idle'], true) ? 'timeout' : 'logged_out';
            $logId  = isset($_SESSION['login_log_id']) ? (int) $_SESSION['login_log_id'] : null;

            if ($logId !== null && $logId > 0) {
                // Fast path: update by primary key.
                $stmt = $this->conn->prepare(
                    "UPDATE imis_history_login_logs
                        SET logout_time = :now,
                            status      = :status,
                            updated_at  = CURRENT_TIMESTAMP
                      WHERE id     = :id
                        AND status = 'active'"
                );
                $stmt->execute([
                    ':now'    => $now,
                    ':status' => $status,
                    ':id'     => $logId,
                ]);
                error_log("LoginLogger::logLogout — log_id={$logId}, status={$status}, rows={$stmt->rowCount()}, time={$now}");
            } elseif ($userId !== null) {
                // Fallback: match by user_id + session_id.
                error_log("LoginLogger::logLogout — Fallback path for user_id={$userId}");
                $stmt = $this->conn->prepare(
                    "UPDATE imis_history_login_logs
                        SET logout_time = :now,
                            status      = :status,
                            updated_at  = CURRENT_TIMESTAMP
                      WHERE user_id    = :user_id
                        AND session_id = :session_id
                        AND status     = 'active'"
                );
                $stmt->execute([
                    ':now'        => $now,
                    ':status'     => $status,
                    ':user_id'    => (int) $userId,
                    ':session_id' => session_id(),
                ]);
                error_log("LoginLogger::logLogout — Fallback rows={$stmt->rowCount()}, time={$now}");
            } else {
                error_log('LoginLogger::logLogout — Cannot log: no log ID and no user_id provided');
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log('LoginLogger::logLogout — PDOException: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            error_log('LoginLogger::logLogout — Error: ' . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITY
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Close all other active sessions for a user before a new login is recorded.
     */
    private function markPreviousSessionsAsLoggedOut(int $userId): void
    {
        try {
            // [TZ-2] PHP-generated timestamp.
            $stmt = $this->conn->prepare(
                "UPDATE imis_history_login_logs
                    SET logout_time = :now,
                        status      = 'logged_out',
                        updated_at  = CURRENT_TIMESTAMP
                  WHERE user_id    = :user_id
                    AND status     = 'active'
                    AND session_id != :session_id"
            );
            $stmt->execute([
                ':now'        => $this->now(),
                ':user_id'    => $userId,
                ':session_id' => session_id(),
            ]);

            $rows = $stmt->rowCount();
            if ($rows > 0) {
                error_log("LoginLogger::markPreviousSessionsAsLoggedOut — Closed {$rows} stale session(s) for user_id={$userId}");
            }
        } catch (PDOException $e) {
            // Non-fatal: log and continue so the primary login is not blocked.
            error_log('LoginLogger::markPreviousSessionsAsLoggedOut — PDOException: ' . $e->getMessage());
        }
    }

    /**
     * Return all active sessions for a user (useful for admin dashboards).
     */
    public function getActiveSessions(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM imis_history_login_logs
                  WHERE user_id = :user_id
                    AND status  = 'active'
                  ORDER BY login_time DESC"
            );
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('LoginLogger::getActiveSessions — ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark sessions older than $daysOld as timed-out (maintenance utility).
     */
    public function cleanupOldSessions(int $daysOld = 30): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days")); // [TZ-2]

            $stmt = $this->conn->prepare(
                "UPDATE imis_history_login_logs
                    SET status      = 'timeout',
                        logout_time = COALESCE(logout_time, login_time),
                        updated_at  = CURRENT_TIMESTAMP
                  WHERE status     = 'active'
                    AND login_time < :cutoff"
            );
            $stmt->execute([':cutoff' => $cutoff]);

            $rows = $stmt->rowCount();
            error_log("LoginLogger::cleanupOldSessions — Closed {$rows} session(s) older than {$daysOld} days");
            return $rows;
        } catch (PDOException $e) {
            error_log('LoginLogger::cleanupOldSessions — ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Debug helper: dump the live table structure to the error log.
     */
    public function verifyTableStructure(): array|false
    {
        try {
            $stmt = $this->conn->query("DESCRIBE imis_history_login_logs");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('LoginLogger::verifyTableStructure — ' . json_encode($cols));
            return $cols;
        } catch (PDOException $e) {
            error_log('LoginLogger::verifyTableStructure — ' . $e->getMessage());
            return false;
        }
    }
}
