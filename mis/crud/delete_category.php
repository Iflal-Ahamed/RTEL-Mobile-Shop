<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('category.php');
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category ID']);
    exit;
}

$imgStmt = $conn->prepare('SELECT image FROM tblcategory WHERE cat_id = ? LIMIT 1');
$imgStmt->bind_param('i', $id);
$imgStmt->execute();
$row = $imgStmt->get_result()->fetch_assoc();
$imgStmt->close();

$delStmt = $conn->prepare('DELETE FROM tblcategory WHERE cat_id = ?');
$delStmt->bind_param('i', $id);
$ok = $delStmt->execute();
$delStmt->close();

if ($ok && $row) {
    $imgPath = __DIR__ . '/../../images/' . (string)$row['image'];
    if (file_exists($imgPath)) @unlink($imgPath);
}
if ($ok) {
    rtel_admin_log_event($conn, 'category_delete', 'success', 'Deleted category #' . $id);
} else {
    rtel_admin_log_event($conn, 'category_delete', 'failed', 'Failed deleting category #' . $id);
}

echo json_encode(['status' => $ok ? 'success' : 'error']);
