<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/bundle_schema.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('bundle.php');
header('Content-Type: application/json');
rtel_ensure_bundle_schema($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}
$stmt = $conn->prepare("UPDATE tblbundle SET status = IF(status = 1, 0, 1) WHERE bundle_id = ?");
$ok = false;
if ($stmt) {
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
}
if ($ok) {
    rtel_admin_log_event($conn, 'bundle_status', 'success', 'Toggled bundle status #' . $id);
} else {
    rtel_admin_log_event($conn, 'bundle_status', 'failed', 'Failed toggling bundle status #' . $id);
}
echo json_encode(['status' => $ok ? 'success' : 'error']);
