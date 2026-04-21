<?php

/**
 * imis/admin/manage_projects.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Superadmin-only UI for managing IMIS projects (imis_projects table).
 * Allows creating, editing, toggling active state, and managing the role →
 * URL mappings stored in imis_access_roles.
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/connect.php';
include_once __DIR__ . '/../imis_include.php';

// ── Auth gate ─────────────────────────────────────────────────────────────────
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
        header('Location: ../dashboard');
        exit();
    default:
        session_unset();
        session_destroy();
        header('Location: ../login');
        exit();
}

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSC RO VIII - IMIS | Manage Projects</title>
    <!-- CSRF token read by JS via this meta tag — never rendered to screen -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php include 'vendor_css.html' ?>
    <style>
        /* ── Role rows inside the modal ───────────────────────────── */
        #rolesBody tr td {
            vertical-align: middle;
        }

        .remove-role-btn {
            line-height: 1;
        }

        /* ── Sticky table header inside the roles tab ─────────────── */
        #rolesTableWrapper {
            max-height: 320px;
            overflow-y: auto;
        }

        #rolesTableWrapper thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8f9fa;
        }

        /* ── Badge alignment in projects DataTable ────────────────── */
        #projectsTable td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <?php imis_include('header_js') ?>
    <?php include 'inc/sidebar.php' ?>

    <main id="main" class="main">
        <div class="pagetitle mb-3">
            <h1>Manage Projects</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index_dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Manage Projects</li>
                </ol>
            </nav>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold text-uppercase text-primary mb-0">
                        <i class="bi bi-grid-fill me-2"></i> IMIS Projects
                    </h5>
                    <button class="btn btn-primary btn-sm" id="addProjectBtn">
                        <i class="bi bi-plus-circle-fill me-1"></i> Add Project
                    </button>
                </div>

                <table id="projectsTable"
                    class="table table-striped table-hover table-bordered w-100"
                    style="font-size: 14px">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center align-middle" style="width:4%">#</th>
                            <th class="align-middle">System Name</th>
                            <th class="align-middle">Description</th>
                            <th class="text-center align-middle" style="width:10%">Code</th>
                            <th class="text-center align-middle" style="width:8%">Setup</th>
                            <th class="text-center align-middle" style="width:8%">Status</th>
                            <th class="text-center align-middle" style="width:13%">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ════════════════════════════════════════════════════════════
         Project Modal — create & edit
         Two tabs:  (1) Project details   (2) Role → URL mappings
         ════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fw-bold mb-0" id="projectModalLabel">
                        <i class="bi bi-grid-fill me-1"></i>
                        <span id="modalTitleText">Add Project</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Hidden project ID — present only when editing -->
                    <input type="hidden" id="projectId">

                    <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="projectTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="detailsTab" data-bs-toggle="tab"
                                data-bs-target="#detailsPane" type="button" role="tab">
                                <i class="bi bi-info-circle me-1"></i> Project Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="rolesTab" data-bs-toggle="tab"
                                data-bs-target="#rolesPane" type="button" role="tab">
                                <i class="bi bi-key me-1"></i> Roles &amp; URLs
                                <span class="badge bg-primary ms-1" id="rolesCount">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="projectTabContent">

                        <!-- ── Tab 1: Project Details ───────────────────────── -->
                        <div class="tab-pane fade show active" id="detailsPane" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        System Code <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle text-muted ms-1"
                                            title="Unique identifier used in PHP/JS (e.g. OTRS, GAD-CORNER). Cannot be changed after creation."
                                            data-bs-toggle="tooltip"></i>
                                    </label>
                                    <input type="text" id="fieldCodeName" class="form-control text-uppercase"
                                        placeholder="e.g. OTRS" maxlength="100" autocomplete="off">
                                    <div class="invalid-feedback" id="codeNameError"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Project Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="fieldProjectName" class="form-control"
                                        placeholder="e.g. Online Training Registration System" maxlength="255">
                                    <div class="invalid-feedback" id="projectNameError"></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea id="fieldDescription" class="form-control" rows="2"
                                        placeholder="Brief description of what this system does" maxlength="1000"></textarea>
                                    <div class="invalid-feedback" id="descriptionError"></div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">
                                        Guest URL
                                        <i class="bi bi-info-circle text-muted ms-1"
                                            title="Optional. When set, users with no assigned role are redirected here instead of being denied. Used by ERIS for its public guest page."
                                            data-bs-toggle="tooltip"></i>
                                    </label>
                                    <input type="text" id="fieldGuestUrl" class="form-control"
                                        placeholder="/eris/db_onsa  (leave blank for no guest access)" maxlength="255">
                                </div>

                                <div class="col-md-2 d-flex flex-column justify-content-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="fieldRequiresSetup">
                                        <label class="form-check-label fw-semibold" for="fieldRequiresSetup">
                                            Requires Setup
                                            <i class="bi bi-info-circle text-muted ms-1"
                                                title="Enable for systems that need a secondary DB query before redirect (CTS, RFCS, ICTSRTS)."
                                                data-bs-toggle="tooltip"></i>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-2 d-flex flex-column justify-content-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="fieldIsActive" checked>
                                        <label class="form-check-label fw-semibold" for="fieldIsActive">
                                            Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /#detailsPane -->

                        <!-- ── Tab 2: Roles & URLs ──────────────────────────── -->
                        <div class="tab-pane fade" id="rolesPane" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="text-muted small mb-0">
                                    Define the roles available for this project and the URL each role is redirected to.
                                    Leave empty to add roles later.
                                </p>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addRoleRowBtn">
                                    <i class="bi bi-plus-lg me-1"></i> Add Role
                                </button>
                            </div>

                            <div id="rolesTableWrapper">
                                <table class="table table-bordered table-hover align-middle mb-0 w-100" style="font-size:13px">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:20%">Role Name <span class="text-danger">*</span></th>
                                            <th>Redirect URL <span class="text-danger">*</span></th>
                                            <th class="text-center" style="width:12%">
                                                External
                                                <i class="bi bi-info-circle text-muted ms-1"
                                                    title="Opens in a new tab (use for external systems like MSDESERVE, COMEXAMS)"
                                                    data-bs-toggle="tooltip"></i>
                                            </th>
                                            <th class="text-center" style="width:7%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rolesBody">
                                        <!-- Role rows injected by JS -->
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-muted small mt-2 mb-0" id="rolesEmptyMsg" style="display:none">
                                <i class="bi bi-info-circle me-1"></i>
                                No roles defined yet. Click <strong>Add Role</strong> to add one.
                            </p>
                        </div><!-- /#rolesPane -->

                    </div><!-- /.tab-content -->
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success" id="saveProjectBtn">
                        <i class="bi bi-floppy-fill me-1"></i> Save
                    </button>
                </div>

            </div>
        </div>
    </div><!-- /#projectModal -->

    <?php include 'vendor_js.html' ?>
    <script src="js/manage_projects.js"></script>
    <?php imis_include('footer') ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center" aria-label="Back to top">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>