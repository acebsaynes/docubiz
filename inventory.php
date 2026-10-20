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
function inventoryEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
$categories = [
    "Baking Supplies",
    "Beverages",
    "Condiments",
    "Dairy",
    "Dry Goods",
    "Meat & Poultry"
];
$locations = ["Main Storage", "Cold Storage"];
$errorMessage = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add_item") {
        $productName = trim($_POST["product_name"] ?? "");
        $quantity = filter_var($_POST["quantity"] ?? null, FILTER_VALIDATE_INT);
        $category = trim($_POST["category"] ?? "");
        $location = trim($_POST["location"] ?? "");
        if ($productName === "") {
            $errorMessage = "Please enter a product name.";
        } elseif ($quantity === false || $quantity < 0) {
            $errorMessage = "Quantity must be zero or higher.";
        } elseif (!in_array($category, $categories, true)) {
            $errorMessage = "Please select a valid category.";
        } elseif (!in_array($location, $locations, true)) {
            $errorMessage = "Please select a valid location.";
        } else {
            $supplyDate = date("Y-m-d");
            $stmt = $conn->prepare(
                "INSERT INTO inventory_items
                (user_id, product_name, category, quantity, location, supply_date)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param(
                    "ississ",
                    $userId,
                    $productName,
                    $category,
                    $quantity,
                    $location,
                    $supplyDate
                );
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $productCode = "INV-" . str_pad((string)$newId, 5, "0", STR_PAD_LEFT);
                    $update = $conn->prepare(
                        "UPDATE inventory_items
                         SET product_code = ?
                         WHERE id = ? AND user_id = ?"
                    );
                    if ($update) {
                        $update->bind_param("sii", $productCode, $newId, $userId);
                        $update->execute();
                        $update->close();
                    }
                    $stmt->close();
                    header("Location: inventory.php?success=added");
                    exit;
                } else {
                    $errorMessage = "Unable to save the item. Please try again.";
                }
                $stmt->close();
            } else {
                $errorMessage = "Unable to prepare the database query.";
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
    "name_asc" => "product_name ASC",
    "name_desc" => "product_name DESC",
    "category_asc" => "category ASC",
    "category_desc" => "category DESC",
    "code_asc" => "product_code ASC",
    "code_desc" => "product_code DESC",
    "quantity_asc" => "quantity ASC",
    "quantity_desc" => "quantity DESC",
    "date_asc" => "supply_date ASC, id ASC",
    "date_desc" => "supply_date DESC, id DESC",
    "location_asc" => "location ASC",
    "location_desc" => "location DESC"
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = "name_asc";
}
if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = "";
}
if (!in_array($locationFilter, $locations, true)) {
    $locationFilter = "";
}

$itemsPerPage = 8;
$page = max(1, (int)($_GET["page"] ?? 1));
$offset = ($page - 1) * $itemsPerPage;
$searchLike = "%" . $search . "%";

$dateCondition = "";
if ($dateFilter === "today") {
    $dateCondition = " AND supply_date = CURDATE()";
} elseif ($dateFilter === "last_7_days") {
    $dateCondition = " AND supply_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($dateFilter === "last_30_days") {
    $dateCondition = " AND supply_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($dateFilter === "this_year") {
    $dateCondition = " AND YEAR(supply_date) = YEAR(CURDATE())";
} elseif ($dateFilter === "last_year") {
    $dateCondition = " AND YEAR(supply_date) = YEAR(CURDATE()) - 1";
} elseif ($dateFilter === "custom" && $customFrom !== "" && $customTo !== "") {
    $dateCondition = " AND supply_date BETWEEN ? AND ?";
}

$countSql = "SELECT COUNT(*) AS total
             FROM inventory_items
             WHERE user_id = ?
             AND (? = '' OR product_name LIKE ? OR product_code LIKE ?
                  OR category LIKE ? OR location LIKE ?)
             AND (? = '' OR category = ?)
             AND (? = '' OR location = ?)" . $dateCondition;

