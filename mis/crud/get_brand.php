<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
    exit;
}

$stmt = $conn->prepare("SELECT brand_id, name, description, image, status FROM tblbrand WHERE brand_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "DB error"]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Brand not found"]);
    exit;
}
echo json_encode($data);
