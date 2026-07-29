<?php
include __DIR__ . "/../connection.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/activity_logger.php";
rtel_require_admin_auth();

$canCouponPage = rtel_admin_can_access_page('coupon.php');
$canPromotionPage = rtel_admin_can_access_page('promotion.php');
if (!$canCouponPage && !$canPromotionPage) {
    http_response_code(403);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

header("Content-Type: application/json; charset=utf-8");

function json_out($arr)
{
    echo json_encode($arr);
    exit;
}

/**
 * Homepage promotion APIs may be used from promotion.php; other coupon APIs require coupon.php.
 */
function coupon_api_require_action_access(string $action, array $get, bool $canCouponPage, bool $canPromotionPage): void
{
    if ($action === "list") {
        $kind = strtolower(clean_text($get["kind"] ?? "coupon", 20));
        if ($kind === "home_promotion") {
            if (!$canCouponPage && !$canPromotionPage) {
                json_out(["status" => "error", "message" => "Access denied."]);
            }
            return;
        }
        if (!$canCouponPage) {
            json_out(["status" => "error", "message" => "Access denied."]);
        }
        return;
    }
    if (in_array($action, ["save_home_promotion", "delete_home_promotion"], true)) {
        if (!$canCouponPage && !$canPromotionPage) {
            json_out(["status" => "error", "message" => "Access denied."]);
        }
        return;
    }
    if (!$canCouponPage) {
        json_out(["status" => "error", "message" => "Access denied."]);
    }
}

function clean_text($v, $max = 255)
{
    $v = trim((string)$v);
    return substr($v, 0, $max);
}

function parse_float($v)
{
    return is_numeric($v) ? (float)$v : 0.0;
}

function save_home_promotion_image(array $file): string
{
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return '';
    }
    $orig = (string)($file['name'] ?? 'promotion-image');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return '';
    }
    $base = 'home_promo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetDir = realpath(__DIR__ . '/../../images');
    if ($targetDir === false) {
        return '';
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $base;
    if (!@move_uploaded_file($tmp, $targetPath)) {
        return '';
    }
    return $base;
}

function table_exists(mysqli $conn, $tableName)
{
    $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tableName);
    if ($tableName === '') return false;
    try {
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$tableName}'");
        return ($res && mysqli_num_rows($res) > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function safe_query(mysqli $conn, $sql)
{
    try {
        return mysqli_query($conn, $sql);
    } catch (Throwable $e) {
        return false;
    }
}

$conn->set_charset("utf8mb4");
// Commerce offer schema setup: coupons, customer discounts, and scoped promotions.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcoupon (
    coupon_id VARCHAR(20) NOT NULL PRIMARY KEY,
    coupon_ref_id INT AUTO_INCREMENT UNIQUE,
    coupon_type VARCHAR(20) NOT NULL DEFAULT 'available',
    order_id VARCHAR(20) NOT NULL DEFAULT '',
    cus_id VARCHAR(250) NOT NULL DEFAULT '',
    code VARCHAR(20) NOT NULL,
    dispercentage INT(3) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expiry_date DATE NOT NULL,
    min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_ref_id INT AUTO_INCREMENT UNIQUE");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_type VARCHAR(20) NOT NULL DEFAULT 'available'");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS order_id VARCHAR(20) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS cus_id VARCHAR(250) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
