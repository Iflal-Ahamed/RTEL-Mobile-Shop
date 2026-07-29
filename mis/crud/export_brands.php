<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$rows = [];
$where = '';
$idsParam = trim((string)($_GET['ids'] ?? ''));
if ($idsParam !== '') {
    $parts = array_filter(array_map('trim', explode(',', $idsParam)));
    $ids = [];
    foreach ($parts as $p) {
        if (ctype_digit($p)) {
            $ids[] = (int)$p;
        }
    }
    if (count($ids) > 0) {
        $where = ' WHERE brand_id IN (' . implode(',', $ids) . ')';
    }
}

$res = $conn->query("SELECT brand_id, name, description, status FROM tblbrand{$where} ORDER BY brand_id DESC");
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['brand_id'],
        'name' => (string)$r['name'],
        'description' => (string)$r['description'],
        'status' => ((int)$r['status'] === 1 ? 'Active' : 'Inactive')
    ];
}
echo json_encode(['status' => 'success', 'rows' => $rows]);
