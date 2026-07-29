<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
include __DIR__ . "/../connection.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/activity_logger.php";
require_once __DIR__ . "/../../web/includes/rtel_db_helpers.php";
require_once __DIR__ . "/../../web/mail/mail_notifications.php";
rtel_require_admin_auth();
rtel_require_admin_page_access('order.php');

header("Content-Type: application/json; charset=utf-8");

if (!function_exists('order_api_fail_json')) {
    function order_api_fail_json($message = 'Order API error')
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
        }
        echo json_encode([
            "status" => "error",
            "message" => (string)$message
        ]);
        exit;
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    order_api_fail_json('Order API runtime warning: ' . (string)$message);
});

set_exception_handler(function ($e) {
    order_api_fail_json('Order API exception: ' . (string)$e->getMessage());
});

ob_start();

function json_out($arr)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode($arr);
    exit;
}

function clean_text($v, $max = 255)
{
    $v = trim((string)$v);
    return substr($v, 0, $max);
}

function customer_col_exists(mysqli $conn, $column)
{
    static $cache = [];
    $col = trim((string)$column);
    if ($col === '') return false;
    if (array_key_exists($col, $cache)) {
        return (bool)$cache[$col];
    }
    $safe = mysqli_real_escape_string($conn, $col);
    $res = $conn->query("SHOW COLUMNS FROM tblcustomer LIKE '{$safe}'");
    $exists = $res && $res->num_rows > 0;
    $cache[$col] = $exists;
    if ($res) $res->free();
    return $exists;
}

function parse_status($status)
{
    $s = strtolower(trim((string)$status));
    if ($s === "accepted") return "Accepted";
    if ($s === "rejected") return "Rejected";
    if ($s === "deleted") return "Deleted";
    if ($s === "delivered") return "Delivered";
    if ($s === "shipped") return "On the way";
    if ($s === "on-way") return "On the way";
    if ($s === "on_way") return "On the way";
    if ($s === "on the way") return "On the way";
    if ($s === "completed") return "Completed";
    return "Pending";
}

function next_status_for($normalizedCurrent)
{
    if ($normalizedCurrent === "Pending") return "Accepted";
    if ($normalizedCurrent === "Accepted") return "On the way";
    if ($normalizedCurrent === "On the way") return "Delivered";
    if ($normalizedCurrent === "Delivered") return "Completed";
    return "";
}

