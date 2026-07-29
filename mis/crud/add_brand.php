<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('brand.php');

header('Content-Type: application/json');

function json_out($status, $message = '')
{
    echo json_encode(["status" => $status, "message" => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out("error", "Invalid request method");
}

$name = trim((string)($_POST['name'] ?? ''));
$desc = trim((string)($_POST['description'] ?? ''));
if ($name === '' || $desc === '' || empty($_FILES['image']['name'])) {
    json_out("error", "All fields required");
}
if (strlen($name) > 50) {
    json_out("error", "Brand name too long");
}
if (strlen($desc) > 250) {
    json_out("error", "Description too long");
}

$image = (string)$_FILES['image']['name'];
$tmp = (string)$_FILES['image']['tmp_name'];
$ext = strtolower((string)pathinfo($image, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
    json_out("error", "Invalid image type");
}

$newName = date('YmdHis') . "_" . random_int(1000, 9999) . "." . $ext;
$path = __DIR__ . "/../../images/" . $newName;
if (!move_uploaded_file($tmp, $path)) {
    json_out("error", "Upload failed");
}

$stmt = $conn->prepare("INSERT INTO tblbrand (name, description, image, status) VALUES (?, ?, ?, 1)");
if (!$stmt) {
    @unlink($path);
    json_out("error", "DB error");
}
$stmt->bind_param("sss", $name, $desc, $newName);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    rtel_admin_log_event($conn, 'brand_add', 'success', 'Added brand: ' . $name);
    json_out("success");
}
rtel_admin_log_event($conn, 'brand_add', 'failed', 'Failed to add brand: ' . $name);
@unlink($path);
json_out("error", "Could not save brand");
