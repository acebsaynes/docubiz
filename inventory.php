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

function inventoryEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
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

    .header-business {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.88rem;
      color: #ffffff;
      margin-left: 20px;
      font-weight: 500;
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

      <div class="header-business">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
        <span>Business Name</span>
      </div>

       <div class="search-bar">
        <input type="text" placeholder="">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>

      <div class="topbar-actions">
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </button>
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>

        <div class="user-profile">
  <div class="profile-avatar">
    <?php echo inventoryEscape($avatarText); ?>
  </div>

  <div class="profile-info">
    <span class="user-name">
      <?php echo inventoryEscape($displayName); ?>
    </span>
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
          <h1 class="page-title">Inventory</h1>
          <div class="action-buttons">
            <button class="btn-action">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
              Sort
            </button>
            <button class="btn-action">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
              Filter
            </button>
            <button class="btn-add-items">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Add Items
            </button>
          </div>
        </div>

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
              <tr>
                <td>JASMINE RICE</td>
                <td>DRY GOODS</td>
                <td>CH-RIC-001</td>
                <td>25 SACKS</td>
                <td>AUGUST 28, 2023</td>
                <td>MAIN STORAGE</td>
              </tr>
              <tr class="row-highlight">
                <td>COOKING OIL</td>
                <td>CONDIMENTS</td>
                <td>CH-OIL-002</td>
                <td>18 BOTTLES</td>
                <td>AUGUST 28, 2023</td>
                <td>MAIN STORAGE</td>
              </tr>
              <tr>

                   <td>CHICKEN BREAST</td>
                <td>MEAT & POULTRY</td>
                <td>CH-MEA-003</td>
                <td>45 KG</td>
                <td>AUGUST 28, 2023</td>
                <td>COLD STORAGE</td>
              </tr>
              <tr class="row-highlight">
                <td>PORK BELLY</td>
                <td>MEAT & POULTRY</td>
                <td>CH-MEA-004</td>
                <td>35 KG</td>
                <td>AUGUST 28, 2023</td>
                <td>FREEZER ROOM</td>
              </tr>
              <tr>
                <td>FRESH MILK</td>
                <td>DAIRY</td>
                <td>CH-DAI-005</td>
                <td>20 LITERS</td>
                <td>AUGUST 28, 2023</td>
                <td>COLD STORAGE</td>
              </tr>
              <tr class="row-highlight">
                <td>ALL-PURPOSE FLOUR</td>
                <td>BAKING SUPPLIES</td>
                <td>CH-BKG-006</td>
                <td>15 BAGS</td>
                <td>AUGUST 28, 2023</td>
                <td>MAIN STORAGE</td>
              </tr>
              <tr>

                   <td>BROWN SUGAR</td>
                <td>BAKING SUPPLIES</td>
                <td>CH-BKG-007</td>
                <td>12 KG</td>
                <td>AUGUST 28, 2023</td>
                <td>MAIN STORAGE</td>
              </tr>
              <tr class="row-highlight">
                <td>BOTTLED WATER</td>
                <td>BEVERAGES</td>
                <td>CH-BVG-008</td>
                <td>8 CASES</td>
                <td>AUGUST 28, 2023</td>
                <td>COLD STORAGE</td>
              </tr>
            </tbody>
          </table>

            <div class="pagination">
            <a href="#" class="page-link">&lt;</a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">4</a>
            <a href="#" class="page-link">&gt;</a>
          </div>
        </div>
      </main>
    </div>
  </div>

</body>
</html>
