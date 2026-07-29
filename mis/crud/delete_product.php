<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/product_schema.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
header('Content-Type: application/json');
rtel_require_admin_auth();
rtel_require_admin_page_access('allproducts.php');
rtel_sync_product_relationships($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

$conn->begin_transaction();

$imgStmt = $conn->prepare("SELECT image_1, image_2, image_3, image_4, image_5 FROM tblimage WHERE product_id = ? LIMIT 1");
$imgStmt->bind_param('i', $id);
$imgStmt->execute();
$images = $imgStmt->get_result()->fetch_assoc();
$imgStmt->close();

$delProductStmt = $conn->prepare("DELETE FROM tblproduct WHERE product_id = ?");
$delProductStmt->bind_param('i', $id);
$ok = $delProductStmt->execute();
$delProductStmt->close();

if ($ok) {
    $conn->commit();
} else {
    $conn->rollback();
}
if ($ok) {
    rtel_admin_log_event($conn, 'product_delete', 'success', 'Deleted product #' . $id);
} else {
    rtel_admin_log_event($conn, 'product_delete', 'failed', 'Failed deleting product #' . $id);
}

if ($ok && $images) {
    foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5'] as $key) {
        $name = trim((string)($images[$key] ?? ''));
        if ($name !== '') {
            $path = __DIR__ . '/../../images/' . $name;
            if (file_exists($path)) @unlink($path);
        }
    }
}

echo json_encode(['status' => $ok ? 'success' : 'error']);
