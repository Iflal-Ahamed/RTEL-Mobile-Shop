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

$stmt = $conn->prepare('UPDATE tblcategory SET status = IF(status = 1, 0, 1) WHERE cat_id = ?');
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    rtel_admin_log_event($conn, 'category_status', 'success', 'Toggled category status #' . $id);
} else {
    rtel_admin_log_event($conn, 'category_status', 'failed', 'Failed toggling category status #' . $id);
}

echo json_encode(['status' => $ok ? 'success' : 'error']);
