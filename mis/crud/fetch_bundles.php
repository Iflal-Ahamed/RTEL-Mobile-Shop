<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/bundle_schema.php';
header('Content-Type: application/json');
rtel_ensure_bundle_schema($conn);

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim((string)($_GET['search'] ?? ''));
$limit = 8;
$offset = ($page - 1) * $limit;

$types = '';
$params = [];
$whereSql = '';
if ($search !== '') {
    $whereSql = ' WHERE (b.bundle_name LIKE ? OR b.bundle_model LIKE ?)';
    $types = 's';
    $params[] = '%' . $search . '%';
    $types .= 's';
    $params[] = '%' . $search . '%';
}

$sql = "SELECT b.bundle_id, b.bundle_name, b.bundle_model, b.bundle_image, b.bundle_price, b.expiry_date, b.status,
               COUNT(bi.bundle_item_id) AS product_count,
               GROUP_CONCAT(CONCAT(COALESCE(p.name,''), IF(COALESCE(p.modal,'')='', '', CONCAT(' (', p.modal, ')'))) ORDER BY bi.sort_order SEPARATOR ' | ') AS product_names
        FROM tblbundle b
        LEFT JOIN tblbundle_item bi ON bi.bundle_id = b.bundle_id
        LEFT JOIN tblproduct p ON p.product_id = bi.product_id
        {$whereSql}
        GROUP BY b.bundle_id
        ORDER BY b.bundle_id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bindTypes = $types . 'ii';
$bindParams = $params;
$bindParams[] = $limit;
$bindParams[] = $offset;
$bind = [$bindTypes];
foreach ($bindParams as $k => $v) $bind[] = &$bindParams[$k];
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
$result = $stmt->get_result();

$rows = '';
$no = $offset + 1;
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['bundle_id'];
    $name = htmlspecialchars((string)$row['bundle_name'], ENT_QUOTES, 'UTF-8');
    $imgRaw = trim((string)($row['bundle_image'] ?? ''));
    $img = htmlspecialchars($imgRaw !== '' ? $imgRaw : 'smartphone.png', ENT_QUOTES, 'UTF-8');
    $model = htmlspecialchars((string)($row['bundle_model'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = number_format((float)$row['bundle_price'], 2);
    $expiry = trim((string)($row['expiry_date'] ?? ''));
    $expiryText = $expiry !== '' ? htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8') : 'No expiry';
    $productNamesRaw = trim((string)($row['product_names'] ?? ''));
    $productNames = '-';
    if ($productNamesRaw !== '') {
        $parts = array_values(array_filter(array_map('trim', explode('|', $productNamesRaw)), function ($x) { return $x !== ''; }));
        if (count($parts) > 0) {
            $safe = array_map(function ($x) {
                return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
            }, $parts);
            $productNames = '<div class="small text-wrap">' . implode('<br>', $safe) . '</div>';
        }
    }
    $statusBtn = ((int)$row['status'] === 1)
        ? "<button class='btn btn-success btn-sm' onclick='Bundle.status($id)'>Active</button>"
        : "<button class='btn btn-danger btn-sm' onclick='Bundle.status($id)'>Inactive</button>";
    $rows .= "
    <tr>
      <td><input type='checkbox' class='row-check' value='{$id}' data-label='{$name}'></td>
      <td>{$no}</td>
      <td><img src='../images/{$img}' width='46' height='46' style='object-fit:cover;border-radius:8px;'></td>
      <td>{$name}</td>
      <td>{$model}</td>
      <td>Rs. {$price}</td>
      <td>{$expiryText}</td>
      <td>{$productNames}</td>
      <td>{$statusBtn}</td>
      <td>
        <a href='javascript:void(0)' class='text-primary me-2' onclick='Bundle.edit($id)' title='Edit'><i class='bi bi-pencil-square'></i></a>
        <a href='javascript:void(0)' class='text-danger' onclick='Bundle.delete($id)' title='Delete'><i class='bi bi-trash'></i></a>
      </td>
    </tr>";
    $no++;
}
$stmt->close();

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblbundle b{$whereSql}");
if ($types !== '') {
    $countBind = [$types];
    $countParams = $params;
    foreach ($countParams as $k => $v) $countBind[] = &$countParams[$k];
    call_user_func_array([$countStmt, 'bind_param'], $countBind);
}
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pages = max(1, (int)ceil($total / $limit));
$pagination = '';
for ($i = 1; $i <= $pages; $i++) {
    $active = ($i === $page) ? 'active' : '';
    $pagination .= "<li class='page-item {$active}'><a href='javascript:void(0)' class='page-link' onclick='Bundle.load({$i})'>{$i}</a></li>";
}

echo json_encode([
    'status' => 'success',
    'table' => $rows === '' ? "<tr><td colspan='10' class='text-center'>No bundles found</td></tr>" : $rows,
    'pagination' => $pagination
]);
