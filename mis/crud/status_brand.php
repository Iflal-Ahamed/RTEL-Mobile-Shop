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

$stmt = $conn->prepare("UPDATE tblbrand SET status = IF(status = 1, 0, 1) WHERE brand_id = ?");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "DB error"]);
    exit;
}
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    rtel_admin_log_event($conn, 'brand_status', 'success', 'Toggled brand status #' . $id);
} else {
    rtel_admin_log_event($conn, 'brand_status', 'failed', 'Failed toggling brand status #' . $id);
}
echo json_encode(["status" => $ok ? "success" : "error"]);
