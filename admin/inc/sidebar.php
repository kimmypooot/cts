<aside id="sidebar" class="sidebar">
  <ul class="sidebar-nav" id="sidebar-nav">

    <!-- Dashboard -->
    <li class="nav-item">
      <a class="nav-link collapsed" href="index">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
    </li>

    <!-- User Management -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#spels-nav" href="index_dashboard">
        <i class="bi bi-people"></i><span>User Management</span>
      </a>
      <ul id="spels-nav" class="nav-content" data-bs-parent="#sidebar-nav">
        <li>
          <a href="users_mgmt">
            <i class="bi bi-circle"></i><span>Manage Users</span>
          </a>
        </li>
        <li>
          <a href="projects_mgmt">
            <i class="bi bi-circle"></i><span>Manage Projects</span>
          </a>
        </li>
        <li>
          <a href="access_mgmt">
            <i class="bi bi-circle"></i><span>Roles and Permissions</span>
          </a>
        </li>
      </ul>
    </li>

    <!-- System Administration -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#spels-nav" href="index_dashboard">
        <i class="bi bi-shield-lock"></i><span>System Administration</span>
      </a>
      <ul id="spels-nav" class="nav-content" data-bs-parent="#sidebar-nav">
        <li>
          <a href="#">
            <i class="bi bi-circle"></i><span>Feedback Management</span>
          </a>
        </li>
        <li>
          <a href="user_logs">
            <i class="bi bi-circle"></i><span>Login History</span>
          </a>
        </li>
        <li>
          <a href="access_logs">
            <i class="bi bi-circle"></i><span>System Access Logs</span>
          </a>
        </li>
        <li>
          <a href="index_bkup">
            <i class="bi bi-circle"></i><span>Backup Central Database</span>
          </a>
        </li>
      </ul>
    </li>

  </ul>
</aside>