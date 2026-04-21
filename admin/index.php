<?php

/**
 * admin/index_dashboard.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Super Administrator Dashboard — entry point for the IMIS admin portal.
 * Restricted to role = 'superadmin' only.
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/connect.php';

/* ── Auth guard ─────────────────────────────────────────────────────────── */
if (empty($_SESSION['username']) || empty($_SESSION['role'])) {
    session_unset();
    session_destroy();
    header('Location: ../login');
    exit();
}

switch ($_SESSION['role']) {
    case 'superadmin':
        break;
    case 'admin':
    case 'user':
        header('Location: ../index_dashboard');
        exit();
    default:
        session_unset();
        session_destroy();
        header('Location: ../login');
        exit();
}

$adminName = htmlspecialchars($_SESSION['username'] ?? 'Administrator', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSC RO VIII - IMIS | Super Admin Dashboard</title>
    <meta name="description" content="IMIS Super Administrator Dashboard">

    <?php include 'vendor_css.html' ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">

</head>

<body>
    <?php
    include_once __DIR__ . '/../imis_include.php';
    imis_include('header_js');
    include 'inc/sidebar.php';
    ?>

    <main id="main" class="main">

        <!-- ── Welcome banner ── -->
        <div class="welcome-banner">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="mb-2">
                        <span class="badge-superadmin">
                            <i class="bi bi-shield-fill-check"></i>Super Administrator
                        </span>
                    </div>
                    <h4>Welcome back, <?= $adminName ?>!</h4>
                    <p>
                        <i class="bi bi-calendar3"></i>
                        <?= date('l, F j, Y') ?>
                        <span style="opacity:.5">·</span>
                        <i class="bi bi-clock"></i>
                        <span id="liveClock"><?= date('h:i A') ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="users_mgmt" class="quick-action-btn">
                        <i class="bi bi-people-fill"></i>Manage Users
                    </a>
                    <a href="access_mgmt" class="quick-action-btn">
                        <i class="bi bi-shield-lock-fill"></i>System Access
                    </a>
                    <a href="projects_mgmt" class="quick-action-btn">
                        <i class="bi bi-grid-fill"></i>Projects
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Toolbar ── -->
        <div class="dashboard-toolbar mb-3">
            <span id="generatedAt" class="timestamp-bar"></span>
            <button id="refreshDashboardBtn" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             DASHBOARD WRAPPER — spinner overlay lives here
             ════════════════════════════════════════════════════════════════ -->
        <div id="dashboardWrapper">

            <!-- Loading spinner -->
            <div id="dashboardSpinner">
                <div class="text-center">
                    <div class="spinner-border text-primary" style="width:2.4rem;height:2.4rem" role="status"></div>
                    <p class="mt-2 text-muted small mb-0">Loading dashboard…</p>
                </div>
            </div>

            <div id="dashboardContent">

                <!-- ══════════════════════════════════════════════════════
                     ROW 1 — Key metric cards (4 across)
                     ══════════════════════════════════════════════════════ -->
                <div class="row g-3 mb-3">

                    <!-- Total Users -->
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card card card-accent-primary h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="metric-label">Total Users</div>
                                        <div class="metric-value" id="cardTotalUsers" data-target="0">0</div>
                                        <div class="metric-sub">
                                            <span class="fw-semibold" style="color:var(--color-success)" id="cardActiveUsers">0</span>
                                            active &nbsp;·&nbsp;
                                            <span style="color:var(--color-danger)" id="cardInactiveUsers">0</span>
                                            inactive
                                        </div>
                                    </div>
                                    <div class="metric-icon" style="background:var(--color-primary-light)">
                                        <i class="bi bi-people-fill" style="color:var(--color-primary)"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registered Systems -->
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card card card-accent-success h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="metric-label">Registered Systems</div>
                                        <div class="metric-value" id="cardTotalSystems" data-target="0">0</div>
                                        <div class="metric-sub">
                                            <span class="fw-semibold" style="color:var(--color-success)" id="cardActiveSystems">0</span>
                                            active
                                        </div>
                                        <div class="metric-progress">
                                            <div id="systemsProgressBar"
                                                class="bar"
                                                style="width:0%;background:var(--color-success)">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="metric-icon" style="background:var(--color-success-light)">
                                        <i class="bi bi-grid-3x3-gap-fill" style="color:var(--color-success)"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Access Grants -->
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card card card-accent-warning h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="metric-label">Access Grants</div>
                                        <div class="metric-value" id="cardAccessGrants" data-target="0">0</div>
                                        <div class="metric-sub">
                                            <span class="fw-semibold" style="color:var(--color-success)" id="cardUsersWithAccess">0</span>
                                            granted &nbsp;·&nbsp;
                                            <span style="color:var(--color-danger)" id="cardUsersWithoutAccess">0</span>
                                            unassigned
                                        </div>
                                        <div class="metric-progress">
                                            <div id="accessProgressBar"
                                                class="bar"
                                                style="width:0%;background:var(--color-warning)">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="metric-icon" style="background:var(--color-warning-light)">
                                        <i class="bi bi-shield-check-fill" style="color:var(--color-warning)"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login Activity -->
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card card card-accent-info h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="metric-label">Login Activity</div>
                                        <div class="metric-value" id="cardLoginsToday" data-target="0">0</div>
                                        <div class="metric-sub">logins today</div>
                                        <div class="metric-sub mt-1">
                                            <span class="fw-semibold" style="color:var(--color-info)" id="cardActiveSessions">0</span>
                                            active session(s) now
                                        </div>
                                    </div>
                                    <div class="metric-icon" style="background:var(--color-info-light)">
                                        <i class="bi bi-activity" style="color:var(--color-info)"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /ROW 1 -->

                <!-- ══════════════════════════════════════════════════════
                     ROW 2 — Login trend + Role donut
                     ══════════════════════════════════════════════════════ -->
                <div class="row g-3 mb-3">

                    <!-- Login trend -->
                    <div class="col-xl-8">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-activity" style="color:var(--color-primary)"></i>
                                        Login Trend
                                    </div>
                                    <span class="text-muted small">Last 14 days</span>
                                </div>
                                <div class="chart-container chart-lg">
                                    <canvas id="loginTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Role distribution -->
                    <div class="col-xl-4">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-person-badge" style="color:var(--color-primary)"></i>
                                        IMIS Roles
                                    </div>
                                </div>
                                <div class="chart-container">
                                    <canvas id="roleDonutChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /ROW 2 -->

                <!-- ══════════════════════════════════════════════════════
                     ROW 3 — Users by division + Access by project
                     ══════════════════════════════════════════════════════ -->
                <div class="row g-3 mb-3">

                    <!-- Users by division -->
                    <div class="col-xl-5">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-building" style="color:var(--color-primary)"></i>
                                        Users by Division / Office
                                    </div>
                                    <span class="text-muted small">Active users</span>
                                </div>
                                <div class="chart-container chart-lg">
                                    <canvas id="divisionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Access by project -->
                    <div class="col-xl-7">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-bar-chart-fill" style="color:var(--color-primary)"></i>
                                        Access Grants per System
                                    </div>
                                    <span class="text-muted small">Active systems</span>
                                </div>
                                <div class="chart-container chart-lg">
                                    <canvas id="accessProjectChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /ROW 3 -->

                <!-- ══════════════════════════════════════════════════════
                     ROW 4 — Top systems table + Users without access
                     ══════════════════════════════════════════════════════ -->
                <div class="row g-3 mb-3">

                    <!-- Top systems -->
                    <div class="col-xl-5">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body pb-0">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-trophy" style="color:var(--color-primary)"></i>
                                        Top Systems by User Count
                                    </div>
                                    <a href="index_access_management.php"
                                        class="btn btn-outline-primary btn-sm py-1 px-3"
                                        style="font-size:12px;border-radius:var(--radius-md)">
                                        Manage <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table dash-table table-hover">
                                    <thead>
                                        <tr>
                                            <th>System</th>
                                            <th class="text-center">Status</th>
                                            <th>Assigned</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topSystemsTbody">
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                                Loading…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Users without access -->
                    <div class="col-xl-7">
                        <div class="card dash-content-card border-0 shadow-sm h-100">
                            <div class="card-body pb-0">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-person-x" style="color:var(--color-danger)"></i>
                                        Users Without System Access
                                        <span id="noAccessCount"
                                            class="badge bg-danger ms-1 fw-semibold">0</span>
                                    </div>
                                    <a href="index_access_management.php"
                                        class="btn btn-outline-danger btn-sm py-1 px-3"
                                        style="font-size:12px;border-radius:var(--radius-md)">
                                        Assign <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive scrollable-tbody">
                                <table class="table dash-table table-hover">
                                    <thead style="position:sticky;top:0;z-index:1">
                                        <tr>
                                            <th>Name / Position</th>
                                            <th>Division</th>
                                            <th class="text-center">IMIS Role</th>
                                        </tr>
                                    </thead>
                                    <tbody id="noAccessTbody">
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                                Loading…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div><!-- /ROW 4 -->

                <!-- ══════════════════════════════════════════════════════
                     ROW 5 — Recent login activity
                     ══════════════════════════════════════════════════════ -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card dash-content-card border-0 shadow-sm">
                            <div class="card-body pb-0">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="bi bi-clock-history" style="color:var(--color-primary)"></i>
                                        Recent Login Activity
                                    </div>
                                    <a href="login_logs.php"
                                        class="btn btn-outline-primary btn-sm py-1 px-3"
                                        style="font-size:12px;border-radius:var(--radius-md)">
                                        View All Logs <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table dash-table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th class="text-center">IP Address</th>
                                            <th>Login Time</th>
                                            <th>Logout Time</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentLoginsTbody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                                Loading…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div><!-- /ROW 5 -->

            </div><!-- /#dashboardContent -->
        </div><!-- /#dashboardWrapper -->

    </main><!-- /main -->

    <!-- ── Vendor JS ── -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <!-- Dashboard module -->
    <script src="js/dashboard.js"></script>

    <?php imis_include('footer'); ?>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center" id="backToTop">
        <i class="bi bi-arrow-up-short"></i>
    </a>

    <script>
        /* ── Back to top ── */
        const btt = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            btt.classList.toggle('active', window.scrollY > 200);
        });
        btt.addEventListener('click', e => {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        /* ── Live clock ── */
        function updateClock() {
            const now = new Date();
            const h = now.getHours();
            const m = String(now.getMinutes()).padStart(2, '0');
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = ((h % 12) || 12);
            document.getElementById('liveClock').textContent = `${h12}:${m} ${ampm}`;
        }
        updateClock();
        setInterval(updateClock, 30000);
    </script>

</body>

</html>