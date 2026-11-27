<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require_once "db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Database connection is unavailable. Please check db.php.");
}
$userId = (int) $_SESSION["user_id"];
$displayName = trim($_SESSION["user_name"] ?? $_SESSION["full_name"] ?? $_SESSION["user_email"] ?? $_SESSION["email"] ?? "User");
if ($displayName === "") $displayName = "User";
$userRoleSession = strtolower(trim($_SESSION["user_role"] ?? $_SESSION["role"] ?? "user"));
$isAdmin = ($userRoleSession === "admin");
$displayRole = $isAdmin ? "Admin Staff" : "User";
$avatarText = strtoupper(substr($displayName, 0, 2));
function inventoryEscape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
$categories = ["Baking Supplies", "Beverages", "Condiments", "Dairy", "Dry Goods", "Meat & Poultry"];
$locations = ["Main Storage", "Cold Storage"];
$errorMessage = "";

// Add inventory items. user_id is retained to record who added each item.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_item") {
    $productName = trim($_POST["product_name"] ?? "");
    $quantity = filter_var($_POST["quantity"] ?? null, FILTER_VALIDATE_INT);
    $category = trim($_POST["category"] ?? "");
    $location = trim($_POST["location"] ?? "");
    if ($productName === "") $errorMessage = "Please enter a product name.";
    elseif ($quantity === false || $quantity < 0) $errorMessage = "Quantity must be zero or higher.";
    elseif (!in_array($category, $categories, true)) $errorMessage = "Please select a valid category.";
    elseif (!in_array($location, $locations, true)) $errorMessage = "Please select a valid location.";
    else {
        $supplyDate = date("Y-m-d");
        $stmt = $conn->prepare("INSERT INTO inventory_items (user_id, product_name, category, quantity, location, supply_date) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) $errorMessage = "Unable to prepare the database query: " . $conn->error;
        else {
            $stmt->bind_param("ississ", $userId, $productName, $category, $quantity, $location, $supplyDate);
            if ($stmt->execute()) {
                $newId = (int)$conn->insert_id;
                $productCode = "INV-" . str_pad((string)$newId, 5, "0", STR_PAD_LEFT);
                $update = $conn->prepare("UPDATE inventory_items SET product_code = ? WHERE id = ? AND user_id = ?");
                if ($update) {
                    $update->bind_param("sii", $productCode, $newId, $userId);
                    $update->execute();
                    $update->close();
                }
                $stmt->close();
                header("Location: inventory.php?success=added");
                exit;
            } else {
                $errorMessage = "Unable to save the item: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Delete selected items (admin only), same flow as Staff Management.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_selected") {
    if (!$isAdmin) {
        header("Location: inventory.php?message=" . urlencode("Only admins can delete inventory items."));
        exit;
    }

    $ids = $_POST["selected_ids"] ?? [];
    if (!is_array($ids) || count($ids) === 0) {
        header("Location: inventory.php?message=" . urlencode("Please select at least one item."));
        exit;
    }

    $ids = array_values(array_unique(array_filter(
        array_map("intval", $ids),
        function ($id) { return $id > 0; }
    )));

    if (!$ids) {
        header("Location: inventory.php?message=" . urlencode("Please select valid inventory records."));
        exit;
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $types = str_repeat("i", count($ids));
    $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id IN ($placeholders)");

    if (!$stmt) {
        error_log("Bulk inventory delete prepare error: " . $conn->error);
        header("Location: inventory.php?message=" . urlencode("Unable to delete selected items."));
        exit;
    }

    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    $stmt->close();

    header("Location: inventory.php?message=" . urlencode($ok ? "Selected items deleted." : "Unable to delete selected items."));
    exit;
}

// Shared inventory: all items are loaded; search, sort, filter and paging happen in the browser.
$inventoryItems = [];
$listQuery = $conn->query(
    "SELECT id, product_name, category, product_code, quantity, supply_date, location
     FROM inventory_items ORDER BY product_name ASC"
);
if ($listQuery) {
    while ($item = $listQuery->fetch_assoc()) $inventoryItems[] = $item;
} else {
    error_log("Inventory list query error: " . $conn->error);
}

$message = trim($_GET["message"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DocuBiz - Inventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
body{background:#d8dcdb;color:#333;min-height:100vh}
.app-layout{display:flex;flex-direction:column;min-height:100vh}
.topbar{background:#122b49;min-height:60px;display:flex;align-items:center;justify-content:space-between;padding:10px 20px;color:#fff;gap:20px}
.brand-container{display:flex;align-items:center;gap:15px;min-width:180px}
.hamburger-btn,.icon-btn{background:none;border:0;color:#fff;cursor:pointer;display:flex;align-items:center}
.brand-name{font-size:1.4rem;font-weight:600}
.search-bar{position:relative;width:min(380px,35vw);display:flex;align-items:center}
.search-bar input[type=text]{width:100%;padding:8px 42px 8px 16px;border-radius:20px;border:0;outline:0;font-size:.85rem;color:#2d3748}
.search-bar input[type=text]::placeholder{color:#8fa0b5}
.search-btn{position:absolute;right:12px;background:none;border:0;color:#122b49;cursor:pointer;display:flex;align-items:center}
.topbar-actions{display:flex;align-items:center;gap:18px}
.icon-btn{padding:4px;text-decoration:none;opacity:.9}
.icon-btn:hover{opacity:1}
.user-profile{display:flex;align-items:center;gap:10px;margin-left:10px}
.profile-avatar{width:34px;height:34px;border-radius:50%;background:#2b5585;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600;color:#fff}
.profile-info{display:flex;flex-direction:column;line-height:1.2}
.user-name{font-size:.82rem;font-weight:500}
.user-role{font-size:.72rem;color:#9bb3d1}
.logout-btn{color:#fff;text-decoration:none;font-size:.8rem;opacity:.9;white-space:nowrap}
.logout-btn:hover{opacity:1;text-decoration:underline}
.main-body{display:flex;flex:1}
.sidebar{width:210px;flex-shrink:0;background:#122b49;padding-top:15px;border-top:1px solid rgba(255,255,255,.08)}
.sidebar-menu{display:flex;flex-direction:column}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#b0c4de;text-decoration:none;font-size:.88rem;transition:all .2s ease}
.menu-item:hover{color:#fff;background:rgba(255,255,255,.05)}
.menu-item.active{color:#fff;background:#274b73}
.content-area{flex:1;min-width:0;padding:30px 40px;background:#d5dad9}
.page-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px;flex-wrap:wrap}
.page-title{font-size:1.8rem;font-weight:600;color:#2a343d}
.action-buttons{display:flex;gap:10px;flex-wrap:wrap}
.btn-action,.btn-add-items{background:#72dbd9;color:#1a365d;border:0;border-radius:18px;padding:8px 16px;font-size:.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;box-shadow:0 2px 5px rgba(0,0,0,.05)}
.btn-add-items{padding:8px 18px;font-size:.85rem}
.inventory-table-card{background:#fff;border-radius:12px;padding:15px 20px;box-shadow:0 4px 15px rgba(0,0,0,.04)}
.table-scroll{width:100%;overflow-x:auto}
.inventory-table{width:100%;border-collapse:collapse;text-align:left;min-width:720px}
.inventory-table th{font-size:.72rem;font-weight:600;color:#718096;text-transform:uppercase;padding:12px 10px;border-bottom:1px solid #edf2f7}
.inventory-table td{padding:12px 10px;font-size:.85rem;color:#2d3748;vertical-align:middle;border-bottom:1px solid #f7fafc}
.inventory-table tbody tr{transition:background-color .15s ease}
.inventory-table tbody tr:hover{background:#f1f5f9}
.row-highlight{background:#f7fafc}
.inventory-table tbody tr.row-selected,
.inventory-table tbody tr.row-selected:hover{background:#cfeeea}
.table-empty{text-align:center;padding:25px!important;color:#718096!important}
.pagination{display:flex;align-items:center;gap:8px;margin-top:20px;font-size:.85rem}
.page-link{display:flex;align-items:center;justify-content:center;min-width:26px;height:26px;padding:0 6px;border-radius:4px;text-decoration:none;color:#4a5568;font-weight:500}
.page-link.active{background:#319795;color:#fff}
.filter-area{display:none;margin-bottom:15px;align-items:center;gap:14px;flex-wrap:wrap}
.filter-area label{font-size:.82rem;margin-right:6px}
.filter-area select,.filter-area input[type=date]{padding:7px 10px;border:1px solid #cbd5e0;border-radius:8px;color:#2d3748;font-size:.82rem;background:#fff}
.filter-group{display:flex;align-items:center}
.form-btn{border:0;border-radius:18px;padding:9px 18px;font-size:.82rem;font-weight:600;cursor:pointer}
.form-btn.delete{background:#e53e3e;color:#fff}
.modal-overlay{position:fixed;inset:0;background:#122b4999;display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;padding:15px}
.modal-overlay.active{opacity:1;visibility:visible}
.modal-container{width:100%;max-width:480px;background:#fff;border-radius:14px;overflow:visible;box-shadow:0 10px 30px #0003}
.modal-header{background:#122b49;padding:18px 24px;color:#fff;font-size:1.25rem;font-weight:600;border-radius:14px 14px 0 0}
.modal-body{padding:24px 28px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.85rem;font-weight:600;color:#122b49;margin-bottom:6px}
.form-control{width:100%;padding:10px 14px;border:1px solid #cbd5e0;border-radius:10px;font-size:.85rem;color:#2d3748;background:#fff}
.quantity-counter{display:flex;align-items:center;justify-content:space-between;border:1px solid #cbd5e0;border-radius:10px;padding:6px 14px}
.qty-btn{background:none;border:0;font-size:1.2rem;color:#718096;cursor:pointer}
.qty-value{font-size:.9rem;color:#a0aec0}
.custom-dropdown{position:relative}
.dropdown-select-btn{width:100%;padding:10px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;display:flex;justify-content:space-between;cursor:pointer;font-size:.85rem;color:#a0aec0}
.dropdown-list{position:absolute;top:calc(100% + 4px);left:0;width:100%;background:#fff;border:1px solid #cbd5e0;border-radius:10px;box-shadow:0 4px 12px #0002;display:none;z-index:20;max-height:200px;overflow-y:auto}
.dropdown-list.active{display:block}
.dropdown-item{padding:10px 14px;font-size:.85rem;cursor:pointer}
.dropdown-item:hover{background:#f1f5f9}
.modal-footer{display:flex;justify-content:flex-end;gap:12px;margin-top:24px}
.btn-modal-cancel,.btn-modal-save{padding:8px 20px;border-radius:8px;font-size:.85rem;cursor:pointer}
.btn-modal-cancel{border:1px solid #cbd5e0;background:#fff;color:#4a5568}
.btn-modal-save{border:0;background:#72dbd9;color:#1a365d;font-weight:600}
.notice{margin-bottom:15px;font-size:.85rem}
.error{color:#c53030}
.success{color:#276749}
.notice-box{margin-bottom:15px;padding:10px 14px;border-radius:8px;background:#e6fffa;color:#234e52;font-size:.85rem}
@media(max-width:1000px){.content-area{padding:24px 20px}.sidebar{width:60px}.sidebar .menu-item span{display:none}.sidebar .menu-item{justify-content:center;padding:13px 10px}.topbar{flex-wrap:wrap}.search-bar{width:250px}}
@media(max-width:650px){.topbar{gap:12px}.brand-container{min-width:auto}.search-bar{order:3;width:100%;max-width:none}.topbar-actions{margin-left:auto}.profile-info{display:none}.content-area{padding:20px 12px}.page-title{font-size:1.5rem}.inventory-table-card{padding:12px}.action-buttons{gap:6px}}
</style>
</head>
<body>
<div class="app-layout">
<header class="topbar">
  <div class="brand-container"><button class="hamburger-btn" type="button" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button><span class="brand-name">DocuBiz</span></div>
  <div class="search-bar">
    <input type="text" id="topSearch" placeholder="Search inventory..." aria-label="Search inventory">
    <button type="button" class="search-btn" id="searchButton" aria-label="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
  </div>
  <div class="topbar-actions">
    <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l-.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-.33-1.82A1.65 1.65 0 0 0 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 7a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V1a2 2 0 0 1 2-1 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
    </a>
    <button data-notification-bell type="button" class="icon-btn" title="Notifications" aria-label="Notifications">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
    </button>
    <div class="user-profile">
      <div class="profile-avatar"><?= inventoryEscape($avatarText) ?></div>
      <div class="profile-info">
        <span class="user-name"><?= inventoryEscape($displayName) ?></span>
        <span class="user-role"><?= inventoryEscape($displayRole) ?></span>
      </div>
    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
  </div>
</header>
<div class="main-body">
<aside class="sidebar">
<nav class="sidebar-menu">
<a href="dashboard.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
<a href="messages.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>Messages</span></a>
<a href="calendar.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span>Calendar</span></a>
<a href="projects.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg><span>Projects</span></a>
<a href="inventory.php" class="menu-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg><span>Inventory</span></a>
<a href="documents.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg><span>Documents</span></a>
<?php if ($isAdmin): ?>
<a href="staff_management.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span>Staff Management</span></a>
<a href="finances.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg><span>Finances</span></a>
<?php endif; ?>
</nav></aside>

<main class="content-area">
<div class="page-header">
  <h1 class="page-title">Inventory</h1>
  <div class="action-buttons">
    <button class="btn-action" id="sortButton" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
      Sort
    </button>
    <button class="btn-action" id="filterButton" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filter
    </button>
    <button class="btn-add-items" id="openAddItemModal" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Items
    </button>
  </div>
</div>

<?php if ($errorMessage !== ""): ?><p class="notice error"><?= inventoryEscape($errorMessage) ?></p><?php endif; ?>
<?php if (($_GET["success"] ?? "") === "added"): ?><p class="notice success">Item added successfully!</p><?php endif; ?>
<?php if ($message !== ""): ?><div class="notice-box"><?= inventoryEscape($message) ?></div><?php endif; ?>

<div class="inventory-table-card">
  <div class="filter-area" id="filterArea">
    <div class="filter-group">
      <label for="categoryFilter">Category:</label>
      <select id="categoryFilter">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?><option value="<?= inventoryEscape($cat) ?>"><?= inventoryEscape($cat) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label for="locationFilter">Location:</label>
      <select id="locationFilter">
        <option value="">All Locations</option>
        <?php foreach ($locations as $loc): ?><option value="<?= inventoryEscape($loc) ?>"><?= inventoryEscape($loc) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label for="dateFilter">Supply History:</label>
      <select id="dateFilter">
        <option value="">All Time</option>
        <option value="today">Today</option>
        <option value="last_7_days">Last 7 days</option>
        <option value="last_30_days">Last 30 days</option>
        <option value="this_year">This year</option>
        <option value="last_year">Last year</option>
        <option value="custom">Custom date range...</option>
      </select>
    </div>
    <div class="filter-group" id="customDateGroup" style="display:none; gap:8px;">
      <label for="customFrom">From</label><input type="date" id="customFrom">
      <label for="customTo">To</label><input type="date" id="customTo">
    </div>
    <button type="button" class="btn-action" id="clearFilter">Clear Filter</button>
  </div>

  <form method="post" action="inventory.php" id="bulkDeleteForm" onsubmit="return confirm('Delete all selected inventory items? This cannot be undone.');">
    <input type="hidden" name="action" value="delete_selected">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <span id="selectionCount" style="font-size:.8rem; color:#718096;">Select items using the checkboxes.</span>
      <?php if ($isAdmin): ?><button class="form-btn delete" type="submit" id="bulkDeleteButton" style="display:none;">Delete Selected</button><?php endif; ?>
    </div>

    <div class="table-scroll">
    <table class="inventory-table">
      <thead>
        <tr>
          <th style="width:40px;"><input type="checkbox" id="selectAll" aria-label="Select all items"></th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Product Code</th>
          <th>Quantity</th>
          <th>Supply History</th>
          <th>Location</th>
        </tr>
      </thead>
      <tbody id="inventoryTableBody">
      <?php foreach ($inventoryItems as $item): ?>
        <tr
          data-id="<?= (int)$item["id"] ?>"
          data-name="<?= inventoryEscape($item["product_name"]) ?>"
          data-category="<?= inventoryEscape($item["category"]) ?>"
          data-code="<?= inventoryEscape($item["product_code"] ?? "") ?>"
          data-qty="<?= inventoryEscape($item["quantity"]) ?>"
          data-date="<?= inventoryEscape($item["supply_date"] ?? "") ?>"
          data-location="<?= inventoryEscape($item["location"]) ?>"
        >
          <td><input type="checkbox" class="inventory-checkbox" name="selected_ids[]" value="<?= (int)$item["id"] ?>" aria-label="Select <?= inventoryEscape($item["product_name"]) ?>"></td>
          <td><?= inventoryEscape(strtoupper($item["product_name"])) ?></td>
          <td><?= inventoryEscape(strtoupper($item["category"])) ?></td>
          <td><?= inventoryEscape($item["product_code"] ?? "") ?></td>
          <td><?= inventoryEscape($item["quantity"]) ?></td>
          <td><?= !empty($item["supply_date"]) ? inventoryEscape(strtoupper(date("F j, Y", strtotime($item["supply_date"])))) : "—" ?></td>
          <td><?= inventoryEscape(strtoupper($item["location"])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>

  <div class="pagination" id="pagination"></div>
</div>
</main></div></div>

<div class="modal-overlay" id="addItemModal"><div class="modal-container"><div class="modal-header">Add Item</div><div class="modal-body"><form method="POST" action="inventory.php" id="addItemForm"><input type="hidden" name="action" value="add_item"><input type="hidden" name="quantity" id="quantityInput" value="0"><input type="hidden" name="category" id="categoryInput" value=""><input type="hidden" name="location" id="locationInput" value=""><div class="form-group"><label for="productName">Product Name</label><input type="text" name="product_name" id="productName" class="form-control" placeholder="Enter product name" maxlength="150" required></div><div class="form-group"><label>Quantity</label><div class="quantity-counter"><button type="button" class="qty-btn" id="qtyMinus">−</button><span class="qty-value" id="qtyDisplay">0</span><button type="button" class="qty-btn" id="qtyPlus">+</button></div></div><div class="form-group"><label>Category</label><div class="custom-dropdown"><div class="dropdown-select-btn" id="categorySelectBtn" role="button" tabindex="0"><span id="categorySelectedText">Item type</span><span id="categoryArrow">⌄</span></div><div class="dropdown-list" id="categoryList"><?php foreach ($categories as $cat): ?><div class="dropdown-item" data-value="<?= inventoryEscape($cat) ?>"><?= inventoryEscape($cat) ?></div><?php endforeach; ?></div></div></div><div class="form-group"><label>Location</label><div class="custom-dropdown"><div class="dropdown-select-btn" id="locationSelectBtn" role="button" tabindex="0"><span id="locationSelectedText">Item location</span><span id="locationArrow">⌄</span></div><div class="dropdown-list" id="locationList"><?php foreach ($locations as $loc): ?><div class="dropdown-item" data-value="<?= inventoryEscape($loc) ?>"><?= inventoryEscape($loc) ?></div><?php endforeach; ?></div></div></div><div class="modal-footer"><button type="button" class="btn-modal-cancel" id="closeAddItemModal">Cancel</button><button type="submit" class="btn-modal-save">Save Item</button></div></form></div></div></div>

<script>
/* ---------- Table: search, sort, filter, pagination, selection ---------- */
const searchInput = document.getElementById("topSearch");
const tableBody = document.getElementById("inventoryTableBody");
const allRows = Array.from(tableBody.querySelectorAll("tr"));
const pagination = document.getElementById("pagination");
const selectAll = document.getElementById("selectAll");
const selectionCount = document.getElementById("selectionCount");
const bulkDeleteButton = document.getElementById("bulkDeleteButton"); // only exists for admins
const bulkDeleteForm = document.getElementById("bulkDeleteForm");
const filterArea = document.getElementById("filterArea");
const categoryFilter = document.getElementById("categoryFilter");
const locationFilter = document.getElementById("locationFilter");
const dateFilter = document.getElementById("dateFilter");
const customDateGroup = document.getElementById("customDateGroup");
const customFrom = document.getElementById("customFrom");
const customTo = document.getElementById("customTo");

let sortAscending = true;   // rows arrive from the server sorted A-Z by product name
let currentPage = 1;
const rowsPerPage = 8;
let visibleRows = [...allRows];

function getRowData(row) {
    return {
        id: row.dataset.id || "",
        name: row.dataset.name || "",
        category: row.dataset.category || "",
        code: row.dataset.code || "",
        qty: row.dataset.qty || "",
        date: row.dataset.date || "",
        location: row.dataset.location || ""
    };
}

function formatDate(d) {
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + day;
}

function matchesDate(dateStr) {
    const mode = dateFilter.value;
    if (!mode) return true;
    if (!dateStr) return false;

    const now = new Date();
    const today = formatDate(now);

    if (mode === "today") return dateStr === today;

    if (mode === "last_7_days" || mode === "last_30_days") {
        const days = mode === "last_7_days" ? 7 : 30;
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - days);
        return dateStr >= formatDate(start);
    }

    if (mode === "this_year") return dateStr.startsWith(String(now.getFullYear()));
    if (mode === "last_year") return dateStr.startsWith(String(now.getFullYear() - 1));

    if (mode === "custom") {
        const from = customFrom.value, to = customTo.value;
        if (!from || !to || from > to) return true; // ignore until a valid range is chosen
        return dateStr >= from && dateStr <= to;
    }
    return true;
}

function applySort() {
    visibleRows.sort((a, b) => {
        const comparison = a.dataset.name.localeCompare(b.dataset.name);
        return sortAscending ? comparison : -comparison;
    });
}

function updateTable() {
    const query = searchInput.value.trim().toLowerCase();

    visibleRows = allRows.filter(row => {
        const data = getRowData(row);
        const searchable = [data.name, data.category, data.code, data.location]
            .join(" ").toLowerCase();

        return searchable.includes(query) &&
            (!categoryFilter.value || data.category === categoryFilter.value) &&
            (!locationFilter.value || data.location === locationFilter.value) &&
            matchesDate(data.date);
    });

    applySort();
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
        row.classList.toggle("row-highlight", index % 2 === 1);
        tableBody.appendChild(row);
    });

    if (visibleRows.length === 0) {
        let emptyRow = document.getElementById("emptyInventoryRow");
        if (!emptyRow) {
            emptyRow = document.createElement("tr");
            emptyRow.id = "emptyInventoryRow";
            const text = allRows.length === 0
                ? "No inventory items yet. Click Add Items to add your first product."
                : "No inventory items match your search or filter.";
            emptyRow.innerHTML = '<td colspan="7" class="table-empty">' + text + '</td>';
        }
        tableBody.appendChild(emptyRow);
    } else {
        const emptyRow = document.getElementById("emptyInventoryRow");
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
    const checked = tableBody.querySelectorAll(".inventory-checkbox:checked");
    selectionCount.textContent = checked.length
        ? checked.length + " item(s) selected."
        : "Select items using the checkboxes.";

    if (bulkDeleteButton) {
        bulkDeleteButton.style.display = checked.length ? "inline-block" : "none";
    }

    selectAll.checked = visibleRows.length > 0 &&
        visibleRows.every(row => row.querySelector(".inventory-checkbox").checked);

    selectAll.indeterminate = checked.length > 0 && !selectAll.checked;

    allRows.forEach(row => {
        const checkbox = row.querySelector(".inventory-checkbox");
        row.classList.toggle("row-selected", !!checkbox && checkbox.checked);
    });
}

searchInput.addEventListener("input", updateTable);
document.getElementById("searchButton").addEventListener("click", updateTable);

document.getElementById("sortButton").addEventListener("click", () => {
    sortAscending = !sortAscending;
    applySort();
    currentPage = 1;
    renderPage();
});

document.getElementById("filterButton").addEventListener("click", () => {
    filterArea.style.display = filterArea.style.display === "flex" ? "none" : "flex";
});

categoryFilter.addEventListener("change", updateTable);
locationFilter.addEventListener("change", updateTable);
dateFilter.addEventListener("change", () => {
    customDateGroup.style.display = dateFilter.value === "custom" ? "flex" : "none";
    updateTable();
});
customFrom.addEventListener("change", updateTable);
customTo.addEventListener("change", updateTable);

document.getElementById("clearFilter").addEventListener("click", () => {
    categoryFilter.value = "";
    locationFilter.value = "";
    dateFilter.value = "";
    customFrom.value = "";
    customTo.value = "";
    customDateGroup.style.display = "none";
    searchInput.value = "";
    updateTable();
});

selectAll.addEventListener("change", () => {
    visibleRows.forEach(row => {
        row.querySelector(".inventory-checkbox").checked = selectAll.checked;
    });
    updateSelectionDisplay();
});

tableBody.addEventListener("change", event => {
    if (event.target.classList.contains("inventory-checkbox")) {
        updateSelectionDisplay();
    }
});

/* Ensure bulk delete sends only selected record IDs. */
bulkDeleteForm.addEventListener("submit", event => {
    bulkDeleteForm.querySelectorAll('input[name="selected_ids[]"]').forEach(input => {
        if (input.type === "hidden") input.remove();
    });

    const checked = tableBody.querySelectorAll(".inventory-checkbox:checked");
    if (!checked.length) {
        event.preventDefault();
        alert("Please select at least one item.");
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

/* ---------- Add Item modal (unchanged behaviour) ---------- */
const modal = document.getElementById('addItemModal');
document.getElementById('openAddItemModal').addEventListener('click', () => modal.classList.add('active'));
document.getElementById('closeAddItemModal').addEventListener('click', () => modal.classList.remove('active'));
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });

let currentQty = 0;
const qtyDisplay = document.getElementById('qtyDisplay'), quantityInput = document.getElementById('quantityInput');
document.getElementById('qtyPlus').addEventListener('click', () => {
    currentQty++; qtyDisplay.textContent = currentQty; quantityInput.value = currentQty; qtyDisplay.style.color = '#2d3748';
});
document.getElementById('qtyMinus').addEventListener('click', () => {
    if (currentQty > 0) currentQty--;
    qtyDisplay.textContent = currentQty; quantityInput.value = currentQty;
    qtyDisplay.style.color = currentQty === 0 ? '#a0aec0' : '#2d3748';
});

function setupDropdown(btnId, listId, textId, arrowId, inputId, otherListId, otherArrowId) {
    const btn = document.getElementById(btnId), list = document.getElementById(listId),
          label = document.getElementById(textId), arrow = document.getElementById(arrowId),
          input = document.getElementById(inputId);
    btn.addEventListener('click', e => {
        e.stopPropagation();
        list.classList.toggle('active');
        document.getElementById(otherListId).classList.remove('active');
        arrow.textContent = list.classList.contains('active') ? '⌃' : '⌄';
        document.getElementById(otherArrowId).textContent = '⌄';
    });
    list.querySelectorAll('.dropdown-item').forEach(item => item.addEventListener('click', e => {
        e.stopPropagation();
        const v = item.dataset.value;
        label.textContent = v; label.style.color = '#2d3748'; input.value = v;
        list.classList.remove('active'); arrow.textContent = '⌄';
    }));
}
setupDropdown('categorySelectBtn', 'categoryList', 'categorySelectedText', 'categoryArrow', 'categoryInput', 'locationList', 'locationArrow');
setupDropdown('locationSelectBtn', 'locationList', 'locationSelectedText', 'locationArrow', 'locationInput', 'categoryList', 'categoryArrow');

document.getElementById('addItemForm').addEventListener('submit', e => {
    if (!document.getElementById('categoryInput').value) { e.preventDefault(); alert('Please select a category.'); }
    else if (!document.getElementById('locationInput').value) { e.preventDefault(); alert('Please select a location.'); }
});

window.addEventListener('click', () => {
    document.getElementById('categoryList').classList.remove('active');
    document.getElementById('locationList').classList.remove('active');
    document.getElementById('categoryArrow').textContent = '⌄';
    document.getElementById('locationArrow').textContent = '⌄';
});

updateTable();
</script>
<?php require __DIR__.'/notification_panel.php'; ?>
</body></html>