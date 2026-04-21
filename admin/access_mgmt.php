<?php
/* imis/admin/index_access_management.php
 * System Access Management – Super Administrator only.
 * Luma Framework removed; uses Bootstrap 5 + DataTables + SweetAlert2 via CDN.
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
    case 'admin':
    case 'user':
        header('Location: ../dashboard');
        exit();
    case 'superadmin':
        break;
    default:
        session_unset();
        session_destroy();
        header('Location: ../login');
        exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>CSC RO VIII - IMIS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <?php include 'vendor_css.html' ?>

    <style>
        /* ── Modal: scrollable user list ───────────────────────────────── */
        #accessTableWrapper {
            max-height: 58vh;
            overflow-y: auto;
        }

        #accessTableWrapper table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f6f9ff;
            /* NiceAdmin table-light equivalent */
            white-space: nowrap;
        }

        /* ── Division group header rows ─────────────────────────────────── */
        tr.group-row>td {
            background: #f6f9ff !important;
            color: #4154f1;
            /* NiceAdmin primary */
            font-weight: 600;
            font-size: 11px;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 6px 14px;
        }

        /* ── Unsaved-change row tint ────────────────────────────────────── */
        tr.row-dirty>td {
            background: #fff3cd !important;
            /* Bootstrap warning-light */
        }

        /* ── Role <select> minimum width ────────────────────────────────── */
        .role-select {
            min-width: 160px;
        }

        /* ── Stats bar pills ────────────────────────────────────────────── */
        #accessStatsBar .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* ── Modal search input cap ──────────────────────────────────────── */
        #modalUserSearch {
            max-width: 260px;
        }

        /* ── Pending-count pill (hidden until changes exist) ─────────────── */
        #pendingCount {
            display: none;
            font-size: 11px;
        }

        /* ── Modal header accent (NiceAdmin primary) ─────────────────────── */
        #accessModal .modal-header.bg-primary {
            background-color: #4154f1 !important;
        }

        /* ── Card title: override NiceAdmin's top padding inside this page ── */
        .card .card-title {
            padding-top: 10px;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>

    <?php
    /* Keep your existing header/nav/sidebar includes untouched */
    include_once __DIR__ . '/../imis_include.php';
    imis_include('header_js');
    include 'inc/sidebar.php';
    ?>

    <main id="main" class="main">

        <!-- ── Page title ── -->
        <div class="pagetitle mb-3">
            <h1>System Access Management</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../index_dashboard"><i class="bi bi-house-door me-1"></i>Home</a>
                    </li>
                    <li class="breadcrumb-item active">System Access</li>
                </ol>
            </nav>
        </div>

        <!-- ── Projects card ── -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex mt-3 align-items-center justify-content-between">
                    <h5 class="card-title">
                        <i class="bi bi-shield-lock-fill me-2"></i>Registered Systems
                    </h5>
                    <span id="projectSummary" class="text-muted small mb-3"></span>
                </div>

                <table
                    id="projectsTable"
                    class="table table-striped table-hover table-bordered w-100"
                    style="font-size: 14px">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px">#</th>
                            <th>System Name</th>
                            <th>Description</th>
                            <th class="text-center" style="width:110px">Code</th>
                            <th class="text-center" style="width:90px">Status</th>
                            <th class="text-center" style="width:110px">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </main><!-- /main -->


    <!-- ══════════════════════════════════════════════════════════════════════
         ACCESS MANAGEMENT MODAL
         ══════════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="accessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">

                <!-- Header -->
                <div class="modal-header bg-primary text-white py-2 px-3">
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                        <i class="bi bi-person-gear fs-5 flex-shrink-0"></i>
                        <div class="min-w-0">
                            <h6 class="modal-title fw-bold mb-0 text-truncate" id="modalProjectName">
                                Manage Access
                            </h6>
                            <small class="text-white-50" id="modalProjectCode"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
                </div>

                <!-- Sub-toolbar (search + bulk actions) -->
                <div class="px-3 pt-2 pb-1 border-bottom bg-light d-flex align-items-center flex-wrap gap-2"
                    id="accessToolbar" style="display:none !important">

                    <!-- Search users -->
                    <div class="input-group input-group-sm" id="modalUserSearch">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="userSearchInput"
                            class="form-control"
                            placeholder="Filter by name, position or division…">
                        <button class="btn btn-outline-secondary" id="clearSearchBtn" type="button"
                            title="Clear filter">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <!-- Role filter -->
                    <select id="roleFilterSelect" class="form-select form-select-sm" style="max-width:180px">
                        <option value="">All roles</option>
                    </select>

                    <!-- Bulk: assign role to all visible -->
                    <div class="d-flex align-items-center gap-1 ms-auto">
                        <span class="text-muted small">Apply to all visible:</span>
                        <select id="bulkRoleSelect" class="form-select form-select-sm" style="max-width:160px">
                            <option value="">— pick role —</option>
                        </select>
                        <button id="bulkApplyBtn" class="btn btn-sm btn-outline-primary" disabled>
                            <i class="bi bi-check2-all me-1"></i>Apply
                        </button>
                        <button id="revokeAllBtn" class="btn btn-sm btn-outline-danger" title="Revoke all access for visible users">
                            <i class="bi bi-slash-circle me-1"></i>Revoke All
                        </button>
                    </div>
                </div>

                <!-- Stats bar -->
                <div id="accessStatsBar" class="px-3 py-1 border-bottom small text-muted d-flex gap-3"
                    style="background:#fafafa; display:none !important">
                    <span class="stat-pill bg-light border">
                        <i class="bi bi-people"></i>
                        <span id="statTotal">0</span> users
                    </span>
                    <span class="stat-pill" style="background:#d1e7dd; color:#0a3622">
                        <i class="bi bi-shield-check"></i>
                        <span id="statWithAccess">0</span> with access
                    </span>
                    <span class="stat-pill" style="background:#f8d7da; color:#58151c">
                        <i class="bi bi-shield-x"></i>
                        <span id="statNoAccess">0</span> no access
                    </span>
                    <span class="stat-pill ms-auto" id="pendingCount"
                        style="background:#fff3cd; color:#664d03">
                        <i class="bi bi-clock-history"></i>
                        <span id="statPending">0</span> pending change(s)
                    </span>
                </div>

                <!-- Body states -->
                <div class="modal-body p-0">

                    <!-- Loading -->
                    <div id="accessLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem"></div>
                        <p class="mt-3 text-muted small mb-0">Loading users…</p>
                    </div>

                    <!-- Table -->
                    <div id="accessTableWrapper" style="display:none">
                        <table class="table table-bordered table-hover align-middle mb-0 w-100"
                            id="accessTable" style="font-size:13px">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:34%">Name</th>
                                    <th style="width:30%">Position</th>
                                    <th class="text-center" style="width:22%">Assigned Role</th>
                                    <th class="text-center" style="width:14%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="accessTableBody"></tbody>
                        </table>

                        <!-- No-results from in-modal search -->
                        <div id="noSearchResults" class="text-center py-4 text-muted d-none">
                            <i class="bi bi-search" style="font-size:1.8rem"></i>
                            <p class="mt-2 mb-0 small">No users match your filter.</p>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div id="accessEmpty" class="text-center py-5 text-muted" style="display:none">
                        <i class="bi bi-people" style="font-size:2.5rem"></i>
                        <p class="mt-2 mb-0">No active users available.</p>
                    </div>

                    <!-- Error state -->
                    <div id="accessError" class="text-center py-5" style="display:none">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size:2.5rem"></i>
                        <p class="mt-2 text-danger" id="accessErrorMsg">Something went wrong.</p>
                        <button class="btn btn-sm btn-outline-danger mt-1" id="retryAccessBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>

                </div><!-- /modal-body -->

                <!-- Footer -->
                <div class="modal-footer py-2">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button class="btn btn-success btn-sm" id="saveAccessBtn" disabled>
                        <i class="bi bi-floppy-fill me-1"></i>Save Changes
                    </button>
                </div>

            </div><!-- /modal-content -->
        </div>
    </div><!-- /accessModal -->


    <!-- ── Vendors ── -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables + Bootstrap 5 -->
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Module -->
    <script src="js/access_management.js"></script>

    <?php imis_include('footer'); ?>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center" id="backToTop">
        <i class="bi bi-arrow-up-short"></i>
    </a>

    <script>
        /* Back-to-top — uses NiceAdmin's .active class (visibility + opacity transition) */
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
    </script>

</body>

</html>