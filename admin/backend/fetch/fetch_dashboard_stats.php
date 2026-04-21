<?php

/**
 * admin/backend/fetch/fetch_dashboard_stats.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Superadmin dashboard: aggregated statistics endpoint.
 * Returns a single JSON payload with all widget data so the frontend
 * needs only one round-trip on page load.
 *
 * Output shape:
 * {
 * success: true,
 * summary: { total_users, active_users, inactive_users,
 * total_projects, active_projects,
 * total_access_grants, users_with_access, users_without_access,
 * logins_today, active_sessions },
 * users_by_division: [{ division, count }],
 * access_by_project:  [{ project_name, count }],
 * login_trend:        [{ date, count }],          // last 14 days
 * recent_logins:      [{ username, name, ip_address, login_time,
 * logout_time, status, user_agent }], // latest 10
 * top_systems:        [{ project_name, code_name, user_count, is_active }],
 * users_no_access:    [{ name, position, fo_rsu, role }],
 * role_distribution:  [{ role, count }]
 * }
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/connect.php';

header('Content-Type: application/json; charset=utf-8');

/* ── Auth guard ─────────────────────────────────────────────────────────── */
if (
    empty($_SESSION['username']) ||
    empty($_SESSION['role'])      ||
    $_SESSION['role'] !== 'superadmin'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit();
}

/* ── Helper: query or die with JSON error ────────────────────────────────── */
function qry(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    if (!$params) {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $error = $pdo->errorInfo();
            throw new RuntimeException($error[2] ?? 'Unknown query error');
        }
        return $stmt;
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        $error = $pdo->errorInfo();
        throw new RuntimeException($error[2] ?? 'Unknown prepare error');
    }
    $stmt->execute($params);
    return $stmt;
}

