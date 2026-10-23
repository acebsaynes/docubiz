<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
$displayName = trim(
    $_SESSION["user_name"]
    ?? $_SESSION["full_name"]
    ?? $_SESSION["user_email"]
    ?? $_SESSION["email"]
    ?? "Jana Lei Lumbreras"
);
if ($displayName === "") {
    $displayName = "Jana Lei Lumbreras";
}
$avatarText = strtoupper(substr($displayName, 0, 2));
$userRoleSession = strtolower(trim($_SESSION["user_role"] ?? $_SESSION["role"] ?? "user"));
$isAdmin = ($userRoleSession === "admin");
$displayRole = $isAdmin ? "Admin Staff" : "User";
function settingsEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Security Settings</title>
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
      width: 380px;
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
      text-decoration: none;
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
    }
    .profile-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }
    .user-name {
      font-size: 0.82rem;
      font-weight: 500;
    }
    .user-role {
      font-size: 0.72rem;
      color: #9bb3d1;
    }
    .main-body {
      display: flex;
      flex: 1;
      align-items: stretch;
    }
    .sidebar {
      width: 210px;
      background-color: #122b49;
      padding-top: 15px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      flex-shrink: 0;
    }
    .sidebar-menu {
      display: flex;
      flex-direction: column;
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
   .content-area {
  flex: 1;
  padding: 25px 20px 0 15px;
  background-color: #d5dad9;
  display: flex;
  gap: 15px;
  align-items: stretch;
}
  .settings-subsidebar {
  width: 210px;
  background-color: #122b49;
  border-radius: 12px 12px 0 0;
  padding: 15px 0;
  align-self: stretch;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow: hidden;
  flex-shrink: 0;
}
    .sub-menu-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 22px;
      color: #b0c4de;
      text-decoration: none;
      font-size: 0.85rem;
      transition: all 0.2s;
    }
    .sub-menu-item:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.05);
    }
    .sub-menu-item.active {
      color: #ffffff;
      background-color: #274b73;
    }
    .settings-main {
      flex: 1;
      min-width: 0;
      padding-right: 10px;
    }
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
    }
    .page-subtitle {
      font-size: 0.8rem;
      color: #64748b;
      margin-bottom: 20px;
    }

    /* Layout grid for Security page */
    .security-layout-grid {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 18px;
    }
    .settings-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 20px 25px;
      margin-bottom: 18px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .settings-card h3 {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 2px;
    }
    .card-desc {
      font-size: 0.75rem;
      color: #64748b;
      margin-bottom: 15px;
    }

    /* Action Buttons */
    .btn-save-top {
      background-color: #72dbd9;
      color: #1a365d;
      border: none;
      border-radius: 8px;
      padding: 6px 20px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-cancel-top {
      background-color: #ffffff;
      color: #475569;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 6px 16px;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
    }
    .btn-pill-action {
      background-color: #e6fffa;
      color: #319795;
      border: 1.5px solid #72dbd9;
      border-radius: 20px;
      padding: 5px 16px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-pill-gray {
      background-color: #f8fafc;
      color: #475569;
      border: 1px solid #cbd5e1;
      border-radius: 20px;
      padding: 5px 14px;
      font-size: 0.75rem;
      font-weight: 500;
      cursor: pointer;
    }
    .btn-deactivate {
      background-color: #ef4444;
      color: #ffffff;
      border: none;
      border-radius: 20px;
      padding: 6px 18px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
    }

    .security-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #f1f5f9;
    }
    .security-row:last-child {
      border-bottom: none;
    }
    .security-row-info h4 {
      font-size: 0.85rem;
      font-weight: 600;
      color: #1e293b;
    }
    .security-row-info p {
      font-size: 0.72rem;
      color: #64748b;
    }

    /* Input Field style */
    .input-field-custom {
      width: 100%;
      padding: 9px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      background-color: #ffffff;
      font-size: 0.82rem;
      color: #334155;
      margin-top: 6px;
      outline: none;
    }

    /* Toggle Switch Fixed Styles */
    .switch {
      position: relative;
      display: inline-block;
      width: 46px;
      height: 24px;
      flex-shrink: 0;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background-color: #cbd5e1;
      transition: .3s;
      border-radius: 24px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #72dbd9;
    }
    input:checked + .slider:before {
      transform: translateX(22px);
    }

    @media (max-width: 1000px) {
      .security-layout-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
      .sidebar { width: 60px; }
      .sidebar .menu-item span { display: none; }
      .content-area { flex-direction: column; }
      .settings-subsidebar { width: 100%; align-self: auto; display: flex; overflow-x: auto; }
    }
  </style>
</head>
<body>
  <div class="app-layout">
    <header class="topbar">
      <div class="brand-container">
        <button class="hamburger-btn">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>
      <div class="search-bar">
        <input type="text" placeholder="Search settings...">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>
      <div class="topbar-actions">
        <a href="settings.php" class="icon-btn" title="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l-.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-.33-1.82A1.65 1.65 0 0 0 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l-.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>
        <button class="icon-btn" title="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
        <div class="user-profile">
          <div class="profile-avatar">
            <?php echo settingsEscape($avatarText); ?>
          </div>
          <div class="profile-info">
            <span class="user-name">
              <?php echo settingsEscape($displayName); ?>
            </span>
            <span class="user-role"><?php echo settingsEscape($displayRole); ?></span>
          </div>
        </div>
      </div>
    </header>
    <div class="main-body">
      <aside class="sidebar">
        <nav class="sidebar-menu">
          <a href="dashboard.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>Dashboard</span>
          </a>
          <a href="messages.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <span>Messages</span>
          </a>
          <a href="calendar.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Calendar</span>
          </a>
          <a href="projects.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <span>Projects</span>
          </a>
          <a href="inventory.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
            <span>Inventory</span>
          </a>
          <a href="documents.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            <span>Documents</span>
          </a>
          
          <?php if ($isAdmin): ?>
          <a href="staff_management.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>Staff Management</span>
          </a>
          <?php endif; ?>
                   <?php if ($isAdmin): ?>
          <a href="finances.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>Finances</span>
          </a>
          <?php endif; ?>
        </nav>
      </aside>
      <main class="content-area">
        <!-- Settings Sub-sidebar Menu -->
        <div class="settings-subsidebar">
          <a href="settings.php" class="sub-menu-item">Account</a>
          <a href="settings_notif.php" class="sub-menu-item">Notifications</a>
          <a href="settings_appearance.php" class="sub-menu-item">Appearance</a>
          <a href="settings_security.php" class="sub-menu-item active">Security</a>
          <a href="settings_system.php" class="sub-menu-item">System</a>
          <a href="settings_about.php" class="sub-menu-item">About</a>
        </div>
        
        <div class="settings-main">
          <h1 class="page-title">Settings</h1>
          <p class="page-subtitle">Manage your account, preferences, and system configurations.</p>
          
          <!-- Top Security & Privacy Header Card -->
          <div class="settings-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <h3>Security & Privacy</h3>
              <p class="card-desc" style="margin-bottom: 0;">Manage security & privacy settings to protect your account.</p>
            </div>
            <div style="display: flex; gap: 10px;">
              <button type="button" class="btn-cancel-top">Cancel</button>
              <button type="button" class="btn-save-top">Save</button>
            </div>
          </div>

          <!-- Main Grid layout for Security Content and 2FA Column -->
          <div class="security-layout-grid">
            <!-- Left Column Cards -->
            <div>
              <!-- Account Details Card -->
              <div class="settings-card">
                <h3>Account Details</h3>
                <p class="card-desc">Manage your core account security credentials.</p>
                
                <div class="security-row">
                  <div class="security-row-info">
                    <h4>Verify Email Address</h4>
                    <p>Verify your email address to confirm the credentials.</p>
                  </div>
                  <button type="button" class="btn-pill-action">VERIFIED</button>
                </div>

                <div class="security-row">
                  <div class="security-row-info">
                    <h4>Update Password</h4>
                    <p>Change your password to update & protect your account.</p>
                  </div>
                  <button type="button" class="btn-pill-gray">Change Password</button>
                </div>
              </div>

              <!-- Recovery Settings Card -->
              <div class="settings-card">
                <h3>Recovery Settings</h3>
                <p class="card-desc">Setup recovery options to secure your account.</p>
                
                <div class="security-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                  <div style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                    <div class="security-row-info">
                      <h4>Recovery Email Address</h4>
                      <p>Setup recovery email to secure your account.</p>
                    </div>
                    <button type="button" class="btn-pill-action">Save</button>
                  </div>
                  <div style="width: 100%;">
                    <label style="font-size: 0.72rem; color: #64748b; font-weight: 500;">Another Email Address</label>
                    <input type="email" class="input-field-custom" value="jvlumbreras@pcu.edu.ph">
                  </div>
                </div>

                <div class="security-row" style="margin-top: 10px;">
                  <div class="security-row-info">
                    <h4>Recovery Phone Number</h4>
                    <p>Add phone number to setup SMS recovery for your account.</p>
                  </div>
                  <button type="button" class="btn-pill-gray">Setup</button>
                </div>
              </div>

              <!-- Deactivate Account Card -->
              <div class="settings-card">
                <h3>Deactivate Account</h3>
                <p class="card-desc" style="margin-bottom: 14px;">This will delete your account.</p>
                <button type="button" class="btn-deactivate">Deactivate</button>
              </div>
            </div>

            <!-- Right Column Card (Two-factor Authentication) with Fixed Toggle Switch -->
            <div>
              <div class="settings-card">
                <h3>Two-factor Authentication</h3>
                <p class="card-desc">Enhance your account security.</p>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; gap: 10px;">
                  <div>
                    <h4 style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">Enable Authentication</h4>
                    <p style="font-size: 0.72rem; color: #64748b; margin-top: 2px; line-height: 1.3;">Enable two-factor authentication to enhance the security.</p>
                  </div>
                  <label class="switch">
                    <input type="checkbox" checked>
                    <span class="slider"></span>
                  </label>
                </div>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>
</body>
</html>