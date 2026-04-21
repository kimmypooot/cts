<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/connect.php';
include_once __DIR__ . '/../imis_include.php';

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

// Centralised division/office map — single source of truth for both modals
$divisions = [
    'CSC Regional Support Units' => [
        'ord'  => 'Office of the Regional Director',
        'esd'  => 'Examination Services Division',
        'msd'  => 'Management Services Division',
        'hrd'  => 'Human Resource Division',
        'pald' => 'Public Assistance and Liaison Division',
        'psed' => 'Policies and Systems Evaluation Division',
        'lsd'  => 'Legal Services Division',
    ],
    'Field Offices and Satellite Office' => [
        'lfoi'  => 'CSC Field Office - Leyte I',
        'lfoii' => 'CSC Field Office - Leyte II',
        'esfo'  => 'CSC Field Office - Eastern Samar',
        'sfo'   => 'CSC Field Office - Samar',
        'bfo'   => 'CSC Field Office - Biliran',
        'slfo'  => 'CSC Field Office - Southern Leyte',
        'nsfo'  => 'CSC Field Office - Northern Samar',
        'wlso'  => 'CSC Satellite Office - Western Leyte',
    ],
];

function renderDivisionSelect(string $selectId, string $nameAttr, bool $required = true, string $extra = ''): string
{
    global $divisions;
    $req  = $required ? ' required' : '';
    $html = "<select class=\"form-select\" id=\"{$selectId}\" name=\"{$nameAttr}\"{$req}{$extra}>";
    $html .= '<option value="" hidden>— Select Division / Office —</option>';
    foreach ($divisions as $group => $options) {
        $html .= '<optgroup label="' . htmlspecialchars($group) . '">';
        foreach ($options as $val => $label) {
            $html .= '<option value="' . $val . '">' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSC RO VIII - IMIS | User Management</title>
    <?php include 'vendor_css.html' ?>
    <style>
        /* ── Profile picture upload widget ─────────────────────────────────── */
        .profile-upload-wrap {
            position: relative;
            display: inline-block;
            width: 110px;
            height: 110px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .profile-upload-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #dee2e6;
            transition: filter .2s;
        }

        .profile-upload-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .25s;
        }

        .profile-upload-wrap:hover .profile-upload-overlay {
            opacity: 1;
        }

        /* ── Skeleton loader ────────────────────────────────────────────────── */
        .skeleton {
            background: linear-gradient(90deg, #e9ecef 25%, #f8f9fa 50%, #e9ecef 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .skeleton-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            margin: 0 auto;
        }

        .skeleton-line {
            height: 34px;
            margin-bottom: 8px;
        }

        .skeleton-line.short {
            width: 60%;
        }

        .skeleton-line.half {
            width: 48%;
            display: inline-block;
        }

        /* ── Edit modal tweaks ──────────────────────────────────────────────── */
        #editModal .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        #editModal .tab-content {
            min-height: 300px;
        }

        .pw-toggle {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php imis_include('header_js') ?>
    <?php include 'inc/sidebar.php' ?>

    <!-- ===================================================================
         Main Content
    ==================================================================== -->
    <main id="main" class="main">
        <div class="pagetitle mb-3">
            <h1>User Management</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index_dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </nav>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold text-uppercase text-primary mb-0">
                        <i class="bi bi-people-fill me-2"></i> All Users
                    </h5>
                    <button class="btn btn-sm btn-success rounded-pill px-3" id="addUserBtn">
                        <i class="bi bi-person-fill-add me-1"></i> Add User
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="usersTable"
                        class="table table-bordered table-striped table-hover align-middle w-100"
                        style="font-size: 14px">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:5%">#</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Office / Division</th>
                                <th>Position</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">IMIS Role</th>
                                <th class="text-center" style="width:10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>


    <!-- ===================================================================
         Add User Modal
    ==================================================================== -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-fill-add me-1"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="addUserForm" novalidate>

                        <div class="row g-3">

                            <!-- Profile picture upload -->
                            <div class="col-12">
                                <label class="form-label">Profile Picture</label>
                                <div class="d-flex align-items-center gap-3">

                                    <!-- Clickable avatar — same UX as the Edit modal -->
                                    <div class="profile-upload-wrap"
                                        id="addProfileUploadTrigger"
                                        title="Click to upload &amp; crop a photo">
                                        <img id="add-profileImgPreview"
                                            src="../assets/img/default-avatar.png"
                                            alt="Profile Preview">
                                        <div class="profile-upload-overlay">
                                            <i class="bi bi-camera-fill text-white fs-4"></i>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Shown after a successful crop -->
                                        <span class="badge bg-success mb-1 d-none" id="add-photoBadge">
                                            <i class="bi bi-check-circle me-1"></i>Photo ready
                                        </span>
                                        <p class="text-muted small mb-0">
                                            Click the avatar to upload &amp; crop a photo.<br>
                                            Optional &middot; JPG / PNG / WEBP &middot; max 5&nbsp;MB (before crop).
                                        </p>
                                    </div>
                                </div>

                                <!--
                                    Hidden file input — NO name attribute so FormData(form) never
                                    picks it up as a raw file entry. The cropped Blob is injected
                                    via fd.set() in insertUser() instead.
                                -->
                                <input type="file" id="add-profilePic" accept="image/*" class="d-none">
                            </div>

                            <!-- Name row -->
                            <div class="col-md-4">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="fname" id="add-fname" required maxlength="64">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="mname" id="add-mname" maxlength="36">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="lname" id="add-lname" required maxlength="64">
                            </div>

                            <!-- Contact -->
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="add-email" required maxlength="128">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="position" id="add-position" required maxlength="128">
                            </div>

                            <!-- Personal details -->
                            <div class="col-md-6">
                                <label class="form-label">Sex <span class="text-danger">*</span></label>
                                <select class="form-select" name="sex" id="add-sex" required>
                                    <option value="" hidden>— Select —</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birthday <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="birthday" id="add-birthday" required>
                            </div>

                            <!-- Division -->
                            <div class="col-12">
                                <label class="form-label">Division / Office <span class="text-danger">*</span></label>
                                <?= renderDivisionSelect('add-type', 'type') ?>
                            </div>

                            <!-- IMIS Role -->
                            <div class="col-md-6">
                                <label class="form-label">IMIS Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" id="add-role" required>
                                    <option value="" hidden>— Select Role —</option>
                                    <option value="superadmin">Superadmin</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>

                        </div><!-- /row -->
                    </form>
                </div><!-- /modal-body -->

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success" id="submitAddUserBtn">
                        <i class="bi bi-person-fill-add me-1"></i> Add User
                    </button>
                </div>

            </div>
        </div>
    </div>


    <!-- ===================================================================
         Edit User Modal
    ==================================================================== -->
    <div class="modal fade" id="editModal" tabindex="-1"
        aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold" id="editModalLabel">
                        <i class="bi bi-pencil-square me-1"></i>
                        <span id="editModalName">Edit User</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- ── Skeleton loading state ─────────────────────────────── -->
                <div id="editSkeleton" class="modal-body">
                    <div class="skeleton skeleton-avatar mb-3"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <div class="skeleton skeleton-line"></div>
                        </div>
                        <div class="col-4">
                            <div class="skeleton skeleton-line"></div>
                        </div>
                        <div class="col-4">
                            <div class="skeleton skeleton-line"></div>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="skeleton skeleton-line"></div>
                        </div>
                        <div class="col-6">
                            <div class="skeleton skeleton-line"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="skeleton skeleton-line"></div>
                    </div>
                    <div class="mb-2">
                        <div class="skeleton skeleton-line"></div>
                    </div>
                    <div class="mb-2">
                        <div class="skeleton skeleton-line short"></div>
                    </div>
                </div>

                <!-- ── Real form (hidden until data loads) ───────────────── -->
                <div id="editFormContent" class="d-none">
                    <input type="hidden" id="edit-id">

                    <!-- Tab nav -->
                    <div class="modal-body pb-0 pt-2">
                        <ul class="nav nav-tabs" id="editTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#tabProfile" type="button">
                                    <i class="bi bi-person me-1"></i>Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#tabAccount" type="button">
                                    <i class="bi bi-shield-check me-1"></i>Account
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#tabSecurity" type="button">
                                    <i class="bi bi-key me-1"></i>Security
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Tab panes -->
                    <div class="modal-body tab-content pt-3" id="editTabContent">

                        <!-- ── Profile Tab ─────────────────────────────────── -->
                        <div class="tab-pane fade show active" id="tabProfile" role="tabpanel">

                            <!-- Clickable avatar with camera overlay -->
                            <div class="d-flex justify-content-center mb-3">
                                <div class="profile-upload-wrap"
                                    id="profileUploadTrigger"
                                    title="Click to change photo">
                                    <img id="edit-profileImg"
                                        src="../assets/img/default-avatar.png"
                                        alt="Profile">
                                    <div class="profile-upload-overlay">
                                        <i class="bi bi-camera-fill text-white fs-4"></i>
                                    </div>
                                </div>
                                <!-- Hidden file input — triggered programmatically -->
                                <input type="file" id="edit-profilePicInput"
                                    accept="image/*" class="d-none">
                            </div>

                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label form-label-sm">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="edit-fname" maxlength="64" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label form-label-sm">Middle Name</label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="edit-mname" maxlength="36">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label form-label-sm">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="edit-lname" maxlength="64" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-sm">Sex</label>
                                    <select class="form-select form-select-sm" id="edit-sex">
                                        <option value="">— Select —</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-sm">
                                        Birthday <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control form-control-sm"
                                        id="edit-birthday" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        Email <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control form-control-sm"
                                        id="edit-email" maxlength="128" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        Position <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="edit-position" maxlength="128" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        Division / Office <span class="text-danger">*</span>
                                    </label>
                                    <?= renderDivisionSelect(
                                        'edit-type',
                                        'type',
                                        true,
                                        ' class="form-select form-select-sm"'
                                    ) ?>
                                </div>
                            </div>
                        </div><!-- /tabProfile -->

                        <!-- ── Account Tab ─────────────────────────────────── -->
                        <div class="tab-pane fade" id="tabAccount" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label form-label-sm">Username</label>
                                    <input type="text"
                                        class="form-control form-control-sm bg-light"
                                        id="edit-username" readonly>
                                    <div class="form-text">Username cannot be changed.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        IMIS Role <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-sm" id="edit-role">
                                        <option value="superadmin">Superadmin</option>
                                        <option value="admin">Admin</option>
                                        <option value="user">User</option>
                                        <option value="none" hidden>None</option>
                                    </select>
                                    <div class="form-text">Controls access to the IMIS admin portal.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm d-block">Account Status</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox"
                                            id="edit-statusToggle" role="switch">
                                        <label class="form-check-label"
                                            id="edit-statusLabel"
                                            for="edit-statusToggle">Active</label>
                                    </div>
                                    <div class="form-text">Inactive users cannot log in to any system.</div>
                                </div>
                            </div>
                        </div><!-- /tabAccount -->

                        <!-- ── Security Tab ────────────────────────────────── -->
                        <div class="tab-pane fade" id="tabSecurity" role="tabpanel">
                            <p class="text-muted small">
                                Set a new password for this user. Notify them separately after saving.
                            </p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control"
                                            id="edit-newPassword" maxlength="128"
                                            placeholder="Enter new password">
                                        <button class="btn btn-outline-secondary pw-toggle"
                                            type="button" data-target="edit-newPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-sm">
                                        Confirm Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control"
                                            id="edit-confirmPassword" maxlength="128"
                                            placeholder="Repeat new password">
                                        <button class="btn btn-outline-secondary pw-toggle"
                                            type="button" data-target="edit-confirmPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div id="pwMatchFeedback" class="form-text mt-1"></div>
                                </div>
                            </div>
                        </div><!-- /tabSecurity -->

                    </div><!-- /tab-content -->

                    <!-- Context-aware footer -->
                    <div class="modal-footer justify-content-between">
                        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Close
                        </button>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm tab-action"
                                id="saveProfileBtn" data-tab="tabProfile">
                                <i class="bi bi-floppy-fill me-1"></i> Save Profile
                            </button>
                            <button class="btn btn-primary btn-sm tab-action d-none"
                                id="saveAccountBtn" data-tab="tabAccount">
                                <i class="bi bi-floppy-fill me-1"></i> Save Account Settings
                            </button>
                            <button class="btn btn-warning btn-sm tab-action d-none"
                                id="changePasswordBtn" data-tab="tabSecurity">
                                <i class="bi bi-key-fill me-1"></i> Update Password
                            </button>
                        </div>
                    </div>

                </div><!-- /editFormContent -->
            </div>
        </div>
    </div>


    <!-- ===================================================================
         Shared Image Crop Modal
         z-index 1060 ensures it stacks above both the Add and Edit modals.
    ==================================================================== -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true"
        data-bs-backdrop="static" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">

                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-crop me-1"></i> Crop Profile Picture
                    </h6>
                    <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-0 bg-light">
                    <div class="img-container" style="max-height: 500px; width: 100%; overflow: hidden;">
                        <img id="imageToCrop" src="" alt="Image to crop"
                            style="display: block; max-width: 100%;">
                    </div>
                </div>

                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm px-4"
                        id="btnCropAndSave"
                        style="background-color: #0077b6; border-color: #0077b6;">
                        <i class="bi bi-check2-circle me-1"></i> Crop &amp; Save
                    </button>
                </div>

            </div>
        </div>
    </div>


    <?php include 'vendor_js.html' ?>
    <script src="js/users_management.js"></script>
    <?php imis_include('footer') ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>