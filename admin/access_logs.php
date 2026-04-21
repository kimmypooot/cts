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
                        <i class="bi bi-grid-fill me-2"></i> SYSTEM ACCESS LOGS
                    </h5>
                </div>

                <div class="table-responsive">
                    <table id="accessLogsTable" class="table table-bordered table-striped table-hover align-middle w-100" style="font-size: 14px">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle" style="width: 5%;">#</th>
                                <th class="text-center align-middle">Username</th>
                                <th class="text-center align-middle">IP Address</th>
                                <th class="text-center align-middle">System</th>
                                <th class="text-center align-middle">Role</th>
                                <th class="text-center align-middle">Accessed At</th>
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
            const table = $('#accessLogsTable').DataTable({
                ajax: {
                    url: 'api/fetch_access_logs.php',
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
                        data: 'system',
                        width: '10%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'role',
                        width: '9%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'accessed_at',
                        width: '15%',
                        className: 'text-center align-middle'
                    },
                    {
                        data: 'user_agent',
                        width: '27%',
                        className: 'align-middle'
                    }
                ],
                columnDefs: [
                    // Allow HTML in username, role, system, and user_agent columns
                    {
                        targets: [1, 2, 3, 4, 6],
                        render: $.fn.dataTable.render.text() === undefined ?
                            undefined : function(data) {
                                return data;
                            }
                    }
                ],
                responsive: true,
                order: [
                    [5, 'desc']
                ], // Sort by accessed_at descending
                language: {
                    emptyTable: "No system access logs found"
                }
            });

            // Auto-refresh every 60 seconds without resetting pagination
            setInterval(function() {
                table.ajax.reload(null, false);
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