$conn->set_charset("utf8mb4");
// Core order schema guards used by all report/list/detail queries below.
mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER ordered_date");
mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status_reason VARCHAR(255) NULL AFTER status");
mysqli_query($conn, "ALTER TABLE tblorder_details ADD COLUMN IF NOT EXISTS selected_feature VARCHAR(255) NOT NULL DEFAULT ''");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblorder_charge (
    order_id VARCHAR(10) NOT NULL PRIMARY KEY,
    cus_id VARCHAR(250) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    coupon_code VARCHAR(30) NOT NULL DEFAULT '',
    coupon_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    special_discount_label VARCHAR(120) NOT NULL DEFAULT '',
    special_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblpayment (
    payment_id VARCHAR(10) NOT NULL PRIMARY KEY,
    order_id VARCHAR(10) NOT NULL,
    cus_id VARCHAR(250) NOT NULL,
    method VARCHAR(20) NOT NULL,
    gateway_ref VARCHAR(120) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'LKR',
    payment_status VARCHAR(20) NOT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL
)");

$action = $_GET["action"] ?? $_POST["action"] ?? "";
$custCol = rtel_customer_display_name_column($conn);

if ($action === "list") {
    // Main order listing query for table view with status/search filters + pagination.
    $search = clean_text($_GET["search"] ?? "", 120);
    $filterStatus = clean_text($_GET["status"] ?? "all", 20);
    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(5, min(100, (int)($_GET["per_page"] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $where = "1=1";
    $types = "";
    $params = [];
    $customerDisplayExpr = "COALESCE(NULLIF(TRIM(c.`{$custCol}`),''), CONCAT('Customer #', o.cus_id))";
    if ($search !== "") {
        $where .= " AND (o.order_id LIKE ? OR {$customerDisplayExpr} LIKE ? OR c.email LIKE ?)";
        $like = "%{$search}%";
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($filterStatus !== "" && strtolower($filterStatus) !== "all") {
        $allowedFilter = ["pending", "accepted", "on the way", "delivered", "completed", "rejected", "deleted"];
        $fs = strtolower($filterStatus);
        if ($fs === "on_the_way") $fs = "on the way";
        if (in_array($fs, $allowedFilter, true)) {
            $where .= " AND LOWER(COALESCE(o.status,'pending')) = ?";
            $types .= "s";
            $params[] = $fs;
        }
    }

    $countSql = "SELECT COUNT(DISTINCT o.order_id) AS total
                 FROM tblorder o
                 LEFT JOIN tblcustomer c ON o.cus_id = c.cus_id
                 WHERE {$where}";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) json_out(["status" => "error", "message" => "Failed to prepare count query"]);
    if ($types !== "") {
        $bind = [$types];
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$countStmt, "bind_param"], $bind);
    }
    $countStmt->execute();
    $total = (int)(($countStmt->get_result()->fetch_assoc()["total"] ?? 0));
    $countStmt->close();

    $sql = "SELECT o.order_id, o.ordered_date, o.status, o.status_reason, o.cus_id,
                   {$customerDisplayExpr} AS customer_name, c.email,
                   COUNT(od.orderdetails_id) AS item_count,
                   COALESCE(NULLIF(ch.grand_total, 0), COALESCE(SUM(od.quantity * od.unitprice),0)) AS order_total,
                   COALESCE(
                        (SELECT tp.name
                         FROM tblorder_details tod
                         LEFT JOIN tblproduct tp ON tod.product_id = tp.product_id
                         WHERE tod.order_id = o.order_id
                         ORDER BY tod.orderdetails_id ASC
                         LIMIT 1),
                        'N/A'
                   ) AS first_product_name,
                   COALESCE(
                        (SELECT ti.image_1
                         FROM tblorder_details tod
                         LEFT JOIN tblimage ti ON tod.product_id = ti.product_id
                         WHERE tod.order_id = o.order_id
                         ORDER BY tod.orderdetails_id ASC
                         LIMIT 1),
                        'smartphone.png'
                   ) AS first_product_image,
                   COALESCE(
                        (SELECT tod.selected_feature
                         FROM tblorder_details tod
                         WHERE tod.order_id = o.order_id
                         ORDER BY tod.orderdetails_id ASC
                         LIMIT 1),
                        ''
                   ) AS first_selected_feature,
                   MAX(COALESCE(pay.method, '')) AS payment_method,
                   MAX(COALESCE(pay.payment_status, '')) AS payment_status
            FROM tblorder o
            LEFT JOIN tblcustomer c ON o.cus_id = c.cus_id
            LEFT JOIN tblorder_details od ON o.order_id = od.order_id
            LEFT JOIN tblorder_charge ch ON o.order_id = ch.order_id
            LEFT JOIN tblpayment pay ON pay.order_id = o.order_id
            WHERE {$where}
            GROUP BY o.order_id, o.ordered_date, o.status, o.status_reason, o.cus_id, c.`{$custCol}`, c.email, ch.grand_total
            ORDER BY o.ordered_date DESC, o.order_id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(["status" => "error", "message" => "Failed to prepare list query"]);

    $listTypes = $types . "ii";
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind = [$listTypes];
    foreach ($listParams as $k => $v) $bind[] = &$listParams[$k];
    call_user_func_array([$stmt, "bind_param"], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) {
        $status = parse_status($r["status"] ?? "");
        $rows[] = [
            "order_id" => (string)$r["order_id"],
            "ordered_date" => (string)$r["ordered_date"],
            "status" => $status,
            "status_reason" => (string)($r["status_reason"] ?? ""),
            "customer_name" => (string)($r["customer_name"] ?? ""),
            "email" => (string)($r["email"] ?? ""),
            "item_count" => (int)($r["item_count"] ?? 0),
            "order_total" => (float)($r["order_total"] ?? 0),
            "first_product_name" => (string)($r["first_product_name"] ?? ""),
            "first_product_image" => (string)($r["first_product_image"] ?? "smartphone.png"),
            "first_selected_feature" => (string)($r["first_selected_feature"] ?? ""),
            "payment_method" => strtolower(trim((string)($r["payment_method"] ?? ""))),
            "payment_status" => trim((string)($r["payment_status"] ?? "")),
            "next_status" => next_status_for($status),
            "can_reject" => in_array($status, ["Pending"], true)
        ];
    }
    $stmt->close();

    $countBaseWhere = "1=1";
    $countTypes = "";
    $countParams = [];
    if ($search !== "") {
        $countBaseWhere .= " AND (o.order_id LIKE ? OR {$customerDisplayExpr} LIKE ? OR c.email LIKE ?)";
        $likeCount = "%{$search}%";
        $countTypes .= "sss";
        $countParams[] = $likeCount;
        $countParams[] = $likeCount;
        $countParams[] = $likeCount;
    }
    $counts = ["all" => 0, "pending" => 0, "accepted" => 0, "on_the_way" => 0, "delivered" => 0, "completed" => 0, "rejected" => 0, "deleted" => 0];
    $countByStatusSql = "SELECT LOWER(COALESCE(o.status, 'pending')) AS st, COUNT(*) AS total
                         FROM tblorder o
                         LEFT JOIN tblcustomer c ON o.cus_id = c.cus_id
                         WHERE {$countBaseWhere}
                         GROUP BY LOWER(COALESCE(o.status, 'pending'))";
    $countByStatusStmt = $conn->prepare($countByStatusSql);
    if ($countByStatusStmt) {
        if ($countTypes !== "") {
            $bindCounts = [$countTypes];
            foreach ($countParams as $k => $v) $bindCounts[] = &$countParams[$k];
            call_user_func_array([$countByStatusStmt, "bind_param"], $bindCounts);
        }
        $countByStatusStmt->execute();
        $cr = $countByStatusStmt->get_result();
        while ($cr && $crow = $cr->fetch_assoc()) {
            $st = strtolower((string)($crow["st"] ?? "pending"));
            if (in_array($st, ["shipped", "on-way", "on_way"], true)) $st = "on the way";
            $totalSt = (int)($crow["total"] ?? 0);
            $counts["all"] += $totalSt;
            if ($st === "accepted") $counts["accepted"] += $totalSt;
            elseif ($st === "on the way") $counts["on_the_way"] += $totalSt;
            elseif ($st === "delivered") $counts["delivered"] += $totalSt;
            elseif ($st === "completed") $counts["completed"] += $totalSt;
            elseif ($st === "rejected") $counts["rejected"] += $totalSt;
            elseif ($st === "deleted") $counts["deleted"] += $totalSt;
            else $counts["pending"] += $totalSt;
        }
        $countByStatusStmt->close();
    } else {
        $counts["all"] = $total;
    }

    json_out([
        "status" => "success",
        "rows" => $rows,
        "counts" => $counts,
        "pagination" => [
            "page" => $page,
            "per_page" => $perPage,
            "total" => $total,
            "total_pages" => max(1, (int)ceil($total / $perPage))
        ]
    ]);
}

if ($action === "detail") {
    // Full order detail query joins customer and charge totals for modal breakdown.
    $orderId = clean_text($_GET["order_id"] ?? "", 20);
    if ($orderId === "") json_out(["status" => "error", "message" => "Order ID required"]);

    $phoneExpr = customer_col_exists($conn, 'phone') ? "COALESCE(c.phone, '')" : "''";
    $addressExpr = customer_col_exists($conn, 'address') ? "COALESCE(c.address, '')" : "''";
    $provinceExpr = customer_col_exists($conn, 'province') ? "COALESCE(c.province, '')" : "''";
    $districtExpr = customer_col_exists($conn, 'district') ? "COALESCE(c.district, '')" : "''";
    $headSql = "SELECT o.order_id, o.ordered_date, o.status, o.status_reason,
                       c.cus_id, COALESCE(NULLIF(TRIM(c.`{$custCol}`),''), CONCAT('Customer #', o.cus_id)) AS customer_name, c.email,
                       {$phoneExpr} AS phone,
                       {$addressExpr} AS address_1,
                       '' AS address_2,
                       {$provinceExpr} AS province,
                       {$districtExpr} AS district,
                       COALESCE(pay.method, '') AS payment_method,
                       COALESCE(pay.payment_status, '') AS payment_status,
                       pay.paid_at AS payment_paid_at
                FROM tblorder o
                LEFT JOIN tblcustomer c ON o.cus_id = c.cus_id
                LEFT JOIN tblpayment pay ON pay.order_id = o.order_id
                WHERE o.order_id = ?
                LIMIT 1";
    $headStmt = $conn->prepare($headSql);
    if (!$headStmt) json_out(["status" => "error", "message" => "Failed to prepare detail query"]);
    $headStmt->bind_param("s", $orderId);
    $headStmt->execute();
    $head = $headStmt->get_result()->fetch_assoc();
    $headStmt->close();
    if (!$head) json_out(["status" => "error", "message" => "Order not found"]);

    $items = [];
    $itemSql = "SELECT od.orderdetails_id, od.product_id, od.quantity, od.unitprice, od.selected_feature,
                       COALESCE(tp.name, CONCAT('Product #', od.product_id)) AS product_name,
                       COALESCE(ti.image_1, 'smartphone.png') AS image
                FROM tblorder_details od
                LEFT JOIN tblproduct tp ON od.product_id = tp.product_id
                LEFT JOIN tblimage ti ON od.product_id = ti.product_id
                WHERE od.order_id = ?
                ORDER BY od.orderdetails_id ASC";
    $itemStmt = $conn->prepare($itemSql);
    if ($itemStmt) {
        $itemStmt->bind_param("s", $orderId);
        $itemStmt->execute();
        $rr = $itemStmt->get_result();
        while ($rr && $row = $rr->fetch_assoc()) {
            $qty = (int)$row["quantity"];
            $unit = (float)$row["unitprice"];
            $items[] = [
                "product_name" => (string)$row["product_name"],
                "image" => (string)$row["image"],
                "quantity" => $qty,
                "unitprice" => $unit,
                "line_total" => $qty * $unit,
                "selected_feature" => (string)($row["selected_feature"] ?? "")
            ];
        }
        $itemStmt->close();
    }

    $charge = [
        "subtotal" => 0,
        "coupon_code" => "",
        "coupon_discount" => 0,
        "special_discount_label" => "",
        "special_discount" => 0,
        "shipping_fee" => 0,
        "grand_total" => 0
    ];
    $chargeStmt = $conn->prepare("SELECT subtotal, coupon_code, coupon_discount, special_discount_label, special_discount, shipping_fee, grand_total
                                  FROM tblorder_charge
                                  WHERE order_id = ?
                                  LIMIT 1");
    if ($chargeStmt) {
        $chargeStmt->bind_param("s", $orderId);
        $chargeStmt->execute();
        $chargeRow = $chargeStmt->get_result()->fetch_assoc();
        $chargeStmt->close();
        if ($chargeRow) {
            $charge = [
                "subtotal" => (float)($chargeRow["subtotal"] ?? 0),
                "coupon_code" => (string)($chargeRow["coupon_code"] ?? ""),
                "coupon_discount" => (float)($chargeRow["coupon_discount"] ?? 0),
                "special_discount_label" => (string)($chargeRow["special_discount_label"] ?? ""),
                "special_discount" => (float)($chargeRow["special_discount"] ?? 0),
                "shipping_fee" => (float)($chargeRow["shipping_fee"] ?? 0),
                "grand_total" => (float)($chargeRow["grand_total"] ?? 0)
            ];
        }
    }

    json_out(["status" => "success", "header" => $head, "items" => $items, "charge" => $charge]);
}

if ($action === "update_status") {
    // Status transition update query (tracking flow) with audit reason + customer notification.
    $orderId = clean_text($_POST["order_id"] ?? "", 20);
    $newStatus = clean_text($_POST["new_status"] ?? "", 20);
    $reason = clean_text($_POST["reason"] ?? "", 255);
    $codCollectedRaw = trim((string)($_POST["cod_collected"] ?? ""));
    $codCollected = in_array(strtolower($codCollectedRaw), ["1", "yes", "true", "on"], true);

    if ($orderId === "") json_out(["status" => "error", "message" => "Order ID required"]);
    if (!in_array($newStatus, ["Accepted", "On the way", "Delivered", "Completed", "Rejected"], true)) {
        json_out(["status" => "error", "message" => "Invalid status"]);
    }

    $current = "";
    $sel = $conn->prepare("SELECT status FROM tblorder WHERE order_id = ? LIMIT 1");
    if ($sel) {
        $sel->bind_param("s", $orderId);
        $sel->execute();
        $current = (string)(($sel->get_result()->fetch_assoc()["status"] ?? ""));
        $sel->close();
    }
    if ($current === "") json_out(["status" => "error", "message" => "Order not found"]);

    $normalizedCurrent = parse_status($current);
    if (in_array($normalizedCurrent, ["Rejected", "Deleted", "Completed"], true)) {
        json_out(["status" => "error", "message" => "Order already finalized"]);
    }

    $allowedTransitions = [
        "Pending" => ["Accepted", "Rejected"],
        "Accepted" => ["On the way"],
        "On the way" => ["Delivered"],
        "Delivered" => ["Completed"]
    ];
    $possible = $allowedTransitions[$normalizedCurrent] ?? [];
    if (!in_array($newStatus, $possible, true)) {
        json_out(["status" => "error", "message" => "Invalid transition from {$normalizedCurrent} to {$newStatus}"]);
    }

    $payMethod = "";
    $payStatus = "";
    $paySel = $conn->prepare("SELECT TRIM(method) AS method, TRIM(payment_status) AS payment_status FROM tblpayment WHERE order_id = ? LIMIT 1");
    if ($paySel) {
        $paySel->bind_param("s", $orderId);
        $paySel->execute();
        $payRow = $paySel->get_result()->fetch_assoc();
        $paySel->close();
        if ($payRow) {
            $payMethod = strtolower((string)($payRow["method"] ?? ""));
            $payStatus = strtolower((string)($payRow["payment_status"] ?? ""));
        }
    }
    $isCodAwaitingCash = ($payMethod === "cod" && $payStatus === "pending");
    if ($newStatus === "Delivered") {
        if ($isCodAwaitingCash && !$codCollected) {
            json_out(["status" => "error", "message" => "Confirm COD collected in the popup before marking this order delivered."]);
        }
    }

    if ($newStatus === "Rejected" && $reason === "") {
        $reason = "Rejected by admin";
    }

    $up = $conn->prepare("UPDATE tblorder SET status = ?, status_reason = ? WHERE order_id = ?");
    if (!$up) json_out(["status" => "error", "message" => "Unable to update order"]);
    $up->bind_param("sss", $newStatus, $reason, $orderId);
    $ok = $up->execute();
    $up->close();
    if (!$ok) json_out(["status" => "error", "message" => "Status update failed"]);

    if ($newStatus === "Delivered" && $isCodAwaitingCash && $codCollected) {
        $paidAt = date("Y-m-d H:i:s");
        $markPaid = $conn->prepare("UPDATE tblpayment SET payment_status = 'Paid', paid_at = ? WHERE order_id = ? AND LOWER(TRIM(method)) = 'cod'");
        if ($markPaid) {
            $markPaid->bind_param("ss", $paidAt, $orderId);
            $markPaid->execute();
            $markPaid->close();
        }
    }

    if (strcasecmp($normalizedCurrent, $newStatus) !== 0) {
        rtel_notify_order_status($conn, $orderId, $newStatus, $reason);
    }
    rtel_admin_log_event($conn, 'order_status', 'success', 'Order ' . $orderId . ': ' . $normalizedCurrent . ' -> ' . $newStatus . ($reason !== '' ? (' (' . $reason . ')') : ''));

    json_out(["status" => "success", "message" => "Order {$orderId} updated to {$newStatus}."]);
}

json_out(["status" => "error", "message" => "Invalid action"]);
