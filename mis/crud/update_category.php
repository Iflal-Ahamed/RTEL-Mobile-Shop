<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('category.php');
header('Content-Type: application/json');

function out_json($status, $message = '')
{
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
if ($id <= 0 || $name === '') {
    out_json('error', 'Invalid input');
}

if (!empty($_FILES['image']['name'])) {
    $ext = strtolower((string)pathinfo((string)$_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        out_json('error', 'Invalid image type');
    }

    $oldStmt = $conn->prepare('SELECT image FROM tblcategory WHERE cat_id = ? LIMIT 1');
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $old = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if (!$old) {
        out_json('error', 'Category not found');
    }

    $newName = date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;
    $newPath = __DIR__ . '/../../images/' . $newName;
    if (!move_uploaded_file((string)$_FILES['image']['tmp_name'], $newPath)) {
        out_json('error', 'Image upload failed');
    }

    $stmt = $conn->prepare('UPDATE tblcategory SET name = ?, image = ? WHERE cat_id = ?');
    $stmt->bind_param('ssi', $name, $newName, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        @unlink($newPath);
        rtel_admin_log_event($conn, 'category_edit', 'failed', 'Failed updating category #' . $id);
        out_json('error', 'Update failed');
    }

    $oldPath = __DIR__ . '/../../images/' . (string)$old['image'];
    if (file_exists($oldPath)) @unlink($oldPath);
    rtel_admin_log_event($conn, 'category_edit', 'success', 'Updated category #' . $id . ': ' . $name);
    out_json('success');
}

$stmt = $conn->prepare('UPDATE tblcategory SET name = ? WHERE cat_id = ?');
$stmt->bind_param('si', $name, $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    rtel_admin_log_event($conn, 'category_edit', 'success', 'Updated category #' . $id . ': ' . $name);
} else {
    rtel_admin_log_event($conn, 'category_edit', 'failed', 'Failed updating category #' . $id);
}
out_json($ok ? 'success' : 'error', $ok ? '' : 'Update failed');
