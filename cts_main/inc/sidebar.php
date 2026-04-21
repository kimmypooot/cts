<?php
session_start(); // Start the session
$user_type = isset($_SESSION['type']) ? $_SESSION['type'] : ''; // Get user type
?>

<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">

        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="index">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
        </li>

        <!-- Applicants -->
        <li class="nav-heading">Publication Management</li>

        <li class="nav-item">
            <a class="nav-link collapsed" href="index">
                <i class="bi bi-people-fill"></i><span>Manage Bulletin of Vacant Positions</span>
            </a>
        </li>

            <!-- Position Management -->
            <li class="nav-heading">Government Agency Management</li>

            <li class="nav-item">
                <a class="nav-link collapsed" href="manage_govt_agencies">
                    <i class="bi bi-clipboard-data"></i><span>Manage Government Agencies</span>
                </a>
            </li>

    </ul>
</aside>
