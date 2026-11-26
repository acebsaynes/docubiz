
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get session information
$userEmail = $_SESSION["user_email"] ?? $_SESSION["email"] ?? "user@gmail.com";
$userName  = $_SESSION["user_name"] ?? $_SESSION["full_name"] ?? $userEmail;

// Checks user role
$userRoleSession = strtolower(trim($_SESSION["user_role"] ?? $_SESSION["role"] ?? "user"));
$displayRole = ($userRoleSession === "admin") ? "Admin Staff" : "User";
$isAdmin = ($userRoleSession === "admin");

// Get user initials
$nameParts = preg_split('/\s+/', trim($userName));
$avatarInitials = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
    : strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Documents</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    body {
      background-color: #d8dcdb;
      color: #333;
      min-height: 100vh;
    }

    .app-layout {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .topbar {
      background-color: #122b49;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      color: #ffffff;
    }

    .brand-container {
      display: flex;
      align-items: center;
      gap: 15px;
      width: 200px;
    }

    .hamburger-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .brand-name {
      font-size: 1.4rem;
      font-weight: 600;
      letter-spacing: -0.5px;
    }

    .search-bar {
      position: relative;
      width: 450px;
      display: flex;
      align-items: center;
    }

    .search-bar input {
      width: 100%;
      padding: 8px 45px 8px 16px;
      border-radius: 20px;
      border: none;
      outline: none;
      background-color: #ffffff;
      font-size: 0.85rem;
      color: #2d3748;
    }

    .search-bar input::placeholder {
      color: #8fa0b5;
    }

    .search-btn {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: #122b49;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .icon-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
      opacity: 0.9;
    }

    .icon-btn:hover {
      opacity: 1;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: 10px;
    }

    .profile-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background-color: #2b5585;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .profile-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .user-name {
      font-size: 0.82rem;
      font-weight: 500;
      color: #ffffff;
    }

    .user-role {
      font-size: 0.72rem;
      color: #9bb3d1;
    }

    .main-body {
      display: flex;
      flex: 1;
    }

    .sidebar {
      width: 210px;
      flex-shrink: 0;
      background-color: #122b49;
      padding-top: 15px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      flex-direction: column;
    }

    .sidebar-menu {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: calc(100vh - 75px);
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 20px;
      color: #b0c4de;
      text-decoration: none;
      font-size: 0.88rem;
      transition: all 0.2s ease;
    }

    .menu-item:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.05);
    }

    .menu-item.active {
      color: #ffffff;
      background-color: #274b73;
    }

    /* Bottom sidebar logout */
    .sidebar-logout {
      margin-top: auto;
      color: #ffffff !important;
      text-decoration: none !important;
    }

    .sidebar-logout span {
      color: #ffffff !important;
    }

    .sidebar-logout:hover {
      color: #ffffff !important;
      background-color: rgba(255, 255, 255, 0.08);
    }

    .content-area {
      flex: 1;
      min-width: 0;
      padding: 25px 35px;
      background-color: #d5dad9;
    }

    .page-header {
      margin-bottom: 18px;
    }

    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
    }

    .upload-dropzone {
      background-color: transparent;
      border: 2px dashed #94a3b8;
      border-radius: 10px;
      padding: 24px;
      text-align: center;
      margin-bottom: 18px;
      cursor: pointer;
      color: #475569;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      font-size: 0.82rem;
      font-weight: 500;
    }

    .upload-dropzone svg {
      color: #475569;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      margin-bottom: 18px;
    }

    .btn-action {
      background-color: #72dbd9;
      color: #1a365d;
      border: none;
      border-radius: 18px;
      padding: 6px 16px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .documents-table-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 15px 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }

    .documents-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    .documents-table th {
      font-size: 0.7rem;
      font-weight: 600;
      color: #718096;
      text-transform: uppercase;
      padding: 12px 10px;
      border-bottom: 1px solid #edf2f7;
    }

    .documents-table td {
      padding: 12px 10px;
      font-size: 0.82rem;
      color: #2d3748;
      vertical-align: middle;
      border-bottom: 1px solid #f7fafc;
    }

    .row-highlight {
      background-color: #b2f5ea;
    }

    /* Logout confirmation popup */
    .logout-modal {
      position: fixed;
      inset: 0;
      background: rgba(18, 43, 73, 0.55);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
      padding: 15px;
    }

    .logout-modal.show {
      opacity: 1;
      visibility: visible;
    }

    .logout-modal-content {
      width: 100%;
      max-width: 400px;
      background: #ffffff;
      border-radius: 14px;
      padding: 30px;
      text-align: center;
      box-shadow: 0 10px 35px rgba(0, 0, 0, 0.2);
      transform: translateY(10px);
      transition: transform 0.25s ease;
    }

    .logout-modal.show .logout-modal-content {
      transform: translateY(0);
    }

    .logout-modal-icon {
      width: 58px;
      height: 58px;
      margin: 0 auto 16px;
      border-radius: 50%;
      background: #e8f0f8;
      color: #122b49;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logout-modal-content h2 {
      color: #122b49;
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .logout-modal-content p {
      color: #718096;
      font-size: 0.85rem;
      line-height: 1.6;
      margin-bottom: 24px;
    }

    .logout-modal-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
    }

    .logout-cancel,
    .logout-confirm {
      border-radius: 8px;
      padding: 9px 20px;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      transition: 0.2s ease;
    }

    .logout-cancel {
      background: #ffffff;
      border: 1px solid #cbd5e0;
      color: #4a5568;
    }

    .logout-cancel:hover {
      background: #f7fafc;
    }

    .logout-confirm {
      background: #122b49;
      border: 1px solid #122b49;
      color: #ffffff;
    }

    .logout-confirm:hover {
      background: #274b73;
    }

    @media (max-width: 900px) {
      .sidebar {
        width: 60px;
      }

      .sidebar .menu-item span {
        display: none;
      }

      .sidebar .menu-item {
        justify-content: center;
        padding: 12px 10px;
      }

      .search-bar {
        width: 200px;
      }
    }

    @media (max-width: 650px) {
      .topbar {
        gap: 10px;
        padding: 0 12px;
      }

      .brand-container {
        width: auto;
      }

      .search-bar {
        display: none;
      }

      .topbar-actions {
        gap: 12px;
      }

      .profile-info {
        display: none;
      }

      .content-area {
        padding: 20px 12px;
      }

      .logout-modal-actions {
        flex-direction: column-reverse;
      }

      .logout-cancel,
      .logout-confirm {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div class="app-layout">

    <header class="topbar">
      <div class="brand-container">
        <button class="hamburger-btn" type="button" aria-label="Menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>

      <div class="search-bar">
        <input type="text" placeholder="Search documents..." aria-label="Search documents">
        <button type="button" class="search-btn" aria-label="Search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </button>
      </div>

      <div class="topbar-actions">
        <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
        </a>

        <button type="button" class="icon-btn" title="Notifications" aria-label="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>

        <div class="user-profile">
          <div class="profile-avatar"><?php echo htmlspecialchars($avatarInitials, ENT_QUOTES, "UTF-8"); ?></div>
          <div class="profile-info">
            <span class="user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($displayRole, ENT_QUOTES, "UTF-8"); ?></span>
          </div>
        </div>
      </div>
    </header>

    <div class="main-body">

      <aside class="sidebar">
        <nav class="sidebar-menu">

          <a href="dashboard.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard</span>
          </a>

          <a href="messages.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Messages</span>
          </a>

          <a href="calendar.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Calendar</span>
          </a>

          <a href="projects.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Projects</span>
          </a>

          <a href="inventory.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            </svg>
            <span>Inventory</span>
          </a>

          <a href="documents.php" class="menu-item active">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            <span>Documents</span>
          </a>

          <?php if ($isAdmin): ?>
          <a href="staff_management.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Staff Management</span>
          </a>

          <a href="finances.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <span>Finances</span>
          </a>
          <?php endif; ?>

          <!-- Logout stays at the bottom of the sidebar -->
          <a href="#" class="menu-item sidebar-logout" id="logoutButton">
            <svg width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Log Out</span>
          </a>

        </nav>
      </aside>

      <main class="content-area">
        <h1 class="page-title">Documents</h1>
        <?php $communicationPage = 'documents'; require __DIR__ . '/communication_ui.php'; ?>
      </main>

    </div>
  </div>

  <!-- Logout Confirmation Modal -->
  <div class="logout-modal" id="logoutModal">
    <div class="logout-modal-content">

      <div class="logout-modal-icon">
        <svg width="28" height="28" viewBox="0 0 24 24"
             fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </div>

      <h2>Log Out?</h2>
      <p>Are you sure you want to log out of your DocuBiz account?</p>

      <div class="logout-modal-actions">
        <button type="button" class="logout-cancel" id="cancelLogout">
          Cancel
        </button>

        <button type="button" class="logout-confirm" id="confirmLogout">
          Yes, Log Out
        </button>
      </div>

    </div>
  </div>

  <script>
    // LOGOUT CONFIRMATION POPUP
    const logoutButton = document.getElementById("logoutButton");
    const logoutModal = document.getElementById("logoutModal");
    const cancelLogout = document.getElementById("cancelLogout");
    const confirmLogout = document.getElementById("confirmLogout");

    // Open popup
    logoutButton?.addEventListener("click", function (e) {
      e.preventDefault();
      logoutModal?.classList.add("show");
    });

    // Cancel logout
    cancelLogout?.addEventListener("click", function () {
      logoutModal?.classList.remove("show");
    });

    // Confirm logout
    confirmLogout?.addEventListener("click", function () {
      window.location.href = "logout.php";
    });

    // Close when clicking outside the popup
    logoutModal?.addEventListener("click", function (e) {
      if (e.target === logoutModal) {
        logoutModal.classList.remove("show");
      }
    });

    // Close when pressing Escape
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        logoutModal?.classList.remove("show");
      }
    });
  </script>

</body>
</html>
