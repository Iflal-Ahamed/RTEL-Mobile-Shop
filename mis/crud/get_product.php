<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

$stmt = $conn->prepare("SELECT p.product_id, p.cat_id, p.brand_id, p.name, p.modal, p.description, p.price, p.cprice, p.quantity, p.status, i.image_1, i.image_2, i.image_3, i.image_4, i.image_5,
                               COALESCE(c.name, '') AS cat_name,
                               COALESCE(b.name, '') AS brand_name
                        FROM tblproduct p
                        LEFT JOIN tblimage i ON p.product_id = i.product_id
                        LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
                        LEFT JOIN tblbrand b ON p.brand_id = b.brand_id
                        WHERE p.product_id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

$features = [];

$tblCheck = $conn->query("SHOW TABLES LIKE 'tblproduct_feature'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $f = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature WHERE product_id = ? ORDER BY feature_id ASC");
    if ($f) {
        $f->bind_param('i', $id);
        $f->execute();
        $fr = $f->get_result();
        while ($r = $fr->fetch_assoc()) {
            $features[] = $r;
        }
        $f->close();
    }
}

$colors = [];
$ramOptions = [];
$romOptions = [];
$cleanFeatures = [];
foreach ($features as $f) {
    $n = trim((string)($f['feature_name'] ?? ''));
    $v = trim((string)($f['feature_value'] ?? ''));
    if ($n === '' || $v === '') continue;
    if (preg_match('/\b(color|colour|colours)\b/i', $n)) {
        foreach (preg_split('/\s*[,|]\s*/', $v) as $c) {
            $c = trim((string)$c);
            if ($c !== '' && !in_array($c, $colors, true)) $colors[] = $c;
        }
        continue;
    }
    if (preg_match('/\b(ram|rom|storage|memory|capacity|variant|disk|ssd|hdd)\b|ram\s*\/\s*rom/i', $n)) {
        foreach (preg_split('/\s*[,|]\s*/', $v) as $s) {
            $s = trim((string)$s);
            if ($s !== '' && !in_array($s, $ramOptions, true)) $ramOptions[] = $s;
        }
        continue;
    }
    $cleanFeatures[] = ['feature_name' => $n, 'feature_value' => $v];
}

$row['specs'] = [
    'colors' => $colors,
    'ram_options' => $ramOptions,
    'rom_options' => $romOptions,
    'features' => $cleanFeatures
];
$imageList = [];
foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5'] as $k) {
    $img = trim((string)($row[$k] ?? ''));
    if ($img !== '' && !in_array($img, $imageList, true)) {
        $imageList[] = $img;
    }
}
$row['image_list'] = $imageList;

echo json_encode($row);
