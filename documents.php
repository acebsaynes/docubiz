<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userEmail = $_SESSION["user_email"] ?? "user@gmail.com";
$userName  = $_SESSION["user_name"]  ?? $userEmail;
$avatarInitials = strtoupper(substr($userEmail, 0, 2));
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
      background-color: #122b49;
      padding-top: 15px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
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
    @media (max-width: 900px) {
      .sidebar {
        width: 60px;
      }
      .sidebar .menu-item span {
        display: none;
      }
      .search-bar {
        width: 200px;
      }
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
        <input type="text" placeholder="Search documents...">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>
      <div class="topbar-actions">
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </button>
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
        <div class="user-profile">
          <div class="profile-avatar"><?php echo htmlspecialchars($avatarInitials); ?></div>
          <div class="profile-info">
            <span class="user-name"><?php echo htmlspecialchars($userEmail); ?></span>
            <span class="user-role"> Admin Staff </span>
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
          <a href="documents.php" class="menu-item active">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            <span>Documents</span>
          </a>
         <?php if (($_SESSION["user_role"] ?? $_SESSION["role"] ?? "user") === "admin"): ?>
<a href="staff_management.php" class="menu-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    <span>Staff Management</span>
</a>
<?php endif; ?>
          <a href="finances.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>Finances</span>
          </a>
        </nav>
      </aside>
      <main class="content-area">
        <div class="page-header">
          <h1 class="page-title">Documents</h1>
        </div>
        <div class="upload-dropzone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
          <span>Choose File or Drag & Drop to Upload</span>
        </div>
        <div class="action-buttons">
          <button class="btn-action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
            Sort
          </button>
          <button class="btn-action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            Filter
          </button>
        </div>
        <div class="documents-table-card">
          <table class="documents-table">
            <thead>
              <tr>
                <th style="width: 40px;"><input type="checkbox"></th>
                <th>File Name</th>
                <th>File Type</th>
                <th>Created By</th>
                <th>Shared To</th>
                <th>Published</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="checkbox"></td>
                <td>1 August 2026 Contract of Agreement</td>
                <td>Word Document</td>
                <td>Althea Casandra Estrella Saynes</td>
                <td>Everyone</td>
                <td>AUGUST 1, 2026</td>
              </tr>
              <tr class="row-highlight">
                <td><input type="checkbox" checked></td>
                <td>22 July Rental - Tables</td>
                <td>Word Document</td>
                <td>Jana Lei Lumbreras</td>
                <td>Everyone</td>
                <td>JULY 22, 2026</td>
              </tr>
              <tr>
                <td><input type="checkbox"></td>
                <td>Contract of Agreement Template</td>
                <td>Word Document</td>
                <td>Althea Casandra Estrella Saynes</td>
                <td>Admin Staff</td>
                <td>JULY 30, 2026</td>
              </tr>
              <tr class="row-highlight">
                <td><input type="checkbox" checked></td>
                <td>AUGUST 2024 FINANCIAL REPORT</td>
                <td>Excel Worksheet</td>
                <td>Althea Casandra Estrella Saynes</td>
                <td>Finance Managers</td>
                <td>AUGUST 28, 2024</td>
              </tr>
              <tr>
                <td><input type="checkbox"></td>
                <td>7th Birthday Catering</td>
                <td>PDF File</td>
                <td>Jana Lei Lumbreras</td>
                <td>Everyone</td>
                <td>JULY 19, 2026</td>
              </tr>
              <tr class="row-highlight">
                <td><input type="checkbox" checked></td>
                <td>2026 Quotation CvSU Main</td>
                <td>PDF File</td>
                <td>Jana Lei Lumbreras</td>
                <td>Everyone</td>
                <td>JUNE 30, 2026</td>
              </tr>
              <tr>
                <td><input type="checkbox"></td>
                <td>MARCH 2026 FINANCIAL REPORT</td>
                <td>PDF File</td>
                <td>Althea Casandra Estrella Saynes</td>
                <td>Finance Managers</td>
                <td>APRIL 3, 2026</td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>
</body>
</html>