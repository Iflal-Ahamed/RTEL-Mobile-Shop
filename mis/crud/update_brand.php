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

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$desc = trim((string)($_POST['description'] ?? ''));
if ($id <= 0 || $name === '' || $desc === '') {
    json_out("error", "Invalid input");
}

if (!empty($_FILES['image']['name'])) {
    $ext = strtolower((string)pathinfo((string)$_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        json_out("error", "Invalid image type");
    }

    $oldImage = null;
    $oldStmt = $conn->prepare("SELECT image FROM tblbrand WHERE brand_id = ? LIMIT 1");
    if ($oldStmt) {
        $oldStmt->bind_param("i", $id);
        $oldStmt->execute();
        $old = $oldStmt->get_result()->fetch_assoc();
        $oldImage = (string)($old['image'] ?? '');
        $oldStmt->close();
    }

    $newName = date('YmdHis') . "_" . random_int(1000, 9999) . "." . $ext;
    $newPath = __DIR__ . "/../../images/" . $newName;
    if (!move_uploaded_file((string)$_FILES['image']['tmp_name'], $newPath)) {
        json_out("error", "Upload failed");
    }

    $stmt = $conn->prepare("UPDATE tblbrand SET name = ?, description = ?, image = ? WHERE brand_id = ?");
    if (!$stmt) {
        @unlink($newPath);
        json_out("error", "DB error");
    }
    $stmt->bind_param("sssi", $name, $desc, $newName, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        rtel_admin_log_event($conn, 'brand_edit', 'success', 'Updated brand #' . $id . ': ' . $name);
        if ($oldImage !== '') {
            $oldPath = __DIR__ . "/../../images/" . $oldImage;
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        json_out("success");
    }

    @unlink($newPath);
    rtel_admin_log_event($conn, 'brand_edit', 'failed', 'Failed to update brand #' . $id);
    json_out("error", "Update failed");
}

$stmt = $conn->prepare("UPDATE tblbrand SET name = ?, description = ? WHERE brand_id = ?");
$stmt->bind_param("ssi", $name, $desc, $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    rtel_admin_log_event($conn, 'brand_edit', 'success', 'Updated brand #' . $id . ': ' . $name);
} else {
    rtel_admin_log_event($conn, 'brand_edit', 'failed', 'Failed to update brand #' . $id);
}
json_out($ok ? "success" : "error", $ok ? "" : "Update failed");
