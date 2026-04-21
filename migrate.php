<?php

/**
 * migrate.php — One-time migration: system_access → imis_system_access
 * ─────────────────────────────────────────────────────────────────────────────
 * Run ONCE after schema.sql has been executed.
 * Reads every row in the old system_access table and creates the equivalent
 * imis_system_access rows using the new normalised structure.
 *
 * Safe to re-run: uses INSERT IGNORE so duplicates are skipped, not errored.
 *
 * Usage (CLI):
 *   php migrate.php
 *
 * IMPORTANT:
 *   - Run schema.sql first (creates imis_projects, imis_access_roles, imis_system_access).
 *   - The old system_access table is NOT dropped by this script — drop it manually
 *     once you have verified the migration is correct.
 *   - Roles marked 'None' or '' in the old table are skipped (no access = no row).
 *   - A migration summary is printed at the end.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/connect.php';

// ── Mapping: old column name → new project code_name(s) ──────────────────────
// A column can map to multiple projects (procurement → PROCURE + PROCUREMENT).
// lcmms is intentionally omitted — no project exists for it in the new schema.
const COLUMN_TO_PROJECTS = [
    'otrs'        => ['OTRS'],
    'eris'        => ['ERIS'],
    'ors'         => ['ORS'],
    'cdl'         => ['CDL'],
    'iis'         => ['IIS'],
    'rfcs'        => ['RFCS'],
    'dvs'         => ['DVS'],
    'cts'         => ['CTS'],
    'lms'         => ['LMS'],
    'psed'        => ['PSED'],
    'rooms'       => ['ROOMS'],
    'msdeserve'   => ['MSDESERVE'],
    'ictsrts'     => ['ICTSRTS'],
    'jportal'     => ['JPORTAL'],
    'comexams'    => ['COMEXAMS'],
    'procurement' => ['PROCURE', 'PROCUREMENT'], // one old column → two new projects
    'gad-corner'  => ['GAD-CORNER'],
    'pms'         => ['PMS'],
    // 'lcmms' => intentionally excluded (no project defined)
];

// ── Build lookup caches from the new tables ───────────────────────────────────
// projectCache:  code_name  → project id
// roleCache:     "PROJECT:role_name" → role id
$projectCache = [];
$roleCache    = [];

$rows = $conn->query(
    'SELECT p.id AS pid, p.code_name, r.id AS rid, r.name AS role_name
     FROM   imis_projects p
     JOIN   imis_access_roles r ON r.project_id = p.id'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $projectCache[$row['code_name']] = (int) $row['pid'];
    $roleCache[$row['code_name'] . ':' . $row['role_name']] = (int) $row['rid'];
}

// ── Fetch all old system_access rows ─────────────────────────────────────────
$oldRows = $conn->query('SELECT * FROM system_access')->fetchAll(PDO::FETCH_ASSOC);

// ── Prepare insert statement ──────────────────────────────────────────────────
$insert = $conn->prepare(
    'INSERT IGNORE INTO imis_system_access (user_id, project_id, role_id)
     VALUES (:user_id, :project_id, :role_id)'
);

// ── Counters for summary report ───────────────────────────────────────────────
$inserted = 0;
$skipped  = 0;
$warnings = [];

// ── Process each old row ──────────────────────────────────────────────────────
foreach ($oldRows as $oldRow) {
    $userId = (int) $oldRow['user'];

    foreach (COLUMN_TO_PROJECTS as $column => $projectCodes) {
        $role = trim((string) ($oldRow[$column] ?? ''));

        // Skip no-access entries
        if ($role === '' || strtolower($role) === 'none') {
            continue;
        }

        foreach ($projectCodes as $code) {
            $projectId = $projectCache[$code] ?? null;
            $roleKey   = $code . ':' . $role;
            $roleId    = $roleCache[$roleKey] ?? null;

            // Project exists but this role isn't seeded (e.g. DVS User, IIS User)
            if ($projectId !== null && $roleId === null) {
                $warnings[] = "  User {$userId}: role '{$role}' for {$code} has no matching"
                    . " imis_access_roles row — skipped (define the role URL first).";
                $skipped++;
                continue;
            }

            // Project not in new schema at all (e.g. lcmms)
            if ($projectId === null) {
                $warnings[] = "  User {$userId}: column '{$column}' maps to unknown project '{$code}' — skipped.";
                $skipped++;
                continue;
            }

            try {
                $insert->execute([
                    ':user_id'    => $userId,
                    ':project_id' => $projectId,
                    ':role_id'    => $roleId,
                ]);
                $inserted += $insert->rowCount(); // 0 if INSERT IGNORE skipped a duplicate
            } catch (PDOException $e) {
                $warnings[] = "  User {$userId} / {$code}: DB error — " . $e->getMessage();
                $skipped++;
            }
        }
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "═══════════════════════════════════════════════════════════════" . PHP_EOL;
echo " Migration complete" . PHP_EOL;
echo "═══════════════════════════════════════════════════════════════" . PHP_EOL;
echo " Users processed : " . count($oldRows) . PHP_EOL;
echo " Rows inserted   : {$inserted}" . PHP_EOL;
echo " Rows skipped    : {$skipped}" . PHP_EOL;

if ($warnings) {
    echo PHP_EOL . " Warnings (" . count($warnings) . "):" . PHP_EOL;
    foreach ($warnings as $w) {
        echo $w . PHP_EOL;
    }
}

echo PHP_EOL;
echo " Next steps:" . PHP_EOL;
echo "  1. Verify imis_system_access in your DB client." . PHP_EOL;
echo "  2. Test a login for each role (Admin, User, Superadmin, staff…)." . PHP_EOL;
echo "  3. When satisfied, DROP TABLE system_access;" . PHP_EOL;
echo PHP_EOL;
