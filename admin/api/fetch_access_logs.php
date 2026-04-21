<?php
// superadmin/fetch_access_logs.php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/connect.php';

// Guard: superadmin only
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['data' => []]);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

try {
    // JOIN back to imis_history_login_logs for the columns that were intentionally
    // not duplicated in imis_history_access_logs (username, ip_address, user_agent).
    // LEFT JOIN so rows where login_log_id is NULL (e.g. login-logger failure) still appear.
    $stmt = $conn->prepare(
        "SELECT
             a.id,
             a.project_code,
             a.role_name,
             a.accessed_at,
             COALESCE(l.username,   '<i class=\"text-muted\">—</i>') AS username,
             COALESCE(l.ip_address, '<i class=\"text-muted\">—</i>') AS ip_address,
             COALESCE(l.user_agent, '<i class=\"text-muted\">—</i>') AS user_agent,
             p.project_name
         FROM  imis_history_access_logs  a
         LEFT JOIN imis_history_login_logs l ON l.id  = a.login_log_id
         LEFT JOIN imis_projects          p ON p.id  = a.project_id
         ORDER BY a.accessed_at DESC"
    );
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data  = [];
    $index = 1;

    foreach ($results as $row) {
        // ── Role badge ────────────────────────────────────────────────────────
        $roleBadge = match (strtolower($row['role_name'])) {
            'admin',
            'superadmin' => 'bg-primary',
            'user'       => 'bg-info text-dark',
            default      => 'bg-secondary',
        };

        // ── Project label ─────────────────────────────────────────────────────
        // Show the short code prominently; full name as tooltip if available.
        $projectName = htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $projectCode = htmlspecialchars($row['project_code'],  ENT_QUOTES, 'UTF-8');

        $systemCell = $projectName
            ? '<span title="' . $projectName . '">' . $projectCode . '</span>'
            : $projectCode;

        $data[] = [
            '#'           => $index++,
            'username'    => $row['username'],
            'ip_address'  => $row['ip_address'],
            'system'      => $systemCell,
            'role'        => '<span class="badge ' . $roleBadge . '">'
                . htmlspecialchars($row['role_name'], ENT_QUOTES, 'UTF-8')
                . '</span>',
            'accessed_at' => $row['accessed_at'],
            'user_agent'  => $row['user_agent'],
        ];
    }

    echo json_encode(['data' => $data]);
} catch (PDOException $e) {
    error_log('[fetch_access_logs] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['data' => []]);
}