safe_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_scope VARCHAR(20) NOT NULL DEFAULT 'all'");
safe_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_type_status_expiry ON tblcoupon(coupon_type, status, expiry_date)");
safe_query($conn, "CREATE UNIQUE INDEX IF NOT EXISTS uq_tblcoupon_available_code ON tblcoupon(code, coupon_type)");
safe_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_scope ON tblcoupon(coupon_scope)");
// One-time migration from deprecated table.
safe_query($conn, "DROP TABLE IF EXISTS tblavailable_coupon");
if (table_exists($conn, 'tblhome_coupon')) {
    safe_query($conn, "INSERT INTO tblcoupon (coupon_id, coupon_type, coupon_scope, code, dispercentage, expiry_date, min_order, status, created_at)
                      SELECT CONCAT('CH', LPAD(id, 8, '0')), 'available', 'home', code, CAST(REPLACE(REPLACE(discount, '% OFF', ''), '%', '') AS UNSIGNED), exp_date, 0.00, status, NOW()
                      FROM tblhome_coupon h
                      WHERE NOT EXISTS (
                          SELECT 1 FROM tblcoupon c WHERE c.code = h.code AND c.coupon_type = 'available'
                      )");
    safe_query($conn, "DROP TABLE IF EXISTS tblhome_coupon");
}
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbldiscount_policy (
    discount_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    customer_group VARCHAR(20) NOT NULL DEFAULT 'regular',
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_date DATE NULL,
    end_date DATE NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblpromotion (
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
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS promotion_scope VARCHAR(20) NOT NULL DEFAULT 'offer'");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS description VARCHAR(255) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS image VARCHAR(255) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS scope_type VARCHAR(20) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS scope_id VARCHAR(20) NOT NULL DEFAULT ''");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS offer_type VARCHAR(20) NOT NULL DEFAULT 'percent'");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS offer_value DECIMAL(10,2) NOT NULL DEFAULT 0.00");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS start_date DATE NULL");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS end_date DATE NULL");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
safe_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
safe_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblpromotion_scope_status ON tblpromotion(promotion_scope, status)");
if (table_exists($conn, 'tblpromotion_offer')) {
    safe_query($conn, "INSERT INTO tblpromotion (promotion_scope, title, description, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, created_at)
                      SELECT 'offer', title, description, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, created_at
                      FROM tblpromotion_offer o
                      WHERE NOT EXISTS (
                          SELECT 1 FROM tblpromotion p
                          WHERE p.promotion_scope='offer'
                            AND p.title=o.title
                            AND COALESCE(p.scope_type,'')=COALESCE(o.scope_type,'')
                            AND COALESCE(p.scope_id,'')=COALESCE(o.scope_id,'')
                      )");
    safe_query($conn, "DROP TABLE IF EXISTS tblpromotion_offer");
}
if (table_exists($conn, 'tblpromotion_home')) {
    safe_query($conn, "INSERT INTO tblpromotion (promotion_scope, title, description, image, link_url, status, created_at)
                      SELECT 'home', title, description, image, COALESCE(link,'promotions.php'), status, NOW()
                      FROM tblpromotion_home h
                      WHERE NOT EXISTS (
                          SELECT 1 FROM tblpromotion p
                          WHERE p.promotion_scope='home'
                            AND p.title=h.title
                      )");
    safe_query($conn, "DROP TABLE IF EXISTS tblpromotion_home");
}

$action = $_GET["action"] ?? $_POST["action"] ?? "";
coupon_api_require_action_access($action, $_GET, $canCouponPage, $canPromotionPage);

if ($action === "list") {
    // Unified listing query dispatcher for coupon/discount/promotion tabs.
    $kind = strtolower(clean_text($_GET["kind"] ?? "coupon", 20));
    $search = clean_text($_GET["search"] ?? "", 120);
    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(5, min(200, (int)($_GET["per_page"] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $map = [
        "coupon" => [
            "table" => "tblcoupon",
            "select" => "coupon_ref_id AS id, code, dispercentage, expiry_date, min_order, status, coupon_scope",
            "search_cols" => ["code"],
            "order_by" => "coupon_ref_id DESC",
            "extra_where" => "coupon_type = 'available'"
        ],
        "discount" => [
            "table" => "tbldiscount_policy",
            "select" => "discount_id AS id, title, customer_group, discount_type, discount_value, min_order, start_date, end_date, status, note",
            "search_cols" => ["title", "customer_group", "note"],
            "order_by" => "discount_id DESC"
        ],
        "promotion" => [
            "table" => "tblpromotion",
            "select" => "promotion_id AS id, title, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, description",
            "search_cols" => ["title", "scope_type", "scope_id", "description"],
            "order_by" => "promotion_id DESC",
            "extra_where" => "promotion_scope = 'offer'"
        ],
        "home_promotion" => [
            "table" => "tblpromotion",
            "select" => "promotion_id AS id, title, description, image, link_url, status",
            "search_cols" => ["title", "description", "image", "link_url"],
            "order_by" => "promotion_id DESC",
            "extra_where" => "promotion_scope = 'home'"
        ]
    ];
    if (!isset($map[$kind])) json_out(["status" => "error", "message" => "Invalid list type"]);
    $cfg = $map[$kind];

    $where = "1=1";
    if (!empty($cfg["extra_where"])) {
        $where .= " AND " . $cfg["extra_where"];
    }
    $types = "";
    $params = [];
    if ($search !== "") {
        $parts = [];
        foreach ($cfg["search_cols"] as $col) {
            $parts[] = "{$col} LIKE ?";
            $types .= "s";
            $params[] = "%{$search}%";
        }
        $where .= " AND (" . implode(" OR ", $parts) . ")";
    }

    $countSql = "SELECT COUNT(*) AS total FROM {$cfg["table"]} WHERE {$where}";
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

    $sql = "SELECT {$cfg["select"]} FROM {$cfg["table"]} WHERE {$where} ORDER BY {$cfg["order_by"]} LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(["status" => "error", "message" => "Failed to prepare list query"]);
    $listTypes = $types . "ii";
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind2 = [$listTypes];
    foreach ($listParams as $k => $v) $bind2[] = &$listParams[$k];
    call_user_func_array([$stmt, "bind_param"], $bind2);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    json_out([
        "status" => "success",
        "rows" => $rows,
        "pagination" => [
            "page" => $page,
            "per_page" => $perPage,
            "total" => $total,
            "total_pages" => max(1, (int)ceil($total / $perPage))
        ]
    ]);
}

if ($action === "save_coupon") {
    // Upsert coupon query with validation around code, expiry, and min order.
    $id = (int)($_POST["id"] ?? 0);
    $code = strtoupper(clean_text($_POST["code"] ?? "", 20));
    $percent = (int)($_POST["dispercentage"] ?? 0);
    $expiry = clean_text($_POST["expiry_date"] ?? "", 20);
    $minOrder = parse_float($_POST["min_order"] ?? 0);
    $status = ((int)($_POST["status"] ?? 1)) ? 1 : 0;
    $scope = strtolower(clean_text($_POST["coupon_scope"] ?? "all", 20));
    if (!in_array($scope, ["all", "home", "checkout"], true)) $scope = "all";
    if ($code === "" || $percent <= 0 || $percent > 100 || $expiry === "") {
        json_out(["status" => "error", "message" => "Invalid coupon details."]);
    }
    if ($minOrder < 0) $minOrder = 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tblcoupon SET code = ?, dispercentage = ?, expiry_date = ?, min_order = ?, status = ?, coupon_scope = ? WHERE coupon_ref_id = ? AND coupon_type = 'available'");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to update coupon."]);
        $stmt->bind_param("sisdisi", $code, $percent, $expiry, $minOrder, $status, $scope, $id);
    } else {
        $couponId = "CA" . strtoupper(substr(uniqid(), -8));
        $type = "available";
        $createdAt = date("Y-m-d H:i:s");
        $stmt = $conn->prepare("INSERT INTO tblcoupon (coupon_id, coupon_type, coupon_scope, code, dispercentage, expiry_date, min_order, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to create coupon."]);
        $stmt->bind_param("ssssisdis", $couponId, $type, $scope, $code, $percent, $expiry, $minOrder, $status, $createdAt);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Coupon save failed."]);
    rtel_admin_log_event($conn, 'discount_save', 'success', ($id > 0 ? 'Updated' : 'Created') . " coupon {$code}");
    json_out(["status" => "success", "message" => "Coupon saved successfully."]);
}

if ($action === "delete_coupon") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) json_out(["status" => "error", "message" => "Invalid coupon"]);
    $stmt = $conn->prepare("DELETE FROM tblcoupon WHERE coupon_ref_id = ? AND coupon_type = 'available'");
    if (!$stmt) json_out(["status" => "error", "message" => "Unable to delete coupon."]);
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Coupon delete failed."]);
    rtel_admin_log_event($conn, 'discount_delete', 'success', "Deleted coupon ref #{$id}");
    json_out(["status" => "success", "message" => "Coupon deleted."]);
}

