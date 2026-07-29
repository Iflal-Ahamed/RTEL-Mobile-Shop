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

$delItems = $conn->prepare("DELETE FROM tblbundle_item WHERE bundle_id = ?");
if ($delItems) {
    $delItems->bind_param("i", $id);
    $delItems->execute();
    $delItems->close();
}
$del = $conn->prepare("DELETE FROM tblbundle WHERE bundle_id = ?");
$ok = false;
if ($del) {
    $del->bind_param("i", $id);
    $ok = $del->execute();
    $del->close();
}
if ($ok) {
    rtel_admin_log_event($conn, 'bundle_delete', 'success', 'Deleted bundle #' . $id);
} else {
    rtel_admin_log_event($conn, 'bundle_delete', 'failed', 'Failed deleting bundle #' . $id);
}
echo json_encode(['status' => $ok ? 'success' : 'error']);
