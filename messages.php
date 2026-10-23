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
    ?? "User"
);
if ($displayName === "") {
    $displayName = "User";
}
$avatarText = strtoupper(substr($displayName, 0, 2));
// Kunin ang role mula sa session, default ay "user" kung walang naka-set
$userRoleSession = strtolower(trim($_SESSION["user_role"] ?? $_SESSION["role"] ?? "user"));
$isAdmin = ($userRoleSession === "admin");
$displayRole = ($userRoleSession === "admin") ? "Admin Staff" : "User";
function messagesEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Messages</title>
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
    .topbar-right-actions {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-left: auto;
    }
    
    .icon-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
      opacity: 0.9;
      text-decoration: none; /* Idinagdag para sa link */
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
    /* Estilo para sa Log Out button (normal font weight at may space) */
    .logout-btn {
      color: #ffffff;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 400;
      opacity: 0.9;
      transition: opacity 0.2s;
      margin-left: 5px;
    }
    .logout-btn:hover {
      opacity: 1;
      text-decoration: underline;
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
      padding: 30px 40px;
      background-color: #d5dad9;
      display: flex;
      flex-direction: column;
    }
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
      margin-bottom: 20px;
    }
    .messages-container {
      display: flex;
      gap: 25px;
      flex: 1;
      min-height: 520px;
    }
    .chat-sidebar {
      width: 320px;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .chat-search-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .chat-search-bar {
      flex: 1;
      position: relative;
      display: flex;
      align-items: center;
    }
    .chat-search-bar input {
      width: 100%;
      padding: 8px 12px 8px 36px;
      border-radius: 20px;
      border: 1px solid #cbd5e0;
      outline: none;
      background-color: #ffffff;
      font-size: 0.82rem;
      color: #2d3748;
    }
    .chat-search-bar input::placeholder {
      color: #a0aec0;
    }
    .search-icon-left {
      position: absolute;
      left: 12px;
      color: #a0aec0;
      display: flex;
      align-items: center;
    }
    .btn-new-chat {
      background-color: #72dbd9;
      border: none;
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #1a365d;
      cursor: pointer;
    }
    .chat-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .chat-item {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
      transition: all 0.2s ease;
    }
    .chat-item.active {
      background-color: #82d8d5;
    }
    .chat-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background-color: #cbd5e0;
      color: #4a5568;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 600;
      flex-shrink: 0;
    }
    .chat-info {
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .chat-name {
      font-size: 0.88rem;
      font-weight: 600;
      color: #2d3748;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .chat-preview {
      font-size: 0.72rem;
      color: #718096;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 2px;
    }
    .chat-item.active .chat-preview {
      color: #2d3748;
    }
    .chat-main {
      flex: 1;
      background-color: #ffffff;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
      padding: 20px;
    }
    .chat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 15px;
      border-bottom: 1px solid #edf2f7;
    }
    .chat-header-user {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .chat-header-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background-color: #cbd5e0;
      color: #4a5568;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      font-weight: 600;
    }
    .chat-header-info {
      display: flex;
      flex-direction: column;
    }
    .chat-header-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: #2d3748;
    }
    .chat-header-sub {
      font-size: 0.72rem;
      color: #a0aec0;
    }
    .chat-header-actions {
      display: flex;
      align-items: center;
      gap: 14px;
      color: #72dbd9;
    }
    .header-action-btn {
      background: none;
      border: none;
      color: #5ececb;
      cursor: pointer;
      display: flex;
      align-items: center;
    }
    .chat-messages-area {
      flex: 1;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      gap: 16px;
      overflow-y: auto;
    }
    .date-divider {
      text-align: center;
      font-size: 0.7rem;
      color: #a0aec0;
      margin: 10px 0;
    }
    .message-bubble {
      max-width: 65%;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 0.82rem;
      line-height: 1.4;
    }
    .message-received {
      background-color: #e2f4f3;
      color: #2d3748;
      align-self: flex-start;
      border-bottom-left-radius: 2px;
    }
    .message-sent {
      background-color: #f7fafc;
      border: 1px solid #edf2f7;
      color: #2d3748;
      align-self: flex-end;
      border-bottom-right-radius: 2px;
    }
    .chat-input-area {
      padding-top: 15px;
      border-top: 1px solid #edf2f7;
    }
    .chat-input-wrapper {
      display: flex;
      align-items: center;
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 24px;
      padding: 8px 16px;
    }
    .chat-input-wrapper input {
      flex: 1;
      border: none;
      outline: none;
      font-size: 0.85rem;
      color: #2d3748;
    }
    .chat-input-wrapper input::placeholder {
      color: #a0aec0;
    }
    .input-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .input-icon-btn {
      background: none;
      border: none;
      color: #5ececb;
      cursor: pointer;
      display: flex;
      align-items: center;
    }
    @media (max-width: 900px) {
      .messages-container {
        flex-direction: column;
      }
      .chat-sidebar {
        width: 100%;
      }
      .sidebar {
        width: 60px;
      }
      .sidebar .menu-item span {
        display: none;
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
      <div class="topbar-right-actions">
        
      
        <a href="settings.php" class="icon-btn" title="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>

        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
      
        <div class="user-profile">
          <div class="profile-avatar">
            <?php echo messagesEscape($avatarText); ?>
          </div>
          <div class="profile-info">
            <span class="user-name">
              <?php echo messagesEscape($displayName); ?>
            </span>
            <span class="user-role"><?php echo messagesEscape($displayRole); ?></span>
          </div>
        </div>
        <!-- Log Out Button na may space at walang bold -->
        <a href="logout.php" class="logout-btn">Log Out</a>
      </div>
    </header>
    <div class="main-body">
      <aside class="sidebar">
        <nav class="sidebar-menu">
          <a href="dashboard.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>Dashboard</span>
          </a>
          <a href="messages.php" class="menu-item active">
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
          
<?php if ($isAdmin): ?>
<a href="finances.php" class="menu-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
    <span>Finances</span>
</a>
<?php endif; ?>

        </nav>
      </aside>
      <main class="content-area">
        <h1 class="page-title">Messages</h1>
        <div class="messages-container">
          <div class="chat-sidebar">
            <div class="chat-search-row">
              <div class="chat-search-bar">
                <span class="search-icon-left">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" placeholder="Search messages or suppliers...">
              </div>
              <button class="btn-new-chat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
            </div>
            <div class="chat-list">
              <div class="chat-item active">
                <div class="chat-avatar">GH</div>
                <div class="chat-info">
                  <span class="chat-name">Golden Harvest Foods</span>
                  <span class="chat-preview">Good day! We'll send an update once it has been dispatched...</span>
                </div>
              </div>
              <div class="chat-item">
                <div class="chat-avatar">KT</div>
                <div class="chat-info">
                  <span class="chat-name">Kitchen Team</span>
                  <span class="chat-preview">We're out of cooking oil.</span>
                </div>
              </div>
              <div class="chat-item">
                <div class="chat-avatar">CO</div>
                <div class="chat-info">
                  <span class="chat-name">Catering Operations</span>
                  <span class="chat-preview">Updated inventory for this week.</span>
                </div>
              </div>
            </div>
          </div>
          <div class="chat-main">
            <div class="chat-header">
              <div class="chat-header-user">
                <div class="chat-header-avatar">GH</div>
                <div class="chat-header-info">
                  <span class="chat-header-title">Golden Harvest Foods</span>
                  <span class="chat-header-sub">Supplier • Active Now</span>
                </div>
              </div>
              <div class="chat-header-actions">
                <button class="header-action-btn">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </button>
                <button class="header-action-btn">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                </button>
              </div>
            </div>
            <div class="chat-messages-area">
              <div class="date-divider">August 17, 2026 9:00 AM</div>
              <div class="message-bubble message-received">
                Hi! Your order for 30 sacks of Jasmine Rice has been confirmed. Expected delivery is September 12, 2026.
              </div>
              <div class="message-bubble message-sent">
                Thank you! Please let us know once the delivery is on the way.
              </div>
              <div class="message-bubble message-received" style="height: 45px; opacity: 0.6;"></div>
            </div>
            <div class="chat-input-area">
              <div class="chat-input-wrapper">
                <input type="text" placeholder="Type a message...">
                <div class="input-actions">
                  <button class="input-icon-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                  </button>
                  <button class="input-icon-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                  </button>
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