<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/bundle_schema.php';
header('Content-Type: application/json');
rtel_ensure_bundle_schema($conn);

$rows = [];
$where = '';
$idsParam = trim((string)($_GET['ids'] ?? ''));
if ($idsParam !== '') {
    $parts = array_filter(array_map('trim', explode(',', $idsParam)));
    $ids = [];
    foreach ($parts as $p) {
        if (ctype_digit($p)) $ids[] = (int)$p;
    }
    if (count($ids) > 0) {
        $where = ' WHERE b.bundle_id IN (' . implode(',', $ids) . ')';
    }
}

$sql = "SELECT b.bundle_id, b.bundle_name, b.bundle_model, b.bundle_price, b.expiry_date, b.status,
               GROUP_CONCAT(CONCAT(COALESCE(p.name,''), IF(COALESCE(p.modal,'')='', '', CONCAT(' (', p.modal, ')'))) ORDER BY bi.sort_order SEPARATOR ' | ') AS product_names
        FROM tblbundle b
        LEFT JOIN tblbundle_item bi ON bi.bundle_id = b.bundle_id
        LEFT JOIN tblproduct p ON p.product_id = bi.product_id
        {$where}
        GROUP BY b.bundle_id
        ORDER BY b.bundle_id DESC";
$res = $conn->query($sql);
while ($res && $r = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['bundle_id'],
        'name' => (string)$r['bundle_name'],
        'model' => (string)($r['bundle_model'] ?? ''),
        'price' => number_format((float)$r['bundle_price'], 2),
        'expiry' => (string)($r['expiry_date'] ?? ''),
        'products' => (string)($r['product_names'] ?? ''),
        'status' => ((int)$r['status'] === 1 ? 'Active' : 'Inactive')
    ];
}
echo json_encode(['status' => 'success', 'rows' => $rows]);
