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
        $where = ' WHERE cat_id IN (' . implode(',', $ids) . ')';
    }
}

$res = $conn->query("SELECT cat_id, name, status FROM tblcategory{$where} ORDER BY cat_id DESC");
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['cat_id'],
        'name' => (string)$r['name'],
        'status' => ((int)$r['status'] === 1 ? 'Active' : 'Inactive')
    ];
}
echo json_encode(['status' => 'success', 'rows' => $rows]);
