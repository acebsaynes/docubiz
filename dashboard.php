
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$userId = (int) $_SESSION["user_id"];

/*
 * Always check the user's role directly from the database.
 * This prevents relying only on a session role.
 */
$roleStmt = $conn->prepare(
    "SELECT full_name, email, role
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$roleStmt) {
    error_log("Dashboard role check prepare failed: " . $conn->error);
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$roleStmt->bind_param("i", $userId);

if (!$roleStmt->execute()) {
    error_log("Dashboard role check failed: " . $roleStmt->error);
    $roleStmt->close();
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$roleStmt->bind_result($dbFullName, $dbEmail, $dbRole);
$userFound = $roleStmt->fetch();
$roleStmt->close();

if (!$userFound || !in_array($dbRole, ["admin", "user"], true)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/* Keep the session information synchronized with the database. */
$_SESSION["user_role"] = $dbRole;
$_SESSION["role"] = $dbRole;
$_SESSION["user_name"] = $dbFullName;
$_SESSION["full_name"] = $dbFullName;
$_SESSION["user_email"] = $dbEmail;

$isAdmin = ($dbRole === "admin");

$taskMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["task_action"] ?? "";

    if ($action === "add_task") {
        $taskTitle = trim($_POST["task_title"] ?? "");
        $deadline = trim($_POST["deadline"] ?? "");

        $dateObject = DateTime::createFromFormat("!Y-m-d", $deadline);
        $validDate = $dateObject && $dateObject->format("Y-m-d") === $deadline;

        if ($taskTitle === "" || strlen($taskTitle) > 255 || !$validDate) {
            $_SESSION["task_message"] = "Please enter a valid task title and deadline.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO tasks (user_id, task_title, deadline)
                 VALUES (?, ?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param("iss", $userId, $taskTitle, $deadline);

                if ($stmt->execute()) {
                    $_SESSION["task_message"] = "Task added successfully!";
                } else {
                    $_SESSION["task_message"] = "Unable to save the task. Please try again.";
                }

                $stmt->close();
            } else {
                $_SESSION["task_message"] = "Unable to save the task. Please check your database.";
            }
        }

        header("Location: dashboard.php");
        exit;
    }

    if ($action === "delete_task") {
        $taskId = (int) ($_POST["task_id"] ?? 0);

        if ($taskId > 0) {
            $stmt = $conn->prepare(
                "DELETE FROM tasks WHERE id = ? AND user_id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("ii", $taskId, $userId);
                $stmt->execute();

                $_SESSION["task_message"] = $stmt->affected_rows > 0
                    ? "Task deleted successfully!"
                    : "Task could not be found.";

                $stmt->close();
            }
        }

        header("Location: dashboard.php");
        exit;
    }
}

/* One-time message after adding or deleting a task. */
if (isset($_SESSION["task_message"])) {
    $taskMessage = $_SESSION["task_message"];
    unset($_SESSION["task_message"]);
}

/* Get the logged-in user's tasks. */
$userTasks = [];

$stmt = $conn->prepare(
    "SELECT id, task_title, deadline
     FROM tasks
     WHERE user_id = ?
     ORDER BY deadline ASC, id DESC"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $tasksResult = $stmt->get_result();

    while ($task = $tasksResult->fetch_assoc()) {
        $userTasks[] = $task;
    }

    $stmt->close();
}

/* Get upcoming events from the database. */
$upcomingEvents = [];

$sql = "SELECT event_name, event_date, start_time, event_type,
               location, description, notes
        FROM events
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC, start_time ASC
        LIMIT 5";

$eventsResult = $conn->query($sql);

if ($eventsResult) {
    while ($event = $eventsResult->fetch_assoc()) {
        $upcomingEvents[] = $event;
    }
}

/* Get logged-in user's display name. */
$displayName = trim($_SESSION["user_name"] ?? "");

if ($displayName === "") {
    $displayName = trim($_SESSION["full_name"] ?? "");
}

if ($displayName === "") {
    $displayName = trim($_SESSION["user_email"] ?? "");
}

if ($displayName === "") {
    $displayName = "User";
}

/* Generate avatar initials. */
$nameParts = preg_split('/\s+/', $displayName);
$avatarText = "";

if (count($nameParts) >= 2) {
    $avatarText = strtoupper(
        mb_substr($nameParts[0], 0, 1, "UTF-8") .
        mb_substr($nameParts[count($nameParts) - 1], 0, 1, "UTF-8")
    );
} else {
    $avatarText = strtoupper(
        mb_substr($displayName, 0, 2, "UTF-8")
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Dashboard</title>
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
      font-size: 0.9rem;
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
    .logout-link {
      color: #ffffff;
      text-decoration: none;
      font-size: 0.8rem;
      opacity: 0.9;
      white-space: nowrap;
    }
    .logout-link:hover {
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
    }
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
      margin-bottom: 25px;
    }
    .dashboard-grid {
      display: flex;
      gap: 25px;
    }
    .left-column {
      flex: 2;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .right-column {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .post-box {
      background-color: #ffffff;
      border-radius: 12px;
      border: none;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }
    .post-tabs {
      display: flex;
      gap: 25px;
      border-bottom: 1px solid #edf2f7;
      padding-bottom: 12px;
      margin-bottom: 18px;
    }
    .tab-btn {
      background: none;
      border: none;
      font-size: 0.78rem;
      font-weight: 600;
      color: #718096;
      cursor: pointer;
      padding-bottom: 4px;
    }
    .tab-btn.active {
      color: #2b6cb0;
      border-bottom: 2px solid #2b6cb0;
    }
    .post-input-container input {
      width: 100%;
      border: none;
      outline: none;
      background: transparent;
      font-size: 0.9rem;
      color: #4a5568;
    }
    .card {
      background: #ffffff;
      border-radius: 12px;
      padding: 22px 25px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }
    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }
    .card-header h2 {
      font-size: 1rem;
      font-weight: 600;
      color: #2d3748;
    }
    .view-all {
      font-size: 0.8rem;
      color: #63b3ed;
      text-decoration: none;
    }
    .events-list {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .event-item {
      border-bottom: 1px solid #edf2f7;
      padding-bottom: 15px;
    }
    .event-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .event-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }
    .event-date {
      font-size: 0.75rem;
      font-weight: 500;
    }
    .date-purple { color: #805ad5; }
    .date-orange { color: #dd6b20; }
    .date-green { color: #38a169; }
    .options-btn {
      background: none;
      border: none;
      color: #a0aec0;
      cursor: pointer;
      font-size: 0.8rem;
    }
    .event-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: #2d3748;
    }
    .event-desc {
      font-size: 0.8rem;
      color: #718096;
      margin: 2px 0 6px 0;
    }
    .event-meta {
      font-size: 0.75rem;
      color: #a0aec0;
    }
    .calendar-card {
      padding: 18px 20px;
    }
    .calendar-header {
      margin-bottom: 15px;
    }
    .cal-day {
      font-size: 1.1rem;
      font-weight: 600;
      color: #2d3748;
    }
    .cal-date {
      font-size: 0.75rem;
      color: #a0aec0;
    }
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px 4px;
      text-align: center;
      font-size: 0.72rem;
    }
    .day-label {
      font-weight: 600;
      color: #718096;
      padding-bottom: 5px;
    }
    .date-cell {
      color: #4a5568;
      padding: 4px 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .active-date {
      background-color: #319795;
      color: #ffffff;
      border-radius: 50%;
      width: 22px;
      height: 22px;
      margin: 0 auto;
    }
    .highlighted-date {
      border-bottom: 2px solid #e53e3e;
      font-weight: 600;
    }
    .underlined-date {
      border-bottom: 2px solid #3182ce;
    }
    .add-task-btn {
      background: none;
      border: none;
      font-size: 1.2rem;
      color: #2d3748;
      cursor: pointer;
    }
    .tasks-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .task-item {
      display: flex;
      flex-direction: column;
      padding-bottom: 12px;
      border-bottom: 1px solid #edf2f7;
    }
    .task-item:last-child {
      padding-bottom: 0;
      border-bottom: none;
    }
    .task-title {
      font-size: 0.88rem;
      font-weight: 600;
      color: #2d3748;
      overflow-wrap: anywhere;
    }
    .task-deadline {
      font-size: 0.72rem;
      color: #a0aec0;
    }
    .task-add-form {
      display: none;
      margin-bottom: 18px;
      padding: 14px;
      background: #f7fafc;
      border: 1px solid #edf2f7;
      border-radius: 10px;
    }
    .task-add-form.show {
      display: block;
    }
    .task-add-form label {
      display: block;
      margin-bottom: 5px;
      color: #4a5568;
      font-size: 0.78rem;
      font-weight: 500;
    }
    .task-add-form input {
      width: 100%;
      padding: 9px 10px;
      margin-bottom: 12px;
      border: 1px solid #cbd5e0;
      border-radius: 6px;
      outline: none;
      font-size: 0.8rem;
      background: #ffffff;
    }
    .task-add-form input:focus {
      border-color: #319795;
    }
    .task-form-actions {
      display: flex;
      gap: 8px;
    }
    .task-save-btn,
    .task-cancel-btn {
      padding: 7px 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.76rem;
    }
    .task-save-btn {
      background: #122b49;
      color: #ffffff;
    }
    .task-cancel-btn {
      background: #e2e8f0;
      color: #2d3748;
    }
    .task-message {
      margin-bottom: 14px;
      color: #2f855a;
      font-size: 0.78rem;
    }
    .task-item-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
    }
    .task-delete-btn {
      flex-shrink: 0;
      padding: 3px 7px;
      border: none;
      border-radius: 5px;
      background: #fff5f5;
      color: #c53030;
      cursor: pointer;
      font-size: 0.72rem;
    }
    .task-delete-btn:hover {
      background: #fed7d7;
    }
    .task-empty {
      color: #a0aec0;
      font-size: 0.8rem;
    }
    @media (max-width: 900px) {
      .dashboard-grid {
        flex-direction: column;
      }
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
        <button class="hamburger-btn" type="button">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>
      <div class="search-bar">
        <input type="text" placeholder="">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>
      <div class="topbar-actions">
        <a href="settings.php" class="icon-btn" aria-label="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0 .33-1.82A1.65 1.65 0 0 0 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 7a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V1a2 2 0 0 1 2-1 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>
        <button class="icon-btn" type="button" aria-label="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
        <div class="user-profile">
          <div class="profile-avatar">
            <?= htmlspecialchars($avatarText, ENT_QUOTES, "UTF-8") ?>
          </div>
          <div class="profile-info">
            <span class="user-name">
              <?= htmlspecialchars($displayName, ENT_QUOTES, "UTF-8") ?>
            </span>
            <span class="user-role"><?= $isAdmin ? "Admin Staff" : "User" ?></span>
          </div>
        </div>
        <a href="logout.php" class="logout-link">Log Out</a>
      </div>
    </header>
    <div class="main-body">
      <aside class="sidebar">
        <nav class="sidebar-menu">
          <a href="dashboard.php" class="menu-item active">
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

          <a href="finances.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>Finances</span>
          </a>
        </nav>
      </aside>
      <main class="content-area">
        <h1 class="page-title">Dashboard</h1>
        <div class="dashboard-grid">
          <div class="left-column">
            <div class="post-box">
              <div class="post-tabs">
                <button class="tab-btn active" type="button">MESSAGE</button>
                <button class="tab-btn" type="button">EVENT</button>
                <button class="tab-btn" type="button">POLL</button>
                <button class="tab-btn" type="button">FILE</button>
              </div>
              <div class="post-input-container">
                <input type="text" placeholder="Send a message...">
              </div>
            </div>
            <div class="card events-card">
              <div class="card-header">
                <h2>Upcoming Events</h2>
                <a href="calendar.php" class="view-all">View All</a>
              </div>
              <div class="events-list">
                <?php if (!empty($upcomingEvents)): ?>
                  <?php
                    $eventColors = ["date-purple", "date-orange", "date-green"];
                    foreach ($upcomingEvents as $index => $event):
                      $eventDate = date("F j, Y", strtotime($event["event_date"]));
                      $eventTime = "";

                      if (!empty($event["start_time"])) {
                          $eventTime = date("g:i A", strtotime($event["start_time"]));
                      }

                      $eventDescription = trim($event["description"] ?? "");

                      if ($eventDescription === "") {
                          $eventDescription = trim($event["event_type"] ?? "");
                      }

                      $eventLocation = trim($event["location"] ?? "");
                      $eventColor = $eventColors[$index % count($eventColors)];
                  ?>
                    <div class="event-item">
                      <div class="event-top">
                        <span class="event-date <?= $eventColor ?>">
                          ● <?= htmlspecialchars($eventDate, ENT_QUOTES, "UTF-8") ?>
                          <?= $eventTime !== "" ? " - " . htmlspecialchars($eventTime, ENT_QUOTES, "UTF-8") : "" ?>
                        </span>
                      </div>
                      <h3 class="event-title">
                        <?= htmlspecialchars($event["event_name"], ENT_QUOTES, "UTF-8") ?>
                      </h3>
                      <p class="event-desc">
                        <?= htmlspecialchars(
                            $eventDescription !== "" ? $eventDescription : "No description provided.",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                      </p>
                      <div class="event-meta">
                        <?= htmlspecialchars(
                            $eventLocation !== "" ? $eventLocation : "Location not specified",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="event-item">
                    <h3 class="event-title">No upcoming events</h3>
                    <p class="event-desc">Your upcoming calendar events will appear here.</p>
                    <div class="event-meta">Add an event through the Calendar page.</div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="right-column">
            <div class="card calendar-card">
              <div class="calendar-header">
                <div class="cal-day">Monday</div>
                <div class="cal-date">September 7, 2026</div>
              </div>
              <div class="calendar-grid">
                <span class="day-label">SUN</span>
                <span class="day-label">MON</span>
                <span class="day-label">TUE</span>
                <span class="day-label">WED</span>
                <span class="day-label">THU</span>
                <span class="day-label">FRI</span>
                <span class="day-label">SAT</span>
                <span class="date-cell empty"></span>
                <span class="date-cell">1</span>
                <span class="date-cell">2</span>
                <span class="date-cell">3</span>
                <span class="date-cell">4</span>
                <span class="date-cell">5</span>
                <span class="date-cell">6</span>
                <span class="date-cell">7</span>
                <span class="date-cell active-date">8</span>
                <span class="date-cell">9</span>
                <span class="date-cell">10</span>
                <span class="date-cell">11</span>
                <span class="date-cell">12</span>
                <span class="date-cell">13</span>
                <span class="date-cell">14</span>
                <span class="date-cell">15</span>
                <span class="date-cell">16</span>
                <span class="date-cell">17</span>
                <span class="date-cell">18</span>
                <span class="date-cell highlighted-date">19</span>
                <span class="date-cell">20</span>
                <span class="date-cell">21</span>
                <span class="date-cell underlined-date">22</span>
                <span class="date-cell">23</span>
                <span class="date-cell">24</span>
                <span class="date-cell">25</span>
                <span class="date-cell">26</span>
                <span class="date-cell">27</span>
                <span class="date-cell">28</span>
                <span class="date-cell">29</span>
                <span class="date-cell">30</span>
              </div>
            </div>
            <div class="card tasks-card">
              <div class="card-header">
                <h2>Tasks</h2>
                <button
                  class="add-task-btn"
                  type="button"
                  id="addTaskButton"
                  aria-label="Add task"
                  aria-expanded="false"
                >+</button>
              </div>
              <form
                method="POST"
                action="dashboard.php"
                class="task-add-form"
                id="taskAddForm"
              >
                <input type="hidden" name="task_action" value="add_task">
                <label for="taskTitle">Task title</label>
                <input
                  type="text"
                  id="taskTitle"
                  name="task_title"
                  maxlength="255"
                  placeholder="Enter task name"
                  required
                >
                <label for="taskDeadline">Deadline</label>
                <input
                  type="date"
                  id="taskDeadline"
                  name="deadline"
                  required
                >
                <div class="task-form-actions">
                  <button type="submit" class="task-save-btn">Save Task</button>
                  <button type="button" class="task-cancel-btn" id="cancelTaskButton">
                    Cancel
                  </button>
                </div>
              </form>
              <?php if ($taskMessage !== ""): ?>
                <p class="task-message">
                  <?= htmlspecialchars($taskMessage, ENT_QUOTES, "UTF-8") ?>
                </p>
              <?php endif; ?>
              <div class="tasks-list">
                <?php if (!empty($userTasks)): ?>
                  <?php foreach ($userTasks as $task): ?>
                    <div class="task-item">
                      <div class="task-item-row">
                        <div>
                          <h3 class="task-title">
                            <?= htmlspecialchars($task["task_title"], ENT_QUOTES, "UTF-8") ?>
                          </h3>
                          <span class="task-deadline">
                            Deadline:
                            <?= htmlspecialchars(
                                date("F j, Y", strtotime($task["deadline"])),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                          </span>
                        </div>
                        <form
                          method="POST"
                          action="dashboard.php"
                          onsubmit="return confirm('Delete this task?');"
                        >
                          <input type="hidden" name="task_action" value="delete_task">
                          <input
                            type="hidden"
                            name="task_id"
                            value="<?= (int) $task["id"] ?>"
                          >
                          <button type="submit" class="task-delete-btn">
                            Delete
                          </button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="task-empty">No tasks yet. Click + to add your first task!</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script>
    const addTaskButton = document.getElementById("addTaskButton");
    const taskAddForm = document.getElementById("taskAddForm");
    const cancelTaskButton = document.getElementById("cancelTaskButton");
    const taskTitleInput = document.getElementById("taskTitle");
    const taskDeadlineInput = document.getElementById("taskDeadline");

    addTaskButton.addEventListener("click", function () {
      const isOpen = taskAddForm.classList.toggle("show");
      addTaskButton.setAttribute("aria-expanded", String(isOpen));

      if (isOpen) {
        taskTitleInput.focus();
      }
    });

    cancelTaskButton.addEventListener("click", function () {
      taskAddForm.classList.remove("show");
      addTaskButton.setAttribute("aria-expanded", "false");
      taskAddForm.reset();
    });

    const today = new Date();

    const localToday = [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, "0"),
      String(today.getDate()).padStart(2, "0")
    ].join("-");

    taskDeadlineInput.min = localToday;
  </script>
</body>
</html>
