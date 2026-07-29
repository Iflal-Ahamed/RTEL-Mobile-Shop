<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('brand.php');
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
    exit;
}

$stmt = $conn->prepare("SELECT image FROM tblbrand WHERE brand_id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($data) {
    $imgPath = __DIR__ . "/../../images/" . $data['image'];
    if (file_exists($imgPath)) @unlink($imgPath);
}

$del = $conn->prepare("DELETE FROM tblbrand WHERE brand_id = ?");
$del->bind_param("i", $id);
$ok = $del->execute();
$del->close();
if ($ok) {
    rtel_admin_log_event($conn, 'brand_delete', 'success', 'Deleted brand #' . $id);
} else {
    rtel_admin_log_event($conn, 'brand_delete', 'failed', 'Failed deleting brand #' . $id);
}

echo json_encode(["status" => $ok ? "success" : "error", "message" => $ok ? "" : "Delete failed"]);
