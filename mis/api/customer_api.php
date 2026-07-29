<?php
include __DIR__ . "/../connection.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/activity_logger.php";
require_once __DIR__ . "/../../web/mail/mail_notifications.php";
require_once __DIR__ . "/../../web/includes/rtel_db_helpers.php";
rtel_require_admin_auth();
rtel_require_admin_page_access('customer.php');

header("Content-Type: application/json; charset=utf-8");

function json_out($arr)
{
    echo json_encode($arr);
    exit;
}

function clean_text($v, $max = 255)
{
    $v = trim((string)$v);
    return substr($v, 0, $max);
}

function is_blocked_customer($statusRaw)
{
    $s = strtolower(trim((string)$statusRaw));
    return in_array($s, ["1", "blocked", "block"], true);
}

function customer_type_label($statusRaw, $orderCount)
{
    if (is_blocked_customer($statusRaw)) return "Blocked";
    return ((int)$orderCount >= 3) ? "Regular" : "New";
}

function is_promotion_eligible($statusRaw, $orderCount, $recentOrderCount)
{
    if (is_blocked_customer($statusRaw)) return false;
    return ((int)$orderCount >= 3 && (int)$recentOrderCount >= 1);
}

function table_columns(mysqli $conn, $table)
{
    $cols = [];
    $safeTable = str_replace("`", "``", (string)$table);
    $sql = "SHOW COLUMNS FROM `{$safeTable}`";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = strtolower((string)($row["Field"] ?? ""));
        }
        $res->free();
    }
    return $cols;
}

function first_existing_column(array $columns, array $candidates, $fallback = "")
{
    $set = array_flip($columns);
    foreach ($candidates as $c) {
        $k = strtolower((string)$c);
        if (isset($set[$k])) return $c;
    }
    return $fallback;
}

$conn->set_charset("utf8mb4");
// Customer schema safety for block state and reason notes.
mysqli_query($conn, "ALTER TABLE tblcustomer ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE tblcustomer ADD COLUMN IF NOT EXISTS status_reason VARCHAR(255) NULL AFTER status");
$custCol = rtel_customer_display_name_column($conn);
$customerCols = table_columns($conn, "tblcustomer");
$phoneCols = table_columns($conn, "tblphone");
$addrCols = table_columns($conn, "tbladdress");
$phonePrimaryCol = first_existing_column($phoneCols, ["phone_1", "phone"], "phone");
$phoneSecondaryCol = first_existing_column($phoneCols, ["phone_2"], "");
$customerPhoneCol = first_existing_column($customerCols, ["phone_1", "phone"], "");
$address1Col = first_existing_column($addrCols, ["address_1", "address"], "address_1");
$address2Col = first_existing_column($addrCols, ["address_2"], "");
$districtCol = first_existing_column($addrCols, ["district"], "");
$provinceCol = first_existing_column($addrCols, ["province"], "");
$customerDistrictCol = first_existing_column($customerCols, ["district"], "");
$customerProvinceCol = first_existing_column($customerCols, ["province"], "");
$phoneFromCustomerExpr = $customerPhoneCol !== "" ? "c.`{$customerPhoneCol}`" : "''";
$districtFromCustomerExpr = $customerDistrictCol !== "" ? "c.`{$customerDistrictCol}`" : "''";
$provinceFromCustomerExpr = $customerProvinceCol !== "" ? "c.`{$customerProvinceCol}`" : "''";
$phonePrimaryExpr = "COALESCE(p.`{$phonePrimaryCol}`, {$phoneFromCustomerExpr}, '')";
$phoneSecondaryExpr = $phoneSecondaryCol !== "" ? "COALESCE(p.`{$phoneSecondaryCol}`,'')" : "''";
$address1Expr = $address1Col !== "" ? "COALESCE(a.`{$address1Col}`,'')" : "''";
$address2Expr = $address2Col !== "" ? "COALESCE(a.`{$address2Col}`,'')" : "''";
$districtExpr = $districtCol !== "" ? "COALESCE(a.`{$districtCol}`, {$districtFromCustomerExpr}, '')" : "COALESCE({$districtFromCustomerExpr}, '')";
$provinceExpr = $provinceCol !== "" ? "COALESCE(a.`{$provinceCol}`, {$provinceFromCustomerExpr}, '')" : "COALESCE({$provinceFromCustomerExpr}, '')";

$action = $_GET["action"] ?? $_POST["action"] ?? "";

