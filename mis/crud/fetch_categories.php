<?php
include __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim((string)($_GET['search'] ?? ''));
$limit = 8;
$offset = ($page - 1) * $limit;

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare('SELECT c.cat_id, c.name, c.image, c.status, COUNT(p.product_id) AS product_count
                            FROM tblcategory c
                            LEFT JOIN tblproduct p ON p.cat_id = c.cat_id
                            WHERE c.name LIKE ?
                            GROUP BY c.cat_id
                            ORDER BY c.cat_id DESC
                            LIMIT ? OFFSET ?');
    $stmt->bind_param('sii', $like, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare('SELECT c.cat_id, c.name, c.image, c.status, COUNT(p.product_id) AS product_count
                            FROM tblcategory c
                            LEFT JOIN tblproduct p ON p.cat_id = c.cat_id
                            GROUP BY c.cat_id
                            ORDER BY c.cat_id DESC
                            LIMIT ? OFFSET ?');
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

$rows = '';
$no = $offset + 1;
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['cat_id'];
    $name = htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8');
    $image = htmlspecialchars((string)$row['image'], ENT_QUOTES, 'UTF-8');
    $productCount = (int)($row['product_count'] ?? 0);
    $statusBtn = ((int)$row['status'] === 1)
        ? "<button class='btn btn-success btn-sm' onclick='Category.status($id)' title='Click to deactivate'>Active</button>"
        : "<button class='btn btn-danger btn-sm' onclick='Category.status($id)' title='Click to activate'>Inactive</button>";

    $rows .= "
    <tr>
      <td><input type='checkbox' class='row-check' value='{$id}' data-label='{$name}'></td>
      <td>{$no}</td>
      <td><img src='../images/{$image}' width='50' height='50' style='object-fit:cover;border-radius:6px;'></td>
      <td>{$name}</td>
      <td>{$productCount}</td>
      <td>{$statusBtn}</td>
      <td>
        <a href='javascript:void(0)' class='text-primary me-2' onclick='Category.edit($id)' title='Edit'><i class='bi bi-pencil-square'></i></a>
        <a href='javascript:void(0)' class='text-danger' onclick='Category.delete($id)' title='Delete'><i class='bi bi-trash'></i></a>
      </td>
    </tr>";
    $no++;
}
$stmt->close();

if ($search !== '') {
    $countStmt = $conn->prepare('SELECT COUNT(*) total FROM tblcategory WHERE name LIKE ?');
    $countStmt->bind_param('s', $like);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $total = (int)$conn->query('SELECT COUNT(*) total FROM tblcategory')->fetch_assoc()['total'];
}

$pages = max(1, (int)ceil($total / $limit));
$pagination = '';
for ($i = 1; $i <= $pages; $i++) {
    $active = ($i === $page) ? 'active' : '';
    $pagination .= "<li class='page-item {$active}'><a href='javascript:void(0)' class='page-link' onclick='Category.load({$i})'>{$i}</a></li>";
}

echo json_encode([
    'status' => 'success',
    'table' => $rows === '' ? "<tr><td colspan='7' class='text-center'>No categories found</td></tr>" : $rows,
    'pagination' => $pagination
]);
