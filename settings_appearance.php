
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

date_default_timezone_set("Asia/Manila");

$userId = (int) $_SESSION["user_id"];

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

$userRoleSession = strtolower(trim(
    $_SESSION["user_role"] ?? $_SESSION["role"] ?? "user"
));

$isAdmin = ($userRoleSession === "admin");
$displayRole = $isAdmin ? "Admin Staff" : "User";

function settingsEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* Default appearance preferences */
$defaults = [
    "theme" => "light",
    "sidebar_feature" => "recent_changes",
    "table_view" => "default"
];

$preferences = $defaults;
$message = "";
$messageType = "";

/* Create appearance settings table automatically */
$createTable = "
CREATE TABLE IF NOT EXISTS user_appearance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    theme VARCHAR(20) NOT NULL DEFAULT 'light',
    sidebar_feature VARCHAR(50) NOT NULL DEFAULT 'recent_changes',
    table_view VARCHAR(20) NOT NULL DEFAULT 'default',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!$conn->query($createTable)) {
    die("Unable to initialize appearance settings: " . settingsEscape($conn->error));
}

/* CSRF token */
if (empty($_SESSION["appearance_csrf"])) {
    $_SESSION["appearance_csrf"] = bin2hex(random_bytes(32));
}

/* Save preferences */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["appearance_csrf"], $submittedToken)) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";
    } else {

        $theme = $_POST["theme"] ?? "light";
        $sidebarFeature = $_POST["sidebar_feature"] ?? "recent_changes";
        $tableView = $_POST["table_view"] ?? "default";

        $allowedThemes = ["light", "dark", "system"];
        $allowedSidebarFeatures = [
            "recent_changes",
            "quick_access",
            "none"
        ];
        $allowedTableViews = ["default", "compact"];

        if (!in_array($theme, $allowedThemes, true)) {
            $theme = "light";
        }

        if (!in_array($sidebarFeature, $allowedSidebarFeatures, true)) {
            $sidebarFeature = "recent_changes";
        }

        if (!in_array($tableView, $allowedTableViews, true)) {
            $tableView = "default";
        }

        $stmt = $conn->prepare("
            INSERT INTO user_appearance_settings
                (user_id, theme, sidebar_feature, table_view)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                sidebar_feature = VALUES(sidebar_feature),
                table_view = VALUES(table_view)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "isss",
                $userId,
                $theme,
                $sidebarFeature,
                $tableView
            );

            if ($stmt->execute()) {
                $preferences = [
                    "theme" => $theme,
                    "sidebar_feature" => $sidebarFeature,
                    "table_view" => $tableView
                ];

                $message = "Appearance preferences saved successfully!";
                $messageType = "success";
            } else {
                $message = "Unable to save your preferences. Please try again.";
                $messageType = "error";
            }

            $stmt->close();
        } else {
            $message = "Unable to process your request.";
            $messageType = "error";
        }
    }
}

/* Load saved preferences */
$stmt = $conn->prepare("
    SELECT theme, sidebar_feature, table_view
    FROM user_appearance_settings
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $preferences = [
            "theme" => $row["theme"],
            "sidebar_feature" => $row["sidebar_feature"],
            "table_view" => $row["table_view"]
        ];
    }

    $stmt->close();
}

