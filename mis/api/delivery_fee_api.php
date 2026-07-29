<?php
include __DIR__ . "/../connection.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/activity_logger.php";
rtel_require_admin_auth();
rtel_require_admin_page_access('delivery_fee.php');

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

$conn->set_charset("utf8mb4");
// Delivery-fee schema and global free-delivery rule defaults.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblshipping_rate (
    province VARCHAR(250) NOT NULL,
    district VARCHAR(250) NOT NULL,
    rate FLOAT NOT NULL,
    PRIMARY KEY (province, district)
)");
mysqli_query($conn, "ALTER TABLE tblshipping_rate ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblfree_delivery_setting (
    setting_id TINYINT NOT NULL PRIMARY KEY,
    free_for_new TINYINT(1) NOT NULL DEFAULT 0,
    free_for_regular TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
)");
mysqli_query($conn, "INSERT INTO tblfree_delivery_setting (setting_id, free_for_new, free_for_regular, updated_at)
                      SELECT 1, 0, 0, NOW()
                      WHERE NOT EXISTS (SELECT 1 FROM tblfree_delivery_setting WHERE setting_id = 1)");

$action = $_GET["action"] ?? $_POST["action"] ?? "";

if ($action === "list") {
    // Delivery fee list query with province/district search and pagination.
    $search = clean_text($_GET["search"] ?? "", 120);
    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(5, min(200, (int)($_GET["per_page"] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $where = "1=1";
    $types = "";
    $params = [];
    if ($search !== "") {
        $where .= " AND (province LIKE ? OR district LIKE ?)";
        $like = "%{$search}%";
        $types .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    $countSql = "SELECT COUNT(*) AS total FROM tblshipping_rate WHERE status = 1 AND {$where}";
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

    $sql = "SELECT province, district, rate
            FROM tblshipping_rate
            WHERE status = 1 AND {$where}
            ORDER BY province ASC, district ASC
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
        $rows[] = [
            "province" => (string)($r["province"] ?? ""),
            "district" => (string)($r["district"] ?? ""),
            "rate" => (float)($r["rate"] ?? 0)
        ];
    }
    $stmt->close();

    $setting = ["free_for_new" => 0, "free_for_regular" => 0];
    $settingRes = $conn->query("SELECT free_for_new, free_for_regular FROM tblfree_delivery_setting WHERE setting_id = 1 LIMIT 1");
    if ($settingRes) {
        $s = $settingRes->fetch_assoc();
        if ($s) {
            $setting["free_for_new"] = (int)$s["free_for_new"];
            $setting["free_for_regular"] = (int)$s["free_for_regular"];
        }
        $settingRes->free();
    }

    json_out([
        "status" => "success",
        "rows" => $rows,
        "settings" => $setting,
        "pagination" => [
            "page" => $page,
            "per_page" => $perPage,
            "total" => $total,
            "total_pages" => max(1, (int)ceil($total / $perPage))
        ]
    ]);
}

if ($action === "save_rate") {
    // Insert/update delivery fee query keyed by (province, district).
    $province = clean_text($_POST["province"] ?? "", 250);
    $district = clean_text($_POST["district"] ?? "", 250);
    $rateRaw = clean_text($_POST["rate"] ?? "", 30);
    if ($province === "" || $district === "") {
        json_out(["status" => "error", "message" => "Province and district are required."]);
    }
    if (!is_numeric($rateRaw)) {
        json_out(["status" => "error", "message" => "Delivery fee must be numeric."]);
    }
    $rate = (float)$rateRaw;
    if ($rate < 0) {
        json_out(["status" => "error", "message" => "Delivery fee cannot be negative."]);
    }

    $up = $conn->prepare("INSERT INTO tblshipping_rate (province, district, rate, status)
                          VALUES (?, ?, ?, 1)
                          ON DUPLICATE KEY UPDATE rate = VALUES(rate), status = 1");
    if (!$up) json_out(["status" => "error", "message" => "Unable to save delivery fee."]);
    $up->bind_param("ssd", $province, $district, $rate);
    $ok = $up->execute();
    $up->close();
    if (!$ok) json_out(["status" => "error", "message" => "Save failed."]);
    rtel_admin_log_event($conn, 'shipping_rate_save', 'success', "Saved shipping rate {$province}/{$district}: {$rate}");

    json_out(["status" => "success", "message" => "Delivery fee saved successfully."]);
}

if ($action === "save_free_rules") {
    // Global free-delivery policy update query (new vs regular customers).
    $freeForNew = ((int)($_POST["free_for_new"] ?? 0)) ? 1 : 0;
    $freeForRegular = ((int)($_POST["free_for_regular"] ?? 0)) ? 1 : 0;
    $up = $conn->prepare("UPDATE tblfree_delivery_setting
                          SET free_for_new = ?, free_for_regular = ?, updated_at = NOW()
                          WHERE setting_id = 1");
    if (!$up) json_out(["status" => "error", "message" => "Unable to save free-delivery settings."]);
    $up->bind_param("ii", $freeForNew, $freeForRegular);
    $ok = $up->execute();
    $up->close();
    if (!$ok) json_out(["status" => "error", "message" => "Settings save failed."]);
    rtel_admin_log_event($conn, 'shipping_rule_save', 'success', "Free delivery rules updated (new={$freeForNew}, regular={$freeForRegular})");

    json_out(["status" => "success", "message" => "Free-delivery settings updated."]);
}

if ($action === "delete_rate") {
    // Soft-delete query to keep historical FK safety while hiding inactive rates.
    $province = clean_text($_POST["province"] ?? "", 250);
    $district = clean_text($_POST["district"] ?? "", 250);
    if ($province === "" || $district === "") {
        json_out(["status" => "error", "message" => "Province and district are required."]);
    }
    $del = $conn->prepare("UPDATE tblshipping_rate SET status = 0 WHERE province = ? AND district = ?");
    if (!$del) json_out(["status" => "error", "message" => "Unable to remove delivery fee."]);
    $del->bind_param("ss", $province, $district);
    $ok = $del->execute();
    $del->close();
    if (!$ok) json_out(["status" => "error", "message" => "Remove failed."]);
    rtel_admin_log_event($conn, 'shipping_rate_delete', 'success', "Removed shipping rate {$province}/{$district}");

    json_out(["status" => "success", "message" => "Delivery fee removed from active list."]);
}

json_out(["status" => "error", "message" => "Invalid action"]);
