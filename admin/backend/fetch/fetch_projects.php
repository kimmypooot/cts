<?php

/**
 * backend/fetch/fetch_projects.php
 * Handles two GET requests:
 * 1. No ID: Returns all IMIS projects for the DataTable { data: [...] }
 * 2. With ID: Returns a specific project and its roles { project: {...}, roles: [...] }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api_guard.php';

try {
    // ── 1. Fetch Single Project (For Edit Modal) ──────────────────────────────
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Get project details
        $stmt = $conn->prepare("SELECT * FROM imis_projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            json_error('Project not found.', 404);
        }

        // Get associated roles
        $rolesStmt = $conn->prepare("
            SELECT id, name, project_url, is_external 
            FROM imis_access_roles 
            WHERE project_id = :id
        ");
        $rolesStmt->execute(['id' => $id]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Return exactly what manage_projects.js is expecting
        json_success([
            'project' => $project,
            'roles'   => $roles
        ]);
        exit;
    }

    // ── 2. Fetch All Projects (For DataTable) ─────────────────────────────────

    /*
     * LEFT JOIN imis_access_roles so we can surface how many roles are
     * defined per project. Useful for the admin to spot projects that have
     * no roles configured yet (role_count = 0).
     */
    $stmt = $conn->query(
        "SELECT
             p.id,
             p.code_name,
             p.project_name,
             p.description,
             p.guest_url,
             p.requires_setup,
             p.is_active,
             COUNT(r.id) AS role_count
         FROM imis_projects p
         LEFT JOIN imis_access_roles r ON r.project_id = p.id
         GROUP BY
             p.id, p.code_name, p.project_name, p.description, p.guest_url, p.requires_setup, p.is_active
         ORDER BY p.project_name ASC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Cast numeric fields for consistent JS handling */
    foreach ($rows as &$row) {
        $row['id']             = (int) $row['id'];
        $row['requires_setup'] = (int) $row['requires_setup'];
        $row['is_active']      = (int) $row['is_active'];
        $row['role_count']     = (int) $row['role_count'];
    }
    unset($row);

    json_success(['data' => $rows]);
} catch (PDOException $e) {
    error_log('[fetch_projects] ' . $e->getMessage());
    json_error('Failed to load projects.', 500);
}
