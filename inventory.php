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
$search = trim($_GET["q"] ?? "");
$categoryFilter = trim($_GET["category"] ?? "");
$locationFilter = trim($_GET["location"] ?? "");
$dateFilter = trim($_GET["date_filter"] ?? "");
$customFrom = trim($_GET["custom_from"] ?? "");
$customTo = trim($_GET["custom_to"] ?? "");
$sort = $_GET["sort"] ?? "name_asc";
$sortOptions = [
    "name_asc" => "product_name ASC", "name_desc" => "product_name DESC",
    "category_asc" => "category ASC", "category_desc" => "category DESC",
    "code_asc" => "product_code ASC", "code_desc" => "product_code DESC",
    "quantity_asc" => "quantity ASC", "quantity_desc" => "quantity DESC",
    "date_asc" => "supply_date ASC, id ASC", "date_desc" => "supply_date DESC, id DESC",
    "location_asc" => "location ASC", "location_desc" => "location DESC"
];
if (!array_key_exists($sort, $sortOptions)) $sort = "name_asc";
if (!in_array($categoryFilter, $categories, true)) $categoryFilter = "";
if (!in_array($locationFilter, $locations, true)) $locationFilter = "";
$itemsPerPage = 8;
$page = max(1, (int)($_GET["page"] ?? 1));
$offset = ($page - 1) * $itemsPerPage;
$searchLike = "%" . $search . "%";
$dateCondition = "";
$useCustomDates = ($dateFilter === "custom" && $customFrom !== "" && $customTo !== "" && $customFrom <= $customTo);
if ($dateFilter === "today") $dateCondition = " AND supply_date = CURDATE()";
elseif ($dateFilter === "last_7_days") $dateCondition = " AND supply_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
elseif ($dateFilter === "last_30_days") $dateCondition = " AND supply_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
elseif ($dateFilter === "this_year") $dateCondition = " AND YEAR(supply_date) = YEAR(CURDATE())";
elseif ($dateFilter === "last_year") $dateCondition = " AND YEAR(supply_date) = YEAR(CURDATE()) - 1";
elseif ($useCustomDates) $dateCondition = " AND supply_date BETWEEN ? AND ?";
else $dateFilter = "";
// Shared inventory: intentionally no WHERE user_id = ? on count/list queries.
$countSql = "SELECT COUNT(*) AS total FROM inventory_items
             WHERE (? = '' OR product_name LIKE ? OR product_code LIKE ? OR category LIKE ? OR location LIKE ?)
             AND (? = '' OR category = ?) AND (? = '' OR location = ?)" . $dateCondition;
$countStmt = $conn->prepare($countSql);
if (!$countStmt) { http_response_code(500); exit("Inventory count query failed: " . inventoryEscape($conn->error)); }
if ($useCustomDates) {
    $countStmt->bind_param("sssssssssss", $search, $searchLike, $searchLike, $searchLike, $searchLike, $categoryFilter, $categoryFilter, $locationFilter, $locationFilter, $customFrom, $customTo);
} else {
    $countStmt->bind_param("sssssssss", $search, $searchLike, $searchLike, $searchLike, $searchLike, $categoryFilter, $categoryFilter, $locationFilter, $locationFilter);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalItems = (int)($countResult->fetch_assoc()["total"] ?? 0);
$countStmt->close();
$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $itemsPerPage; }
$listSql = "SELECT id, product_name, category, product_code, quantity, supply_date, location
            FROM inventory_items
            WHERE (? = '' OR product_name LIKE ? OR product_code LIKE ? OR category LIKE ? OR location LIKE ?)
            AND (? = '' OR category = ?) AND (? = '' OR location = ?)" . $dateCondition . "
            ORDER BY " . $sortOptions[$sort] . " LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