try {
    /* ── 1. Summary counts ─────────────────────────────────────────────── */
    $r = qry($conn, "
        SELECT
            COUNT(*)                                             AS total_users,
            SUM(status = 'Active')                               AS active_users,
            SUM(status = 'Inactive')                             AS inactive_users
        FROM users_cscro8
    ")->fetch(PDO::FETCH_ASSOC);

    $summary = [
        'total_users'         => (int)$r['total_users'],
        'active_users'        => (int)$r['active_users'],
        'inactive_users'      => (int)$r['inactive_users'],
    ];

    /* Projects */
    $r = qry($conn, "
        SELECT COUNT(*) AS total, SUM(is_active) AS active_projects
        FROM imis_projects
    ")->fetch(PDO::FETCH_ASSOC);

    $summary['total_projects']  = (int)$r['total'];
    $summary['active_projects'] = (int)$r['active_projects'];

    /* Access grants */
    $r = qry($conn, "
        SELECT
            COUNT(*)                           AS total_grants,
            COUNT(DISTINCT user_id)            AS users_with_access
        FROM imis_system_access
    ")->fetch(PDO::FETCH_ASSOC);

    $summary['total_access_grants']  = (int)$r['total_grants'];
    $summary['users_with_access']    = (int)$r['users_with_access'];
    $summary['users_without_access']  = $summary['active_users'] - $summary['users_with_access'];

    /* Login activity (today + active sessions) */
    $r = qry($conn, "
        SELECT
            SUM(DATE(login_time) = CURDATE())   AS logins_today,
            SUM(status = 'active')              AS active_sessions
        FROM imis_history_login_logs
        WHERE login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch(PDO::FETCH_ASSOC);

    $summary['logins_today']     = (int)($r['logins_today']   ?? 0);
    $summary['active_sessions']  = (int)($r['active_sessions'] ?? 0);

    /* ── 2. Users by division ─────────────────────────────────────────── */
    $users_by_division = [];
    $res = qry($conn, "
        SELECT fo_rsu AS division, COUNT(*) AS count
        FROM users_cscro8
        WHERE status = 'Active'
        GROUP BY fo_rsu
        ORDER BY count DESC
        LIMIT 15
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $users_by_division[] = [
            'division' => $row['division'],
            'count'    => (int)$row['count'],
        ];
    }

    /* ── 3. Access grants by project ─────────────────────────────────── */
    $access_by_project = [];
    $res = qry($conn, "
        SELECT p.project_name, COUNT(sa.id) AS count
        FROM imis_projects p
        LEFT JOIN imis_system_access sa ON sa.project_id = p.id
        WHERE p.is_active = 1
        GROUP BY p.id, p.project_name
        ORDER BY count DESC
        LIMIT 12
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $access_by_project[] = [
            'project_name' => $row['project_name'],
            'count'        => (int)$row['count'],
        ];
    }

    /* ── 4. Login trend — last 14 days ───────────────────────────────── */
    $login_trend = [];
    $res = qry($conn, "
        SELECT DATE(login_time) AS date, COUNT(*) AS count
        FROM imis_history_login_logs
        WHERE login_time >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(login_time)
        ORDER BY date ASC
    ");
    /* Fill in gaps (days with no logins) */
    $trend_map = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $trend_map[$row['date']] = (int)$row['count'];
    }
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $login_trend[] = ['date' => $d, 'count' => $trend_map[$d] ?? 0];
    }

    /* ── 5. Recent logins (latest 10) ────────────────────────────────── */
    $recent_logins = [];
    $res = qry($conn, "
        SELECT
            l.username,
            CONCAT(u.fname, ' ', u.lname)  AS name,
            l.ip_address,
            l.login_time,
            l.logout_time,
            l.status,
            l.user_agent
        FROM imis_history_login_logs l
        LEFT JOIN users_cscro8 u ON u.id = l.user_id
        ORDER BY l.login_time DESC
        LIMIT 10
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $recent_logins[] = $row;
    }

    /* ── 6. Top systems by access count ─────────────────────────────── */
    $top_systems = [];
    $res = qry($conn, "
        SELECT
            p.project_name,
            p.code_name,
            p.is_active,
            COUNT(sa.id) AS user_count
        FROM imis_projects p
        LEFT JOIN imis_system_access sa ON sa.project_id = p.id
        GROUP BY p.id, p.project_name, p.code_name, p.is_active
        ORDER BY user_count DESC
        LIMIT 8
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $top_systems[] = [
            'project_name' => $row['project_name'],
            'code_name'    => $row['code_name'],
            'user_count'   => (int)$row['user_count'],
            'is_active'    => (bool)$row['is_active'],
        ];
    }

    /* ── 7. Users with no system access (active only) ────────────────── */
    $users_no_access = [];
    $res = qry($conn, "
        SELECT
            CONCAT(u.fname, ' ', u.lname) AS name,
            u.position,
            u.fo_rsu,
            u.role
        FROM users_cscro8 u
        WHERE u.status = 'Active'
          AND u.id NOT IN (SELECT DISTINCT user_id FROM imis_system_access)
        ORDER BY u.lname ASC
        LIMIT 10
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $users_no_access[] = $row;
    }

    /* ── 8. IMIS role distribution ───────────────────────────────────── */
    $role_distribution = [];
    $res = qry($conn, "
        SELECT role, COUNT(*) AS count
        FROM users_cscro8
        WHERE status = 'Active'
        GROUP BY role
        ORDER BY count DESC
    ");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $role_distribution[] = [
            'role'  => $row['role'],
            'count' => (int)$row['count'],
        ];
    }

    /* ── Assemble & output ───────────────────────────────────────────── */
    echo json_encode([
        'success'           => true,
        'summary'           => $summary,
        'users_by_division' => $users_by_division,
        'access_by_project' => $access_by_project,
        'login_trend'       => $login_trend,
        'recent_logins'     => $recent_logins,
        'top_systems'       => $top_systems,
        'users_no_access'   => $users_no_access,
        'role_distribution' => $role_distribution,
        'generated_at'      => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