if ($action === "list") {
    // Paginated customer list query with dynamic schema-compatible phone/address fields.
    $search = clean_text($_GET["search"] ?? "", 120);
    $filterType = strtolower(clean_text($_GET["type"] ?? "all", 20));
    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(5, min(200, (int)($_GET["per_page"] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $where = "1=1";
    $types = "";
    $params = [];
    if ($search !== "") {
        $where .= " AND (c.cus_id LIKE ? OR c.`{$custCol}` LIKE ? OR c.email LIKE ? OR {$phonePrimaryExpr} LIKE ?)";
        $like = "%{$search}%";
        $types .= "ssss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($filterType === "blocked") {
        $where .= " AND (LOWER(COALESCE(c.status,'')) = '1' OR LOWER(COALESCE(c.status,'')) = 'blocked')";
    } elseif ($filterType === "regular") {
        $where .= " AND NOT (LOWER(COALESCE(c.status,'')) = '1' OR LOWER(COALESCE(c.status,'')) = 'blocked')
                   AND (SELECT COUNT(*) FROM tblorder o2 WHERE o2.cus_id = c.cus_id) >= 3";
    } elseif ($filterType === "promotion") {
        $where .= " AND NOT (LOWER(COALESCE(c.status,'')) = '1' OR LOWER(COALESCE(c.status,'')) = 'blocked')
                   AND (SELECT COUNT(*) FROM tblorder o2 WHERE o2.cus_id = c.cus_id) >= 3
                   AND (SELECT COUNT(*) FROM tblorder o3 WHERE o3.cus_id = c.cus_id AND DATE(o3.ordered_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) >= 1";
    } elseif ($filterType === "new") {
        $where .= " AND NOT (LOWER(COALESCE(c.status,'')) = '1' OR LOWER(COALESCE(c.status,'')) = 'blocked')
                   AND (SELECT COUNT(*) FROM tblorder o2 WHERE o2.cus_id = c.cus_id) < 3";
    }

    $countSql = "SELECT COUNT(DISTINCT c.cus_id) AS total
                 FROM tblcustomer c
                 LEFT JOIN tblphone p ON c.cus_id = p.cus_id
                 WHERE {$where}";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) json_out(["status" => "error", "message" => "Failed to prepare count query"]);
    if ($types !== "") {
        $bindCount = [$types];
        foreach ($params as $k => $v) $bindCount[] = &$params[$k];
        call_user_func_array([$countStmt, "bind_param"], $bindCount);
    }
    $countStmt->execute();
    $total = (int)(($countStmt->get_result()->fetch_assoc()["total"] ?? 0));
    $countStmt->close();

    $sql = "SELECT c.cus_id, c.`{$custCol}` AS customer_name, c.email, c.status, c.status_reason,
                   {$phonePrimaryExpr} AS phone_1,
                   (SELECT COUNT(*) FROM tblorder o WHERE o.cus_id = c.cus_id) AS order_count,
                   (SELECT COUNT(*) FROM tblorder o4 WHERE o4.cus_id = c.cus_id AND DATE(o4.ordered_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS recent_order_count
            FROM tblcustomer c
            LEFT JOIN tblphone p ON c.cus_id = p.cus_id
            WHERE {$where}
            GROUP BY c.cus_id, c.`{$custCol}`, c.email, c.status, c.status_reason, {$phonePrimaryExpr}
            ORDER BY c.cus_id DESC
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
        $orderCount = (int)($r["order_count"] ?? 0);
        $recentOrderCount = (int)($r["recent_order_count"] ?? 0);
        $typeLabel = customer_type_label($r["status"] ?? "", $orderCount);
        $rows[] = [
            "cus_id" => (string)($r["cus_id"] ?? ""),
            "name" => (string)($r["customer_name"] ?? ""),
            "email" => (string)($r["email"] ?? ""),
            "phone_1" => (string)($r["phone_1"] ?? ""),
            "order_count" => $orderCount,
            "recent_order_count" => $recentOrderCount,
            "customer_type" => $typeLabel,
            "promotion_eligible" => is_promotion_eligible($r["status"] ?? "", $orderCount, $recentOrderCount),
            "is_blocked" => ($typeLabel === "Blocked")
        ];
    }
    $stmt->close();

    $countBaseWhere = "1=1";
    $countTypes2 = "";
    $countParams2 = [];
    if ($search !== "") {
        $countBaseWhere .= " AND (c.cus_id LIKE ? OR c.`{$custCol}` LIKE ? OR c.email LIKE ? OR {$phonePrimaryExpr} LIKE ?)";
        $like2 = "%{$search}%";
        $countTypes2 .= "ssss";
        $countParams2[] = $like2;
        $countParams2[] = $like2;
        $countParams2[] = $like2;
        $countParams2[] = $like2;
    }
    $countByTypeSql = "SELECT c.status,
                              (SELECT COUNT(*) FROM tblorder o WHERE o.cus_id = c.cus_id) AS order_count,
                              (SELECT COUNT(*) FROM tblorder o4 WHERE o4.cus_id = c.cus_id AND DATE(o4.ordered_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS recent_order_count
                       FROM tblcustomer c
                       LEFT JOIN tblphone p ON c.cus_id = p.cus_id
                       WHERE {$countBaseWhere}";
    $countByTypeStmt = $conn->prepare($countByTypeSql);
    $counts = ["all" => 0, "new" => 0, "regular" => 0, "promotion" => 0, "blocked" => 0];
    if ($countByTypeStmt) {
        if ($countTypes2 !== "") {
            $bind2 = [$countTypes2];
            foreach ($countParams2 as $k => $v) $bind2[] = &$countParams2[$k];
            call_user_func_array([$countByTypeStmt, "bind_param"], $bind2);
        }
        $countByTypeStmt->execute();
        $rr = $countByTypeStmt->get_result();
        while ($rr && $row = $rr->fetch_assoc()) {
            $counts["all"]++;
            $orderCount = (int)($row["order_count"] ?? 0);
            $recentOrderCount = (int)($row["recent_order_count"] ?? 0);
            $label = customer_type_label($row["status"] ?? "", $orderCount);
            if ($label === "Blocked") $counts["blocked"]++;
            elseif ($label === "Regular") $counts["regular"]++;
            else $counts["new"]++;
            if (is_promotion_eligible($row["status"] ?? "", $orderCount, $recentOrderCount)) $counts["promotion"]++;
        }
        $countByTypeStmt->close();
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
    // Customer detail query used by the info modal (orders, spend, location, profile).
    $cusId = clean_text($_GET["cus_id"] ?? "", 20);
    if ($cusId === "") json_out(["status" => "error", "message" => "Customer ID required"]);

    $sql = "SELECT c.cus_id, c.`{$custCol}` AS customer_name, c.email, c.dob, c.gender, c.status, c.status_reason,
                   {$address1Expr} AS address_1,
                   {$address2Expr} AS address_2,
                   {$districtExpr} AS district,
                   {$provinceExpr} AS province,
                   {$phonePrimaryExpr} AS phone_1,
                   {$phoneSecondaryExpr} AS phone_2,
                   (SELECT COUNT(*) FROM tblorder o WHERE o.cus_id = c.cus_id) AS order_count,
                   (SELECT COUNT(*) FROM tblorder o4 WHERE o4.cus_id = c.cus_id AND DATE(o4.ordered_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS recent_order_count,
                   (SELECT COALESCE(SUM(od.quantity * od.unitprice),0)
                    FROM tblorder o5
                    LEFT JOIN tblorder_details od ON o5.order_id = od.order_id
                    WHERE o5.cus_id = c.cus_id) AS total_spent
            FROM tblcustomer c
            LEFT JOIN tbladdress a ON c.cus_id = a.cus_id
            LEFT JOIN tblphone p ON c.cus_id = p.cus_id
            WHERE c.cus_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(["status" => "error", "message" => "Unable to load details"]);
    $stmt->bind_param("s", $cusId);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$d) json_out(["status" => "error", "message" => "Customer not found"]);

    $fullAddress = trim(implode(", ", array_filter([
        (string)($d["address_1"] ?? ""),
        (string)($d["address_2"] ?? ""),
        (string)($d["district"] ?? ""),
        (string)($d["province"] ?? "")
    ])));
    $d["name"] = (string)($d["customer_name"] ?? "");
    $d["is_blocked"] = is_blocked_customer($d["status"] ?? "");
    $d["customer_type"] = customer_type_label($d["status"] ?? "", (int)($d["order_count"] ?? 0));
    $d["total_spent_formatted"] = "Rs. " . number_format((float)($d["total_spent"] ?? 0), 2);
    $d["full_address"] = $fullAddress;

    json_out(["status" => "success", "detail" => $d]);
}

if ($action === "toggle_block") {
    // Block/unblock update query + optional reason, then trigger notification email.
    $cusId = clean_text($_POST["cus_id"] ?? "", 20);
    $blocked = clean_text($_POST["blocked"] ?? "0", 5) === "1";
    $note = clean_text($_POST["note"] ?? "", 255);
    if ($cusId === "") json_out(["status" => "error", "message" => "Customer ID required"]);

    $old = null;
    $sel = $conn->prepare("SELECT `{$custCol}` AS customer_name, email, status FROM tblcustomer WHERE cus_id = ? LIMIT 1");
    if (!$sel) json_out(["status" => "error", "message" => "Unable to check customer"]);
    $sel->bind_param("s", $cusId);
    $sel->execute();
    $old = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$old) json_out(["status" => "error", "message" => "Customer not found"]);

    $newStatus = $blocked ? "1" : "";
    $up = $conn->prepare("UPDATE tblcustomer SET status = ?, status_reason = ? WHERE cus_id = ?");
    if (!$up) json_out(["status" => "error", "message" => "Unable to update customer"]);
    $up->bind_param("sss", $newStatus, $note, $cusId);
    $ok = $up->execute();
    $up->close();
    if (!$ok) json_out(["status" => "error", "message" => "Update failed"]);

    rtel_notify_customer_access_status(
        (string)($old["email"] ?? ""),
        (string)($old["customer_name"] ?? ""),
        $blocked,
        $note
    );
    rtel_admin_log_event($conn, 'customer_block', 'success', ($blocked ? 'Blocked ' : 'Unblocked ') . 'customer ' . $cusId . ($note !== '' ? (' (' . $note . ')') : ''));

    json_out(["status" => "success", "message" => $blocked ? "Customer blocked successfully." : "Customer unblocked successfully."]);
}

json_out(["status" => "error", "message" => "Invalid action"]);
