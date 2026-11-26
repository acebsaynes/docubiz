<?php
// Export a saved catering quotation using the original Word template.
session_start();
function exportError($status, $message) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit($message);
}
if (empty($_SESSION['user_id'])) exportError(401, 'Please log in.');
require_once __DIR__ . '/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$id) exportError(400, 'Invalid project ID.');
if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
    exportError(500, 'Enable the PHP zip and dom extensions.');
}
$temp = null;
try {
    // Verify the current database role rather than trusting browser input.
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || $user['role'] !== 'admin') exportError(403, 'Admins only.');
    $stmt = $conn->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$project) exportError(404, 'Project not found.');
    if ($project['project_type'] !== 'Catering Quotation' ||
        empty($project['quotation_number']) || (int)$project['terms_accepted'] !== 1) {
        exportError(422, 'Only saved catering quotations with accepted terms can be downloaded.');
    }
    session_write_close();
    require_once __DIR__ . '/quotation_word.php';
    $temp = buildQuotationWord($project, __DIR__ . '/templates/Catering-Quotation-Template.docx');
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $project['quotation_number']) . '-Catering-Quotation.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($temp));
    readfile($temp);
} catch (Throwable $e) {
    error_log('Quotation export: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unable to create the quotation. Check the PHP error log and template path.';
    }
} finally {
    if ($temp && is_file($temp)) unlink($temp);
}
