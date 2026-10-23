<?php
session_start();
/* Logout is handled here; no separate logout.php is required. */
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable. Please check db.php.');
}
$userId = (int) $_SESSION['user_id'];
/* Keep the profile and role consistent with the existing users table. */
$userStmt = $conn->prepare('SELECT full_name, email, role FROM users WHERE id = ? LIMIT 1');
if (!$userStmt) {
    error_log('Finance user query prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load your account. Please check the database setup.');
}
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userStmt->bind_result($dbFullName, $dbEmail, $dbRole);
$userFound = $userStmt->fetch();
$userStmt->close();
if (!$userFound) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['user_name'] = $dbFullName;
$_SESSION['full_name'] = $dbFullName;
$_SESSION['user_email'] = $dbEmail;
$_SESSION['user_role'] = $dbRole;
$_SESSION['role'] = $dbRole;
$userName = $dbFullName ?: 'User';
$userEmail = $dbEmail ?: '';
$isAdmin = ($dbRole === 'admin');
/* ADMIN-ONLY ACCESS: block regular users from the Finances page. */
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}
$nameParts = preg_split('/\s+/', trim($userName));
$avatarInitials = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
    : strtoupper(substr($userName, 0, 2));
/* These tables are created once automatically in the existing docubiz_db database. */
$schemaSql = [
    "CREATE TABLE IF NOT EXISTS finance_ledgers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ledger_type ENUM('sales','purchase','expense') NOT NULL,
        ledger_number VARCHAR(80) NOT NULL,
        ledger_date DATE NOT NULL,
        party_name VARCHAR(180) NOT NULL,
        customer_name VARCHAR(180) NOT NULL DEFAULT '',
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_finance_user_date (user_id, ledger_date),
        INDEX idx_finance_user_type (user_id, ledger_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS finance_ledger_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ledger_id INT UNSIGNED NOT NULL,
        product_code VARCHAR(80) NOT NULL DEFAULT '',
        item_name VARCHAR(180) NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_finance_item_ledger (ledger_id),
        CONSTRAINT fk_finance_items_ledger FOREIGN KEY (ledger_id) REFERENCES finance_ledgers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
foreach ($schemaSql as $sql) {
    if (!$conn->query($sql)) {
        error_log('Finance table setup failed: ' . $conn->error);
        http_response_code(500);
        exit('Finance database setup error: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }
}
if (empty($_SESSION['finance_csrf'])) {
    $_SESSION['finance_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['finance_csrf'];
$successMessage = '';
$errorMessage = '';
$allowedTabs = ['general', 'sales', 'purchase', 'expenses', 'reports'];
$activeTab = strtolower((string)($_GET['tab'] ?? 'general'));
if (!in_array($activeTab, $allowedTabs, true)) $activeTab = 'general';
$searchTerm = trim((string)($_GET['q'] ?? ''));
function finance_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function finance_money($value): string {
    return '₱' . number_format((float)$value, 2);
}
function finance_type_label($type): string {
    return ['sales' => 'Sales Ledger', 'purchase' => 'Purchase Ledger', 'expense' => 'Expenses'][$type] ?? 'General Ledger';
}
/* CSV report export: finance records are shared across signed-in users. */
if (($_GET['export'] ?? '') === 'csv') {
    $exportType = (string)($_GET['tab'] ?? 'general');
    $exportSql = "SELECT l.id, l.ledger_type, l.ledger_number, l.ledger_date, l.party_name, l.customer_name,
                         COALESCE(SUM(i.unit_price * i.quantity),0) AS total_amount
                  FROM finance_ledgers l LEFT JOIN finance_ledger_items i ON i.ledger_id = l.id
                  WHERE 1=1";
    if (in_array($exportType, ['sales','purchase','expenses'], true)) {
        $dbType = $exportType === 'expenses' ? 'expense' : $exportType;
        $exportSql .= ' AND l.ledger_type = ?';
    }
    if ($searchTerm !== '') {
        $exportSql .= ' AND (l.ledger_number LIKE ? OR l.party_name LIKE ? OR l.customer_name LIKE ? OR l.notes LIKE ?)';
    }
    $exportSql .= ' GROUP BY l.id ORDER BY l.ledger_date DESC, l.id DESC';
    $exportStmt = $conn->prepare($exportSql);
    if (!$exportStmt) {
        http_response_code(500);
        exit('Unable to prepare report export.');
    }
    if (in_array($exportType, ['sales','purchase','expenses'], true) && $searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $exportStmt->bind_param('sssss', $dbType, $like, $like, $like, $like);
    } elseif (in_array($exportType, ['sales','purchase','expenses'], true)) {
        $exportStmt->bind_param('s', $dbType);
    } elseif ($searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $exportStmt->bind_param('ssss', $like, $like, $like, $like);
    }
    $exportStmt->execute();
    $exportStmt->bind_result($csvId, $csvType, $csvNumber, $csvDate, $csvParty, $csvCustomer, $csvTotal);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="docubiz-finance-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Ledger Type','Ledger Number','Date','Salesman / Supplier','Customer','Total Amount']);
    while ($exportStmt->fetch()) {
        fputcsv($out, [$csvId, finance_type_label($csvType), $csvNumber, $csvDate, $csvParty, $csvCustomer, number_format((float)$csvTotal, 2, '.', '')]);
    }
    fclose($out);
    $exportStmt->close();
    exit;
}
/* Create a ledger and its line items in one database transaction. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errorMessage = 'Your form session expired. Refresh the page and try again.';
    } elseif (($_POST['form_action'] ?? '') === 'create_ledger') {
        $type = (string)($_POST['ledger_type'] ?? 'sales');
        $number = trim((string)($_POST['ledger_number'] ?? ''));
        $date = trim((string)($_POST['ledger_date'] ?? ''));
        $party = trim((string)($_POST['party_name'] ?? ''));
        $customer = trim((string)($_POST['customer_name'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $codes = $_POST['product_code'] ?? [];
        $items = $_POST['item_name'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $validDate = DateTime::createFromFormat('Y-m-d', $date);
        $rows = [];
        if (!in_array($type, ['sales','purchase','expense'], true)) {
            $errorMessage = 'Please select a valid ledger type.';
        } elseif ($number === '' || strlen($number) > 80) {
            $errorMessage = 'Enter an invoice or reference number (maximum 80 characters).';
        } elseif (!$validDate || $validDate->format('Y-m-d') !== $date) {
            $errorMessage = 'Please enter a valid ledger date.';
        } elseif ($party === '') {
            $errorMessage = 'Enter the salesman or supplier name.';
        } elseif (!is_array($items) || !is_array($prices) || !is_array($quantities)) {
            $errorMessage = 'Please enter at least one valid line item.';
        } else {
            foreach ($items as $index => $itemName) {
                $itemName = trim((string)$itemName);
                $price = filter_var($prices[$index] ?? null, FILTER_VALIDATE_FLOAT);
                $qty = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_FLOAT);
                $code = trim((string)($codes[$index] ?? ''));
                if ($itemName === '' && ($price === false || (float)$price == 0) && ($qty === false || (float)$qty == 0) && $code === '') {
                    continue;
                }
                if ($itemName === '' || $price === false || (float)$price < 0 || $qty === false || (float)$qty <= 0) {
                    $errorMessage = 'Each line item needs a name, a non-negative price, and a quantity greater than zero.';
                    break;
                }
                $rows[] = ['code' => $code, 'name' => $itemName, 'price' => (float)$price, 'qty' => (float)$qty];
            }
            if ($errorMessage === '' && count($rows) === 0) {
                $errorMessage = 'Add at least one line item before saving.';
            }
        }
        if ($errorMessage === '') {
            try {
                $conn->begin_transaction();
                $headerStmt = $conn->prepare('INSERT INTO finance_ledgers (user_id, ledger_type, ledger_number, ledger_date, party_name, customer_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$headerStmt) throw new RuntimeException($conn->error);
                $headerStmt->bind_param('issssss', $userId, $type, $number, $date, $party, $customer, $notes);
                if (!$headerStmt->execute()) throw new RuntimeException($headerStmt->error);
                $ledgerId = (int)$conn->insert_id;
                $headerStmt->close();
                $itemStmt = $conn->prepare('INSERT INTO finance_ledger_items (ledger_id, product_code, item_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
                if (!$itemStmt) throw new RuntimeException($conn->error);
                foreach ($rows as $row) {
                    $itemStmt->bind_param('issdd', $ledgerId, $row['code'], $row['name'], $row['price'], $row['qty']);
                    if (!$itemStmt->execute()) throw new RuntimeException($itemStmt->error);
                }
                $itemStmt->close();
                $conn->commit();
                header('Location: finances.php?tab=' . urlencode($type === 'expense' ? 'expenses' : $type) . '&ledger_id=' . $ledgerId . '&saved=1');
                exit;
            } catch (Throwable $e) {
                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    error_log('Finance rollback failed: ' . $rollbackError->getMessage());
                }
                error_log('Finance ledger save failed: ' . $e->getMessage());
                $errorMessage = 'Save failed: ' . $e->getMessage();
            }
        }
        $activeTab = $type === 'expense' ? 'expenses' : (in_array($type, ['sales','purchase'], true) ? $type : 'general');
    } elseif (($_POST['form_action'] ?? '') === 'delete_ledger') {
        $deleteId = filter_var($_POST['ledger_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$deleteId || $deleteId < 1) {
            $errorMessage = 'Invalid ledger selected.';
        } else {
            $deleteStmt = $conn->prepare('DELETE FROM finance_ledgers WHERE id = ? AND user_id = ?');
            if (!$deleteStmt) {
                $errorMessage = 'Unable to prepare delete request.';
            } else {
                $deleteStmt->bind_param('ii', $deleteId, $userId);
                $deleteStmt->execute();
                $deleted = $deleteStmt->affected_rows > 0;
                $deleteStmt->close();
                if ($deleted) {
                    header('Location: finances.php?tab=' . urlencode($activeTab) . '&deleted=1');
                    exit;
                }
                $errorMessage = 'Ledger not found or you do not have permission to delete it.';
            }
        }
    }
}
if (isset($_GET['saved'])) $successMessage = 'Ledger saved successfully.';
if (isset($_GET['deleted'])) $successMessage = 'Ledger deleted successfully.';
/* Build the shared finance list for the current tab and search query. */
$listSql = "SELECT l.id, l.ledger_type, l.ledger_number, l.ledger_date, l.party_name, l.customer_name,
                    COALESCE(SUM(i.unit_price * i.quantity),0) AS total_amount
            FROM finance_ledgers l LEFT JOIN finance_ledger_items i ON i.ledger_id = l.id
            WHERE 1=1";
if (in_array($activeTab, ['sales','purchase','expenses'], true)) {
    $filterType = $activeTab === 'expenses' ? 'expense' : $activeTab;
    $listSql .= ' AND l.ledger_type = ?';
}
if ($searchTerm !== '') {
    $listSql .= ' AND (l.ledger_number LIKE ? OR l.party_name LIKE ? OR l.customer_name LIKE ? OR l.notes LIKE ?)';
}
$listSql .= ' GROUP BY l.id ORDER BY l.ledger_date DESC, l.id DESC';
$listStmt = $conn->prepare($listSql);
if (!$listStmt) {
    http_response_code(500);
    exit('Unable to load finance records.');
}
if (in_array($activeTab, ['sales','purchase','expenses'], true) && $searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $listStmt->bind_param('sssss', $filterType, $like, $like, $like, $like);
} elseif (in_array($activeTab, ['sales','purchase','expenses'], true)) {
    $listStmt->bind_param('s', $filterType);
} elseif ($searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $listStmt->bind_param('ssss', $like, $like, $like, $like);
}
$listStmt->execute();
$listStmt->bind_result($rowId, $rowType, $rowNumber, $rowDate, $rowParty, $rowCustomer, $rowTotal);
$ledgers = [];
while ($listStmt->fetch()) {
    $ledgers[] = ['id'=>(int)$rowId, 'type'=>$rowType, 'number'=>$rowNumber, 'date'=>$rowDate, 'party'=>$rowParty, 'customer'=>$rowCustomer, 'total'=>(float)$rowTotal];
}
$listStmt->close();
$selectedId = filter_var($_GET['ledger_id'] ?? null, FILTER_VALIDATE_INT);
$selectedLedger = null;
if ($selectedId && $selectedId > 0) {
    $detailStmt = $conn->prepare('SELECT id, ledger_type, ledger_number, ledger_date, party_name, customer_name, notes FROM finance_ledgers WHERE id = ? LIMIT 1');
    if ($detailStmt) {
        $detailStmt->bind_param('i', $selectedId);
        $detailStmt->execute();
        $detailStmt->bind_result($dId, $dType, $dNumber, $dDate, $dParty, $dCustomer, $dNotes);
        if ($detailStmt->fetch()) {
            $selectedLedger = ['id'=>$dId,'type'=>$dType,'number'=>$dNumber,'date'=>$dDate,'party'=>$dParty,'customer'=>$dCustomer,'notes'=>$dNotes];
        }
        $detailStmt->close();
    }
}
if (!$selectedLedger && count($ledgers) > 0 && $activeTab !== 'reports') {
    $firstId = $ledgers[0]['id'];
    $detailStmt = $conn->prepare('SELECT id, ledger_type, ledger_number, ledger_date, party_name, customer_name, notes FROM finance_ledgers WHERE id = ? LIMIT 1');
    if ($detailStmt) {
        $detailStmt->bind_param('i', $firstId);
        $detailStmt->execute();
        $detailStmt->bind_result($dId, $dType, $dNumber, $dDate, $dParty, $dCustomer, $dNotes);
        if ($detailStmt->fetch()) {
            $selectedLedger = ['id'=>$dId,'type'=>$dType,'number'=>$dNumber,'date'=>$dDate,'party'=>$dParty,'customer'=>$dCustomer,'notes'=>$dNotes];
        }
        $detailStmt->close();
    }
}
$detailItems = [];
$detailTotal = 0.0;
if ($selectedLedger) {
    $itemRead = $conn->prepare('SELECT product_code, item_name, unit_price, quantity FROM finance_ledger_items WHERE ledger_id = ? ORDER BY id');
    if ($itemRead) {
        $itemRead->bind_param('i', $selectedLedger['id']);
        $itemRead->execute();
        $itemRead->bind_result($diCode, $diName, $diPrice, $diQty);
        while ($itemRead->fetch()) {
            $line = (float)$diPrice * (float)$diQty;
            $detailItems[] = ['code'=>$diCode,'name'=>$diName,'price'=>(float)$diPrice,'qty'=>(float)$diQty,'total'=>$line];
            $detailTotal += $line;
        }
        $itemRead->close();
    }
}
$summary = ['sales_count'=>0,'sales_total'=>0.0,'purchase_count'=>0,'purchase_total'=>0.0,'expense_count'=>0,'expense_total'=>0.0];
$sumStmt = $conn->prepare("SELECT l.ledger_type, COUNT(DISTINCT l.id), COALESCE(SUM(i.unit_price * i.quantity),0) FROM finance_ledgers l LEFT JOIN finance_ledger_items i ON i.ledger_id=l.id GROUP BY l.ledger_type");
if ($sumStmt) {
    $sumStmt->execute();
    $sumStmt->bind_result($sumType, $sumCount, $sumAmount);
    while ($sumStmt->fetch()) {
        $prefix = $sumType === 'expense' ? 'expense' : $sumType;
        $summary[$prefix.'_count'] = (int)$sumCount;
        $summary[$prefix.'_total'] = (float)$sumAmount;
    }
    $sumStmt->close();
}
$tabLabels = ['general'=>'General Ledger','sales'=>'Sales Ledger','purchase'=>'Purchase Ledger','expenses'=>'Expenses','reports'=>'Reports'];
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
    /* Pinaliit na ang lapad ng search bar dito */
    .search-bar {
      position: relative;
      width: 330px;
      max-width: 330px;
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
    /* Matches the Log Out link styling from the dashboard. */
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
  
    /* Functional finance controls; existing page styles above are preserved. */
    .finance-search-form { display:flex; width:100%; align-items:center; }
    .finance-search-form input { width:100%; }
    .ledger-pill { text-decoration:none; }
    .ledger-list-card, .report-card { background:#fff; border-radius:12px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03); }
    .ledger-list-title { color:#2a343d; font-size:1rem; font-weight:600; margin-bottom:12px; }
    .finance-list-table { width:100%; border-collapse:collapse; text-align:left; }
    .finance-list-table th { font-size:.72rem; font-weight:600; color:#718096; text-transform:uppercase; padding:11px 10px; border-bottom:1px solid #edf2f7; }
    .finance-list-table td { padding:11px 10px; font-size:.82rem; color:#334155; border-bottom:1px solid #f1f5f9; }
    .finance-list-table tr:last-child td { border-bottom:none; }
    .finance-list-table a { color:#1a6574; font-weight:600; text-decoration:none; }
    .finance-list-table a:hover { text-decoration:underline; }
    .finance-empty { padding:25px 15px; text-align:center; color:#64748b; font-size:.88rem; }
    .finance-status { padding:10px 14px; border-radius:8px; margin-bottom:16px; background:#e6fffa; color:#285e61; font-size:.85rem; }
    .finance-status.error { background:#fff5f5; color:#9b2c2c; }
    .finance-action { border:0; border-radius:7px; background:#122b49; color:#fff; padding:7px 11px; font-size:.78rem; cursor:pointer; text-decoration:none; display:inline-block; }
    .finance-action.danger { background:#b83232; }
    .invoice-header-bar a { color:#1e293b; display:flex; align-items:center; gap:7px; text-decoration:none; }
    .report-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px; }
    .report-metric { border:1px solid #edf2f7; border-radius:10px; padding:17px; }
    .report-metric span { display:block; color:#718096; font-size:.75rem; margin-bottom:7px; }
    .report-metric strong { display:block; color:#1e293b; font-size:1.25rem; }
    .modal-backdrop { display:none; position:fixed; inset:0; z-index:1000; background:rgba(15,23,42,.55); padding:24px; overflow:auto; }
    .modal-backdrop.open { display:block; }
    .finance-modal { width:min(760px,100%); margin:25px auto; background:#fff; border-radius:12px; box-shadow:0 15px 45px rgba(0,0,0,.2); overflow:hidden; }
    .finance-modal-head { background:#80d3ce; color:#1e293b; display:flex; align-items:center; justify-content:space-between; padding:14px 20px; font-weight:700; }
    .finance-modal-head button { background:none; border:0; font-size:1.5rem; cursor:pointer; color:#1e293b; }
    .finance-form { padding:20px; }
    .finance-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .finance-field { display:flex; flex-direction:column; gap:6px; margin-bottom:13px; }
    .finance-field label { font-size:.78rem; font-weight:600; color:#475569; }
    .finance-field input, .finance-field select, .finance-field textarea { width:100%; padding:9px 10px; border:1px solid #cbd5e0; border-radius:7px; background:#fff; color:#1e293b; font-size:.84rem; }
    .finance-field textarea { min-height:70px; resize:vertical; }
    .finance-items-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:15px 0 8px; color:#334155; font-weight:600; font-size:.9rem; }
    .finance-item-row { display:grid; grid-template-columns:1fr 1.6fr .8fr .6fr auto; gap:8px; align-items:center; margin-bottom:8px; }
    .finance-item-row input { min-width:0; width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:.8rem; }
    .finance-item-row button { border:0; background:#fee2e2; color:#991b1b; border-radius:6px; padding:7px 9px; cursor:pointer; }
    .finance-form-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }
    .finance-form-actions button { border:0; border-radius:18px; padding:9px 17px; cursor:pointer; font-size:.83rem; font-weight:600; }
    .finance-cancel { background:#e2e8f0; color:#334155; }
    .finance-save { background:#72dbd9; color:#1a365d; }
    .search-bar input { min-width:0; }
    @media (max-width:900px) { .report-grid { grid-template-columns:1fr; } .finance-item-row { grid-template-columns:1fr 1fr; padding:10px 0; border-bottom:1px solid #edf2f7; } .finance-form-grid { grid-template-columns:1fr; } .finance-list-table { min-width:620px; } .ledger-list-card { overflow-x:auto; } }
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
      <form class="search-bar finance-search-form" method="get" action="finances.php">
        <input type="hidden" name="tab" value="<?php echo finance_h($activeTab); ?>">
        <input type="text" name="q" value="<?php echo finance_h($searchTerm); ?>" placeholder="Search finance files..." aria-label="Search finance records">
        <button type="submit" class="search-btn" aria-label="Search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </form>
      <div class="topbar-actions">
        <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l-.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-.33-1.82A1.65 1.65 0 0 0 3 13h-.09A2 2 0 0 1 1 11a2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 7a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V1a2 2 0 0 1 2-1 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0l.06.06a2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
    </a>
        <button class="icon-btn" type="button" aria-label="Notifications"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></button>
        <div class="user-profile">
          <div class="profile-avatar"><?php echo finance_h($avatarInitials); ?></div>
          <div class="profile-info"><span class="user-name"><?php echo finance_h($userName); ?></span><span class="user-role"><?php echo $isAdmin ? 'Admin Staff' : 'User'; ?></span></div>
        </div>
        <a href="finances.php?logout=true" class="logout-link">Log Out</a>
      </div>
    </header>
    <div class="main-body">
      <aside class="sidebar"><nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
        <a href="messages.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>Messages</span></a>
        <a href="calendar.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span>Calendar</span></a>
        <a href="projects.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg><span>Projects</span></a>
        <a href="inventory.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4a2 2 0 0 0-1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg><span>Inventory</span></a>
        <a href="documents.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg><span>Documents</span></a>
        <?php if ($isAdmin): ?><a href="staff_management.php" class="menu-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span>Staff Management</span></a><?php endif; ?>
        <a href="finances.php" class="menu-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg><span>Finances</span></a>
      </nav></aside>
      <main class="content-area">
        <div class="page-header"><h1 class="page-title">Finances</h1><button class="btn-create-ledger" type="button" id="openLedgerModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Create Ledger</button></div>
        <?php if ($successMessage !== ''): ?><div class="finance-status"><?php echo finance_h($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="finance-status error"><?php echo finance_h($errorMessage); ?></div><?php endif; ?>
        <div class="ledger-pills-row">
          <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
            <a href="finances.php?tab=<?php echo urlencode($tabKey); ?>" class="ledger-pill <?php echo $activeTab === $tabKey ? 'active' : ''; ?>">
              <span class="pill-icon" style="color: <?php echo ['general'=>'#ec4899','sales'=>'#f97316','purchase'=>'#22c55e','expenses'=>'#ef4444','reports'=>'#3b82f6'][$tabKey]; ?>;"><?php echo ['general'=>'📖','sales'=>'📊','purchase'=>'💳','expenses'=>'💲','reports'=>'📄'][$tabKey]; ?></span><span><?php echo finance_h($tabLabel); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php if ($activeTab === 'reports'): ?>
          <div class="report-card"><h2 class="ledger-list-title">Finance Summary Report</h2><div class="report-grid">
            <div class="report-metric"><span>Sales Records</span><strong><?php echo (int)$summary['sales_count']; ?></strong><span>Total sales</span><strong><?php echo finance_money($summary['sales_total']); ?></strong></div>
            <div class="report-metric"><span>Purchase Records</span><strong><?php echo (int)$summary['purchase_count']; ?></strong><span>Total purchases</span><strong><?php echo finance_money($summary['purchase_total']); ?></strong></div>
            <div class="report-metric"><span>Expense Records</span><strong><?php echo (int)$summary['expense_count']; ?></strong><span>Total expenses</span><strong><?php echo finance_money($summary['expense_total']); ?></strong></div>
          </div><p style="margin-top:18px;color:#64748b;font-size:.83rem;">Net amount (sales less purchases and expenses): <strong style="color:#1e293b;"><?php echo finance_money($summary['sales_total'] - $summary['purchase_total'] - $summary['expense_total']); ?></strong></p>
          <p style="margin-top:15px;"><a class="finance-action" href="finances.php?tab=reports&amp;export=csv">Download CSV Report</a></p></div>
        <?php else: ?>
          <section class="ledger-list-card"><h2 class="ledger-list-title"><?php echo finance_h($tabLabels[$activeTab]); ?> Records</h2>
            <?php if (!$ledgers): ?><div class="finance-empty">No finance records found. Click <strong>Create Ledger</strong> to add your first record.</div>
            <?php else: ?><table class="finance-list-table"><thead><tr><th>Reference No.</th><th>Date</th><th>Type</th><th>Salesman / Supplier</th><th>Customer</th><th>Total</th><th>View</th></tr></thead><tbody>
              <?php foreach ($ledgers as $entry): ?><tr><td><?php echo finance_h($entry['number']); ?></td><td><?php echo date('m-d-Y', strtotime($entry['date'])); ?></td><td><?php echo finance_h(finance_type_label($entry['type'])); ?></td><td><?php echo finance_h($entry['party']); ?></td><td><?php echo finance_h($entry['customer'] !== '' ? $entry['customer'] : '—'); ?></td><td><?php echo finance_money($entry['total']); ?></td><td><a href="finances.php?tab=<?php echo urlencode($activeTab); ?>&amp;ledger_id=<?php echo (int)$entry['id']; ?>">View details</a></td></tr><?php endforeach; ?>
            </tbody></table><?php endif; ?></section>
          <?php if ($selectedLedger): ?>
          <div class="invoice-card">
            <div class="invoice-header-bar"><span><?php echo finance_h(strtoupper(finance_type_label($selectedLedger['type'])) . ' DETAILS'); ?></span><a href="finances.php?tab=<?php echo urlencode($activeTab); ?>&amp;export=csv" title="Export this list as CSV"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Export CSV</a></div>
            <div class="invoice-meta-grid"><div class="meta-box"><span class="meta-label">Invoice / Reference Number</span><span class="meta-value"><?php echo finance_h($selectedLedger['number']); ?></span></div><div class="meta-box"><span class="meta-label">Date</span><span class="meta-value"><?php echo date('m-d-Y', strtotime($selectedLedger['date'])); ?></span></div><div class="meta-box"><span class="meta-label"><?php echo $selectedLedger['type'] === 'sales' ? 'Salesman' : 'Supplier / Payee'; ?></span><span class="meta-value"><?php echo finance_h($selectedLedger['party']); ?></span></div><div class="meta-box"><span class="meta-label">Customer / Notes</span><span class="meta-value"><?php echo finance_h($selectedLedger['customer'] !== '' ? $selectedLedger['customer'] : '—'); ?></span></div></div>
            <?php if ($selectedLedger['notes'] !== ''): ?><p style="padding:12px 20px;color:#64748b;font-size:.82rem;">Notes: <?php echo finance_h($selectedLedger['notes']); ?></p><?php endif; ?>
            <div class="order-summary-title">Order Summary</div><table class="order-table"><thead><tr><th>Product Code</th><th>Item</th><th>Unit Price</th><th>Quantity</th><th>Total</th></tr></thead><tbody>
              <?php foreach ($detailItems as $item): ?><tr><td><?php echo finance_h($item['code'] !== '' ? $item['code'] : '—'); ?></td><td><?php echo finance_h($item['name']); ?></td><td><?php echo finance_money($item['price']); ?></td><td><?php echo rtrim(rtrim(number_format($item['qty'], 2), '0'), '.'); ?></td><td><?php echo finance_money($item['total']); ?></td></tr><?php endforeach; ?>
              <tr><td colspan="4" style="text-align:right;">Total</td><td><?php echo finance_money($detailTotal); ?></td></tr>
            </tbody></table>
            <div style="display:flex;justify-content:flex-end;padding:14px 20px;"><form method="post" onsubmit="return confirm('Delete this ledger and all its line items? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo finance_h($csrfToken); ?>"><input type="hidden" name="form_action" value="delete_ledger"><input type="hidden" name="ledger_id" value="<?php echo (int)$selectedLedger['id']; ?>"><button class="finance-action danger" type="submit">Delete Ledger</button></form></div>
          </div>
          <?php else: ?><div class="invoice-card"><div class="finance-empty">Select a record above to view its details, or create a new ledger.</div></div><?php endif; ?>
        <?php endif; ?>
      </main>
    </div>
  </div>
  <div class="modal-backdrop" id="ledgerModal" aria-hidden="true"><section class="finance-modal" role="dialog" aria-modal="true" aria-labelledby="ledgerModalTitle">
    <div class="finance-modal-head"><span id="ledgerModalTitle">Create Ledger</span><button type="button" id="closeLedgerModal" aria-label="Close">&times;</button></div>
    <form class="finance-form" method="post" action="finances.php?tab=<?php echo finance_h($activeTab); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo finance_h($csrfToken); ?>"><input type="hidden" name="form_action" value="create_ledger">
      <div class="finance-form-grid">
        <div class="finance-field"><label for="ledger_type">Ledger Type *</label><select id="ledger_type" name="ledger_type" required><option value="sales">Sales Ledger</option><option value="purchase">Purchase Ledger</option><option value="expense">Expenses</option></select></div>
        <div class="finance-field"><label for="ledger_number">Invoice / Reference Number *</label><input id="ledger_number" name="ledger_number" maxlength="80" required placeholder="e.g. INV-2026-001"></div>
        <div class="finance-field"><label for="ledger_date">Date *</label><input type="date" id="ledger_date" name="ledger_date" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="finance-field"><label for="party_name">Salesman / Supplier / Payee *</label><input id="party_name" name="party_name" maxlength="180" required placeholder="Name"></div>
        <div class="finance-field"><label for="customer_name">Customer (optional)</label><input id="customer_name" name="customer_name" maxlength="180" placeholder="Customer name"></div>
        <div class="finance-field"><label for="notes">Notes (optional)</label><input id="notes" name="notes" placeholder="Short description"></div>
      </div>
      <div class="finance-items-head"><span>Line Items *</span><button type="button" class="finance-action" id="addFinanceItem">+ Add Item</button></div>
      <div id="financeItems"><div class="finance-item-row"><input name="product_code[]" maxlength="80" placeholder="Product code"><input name="item_name[]" maxlength="180" placeholder="Item / expense name" required><input type="number" name="unit_price[]" min="0" step="0.01" placeholder="Unit price" required><input type="number" name="quantity[]" min="0.01" step="0.01" value="1" placeholder="Qty" required><button type="button" class="remove-finance-item" aria-label="Remove item" disabled>&times;</button></div></div>
      <div class="finance-form-actions"><button type="button" class="finance-cancel" id="cancelLedgerModal">Cancel</button><button type="submit" class="finance-save">Save Ledger</button></div>
    </form>
  </section></div>
  <script>
    (function () {
      const modal = document.getElementById('ledgerModal');
      const openBtn = document.getElementById('openLedgerModal');
      const closeBtn = document.getElementById('closeLedgerModal');
      const cancelBtn = document.getElementById('cancelLedgerModal');
      const itemsWrap = document.getElementById('financeItems');
      function openModal() { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); document.getElementById('ledger_number').focus(); }
      function closeModal() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); }
      openBtn.addEventListener('click', openModal);
      closeBtn.addEventListener('click', closeModal);
      cancelBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
      document.getElementById('addFinanceItem').addEventListener('click', function () {
        const row = document.createElement('div'); row.className = 'finance-item-row';
        row.innerHTML = '<input name="product_code[]" maxlength="80" placeholder="Product code"><input name="item_name[]" maxlength="180" placeholder="Item / expense name" required><input type="number" name="unit_price[]" min="0" step="0.01" placeholder="Unit price" required><input type="number" name="quantity[]" min="0.01" step="0.01" value="1" placeholder="Qty" required><button type="button" class="remove-finance-item" aria-label="Remove item">&times;</button>';
        itemsWrap.appendChild(row); updateRemoveButtons(); row.querySelector('input[name="item_name[]"]').focus();
      });
      function updateRemoveButtons() {
        const buttons = itemsWrap.querySelectorAll('.remove-finance-item');
        buttons.forEach(function (button) { button.disabled = buttons.length === 1; });
      }
      itemsWrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-finance-item') && itemsWrap.querySelectorAll('.finance-item-row').length > 1) { e.target.closest('.finance-item-row').remove(); updateRemoveButtons(); }
      });
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_ledger' && $errorMessage !== ''): ?>
      openModal();
      <?php endif; ?>
    })();
  </script>
</body>
</html>