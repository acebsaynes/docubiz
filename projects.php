
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$userId = (int) $_SESSION["user_id"];

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

function projectsEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectProjects() {
    header("Location: projects.php");
    exit;
}

$message = "";
$error = "";

/* ADD, UPDATE, AND DELETE PROJECTS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "update") {
        $projectName = trim($_POST["project_name"] ?? "");
        $clientName = trim($_POST["client_name"] ?? "");
        $eventType = trim($_POST["event_type"] ?? "Catering Event");
        $location = trim($_POST["location"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $startDate = $_POST["start_date"] ?? "";
        $dueDate = $_POST["due_date"] ?? "";
        $eventTime = $_POST["event_time"] ?? "";
        $guestCount = $_POST["guest_count"] ?? "";
        $status = $_POST["status"] ?? "Planned";
        $progress = filter_var(
            $_POST["progress"] ?? 0,
            FILTER_VALIDATE_INT
        );

        $validStatuses = ["Planned", "In Progress", "Completed", "On Hold"];

        if (
            $projectName === "" ||
            !in_array($status, $validStatuses, true) ||
            $progress === false ||
            $progress < 0 ||
            $progress > 100
        ) {
            $error = "Please enter a project name and valid project details.";
        } elseif (
            ($startDate !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ||
            ($dueDate !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) ||
            ($eventTime !== "" && !preg_match('/^\d{2}:\d{2}$/', $eventTime)) ||
            ($guestCount !== "" && (!ctype_digit($guestCount) || (int)$guestCount < 0))
        ) {
            $error = "Please check the date, time, and guest count.";
        } else {
            if ($startDate === "") $startDate = null;
            if ($dueDate === "") $dueDate = null;
            if ($eventTime === "") $eventTime = null;
            if ($guestCount === "") $guestCount = null;
            if ($clientName === "") $clientName = null;
            if ($location === "") $location = null;
            if ($description === "") $description = null;

            if ($action === "add") {
                $stmt = $conn->prepare(
                    "INSERT INTO projects
                    (user_id, project_name, client_name, description,
                     start_date, due_date, status, event_type, location,
                     progress, guest_count, event_time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param(
                        "issssssssiss",
                        $userId,
                        $projectName,
                        $clientName,
                        $description,
                        $startDate,
                        $dueDate,
                        $status,
                        $eventType,
                        $location,
                        $progress,
                        $guestCount,
                        $eventTime
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        redirectProjects();
                    } else {
                        $error = "Unable to save the project. Please try again.";
                    }
                    $stmt->close();
                } else {
                    $error = "Unable to prepare the project request.";
                }
            } else {
                $projectId = filter_input(INPUT_POST, "project_id", FILTER_VALIDATE_INT);

                if (!$projectId) {
                    $error = "Invalid project selected.";
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE projects SET
                         project_name = ?, client_name = ?, description = ?,
                         start_date = ?, due_date = ?, status = ?,
                         event_type = ?, location = ?, progress = ?,
                         guest_count = ?, event_time = ?
                         WHERE id = ? AND user_id = ?"
                    );

                    if ($stmt) {
                        $stmt->bind_param(
                            "ssssssssissii",
                            $projectName,
                            $clientName,
                            $description,
                            $startDate,
                            $dueDate,
                            $status,
                            $eventType,
                            $location,
                            $progress,
                            $guestCount,
                            $eventTime,
                            $projectId,
                            $userId
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            redirectProjects();
                        } else {
                            $error = "Unable to update the project.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Unable to prepare the update request.";
                    }
                }
            }
        }
    } elseif ($action === "delete") {
        $projectId = filter_input(INPUT_POST, "project_id", FILTER_VALIDATE_INT);

        if ($projectId) {
            $stmt = $conn->prepare(
                "DELETE FROM projects WHERE id = ? AND user_id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("ii", $projectId, $userId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirectProjects();
                } else {
                    $error = "Unable to delete the project.";
                }
                $stmt->close();
            } else {
                $error = "Unable to prepare the delete request.";
            }
        } else {
            $error = "Invalid project selected.";
        }
    }
}

/* GET PROJECTS FOR THE LOGGED-IN USER */
$projects = [];

