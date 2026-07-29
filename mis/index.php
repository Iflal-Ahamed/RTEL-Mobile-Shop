<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
rtel_require_admin_auth();
$conn->set_charset('utf8mb4');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Coupon schema guard for older databases (pre-merge structure).
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcoupon (
    coupon_id VARCHAR(20) NOT NULL PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    dispercentage INT(3) NOT NULL,
    expiry_date DATE NOT NULL
)");
@mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_type VARCHAR(20) NOT NULL DEFAULT 'available'");
@mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
@mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00");
@mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_scope VARCHAR(20) NOT NULL DEFAULT 'all'");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblorder_charge (
    charge_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(20) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    coupon_code VARCHAR(50) NOT NULL DEFAULT '',
    coupon_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    special_discount_label VARCHAR(100) NOT NULL DEFAULT '',
    special_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL
)");

$adminName = rtel_current_admin_name($conn);

function scalar_count($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_row($res);
    return (int)($row[0] ?? 0);
}
function scalar_sum($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0.0;
    $row = mysqli_fetch_row($res);
    return (float)($row[0] ?? 0);
}
$hasAddressTable = false;
$hasAddressDistrict = false;
$tblRes = mysqli_query($conn, "SHOW TABLES LIKE 'tbladdress'");
if ($tblRes && mysqli_num_rows($tblRes) > 0) {
    $hasAddressTable = true;
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM tbladdress LIKE 'district'");
    $hasAddressDistrict = ($colRes && mysqli_num_rows($colRes) > 0);
}
$newOrders = scalar_count($conn, "SELECT COUNT(*) FROM tblorder WHERE LOWER(COALESCE(status,'pending'))='pending'");
$newFeedbacks = scalar_count($conn, "SELECT COUNT(*) FROM tblcomment WHERE COALESCE(status,0)=0");
$newRatings = scalar_count($conn, "SELECT COUNT(*) FROM tblratings WHERE DATE(COALESCE(created_at, NOW())) = CURDATE()");
$totalProducts = scalar_count($conn, "SELECT COUNT(*) FROM tblproduct WHERE COALESCE(status,'1')='1'");
$totalCustomers = scalar_count($conn, "SELECT COUNT(*) FROM tblcustomer");
$totalCategories = scalar_count($conn, "SELECT COUNT(*) FROM tblcategory WHERE COALESCE(status,'1')='1'");
$totalBrands = scalar_count($conn, "SELECT COUNT(*) FROM tblbrand WHERE COALESCE(status,'1')='1'");