if ($action === "save_discount") {
    // Upsert discount policy query for customer-group based pricing rules.
    $id = (int)($_POST["id"] ?? 0);
    $title = clean_text($_POST["title"] ?? "", 120);
    $group = strtolower(clean_text($_POST["customer_group"] ?? "regular", 20));
    $dtype = strtolower(clean_text($_POST["discount_type"] ?? "percent", 20));
    $value = parse_float($_POST["discount_value"] ?? 0);
    $minOrder = parse_float($_POST["min_order"] ?? 0);
    $startDate = clean_text($_POST["start_date"] ?? "", 20);
    $endDate = clean_text($_POST["end_date"] ?? "", 20);
    $status = ((int)($_POST["status"] ?? 1)) ? 1 : 0;
    $note = clean_text($_POST["note"] ?? "", 255);
    if ($title === "" || !in_array($group, ["new", "regular", "all"], true) || !in_array($dtype, ["percent", "fixed"], true) || $value <= 0) {
        json_out(["status" => "error", "message" => "Invalid discount details."]);
    }
    if ($minOrder < 0) $minOrder = 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tbldiscount_policy
                                SET title = ?, customer_group = ?, discount_type = ?, discount_value = ?, min_order = ?, start_date = NULLIF(?, ''), end_date = NULLIF(?, ''), status = ?, note = ?
                                WHERE discount_id = ?");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to update discount."]);
        $stmt->bind_param("sssddssisi", $title, $group, $dtype, $value, $minOrder, $startDate, $endDate, $status, $note, $id);
    } else {
        $now = date("Y-m-d H:i:s");
        $stmt = $conn->prepare("INSERT INTO tbldiscount_policy
                                (title, customer_group, discount_type, discount_value, min_order, start_date, end_date, status, note, created_at)
                                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to create discount."]);
        $stmt->bind_param("sssddssiss", $title, $group, $dtype, $value, $minOrder, $startDate, $endDate, $status, $note, $now);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Discount save failed."]);
    rtel_admin_log_event($conn, 'discount_save', 'success', ($id > 0 ? 'Updated' : 'Created') . " special discount: {$title}");
    json_out(["status" => "success", "message" => "Discount saved successfully."]);
}

if ($action === "delete_discount") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) json_out(["status" => "error", "message" => "Invalid discount"]);
    $stmt = $conn->prepare("DELETE FROM tbldiscount_policy WHERE discount_id = ?");
    if (!$stmt) json_out(["status" => "error", "message" => "Unable to delete discount."]);
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Discount delete failed."]);
    rtel_admin_log_event($conn, 'discount_delete', 'success', "Deleted special discount #{$id}");
    json_out(["status" => "success", "message" => "Discount deleted."]);
}

