<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim((string)($_GET['search'] ?? ''));
$brandId = (int)($_GET['brand_id'] ?? 0);
$catId = (int)($_GET['cat_id'] ?? 0);
$limit = 8;
$offset = ($page - 1) * $limit;

$baseSelect = "SELECT p.product_id, p.name, p.quantity, p.price, p.status, i.image_1
               FROM tblproduct p
               LEFT JOIN tblimage i ON p.product_id = i.product_id";
$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = "p.name LIKE ?";
    $types .= 's';
    $params[] = '%' . $search . '%';
}
if ($brandId > 0) {
    $where[] = "p.brand_id = ?";
    $types .= 'i';
    $params[] = $brandId;
}
if ($catId > 0) {
    $where[] = "p.cat_id = ?";
    $types .= 'i';
    $params[] = $catId;
}
$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
$stmt = $conn->prepare($baseSelect . $whereSql . " ORDER BY p.product_id DESC LIMIT ? OFFSET ?");
$bindTypes = $types . 'ii';
$bindParams = $params;
$bindParams[] = $limit;
$bindParams[] = $offset;
$bind = [$bindTypes];
foreach ($bindParams as $k => $v) {
    $bind[] = &$bindParams[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
$result = $stmt->get_result();

$rows = '';
$no = $offset + 1;
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['product_id'];
    $name = htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8');
    $qty = (int)$row['quantity'];
    $price = number_format((float)$row['price'], 2);
    $image = htmlspecialchars((string)($row['image_1'] ?? ''), ENT_QUOTES, 'UTF-8');
    $statusBtn = ((int)$row['status'] === 1)
        ? "<button class='btn btn-success btn-sm' onclick='Product.status($id)'>Active</button>"
        : "<button class='btn btn-danger btn-sm' onclick='Product.status($id)'>Inactive</button>";

    $rows .= "
    <tr>
      <td><input type='checkbox' class='row-check' value='{$id}' data-label='{$name}'></td>
      <td>{$no}</td>
      <td><img src='../images/{$image}' width='50' height='50' style='object-fit:cover;border-radius:6px;'></td>
      <td>{$name}</td>
      <td>{$qty}</td>
      <td>{$price}</td>
      <td>{$statusBtn}</td>
      <td>
        <a href='javascript:void(0)' class='text-primary me-2' onclick='Product.edit($id)' title='Edit'><i class='bi bi-pencil-square'></i></a>
        <a href='javascript:void(0)' class='text-danger' onclick='Product.delete($id)' title='Delete'><i class='bi bi-trash'></i></a>
      </td>
    </tr>";
    $no++;
}
$stmt->close();

$countStmt = $conn->prepare("SELECT COUNT(*) total FROM tblproduct p" . $whereSql);
if ($types !== '') {
    $countBind = [$types];
    $countParams = $params;
    foreach ($countParams as $k => $v) {
        $countBind[] = &$countParams[$k];
    }
    call_user_func_array([$countStmt, 'bind_param'], $countBind);
}
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pages = max(1, (int)ceil($total / $limit));
$pagination = '';
for ($i = 1; $i <= $pages; $i++) {
    $active = ($i === $page) ? 'active' : '';
    $pagination .= "<li class='page-item {$active}'><a href='javascript:void(0)' class='page-link' onclick='Product.load({$i})'>{$i}</a></li>";
}

echo json_encode([
    'status' => 'success',
    'table' => $rows === '' ? "<tr><td colspan='8' class='text-center'>No products found</td></tr>" : $rows,
    'pagination' => $pagination
]);
