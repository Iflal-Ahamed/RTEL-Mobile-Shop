<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
rtel_require_admin_auth();

$conn->set_charset('utf8mb4');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$hasEventType = false;
$check = $conn->query("SHOW COLUMNS FROM tbladmin_log LIKE 'event_type'");
if ($check) {
    $hasEventType = $check->num_rows > 0;
    $check->free();
}

$search = trim((string)($_GET['search'] ?? ''));
$eventFilter = trim((string)($_GET['event'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$adminFilter = trim((string)($_GET['admin'] ?? ''));
$fromDate = trim((string)($_GET['from'] ?? ''));
$toDate = trim((string)($_GET['to'] ?? ''));
$rows = [];
$adminOptions = [];

$eventLabels = [
    'login' => 'Login',
    'manager_create' => 'Manager Create',
    'manager_permission' => 'Manager Permission',
    'brand_add' => 'Brand Add',
    'brand_edit' => 'Brand Edit',
    'brand_delete' => 'Brand Delete',
    'brand_status' => 'Brand Status',
    'category_add' => 'Category Add',
    'category_edit' => 'Category Edit',
    'category_delete' => 'Category Delete',
    'category_status' => 'Category Status',
    'product_add' => 'Product Add',
    'product_edit' => 'Product Edit',
    'product_delete' => 'Product Delete',
    'product_status' => 'Product Status',
    'order_status' => 'Order Status',
    'shipping_rate_save' => 'Shipping Rate Save',
    'shipping_rate_delete' => 'Shipping Rate Delete',
    'shipping_rule_save' => 'Shipping Rule Save',
    'discount_save' => 'Discount Save',
    'discount_delete' => 'Discount Delete',
    'promotion_save' => 'Promotion Save',
    'promotion_delete' => 'Promotion Delete',
    'customer_block' => 'Customer Block/Unblock',
    'settings_update' => 'Settings Update',
];

function parse_role_from_note($note)
{
    $note = trim((string)$note);
    if (preg_match('/^\[([^\]]+)\]/', $note, $m)) {
        return trim((string)$m[1]);
    }
    return '';
}

function clean_note_prefix($note)
{
    return trim((string)preg_replace('/^\[[^\]]+\]\s*/', '', trim((string)$note)));
}

function rtel_render_log_rows($rows, $hasEventType, $eventLabels)
{
    ob_start();
    if (count($rows) === 0) {
        ?>
        <tr><td colspan="<?php echo $hasEventType ? 7 : 6; ?>" class="text-center text-muted">No logs found.</td></tr>
        <?php
    } else {
        foreach ($rows as $r) {
            if ($hasEventType) {
                ?>
                <tr>
                    <td><?php echo (int)$r['log_id']; ?></td>
                    <td>
                        <div><?php echo h($r['admin_id'] ?? '-'); ?></div>
                        <small class="text-muted"><?php echo h(parse_role_from_note((string)($r['note'] ?? '')) ?: 'Unknown role'); ?></small>
                    </td>
                    <td><?php echo h($eventLabels[(string)($r['event_type'] ?? '')] ?? ($r['event_type'] ?? '-')); ?></td>
                    <td><span class="badge <?php echo (($r['status'] ?? '') === 'success') ? 'bg-success' : 'bg-danger'; ?>"><?php echo h($r['status'] ?? '-'); ?></span></td>
                    <td><?php echo h($r['ip_address'] ?? '-'); ?></td>
                    <td><?php echo h(clean_note_prefix((string)($r['note'] ?? '-'))); ?></td>
                    <td><?php echo h($r['created_at'] ?? '-'); ?></td>
                </tr>
                <?php
            } else {
                ?>
                <tr>
                    <td><?php echo h($r['log_id'] ?? '-'); ?></td>
                    <td><?php echo h($r['admin_id'] ?? '-'); ?></td>
                    <td><?php echo h($r['action_type'] ?? '-'); ?></td>
                    <td><?php echo h($r['entity_type'] ?? '-'); ?></td>
                    <td><?php echo h($r['entity_id'] ?? '-'); ?></td>
                    <td><?php echo h($r['activity_date'] ?? '-'); ?></td>
                </tr>
                <?php
            }
        }
    }
    return (string)ob_get_clean();
}

$hasAdminNameCol = false;
$adminColCheck = $conn->query("SHOW COLUMNS FROM tbladmin LIKE 'name'");
if ($adminColCheck) {
    $hasAdminNameCol = ($adminColCheck->num_rows > 0);
    $adminColCheck->free();
}
$adminSql = $hasAdminNameCol
    ? "SELECT admin_id, COALESCE(NULLIF(TRIM(name), ''), admin_id) AS display_name FROM tbladmin ORDER BY display_name ASC"
    : "SELECT admin_id, admin_id AS display_name FROM tbladmin ORDER BY admin_id ASC";
$adminRes = $conn->query($adminSql);
if ($adminRes) {
    while ($ar = $adminRes->fetch_assoc()) {
        $adminOptions[] = [
            'admin_id' => (string)($ar['admin_id'] ?? ''),
            'display_name' => (string)($ar['display_name'] ?? ''),
        ];
    }
    $adminRes->free();
}

if ($hasEventType) {
    $where = "1=1";
    $types = "";
    $params = [];
    if ($search !== '') {
        $where .= " AND (admin_id LIKE ? OR event_type LIKE ? OR status LIKE ? OR note LIKE ?)";
        $types .= "ssss";
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($eventFilter !== '') {
        $where .= " AND event_type = ?";
        $types .= "s";
        $params[] = $eventFilter;
    }
    if ($statusFilter !== '') {
        $where .= " AND status = ?";
        $types .= "s";
        $params[] = $statusFilter;
    }
    if ($adminFilter !== '') {
        $where .= " AND admin_id = ?";
        $types .= "s";
        $params[] = $adminFilter;
    }
    if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $where .= " AND DATE(created_at) >= ?";
        $types .= "s";
        $params[] = $fromDate;
    }
    if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        $where .= " AND DATE(created_at) <= ?";
        $types .= "s";
        $params[] = $toDate;
    }
    $sql = "SELECT log_id, admin_id, event_type, status, ip_address, user_agent, note, created_at
            FROM tbladmin_log
            WHERE {$where}
            ORDER BY log_id DESC
            LIMIT 300";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== "" && !empty($params)) {
            $bind = [$types];
            foreach ($params as $k => $v) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
} else {
    $where = "1=1";
    $types = "";
    $params = [];
    if ($search !== '') {
        $where .= " AND (admin_id LIKE ? OR action_type LIKE ? OR entity_type LIKE ?)";
        $types .= "sss";
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($eventFilter !== '') {
        $where .= " AND action_type LIKE ?";
        $types .= "s";
        $params[] = $eventFilter . ':%';
    }
    if ($statusFilter !== '') {
        $where .= " AND action_type LIKE ?";
        $types .= "s";
        $params[] = '%:' . $statusFilter . '%';
    }
    if ($adminFilter !== '') {
        $where .= " AND admin_id = ?";
        $types .= "s";
        $params[] = $adminFilter;
    }
    if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $where .= " AND activity_date >= ?";
        $types .= "s";
        $params[] = $fromDate;
    }
    if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        $where .= " AND activity_date <= ?";
        $types .= "s";
        $params[] = $toDate;
    }
    $sql = "SELECT adminlog_id AS log_id, admin_id, action_type, entity_type, entity_id, activity_date
            FROM tbladmin_log
            WHERE {$where}
            ORDER BY adminlog_id DESC
            LIMIT 300";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== "" && !empty($params)) {
            $bind = [$types];
            foreach ($params as $k => $v) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

$tbodyHtml = rtel_render_log_rows($rows, $hasEventType, $eventLabels);
if (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'tbody_html' => $tbodyHtml]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R-tel Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('activity_log.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Activity Log</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Activity Log</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="mb-3" id="logFilterForm">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" name="search" value="<?php echo h($search); ?>" placeholder="Search text">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select name="admin" class="form-select">
                                        <option value="">All Admins</option>
                                        <?php foreach ($adminOptions as $ao): ?>
                                            <option value="<?php echo h($ao['admin_id']); ?>" <?php echo $adminFilter === $ao['admin_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($ao['display_name'] . ' (' . $ao['admin_id'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="event" class="form-select">
                                        <option value="">All Actions</option>
                                        <?php foreach ($eventLabels as $k => $v): ?>
                                            <option value="<?php echo h($k); ?>" <?php echo $eventFilter === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <select name="status" class="form-select">
                                        <option value="">All</option>
                                        <option value="success" <?php echo $statusFilter === 'success' ? 'selected' : ''; ?>>OK</option>
                                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <input type="date" class="form-control" name="from" value="<?php echo h($fromDate); ?>">
                                </div>
                                <div class="col-md-1">
                                    <input type="date" class="form-control" name="to" value="<?php echo h($toDate); ?>">
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button class="btn btn-primary btn-sm" type="submit">Apply Filters</button>
                                    <a href="activity_log.php" class="btn btn-light-secondary btn-sm" id="logFilterReset">Reset</a>
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-hover text-start">
                                <thead>
                                <?php if ($hasEventType): ?>
                                    <tr><th>ID</th><th>Who Did This</th><th>Action</th><th>Status</th><th>IP</th><th>Details</th><th>Date/Time</th></tr>
                                <?php else: ?>
                                    <tr><th>ID</th><th>Admin</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>Date</th></tr>
                                <?php endif; ?>
                                </thead>
                                <tbody id="activityLogTbody"><?php echo $tbodyHtml; ?></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function () {
    var form = document.getElementById('logFilterForm');
    var tbody = document.getElementById('activityLogTbody');
    var reset = document.getElementById('logFilterReset');
    if (!form || !tbody) return;

    function loadFilteredLogs() {
        var fd = new FormData(form);
        var params = new URLSearchParams();
        fd.forEach(function (v, k) {
            if (String(v || '').trim() !== '') params.append(k, String(v));
        });
        params.append('ajax', '1');
        fetch('activity_log.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.status !== 'success') return;
                tbody.innerHTML = String(data.tbody_html || '');
            })
            .catch(function () {});
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadFilteredLogs();
    });

    if (reset) {
        reset.addEventListener('click', function (e) {
            e.preventDefault();
            form.reset();
            loadFilteredLogs();
        });
    }
})();
</script>
</body>
</html>