if ($action === "save_promotion") {
    // Upsert promotion query for product/category/brand scoped offers.
    $id = (int)($_POST["id"] ?? 0);
    $title = clean_text($_POST["title"] ?? "", 150);
    $scopeType = strtolower(clean_text($_POST["scope_type"] ?? "", 20));
    $scopeId = clean_text($_POST["scope_id"] ?? "", 20);
    $offerType = strtolower(clean_text($_POST["offer_type"] ?? "percent", 20));
    $offerValue = parse_float($_POST["offer_value"] ?? 0);
    $startDate = clean_text($_POST["start_date"] ?? "", 20);
    $endDate = clean_text($_POST["end_date"] ?? "", 20);
    $status = ((int)($_POST["status"] ?? 1)) ? 1 : 0;
    $description = clean_text($_POST["description"] ?? "", 255);
    if ($title === "" || !in_array($scopeType, ["product", "category", "brand"], true) || $scopeId === "" || !in_array($offerType, ["percent", "fixed"], true) || $offerValue <= 0) {
        json_out(["status" => "error", "message" => "Invalid promotion details."]);
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tblpromotion
                                SET title = ?, scope_type = ?, scope_id = ?, offer_type = ?, offer_value = ?, start_date = NULLIF(?, ''), end_date = NULLIF(?, ''), status = ?, description = ?
                                WHERE promotion_id = ? AND promotion_scope = 'offer'");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to update promotion."]);
        $stmt->bind_param("ssssdssisi", $title, $scopeType, $scopeId, $offerType, $offerValue, $startDate, $endDate, $status, $description, $id);
    } else {
        $now = date("Y-m-d H:i:s");
        $scope = "offer";
        $stmt = $conn->prepare("INSERT INTO tblpromotion
                                (promotion_scope, title, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, description, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to create promotion."]);
        $stmt->bind_param("sssssdssiss", $scope, $title, $scopeType, $scopeId, $offerType, $offerValue, $startDate, $endDate, $status, $description, $now);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Promotion save failed."]);
    rtel_admin_log_event($conn, 'promotion_save', 'success', ($id > 0 ? 'Updated' : 'Created') . " promotion: {$title} ({$scopeType}:{$scopeId})");
    json_out(["status" => "success", "message" => "Promotion saved successfully."]);
}

if ($action === "delete_promotion") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) json_out(["status" => "error", "message" => "Invalid promotion"]);
    $stmt = $conn->prepare("DELETE FROM tblpromotion WHERE promotion_id = ? AND promotion_scope = 'offer'");
    if (!$stmt) json_out(["status" => "error", "message" => "Unable to delete promotion."]);
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Promotion delete failed."]);
    rtel_admin_log_event($conn, 'promotion_delete', 'success', "Deleted promotion #{$id}");
    json_out(["status" => "success", "message" => "Promotion deleted."]);
}

