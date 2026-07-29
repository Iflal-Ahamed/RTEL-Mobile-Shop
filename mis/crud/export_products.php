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
        $where = ' WHERE product_id IN (' . implode(',', $ids) . ')';
    }
}

$res = $conn->query("SELECT product_id, name, quantity, price, status FROM tblproduct{$where} ORDER BY product_id DESC");
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['product_id'],
        'name' => (string)$r['name'],
        'quantity' => (int)$r['quantity'],
        'price' => number_format((float)$r['price'], 2),
        'status' => ((int)$r['status'] === 1 ? 'Active' : 'Inactive')
    ];
}
echo json_encode(['status' => 'success', 'rows' => $rows]);
