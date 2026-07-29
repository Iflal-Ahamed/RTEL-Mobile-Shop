<?php
header('Content-Type: application/json');
include __DIR__ . '/../connection.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = max(1, $page);

$limit = 5;
$offset = ($page - 1) * $limit;

if ($search !== '') {
    $like = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT b.*, COUNT(p.product_id) AS product_count
                            FROM tblbrand b
                            LEFT JOIN tblproduct p ON p.brand_id = b.brand_id
                            WHERE b.name LIKE ?
                            GROUP BY b.brand_id
                            ORDER BY b.brand_id DESC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $like, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare("SELECT b.*, COUNT(p.product_id) AS product_count
                            FROM tblbrand b
                            LEFT JOIN tblproduct p ON p.brand_id = b.brand_id
                            GROUP BY b.brand_id
                            ORDER BY b.brand_id DESC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

$table = "";
$no = $offset + 1;
while ($row = mysqli_fetch_assoc($result)) {
    $id = (int)$row['brand_id'];
    $name = htmlspecialchars($row['name']);
    $description = htmlspecialchars($row['description']);
    $image = htmlspecialchars($row['image']);
    $productCount = (int)($row['product_count'] ?? 0);

    $statusBtn = ($row['status'] == 1)
        ? "<button class='btn btn-success btn-sm' onclick='Brand.status($id)' data-bs-toggle='tooltip' title='Click to deactivate'>Active</button>"
        : "<button class='btn btn-danger btn-sm' onclick='Brand.status($id)' data-bs-toggle='tooltip' title='Click to activate'>Inactive</button>";

    $table .= "
        <tr>
            <td><input type='checkbox' class='row-check' value='{$id}' data-label='{$name}'></td>
            <td>{$no}</td>
            <td><img src='../images/{$image}' width='50' height='50' style='object-fit:cover;'></td>
            <td>{$name}</td>
            <td>{$description}</td>
            <td>{$productCount}</td>
            <td>{$statusBtn}</td>
            <td>
                <a href='javascript:void(0)' onclick='Brand.edit($id)' class='text-primary me-2' data-bs-toggle='tooltip' title='Edit Brand'>
                    <i class='bi bi-pencil-square' style='font-size:18px;'></i>
                </a>
                <a href='javascript:void(0)' onclick='Brand.delete($id, this)' class='text-danger' data-bs-toggle='tooltip' title='Delete Brand'>
                    <i class='bi bi-trash' style='font-size:18px;'></i>
                </a>
            </td>
        </tr>
    ";
    $no++;
}

if ($search !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM tblbrand WHERE name LIKE ?");
    $countStmt->bind_param("s", $like);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $countQuery = $conn->query("SELECT COUNT(*) as total FROM tblbrand");
    $total = (int)$countQuery->fetch_assoc()['total'];
}

$pages = ceil($total / $limit);
$pagination = "<ul class='pagination justify-content-start'>";
for ($i = 1; $i <= $pages; $i++) {
    $active = ($i == $page) ? "bg-dark text-white border-dark" : "";
    $pagination .= "<li class='page-item'><a href='javascript:void(0)' class='page-link {$active}' onclick='Brand.load($i)'>{$i}</a></li>";
}
$pagination .= "</ul>";
$stmt->close();

echo json_encode([
    "status" => "success",
    "table" => $table === '' ? "<tr><td colspan='8' class='text-center'>No brands found</td></tr>" : $table,
    "pagination" => $pagination
]);
