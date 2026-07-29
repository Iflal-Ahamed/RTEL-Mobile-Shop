<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category ID']);
    exit;
}

$stmt = $conn->prepare('SELECT cat_id, name, image, status FROM tblcategory WHERE cat_id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Category not found']);
    exit;
}
echo json_encode($row);