if (!$listStmt) { http_response_code(500); exit("Inventory list query failed: " . inventoryEscape($conn->error)); }
if ($useCustomDates) {
    $listStmt->bind_param("sssssssssssii", $search, $searchLike, $searchLike, $searchLike, $searchLike, $categoryFilter, $categoryFilter, $locationFilter, $locationFilter, $customFrom, $customTo, $itemsPerPage, $offset);
} else {
    $listStmt->bind_param("sssssssssii", $search, $searchLike, $searchLike, $searchLike, $searchLike, $categoryFilter, $categoryFilter, $locationFilter, $locationFilter, $itemsPerPage, $offset);
}
$listStmt->execute();
$itemsResult = $listStmt->get_result();
$inventoryItems = [];
while ($item = $itemsResult->fetch_assoc()) $inventoryItems[] = $item;
$listStmt->close();
function inventoryPageUrl($pageNumber, $search, $categoryFilter, $locationFilter, $dateFilter, $customFrom, $customTo, $sort) {
    return "inventory.php?" . http_build_query(["page"=>$pageNumber,"q"=>$search,"category"=>$categoryFilter,"location"=>$locationFilter,"date_filter"=>$dateFilter,"custom_from"=>$customFrom,"custom_to"=>$customTo,"sort"=>$sort]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DocuBiz - Inventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}body{background:#d8dcdb;color:#333;min-height:100vh}.app-layout{display:flex;flex-direction:column;min-height:100vh}.topbar{background:#122b49;min-height:60px;display:flex;align-items:center;justify-content:space-between;padding:10px 20px;color:#fff;gap:20px}.brand-container{display:flex;align-items:center;gap:15px;min-width:180px}.hamburger-btn,.icon-btn{background:none;border:0;color:#fff;cursor:pointer;display:flex;align-items:center}.brand-name{font-size:1.4rem;font-weight:600}.search-bar{position:relative;width:min(380px,35vw);display:flex;align-items:center}.search-bar input[type=text]{width:100%;padding:8px 42px 8px 16px;border-radius:20px;border:0;outline:0;font-size:.85rem;color:#2d3748}.search-bar input[type=text]::placeholder{color:#8fa0b5}.search-btn{position:absolute;right:12px;background:none;border:0;color:#122b49;cursor:pointer}.topbar-actions{display:flex;align-items:center;gap:18px}.icon-btn{padding:4px;text-decoration:none;opacity:0.9}.icon-btn:hover{opacity:1}.user-profile{display:flex;align-items:center;gap:10px;margin-left:10px}.profile-avatar{width:34px;height:34px;border-radius:50%;background:#2b5585;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600;color:#fff}.profile-info{display:flex;flex-direction:column;line-height:1.2}.user-name{font-size:.82rem;font-weight:500}.user-role{font-size:.72rem;color:#9bb3d1}.logout-btn{color:#fff;text-decoration:none;font-size:.8rem;opacity:0.9;white-space:nowrap}.logout-btn:hover{opacity:1;text-decoration:underline}.main-body{display:flex;flex:1}.sidebar{width:210px;flex-shrink:0;background:#122b49;padding-top:15px;border-top:1px solid rgba(255,255,255,0.08)}.sidebar-menu{display:flex;flex-direction:column}.menu-item{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#b0c4de;text-decoration:none;font-size:.88rem;transition:all 0.2s ease}.menu-item:hover{color:#fff;background:rgba(255,255,255,0.05)}.menu-item.active{color:#fff;background:#274b73}.content-area{flex:1;min-width:0;padding:30px 40px;background:#d5dad9}.page-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px;flex-wrap:wrap}.page-title{font-size:1.8rem;font-weight:600;color:#2a343d}.action-buttons{display:flex;gap:10px;position:relative;flex-wrap:wrap}.btn-action,.btn-add-items{background:#72dbd9;color:#1a365d;border:0;border-radius:18px;padding:8px 16px;font-size:.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap}.btn-add-items{padding:8px 18px;font-size:.85rem}.filter-dropdown-menu,.sort-dropdown-menu{position:absolute;top:calc(100% + 6px);background:#fff;border-radius:12px;box-shadow:0 10px 25px #0002;width:170px;padding:6px 0;display:none;z-index:200}.sort-dropdown-menu{left:0}.filter-dropdown-menu{right:90px}.filter-dropdown-menu.active,.sort-dropdown-menu.active{display:block}.filter-menu-item,.sort-menu-item{position:relative;display:flex;align-items:center;justify-content:space-between;padding:8px 12px;font-size:.8rem;color:#2d3748;cursor:pointer;white-space:nowrap}.filter-menu-item:hover,.sort-menu-item:hover{background:#f1f5f9;color:#122b49}.filter-submenu,.sort-submenu{position:absolute;top:-6px;left:calc(100% + 4px);background:#fff;border-radius:12px;box-shadow:0 10px 25px #0002;width:165px;padding:6px 0;display:none;z-index:210}.filter-menu-item:hover>.filter-submenu,.sort-menu-item:hover>.sort-submenu,.filter-submenu.active,.sort-submenu.active{display:block}.inventory-table-card{background:#fff;border-radius:12px;padding:15px 20px;box-shadow:0 4px 15px #0001;overflow-x:auto}.inventory-table{width:100%;border-collapse:collapse;text-align:left;min-width:680px}.inventory-table th{font-size:.72rem;font-weight:600;color:#718096;text-transform:uppercase;padding:12px 10px;border-bottom:1px solid #edf2f7}.inventory-table td{padding:12px 10px;font-size:.85rem;color:#2d3748;vertical-align:middle;border-bottom:1px solid #f7fafc}.row-highlight{background:#b2f5ea}.pagination{display:flex;align-items:center;gap:8px;margin-top:20px;font-size:.85rem}.page-link{display:flex;align-items:center;justify-content:center;min-width:26px;height:26px;padding:0 6px;border-radius:4px;text-decoration:none;color:#4a5568;font-weight:500}.page-link.active{background:#319795;color:#fff}.modal-overlay,.date-popout-overlay{position:fixed;inset:0;background:#122b4999;display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;padding:15px}.modal-overlay.active,.date-popout-overlay.active{opacity:1;visibility:visible}.modal-container,.date-popout-box{width:100%;max-width:480px;background:#fff;border-radius:14px;overflow:visible;box-shadow:0 10px 30px #0003}.modal-header{background:#122b49;padding:18px 24px;color:#fff;font-size:1.25rem;font-weight:600;border-radius:14px 14px 0 0}.modal-body{padding:24px 28px}.form-group{margin-bottom:18px}.form-group label{display:block;font-size:.85rem;font-weight:600;color:#122b49;margin-bottom:6px}.form-control{width:100%;padding:10px 14px;border:1px solid #cbd5e0;border-radius:10px;font-size:.85rem;color:#2d3748;background:#fff}.quantity-counter{display:flex;align-items:center;justify-content:space-between;border:1px solid #cbd5e0;border-radius:10px;padding:6px 14px}.qty-btn{background:none;border:0;font-size:1.2rem;color:#718096;cursor:pointer}.qty-value{font-size:.9rem;color:#a0aec0}.custom-dropdown{position:relative}.dropdown-select-btn{width:100%;padding:10px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;display:flex;justify-content:space-between;cursor:pointer;font-size:.85rem;color:#a0aec0}.dropdown-list{position:absolute;top:calc(100% + 4px);left:0;width:100%;background:#fff;border:1px solid #cbd5e0;border-radius:10px;box-shadow:0 4px 12px #0002;display:none;z-index:20;max-height:200px;overflow-y:auto}.dropdown-list.active{display:block}.dropdown-item{padding:10px 14px;font-size:.85rem;cursor:pointer}.dropdown-item:hover{background:#f1f5f9}.modal-footer,.date-popout-footer{display:flex;justify-content:flex-end;gap:12px;margin-top:24px}.btn-modal-cancel,.btn-modal-save{padding:8px 20px;border-radius:8px;font-size:.85rem;cursor:pointer}.btn-modal-cancel{border:1px solid #cbd5e0;background:#fff;color:#4a5568}.btn-modal-save{border:0;background:#72dbd9;color:#1a365d;font-weight:600}.date-popout-box{max-width:380px;padding:20px}.date-popout-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.date-popout-header h3{font-size:1rem;color:#1e293b}.date-close-btn{background:none;border:0;color:#e53e3e;font-size:1.2rem;cursor:pointer}.date-popout-body label{display:block;font-size:.78rem;font-weight:600;color:#64748b;margin-bottom:4px}.date-popout-body input{width:100%;padding:8px 12px;border:1px solid #cbd5e0;border-radius:8px}.notice{margin-bottom:15px;font-size:.85rem}.error{color:#c53030}.success{color:#276749}@media(max-width:1000px){.content-area{padding:24px 20px}.sidebar{width:60px}.sidebar .menu-item span{display:none}.sidebar .menu-item{justify-content:center;padding:13px 10px}.topbar{flex-wrap:wrap}.search-bar{width:250px}}@media(max-width:650px){.topbar{gap:12px}.brand-container{min-width:auto}.search-bar{order:3;width:100%;max-width:none}.topbar-actions{margin-left:auto}.profile-info{display:none}.content-area{padding:20px 12px}.page-title{font-size:1.5rem}.inventory-table-card{padding:12px}.action-buttons{gap:6px}}
</style>
</head>
<body>
<div class="app-layout">
<header class="topbar">
  <div class="brand-container"><button class="hamburger-btn" type="button" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button><span class="brand-name">DocuBiz</span></div>
  <form class="search-bar" method="GET" action="inventory.php"><input type="text" name="q" value="<?= inventoryEscape($search) ?>" placeholder="Search inventory..."><input type="hidden" name="category" value="<?= inventoryEscape($categoryFilter) ?>"><input type="hidden" name="location" value="<?= inventoryEscape($locationFilter) ?>"><input type="hidden" name="date_filter" value="<?= inventoryEscape($dateFilter) ?>"><input type="hidden" name="custom_from" value="<?= inventoryEscape($customFrom) ?>"><input type="hidden" name="custom_to" value="<?= inventoryEscape($customTo) ?>"><input type="hidden" name="sort" value="<?= inventoryEscape($sort) ?>"><button type="submit" class="search-btn" aria-label="Search">⌕</button></form>
  <div class="topbar-actions">
    <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l-.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-.33-1.82A1.65 1.65 0 0 0 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 7a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V1a2 2 0 0 1 2-1 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
    </a>
    <button type="button" class="icon-btn" title="Notifications" aria-label="Notifications">
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
<?php endif; ?>
<a href="finances.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg><span>Finances</span></a>
</nav></aside>
<main class="content-area">
<div class="page-header"><h1 class="page-title">Inventory</h1><div class="action-buttons">
<button class="btn-action" type="button" id="sortButton">☷ Sort</button>
<div class="sort-dropdown-menu" id="sortDropdownMenu"><div class="sort-menu-item">Ascending <span>›</span><div class="sort-submenu"><?php foreach (["name_asc"=>"Product Name","category_asc"=>"Category","code_asc"=>"Product Code","quantity_asc"=>"Quantity","date_asc"=>"Supply History","location_asc"=>"Location"] as $key=>$label): ?><div class="sort-menu-item sort-opt" data-sort="<?= inventoryEscape($key) ?>"><?= inventoryEscape($label) ?></div><?php endforeach; ?></div></div><div class="sort-menu-item">Descending <span>›</span><div class="sort-submenu"><?php foreach (["name_desc"=>"Product Name","category_desc"=>"Category","code_desc"=>"Product Code","quantity_desc"=>"Quantity","date_desc"=>"Supply History","location_desc"=>"Location"] as $key=>$label): ?><div class="sort-menu-item sort-opt" data-sort="<?= inventoryEscape($key) ?>"><?= inventoryEscape($label) ?></div><?php endforeach; ?></div></div></div>
<button class="btn-action" type="button" id="filterButton">▽ Filter</button>
<div class="filter-dropdown-menu" id="filterDropdownMenu"><div class="filter-menu-item">Category <span>›</span><div class="filter-submenu"><?php foreach ([""=>"All Categories", "Baking Supplies"=>"Baking Supplies", "Beverages"=>"Beverages", "Condiments"=>"Condiments", "Dairy"=>"Dairy", "Dry Goods"=>"Dry Goods", "Meat & Poultry"=>"Meat & Poultry"] as $key=>$label): ?><div class="filter-menu-item category-opt" data-cat="<?= inventoryEscape($key) ?>"><?= inventoryEscape($label) ?></div><?php endforeach; ?></div></div><div class="filter-menu-item">Supply History <span>›</span><div class="filter-submenu"><?php foreach ([""=>"All Time","today"=>"Today","last_7_days"=>"Last 7 days","last_30_days"=>"Last 30 days","this_year"=>"This year","last_year"=>"Last year"] as $key=>$label): ?><div class="filter-menu-item supply-opt" data-date="<?= inventoryEscape($key) ?>"><?= inventoryEscape($label) ?></div><?php endforeach; ?><div class="filter-menu-item" id="customDateRangeTrigger">Custom date range...</div></div></div><div class="filter-menu-item">Location <span>›</span><div class="filter-submenu"><?php foreach ([""=>"All Locations","Main Storage"=>"Main Storage","Cold Storage"=>"Cold Storage"] as $key=>$label): ?><div class="filter-menu-item location-opt" data-loc="<?= inventoryEscape($key) ?>"><?= inventoryEscape($label) ?></div><?php endforeach; ?></div></div></div>
<button class="btn-add-items" id="openAddItemModal" type="button">＋ Add Items</button></div></div>
<?php if ($errorMessage !== ""): ?><p class="notice error"><?= inventoryEscape($errorMessage) ?></p><?php endif; ?><?php if (($_GET["success"] ?? "") === "added"): ?><p class="notice success">Item added successfully!</p><?php endif; ?>
<div class="inventory-table-card"><table class="inventory-table"><thead><tr><th>Product Name</th><th>Category</th><th>Product Code</th><th>Quantity</th><th>Supply History</th><th>Location</th></tr></thead><tbody>
<?php if ($inventoryItems): foreach ($inventoryItems as $index=>$item): ?><tr class="<?= $index % 2 === 1 ? 'row-highlight' : '' ?>"><td><?= inventoryEscape(strtoupper($item["product_name"])) ?></td><td><?= inventoryEscape(strtoupper($item["category"])) ?></td><td><?= inventoryEscape($item["product_code"] ?? "") ?></td><td><?= inventoryEscape($item["quantity"]) ?></td><td><?= !empty($item["supply_date"]) ? inventoryEscape(strtoupper(date("F j, Y", strtotime($item["supply_date"])))) : "—" ?></td><td><?= inventoryEscape(strtoupper($item["location"])) ?></td></tr><?php endforeach; else: ?><tr><td colspan="6" style="text-align:center;padding:25px;"><?= ($search !== "" || $categoryFilter !== "" || $locationFilter !== "" || $dateFilter !== "") ? "No inventory items match your search or filter." : "No inventory items yet. Click Add Items to add your first product." ?></td></tr><?php endif; ?>
</tbody></table><div class="pagination"><?php if ($page > 1): ?><a class="page-link" href="<?= inventoryEscape(inventoryPageUrl($page-1,$search,$categoryFilter,$locationFilter,$dateFilter,$customFrom,$customTo,$sort)) ?>">&lt;</a><?php else: ?><span class="page-link">&lt;</span><?php endif; ?><?php for ($p=1;$p<=$totalPages;$p++): ?><a class="page-link <?= $p===$page?'active':'' ?>" href="<?= inventoryEscape(inventoryPageUrl($p,$search,$categoryFilter,$locationFilter,$dateFilter,$customFrom,$customTo,$sort)) ?>"><?= $p ?></a><?php endfor; ?><?php if ($page < $totalPages): ?><a class="page-link" href="<?= inventoryEscape(inventoryPageUrl($page+1,$search,$categoryFilter,$locationFilter,$dateFilter,$customFrom,$customTo,$sort)) ?>">&gt;</a><?php else: ?><span class="page-link">&gt;</span><?php endif; ?></div></div>
</main></div></div>
<div class="date-popout-overlay" id="customDateModal"><div class="date-popout-box"><div class="date-popout-header"><h3>Filter</h3><button type="button" class="date-close-btn" id="closeDateModal">&times;</button></div><div class="date-popout-body"><p style="font-size:.8rem;font-weight:600;margin-bottom:10px">Supply History</p><div class="form-group"><label for="customFromDate">From</label><input type="date" id="customFromDate" value="<?= inventoryEscape($customFrom) ?>" required></div><div class="form-group"><label for="customToDate">To</label><input type="date" id="customToDate" value="<?= inventoryEscape($customTo) ?>" required></div></div><div class="date-popout-footer"><button type="button" class="btn-modal-cancel" id="cancelDateModal">Cancel</button><button type="button" class="btn-modal-save" id="applyDateModal">Apply</button></div></div></div>
<div class="modal-overlay" id="addItemModal"><div class="modal-container"><div class="modal-header">Add Item</div><div class="modal-body"><form method="POST" action="inventory.php" id="addItemForm"><input type="hidden" name="action" value="add_item"><input type="hidden" name="quantity" id="quantityInput" value="0"><input type="hidden" name="category" id="categoryInput" value=""><input type="hidden" name="location" id="locationInput" value=""><div class="form-group"><label for="productName">Product Name</label><input type="text" name="product_name" id="productName" class="form-control" placeholder="Enter product name" maxlength="150" required></div><div class="form-group"><label>Quantity</label><div class="quantity-counter"><button type="button" class="qty-btn" id="qtyMinus">−</button><span class="qty-value" id="qtyDisplay">0</span><button type="button" class="qty-btn" id="qtyPlus">+</button></div></div><div class="form-group"><label>Category</label><div class="custom-dropdown"><div class="dropdown-select-btn" id="categorySelectBtn" role="button" tabindex="0"><span id="categorySelectedText">Item type</span><span id="categoryArrow">⌄</span></div><div class="dropdown-list" id="categoryList"><?php foreach ($categories as $cat): ?><div class="dropdown-item" data-value="<?= inventoryEscape($cat) ?>"><?= inventoryEscape($cat) ?></div><?php endforeach; ?></div></div></div><div class="form-group"><label>Location</label><div class="custom-dropdown"><div class="dropdown-select-btn" id="locationSelectBtn" role="button" tabindex="0"><span id="locationSelectedText">Item location</span><span id="locationArrow">⌄</span></div><div class="dropdown-list" id="locationList"><?php foreach ($locations as $loc): ?><div class="dropdown-item" data-value="<?= inventoryEscape($loc) ?>"><?= inventoryEscape($loc) ?></div><?php endforeach; ?></div></div></div><div class="modal-footer"><button type="button" class="btn-modal-cancel" id="closeAddItemModal">Cancel</button><button type="submit" class="btn-modal-save">Save Item</button></div></form></div></div></div>
<script>
const paramsBase = {q: <?= json_encode($search) ?>, category: <?= json_encode($categoryFilter) ?>, location: <?= json_encode($locationFilter) ?>, date_filter: <?= json_encode($dateFilter) ?>, custom_from: <?= json_encode($customFrom) ?>, custom_to: <?= json_encode($customTo) ?>, sort: <?= json_encode($sort) ?>};
function goWith(changes){const p=new URLSearchParams({...paramsBase,...changes});p.delete('page');window.location.href='inventory.php?'+p.toString();}
const modal=document.getElementById('addItemModal');document.getElementById('openAddItemModal').addEventListener('click',()=>modal.classList.add('active'));document.getElementById('closeAddItemModal').addEventListener('click',()=>modal.classList.remove('active'));modal.addEventListener('click',e=>{if(e.target===modal)modal.classList.remove('active')});
let currentQty=0;const qtyDisplay=document.getElementById('qtyDisplay'),quantityInput=document.getElementById('quantityInput');document.getElementById('qtyPlus').addEventListener('click',()=>{currentQty++;qtyDisplay.textContent=currentQty;quantityInput.value=currentQty;qtyDisplay.style.color='#2d3748'});document.getElementById('qtyMinus').addEventListener('click',()=>{if(currentQty>0)currentQty--;qtyDisplay.textContent=currentQty;quantityInput.value=currentQty;qtyDisplay.style.color=currentQty===0?'#a0aec0':'#2d3748'});
function setupDropdown(btnId,listId,textId,arrowId,inputId,otherListId,otherArrowId){const btn=document.getElementById(btnId),list=document.getElementById(listId),label=document.getElementById(textId),arrow=document.getElementById(arrowId),input=document.getElementById(inputId);btn.addEventListener('click',e=>{e.stopPropagation();list.classList.toggle('active');document.getElementById(otherListId).classList.remove('active');arrow.textContent=list.classList.contains('active')?'⌃':'⌄';document.getElementById(otherArrowId).textContent='⌄'});list.querySelectorAll('.dropdown-item').forEach(item=>item.addEventListener('click',e=>{e.stopPropagation();const v=item.dataset.value;label.textContent=v;label.style.color='#2d3748';input.value=v;list.classList.remove('active');arrow.textContent='⌄'}));}
setupDropdown('categorySelectBtn','categoryList','categorySelectedText','categoryArrow','categoryInput','locationList','locationArrow');setupDropdown('locationSelectBtn','locationList','locationSelectedText','locationArrow','locationInput','categoryList','categoryArrow');
document.getElementById('addItemForm').addEventListener('submit',e=>{if(!document.getElementById('categoryInput').value){e.preventDefault();alert('Please select a category.')}else if(!document.getElementById('locationInput').value){e.preventDefault();alert('Please select a location.')}});
const sortButton=document.getElementById('sortButton'),sortMenu=document.getElementById('sortDropdownMenu'),filterButton=document.getElementById('filterButton'),filterMenu=document.getElementById('filterDropdownMenu');sortButton.addEventListener('click',e=>{e.stopPropagation();sortMenu.classList.toggle('active');filterMenu.classList.remove('active')});filterButton.addEventListener('click',e=>{e.stopPropagation();filterMenu.classList.toggle('active');sortMenu.classList.remove('active')});
document.querySelectorAll('.sort-opt').forEach(el=>el.addEventListener('click',e=>{e.stopPropagation();goWith({sort:el.dataset.sort})}));document.querySelectorAll('.category-opt').forEach(el=>el.addEventListener('click',e=>{e.stopPropagation();goWith({category:el.dataset.cat})}));document.querySelectorAll('.supply-opt').forEach(el=>el.addEventListener('click',e=>{e.stopPropagation();goWith({date_filter:el.dataset.date,custom_from:'',custom_to:''})}));document.querySelectorAll('.location-opt').forEach(el=>el.addEventListener('click',e=>{e.stopPropagation();goWith({location:el.dataset.loc})}));
const dateModal=document.getElementById('customDateModal');document.getElementById('customDateRangeTrigger').addEventListener('click',e=>{e.stopPropagation();filterMenu.classList.remove('active');dateModal.classList.add('active')});function closeDate(){dateModal.classList.remove('active')}document.getElementById('closeDateModal').addEventListener('click',closeDate);document.getElementById('cancelDateModal').addEventListener('click',closeDate);dateModal.addEventListener('click',e=>{if(e.target===dateModal)closeDate()});document.getElementById('applyDateModal').addEventListener('click',()=>{const from=document.getElementById('customFromDate').value,to=document.getElementById('customToDate').value;if(!from||!to){alert('Please select both From and To dates.');return}if(from>to){alert('The From date must not be later than the To date.');return}goWith({date_filter:'custom',custom_from:from,custom_to:to})});window.addEventListener('click',()=>{sortMenu.classList.remove('active');filterMenu.classList.remove('active');document.getElementById('categoryList').classList.remove('active');document.getElementById('locationList').classList.remove('active');document.getElementById('categoryArrow').textContent='⌄';document.getElementById('locationArrow').textContent='⌄'});
</script>
</body></html>