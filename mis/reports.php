<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../web/includes/rtel_db_helpers.php';
rtel_require_admin_auth();
$conn->set_charset('utf8mb4');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return number_format((float)$v, 2); }

function valid_date($d)
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-30 days'));
$from = trim((string)($_GET['from'] ?? $defaultFrom));
$to = trim((string)($_GET['to'] ?? $today));
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
$allowedStatuses = ['all', 'pending', 'accepted', 'on the way', 'delivered', 'completed', 'rejected'];
if (!valid_date($from)) $from = $defaultFrom;
if (!valid_date($to)) $to = $today;
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}
if (!in_array($status, $allowedStatuses, true)) $status = 'all';

$custCol = rtel_customer_display_name_column($conn);
$statusWhere = $status === 'all' ? '' : " AND LOWER(COALESCE(o.status,'pending')) = ? ";

$summary = [
    'orders' => 0,
    'completed' => 0,
    'in_progress' => 0,
    'sales' => 0.0,
    'shipping' => 0.0,
    'coupon_discount' => 0.0,
    'special_discount' => 0.0
];

$summarySql = "SELECT
    COUNT(*) AS orders,
    SUM(CASE WHEN LOWER(COALESCE(o.status,'pending'))='completed' THEN 1 ELSE 0 END) AS completed_orders,
    SUM(CASE WHEN LOWER(COALESCE(o.status,'pending')) IN ('pending','accepted','on the way','delivered') THEN 1 ELSE 0 END) AS in_progress_orders,
    COALESCE(SUM(ch.grand_total),0) AS total_sales,
    COALESCE(SUM(ch.shipping_fee),0) AS total_shipping,
    COALESCE(SUM(ch.coupon_discount),0) AS total_coupon_discount,
    COALESCE(SUM(ch.special_discount),0) AS total_special_discount
FROM tblorder o
LEFT JOIN tblorder_charge ch ON ch.order_id = o.order_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}";
$st = $conn->prepare($summarySql);
if ($st) {
    if ($status === 'all') {
        $st->bind_param('ss', $from, $to);
    } else {
        $st->bind_param('sss', $from, $to, $status);
    }
    $st->execute();
    $res = $st->get_result();
    if ($res && ($r = $res->fetch_assoc())) {
        $summary['orders'] = (int)($r['orders'] ?? 0);
        $summary['completed'] = (int)($r['completed_orders'] ?? 0);
        $summary['in_progress'] = (int)($r['in_progress_orders'] ?? 0);
        $summary['sales'] = (float)($r['total_sales'] ?? 0);
        $summary['shipping'] = (float)($r['total_shipping'] ?? 0);
        $summary['coupon_discount'] = (float)($r['total_coupon_discount'] ?? 0);
        $summary['special_discount'] = (float)($r['total_special_discount'] ?? 0);
    }
    $st->close();
}

$statusRows = [];
$statusSql = "SELECT LOWER(COALESCE(o.status,'pending')) AS order_status, COUNT(*) AS order_count,
    COALESCE(SUM(ch.grand_total),0) AS total_sales
FROM tblorder o
LEFT JOIN tblorder_charge ch ON ch.order_id = o.order_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}
GROUP BY LOWER(COALESCE(o.status,'pending'))
ORDER BY order_count DESC";
$st = $conn->prepare($statusSql);
if ($st) {
    if ($status === 'all') $st->bind_param('ss', $from, $to);
    else $st->bind_param('sss', $from, $to, $status);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($r = $res->fetch_assoc())) $statusRows[] = $r;
    $st->close();
}

$productRows = [];
$productSql = "SELECT od.product_id,
    COALESCE(p.name, CONCAT('Product #', od.product_id)) AS product_name,
    COALESCE(SUM(od.quantity),0) AS qty_sold,
    COALESCE(SUM(od.quantity * od.unitprice),0) AS gross_sales
FROM tblorder_details od
LEFT JOIN tblorder o ON o.order_id = od.order_id
LEFT JOIN tblproduct p ON p.product_id = od.product_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}
GROUP BY od.product_id, p.name
ORDER BY qty_sold DESC
LIMIT 15";
$st = $conn->prepare($productSql);
if ($st) {
    if ($status === 'all') $st->bind_param('ss', $from, $to);
    else $st->bind_param('sss', $from, $to, $status);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($r = $res->fetch_assoc())) $productRows[] = $r;
    $st->close();
}