$countStmt = $conn->prepare($countSql);
if ($dateFilter === "custom" && $customFrom !== "" && $customTo !== "") {
    $countStmt->bind_param(
        "issssssssss",
        $userId,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $categoryFilter,
        $categoryFilter,
        $locationFilter,
        $locationFilter,
        $customFrom,
        $customTo
    );
} else {
    $countStmt->bind_param(
        "isssssssss",
        $userId,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $categoryFilter,
        $categoryFilter,
        $locationFilter,
        $locationFilter
    );
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalItems = (int)($countResult->fetch_assoc()["total"] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $itemsPerPage;
}

$listSql = "SELECT id, product_name, category, product_code,
                   quantity, supply_date, location
            FROM inventory_items
            WHERE user_id = ?
            AND (? = '' OR product_name LIKE ? OR product_code LIKE ?
                 OR category LIKE ? OR location LIKE ?)
            AND (? = '' OR category = ?)
            AND (? = '' OR location = ?)" . $dateCondition . "
            ORDER BY " . $sortOptions[$sort] . "
            LIMIT ? OFFSET ?";

$listStmt = $conn->prepare($listSql);
if ($dateFilter === "custom" && $customFrom !== "" && $customTo !== "") {
    $listStmt->bind_param(
        "issssssssssii",
        $userId,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $categoryFilter,
        $categoryFilter,
        $locationFilter,
        $locationFilter,
        $customFrom,
        $customTo,
        $itemsPerPage,
        $offset
    );
} else {
    $listStmt->bind_param(
        "isssssssssii",
        $userId,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $categoryFilter,
        $categoryFilter,
        $locationFilter,
        $locationFilter,
        $itemsPerPage,
        $offset
    );
}
$listStmt->execute();
$itemsResult = $listStmt->get_result();
$inventoryItems = [];
while ($item = $itemsResult->fetch_assoc()) {
    $inventoryItems[] = $item;
}
$listStmt->close();

function inventoryPageUrl($pageNumber, $search, $categoryFilter, $locationFilter, $dateFilter, $customFrom, $customTo, $sort) {
    return "inventory.php?" . http_build_query([
        "page" => $pageNumber,
        "q" => $search,
        "category" => $categoryFilter,
        "location" => $locationFilter,
        "date_filter" => $dateFilter,
        "custom_from" => $customFrom,
        "custom_to" => $customTo,
        "sort" => $sort
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Inventory</title>
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
      gap: 15px;
    }
    /* Ginawang explicit button with high z-index para 100% clickable at hindi ma-block */
   .icon-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.9;
      text-decoration: none;
      padding: 4px;
      transition: opacity 0.2s;
    }
    .icon-btn:hover {
      opacity: 1;
    }
    .icon-btn:hover {
      opacity: 1;
      background-color: rgba(255, 255, 255, 0.2);
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
    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
    }
    .action-buttons {
      display: flex;
      gap: 10px;
      position: relative;
    }
    .btn-action {
      background-color: #72dbd9;
      color: #1a365d;
      border: none;
      border-radius: 18px;
      padding: 8px 16px;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .btn-add-items {
      background-color: #72dbd9;
      color: #1a365d;
      border: none;
      border-radius: 18px;
      padding: 8px 18px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .filter-dropdown-menu, .sort-dropdown-menu {
      position: absolute;
      top: calc(100% + 6px);
      background-color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      width: 155px;
      padding: 6px 0;
      display: none;
      z-index: 200;
      overflow: visible;
    }
    .sort-dropdown-menu {
      left: 0;
    }
    .filter-dropdown-menu {
      left: 80px;
    }
    .filter-dropdown-menu.active, .sort-dropdown-menu.active {
      display: block;
    }
    .filter-menu-item, .sort-menu-item {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 7px 12px;
      font-size: 0.8rem;
      color: #2d3748;
      cursor: pointer;
      transition: background 0.15s;
      white-space: nowrap;
    }
    .filter-menu-item:hover, .sort-menu-item:hover {
      background-color: #f1f5f9;
      color: #122b49;
      font-weight: 500;
    }

    .filter-submenu, .sort-submenu {
      position: absolute;
      top: -6px;
      left: calc(100% + 6px);
      background-color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      width: 165px;
      padding: 6px 0;
      display: none;
      z-index: 210;
      overflow: visible;
    }
    .filter-menu-item:hover > .filter-submenu,
    .sort-menu-item:hover > .sort-submenu,
    .filter-submenu.active, 
    .sort-submenu.active {
      display: block;
    }

    .date-popout-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(18, 43, 73, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
    }
    .date-popout-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .date-popout-box {
      width: 100%;
      max-width: 380px;
      background-color: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      padding: 20px;
    }
    .date-popout-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
    }
    .date-popout-header h3 {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
    }
    .date-close-btn {
      background: none;
      border: none;
      color: #e53e3e;
      font-size: 1.2rem;
      cursor: pointer;
      font-weight: 600;
    }
    .date-popout-body .form-group {
      margin-bottom: 12px;
    }
    .date-popout-body label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: #64748b;
      margin-bottom: 4px;
    }
    .date-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .date-input-wrap input {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #cbd5e0;
      border-radius: 8px;
      font-size: 0.8rem;
      outline: none;
      color: #2d3748;
    }
    .date-popout-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 18px;
    }

    .inventory-table-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 15px 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }
    .inventory-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    .inventory-table th {
      font-size: 0.72rem;
      font-weight: 600;
      color: #718096;
      text-transform: uppercase;
      padding: 12px 10px;
      border-bottom: 1px solid #edf2f7;
    }
    .inventory-table td {
      padding: 12px 10px;
      font-size: 0.85rem;
      color: #2d3748;
      vertical-align: middle;
      border-bottom: 1px solid #f7fafc;
    }
    .row-highlight {
      background-color: #b2f5ea;
    }
    .pagination {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 20px;
      font-size: 0.85rem;
    }
    .page-link {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 26px;
      height: 26px;
      border-radius: 4px;
      text-decoration: none;
      color: #4a5568;
      font-weight: 500;
    }
    .page-link.active {
      background-color: #319795;
      color: #ffffff;
    }
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(18, 43, 73, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .modal-container {
      width: 100%;
      max-width: 480px;
      background-color: #ffffff;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      transform: scale(0.92);
      transition: transform 0.25s ease;
    }
    .modal-overlay.active .modal-container {
      transform: scale(1);
    }
    .modal-header {
      background-color: #122b49;
      padding: 18px 24px;
      color: #ffffff;
      font-size: 1.4rem;
      font-weight: 600;
    }
    .modal-body {
      padding: 24px 28px;
    }
    .form-group {
      margin-bottom: 18px;
    }
    .form-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: #122b49;
      margin-bottom: 6px;
    }
    .form-control {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      font-size: 0.85rem;
      color: #2d3748;
      outline: none;
      background-color: #ffffff;
    }
    .form-control::placeholder {
      color: #a0aec0;
    }
    .quantity-counter {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      padding: 6px 14px;
    }
    .qty-btn {
      background: none;
      border: none;
      font-size: 1.2rem;
      color: #a0aec0;
      cursor: pointer;
      font-weight: 600;
    }
    .qty-value {
      font-size: 0.9rem;
      color: #a0aec0;
      font-weight: 500;
    }
    .custom-dropdown {
      position: relative;
    }
    .dropdown-select-btn {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      background-color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      font-size: 0.85rem;
      color: #a0aec0;
    }
    .dropdown-list {
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      width: 100%;
      background-color: #ffffff;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: none;
      z-index: 10;
      max-height: 200px;
      overflow-y: auto;
    }
    .dropdown-list.active {
      display: block;
    }
    .dropdown-item {
      padding: 10px 14px;
      font-size: 0.85rem;
      color: #2d3748;
      cursor: pointer;
      transition: background 0.2s;
    }
    .dropdown-item:hover {
      background-color: #f1f5f9;
    }
    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
    }
    .btn-modal-cancel {
      padding: 8px 20px;
      border: 1px solid #cbd5e0;
      background-color: #ffffff;
      color: #4a5568;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }
    .btn-modal-save {
      padding: 8px 20px;
      border: none;
      background-color: #72dbd9;
      color: #1a365d;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
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
        <button class="hamburger-btn" type="button">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>
      <form class="search-bar" method="GET" action="inventory.php">
        <input type="text" name="q" value="<?= inventoryEscape($search) ?>" placeholder="Search inventory...">
        <input type="hidden" name="category" value="<?= inventoryEscape($categoryFilter) ?>">
        <input type="hidden" name="location" value="<?= inventoryEscape($locationFilter) ?>">
        <input type="hidden" name="date_filter" value="<?= inventoryEscape($dateFilter) ?>">
        <input type="hidden" name="sort" value="<?= inventoryEscape($sort) ?>">
        <button type="submit" class="search-btn" aria-label="Search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </form>
      <div class="topbar-actions">
     
        <button type="button" class="icon-btn" onclick="window.location.href='settings.php'" aria-label="Settings" title="Settings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </button>
        <button class="icon-btn" type="button" aria-label="Notifications" title="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
        <div class="user-profile">
          <div class="profile-avatar"><?= inventoryEscape($avatarText) ?></div>
          <div class="profile-info">
            <span class="user-name"><?= inventoryEscape($displayName) ?></span>
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
          <a href="projects.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <span>Projects</span>
          </a>
          <a href="inventory.php" class="menu-item active">
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
          <a href="finances.php" class="menu-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>Finances</span>
          </a>
        </nav>
      </aside>
      <main class="content-area">
        <div class="page-header">
          <h1 class="page-title">Inventory</h1>
          <div class="action-buttons">
            <button class="btn-action" type="button" id="sortButton">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
              Sort
            </button>

        
            <div class="sort-dropdown-menu" id="sortDropdownMenu">
              <div class="sort-menu-item" id="sortAscTrigger">
                <span><b style="font-size:0.85rem;">A</b><b style="font-size:0.7rem;">Z</b>↓ Ascending</span>
                <span>&gt;</span>
           
                <div class="sort-submenu" id="ascSubmenu">
                  <div class="sort-menu-item sort-opt" data-sort="name_asc">Product Name</div>
                  <div class="sort-menu-item sort-opt" data-sort="category_asc">Category</div>
                  <div class="sort-menu-item sort-opt" data-sort="code_asc">Product Code</div>
                  <div class="sort-menu-item sort-opt" data-sort="quantity_asc">Quantity</div>
                  <div class="sort-menu-item sort-opt" data-sort="date_asc">Supply History</div>
                  <div class="sort-menu-item sort-opt" data-sort="location_asc">Location</div>
                </div>
              </div>
              <div class="sort-menu-item" id="sortDescTrigger">
                <span><b style="font-size:0.85rem;">A</b><b style="font-size:0.7rem;">Z</b>↑ Descending</span>
                <span>&gt;</span>
              
                <div class="sort-submenu" id="descSubmenu">
                  <div class="sort-menu-item sort-opt" data-sort="name_desc">Product Name</div>
                  <div class="sort-menu-item sort-opt" data-sort="category_desc">Category</div>
                  <div class="sort-menu-item sort-opt" data-sort="code_desc">Product Code</div>
                  <div class="sort-menu-item sort-opt" data-sort="quantity_desc">Quantity</div>
                  <div class="sort-menu-item sort-opt" data-sort="date_desc">Supply History</div>
                  <div class="sort-menu-item sort-opt" data-sort="location_desc">Location</div>
                </div>
              </div>
            </div>

            <button class="btn-action" type="button" id="filterButton">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
              Filter
            </button>

            <div class="filter-dropdown-menu" id="filterDropdownMenu">
              <div class="filter-menu-item" id="filterCategoryTrigger">
                <span>Category</span> <span>&gt;</span>
          
              
                <div class="filter-submenu" id="categorySubmenu">
                  <div class="filter-menu-item category-opt" data-cat="">All Categories</div>
                  <div class="filter-menu-item category-opt" data-cat="Baking Supplies">Baking Supplies</div>
                  <div class="filter-menu-item category-opt" data-cat="Beverages">Beverages</div>
                  <div class="filter-menu-item category-opt" data-cat="Condiments">Condiments</div>
                  <div class="filter-menu-item category-opt" data-cat="Dairy">Dairy</div>
                  <div class="filter-menu-item category-opt" data-cat="Dry Goods">Dry Goods</div>
                  <div class="filter-menu-item category-opt" data-cat="Meat & Poultry">Meat & Poultry</div>
                </div>
              </div>
              <div class="filter-menu-item" id="filterSupplyTrigger">
                <span>Supply History</span> <span>&gt;</span>
              
                <div class="filter-submenu" id="supplySubmenu">
                  <div class="filter-menu-item supply-opt" data-date="">All Time</div>
                  <div class="filter-menu-item supply-opt" data-date="today">Today</div>
                  <div class="filter-menu-item supply-opt" data-date="last_7_days">Last 7 days</div>
                  <div class="filter-menu-item supply-opt" data-date="last_30_days">Last 30 days</div>
                  <div class="filter-menu-item supply-opt" data-date="this_year">This year (2026)</div>
                  <div class="filter-menu-item supply-opt" data-date="last_year">Last year (2025)</div>
                  <div class="filter-menu-item" id="customDateRangeTrigger" style="border-top:1px solid #edf2f7;">Custom date range...</div>
                </div>
              </div>
              <div class="filter-menu-item" id="filterLocationTrigger">
                <span>Location</span> <span>&gt;</span>
             
                <div class="filter-submenu" id="locationSubmenu">
                  <div class="filter-menu-item location-opt" data-loc="">All Locations</div>
                  <div class="filter-menu-item location-opt" data-loc="Main Storage">Main Storage</div>
                  <div class="filter-menu-item location-opt" data-loc="Cold Storage">Cold Storage</div>
                </div>
              </div>
            </div>

            <button class="btn-add-items" id="openAddItemModal" type="button">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Add Items
            </button>
          </div>
        </div>

        <?php if ($errorMessage !== ""): ?>
          <p style="margin-bottom:15px;color:#c53030;font-size:0.85rem;">
            <?= inventoryEscape($errorMessage) ?>
          </p>
        <?php endif; ?>
        <?php if (($_GET["success"] ?? "") === "added"): ?>
          <p style="margin-bottom:15px;color:#276749;font-size:0.85rem;">
            Item added successfully!
          </p>
        <?php endif; ?>

        <div class="inventory-table-card">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Product Code</th>
                <th>Quantity</th>
                <th>Supply History</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($inventoryItems)): ?>
                <?php foreach ($inventoryItems as $index => $item): ?>
                  <tr class="<?= $index % 2 === 1 ? 'row-highlight' : '' ?>">
                    <td><?= inventoryEscape(strtoupper($item["product_name"])) ?></td>
                    <td><?= inventoryEscape(strtoupper($item["category"])) ?></td>
                    <td><?= inventoryEscape($item["product_code"] ?? "") ?></td>
                    <td><?= inventoryEscape($item["quantity"]) ?></td>
                    <td>
                      <?= !empty($item["supply_date"])
                        ? inventoryEscape(strtoupper(date("F j, Y", strtotime($item["supply_date"]))))
                        : "—" ?>
                    </td>
                    <td><?= inventoryEscape(strtoupper($item["location"])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center;padding:25px;">
                    <?= $search !== "" || $categoryFilter !== "" || $locationFilter !== "" || $dateFilter !== ""
                      ? "No inventory items match your search or filter."
                      : "No inventory items yet. Click Add Items to add your first product." ?>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="<?= inventoryEscape(inventoryPageUrl($page - 1, $search, $categoryFilter, $locationFilter, $dateFilter, $customFrom, $customTo, $sort)) ?>" class="page-link">&lt;</a>
            <?php else: ?>
              <span class="page-link">&lt;</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <a href="<?= inventoryEscape(inventoryPageUrl($p, $search, $categoryFilter, $locationFilter, $dateFilter, $customFrom, $customTo, $sort)) ?>"
                 class="page-link <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
              <a href="<?= inventoryEscape(inventoryPageUrl($page + 1, $search, $categoryFilter, $locationFilter, $dateFilter, $customFrom, $customTo, $sort)) ?>" class="page-link">&gt;</a>
            <?php else: ?>
              <span class="page-link">&gt;</span>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>



  <div class="date-popout-overlay" id="customDateModal">
    <div class="date-popout-box">
      <div class="date-popout-header">
        <h3>Filter</h3>
        <button type="button" class="date-close-btn" id="closeDateModal">&times;</button>
      </div>
      <form id="customDateForm">
        <div class="date-popout-body">
          <p style="font-size:0.8rem; font-weight:600; color:#1e293b; margin-bottom:10px;">Supply History</p>
          <div class="form-group">
            <label>From</label>
            <div class="date-input-wrap">
              <input type="date" id="customFromDate" class="form-control" value="<?= inventoryEscape($customFrom) ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>To</label>
            <div class="date-input-wrap">
              <input type="date" id="customToDate" class="form-control" value="<?= inventoryEscape($customTo) ?>" required>
            </div>
          </div>
        </div>
        <div class="date-popout-footer">
          <button type="button" class="btn-modal-cancel" id="cancelDateModal">Cancel</button>
          <button type="button" class="btn-modal-save" id="applyDateModal">Apply</button>
        </div>
      </form>
    </div>
  </div>


  <div class="modal-overlay" id="addItemModal">
    <div class="modal-container">
      <div class="modal-header">Add Item</div>
      <div class="modal-body">
        <form method="POST" action="inventory.php" id="addItemForm">
          <input type="hidden" name="action" value="add_item">
          <input type="hidden" name="quantity" id="quantityInput" value="0">
          <input type="hidden" name="category" id="categoryInput" value="">
          <input type="hidden" name="location" id="locationInput" value="">
          <div class="form-group">
            <label for="productName">Product Name</label>
            <input type="text" name="product_name" id="productName" class="form-control" placeholder="Enter product name" maxlength="150" required>
          </div>
          <div class="form-group">
            <label>Quantity</label>
            <div class="quantity-counter">
              <button type="button" class="qty-btn" id="qtyMinus">−</button>
              <span class="qty-value" id="qtyDisplay">0</span>
              <button type="button" class="qty-btn" id="qtyPlus">+</button>
            </div>
          </div>
          <div class="form-group">
            <label>Category</label>
            <div class="custom-dropdown" id="categoryDropdown">
              <div class="dropdown-select-btn" id="categorySelectBtn" role="button" tabindex="0">
                <span id="categorySelectedText">Item type</span>
                <span id="categoryArrow">⌄</span>
              </div>
              <div class="dropdown-list" id="categoryList">
                <div class="dropdown-item" data-value="Baking Supplies">Baking Supplies</div>
                <div class="dropdown-item" data-value="Beverages">Beverages</div>
                <div class="dropdown-item" data-value="Condiments">Condiments</div>
                <div class="dropdown-item" data-value="Dairy">Dairy</div>
                <div class="dropdown-item" data-value="Dry Goods">Dry Goods</div>
                <div class="dropdown-item" data-value="Meat & Poultry">Meat & Poultry</div>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Location</label>
            <div class="custom-dropdown" id="locationDropdown">
              <div class="dropdown-select-btn" id="locationSelectBtn" role="button" tabindex="0">
                <span id="locationSelectedText">Item location</span>
                <span id="locationArrow">⌄</span>
              </div>
              <div class="dropdown-list" id="locationList">
                <div class="dropdown-item" data-value="Main Storage">Main Storage</div>
                <div class="dropdown-item" data-value="Cold Storage">Cold Storage</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="closeAddItemModal">Cancel</button>
            <button type="submit" class="btn-modal-save" id="saveAddItemModal">Save Item</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const openModalBtn = document.getElementById('openAddItemModal');
    const closeModalBtn = document.getElementById('closeAddItemModal');
    const modalOverlay = document.getElementById('addItemModal');
    const addItemForm = document.getElementById('addItemForm');

    openModalBtn.addEventListener('click', () => {
      modalOverlay.classList.add('active');
    });
    closeModalBtn.addEventListener('click', () => {
      modalOverlay.classList.remove('active');
    });
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        modalOverlay.classList.remove('active');
      }
    });

    let currentQty = 0;
    const qtyDisplay = document.getElementById('qtyDisplay');
    const quantityInput = document.getElementById('quantityInput');
    document.getElementById('qtyPlus').addEventListener('click', () => {
      currentQty++;
      qtyDisplay.textContent = currentQty;
      quantityInput.value = currentQty;
      qtyDisplay.style.color = '#2d3748';
    });
    document.getElementById('qtyMinus').addEventListener('click', () => {
      if (currentQty > 0) currentQty--;
      qtyDisplay.textContent = currentQty;
      quantityInput.value = currentQty;
      if (currentQty === 0) {
        qtyDisplay.style.color = '#a0aec0';
      }
    });

    const categoryBtn = document.getElementById('categorySelectBtn');
    const categoryList = document.getElementById('categoryList');
    const categoryText = document.getElementById('categorySelectedText');
    const categoryArrow = document.getElementById('categoryArrow');
    const categoryInput = document.getElementById('categoryInput');

    const locationBtn = document.getElementById('locationSelectBtn');
    const locationList = document.getElementById('locationList');
    const locationText = document.getElementById('locationSelectedText');
    const locationArrow = document.getElementById('locationArrow');
    const locationInput = document.getElementById('locationInput');

    categoryBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      categoryList.classList.toggle('active');
      locationList.classList.remove('active');
      categoryArrow.textContent = categoryList.classList.contains('active') ? '⌃' : '⌄';
      locationArrow.textContent = '⌄';
    });
    categoryList.querySelectorAll('.dropdown-item').forEach(item => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        const value = item.getAttribute('data-value');
        categoryText.textContent = value;
        categoryText.style.color = '#2d3748';
        categoryInput.value = value;
        categoryList.classList.remove('active');
        categoryArrow.textContent = '⌄';
      });
    });

    locationBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      locationList.classList.toggle('active');
      categoryList.classList.remove('active');
      locationArrow.textContent = locationList.classList.contains('active') ? '⌃' : '⌄';
      locationArrow.textContent = '⌄';
    });
    locationList.querySelectorAll('.dropdown-item').forEach(item => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        const value = item.getAttribute('data-value');
        locationText.textContent = value;
        locationText.style.color = '#2d3748';
        locationInput.value = value;
        locationList.classList.remove('active');
        locationArrow.textContent = '⌄';
      });
    });

    addItemForm.addEventListener('submit', (e) => {
      if (categoryInput.value === "") {
        e.preventDefault();
        alert("Please select a category.");
        return;
      }
      if (locationInput.value === "") {
        e.preventDefault();
        alert("Please select a location.");
        return;
      }
    });

    // Toggle Dropdown Buttons
    const sortButton = document.getElementById('sortButton');
    const sortDropdownMenu = document.getElementById('sortDropdownMenu');
    const filterButton = document.getElementById('filterButton');
    const filterDropdownMenu = document.getElementById('filterDropdownMenu');

    sortButton.addEventListener('click', (e) => {
      e.stopPropagation();
      sortDropdownMenu.classList.toggle('active');
      filterDropdownMenu.classList.remove('active');
    });

    filterButton.addEventListener('click', (e) => {
      e.stopPropagation();
      filterDropdownMenu.classList.toggle('active');
      sortDropdownMenu.classList.remove('active');
    });

    // Sort options click
    document.querySelectorAll('.sort-opt').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        const selectedSort = opt.getAttribute('data-sort');
        window.location.href = "inventory.php?" + new URLSearchParams({
          q: "<?= inventoryEscape($search) ?>",
          category: "<?= inventoryEscape($categoryFilter) ?>",
          location: "<?= inventoryEscape($locationFilter) ?>",
          date_filter: "<?= inventoryEscape($dateFilter) ?>",
          custom_from: "<?= inventoryEscape($customFrom) ?>",
          custom_to: "<?= inventoryEscape($customTo) ?>",
          sort: selectedSort
        }).toString();
      });
    });

    // Category options click
    document.querySelectorAll('.category-opt').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        const cat = opt.getAttribute('data-cat');
        window.location.href = "inventory.php?" + new URLSearchParams({
          q: "<?= inventoryEscape($search) ?>",
          category: cat,
          location: "<?= inventoryEscape($locationFilter) ?>",
          date_filter: "<?= inventoryEscape($dateFilter) ?>",
          custom_from: "<?= inventoryEscape($customFrom) ?>",
          custom_to: "<?= inventoryEscape($customTo) ?>",
          sort: "<?= inventoryEscape($sort) ?>"
        }).toString();
      });
    });

    // Supply history options click
    document.querySelectorAll('.supply-opt').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        const df = opt.getAttribute('data-date');
        window.location.href = "inventory.php?" + new URLSearchParams({
          q: "<?= inventoryEscape($search) ?>",
          category: "<?= inventoryEscape($categoryFilter) ?>",
          location: "<?= inventoryEscape($locationFilter) ?>",
          date_filter: df,
          custom_from: "",
          custom_to: "",
          sort: "<?= inventoryEscape($sort) ?>"
        }).toString();
      });
    });

    // Location options click
    document.querySelectorAll('.location-opt').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        const loc = opt.getAttribute('data-loc');
        window.location.href = "inventory.php?" + new URLSearchParams({
          q: "<?= inventoryEscape($search) ?>",
          category: "<?= inventoryEscape($categoryFilter) ?>",
          location: loc,
          date_filter: "<?= inventoryEscape($dateFilter) ?>",
          custom_from: "<?= inventoryEscape($customFrom) ?>",
          custom_to: "<?= inventoryEscape($customTo) ?>",
          sort: "<?= inventoryEscape($sort) ?>"
        }).toString();
      });
    });

    // Custom Date Range Modal triggers
    const customDateModal = document.getElementById('customDateModal');
    const customDateRangeTrigger = document.getElementById('customDateRangeTrigger');
    const closeDateModal = document.getElementById('closeDateModal');
    const cancelDateModal = document.getElementById('cancelDateModal');
    const applyDateModal = document.getElementById('applyDateModal');

    customDateRangeTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      filterDropdownMenu.classList.remove('active');
      customDateModal.classList.add('active');
    });

    closeDateModal.addEventListener('click', () => {
      customDateModal.classList.remove('active');
    });
    cancelDateModal.addEventListener('click', () => {
      customDateModal.classList.remove('active');
    });
    customDateModal.addEventListener('click', (e) => {
      if (e.target === customDateModal) {
        customDateModal.classList.remove('active');
      }
    });

    applyDateModal.addEventListener('click', () => {
      const fromVal = document.getElementById('customFromDate').value;
      const toVal = document.getElementById('customToDate').value;
      if (!fromVal || !toVal) {
        alert("Please select both From and To dates.");
        return;
      }
      window.location.href = "inventory.php?" + new URLSearchParams({
        q: "<?= inventoryEscape($search) ?>",
        category: "<?= inventoryEscape($categoryFilter) ?>",
        location: "<?= inventoryEscape($locationFilter) ?>",
        date_filter: "custom",
        custom_from: fromVal,
        custom_to: toVal,
        sort: "<?= inventoryEscape($sort) ?>"
      }).toString();
    });

    // Close dropdowns on outside click
    window.addEventListener('click', () => {
      sortDropdownMenu.classList.remove('active');
      filterDropdownMenu.classList.remove('active');
      categoryList.classList.remove('active');
      locationList.classList.remove('active');
      categoryArrow.textContent = '⌄';
      locationArrow.textContent = '⌄';
    });
  </script>
</body>
</html>