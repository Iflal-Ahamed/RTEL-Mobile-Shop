<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/bundle_schema.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('bundle.php');
header('Content-Type: application/json');
rtel_ensure_bundle_schema($conn);

function json_out($status, $message = '')
{
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['bundle_name'] ?? ''));
$model = trim((string)($_POST['bundle_model'] ?? ''));
$price = (float)($_POST['bundle_price'] ?? 0);
$expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
$oldImage = trim((string)($_POST['old_image'] ?? ''));
$products = $_POST['product_ids'] ?? [];
if (!is_array($products)) $products = [];
$products = array_values(array_unique(array_filter(array_map('trim', $products), function ($x) { return $x !== ''; })));
if ($id <= 0 || $name === '' || $model === '' || $price <= 0 || count($products) < 2) {
    json_out('error', 'Invalid input.');
}
if ($expiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
    json_out('error', 'Invalid expiry date.');
}

// Optional image replacement
$newImage = '';
if (!empty($_FILES['bundle_image']['name'])) {
    $imgName = (string)$_FILES['bundle_image']['name'];
    $imgTmp = (string)$_FILES['bundle_image']['tmp_name'];
    $ext = strtolower((string)pathinfo($imgName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        json_out('error', 'Invalid image type.');
    }
    $newImage = date('YmdHis') . '_bundle_' . random_int(1000, 9999) . '.' . $ext;
    $newPath = __DIR__ . '/../../images/' . $newImage;
    if (!move_uploaded_file($imgTmp, $newPath)) {
        json_out('error', 'Image upload failed.');
    }
}

$stmt = $conn->prepare("UPDATE tblbundle SET bundle_name = ?, bundle_model = ?, bundle_price = ?, expiry_date = ?, bundle_image = COALESCE(NULLIF(?, ''), bundle_image) WHERE bundle_id = ?");
if (!$stmt) json_out('error', 'DB error');
$expiryVal = ($expiryDate === '') ? null : $expiryDate;
$stmt->bind_param("ssdssi", $name, $model, $price, $expiryVal, $newImage, $id);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) {
    if ($newImage !== '') @unlink(__DIR__ . '/../../images/' . $newImage);
    rtel_admin_log_event($conn, 'bundle_edit', 'failed', 'Failed to update bundle #' . $id);
    json_out('error', 'Update failed');
}
if ($newImage !== '' && $oldImage !== '' && $oldImage !== $newImage) {
    $oldPath = __DIR__ . '/../../images/' . basename($oldImage);
    if (is_file($oldPath)) @unlink($oldPath);
}

$delStmt = $conn->prepare("DELETE FROM tblbundle_item WHERE bundle_id = ?");
if ($delStmt) {
    $delStmt->bind_param("i", $id);
    $delStmt->execute();
    $delStmt->close();
}
$itemStmt = $conn->prepare("INSERT INTO tblbundle_item (bundle_id, product_id, sort_order) VALUES (?, ?, ?)");
if ($itemStmt) {
    $i = 1;
    foreach ($products as $pid) {
        $itemStmt->bind_param("isi", $id, $pid, $i);
        $itemStmt->execute();
        $i++;
    }
    $itemStmt->close();
}

rtel_admin_log_event($conn, 'bundle_edit', 'success', 'Updated bundle #' . $id . ': ' . $name);
json_out('success');