$thisMonthSales = scalar_sum($conn, "SELECT COALESCE(SUM(ch.grand_total),0)
    FROM tblorder_charge ch
    LEFT JOIN tblorder o ON o.order_id = ch.order_id
    WHERE MONTH(COALESCE(ch.created_at, NOW())) = MONTH(CURDATE())
      AND YEAR(COALESCE(ch.created_at, NOW())) = YEAR(CURDATE())
      AND LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')");

$totalSales = scalar_sum($conn, "SELECT COALESCE(SUM(ch.grand_total),0)
    FROM tblorder_charge ch
    LEFT JOIN tblorder o ON o.order_id = ch.order_id
    WHERE LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')");

$monthly = array_fill(0, 12, 0.0);
$monthlyRes = mysqli_query($conn, "SELECT MONTH(COALESCE(ch.created_at, NOW())) AS m, COALESCE(SUM(ch.grand_total),0) AS total
    FROM tblorder_charge ch
    LEFT JOIN tblorder o ON o.order_id = ch.order_id
    WHERE YEAR(COALESCE(ch.created_at, NOW())) = YEAR(CURDATE())
      AND LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')
    GROUP BY MONTH(COALESCE(ch.created_at, NOW()))");
while ($monthlyRes && $r = mysqli_fetch_assoc($monthlyRes)) {
    $m = (int)($r['m'] ?? 0);
    if ($m >= 1 && $m <= 12) $monthly[$m - 1] = (float)($r['total'] ?? 0);
}

$lowStock = [];
$lowStockRes = mysqli_query($conn, "SELECT product_id, name, quantity
    FROM tblproduct
    WHERE COALESCE(status,'1')='1' AND COALESCE(quantity,0) <= 5
    ORDER BY quantity ASC, product_id DESC
    LIMIT 50");
while ($lowStockRes && $r = mysqli_fetch_assoc($lowStockRes)) $lowStock[] = $r;

$trending = [];
$trendRes = mysqli_query($conn, "SELECT od.product_id, COALESCE(p.name, CONCAT('Product #', od.product_id)) AS product_name,
    COALESCE(SUM(od.quantity),0) AS sold_qty
    FROM tblorder_details od
    LEFT JOIN tblorder o ON o.order_id = od.order_id
    LEFT JOIN tblproduct p ON p.product_id = od.product_id
    WHERE LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')
    GROUP BY od.product_id, p.name
    ORDER BY sold_qty DESC
    LIMIT 50");
while ($trendRes && $r = mysqli_fetch_assoc($trendRes)) $trending[] = $r;

$topDistricts = [];
$districtSql = "SELECT 'Unknown' AS district, COUNT(*) AS order_count
    FROM tblorder o
    WHERE LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')
    GROUP BY 'Unknown'
    ORDER BY order_count DESC
    LIMIT 50";
if ($hasAddressTable && $hasAddressDistrict) {
    $districtSql = "SELECT COALESCE(NULLIF(TRIM(ad.district),''),'Unknown') AS district, COUNT(*) AS order_count
        FROM tblorder o
        LEFT JOIN (
            SELECT a1.cus_id, a1.district
            FROM tbladdress a1
            INNER JOIN (
                SELECT cus_id, MAX(CAST(SUBSTRING(address_id, 2) AS UNSIGNED)) AS max_no
                FROM tbladdress
                GROUP BY cus_id
            ) al ON al.cus_id = a1.cus_id
               AND CAST(SUBSTRING(a1.address_id, 2) AS UNSIGNED) = al.max_no
        ) ad ON ad.cus_id = o.cus_id
        WHERE LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')
        GROUP BY COALESCE(NULLIF(TRIM(ad.district),''),'Unknown')
        ORDER BY order_count DESC
        LIMIT 50";
}
$districtRes = mysqli_query($conn, $districtSql);
while ($districtRes && $r = mysqli_fetch_assoc($districtRes)) $topDistricts[] = $r;

$maxTrendingQty = 0;
foreach ($trending as $t) {
    $qty = (int)($t['sold_qty'] ?? 0);
    if ($qty > $maxTrendingQty) $maxTrendingQty = $qty;
}
$maxDistrictOrders = 0;
foreach ($topDistricts as $d) {
    $cnt = (int)($d['order_count'] ?? 0);
    if ($cnt > $maxDistrictOrders) $maxDistrictOrders = $cnt;
}

$processingOrders = scalar_count($conn, "SELECT COUNT(*) FROM tblorder WHERE LOWER(COALESCE(status,'pending')) IN ('pending','accepted','on the way','delivered')");
$completedOrders = scalar_count($conn, "SELECT COUNT(*) FROM tblorder WHERE LOWER(COALESCE(status,'pending'))='completed'");
$activeCoupons = scalar_count($conn, "SELECT COUNT(*) FROM tblcoupon WHERE COALESCE(coupon_type,'available')='available' AND COALESCE(status,1)=1 AND expiry_date >= CURDATE()");
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblpromotion (
    promotion_id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_scope VARCHAR(20) NOT NULL DEFAULT 'offer',
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    image VARCHAR(255) NOT NULL DEFAULT '',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    scope_type VARCHAR(20) NOT NULL DEFAULT '',
    scope_id VARCHAR(20) NOT NULL DEFAULT '',
    offer_type VARCHAR(20) NOT NULL DEFAULT 'percent',
    offer_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_date DATE NULL,
    end_date DATE NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS promotion_scope VARCHAR(20) NOT NULL DEFAULT 'offer'");
$activePromotions = scalar_count($conn, "SELECT COUNT(*) FROM tblpromotion WHERE COALESCE(promotion_scope,'offer')='offer' AND COALESCE(status,1)=1
    AND (start_date IS NULL OR start_date='' OR start_date<=CURDATE())
    AND (end_date IS NULL OR end_date='' OR end_date>=CURDATE())");

$lowStockTotal = scalar_count($conn, "SELECT COUNT(*) FROM tblproduct WHERE COALESCE(status,'1')='1' AND COALESCE(quantity,0) <= 5");

$cards = [
    ['label'=>'New Orders','value'=>$newOrders,'icon'=>'bi-basket','color'=>'#7c6bff'],
    ['label'=>'This Month Sales','value'=>number_format($thisMonthSales,2),'icon'=>'bi-receipt','color'=>'#00c5ff'],
    ['label'=>'Total Sales','value'=>number_format($totalSales,2),'icon'=>'bi-journal-text','color'=>'#bf5f5f'],
    ['label'=>'Total Customers','value'=>$totalCustomers,'icon'=>'bi-people-fill','color'=>'#0d6efd'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTel Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <style>
        .page-topline { display:flex; justify-content:flex-end; align-items:center; margin-bottom:14px; }
        .page-topline .admin-name { margin:0; font-size:15px; color:#6c757d; font-weight:600; }
        .page-content { background: transparent !important; }
        .dash-card { background:transparent; border-radius:12px; border:1px solid #ececec; padding:16px; height:100%; box-shadow:none; }
        .dash-card * { background: transparent; }
        .dash-row { display:flex; align-items:center; gap:12px; }
        .dash-icon { width:34px; height:34px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px; }
        .dash-title { margin:0; font-size:24px; font-weight:700; text-align:right; }
        .dash-label { margin:0 0 8px 0; font-size:14px; color:#6c757d; font-weight:700; text-transform:uppercase; letter-spacing:.2px; }
        .insight-card { background:transparent; border:1px solid #ececec; border-radius:12px; padding:12px 14px; height:100%; }
        .insight-title { font-size:14px; color:#6b6b6b; margin-bottom:4px; }
        .insight-value { font-size:22px; font-weight:800; margin:0; color:#111; }
        .list-card { background:transparent; border:1px solid #e9edf5; border-radius:14px; padding:0; height:100%; box-shadow:none; overflow:hidden; }
        .list-head { padding:12px 14px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #eef2f7; }
        .list-head h6 { margin:0; font-weight:800; font-size:14px; }
        .list-head .head-icon { width:30px; height:30px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; }
        .head-low { background:linear-gradient(135deg,#ef4444,#f97316); }
        .head-trend { background:linear-gradient(135deg,#6366f1,#8b5cf6); }
        .head-district { background:linear-gradient(135deg,#0ea5e9,#14b8a6); }
        .mini-list { list-style:none; margin:0; padding:0; }
        .mini-list-scroll { max-height: 286px; overflow-y: auto; }
        .mini-list-scroll::-webkit-scrollbar { width: 7px; }
        .mini-list-scroll::-webkit-scrollbar-track { background: #eef2f7; border-radius: 999px; }
        .mini-list-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .mini-list-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .mini-list li { display:flex; gap:10px; padding:10px 14px; border-bottom:1px solid #f1f4f8; font-size:14px; align-items:flex-start; transition:background .2s ease; }
        .mini-list li:hover { background:#f8fbff; }
        .mini-list li:last-child { border-bottom:none; }
        .mini-list a { color:#111; text-decoration:none; }
        .mini-list a:hover { color:#0d6efd; }
        .rank-dot { min-width:24px; height:24px; border-radius:8px; background:#eef2ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; margin-top:1px; }
        .mini-main { flex:1; min-width:0; }
        .mini-title { display:flex; justify-content:space-between; gap:8px; align-items:center; }
        .mini-title a { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; max-width:100%; }
        .mini-badge { color:#fff; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
        .badge-low { background:#dc3545; }
        .badge-warn { background:#f59e0b; }
        .badge-ok { background:#0d6efd; }
        .mini-bar { height:5px; border-radius:999px; background:#e9edf5; margin-top:8px; overflow:hidden; }
        .mini-fill { height:100%; border-radius:999px; }
        .fill-low { background:linear-gradient(90deg,#ef4444,#f97316); }
        .fill-trend { background:linear-gradient(90deg,#6366f1,#8b5cf6); }
        .fill-district { background:linear-gradient(90deg,#0ea5e9,#14b8a6); }
        .empty-row { padding:12px 14px; color:#6c757d; font-size:14px; }

        html[data-theme="dark"] .dash-card,
        html[data-theme="dark"] .insight-card,
        html[data-theme="dark"] .list-card,
        html[data-theme="dark"] .list-head,
        html[data-theme="dark"] .page-content {
            background: transparent !important;
        }
        html[data-theme="dark"] .dash-title,
        html[data-theme="dark"] .insight-value,
        html[data-theme="dark"] .dash-label,
        html[data-theme="dark"] .insight-title,
        html[data-theme="dark"] .list-head h6,
        html[data-theme="dark"] .mini-title a,
        html[data-theme="dark"] .empty-row {
            color: #ffffff !important;
        }
        html[data-theme="dark"] .mini-list li:hover,
        html[data-theme="dark"] .mini-list li:hover *,
        html[data-theme="dark"] .mini-list li:hover a {
            color: #000000 !important;
        }
        /* Explicit fix for "This Month Sales" card (2nd KPI card). */
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-card {
            background: transparent !important;
            border-color: #334155 !important;
            box-shadow: none !important;
        }
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2),
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-row,
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-label,
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-title {
            background: transparent !important;
        }
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-title,
        html[data-theme="dark"] .row.g-3 > .col-12.col-sm-6.col-lg-3:nth-child(2) .dash-label {
            color: #ffffff !important;
        }
        html[data-theme="dark"] .mini-list li:hover {
            background: #ffffff !important;
        }
        html[data-theme="dark"] .mini-list li:hover .mini-main,
        html[data-theme="dark"] .mini-list li:hover .mini-main *,
        html[data-theme="dark"] .mini-list li:hover .mini-title a,
        html[data-theme="dark"] .mini-list li:hover .mini-badge {
            color: #000000 !important;
        }
        .admin-alerts {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            background: #fff;
        }
        .admin-alerts-title {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 800;
        }
        .admin-alert-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            margin: 0 8px 8px 0;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: #111;
            font-size: 13px;
            font-weight: 600;
        }
        .admin-alert-count {
            background: #0d6efd;
            color: #fff;
            border-radius: 999px;
            padding: 1px 8px;
            font-size: 12px;
        }
        html[data-theme="dark"] .admin-alerts {
            background: transparent !important;
            border-color: #334155 !important;
        }
        html[data-theme="dark"] .admin-alerts-title,
        html[data-theme="dark"] .admin-alert-item {
            color: #fff !important;
        }
        html[data-theme="dark"] .admin-alert-item {
            background: rgba(148, 163, 184, 0.08) !important;
            border-color: #334155 !important;
        }
        html[data-theme="dark"] .mini-list-scroll::-webkit-scrollbar-track { background: #1f2937; }
        html[data-theme="dark"] .mini-list-scroll::-webkit-scrollbar-thumb { background: #475569; }
        html[data-theme="dark"] .mini-list-scroll::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('index.php'); ?>
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>

        <div class="page-content">
            <div class="page-topline">
                <p class="admin-name mb-0">Welcome, <?php echo h($adminName); ?></p>
            </div>
            <?php if ($newOrders > 0 || $newFeedbacks > 0 || $newRatings > 0): ?>
                <div class="admin-alerts">
                    <p class="admin-alerts-title"><i class="bi bi-bell-fill me-1"></i> New Alerts</p>
                    <?php if ($newOrders > 0): ?>
                        <a class="admin-alert-item" href="order.php"><span>New Orders</span><span class="admin-alert-count"><?php echo (int)$newOrders; ?></span></a>
                    <?php endif; ?>
                    <?php if ($newFeedbacks > 0): ?>
                        <a class="admin-alert-item" href="feedback.php"><span>New Feedbacks</span><span class="admin-alert-count"><?php echo (int)$newFeedbacks; ?></span></a>
                    <?php endif; ?>
                    <?php if ($newRatings > 0): ?>
                        <a class="admin-alert-item" href="feedback.php"><span>New Ratings (Today)</span><span class="admin-alert-count"><?php echo (int)$newRatings; ?></span></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($cards as $c): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="dash-card">
                            <p class="dash-label"><?php echo h($c['label']); ?></p>
                            <div class="dash-row">
                                <div class="dash-icon" style="background:<?php echo h($c['color']); ?>;">
                                    <i class="bi <?php echo h($c['icon']); ?>"></i>
                                </div>
                                <h6 class="dash-title ms-auto"><?php echo h((string)$c['value']); ?></h6>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-xl-4">
                    <div class="list-card">
                        <div class="list-head">
                            <h6>Low Stock Products</h6>
                            <div class="head-icon head-low"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        </div>
                        <div class="mini-list-scroll">
                        <ul class="mini-list">
                            <?php if (count($lowStock) === 0): ?>
                                <li><div class="empty-row">No low stock products.</div></li>
                            <?php else: foreach ($lowStock as $i => $r):
                                $qty = (int)($r['quantity'] ?? 0);
                                $stockBadge = $qty <= 2 ? 'badge-low' : ($qty <= 4 ? 'badge-warn' : 'badge-ok');
                                $stockText = $qty <= 2 ? 'Critical' : ($qty <= 4 ? 'Warning' : 'Low');
                                $barPct = max(5, min(100, (int)round(($qty / 5) * 100)));
                            ?>
                                <li>
                                    <div class="rank-dot"><?php echo (int)$i + 1; ?></div>
                                    <div class="mini-main">
                                        <div class="mini-title">
                                            <a href="allproducts.php?search=<?php echo urlencode((string)($r['name'] ?? '')); ?>" title="Open product list"><?php echo h($r['name'] ?? ('Product #' . ($r['product_id'] ?? ''))); ?></a>
                                            <span class="mini-badge <?php echo h($stockBadge); ?>">Qty <?php echo h((string)$qty); ?> · <?php echo h($stockText); ?></span>
                                        </div>
                                        <div class="mini-bar"><div class="mini-fill fill-low" style="width:<?php echo h((string)$barPct); ?>%"></div></div>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="list-card">
                        <div class="list-head">
                            <h6>Trending Products</h6>
                            <div class="head-icon head-trend"><i class="bi bi-graph-up-arrow"></i></div>
                        </div>
                        <div class="mini-list-scroll">
                        <ul class="mini-list">
                            <?php if (count($trending) === 0): ?>
                                <li><div class="empty-row">No order trend data yet.</div></li>
                            <?php else: foreach ($trending as $i => $r):
                                $sold = (int)($r['sold_qty'] ?? 0);
                                $barPct = $maxTrendingQty > 0 ? max(6, min(100, (int)round(($sold / $maxTrendingQty) * 100))) : 0;
                                $trendBadge = $i < 3 ? 'Hot' : 'Top';
                            ?>
                                <li>
                                    <div class="rank-dot"><?php echo (int)$i + 1; ?></div>
                                    <div class="mini-main">
                                        <div class="mini-title">
                                            <a href="allproducts.php?search=<?php echo urlencode((string)($r['product_name'] ?? '')); ?>" title="Open product list"><?php echo h($r['product_name'] ?? '-'); ?></a>
                                            <span class="mini-badge badge-ok"><?php echo h((string)$sold); ?> sold · <?php echo h($trendBadge); ?></span>
                                        </div>
                                        <div class="mini-bar"><div class="mini-fill fill-trend" style="width:<?php echo h((string)$barPct); ?>%"></div></div>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="list-card">
                        <div class="list-head">
                            <h6>Top Customer Districts</h6>
                            <div class="head-icon head-district"><i class="bi bi-geo-alt-fill"></i></div>
                        </div>
                        <div class="mini-list-scroll">
                        <ul class="mini-list">
                            <?php if (count($topDistricts) === 0): ?>
                                <li><div class="empty-row">No district data available.</div></li>
                            <?php else: foreach ($topDistricts as $i => $r):
                                $orders = (int)($r['order_count'] ?? 0);
                                $barPct = $maxDistrictOrders > 0 ? max(6, min(100, (int)round(($orders / $maxDistrictOrders) * 100))) : 0;
                                $districtBadge = $i < 3 ? 'High Demand' : 'Active';
                            ?>
                                <li>
                                    <div class="rank-dot"><?php echo (int)$i + 1; ?></div>
                                    <div class="mini-main">
                                        <div class="mini-title">
                                            <a href="customer.php?search=<?php echo urlencode((string)($r['district'] ?? 'Unknown')); ?>" title="Open customer list"><?php echo h($r['district'] ?? 'Unknown'); ?></a>
                                            <span class="mini-badge badge-ok"><?php echo h((string)$orders); ?> orders · <?php echo h($districtBadge); ?></span>
                                        </div>
                                        <div class="mini-bar"><div class="mini-fill fill-district" style="width:<?php echo h((string)$barPct); ?>%"></div></div>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h5>Monthly Sales</h5></div>
                        <div class="card-body"><div id="chart-profile-visit"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendors/apexcharts/apexcharts.js"></script>
<script>
new ApexCharts(document.querySelector("#chart-profile-visit"), {
    chart: { type: 'bar', height: 300, toolbar: { show: false } },
    dataLabels: { enabled: false },
    colors: ['#0d6efd'],
    series: [{ name: 'Sales', data: <?php echo json_encode(array_values($monthly)); ?> }],
    xaxis: { categories: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"] },
    yaxis: { labels: { formatter: function(v){ return Number(v).toFixed(0); } } }
}).render();
</script>
<script src="assets/js/main.js"></script>
</body>
</html>