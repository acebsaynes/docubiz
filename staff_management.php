
<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$userId = (int) $_SESSION["user_id"];

$roleStmt = $conn->prepare(
    "SELECT full_name, email, role FROM users WHERE id = ? LIMIT 1"
);

if (!$roleStmt) {
    error_log("Staff role check error: " . $conn->error);
    http_response_code(500);
    exit("Unable to verify your account. Please check the database configuration.");
}

$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$roleStmt->bind_result($dbFullName, $dbEmail, $dbRole);
$userFound = $roleStmt->fetch();
$roleStmt->close();

if (!$userFound || !in_array($dbRole, ["admin", "user"], true)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($dbRole !== "admin") {
    header("Location: dashboard.php");
    exit;
}

$_SESSION["user_role"] = $dbRole;
$_SESSION["role"] = $dbRole;
$_SESSION["user_name"] = $dbFullName;
$_SESSION["full_name"] = $dbFullName;
$_SESSION["user_email"] = $dbEmail;

$userName = trim($dbFullName) ?: ($dbEmail ?: "Admin");
$nameParts = preg_split('/\s+/', $userName);

if (count($nameParts) >= 2) {
    $avatarInitials = strtoupper(
        mb_substr($nameParts[0], 0, 1, "UTF-8") .
        mb_substr($nameParts[count($nameParts) - 1], 0, 1, "UTF-8")
    );
} else {
    $avatarInitials = strtoupper(mb_substr($userName, 0, 2, "UTF-8"));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectStaff($message) {
    header("Location: staff_management.php?message=" . urlencode($message));
    exit;
}

/*
 * All create, edit, and delete actions are handled by PHP.
 * The staff table is separate from the users login table.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "edit") {
        $staffNumber = trim($_POST["staff_number"] ?? "");
        $fullName = trim($_POST["full_name"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $position = trim($_POST["position"] ?? "");
        $branch = trim($_POST["branch"] ?? "");

        if (
            $staffNumber === "" || $fullName === "" ||
            $department === "" || $email === "" ||
            $phone === "" || $position === "" || $branch === ""
        ) {
            redirectStaff("Please fill in all staff fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectStaff("Please enter a valid email address.");
        }

        if ($action === "add") {
            $stmt = $conn->prepare(
                "INSERT INTO staff
                (staff_number, full_name, department, email, phone, position, branch)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                error_log("Staff insert prepare error: " . $conn->error);
                redirectStaff("Unable to add staff. Please try again.");
            }

            $stmt->bind_param(
                "sssssss",
                $staffNumber, $fullName, $department,
                $email, $phone, $position, $branch
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectStaff("Staff member added successfully.");
            }

            $errorNumber = $stmt->errno;
            $stmt->close();

            if ($errorNumber === 1062) {
                redirectStaff("That staff ID already exists. Please use another ID.");
            }

            redirectStaff("Unable to add staff. Please try again.");
        }

        if ($action === "edit") {
            $id = (int)($_POST["id"] ?? 0);

            $stmt = $conn->prepare(
                "UPDATE staff SET
                    staff_number = ?, full_name = ?, department = ?,
                    email = ?, phone = ?, position = ?, branch = ?
                 WHERE id = ?"
            );

            if (!$stmt) {
                error_log("Staff update prepare error: " . $conn->error);
                redirectStaff("Unable to update staff. Please try again.");
            }

            $stmt->bind_param(
                "sssssssi",
                $staffNumber, $fullName, $department,
                $email, $phone, $position, $branch, $id
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectStaff("Staff member updated successfully.");
            }

            $errorNumber = $stmt->errno;
            $stmt->close();

            if ($errorNumber === 1062) {
                redirectStaff("That staff ID already exists. Please use another ID.");
            }

            redirectStaff("Unable to update staff. Please try again.");
        }
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);

        if ($id <= 0) {
            redirectStaff("Invalid staff record.");
        }

        $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectStaff("Staff member deleted successfully.");
        }

        $stmt->close();
        redirectStaff("Unable to delete staff. Please try again.");
    }

    if ($action === "delete_selected") {
        $ids = $_POST["selected_ids"] ?? [];

        if (!is_array($ids) || count($ids) === 0) {
            redirectStaff("Please select at least one staff member.");
        }

        $ids = array_values(array_unique(array_filter(
            array_map("intval", $ids),
            function ($id) {
                return $id > 0;
            }
        )));

        if (!$ids) {
            redirectStaff("Please select valid staff records.");
        }

        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $types = str_repeat("i", count($ids));

        $stmt = $conn->prepare("DELETE FROM staff WHERE id IN ($placeholders)");

        if (!$stmt) {
            error_log("Bulk staff delete prepare error: " . $conn->error);
            redirectStaff("Unable to delete selected staff.");
        }

        $stmt->bind_param($types, ...$ids);

        if ($stmt->execute()) {
            $stmt->close();
            redirectStaff("Selected staff members deleted.");
        }

        $stmt->close();
        redirectStaff("Unable to delete selected staff.");
    }
}

$staffList = [];
$staffQuery = $conn->query(
    "SELECT id, staff_number, full_name, department, email, phone, position, branch
     FROM staff ORDER BY id DESC"
);

if ($staffQuery) {
    while ($record = $staffQuery->fetch_assoc()) {
        $staffList[] = $record;
    }
} else {
    error_log("Staff list query error: " . $conn->error);
}

$departments = [];
foreach ($staffList as $record) {
    if ($record["department"] !== "") {
        $departments[$record["department"]] = true;
    }
}
$departments = array_keys($departments);
sort($departments);

$message = trim($_GET["message"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DocuBiz - Staff Management</title>
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
.app-layout { display: flex; flex-direction: column; min-height: 100vh; }
.topbar {
    background-color: #122b49;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    color: #fff;
}
.brand-container { display: flex; align-items: center; gap: 15px; width: 200px; }
.hamburger-btn, .icon-btn, .search-btn {
    background: none; border: none; color: #fff; cursor: pointer;
    display: flex; align-items: center;
}
.brand-name { font-size: 1.4rem; font-weight: 600; letter-spacing: -0.5px; }
.search-bar { position: relative; width: 450px; display: flex; align-items: center; }
.search-bar input {
    width: 100%; padding: 8px 45px 8px 16px; border-radius: 20px;
    border: none; outline: none; background: #fff; font-size: .85rem; color: #2d3748;
}
.search-bar input::placeholder { color: #8fa0b5; }
.search-btn { position: absolute; right: 12px; color: #122b49; }
.topbar-actions { display: flex; align-items: center; gap: 18px; }
.icon-btn { opacity: .9; }
.icon-btn:hover { opacity: 1; }
.user-profile { display: flex; align-items: center; gap: 10px; margin-left: 10px; }
.profile-avatar {
    width: 34px; height: 34px; border-radius: 50%; background: #2b5585;
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: .85rem; font-weight: 600;
}
.profile-info { display: flex; flex-direction: column; line-height: 1.2; }
.user-name { font-size: .82rem; font-weight: 500; color: #fff; }
.user-role { font-size: .72rem; color: #9bb3d1; }
.logout-link { color: #fff; text-decoration: none; font-size: .8rem; opacity: .9; white-space: nowrap; }
.logout-link:hover { opacity: 1; text-decoration: underline; }
.main-body { display: flex; flex: 1; }
.sidebar {
    width: 210px; flex-shrink: 0; background: #122b49; padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.sidebar-menu { display: flex; flex-direction: column; }
.menu-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 20px;
    color: #b0c4de; text-decoration: none; font-size: .88rem; transition: all .2s ease;
}
.menu-item:hover { color: #fff; background: rgba(255,255,255,.05); }
.menu-item.active { color: #fff; background: #274b73; }
.content-area { flex: 1; min-width: 0; padding: 30px 40px; background: #d5dad9; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 20px; }
.page-title { font-size: 1.8rem; font-weight: 600; color: #2a343d; }
.action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-action, .btn-add-staff {
    background: #72dbd9; color: #1a365d; border: none; border-radius: 18px;
    padding: 8px 16px; font-size: .82rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 5px rgba(0,0,0,.05);
}
.btn-add-staff { padding: 8px 18px; font-size: .85rem; }
.staff-table-card {
    background: #fff; border-radius: 12px; padding: 15px 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);
}
.table-scroll { width: 100%; overflow-x: auto; }
.staff-table { width: 100%; border-collapse: collapse; text-align: left; }
.staff-table th {
    font-size: .72rem; font-weight: 600; color: #718096;
    text-transform: uppercase; padding: 12px 10px; border-bottom: 1px solid #edf2f7;
}
.staff-table td {
    padding: 12px 10px; font-size: .85rem; color: #2d3748;
    vertical-align: middle; border-bottom: 1px solid #f7fafc;
}
.staff-table tbody tr { cursor: pointer; transition: background-color .15s ease; }
.staff-table tbody tr:hover { background: #f1f5f9; }
.staff-row-highlight { background: #b2f5ea; }
.staff-info-cell { display: flex; align-items: center; gap: 12px; min-width: 220px; }
.staff-avatar {
    width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%;
    background: #319795; color: #fff; display: flex; align-items: center;
    justify-content: center; font-size: .8rem; font-weight: 600;
}
.staff-details { display: flex; flex-direction: column; }
.staff-name { font-weight: 600; font-size: .88rem; color: #2d3748; }
.staff-role { font-size: .75rem; color: #718096; }
.pagination { display: flex; align-items: center; gap: 8px; margin-top: 20px; font-size: .85rem; }
.page-link {
    display: flex; align-items: center; justify-content: center; min-width: 26px;
    height: 26px; padding: 0 5px; border-radius: 4px; text-decoration: none; color: #4a5568; font-weight: 500;
}
.page-link.active { background: #319795; color: #fff; }
.page-link:disabled { opacity: .4; }
.modal-overlay {
    display: none; position: fixed; inset: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,.4); z-index: 1000; justify-content: center;
    align-items: center; padding: 20px; overflow-y: auto;
}
.modal-overlay.active { display: flex; }
.modal-card {
    background: #fff; width: 650px; max-width: 100%; max-height: 90vh; overflow-y: auto;
    border-radius: 12px; padding: 30px; position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,.15); animation: fadeIn .2s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal-close-btn {
    position: absolute; top: 15px; right: 20px; background: none; border: none;
    color: #e53e3e; cursor: pointer; font-size: 1.6rem; font-weight: 600;
}
.modal-header-section { display: flex; align-items: center; gap: 25px; position: relative; padding-right: 70px; }
.modal-avatar-container {
    width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
    background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center;
    justify-content: center; color: #319795; font-size: 1.5rem; font-weight: 600;
}
.modal-user-main-info h2 { font-size: 1.25rem; color: #2d3748; font-weight: 600; margin-bottom: 4px; }
.modal-user-main-info p { font-size: .85rem; color: #4a5568; margin-bottom: 12px; }
.online-status { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: #2d3748; font-weight: 500; }
.status-dot { width: 8px; height: 8px; background: #48bb78; border-radius: 50%; }
.message-btn {
    position: absolute; right: 0; top: 25px; border: 1px solid #cbd5e0;
    background: #fff; padding: 6px 18px; border-radius: 20px; font-size: .8rem;
    cursor: pointer; color: #2d3748; font-weight: 500;
}
.message-btn:hover { background: #f7fafc; }
.modal-divider { border: none; border-top: 1px solid #edf2f7; margin: 25px 0 20px; }
.modal-section-title { font-size: .95rem; font-weight: 600; color: #2d3748; margin-bottom: 15px; }
.modal-grid-info { display: grid; grid-template-columns: 1fr 1fr; row-gap: 15px; column-gap: 20px; margin-bottom: 25px; }
.info-group label { display: block; font-size: .72rem; color: #718096; margin-bottom: 2px; }
.info-group span { font-size: .85rem; color: #2d3748; font-weight: 500; overflow-wrap: anywhere; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 20px; }
.form-group label { display: block; font-size: .78rem; color: #4a5568; margin-bottom: 5px; }
.form-group input, .form-group select {
    width: 100%; border: 1px solid #cbd5e0; border-radius: 7px; padding: 10px;
    font-size: .82rem; color: #2d3748; background: #fff; outline-color: #319795;
}
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }
.form-btn {
    border: none; border-radius: 18px; padding: 9px 18px; font-size: .82rem;
    font-weight: 600; cursor: pointer;
}
.form-btn.save { background: #72dbd9; color: #1a365d; }
.form-btn.cancel { background: #edf2f7; color: #2d3748; }
.form-btn.delete { background: #e53e3e; color: #fff; }
.notice {
    margin-bottom: 15px; padding: 10px 14px; border-radius: 8px;
    background: #e6fffa; color: #234e52; font-size: .85rem;
}
.table-empty { text-align: center; padding: 25px !important; color: #718096 !important; }
@media (max-width: 1100px) {
    .content-area { padding: 25px 20px; }
    .search-bar { width: 250px; }
    .topbar-actions { gap: 10px; }
}
@media (max-width: 900px) {
    .sidebar { width: 60px; }
    .sidebar .menu-item span { display: none; }
    .search-bar { width: 200px; }
    .page-header { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 650px) {
    .topbar { padding: 0 10px; }
    .brand-container { width: auto; gap: 8px; }
    .brand-name { font-size: 1.1rem; }
    .search-bar { width: 150px; }
    .topbar-actions { gap: 7px; }
    .user-profile { margin-left: 0; }
    .profile-info, .icon-btn { display: none; }
    .content-area { padding: 20px 10px; }
    .page-title { font-size: 1.4rem; }
    .staff-table-card { padding: 10px; }
    .modal-card { padding: 25px 18px; }
    .modal-header-section { gap: 12px; padding-right: 0; }
    .modal-avatar-container { width: 65px; height: 65px; }
    .message-btn { position: static; margin-left: auto; }
    .form-grid, .modal-grid-info { grid-template-columns: 1fr; }
}
</style>
</head>

<body>
<div class="app-layout">
<header class="topbar">
    <div class="brand-container">
        <button class="hamburger-btn" type="button" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
    </div>

    <div class="search-bar">
        <input id="topSearch" type="text" placeholder="Search staff members..." aria-label="Search staff members">
        <button type="button" class="search-btn" id="searchButton" aria-label="Search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
    </div>

    <div class="topbar-actions">
        <button class="icon-btn" type="button" aria-label="Settings">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 1 .33-1.82A1.65 1.65 0 0 1 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 1 4.6 7a1.65 1.65 0 0 1-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 1 1.82.33H9a1.65 1.65 0 0 1 1-1.51V1a2 2 0 0 1 2-1 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 1 1 1.51 1.65 1.65 0 0 1 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 1-.33 1.82V8a1.65 1.65 0 0 1 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 1-1.51 1z"/></svg>
        </button>
        <button class="icon-btn" type="button" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </button>
        <div class="user-profile">
            <div class="profile-avatar"><?= h($avatarInitials) ?></div>
            <div class="profile-info">
                <span class="user-name"><?= h($userName) ?></span>
                <span class="user-role">Admin Staff</span>
            </div>
        </div>
        <a href="logout.php" class="logout-link">Log Out</a>
    </div>
</header>

<div class="main-body">
<aside class="sidebar">
<nav class="sidebar-menu">
    <a href="dashboard.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
    <a href="messages.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="calendar.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Calendar</span></a>
    <a href="projects.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><span>Projects</span></a>
    <a href="inventory.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg><span>Inventory</span></a>
    <a href="documents.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Documents</span></a>
    <a href="staff_management.php" class="menu-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Staff Management</span></a>
    <a href="finances.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Finances</span></a>
</nav>
</aside>

<main class="content-area">
<div class="page-header">
    <h1 class="page-title">Staff Management</h1>
    <div class="action-buttons">
        <button class="btn-action" id="sortButton" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
            Sort
        </button>
        <button class="btn-action" id="filterButton" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filter
        </button>
        <button class="btn-add-staff" type="button" onclick="openAddModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Staff Member
        </button>
    </div>
</div>

<?php if ($message !== ""): ?>
    <div class="notice"><?= h($message) ?></div>
<?php endif; ?>

<div class="staff-table-card">
<div style="display:none; margin-bottom:15px;" id="filterArea">
    <label for="departmentFilter" style="font-size:.82rem; margin-right:8px;">Department:</label>
    <select id="departmentFilter" style="padding:7px 10px; border:1px solid #cbd5e0; border-radius:8px; color:#2d3748;">
        <option value="">All Departments</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= h($department) ?>"><?= h($department) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="button" class="btn-action" id="clearFilter" style="display:inline-flex; margin-left:5px;">Clear Filter</button>
</div>

<form method="post" id="bulkDeleteForm" onsubmit="return confirm('Delete all selected staff records? This cannot be undone.');">
    <input type="hidden" name="action" value="delete_selected">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
        <span id="selectionCount" style="font-size:.8rem; color:#718096;">Select staff members using the checkboxes.</span>
        <button class="form-btn delete" type="submit" id="bulkDeleteButton" style="display:none;">Delete Selected</button>
    </div>

    <div class="table-scroll">
    <table class="staff-table">
        <thead>
        <tr>
            <th style="width:40px;"><input type="checkbox" id="selectAll" aria-label="Select all staff"></th>
            <th>Staff Member</th>
            <th>ID Number</th>
            <th>Department</th>
            <th>Email</th>
            <th>Mobile</th>
        </tr>
        </thead>
        <tbody id="staffTableBody">
        <?php foreach ($staffList as $index => $staff):
            $initials = "";
            $parts = preg_split('/\s+/', trim($staff["full_name"]));
            if (count($parts) >= 2) {
                $initials = strtoupper(
                    mb_substr($parts[0], 0, 1, "UTF-8") .
                    mb_substr($parts[count($parts) - 1], 0, 1, "UTF-8")
                );
            } else {
                $initials = strtoupper(mb_substr($staff["full_name"], 0, 2, "UTF-8"));
            }
            $colors = ["#319795", "#2b6cb0", "#d69e2e", "#e53e3e", "#805ad5", "#dd6b20"];
            $color = $colors[$index % count($colors)];
        ?>
        <tr
            data-id="<?= (int)$staff["id"] ?>"
            data-name="<?= h($staff["full_name"]) ?>"
            data-staff-number="<?= h($staff["staff_number"]) ?>"
            data-dept="<?= h($staff["department"]) ?>"
            data-email="<?= h($staff["email"]) ?>"
            data-phone="<?= h($staff["phone"]) ?>"
            data-role="<?= h($staff["position"]) ?>"
            data-branch="<?= h($staff["branch"]) ?>"
            onclick="openDetails(this)"
        >
            <td onclick="event.stopPropagation()">
                <input type="checkbox" class="staff-checkbox" name="selected_ids[]" value="<?= (int)$staff["id"] ?>" aria-label="Select <?= h($staff["full_name"]) ?>">
            </td>
            <td>
                <div class="staff-info-cell">
                    <div class="staff-avatar" style="background-color:<?= h($color) ?>;"><?= h($initials) ?></div>
                    <div class="staff-details">
                        <span class="staff-name"><?= h($staff["full_name"]) ?></span>
                        <span class="staff-role"><?= h($staff["position"]) ?></span>
                    </div>
                </div>
            </td>
            <td><?= h($staff["staff_number"]) ?></td>
            <td><?= h($staff["department"]) ?></td>
            <td><?= h($staff["email"]) ?></td>
            <td><?= h($staff["phone"]) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</form>

<div class="pagination" id="pagination"></div>
</div>
</main>
</div>
</div>

<!-- Staff details modal -->
<div class="modal-overlay" id="staffModal">
    <div class="modal-card">
        <button class="modal-close-btn" type="button" onclick="closeModal()" aria-label="Close">&times;</button>
        <div class="modal-header-section">
            <div class="modal-avatar-container" id="detailAvatar">ST</div>
            <div class="modal-user-main-info">
                <h2 id="modalName">Staff Name</h2>
                <p>ID Number: <span id="modalId"></span></p>
                <div class="online-status"><div class="status-dot"></div><span>STAFF MEMBER</span></div>
            </div>
            <button class="message-btn" type="button" onclick="messageStaff()">Message</button>
        </div>
        <hr class="modal-divider">
        <div class="modal-section-title">Contact Information</div>
        <div class="modal-grid-info">
            <div class="info-group"><label>Email Address</label><span id="modalEmail"></span></div>
            <div class="info-group"><label>Phone</label><span id="modalPhone"></span></div>
            <div class="info-group"><label>City/State</label><span>Dasmariñas</span></div>
            <div class="info-group"><label>Postal Code</label><span>4114</span></div>
        </div>
        <div class="modal-section-title">Employment Details</div>
        <div class="modal-grid-info">
            <div class="info-group"><label>Department</label><span id="modalDept"></span></div>
            <div class="info-group"><label>Office Branch</label><span id="modalBranch"></span></div>
            <div class="info-group" style="grid-column:span 2;"><label>Position</label><span id="modalRole"></span></div>
        </div>
        <div class="form-actions">
            <button type="button" class="form-btn save" onclick="openEditModal()">Edit Staff</button>
            <form method="post" id="deleteStaffForm" onsubmit="return confirm('Are you sure you want to delete this staff member? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteStaffId">
                <button type="submit" class="form-btn delete">Delete Staff</button>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit modal -->
<div class="modal-overlay" id="staffFormModal">
    <div class="modal-card">
        <button class="modal-close-btn" type="button" onclick="closeFormModal()" aria-label="Close">&times;</button>
        <h2 id="formTitle" style="font-size:1.25rem; color:#2d3748; font-weight:600;">Add Staff Member</h2>
        <p style="font-size:.82rem; color:#718096; margin-top:5px;">Enter the staff member's information below.</p>

        <form method="post" id="staffForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">

            <div class="form-grid">
                <div class="form-group">
                    <label for="formStaffNumber">ID Number</label>
                    <input type="text" name="staff_number" id="formStaffNumber" required maxlength="50">
                </div>
                <div class="form-group">
                    <label for="formFullName">Full Name</label>
                    <input type="text" name="full_name" id="formFullName" required maxlength="150">
                </div>
                <div class="form-group">
                    <label for="formDepartment">Department</label>
                    <input type="text" name="department" id="formDepartment" list="departmentOptions" required maxlength="100">
                    <datalist id="departmentOptions">
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= h($department) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="formPosition">Position</label>
                    <input type="text" name="position" id="formPosition" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="formEmail">Email Address</label>
                    <input type="email" name="email" id="formEmail" required maxlength="150">
                </div>
                <div class="form-group">
                    <label for="formPhone">Mobile Number</label>
                    <input type="text" name="phone" id="formPhone" required maxlength="30">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label for="formBranch">Office Branch</label>
                    <input type="text" name="branch" id="formBranch" required maxlength="150">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="form-btn cancel" onclick="closeFormModal()">Cancel</button>
                <button type="submit" class="form-btn save" id="saveStaffButton">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
const searchInput = document.getElementById("topSearch");
const tableBody = document.getElementById("staffTableBody");
const allRows = Array.from(tableBody.querySelectorAll("tr"));
const pagination = document.getElementById("pagination");
const selectAll = document.getElementById("selectAll");
const selectionCount = document.getElementById("selectionCount");
const bulkDeleteButton = document.getElementById("bulkDeleteButton");
const bulkDeleteForm = document.getElementById("bulkDeleteForm");
const filterArea = document.getElementById("filterArea");
const departmentFilter = document.getElementById("departmentFilter");

let sortAscending = true;
let currentPage = 1;
const rowsPerPage = 8;
let currentStaffRow = null;
let visibleRows = [...allRows];

function getRowData(row) {
    return {
        id: row.dataset.id || "",
        staffNumber: row.dataset.staffNumber || "",
        name: row.dataset.name || "",
        department: row.dataset.dept || "",
        email: row.dataset.email || "",
        phone: row.dataset.phone || "",
        position: row.dataset.role || "",
        branch: row.dataset.branch || ""
    };
}

function updateTable() {
    const query = searchInput.value.trim().toLowerCase();
    const department = departmentFilter.value;

    visibleRows = allRows.filter(row => {
        const data = getRowData(row);
        const searchable = [
            data.name, data.staffNumber, data.department,
            data.email, data.phone, data.position, data.branch
        ].join(" ").toLowerCase();

        return searchable.includes(query) &&
            (!department || data.department === department);
    });

    currentPage = 1;
    renderPage();
}

function renderPage() {
    const pageCount = Math.max(1, Math.ceil(visibleRows.length / rowsPerPage));
    if (currentPage > pageCount) currentPage = pageCount;

    allRows.forEach(row => {
        row.style.display = "none";
    });

    const start = (currentPage - 1) * rowsPerPage;
    const pageRows = visibleRows.slice(start, start + rowsPerPage);

    pageRows.forEach((row, index) => {
        row.style.display = "";
        row.classList.toggle("staff-row-highlight", index % 2 === 1);
        tableBody.appendChild(row);
    });

    if (visibleRows.length === 0) {
        let emptyRow = document.getElementById("emptyStaffRow");
        if (!emptyRow) {
            emptyRow = document.createElement("tr");
            emptyRow.id = "emptyStaffRow";
            emptyRow.innerHTML = '<td colspan="6" class="table-empty">No staff members found.</td>';
        }
        tableBody.appendChild(emptyRow);
    } else {
        const emptyRow = document.getElementById("emptyStaffRow");
        if (emptyRow) emptyRow.remove();
    }

    pagination.innerHTML = "";

    function addPageLink(label, page, active, disabled) {
        const link = document.createElement("a");
        link.href = "#";
        link.className = "page-link" + (active ? " active" : "");
        link.textContent = label;

        if (disabled) {
            link.style.opacity = ".4";
            link.style.pointerEvents = "none";
        }

        link.addEventListener("click", event => {
            event.preventDefault();
            if (!disabled) {
                currentPage = page;
                renderPage();
            }
        });

        pagination.appendChild(link);
    }

    addPageLink("<", currentPage - 1, false, currentPage === 1);

    for (let page = 1; page <= pageCount; page++) {
        addPageLink(String(page), page, page === currentPage, false);
    }

    addPageLink(">", currentPage + 1, false, currentPage === pageCount);

    updateSelectionDisplay();
}

function updateSelectionDisplay() {
    const checked = tableBody.querySelectorAll(".staff-checkbox:checked");
    selectionCount.textContent = checked.length
        ? checked.length + " staff member(s) selected."
        : "Select staff members using the checkboxes.";

    bulkDeleteButton.style.display = checked.length ? "inline-block" : "none";

    selectAll.checked = visibleRows.length > 0 &&
        visibleRows.every(row => row.querySelector(".staff-checkbox").checked);

    selectAll.indeterminate = checked.length > 0 && !selectAll.checked;
}

searchInput.addEventListener("input", updateTable);
document.getElementById("searchButton").addEventListener("click", updateTable);

document.getElementById("sortButton").addEventListener("click", () => {
    visibleRows.sort((a, b) => {
        const comparison = a.dataset.name.localeCompare(b.dataset.name);
        return sortAscending ? comparison : -comparison;
    });
    sortAscending = !sortAscending;
    currentPage = 1;
    renderPage();
});

document.getElementById("filterButton").addEventListener("click", () => {
    filterArea.style.display = filterArea.style.display === "none" ? "block" : "none";
});

departmentFilter.addEventListener("change", updateTable);

document.getElementById("clearFilter").addEventListener("click", () => {
    departmentFilter.value = "";
    searchInput.value = "";
    updateTable();
});

selectAll.addEventListener("change", () => {
    visibleRows.forEach(row => {
        row.querySelector(".staff-checkbox").checked = selectAll.checked;
    });
    updateSelectionDisplay();
});

tableBody.addEventListener("change", event => {
    if (event.target.classList.contains("staff-checkbox")) {
        updateSelectionDisplay();
    }
});

/* Ensure bulk delete sends only selected record IDs. */
bulkDeleteForm.addEventListener("submit", event => {
    bulkDeleteForm.querySelectorAll('input[name="selected_ids[]"]').forEach(input => {
        input.remove();
    });

    const checked = tableBody.querySelectorAll(".staff-checkbox:checked");
    if (!checked.length) {
        event.preventDefault();
        alert("Please select at least one staff member.");
        return;
    }

    checked.forEach(checkbox => {
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "selected_ids[]";
        hidden.value = checkbox.value;
        bulkDeleteForm.appendChild(hidden);
    });
});

function openDetails(row) {
    currentStaffRow = row;
    const data = getRowData(row);

    document.getElementById("modalName").textContent = data.name;
    document.getElementById("modalId").textContent = data.staffNumber;
    document.getElementById("modalEmail").textContent = data.email;
    document.getElementById("modalPhone").textContent = data.phone;
    document.getElementById("modalDept").textContent = data.department;
    document.getElementById("modalRole").textContent = data.position;
    document.getElementById("modalBranch").textContent = data.branch;
    document.getElementById("deleteStaffId").value = data.id;

    const parts = data.name.trim().split(/\s+/);
    const initials = parts.length > 1
        ? parts[0][0] + parts[parts.length - 1][0]
        : data.name.substring(0, 2);

    document.getElementById("detailAvatar").textContent = initials.toUpperCase();
    document.getElementById("staffModal").classList.add("active");
}

function closeModal() {
    document.getElementById("staffModal").classList.remove("active");
}

function openAddModal() {
    document.getElementById("staffForm").reset();
    document.getElementById("formTitle").textContent = "Add Staff Member";
    document.getElementById("formAction").value = "add";
    document.getElementById("formId").value = "";
    document.getElementById("saveStaffButton").textContent = "Save Staff";
    document.getElementById("staffFormModal").classList.add("active");
}

function openEditModal() {
    if (!currentStaffRow) return;

    const data = getRowData(currentStaffRow);

    document.getElementById("staffForm").reset();
    document.getElementById("formTitle").textContent = "Edit Staff Member";
    document.getElementById("formAction").value = "edit";
    document.getElementById("formId").value = data.id;
    document.getElementById("formStaffNumber").value = data.staffNumber;
    document.getElementById("formFullName").value = data.name;
    document.getElementById("formDepartment").value = data.department;
    document.getElementById("formPosition").value = data.position;
    document.getElementById("formEmail").value = data.email;
    document.getElementById("formPhone").value = data.phone;
    document.getElementById("formBranch").value = data.branch;
    document.getElementById("saveStaffButton").textContent = "Save Changes";

    closeModal();
    document.getElementById("staffFormModal").classList.add("active");
}

function closeFormModal() {
    document.getElementById("staffFormModal").classList.remove("active");
}

function messageStaff() {
    if (!currentStaffRow) return;

    const data = getRowData(currentStaffRow);

    /*
     * This opens the email app for the selected staff member.
     * It does not create an in-app chat conversation.
     */
    window.location.href = "mailto:" + encodeURIComponent(data.email);
}

document.querySelectorAll(".modal-overlay").forEach(modal => {
    modal.addEventListener("click", event => {
        if (event.target === modal) {
            modal.classList.remove("active");
        }
    });
});

document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        closeModal();
        closeFormModal();
    }
});

updateTable();
</script>
</body>
</html>