$stmt = $conn->prepare(
    "SELECT id, project_name, client_name, description, start_date,
            due_date, status, event_type, location, progress,
            guest_count, event_time
     FROM projects
     WHERE user_id = ?
     ORDER BY COALESCE(due_date, start_date) DESC, id DESC"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }

    $stmt->close();
}

$totalProjects = count($projects);
$ongoingProjects = 0;
$upcomingProjects = 0;
$completedProjects = 0;

foreach ($projects as $project) {
    if ($project["status"] === "In Progress") {
        $ongoingProjects++;
    } elseif ($project["status"] === "Planned") {
        $upcomingProjects++;
    } elseif ($project["status"] === "Completed") {
        $completedProjects++;
    }
}

function projectDate($date) {
    if (!$date) return "Not set";
    return date("F j, Y", strtotime($date));
}

function projectStatusClass($status) {
    switch ($status) {
        case "Completed": return "status-completed";
        case "In Progress": return "status-ongoing";
        case "On Hold": return "status-hold";
        default: return "status-badge-upcoming";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DocuBiz - Projects</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
body{background-color:#d8dcdb;color:#333;min-height:100vh}
.app-layout{display:flex;flex-direction:column;min-height:100vh}
.topbar{background-color:#122b49;height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;color:#fff}
.brand-container{display:flex;align-items:center;gap:15px;width:200px}
.hamburger-btn,.icon-btn,.search-btn{background:none;border:none;color:#fff;cursor:pointer;display:flex;align-items:center}
.brand-name{font-size:1.4rem;font-weight:600;letter-spacing:-.5px}
.search-bar{position:relative;width:450px;display:flex;align-items:center}
.search-bar input{width:100%;padding:8px 45px 8px 16px;border-radius:20px;border:none;outline:none;background:#fff;font-size:.85rem;color:#2d3748}
.search-bar input::placeholder{color:#8fa0b5}
.search-btn{position:absolute;right:12px;color:#122b49}
.topbar-actions{display:flex;align-items:center;gap:18px}
.icon-btn{opacity:.9}
.icon-btn:hover{opacity:1}
.user-profile{display:flex;align-items:center;gap:10px;margin-left:10px}
.profile-avatar{width:34px;height:34px;border-radius:50%;background:#2b5585;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600}
.profile-info{display:flex;flex-direction:column;line-height:1.2}
.user-name{font-size:.82rem;font-weight:500}
.user-role{font-size:.72rem;color:#9bb3d1}
.main-body{display:flex;flex:1}
.sidebar{width:210px;flex-shrink:0;background:#122b49;padding-top:15px;border-top:1px solid rgba(255,255,255,.08)}
.sidebar-menu{display:flex;flex-direction:column}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#b0c4de;text-decoration:none;font-size:.88rem;transition:all .2s ease}
.menu-item:hover{color:#fff;background:rgba(255,255,255,.05)}
.menu-item.active{color:#fff;background:#274b73}
.content-area{flex:1;min-width:0;padding:25px 35px;background:#d5dad9}
.page-header{margin-bottom:18px}
.page-title{font-size:1.8rem;font-weight:600;color:#2a343d;line-height:1.1}
.page-subtitle{font-size:.82rem;color:#637381;margin-top:4px}
.projects-layout{display:flex;gap:20px}
.main-projects-section{flex:2.2;min-width:0;display:flex;flex-direction:column;gap:18px}
.right-sidebar-section{flex:1;min-width:240px;display:flex;flex-direction:column;gap:18px}
.metrics-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.metric-card{background:#fff;border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 8px rgba(0,0,0,.03)}
.metric-icon-box{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.bg-blue{background:#e0f2fe;color:#0284c7}.bg-green{background:#dcfce7;color:#16a34a}.bg-orange{background:#ffedd5;color:#ea580c}.bg-red{background:#ffe4e6;color:#e11d48}
.metric-data{display:flex;flex-direction:column}
.metric-value{font-size:1.15rem;font-weight:700;color:#1e293b;line-height:1}
.metric-label{font-size:.7rem;color:#64748b;margin-top:3px}
.projects-card,.card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 4px 15px rgba(0,0,0,.04)}
.project-tabs{display:flex;gap:25px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-bottom:15px;flex-wrap:wrap}
.tab-btn{background:none;border:none;font-size:.85rem;font-weight:600;color:#64748b;cursor:pointer;padding-bottom:6px}
.tab-btn.active{color:#1e293b;border-bottom:2px solid #0284c7}
.table-filter-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:15px;flex-wrap:wrap}
.table-search-input{position:relative;flex:1;max-width:320px}
.table-search-input input{width:100%;padding:6px 30px 6px 12px;border:1px solid #cbd5e0;border-radius:16px;font-size:.78rem;outline:none}
.table-search-icon{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#a0aec0;display:flex}
.filters-group{display:flex;gap:8px;flex-wrap:wrap}
.filter-select-wrapper{display:flex;flex-direction:column}
.filter-label{font-size:.58rem;color:#64748b;margin-bottom:2px}
.filter-select{padding:4px 18px 4px 8px;border:1px solid #cbd5e0;border-radius:6px;font-size:.68rem;color:#334155;outline:none;background-color:#fff}
.table-scroll{width:100%;overflow-x:auto}
.projects-table{width:100%;border-collapse:collapse;text-align:left;min-width:760px}
.projects-table th{font-size:.72rem;font-weight:600;color:#475569;padding:10px 8px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.projects-table td{padding:10px 8px;font-size:.78rem;color:#334155;vertical-align:middle;border-bottom:1px solid #f1f5f9}
.row-highlight{background:#80d3ce}
.project-name-cell{display:flex;flex-direction:column}
.project-title{font-weight:600;font-size:.82rem;color:#1e293b}
.project-location{font-size:.68rem;color:#94a3b8}
.event-type-badge{background:#e9d5ff;color:#6b21a8;font-size:.65rem;font-weight:600;padding:4px 8px;border-radius:4px;display:inline-block;text-align:center;line-height:1.3}
.status-badge-upcoming,.status-completed,.status-ongoing,.status-hold{font-size:.68rem;font-weight:600;padding:3px 10px;border-radius:4px;display:inline-block;white-space:nowrap}
.status-badge-upcoming{background:#fde68a;color:#92400e}
.status-completed{background:#dcfce7;color:#166534}
.status-ongoing{background:#dbeafe;color:#1d4ed8}
.status-hold{background:#fee2e2;color:#991b1b}
.progress-box{display:flex;flex-direction:column;gap:3px;width:100px}
.progress-text{font-size:.68rem;font-weight:600;color:#475569}
.progress-bar-bg{width:100%;height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden}
.progress-bar-fill{height:100%;background:#38bdf8;border-radius:3px}
.action-buttons{display:flex;gap:6px}
.small-btn{border:0;border-radius:5px;padding:5px 8px;font-size:.68rem;cursor:pointer;font-weight:600}
.edit-btn{background:#e0f2fe;color:#0369a1}.delete-btn{background:#fee2e2;color:#b91c1c}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}
.card-header h2{font-size:.95rem;font-weight:600;color:#1e293b}
.view-all{font-size:.75rem;color:#38bdf8;text-decoration:none}
.upcoming-projects-list{display:flex;flex-direction:column;gap:14px}
.upcoming-item{display:flex;align-items:center;gap:12px}
.date-badge{width:44px;height:44px;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:700;color:#1e293b;line-height:1;flex-shrink:0}
.date-badge .month{font-size:.65rem}.date-badge .day{font-size:.9rem;margin-top:2px}
.badge-purple{background:#d8b4fe}.badge-red{background:#fca5a5}.badge-green{background:#86efac}
.item-details h3{font-size:.85rem;font-weight:600;color:#1e293b}
.item-details .meta,.item-details .location{font-size:.68rem;color:#64748b;margin-top:2px}
.reminders-list{display:flex;flex-direction:column;gap:10px}
.reminder-item{display:flex;align-items:center;gap:10px}
.reminder-dot{width:14px;height:14px;border-radius:50%;background:#86efac;flex-shrink:0}
.reminder-info h4{font-size:.78rem;font-weight:600;color:#334155}
.reminder-info p{font-size:.65rem;color:#94a3b8}
.primary-btn{background:#122b49;color:#fff;border:none;border-radius:7px;padding:9px 14px;font-size:.78rem;font-weight:600;cursor:pointer}
.primary-btn:hover{background:#274b73}
.notice{padding:10px 13px;border-radius:8px;margin-bottom:15px;font-size:.8rem}
.notice.error{background:#fee2e2;color:#991b1b}
.empty-row{text-align:center;padding:24px!important;color:#64748b!important}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:center;justify-content:center;padding:18px}
.modal-overlay.open{display:flex}
.project-modal{background:#fff;width:100%;max-width:650px;max-height:92vh;overflow-y:auto;border-radius:14px;padding:24px;box-shadow:0 12px 40px rgba(0,0,0,.2)}
.modal-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.modal-heading h2{font-size:1.15rem;color:#1e293b}
.close-modal{border:0;background:none;font-size:1.6rem;color:#64748b;cursor:pointer}
.project-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.form-field{display:flex;flex-direction:column;gap:5px}
.form-field.full-width{grid-column:1/-1}
.form-field label{font-size:.75rem;font-weight:600;color:#475569}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:9px 10px;border:1px solid #cbd5e0;border-radius:7px;font-size:.8rem;outline:none;background:#fff;color:#334155}
.form-field textarea{min-height:75px;resize:vertical}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{border-color:#38bdf8}
.form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:18px}
.cancel-btn{background:#e2e8f0;color:#334155;border:0;border-radius:7px;padding:9px 14px;font-size:.78rem;cursor:pointer}
@media(max-width:1100px){.projects-layout{flex-direction:column}.right-sidebar-section{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.sidebar{width:60px}.sidebar .menu-item span{display:none}.menu-item{justify-content:center;padding:12px 8px}.search-bar{width:200px}.content-area{padding:20px}}
@media(max-width:650px){.topbar{padding:0 10px}.brand-container{width:auto;gap:8px}.brand-name{font-size:1.1rem}.search-bar{width:130px}.topbar-actions{gap:8px}.profile-info{display:none}.right-sidebar-section{display:flex}.content-area{padding:16px 10px}.projects-card,.card{padding:14px 12px}.project-form-grid{grid-template-columns:1fr}.form-field.full-width{grid-column:auto}.table-filter-bar{align-items:stretch}.table-search-input{max-width:none}.filters-group{justify-content:space-between}.project-tabs{gap:14px}}
</style>
</head>
<body>
<div class="app-layout">
<header class="topbar">
  <div class="brand-container">
    <button class="hamburger-btn" type="button" aria-label="Menu">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <span class="brand-name">DocuBiz</span>
  </div>

  <div class="search-bar">
    <input id="topSearch" type="text" placeholder="Search projects..." aria-label="Search projects">
    <button type="button" class="search-btn" aria-label="Search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </button>
  </div>

  <div class="topbar-actions">
    <button class="icon-btn" type="button" aria-label="Settings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
    </button>
    <button class="icon-btn" type="button" aria-label="Notifications">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
    </button>
    <div class="user-profile">
      <div class="profile-avatar"><?php echo projectsEscape($avatarText); ?></div>
      <div class="profile-info">
        <span class="user-name"><?php echo projectsEscape($displayName); ?></span>
        <span class="user-role">Admin Staff</span>
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

    <a href="projects.php" class="menu-item active">
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

    <a href="staff_management.php" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      <span>Staff Management</span>
    </a>

    <a href="finances.php" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
      <span>Finances</span>
    </a>
  </nav>
</aside>


<main class="content-area">
  <div class="page-header">
    <h1 class="page-title">Projects</h1>
    <p class="page-subtitle">Plan, track, and manage all catering projects and events.</p>
  </div>

  <?php if ($error !== ""): ?>
    <div class="notice error"><?php echo projectsEscape($error); ?></div>
  <?php endif; ?>

  <div class="projects-layout">
    <div class="main-projects-section">
      <div class="metrics-grid">
        <div class="metric-card"><div class="metric-icon-box bg-blue">📁</div><div class="metric-data"><span class="metric-value"><?php echo $totalProjects; ?></span><span class="metric-label">Total Projects</span></div></div>
        <div class="metric-card"><div class="metric-icon-box bg-green">✔</div><div class="metric-data"><span class="metric-value"><?php echo $ongoingProjects; ?></span><span class="metric-label">Ongoing</span></div></div>
        <div class="metric-card"><div class="metric-icon-box bg-orange">🔄</div><div class="metric-data"><span class="metric-value"><?php echo $upcomingProjects; ?></span><span class="metric-label">Upcoming</span></div></div>
        <div class="metric-card"><div class="metric-icon-box bg-red">🍰</div><div class="metric-data"><span class="metric-value"><?php echo $completedProjects; ?></span><span class="metric-label">Completed</span></div></div>
      </div>

      <div class="projects-card">
        <div class="project-tabs">
          <button type="button" class="tab-btn active" data-tab="All">All Projects</button>
          <button type="button" class="tab-btn" data-tab="In Progress">Ongoing</button>
          <button type="button" class="tab-btn" data-tab="Planned">Upcoming</button>
          <button type="button" class="tab-btn" data-tab="Completed">Completed</button>
        </div>

        <div class="table-filter-bar">
          <div class="table-search-input">
            <input id="tableSearch" type="text" placeholder="Search projects..." aria-label="Search projects">
            <span class="table-search-icon">⌕</span>
          </div>
          <div class="filters-group">
            <div class="filter-select-wrapper">
              <span class="filter-label">Event Type</span>
              <select class="filter-select" id="eventFilter">
                <option value="">All</option>
                <option value="Catering Event">Catering Event</option>
                <option value="Birthday">Birthday</option>
                <option value="Wedding">Wedding</option>
                <option value="Corporate">Corporate</option>
                <option value="Meeting">Meeting</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="filter-select-wrapper">
              <span class="filter-label">Status</span>
              <select class="filter-select" id="statusFilter">
                <option value="">All</option>
                <option value="Planned">Planned</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="On Hold">On Hold</option>
              </select>
            </div>
            <div class="filter-select-wrapper">
              <span class="filter-label">Sort by</span>
              <select class="filter-select" id="sortFilter">
                <option value="newest">Date (Newest)</option>
                <option value="oldest">Date (Oldest)</option>
                <option value="name">Project Name</option>
              </select>
            </div>
          </div>
          <button type="button" class="primary-btn" id="addProjectBtn">+ Add Project</button>
        </div>

        <div class="table-scroll">
        <table class="projects-table">
          <thead><tr><th>Project Name</th><th>Client</th><th>Event Type</th><th>Date</th><th>Status</th><th>Progress</th><th>Actions</th></tr></thead>
          <tbody id="projectsTableBody">
          <?php if (empty($projects)): ?>
            <tr id="emptyProjectsRow"><td colspan="7" class="empty-row">No projects yet. Click “Add Project” to create your first one.</td></tr>
          <?php else: ?>
            <?php foreach ($projects as $project): ?>
              <tr class="project-row"
                  data-name="<?php echo projectsEscape(strtolower($project["project_name"])); ?>"
                  data-client="<?php echo projectsEscape(strtolower($project["client_name"] ?? "")); ?>"
                  data-event="<?php echo projectsEscape($project["event_type"]); ?>"
                  data-status="<?php echo projectsEscape($project["status"]); ?>"
                  data-date="<?php echo projectsEscape($project["due_date"] ?? $project["start_date"] ?? ""); ?>">
                <td><div class="project-name-cell"><span class="project-title"><?php echo projectsEscape($project["project_name"]); ?></span><span class="project-location"><?php echo projectsEscape($project["location"] ?: "Location not set"); ?></span></div></td>
                <td><?php echo projectsEscape($project["client_name"] ?: "—"); ?></td>
                <td><span class="event-type-badge"><?php echo projectsEscape($project["event_type"]); ?></span></td>
                <td><?php echo projectsEscape(projectDate($project["due_date"] ?: $project["start_date"])); ?></td>
                <td><span class="<?php echo projectsEscape(projectStatusClass($project["status"])); ?>"><?php echo projectsEscape($project["status"]); ?></span></td>
                <td><div class="progress-box"><span class="progress-text"><?php echo (int)$project["progress"]; ?>%</span><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?php echo (int)$project["progress"]; ?>%"></div></div></div></td>
                <td>
                  <div class="action-buttons">
                    <button type="button" class="small-btn edit-btn" data-project="<?php echo projectsEscape(json_encode($project, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)); ?>">Edit</button>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this project?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="project_id" value="<?php echo (int)$project["id"]; ?>">
                      <button type="submit" class="small-btn delete-btn">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
        </div>
        <p id="noFilterResults" style="display:none;text-align:center;padding:20px;color:#64748b;font-size:.8rem;">No projects match your search or filters.</p>
      </div>
    </div>

    <div class="right-sidebar-section">
      <div class="card">
        <div class="card-header"><h2>Upcoming Projects</h2><a href="#" class="view-all" id="viewUpcoming">View All</a></div>
        <div class="upcoming-projects-list">
          <?php
          $upcomingShown = 0;
          foreach ($projects as $project):
              if (!in_array($project["status"], ["Planned", "In Progress"], true)) continue;
              if ($project["due_date"] && $project["due_date"] < date("Y-m-d")) continue;
              $dateForBadge = $project["due_date"] ?: $project["start_date"];
              $badgeClasses = ["badge-purple", "badge-red", "badge-green"];
              $badgeClass = $badgeClasses[$upcomingShown % count($badgeClasses)];
              $upcomingShown++;
              if ($upcomingShown > 5) break;
          ?>
            <div class="upcoming-item">
              <div class="date-badge <?php echo $badgeClass; ?>">
                <span class="month"><?php echo $dateForBadge ? strtoupper(date("M", strtotime($dateForBadge))) : "TBD"; ?></span>
                <span class="day"><?php echo $dateForBadge ? date("d", strtotime($dateForBadge)) : "—"; ?></span>
              </div>
              <div class="item-details">
                <h3><?php echo projectsEscape($project["project_name"]); ?></h3>
                <div class="meta">
                  <?php echo $project["guest_count"] !== null ? (int)$project["guest_count"] . " Guests" : projectsEscape($project["status"]); ?>
                  <?php if ($project["event_time"]): ?> • <?php echo date("g:i A", strtotime($project["event_time"])); ?><?php endif; ?>
                </div>
                <div class="location"><?php echo projectsEscape($project["location"] ?: "Location not set"); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($upcomingShown === 0): ?>
            <p style="font-size:.78rem;color:#64748b;">No upcoming projects yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Project Reminders</h2></div>
        <div class="reminders-list">
          <div class="reminder-item"><div class="reminder-dot"></div><div class="reminder-info"><h4>Review project deadlines</h4><p>Check the due dates of your projects</p></div></div>
          <div class="reminder-item"><div class="reminder-dot"></div><div class="reminder-info"><h4>Update project progress</h4><p>Keep project statuses up to date</p></div></div>
          <div class="reminder-item"><div class="reminder-dot"></div><div class="reminder-info"><h4>Confirm event details</h4><p>Check client and location details</p></div></div>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
</div>

<!-- ADD / EDIT PROJECT MODAL -->
<div class="modal-overlay" id="projectModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="project-modal">
    <div class="modal-heading">
      <h2 id="modalTitle">Add Project</h2>
      <button type="button" class="close-modal" id="closeModal" aria-label="Close">&times;</button>
    </div>

    <form method="POST" id="projectForm">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="project_id" id="projectId">

      <div class="project-form-grid">
        <div class="form-field full-width">
          <label for="projectName">Project Name *</label>
          <input type="text" name="project_name" id="projectName" maxlength="150" required>
        </div>
        <div class="form-field">
          <label for="clientName">Client Name</label>
          <input type="text" name="client_name" id="clientName" maxlength="150">
        </div>
        <div class="form-field">
          <label for="eventType">Event Type</label>
          <select name="event_type" id="eventType">
            <option value="Catering Event">Catering Event</option>
            <option value="Birthday">Birthday</option>
            <option value="Wedding">Wedding</option>
            <option value="Corporate">Corporate</option>
            <option value="Meeting">Meeting</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-field">
          <label for="startDate">Start Date</label>
          <input type="date" name="start_date" id="startDate">
        </div>
        <div class="form-field">
          <label for="dueDate">Due / Event Date</label>
          <input type="date" name="due_date" id="dueDate">
        </div>
        <div class="form-field">
          <label for="eventTime">Event Time</label>
          <input type="time" name="event_time" id="eventTime">
        </div>
        <div class="form-field">
          <label for="guestCount">Number of Guests</label>
          <input type="number" name="guest_count" id="guestCount" min="0">
        </div>
        <div class="form-field">
          <label for="projectStatus">Status</label>
          <select name="status" id="projectStatus">
            <option value="Planned">Planned</option>
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="On Hold">On Hold</option>
          </select>
        </div>
        <div class="form-field">
          <label for="progress">Progress (%)</label>
          <input type="number" name="progress" id="progress" min="0" max="100" value="0" required>
        </div>
        <div class="form-field full-width">
          <label for="location">Location</label>
          <input type="text" name="location" id="location" maxlength="255">
        </div>
        <div class="form-field full-width">
          <label for="description">Description / Notes</label>
          <textarea name="description" id="description" maxlength="5000"></textarea>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="cancel-btn" id="cancelModal">Cancel</button>
        <button type="submit" class="primary-btn" id="saveProjectBtn">Save Project</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById("projectModal");
const projectForm = document.getElementById("projectForm");

function openModal() {
  modal.classList.add("open");
  document.body.style.overflow = "hidden";
}

function closeModal() {
  modal.classList.remove("open");
  document.body.style.overflow = "";
}

document.getElementById("addProjectBtn").addEventListener("click", function () {
  projectForm.reset();
  document.getElementById("modalTitle").textContent = "Add Project";
  document.getElementById("formAction").value = "add";
  document.getElementById("projectId").value = "";
  document.getElementById("progress").value = "0";
  document.getElementById("saveProjectBtn").textContent = "Save Project";
  openModal();
});

document.getElementById("closeModal").addEventListener("click", closeModal);
document.getElementById("cancelModal").addEventListener("click", closeModal);

modal.addEventListener("click", function (event) {
  if (event.target === modal) closeModal();
});

document.querySelectorAll(".edit-btn").forEach(function (button) {
  button.addEventListener("click", function () {
    let project;

    try {
      project = JSON.parse(this.dataset.project);
    } catch (error) {
      alert("Unable to load this project for editing.");
      return;
    }

    projectForm.reset();
    document.getElementById("modalTitle").textContent = "Edit Project";
    document.getElementById("formAction").value = "update";
    document.getElementById("projectId").value = project.id;
    document.getElementById("projectName").value = project.project_name || "";
    document.getElementById("clientName").value = project.client_name || "";
    document.getElementById("eventType").value = project.event_type || "Catering Event";
    document.getElementById("startDate").value = project.start_date || "";
    document.getElementById("dueDate").value = project.due_date || "";
    document.getElementById("eventTime").value = project.event_time ? project.event_time.substring(0, 5) : "";
    document.getElementById("guestCount").value = project.guest_count ?? "";
    document.getElementById("projectStatus").value = project.status || "Planned";
    document.getElementById("progress").value = project.progress ?? 0;
    document.getElementById("location").value = project.location || "";
    document.getElementById("description").value = project.description || "";
    document.getElementById("saveProjectBtn").textContent = "Update Project";
    openModal();
  });
});

/* SEARCH AND FILTER PROJECTS */
const tableRows = Array.from(document.querySelectorAll(".project-row"));
const tableSearch = document.getElementById("tableSearch");
const topSearch = document.getElementById("topSearch");
const eventFilter = document.getElementById("eventFilter");
const statusFilter = document.getElementById("statusFilter");
const sortFilter = document.getElementById("sortFilter");
const noFilterResults = document.getElementById("noFilterResults");
let activeTab = "All";

function applyProjectFilters() {
  const searchTerm = (tableSearch.value || topSearch.value).toLowerCase().trim();
  const eventValue = eventFilter.value;
  const statusValue = statusFilter.value;

  let visibleCount = 0;

  tableRows.forEach(function (row) {
    const searchable = (row.dataset.name + " " + row.dataset.client).toLowerCase();
    const matchesSearch = searchable.includes(searchTerm);
    const matchesEvent = !eventValue || row.dataset.event === eventValue;
    const matchesStatus = !statusValue || row.dataset.status === statusValue;
    const matchesTab = activeTab === "All" || row.dataset.status === activeTab;
    const show = matchesSearch && matchesEvent && matchesStatus && matchesTab;

    row.style.display = show ? "" : "none";
    if (show) visibleCount++;
  });

  const tbody = document.getElementById("projectsTableBody");
  const emptyOriginalRow = document.getElementById("emptyProjectsRow");

  if (emptyOriginalRow) {
    emptyOriginalRow.style.display = searchTerm || eventValue || statusValue || activeTab !== "All" ? "none" : "";
  }

  noFilterResults.style.display =
    tableRows.length > 0 && visibleCount === 0 ? "block" : "none";

  const sortedRows = [...tableRows].sort(function (a, b) {
    if (sortFilter.value === "name") {
      return a.dataset.name.localeCompare(b.dataset.name);
    }
    const dateA = a.dataset.date || "";
    const dateB = b.dataset.date || "";
    return sortFilter.value === "oldest"
      ? dateA.localeCompare(dateB)
      : dateB.localeCompare(dateA);
  });

  sortedRows.forEach(row => tbody.appendChild(row));
}

tableSearch.addEventListener("input", function () {
  topSearch.value = this.value;
  applyProjectFilters();
});

topSearch.addEventListener("input", function () {
  tableSearch.value = this.value;
  applyProjectFilters();
});

eventFilter.addEventListener("change", applyProjectFilters);
statusFilter.addEventListener("change", applyProjectFilters);
sortFilter.addEventListener("change", applyProjectFilters);

document.querySelectorAll(".tab-btn").forEach(function (button) {
  button.addEventListener("click", function () {
    document.querySelectorAll(".tab-btn").forEach(btn => btn.classList.remove("active"));
    this.classList.add("active");
    activeTab = this.dataset.tab;
    applyProjectFilters();
  });
});

document.getElementById("viewUpcoming").addEventListener("click", function (event) {
  event.preventDefault();
  activeTab = "Planned";
  document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.classList.toggle("active", btn.dataset.tab === "Planned");
  });
  statusFilter.value = "";
  applyProjectFilters();
  document.querySelector(".projects-card").scrollIntoView({behavior: "smooth"});
});

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") closeModal();
});

applyProjectFilters();
</script>
</body>
</html>
