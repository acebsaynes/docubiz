<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userEmail = $_SESSION["user_email"] ?? "user@gmail.com";
$userName  = $_SESSION["user_name"]  ?? "User";

$nameParts = explode(" ", trim($userName));
$avatarInitials = "";
if (count($nameParts) >= 2) {
    $avatarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $avatarInitials = strtoupper(substr($userName, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Finances</title>
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
    .btn-create-ledger {
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
    .ledger-pills-row {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
    }
    .ledger-pill {
      background-color: #ffffff;
      border: none;
      border-radius: 12px;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.85rem;
      font-weight: 600;
      color: #334155;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .ledger-pill.active {
      border: 1px solid #94a3b8;
    }
    .pill-icon {
      font-size: 1.1rem;
    }
    .invoice-card {
      background-color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.04);
      overflow: hidden;
    }
    .invoice-header-bar {
      background-color: #80d3ce;
      color: #1e293b;
      padding: 12px 20px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .invoice-meta-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      border-bottom: 1px solid #e2e8f0;
    }
    .meta-box {
      padding: 15px 20px;
      border-right: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .meta-box:last-child {
      border-right: none;
    }
    .meta-label {
      font-size: 0.72rem;
      color: #64748b;
    }
    .meta-value {
      font-size: 0.85rem;
      font-weight: 600;
      color: #1e293b;
    }
    .order-summary-title {
      font-size: 0.85rem;
      font-weight: 600;
      color: #475569;
      padding: 15px 20px 10px 20px;
    }
    .order-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    .order-table th {
      background-color: #b2f5ea;
      font-size: 0.75rem;
      font-weight: 600;
      color: #1e293b;
      padding: 10px 20px;
      border-bottom: 1px solid #cbd5e0;
    }
    .order-table td {
      padding: 12px 20px;
      font-size: 0.82rem;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }
    .order-table tr:last-child td {
      border-bottom: none;
      font-weight: 700;
      color: #1e293b;
    }
    @media (max-width: 900px) {
      .ledger-pills-row {
        flex-wrap: wrap;
      }
      .invoice-meta-grid {
        grid-template-columns: 1fr 1fr;
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
        <button class="hamburger-btn">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>
      <div class="search-bar">
        <input type="text" placeholder="Search finance files...">
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
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
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
          <a href="finances.php" class="menu-item active">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>Finances</span>
          </a>
        </nav>
      </aside>
      <main class="content-area">
        <div class="page-header">
          <h1 class="page-title">Finances</h1>
          <button class="btn-create-ledger">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Create Ledger
          </button>
        </div>
        <div class="ledger-pills-row">
          <button class="ledger-pill active">
            <span class="pill-icon" style="color: #ec4899;">📖</span>
            <span>General Ledger</span>
          </button>
          <button class="ledger-pill">
            <span class="pill-icon" style="color: #f97316;">📊</span>
            <span>Sales Ledger</span>
          </button>
          <button class="ledger-pill">
            <span class="pill-icon" style="color: #22c55e;">💳</span>
            <span>Purchase Ledger</span>
          </button>
          <button class="ledger-pill">
            <span class="pill-icon" style="color: #ef4444;">💲</span>
            <span>Expenses</span>
          </button>
          <button class="ledger-pill">
            <span class="pill-icon" style="color: #3b82f6;">📄</span>
            <span>Reports</span>
          </button>
        </div>
        <div class="invoice-card">
          <div class="invoice-header-bar">
            <span>SALES INVOICE DETAILS</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          </div>
          <div class="invoice-meta-grid">
            <div class="meta-box">
              <span class="meta-label">Invoice Number</span>
              <span class="meta-value">2026001</span>
            </div>
            <div class="meta-box">
              <span class="meta-label">Invoice Date</span>
              <span class="meta-value">08-31-2026</span>
            </div>
            <div class="meta-box">
              <span class="meta-label">Salesman</span>
              <span class="meta-value">LuckyFive Events</span>
            </div>
            <div class="meta-box">
              <span class="meta-label">Customer</span>
              <span class="meta-value">Joy Anne Barcoma</span>
            </div>
          </div>
          <div class="order-summary-title">Order Summary</div>
          <table class="order-table">
            <thead>
              <tr>
                <th>Product Code</th>
                <th>Item</th>
                <th>Unit Price</th>
                <th>Quantity</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>CH-RIC-001</td>
                <td>JASMINE RICE</td>
                <td>₱1,200.00</td>
                <td>1</td>
                <td>₱1,200.00</td>
              </tr>
              <tr>
                <td>CH-OIL-002</td>
                <td>COOKING OIL</td>
                <td>₱40.00</td>
                <td>5</td>
                <td>₱200.00</td>
              </tr>
              <tr>
                <td>CH-MEA-003</td>
                <td>CHICKEN BREAST</td>
                <td>₱320.00</td>
                <td>2</td>
                <td>₱640.00</td>
              </tr>
              <tr>
                <td colspan="4" style="text-align: right;">Total</td>
                <td>₱1,940.00</td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>
</body>
</html>