$theme = $preferences["theme"];
$sidebarFeature = $preferences["sidebar_feature"];
$tableView = $preferences["table_view"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Appearance Settings</title>

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

    .settings-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 22px 28px;
      margin-bottom: 18px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }

    .settings-card h3 {
      font-size: 1.05rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 2px;
    }

    .card-desc {
      font-size: 0.75rem;
      color: #64748b;
      margin-bottom: 18px;
    }

    .theme-options-grid {
      display: flex;
      gap: 15px;
      margin-top: 10px;
    }

    .theme-option-box {
      flex: 1;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 16px 18px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      cursor: pointer;
      background-color: #ffffff;
      transition: all 0.2s ease;
    }

    .theme-option-box.active {
      border-color: #72dbd9;
      box-shadow: 0 0 0 1px #72dbd9;
    }

    .theme-option-title {
      font-size: 0.85rem;
      font-weight: 600;
      color: #1e293b;
    }

    .radio-indicator {
      width: 18px;
      height: 18px;
      border: 2px solid #cbd5e1;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      flex-shrink: 0;
    }

    .theme-option-box.active .radio-indicator {
      border-color: #72dbd9;
      background-color: #e6fffa;
    }

    .theme-option-box.active .radio-indicator::after {
      content: '';
      width: 8px;
      height: 8px;
      background-color: #319795;
      border-radius: 50%;
    }

    .theme-option-box input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .dropdown-container {
      position: relative;
      width: 250px;
      max-width: 100%;
    }

    .custom-select-box {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      background-color: #ffffff;
      font-size: 0.82rem;
      color: #334155;
      cursor: pointer;
    }

    .custom-select-box:focus {
      outline: 2px solid #72dbd9;
      border-color: #72dbd9;
    }

    .save-button {
      background-color: #122b49;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 11px 22px;
      font-size: 0.82rem;
      font-weight: 500;
      cursor: pointer;
      transition: 0.2s;
      margin-bottom: 25px;
    }

    .save-button:hover {
      background-color: #274b73;
    }

    .status-message {
      padding: 11px 15px;
      border-radius: 8px;
      font-size: 0.8rem;
      margin-bottom: 15px;
    }

    .status-message.success {
      background: #e6f7ee;
      color: #18794e;
      border: 1px solid #b7e4ca;
    }

    .status-message.error {
      background: #fdecec;
      color: #b42318;
      border: 1px solid #f5c2c0;
    }

    /* Dark theme */
    body.dark-theme {
      background-color: #151e2b;
      color: #e2e8f0;
    }

    body.dark-theme .content-area {
      background-color: #1e293b;
    }

    body.dark-theme .settings-card {
      background-color: #263548;
      box-shadow: none;
    }

    body.dark-theme .page-title,
    body.dark-theme .settings-card h3 {
      color: #f1f5f9;
    }

    body.dark-theme .page-subtitle,
    body.dark-theme .card-desc {
      color: #b0bdcc;
    }

    body.dark-theme .theme-option-box {
      background-color: #334155;
      border-color: #526176;
    }

    body.dark-theme .theme-option-title {
      color: #f1f5f9;
    }

    body.dark-theme .custom-select-box {
      background-color: #334155;
      border-color: #526176;
      color: #f1f5f9;
    }

    body.dark-theme .status-message.success {
      background: #173c31;
      color: #a7f3d0;
      border-color: #28634d;
    }

    body.dark-theme .status-message.error {
      background: #482727;
      color: #fecaca;
      border-color: #773838;
    }

    /* System theme */
    @media (prefers-color-scheme: dark) {
      body.system-theme {
        background-color: #151e2b;
        color: #e2e8f0;
      }

      body.system-theme .content-area {
        background-color: #1e293b;
      }

      body.system-theme .settings-card {
        background-color: #263548;
        box-shadow: none;
      }

      body.system-theme .page-title,
      body.system-theme .settings-card h3 {
        color: #f1f5f9;
      }

      body.system-theme .page-subtitle,
      body.system-theme .card-desc {
        color: #b0bdcc;
      }

      body.system-theme .theme-option-box {
        background-color: #334155;
        border-color: #526176;
      }

      body.system-theme .theme-option-title {
        color: #f1f5f9;
      }

      body.system-theme .custom-select-box {
        background-color: #334155;
        border-color: #526176;
        color: #f1f5f9;
      }
    }

    /* Compact tables */
    body.compact-tables table th,
    body.compact-tables table td {
      padding: 5px 8px !important;
    }

    @media (max-width: 900px) {
      .sidebar {
        width: 60px;
      }

      .sidebar .menu-item span {
        display: none;
      }

      .content-area {
        flex-direction: column;
      }

      .settings-subsidebar {
        width: 100%;
        align-self: auto;
        display: flex;
        overflow-x: auto;
      }

      .theme-options-grid {
        flex-direction: column;
      }
    }
  </style>
</head>

<body class="<?php
    echo $theme === "dark" ? "dark-theme" :
        ($theme === "system" ? "system-theme" : "");

    if ($tableView === "compact") {
        echo " compact-tables";
    }