$customerRows = [];
$customerSql = "SELECT o.cus_id,
    COALESCE(NULLIF(TRIM(c.`{$custCol}`),''), CONCAT('Customer #', o.cus_id)) AS customer_name,
    COUNT(*) AS order_count,
    COALESCE(SUM(ch.grand_total),0) AS total_spent
FROM tblorder o
LEFT JOIN tblcustomer c ON c.cus_id = o.cus_id
LEFT JOIN tblorder_charge ch ON ch.order_id = o.order_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}
GROUP BY o.cus_id, c.`{$custCol}`
ORDER BY total_spent DESC
LIMIT 15";
$st = $conn->prepare($customerSql);
if ($st) {
    if ($status === 'all') $st->bind_param('ss', $from, $to);
    else $st->bind_param('sss', $from, $to, $status);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($r = $res->fetch_assoc())) $customerRows[] = $r;
    $st->close();
}

$couponRows = [];
$couponSql = "SELECT COALESCE(NULLIF(TRIM(ch.coupon_code),''),'(No Coupon)') AS coupon_code,
    COUNT(*) AS used_orders,
    COALESCE(SUM(ch.coupon_discount),0) AS discount_total
FROM tblorder_charge ch
LEFT JOIN tblorder o ON o.order_id = ch.order_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}
GROUP BY COALESCE(NULLIF(TRIM(ch.coupon_code),''),'(No Coupon)')
HAVING coupon_code <> '(No Coupon)'
ORDER BY used_orders DESC, discount_total DESC
LIMIT 15";
$st = $conn->prepare($couponSql);
if ($st) {
    if ($status === 'all') $st->bind_param('ss', $from, $to);
    else $st->bind_param('sss', $from, $to, $status);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($r = $res->fetch_assoc())) $couponRows[] = $r;
    $st->close();
}

$orderRows = [];
$orderSql = "SELECT o.order_id,
    DATE_FORMAT(COALESCE(o.ordered_date, NOW()), '%Y-%m-%d') AS ordered_day,
    COALESCE(NULLIF(TRIM(c.`{$custCol}`),''), CONCAT('Customer #', o.cus_id)) AS customer_name,
    LOWER(COALESCE(o.status,'pending')) AS order_status,
    COALESCE(ch.subtotal,0) AS subtotal,
    COALESCE(ch.coupon_code,'') AS coupon_code,
    COALESCE(ch.coupon_discount,0) AS coupon_discount,
    COALESCE(ch.special_discount,0) AS special_discount,
    COALESCE(ch.shipping_fee,0) AS shipping_fee,
    COALESCE(ch.grand_total,0) AS grand_total
