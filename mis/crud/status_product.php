<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
header('Content-Type: application/json');
rtel_require_admin_auth();
rtel_require_admin_page_access('allproducts.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE tblproduct SET status = IF(status = 1, 0, 1) WHERE product_id = ?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    rtel_admin_log_event($conn, 'product_status', 'success', 'Toggled product status #' . $id);
} else {
    rtel_admin_log_event($conn, 'product_status', 'failed', 'Failed toggling product status #' . $id);
}

echo json_encode(['status' => $ok ? 'success' : 'error']);
