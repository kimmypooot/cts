<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/connect.php';
include_once __DIR__ . '/../imis_include.php';

// Check if user is logged in and has a valid role
if (
    empty($_SESSION['username']) ||
    empty($_SESSION['role'])
) {
    session_unset();
    session_destroy();
    header('Location: ../login');
    exit();
}

// Redirect regular users away from superadmin pages
switch ($_SESSION['role']) {
    case 'admin':
    case 'user':
        header('Location: ../index_dashboard');
        exit();
    case 'superadmin':
        // Allowed: continue
        break;
    default:
        // Unknown role: force logout
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
        .edit-overlay {
            background: rgba(0, 0, 0, 0.4);
            /* semi-transparent dark */
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }

        .profile-container:hover .edit-overlay {
            opacity: 1;
        }
    </style>

</head>

<body>
    <?php imis_include('header_js') ?>
    <?php include 'inc/sidebar.php' ?>

    <!-- Main Elements Here -->
    <main id="main" class="main">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold text-uppercase text-primary mb-0">
                        <i class="bi bi-person-fill me-2"></i> LOGIN HISTORY LOGS
                    </h5>
                </div>

                <div class="table-responsive">
                    <table id="historylogsTable" class="table table-bordered table-striped table-hover align-middle w-100" style="font-size: 14px">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle" style="width: 5%;">#</th>
                                <th class="text-center align-middle">Username</th>
                                <th class="text-center align-middle">IP Address</th>
                                <th class="text-center align-middle">Login Time</th>
                                <th class="text-center align-middle">Logout Time</th>
                                <th class="text-center align-middle">Status</th>
                                <th class="text-center align-middle">User Agent</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>

    <!---------------------------------------------------- Scripts Here ---------------------------------------------------->
    <?php include 'vendor_js.html' ?>
    <script>
        <?php include 'js/users_management.js' ?>
    </script>
    <script>
        $(document).ready(function() {
            const table = $('#historylogsTable').DataTable({
                ajax: {
                    url: 'api/fetch_login_logs.php',
                    type: 'GET',
                    dataSrc: 'data'
                },
                columns: [{
                        data: '#',
                        width: '5%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'username',
                        width: '12%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'ip_address',
                        width: '12%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'login_time',
                        width: '15%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'logout_time',
                        width: '15%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'status',
                        width: '10%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'user_agent',
                        width: '25%',
                        className: 'align-middle'
                    }
                ],
                responsive: true,
                order: [
                    [3, 'desc']
                ], // Sort by login_time
                language: {
                    emptyTable: "No login logs found"
                }
            });

            // Auto refresh every 60 seconds
            setInterval(function() {
                table.ajax.reload(null, false); // Do not reset pagination
            }, 60000);
        });
    </script>
    <!---------------------------------------------------- Footer ---------------------------------------------------->
    <?php imis_include('footer') ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>