FROM tblorder o
LEFT JOIN tblcustomer c ON c.cus_id = o.cus_id
LEFT JOIN tblorder_charge ch ON ch.order_id = o.order_id
WHERE DATE(COALESCE(o.ordered_date, NOW())) BETWEEN ? AND ? {$statusWhere}
ORDER BY COALESCE(o.ordered_date, NOW()) DESC
LIMIT 500";
$st = $conn->prepare($orderSql);
if ($st) {
    if ($status === 'all') $st->bind_param('ss', $from, $to);
    else $st->bind_param('sss', $from, $to, $status);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($r = $res->fetch_assoc())) $orderRows[] = $r;
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R-TEL Reports</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <style>
        .report-filter-card { border:1px solid #e8edf4; border-radius:12px; }
        .report-kpi { border:1px solid #e8edf4; border-radius:12px; padding:14px; height:100%; background:#fff; }
        .report-kpi .label { font-size:13px; color:#6c757d; margin-bottom:6px; font-weight:700; }
        .report-kpi .value { font-size:24px; font-weight:800; margin:0; color:#111827; }
        .report-box { border:1px solid #e8edf4; border-radius:12px; background:#fff; }
        .report-box .head { padding:12px 14px; border-bottom:1px solid #edf1f5; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .report-box .body { padding:10px 14px 14px; }
        .report-box h6 { margin:0; font-weight:700; }
        .table td, .table th { vertical-align: middle; }
        .small-note { color:#6c757d; font-size:12px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('reports.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Reports</h3>
                        <p class="text-muted mb-0">Generate all key business reports with date and status filters.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Reports</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section mt-2">
                <div class="card report-filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Order Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($allowedStatuses as $s): ?>
                                        <option value="<?php echo h($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                                            <?php echo h(ucwords($s)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-grid">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Generate Report</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3"><div class="report-kpi"><div class="label">Total Orders</div><p class="value"><?php echo (int)$summary['orders']; ?></p></div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="report-kpi"><div class="label">Completed Orders</div><p class="value"><?php echo (int)$summary['completed']; ?></p></div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="report-kpi"><div class="label">Orders In Progress</div><p class="value"><?php echo (int)$summary['in_progress']; ?></p></div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="report-kpi"><div class="label">Total Sales (Rs)</div><p class="value"><?php echo money($summary['sales']); ?></p></div></div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-12 col-md-4"><div class="report-kpi"><div class="label">Shipping Collected (Rs)</div><p class="value"><?php echo money($summary['shipping']); ?></p></div></div>
                    <div class="col-12 col-md-4"><div class="report-kpi"><div class="label">Coupon Discount (Rs)</div><p class="value"><?php echo money($summary['coupon_discount']); ?></p></div></div>
                    <div class="col-12 col-md-4"><div class="report-kpi"><div class="label">Special Discount (Rs)</div><p class="value"><?php echo money($summary['special_discount']); ?></p></div></div>
                </div>
            </section>

            <section class="section mt-3">
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <div class="report-box">
                            <div class="head">
                                <h6>Order Status Summary</h6>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm report-export-csv" data-table="tblStatus" data-file="order-status-summary.csv">CSV</button>
                                    <button class="btn btn-primary btn-sm report-export-pdf" data-table="tblStatus" data-title="Order Status Summary" data-file="order-status-summary.pdf">PDF</button>
                                </div>
                            </div>
                            <div class="body table-responsive">
                                <table class="table table-hover text-start mb-0" id="tblStatus">
                                    <thead><tr><th>Status</th><th>Orders</th><th>Sales (Rs)</th></tr></thead>
                                    <tbody>
                                    <?php if (!$statusRows): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No status data found.</td></tr>
                                    <?php else: foreach ($statusRows as $r): ?>
                                        <tr>
                                            <td><?php echo h(ucwords((string)($r['order_status'] ?? 'pending'))); ?></td>
                                            <td><?php echo (int)($r['order_count'] ?? 0); ?></td>
                                            <td><?php echo money($r['total_sales'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="report-box">
                            <div class="head">
                                <h6>Coupon Usage Summary</h6>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm report-export-csv" data-table="tblCoupon" data-file="coupon-usage-summary.csv">CSV</button>
                                    <button class="btn btn-primary btn-sm report-export-pdf" data-table="tblCoupon" data-title="Coupon Usage Summary" data-file="coupon-usage-summary.pdf">PDF</button>
                                </div>
                            </div>
                            <div class="body table-responsive">
                                <table class="table table-hover text-start mb-0" id="tblCoupon">
                                    <thead><tr><th>Coupon Code</th><th>Used Orders</th><th>Total Discount (Rs)</th></tr></thead>
                                    <tbody>
                                    <?php if (!$couponRows): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No coupon usage in selected range.</td></tr>
                                    <?php else: foreach ($couponRows as $r): ?>
                                        <tr>
                                            <td><?php echo h($r['coupon_code'] ?? '-'); ?></td>
                                            <td><?php echo (int)($r['used_orders'] ?? 0); ?></td>
                                            <td><?php echo money($r['discount_total'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="report-box">
                            <div class="head">
                                <h6>Top Products Report</h6>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm report-export-csv" data-table="tblProducts" data-file="top-products-report.csv">CSV</button>
                                    <button class="btn btn-primary btn-sm report-export-pdf" data-table="tblProducts" data-title="Top Products Report" data-file="top-products-report.pdf">PDF</button>
                                </div>
                            </div>
                            <div class="body table-responsive">
                                <table class="table table-hover text-start mb-0" id="tblProducts">
                                    <thead><tr><th>Product</th><th>Qty Sold</th><th>Gross Sales (Rs)</th></tr></thead>
                                    <tbody>
                                    <?php if (!$productRows): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No product sales in selected range.</td></tr>
                                    <?php else: foreach ($productRows as $r): ?>
                                        <tr>
                                            <td><?php echo h($r['product_name'] ?? '-'); ?></td>
                                            <td><?php echo (int)($r['qty_sold'] ?? 0); ?></td>
                                            <td><?php echo money($r['gross_sales'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="report-box">
                            <div class="head">
                                <h6>Top Customers Report</h6>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm report-export-csv" data-table="tblCustomers" data-file="top-customers-report.csv">CSV</button>
                                    <button class="btn btn-primary btn-sm report-export-pdf" data-table="tblCustomers" data-title="Top Customers Report" data-file="top-customers-report.pdf">PDF</button>
                                </div>
                            </div>
                            <div class="body table-responsive">
                                <table class="table table-hover text-start mb-0" id="tblCustomers">
                                    <thead><tr><th>Customer</th><th>Orders</th><th>Total Spent (Rs)</th></tr></thead>
                                    <tbody>
                                    <?php if (!$customerRows): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No customer sales in selected range.</td></tr>
                                    <?php else: foreach ($customerRows as $r): ?>
                                        <tr>
                                            <td><?php echo h($r['customer_name'] ?? '-'); ?></td>
                                            <td><?php echo (int)($r['order_count'] ?? 0); ?></td>
                                            <td><?php echo money($r['total_spent'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section mt-3">
                <div class="report-box">
                    <div class="head">
                        <h6>Detailed Orders Report</h6>
                        <div>
                            <button class="btn btn-outline-primary btn-sm report-export-csv" data-table="tblOrders" data-file="detailed-orders-report.csv">CSV</button>
                            <button class="btn btn-primary btn-sm report-export-pdf" data-table="tblOrders" data-title="Detailed Orders Report" data-file="detailed-orders-report.pdf">PDF</button>
                        </div>
                    </div>
                    <div class="body">
                        <p class="small-note mb-2">Showing up to 500 rows in selected range for fast loading.</p>
                        <div class="table-responsive">
                            <table class="table table-hover text-start mb-0" id="tblOrders">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Subtotal</th>
                                        <th>Coupon</th>
                                        <th>Coupon Discount</th>
                                        <th>Special Discount</th>
                                        <th>Shipping</th>
                                        <th>Grand Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$orderRows): ?>
                                    <tr><td colspan="10" class="text-center text-muted">No orders found for selected filters.</td></tr>
                                <?php else: foreach ($orderRows as $r): ?>
                                    <tr>
                                        <td><?php echo h($r['order_id'] ?? '-'); ?></td>
                                        <td><?php echo h($r['ordered_day'] ?? '-'); ?></td>
                                        <td><?php echo h($r['customer_name'] ?? '-'); ?></td>
                                        <td><?php echo h(ucwords((string)($r['order_status'] ?? 'pending'))); ?></td>
                                        <td><?php echo money($r['subtotal'] ?? 0); ?></td>
                                        <td><?php echo h(($r['coupon_code'] ?? '') !== '' ? (string)$r['coupon_code'] : '-'); ?></td>
                                        <td><?php echo money($r['coupon_discount'] ?? 0); ?></td>
                                        <td><?php echo money($r['special_discount'] ?? 0); ?></td>
                                        <td><?php echo money($r['shipping_fee'] ?? 0); ?></td>
                                        <td><?php echo money($r['grand_total'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function () {
    function tableRows(table) {
        const rows = [];
        const trs = table.querySelectorAll('tbody tr');
        trs.forEach((tr) => {
            const cells = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
            if (cells.length > 0 && !cells.join('').toLowerCase().includes('no ')) rows.push(cells);
        });
        return rows;
    }

    function tableHeaders(table) {
        return Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    }

    function exportCsv(tableId, fileName) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const headers = tableHeaders(table);
        const rows = tableRows(table);
        if (!rows.length) {
            Swal.fire('Info', 'No rows to export', 'info');
            return;
        }
        const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
        const lines = [headers.map(esc).join(',')].concat(rows.map(r => r.map(esc).join(',')));
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function exportPdf(tableId, title, fileName) {
        const table = document.getElementById(tableId);
        if (!table || !window.jspdf || !window.jspdf.jsPDF) return;
        const headers = [tableHeaders(table)];
        const rows = tableRows(table);
        if (!rows.length) {
            Swal.fire('Info', 'No rows to export', 'info');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape' });
        doc.setFontSize(12);
        doc.text(title, 14, 14);
        doc.autoTable({
            startY: 20,
            head: headers,
            body: rows,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: [13, 110, 253] }
        });
        doc.save(fileName);
    }

    document.querySelectorAll('.report-export-csv').forEach((btn) => {
        btn.addEventListener('click', function () {
            exportCsv(this.getAttribute('data-table'), this.getAttribute('data-file') || 'report.csv');
        });
    });
    document.querySelectorAll('.report-export-pdf').forEach((btn) => {
        btn.addEventListener('click', function () {
            exportPdf(
                this.getAttribute('data-table'),
                this.getAttribute('data-title') || 'Report',
                this.getAttribute('data-file') || 'report.pdf'
            );
        });
    });
})();
</script>
</body>
</html>