?>">
  <div class="app-layout">

    <header class="topbar">
      <div class="brand-container">
        <button class="hamburger-btn" type="button">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>

      <div class="search-bar">
        <input type="text" placeholder="Search settings...">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </button>
      </div>

      <div class="topbar-actions">
        <a href="settings.php" class="icon-btn" title="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l-.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
        </a>

        <button class="icon-btn" type="button" title="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>

        <div class="user-profile">
          <div class="profile-avatar">
            <?php echo settingsEscape($avatarText); ?>
          </div>
          <div class="profile-info">
            <span class="user-name">
              <?php echo settingsEscape($displayName); ?>
            </span>
            <span class="user-role">
              <?php echo settingsEscape($displayRole); ?>
            </span>
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

          <a href="documents.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

        </nav>
      </aside>

      <main class="content-area">

        <div class="settings-subsidebar">
          <a href="settings.php" class="sub-menu-item">Account</a>
          <a href="settings_notif.php" class="sub-menu-item">Notifications</a>
          <a href="settings_appearance.php" class="sub-menu-item active">Appearance</a>
          <a href="settings_secu.php" class="sub-menu-item">Security</a>
          <a href="settings_system.php" class="sub-menu-item">System</a>
          <a href="settings_about.php" class="sub-menu-item">About</a>
        </div>

        <div class="settings-main">

          <h1 class="page-title">Settings</h1>
          <p class="page-subtitle">
            Manage your account, preferences, and system configurations.
          </p>

          <?php if ($message !== ""): ?>
            <div class="status-message <?php echo settingsEscape($messageType); ?>">
              <?php echo settingsEscape($message); ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="settings_appearance.php">

            <input
              type="hidden"
              name="csrf_token"
              value="<?php echo settingsEscape($_SESSION["appearance_csrf"]); ?>"
            >

            <!-- Appearance Card Header -->
            <div class="settings-card">
              <h3>Appearance</h3>
              <p class="card-desc" style="margin-bottom:0;">
                Choose your system's appearance.
              </p>
            </div>

            <!-- Interface Theme Card -->
            <div class="settings-card">
              <h3>Interface Theme</h3>
              <p class="card-desc">Select or customize your UI theme.</p>

              <div class="theme-options-grid">

                <label class="theme-option-box <?php echo $theme === 'light' ? 'active' : ''; ?>">
                  <span class="theme-option-title">Light</span>
                  <div class="radio-indicator"></div>
                  <input
                    type="radio"
                    name="theme"
                    value="light"
                    <?php echo $theme === "light" ? "checked" : ""; ?>
                  >
                </label>

                <label class="theme-option-box <?php echo $theme === 'dark' ? 'active' : ''; ?>">
                  <span class="theme-option-title">Dark</span>
                  <div class="radio-indicator"></div>
                  <input
                    type="radio"
                    name="theme"
                    value="dark"
                    <?php echo $theme === "dark" ? "checked" : ""; ?>
                  >
                </label>

                <label class="theme-option-box <?php echo $theme === 'system' ? 'active' : ''; ?>">
                  <span class="theme-option-title">System</span>
                  <div class="radio-indicator"></div>
                  <input
                    type="radio"
                    name="theme"
                    value="system"
                    <?php echo $theme === "system" ? "checked" : ""; ?>
                  >
                </label>

              </div>
            </div>

            <!-- Sidebar Feature Card -->
            <div class="settings-card">
              <h3>Sidebar Feature</h3>
              <p class="card-desc">What shows in desktop sidebar.</p>

              <div class="dropdown-container">
                <select
                  name="sidebar_feature"
                  class="custom-select-box"
                >
                  <option value="recent_changes" <?php echo $sidebarFeature === "recent_changes" ? "selected" : ""; ?>>
                    Recent Changes
                  </option>

                  <option value="quick_access" <?php echo $sidebarFeature === "quick_access" ? "selected" : ""; ?>>
                    Quick Access
                  </option>

                  <option value="none" <?php echo $sidebarFeature === "none" ? "selected" : ""; ?>>
                    None
                  </option>
                </select>
              </div>
            </div>

            <!-- Tables View Card -->
            <div class="settings-card">
              <h3>Tables View</h3>
              <p class="card-desc">How are tables displayed in the app.</p>

              <div class="theme-options-grid" style="max-width: 66%;">

                <label class="theme-option-box <?php echo $tableView === 'default' ? 'active' : ''; ?>">
                  <span class="theme-option-title">Default</span>
                  <div class="radio-indicator"></div>
                  <input
                    type="radio"
                    name="table_view"
                    value="default"
                    <?php echo $tableView === "default" ? "checked" : ""; ?>
                  >
                </label>

                <label class="theme-option-box <?php echo $tableView === 'compact' ? 'active' : ''; ?>">
                  <span class="theme-option-title">Compact</span>
                  <div class="radio-indicator"></div>
                  <input
                    type="radio"
                    name="table_view"
                    value="compact"
                    <?php echo $tableView === "compact" ? "checked" : ""; ?>
                  >
                </label>

              </div>
            </div>

            <button type="submit" class="save-button">
              Save Changes
            </button>

          </form>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Update selected theme and table option appearance.
    document.querySelectorAll(
      'input[name="theme"], input[name="table_view"]'
    ).forEach(function(input) {

      input.addEventListener("change", function() {
        const group = this.name;

        document.querySelectorAll(
          'input[name="' + group + '"]'
        ).forEach(function(radio) {
          radio.closest(".theme-option-box").classList.remove("active");
        });

        this.closest(".theme-option-box").classList.add("active");
      });

    });

    // Preview theme immediately before saving.
    document.querySelectorAll('input[name="theme"]').forEach(function(input) {
      input.addEventListener("change", function() {
        document.body.classList.remove("dark-theme", "system-theme");

        if (this.value === "dark") {
          document.body.classList.add("dark-theme");
        } else if (this.value === "system") {
          document.body.classList.add("system-theme");
        }
      });
    });

    // Preview compact table preference.
    document.querySelectorAll('input[name="table_view"]').forEach(function(input) {
      input.addEventListener("change", function() {
        document.body.classList.toggle(
          "compact-tables",
          this.value === "compact"
        );
      });
    });
  </script>

</body>
</html>
