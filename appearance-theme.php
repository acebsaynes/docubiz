
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    return;
}

if (!isset($conn)) {
    require_once __DIR__ . "/db.php";
}

/* Ensure the appearance settings table exists */
$conn->query("
    CREATE TABLE IF NOT EXISTS user_appearance_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        theme VARCHAR(20) NOT NULL DEFAULT 'light',
        sidebar_feature VARCHAR(50) NOT NULL DEFAULT 'recent_changes',
        table_view VARCHAR(20) NOT NULL DEFAULT 'default',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* Load the logged-in user's saved appearance */
$userId = (int) $_SESSION["user_id"];

$theme = "light";
$tableView = "default";

$stmt = $conn->prepare("
    SELECT theme, table_view
    FROM user_appearance_settings
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (in_array($row["theme"], ["light", "dark", "system"], true)) {
            $theme = $row["theme"];
        }

        if (in_array($row["table_view"], ["default", "compact"], true)) {
            $tableView = $row["table_view"];
        }
    }

    $stmt->close();
}

$themeClass = "docubiz-theme-" . $theme;
$tableClass = $tableView === "compact" ? "docubiz-compact-tables" : "";
?>

<script>
document.documentElement.classList.add(
    <?php echo json_encode($themeClass); ?>
);

<?php if ($tableClass !== ""): ?>
document.documentElement.classList.add("docubiz-compact-tables");
<?php endif; ?>
</script>

<style>
/* GLOBAL DARK MODE */
html.docubiz-theme-dark body {
    background-color: #151e2b !important;
    color: #e2e8f0 !important;
}

html.docubiz-theme-dark .content-area,
html.docubiz-theme-dark .settings-main,
html.docubiz-theme-dark .main-content,
html.docubiz-theme-dark .page-content,
html.docubiz-theme-dark .content-wrapper {
    background-color: #1e293b !important;
    color: #e2e8f0 !important;
}

html.docubiz-theme-dark .settings-card,
html.docubiz-theme-dark .card,
html.docubiz-theme-dark .stat-card,
html.docubiz-theme-dark .dashboard-card,
html.docubiz-theme-dark .project-card,
html.docubiz-theme-dark .modal-content,
html.docubiz-theme-dark .table-container {
    background-color: #263548 !important;
    color: #e2e8f0 !important;
    border-color: #3b4b60 !important;
}

html.docubiz-theme-dark h1,
html.docubiz-theme-dark h2,
html.docubiz-theme-dark h3,
html.docubiz-theme-dark h4,
html.docubiz-theme-dark p,
html.docubiz-theme-dark label,
html.docubiz-theme-dark td,
html.docubiz-theme-dark th,
html.docubiz-theme-dark .page-title,
html.docubiz-theme-dark .card-title,
html.docubiz-theme-dark .card-desc {
    color: #e2e8f0;
}

html.docubiz-theme-dark input,
html.docubiz-theme-dark textarea,
html.docubiz-theme-dark select {
    background-color: #334155 !important;
    color: #f1f5f9 !important;
    border-color: #526176 !important;
}

html.docubiz-theme-dark input::placeholder,
html.docubiz-theme-dark textarea::placeholder {
    color: #aab7c8 !important;
}

html.docubiz-theme-dark table {
    color: #e2e8f0 !important;
}

html.docubiz-theme-dark th,
html.docubiz-theme-dark td {
    border-color: #3b4b60 !important;
}

html.docubiz-theme-dark tr:hover {
    background-color: #334155 !important;
}

/* Keep the existing DocuBiz navy navigation */
html.docubiz-theme-dark .topbar,
html.docubiz-theme-dark .sidebar,
html.docubiz-theme-dark .settings-subsidebar {
    background-color: #122b49 !important;
}

/* GLOBAL SYSTEM THEME */
@media (prefers-color-scheme: dark) {
    html.docubiz-theme-system body {
        background-color: #151e2b !important;
        color: #e2e8f0 !important;
    }

    html.docubiz-theme-system .content-area,
    html.docubiz-theme-system .settings-main,
    html.docubiz-theme-system .main-content,
    html.docubiz-theme-system .page-content,
    html.docubiz-theme-system .content-wrapper {
        background-color: #1e293b !important;
        color: #e2e8f0 !important;
    }

    html.docubiz-theme-system .settings-card,
    html.docubiz-theme-system .card,
    html.docubiz-theme-system .stat-card,
    html.docubiz-theme-system .dashboard-card,
    html.docubiz-theme-system .project-card,
    html.docubiz-theme-system .modal-content,
    html.docubiz-theme-system .table-container {
        background-color: #263548 !important;
        color: #e2e8f0 !important;
        border-color: #3b4b60 !important;
    }

    html.docubiz-theme-system h1,
    html.docubiz-theme-system h2,
    html.docubiz-theme-system h3,
    html.docubiz-theme-system h4,
    html.docubiz-theme-system p,
    html.docubiz-theme-system label,
    html.docubiz-theme-system td,
    html.docubiz-theme-system th,
    html.docubiz-theme-system .page-title,
    html.docubiz-theme-system .card-title,
    html.docubiz-theme-system .card-desc {
        color: #e2e8f0;
    }

    html.docubiz-theme-system input,
    html.docubiz-theme-system textarea,
    html.docubiz-theme-system select {
        background-color: #334155 !important;
        color: #f1f5f9 !important;
        border-color: #526176 !important;
    }

    html.docubiz-theme-system input::placeholder,
    html.docubiz-theme-system textarea::placeholder {
        color: #aab7c8 !important;
    }

    html.docubiz-theme-system table {
        color: #e2e8f0 !important;
    }

    html.docubiz-theme-system th,
    html.docubiz-theme-system td {
        border-color: #3b4b60 !important;
    }

    html.docubiz-theme-system .topbar,
    html.docubiz-theme-system .sidebar,
    html.docubiz-theme-system .settings-subsidebar {
        background-color: #122b49 !important;
    }
}

/* GLOBAL COMPACT TABLES */
html.docubiz-compact-tables table th,
html.docubiz-compact-tables table td {
    padding: 5px 8px !important;
}
</style>
