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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_json('error', 'Invalid request method');
}

$name = trim((string)($_POST['name'] ?? ''));
if ($name === '' || empty($_FILES['image']['name'])) {
    out_json('error', 'Category name and image are required');
}
if (strlen($name) > 50) {
    out_json('error', 'Category name too long');
}

$ext = strtolower((string)pathinfo((string)$_FILES['image']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
    out_json('error', 'Invalid image type');
}

$newName = date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;
$path = __DIR__ . '/../../images/' . $newName;
if (!move_uploaded_file((string)$_FILES['image']['tmp_name'], $path)) {
    out_json('error', 'Image upload failed');
}

$stmt = $conn->prepare('INSERT INTO tblcategory (name, image, status) VALUES (?, ?, 1)');
if (!$stmt) {
    @unlink($path);
    out_json('error', 'DB error');
}
$stmt->bind_param('ss', $name, $newName);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    rtel_admin_log_event($conn, 'category_add', 'success', 'Added category: ' . $name);
    out_json('success');
}
rtel_admin_log_event($conn, 'category_add', 'failed', 'Failed to add category: ' . $name);
@unlink($path);
out_json('error', 'Insert failed');