if ($action === "save_home_promotion") {
    // Upsert homepage promotion banner data into unified tblpromotion table.
    $id = (int)($_POST["id"] ?? 0);
    $title = clean_text($_POST["title"] ?? "", 150);
    $description = clean_text($_POST["description"] ?? "", 255);
    $image = clean_text($_POST["image"] ?? "", 255);
    $existingImage = clean_text($_POST["existing_image"] ?? "", 255);
    $linkUrl = clean_text($_POST["link_url"] ?? "promotions.php", 255);
    $status = ((int)($_POST["status"] ?? 1)) ? 1 : 0;
    if ($title === "") {
        json_out(["status" => "error", "message" => "Title is required."]);
    }
    $uploadedImage = '';
    if (isset($_FILES['image_file']) && is_array($_FILES['image_file'])) {
        $uploadedImage = save_home_promotion_image($_FILES['image_file']);
        if ($uploadedImage === '' && (int)($_FILES['image_file']['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
            json_out(["status" => "error", "message" => "Invalid image upload. Use jpg, jpeg, png, webp, or gif."]);
        }
    }
    if ($uploadedImage !== '') {
        $image = $uploadedImage;
    } elseif ($image === '' && $existingImage !== '') {
        $image = $existingImage;
    }
    if ($image === "") {
        json_out(["status" => "error", "message" => "Image file is required for home promotions."]);
    }
    if ($linkUrl === "") $linkUrl = "promotions.php";

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tblpromotion
                                SET title = ?, description = ?, image = ?, link_url = ?, status = ?
                                WHERE promotion_id = ? AND promotion_scope = 'home'");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to update home promotion."]);
        $stmt->bind_param("ssssii", $title, $description, $image, $linkUrl, $status, $id);
    } else {
        $now = date("Y-m-d H:i:s");
        $scope = "home";
        $stmt = $conn->prepare("INSERT INTO tblpromotion
                                (promotion_scope, title, description, image, link_url, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) json_out(["status" => "error", "message" => "Unable to create home promotion."]);
        $stmt->bind_param("sssssis", $scope, $title, $description, $image, $linkUrl, $status, $now);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Home promotion save failed."]);
    rtel_admin_log_event($conn, 'promotion_save', 'success', ($id > 0 ? 'Updated' : 'Created') . " home promotion: {$title}");
    json_out(["status" => "success", "message" => "Home promotion saved successfully."]);
}

if ($action === "delete_home_promotion") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) json_out(["status" => "error", "message" => "Invalid home promotion"]);
    $stmt = $conn->prepare("DELETE FROM tblpromotion WHERE promotion_id = ? AND promotion_scope = 'home'");
    if (!$stmt) json_out(["status" => "error", "message" => "Unable to delete home promotion."]);
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status" => "error", "message" => "Home promotion delete failed."]);
    rtel_admin_log_event($conn, 'promotion_delete', 'success', "Deleted home promotion #{$id}");
    json_out(["status" => "success", "message" => "Home promotion deleted."]);
}

if ($action === "options") {
    $brands = [];
    $categories = [];
    $products = [];
    $rb = $conn->query("SELECT brand_id, name FROM tblbrand ORDER BY name ASC");
    if ($rb) while ($row = $rb->fetch_assoc()) $brands[] = ["id" => (string)$row["brand_id"], "name" => (string)$row["name"]];
    $rc = $conn->query("SELECT cat_id, name FROM tblcategory ORDER BY name ASC");
    if ($rc) while ($row = $rc->fetch_assoc()) $categories[] = ["id" => (string)$row["cat_id"], "name" => (string)$row["name"]];
    $rp = $conn->query("SELECT product_id, name FROM tblproduct ORDER BY name ASC");
    if ($rp) while ($row = $rp->fetch_assoc()) $products[] = ["id" => (string)$row["product_id"], "name" => (string)$row["name"]];
    json_out(["status" => "success", "brands" => $brands, "categories" => $categories, "products" => $products]);
}

json_out(["status" => "error", "message" => "Invalid action"]);
