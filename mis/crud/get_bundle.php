<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/bundle_schema.php';
header('Content-Type: application/json');
rtel_ensure_bundle_schema($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT bundle_id, bundle_name, bundle_model, bundle_image, bundle_price, expiry_date, status FROM tblbundle WHERE bundle_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$bundle = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$bundle) {
    echo json_encode(['status' => 'error', 'message' => 'Bundle not found']);
    exit;
}

$productIds = [];
$itemStmt = $conn->prepare("SELECT product_id FROM tblbundle_item WHERE bundle_id = ? ORDER BY sort_order ASC, bundle_item_id ASC");
if ($itemStmt) {
    $itemStmt->bind_param("i", $id);
    $itemStmt->execute();
    $res = $itemStmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $productIds[] = (string)$row['product_id'];
    }
    $itemStmt->close();
}

echo json_encode([
    'status' => 'success',
    'bundle_id' => (int)$bundle['bundle_id'],
    'bundle_name' => (string)$bundle['bundle_name'],
    'bundle_model' => (string)($bundle['bundle_model'] ?? ''),
    'bundle_image' => (string)($bundle['bundle_image'] ?? ''),
    'bundle_price' => (float)$bundle['bundle_price'],
    'expiry_date' => (string)($bundle['expiry_date'] ?? ''),
    'product_ids' => $productIds
]);
