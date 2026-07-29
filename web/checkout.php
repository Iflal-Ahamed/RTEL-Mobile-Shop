<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "connection.php";
require_once __DIR__ . "/includes/rtel_product_variants.php";
// Mail module imports are grouped under /web/mail
require_once "mail/mail_notifications.php";

function make_id($prefix)
{
    return substr($prefix . strtoupper(uniqid()), 0, 10);
}

function format_rs($amount)
{
    return "Rs. " . number_format((float)$amount, 2);
}

$userId = (string)$_SESSION['user_id'];
$selectedCartIds = $_POST['cart_ids'] ?? $_GET['cart_ids'] ?? [];
if (!is_array($selectedCartIds)) {
    $selectedCartIds = [];
}

$selectedCartIds = array_values(array_unique(array_filter(array_map(function ($id) {
    return preg_replace('/[^A-Za-z0-9]/', '', (string)$id);
}, $selectedCartIds))));

$couponMessage = "";
$orderMessage = $_SESSION['checkout_order_message'] ?? "";
$placedOrderId = $_SESSION['checkout_order_id'] ?? "";
$orderError = "";
$appliedCoupon = null;
$discountAmount = 0.0;
$specialDiscountAmount = 0.0;
$specialDiscountLabel = "";
$shippingAmount = 0.0;
$shippingProvince = "";
$shippingDistrict = "";
$shippingAddressText = "";
$shippingPhoneText = "";
$selectedAddressId = trim((string)($_POST['shipping_address_id'] ?? $_GET['shipping_address_id'] ?? ""));
$selectedPhoneId = trim((string)($_POST['shipping_phone_id'] ?? $_GET['shipping_phone_id'] ?? ""));
$addressOptions = [];
$phoneOptions = [];
$paymentMethod = "cod";
$gatewayToken = "";
$paymentMessage = "";
$subtotal = 0.0;
$grandTotal = 0.0;
$items = [];
unset($_SESSION['checkout_order_message'], $_SESSION['checkout_order_id']);

// Create payment table if it does not exist.
// This stores one payment record per order.
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
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladdress (
    address_id VARCHAR(10) NOT NULL PRIMARY KEY,
    cus_id INT(11) NOT NULL,
    address VARCHAR(250) NOT NULL,
    province VARCHAR(250) NOT NULL,
    district VARCHAR(250) NOT NULL
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblphone (
    phone_id VARCHAR(10) NOT NULL PRIMARY KEY,
    cus_id INT(11) NOT NULL,
    phone VARCHAR(20) NOT NULL
)");
mysqli_query($conn, "ALTER TABLE tblorder_details ADD COLUMN IF NOT EXISTS selected_feature VARCHAR(255) NOT NULL DEFAULT ''");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcart_bundle (
    cart_bundle_id VARCHAR(20) NOT NULL PRIMARY KEY,
    cus_id VARCHAR(250) NOT NULL,
    bundle_id INT UNSIGNED NOT NULL,
    bundle_name VARCHAR(150) NOT NULL,
    bundle_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    selected_variants TEXT NULL,
    bundle_items_json TEXT NULL,
    added_date DATE NOT NULL
)");
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
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS cus_id VARCHAR(250) NOT NULL DEFAULT '' AFTER order_id");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER dispercentage");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_ref_id INT AUTO_INCREMENT UNIQUE");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_type VARCHAR(20) NOT NULL DEFAULT 'available'");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_scope VARCHAR(20) NOT NULL DEFAULT 'all'");
mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_type_status_expiry ON tblcoupon(coupon_type, status, expiry_date)");
mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_scope ON tblcoupon(coupon_scope)");
try {
    mysqli_query($conn, "INSERT INTO tblcoupon (coupon_id, coupon_type, coupon_scope, code, dispercentage, expiry_date, min_order, status, created_at)
                        SELECT CONCAT('CH', LPAD(id, 8, '0')), 'available', 'home', code, CAST(REPLACE(REPLACE(discount, '% OFF', ''), '%', '') AS UNSIGNED), exp_date, 0.00, status, NOW()
                        FROM tblhome_coupon h
                        WHERE NOT EXISTS (
                            SELECT 1 FROM tblcoupon c WHERE c.code = h.code AND c.coupon_type = 'available'
                        )");
} catch (Throwable $e) {
    // Legacy table may not exist; ignore migration error.
}
@mysqli_query($conn, "DROP TABLE IF EXISTS tblavailable_coupon");
@mysqli_query($conn, "DROP TABLE IF EXISTS tblhome_coupon");
mysqli_query($conn, "ALTER TABLE tblorder_charge ADD COLUMN IF NOT EXISTS shipping_address_id VARCHAR(20) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE tblorder_charge ADD COLUMN IF NOT EXISTS shipping_phone_id VARCHAR(20) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE tblorder_charge ADD COLUMN IF NOT EXISTS shipping_address_text VARCHAR(255) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE tblorder_charge ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(20) NOT NULL DEFAULT ''");
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
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblfree_delivery_setting (
    setting_id TINYINT NOT NULL PRIMARY KEY,
    free_for_new TINYINT(1) NOT NULL DEFAULT 0,
    free_for_regular TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
)");
mysqli_query($conn, "INSERT INTO tblfree_delivery_setting (setting_id, free_for_new, free_for_regular, updated_at)
                      SELECT 1, 0, 0, NOW()
                      WHERE NOT EXISTS (SELECT 1 FROM tblfree_delivery_setting WHERE setting_id = 1)");

if (!empty($selectedCartIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedCartIds), '?'));
    $types = str_repeat('s', count($selectedCartIds) + 1);
    $sql = "SELECT c.cart_id, c.product_id, c.quantity, c.selected_feature, p.name, p.price, p.quantity AS stock_qty, i.image_1
            FROM tblcart c
            JOIN tblproduct p ON c.product_id = p.product_id
            LEFT JOIN tblimage i ON p.product_id = i.product_id
            WHERE c.cus_id = ? AND c.cart_id IN ($placeholders)
            ORDER BY c.added_date DESC, c.cart_id DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = array_merge([$userId], $selectedCartIds);
        $bind = [$types];
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $unitPrice = (float)$row['price'];
            $lineTotal = $unitPrice * (int)$row['quantity'];
            $subtotal += $lineTotal;
            $row['unit_price'] = $unitPrice;
            $row['line_total'] = $lineTotal;
            $items[] = $row;
        }
        $stmt->close();
    }
}
if (!empty($selectedCartIds)) {
    $bundleIds = array_values(array_filter($selectedCartIds, function ($id) {
        return stripos((string)$id, 'CB') === 0;
    }));
    if (!empty($bundleIds)) {
        $placeholders = implode(',', array_fill(0, count($bundleIds), '?'));
        $types = str_repeat('s', count($bundleIds) + 1);
        $sql = "SELECT cart_bundle_id, bundle_name, bundle_price, quantity, bundle_items_json
                FROM tblcart_bundle
                WHERE cus_id = ? AND cart_bundle_id IN ($placeholders)
                ORDER BY added_date DESC, cart_bundle_id DESC";
        $bStmt = $conn->prepare($sql);
        if ($bStmt) {
            $params = array_merge([$userId], $bundleIds);
            $bind = [$types];
            foreach ($params as $k => $v) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$bStmt, 'bind_param'], $bind);
            $bStmt->execute();
            $bres = $bStmt->get_result();
            while ($bres && $row = $bres->fetch_assoc()) {
                $unitPrice = (float)($row['bundle_price'] ?? 0);
                $lineTotal = $unitPrice * (int)$row['quantity'];
                $subtotal += $lineTotal;
                $row['unit_price'] = $unitPrice;
                $row['line_total'] = $lineTotal;
                $row['is_bundle'] = 1;
                $row['cart_id'] = (string)$row['cart_bundle_id'];
                $items[] = $row;
            }
            $bStmt->close();
        }
    }
}

$addressStmt = $conn->prepare("SELECT address_id, address, province, district FROM tbladdress WHERE cus_id = ? ORDER BY address_id DESC");
if ($addressStmt) {
    $addressStmt->bind_param("s", $userId);
    $addressStmt->execute();
    $addressRes = $addressStmt->get_result();
    $addressStmt->close();
    while ($addressRes && $addressRow = $addressRes->fetch_assoc()) {
        $addressOptions[] = $addressRow;
    }
}
$phoneStmt = $conn->prepare("SELECT phone_id, phone FROM tblphone WHERE cus_id = ? ORDER BY phone_id DESC");
if ($phoneStmt) {
    $phoneStmt->bind_param("s", $userId);
    $phoneStmt->execute();
    $phoneRes = $phoneStmt->get_result();
    $phoneStmt->close();
    while ($phoneRes && $phoneRow = $phoneRes->fetch_assoc()) {
        $phoneOptions[] = $phoneRow;
    }
}
if ($selectedAddressId === "" && count($addressOptions) > 0) {
    $selectedAddressId = (string)$addressOptions[0]['address_id'];
}
if ($selectedPhoneId === "" && count($phoneOptions) > 0) {
    $selectedPhoneId = (string)$phoneOptions[0]['phone_id'];
}
foreach ($addressOptions as $ao) {
    if ((string)$ao['address_id'] === $selectedAddressId) {
        $shippingProvince = trim((string)$ao['province']);
        $shippingDistrict = trim((string)$ao['district']);
        $shippingAddressText = trim((string)$ao['address']);
        break;
    }
}
foreach ($phoneOptions as $po) {
    if ((string)$po['phone_id'] === $selectedPhoneId) {
        $shippingPhoneText = trim((string)$po['phone']);
        break;
    }
}

if ($shippingProvince !== "" && $shippingDistrict !== "") {
    $shipStmt = $conn->prepare("SELECT rate
                                FROM tblshipping_rate
                                WHERE status = 1
                                  AND LOWER(district) = LOWER(?)
                                  AND (
                                    LOWER(province) = LOWER(?)
                                    OR REPLACE(LOWER(province), ' province', '') = REPLACE(LOWER(?), ' province', '')
                                    OR REPLACE(LOWER(province), ' ', '') = REPLACE(LOWER(?), ' ', '')
                                  )
                                LIMIT 1");
    if ($shipStmt) {
        $shipStmt->bind_param("ssss", $shippingDistrict, $shippingProvince, $shippingProvince, $shippingProvince);
        $shipStmt->execute();
        $shipRow = $shipStmt->get_result()->fetch_assoc();
        $shipStmt->close();
        if ($shipRow) {
            $shippingAmount = (float)$shipRow['rate'];
        } else {
            $shipProvinceStmt = $conn->prepare("SELECT rate
                                                FROM tblshipping_rate
                                                WHERE status = 1
                                                  AND (
                                                    LOWER(province) = LOWER(?)
                                                    OR REPLACE(LOWER(province), ' province', '') = REPLACE(LOWER(?), ' province', '')
                                                    OR REPLACE(LOWER(province), ' ', '') = REPLACE(LOWER(?), ' ', '')
                                                  )
                                                ORDER BY rate ASC
                                                LIMIT 1");
            if ($shipProvinceStmt) {
                $shipProvinceStmt->bind_param("sss", $shippingProvince, $shippingProvince, $shippingProvince);
                $shipProvinceStmt->execute();
                $shipProvinceRow = $shipProvinceStmt->get_result()->fetch_assoc();
                $shipProvinceStmt->close();
                if ($shipProvinceRow) {
                    $shippingAmount = (float)$shipProvinceRow['rate'];
                }
            }
        }
    }
}

$customerOrderCount = 0;
$customerType = "new";
$freeDeliveryApplied = false;
$freeDeliveryReason = "";
$countOrderStmt = $conn->prepare("SELECT COUNT(*) AS total_orders FROM tblorder WHERE cus_id = ?");
if ($countOrderStmt) {
    $countOrderStmt->bind_param("s", $userId);
    $countOrderStmt->execute();
    $countOrderRow = $countOrderStmt->get_result()->fetch_assoc();
    $countOrderStmt->close();
    $customerOrderCount = (int)($countOrderRow["total_orders"] ?? 0);
    $customerType = $customerOrderCount >= 3 ? "regular" : "new";
}
$freeSettingRes = mysqli_query($conn, "SELECT free_for_new, free_for_regular FROM tblfree_delivery_setting WHERE setting_id = 1 LIMIT 1");
if ($freeSettingRes) {
    $freeSetting = mysqli_fetch_assoc($freeSettingRes);
    $freeForNew = (int)($freeSetting["free_for_new"] ?? 0) === 1;
    $freeForRegular = (int)($freeSetting["free_for_regular"] ?? 0) === 1;
    if (($customerType === "new" && $freeForNew) || ($customerType === "regular" && $freeForRegular)) {
        if ($shippingAmount > 0) {
            $freeDeliveryApplied = true;
            $freeDeliveryReason = $customerType === "new"
                ? "Free delivery is active for new customers."
                : "Free delivery is active for regular customers.";
        }
        $shippingAmount = 0.0;
    }
}

$couponCodeInput = strtoupper(trim((string)($_POST['coupon_code'] ?? "")));
$paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? "cod")));
$gatewayToken = trim((string)($_POST['gateway_token'] ?? ""));
if ($couponCodeInput !== "" && $subtotal > 0) {
    $alreadyUsed = false;
    $usedStmt = $conn->prepare("SELECT coupon_id FROM tblcoupon WHERE coupon_type = 'redeemed' AND cus_id = ? AND code = ? LIMIT 1");
    if ($usedStmt) {
        $usedStmt->bind_param("ss", $userId, $couponCodeInput);
        $usedStmt->execute();
        $alreadyUsed = (bool)$usedStmt->get_result()->fetch_assoc();
        $usedStmt->close();
    }
    if ($alreadyUsed) {
        $couponMessage = "You already used this coupon.";
    } else {
    $couponStmt = $conn->prepare("SELECT code, dispercentage, expiry_date, min_order FROM tblcoupon WHERE coupon_type = 'available' AND coupon_scope IN ('all','checkout') AND code = ? AND status = 1 LIMIT 1");
    if ($couponStmt) {
        $couponStmt->bind_param("s", $couponCodeInput);
        $couponStmt->execute();
        $couponRow = $couponStmt->get_result()->fetch_assoc();
        $couponStmt->close();

        if (!$couponRow) {
            $couponMessage = "Invalid coupon code.";
        } elseif (strtotime((string)$couponRow['expiry_date']) < strtotime(date('Y-m-d'))) {
            $couponMessage = "Coupon has expired.";
        } elseif ($subtotal < (float)$couponRow['min_order']) {
            $couponMessage = "This coupon requires minimum order of " . format_rs((float)$couponRow['min_order']) . ".";
        } else {
            $appliedCoupon = $couponRow;
            $discountAmount = round($subtotal * ((int)$couponRow['dispercentage'] / 100), 2);
            $couponMessage = "Coupon applied successfully.";
        }
    }
    }
}

if ($subtotal > 0) {
    $customerOrderCountForDiscount = 0;
    $countStmtForDiscount = $conn->prepare("SELECT COUNT(*) AS total_orders FROM tblorder WHERE cus_id = ?");
    if ($countStmtForDiscount) {
        $countStmtForDiscount->bind_param("s", $userId);
        $countStmtForDiscount->execute();
        $countRowForDiscount = $countStmtForDiscount->get_result()->fetch_assoc();
        $countStmtForDiscount->close();
        $customerOrderCountForDiscount = (int)($countRowForDiscount["total_orders"] ?? 0);
    }
    $customerGroupForDiscount = $customerOrderCountForDiscount >= 3 ? "regular" : "new";
    $todayDate = date("Y-m-d");
    $bestDiscountAmount = 0.0;
    $bestDiscountTitle = "";
    $specialStmt = $conn->prepare("SELECT title, customer_group, discount_type, discount_value, min_order
                                   FROM tbldiscount_policy
                                   WHERE status = 1
                                     AND customer_group IN (?, 'all')
                                     AND (start_date IS NULL OR start_date = '' OR start_date <= ?)
                                     AND (end_date IS NULL OR end_date = '' OR end_date >= ?)");
    if ($specialStmt) {
        $specialStmt->bind_param("sss", $customerGroupForDiscount, $todayDate, $todayDate);
        $specialStmt->execute();
        $specialRes = $specialStmt->get_result();
        while ($specialRes && $row = $specialRes->fetch_assoc()) {
            $minOrderRule = (float)($row["min_order"] ?? 0);
            if ($subtotal < $minOrderRule) continue;
            $ruleType = strtolower(trim((string)($row["discount_type"] ?? "percent")));
            $ruleValue = (float)($row["discount_value"] ?? 0);
            if ($ruleValue <= 0) continue;
            $calc = $ruleType === "fixed" ? $ruleValue : round($subtotal * ($ruleValue / 100), 2);
            if ($calc > $bestDiscountAmount) {
                $bestDiscountAmount = $calc;
                $bestDiscountTitle = (string)($row["title"] ?? "");
            }
        }
        $specialStmt->close();
    }
    $maxAllowedSpecial = max(0, $subtotal - $discountAmount);
    if ($bestDiscountAmount > $maxAllowedSpecial) $bestDiscountAmount = $maxAllowedSpecial;
    if ($bestDiscountAmount > 0) {
        $specialDiscountAmount = $bestDiscountAmount;
        $specialDiscountLabel = $bestDiscountTitle !== "" ? $bestDiscountTitle : "Special discount";
    }
}

$grandTotal = max(0, $subtotal - $discountAmount - $specialDiscountAmount + $shippingAmount);

if (isset($_POST['place_order'])) {
    if (count($items) === 0) {
        $orderError = "No valid cart items selected for checkout.";
    } elseif ($selectedAddressId === "" || $selectedPhoneId === "") {
        $orderError = "Please select shipping address and phone number.";
    } elseif (!in_array($paymentMethod, ["cod", "stripe"], true)) {
        $orderError = "Please select a valid payment method.";
    } elseif ($paymentMethod === "stripe" && $gatewayToken === "") {
        $orderError = "Please complete payment verification before placing the order.";
    } else {
        $variantError = "";
        foreach ($items as $item) {
            if (!empty($item["is_bundle"])) {
                continue;
            }
            $pid = trim((string)($item["product_id"] ?? ""));
            $sf = trim((string)($item["selected_feature"] ?? ""));
            if ($pid !== "" && !rtel_pv_variant_selection_complete($conn, $pid, $sf)) {
                $variantError = "Please select variants for \"" . trim((string)($item["name"] ?? "item")) . "\" in your cart before checkout.";
                break;
            }
        }
        if ($variantError !== "") {
            $orderError = $variantError;
        } else {
        $stockError = "";
        foreach ($items as $item) {
            if (!empty($item['is_bundle'])) {
                $entries = json_decode((string)($item['bundle_items_json'] ?? '[]'), true);
                if (!is_array($entries) || count($entries) === 0) {
                    $stockError = "Bundle items are missing.";
                    break;
                }
                $bundleQty = max(1, (int)$item['quantity']);
                foreach ($entries as $en) {
                    $pid = (string)($en['product_id'] ?? '');
                    if ($pid === '') continue;
                    $stockCheck = $conn->prepare("SELECT quantity, name FROM tblproduct WHERE product_id = ? AND status = '1' LIMIT 1");
                    if ($stockCheck) {
                        $stockCheck->bind_param("s", $pid);
                        $stockCheck->execute();
                        $sr = $stockCheck->get_result()->fetch_assoc();
                        $stockCheck->close();
                        if (!$sr || (int)($sr['quantity'] ?? 0) < $bundleQty) {
                            $stockError = "Insufficient stock for bundle item " . (string)($sr['name'] ?? $pid) . ".";
                            break 2;
                        }
                    }
                }
            } else {
                if ((int)$item['stock_qty'] < (int)$item['quantity']) {
                    $stockError = "Insufficient stock for " . $item['name'] . ".";
                    break;
                }
            }
        }

        if ($stockError !== "") {
            $orderError = $stockError;
        } else {
            mysqli_begin_transaction($conn);
            try {
                $orderId = make_id('O');
                $orderedDate = date('Y-m-d');

                $orderStmt = $conn->prepare("INSERT INTO tblorder (order_id, cus_id, ordered_date) VALUES (?, ?, ?)");
                if (!$orderStmt) {
                    throw new Exception("Unable to create order.");
                }
                $orderStmt->bind_param("sss", $orderId, $userId, $orderedDate);
                $orderStmt->execute();
                $orderStmt->close();

                $detailStmt = $conn->prepare("INSERT INTO tblorder_details (orderdetails_id, order_id, product_id, quantity, unitprice, selected_feature) VALUES (?, ?, ?, ?, ?, ?)");
                $stockStmt = $conn->prepare("UPDATE tblproduct SET quantity = quantity - ? WHERE product_id = ? AND quantity >= ?");
                $removeStmt = $conn->prepare("DELETE FROM tblcart WHERE cart_id = ? AND cus_id = ?");
                if (!$detailStmt || !$stockStmt || !$removeStmt) {
                    throw new Exception("Unable to process order details.");
                }

                foreach ($items as $item) {
                    if (!empty($item['is_bundle'])) {
                        $entries = json_decode((string)($item['bundle_items_json'] ?? '[]'), true);
                        if (!is_array($entries) || count($entries) === 0) {
                            throw new Exception("Bundle items are missing.");
                        }
                        $bundleQty = max(1, (int)$item['quantity']);
                        foreach ($entries as $en) {
                            $productId = (string)($en['product_id'] ?? '');
                            if ($productId === '') continue;
                            $orderDetailsId = make_id('D');
                            $entryQty = $bundleQty;
                            $unitPrice = 0;
                            $selectedFeature = trim((string)($en['selected_feature'] ?? ''));
                            $detailStmt->bind_param("sssiis", $orderDetailsId, $orderId, $productId, $entryQty, $unitPrice, $selectedFeature);
                            $detailStmt->execute();
                            $stockStmt->bind_param("isi", $entryQty, $productId, $entryQty);
                            $stockStmt->execute();
                            if ($stockStmt->affected_rows < 1) {
                                throw new Exception("Stock changed while ordering. Please try again.");
                            }
                        }
                        $rmBundleStmt = $conn->prepare("DELETE FROM tblcart_bundle WHERE cart_bundle_id = ? AND cus_id = ?");
                        if ($rmBundleStmt) {
                            $cartBundleId = (string)$item['cart_bundle_id'];
                            $rmBundleStmt->bind_param("ss", $cartBundleId, $userId);
                            $rmBundleStmt->execute();
                            $rmBundleStmt->close();
                        }
                    } else {
                        $orderDetailsId = make_id('D');
                        $productId = (string)$item['product_id'];
                        $qty = (int)$item['quantity'];
                        $unitPrice = (int)round((float)$item['unit_price']);
                        $cartId = (string)$item['cart_id'];
                        $selectedFeature = trim((string)($item['selected_feature'] ?? ''));

                        $detailStmt->bind_param("sssiis", $orderDetailsId, $orderId, $productId, $qty, $unitPrice, $selectedFeature);
                        $detailStmt->execute();

                        $stockStmt->bind_param("isi", $qty, $productId, $qty);
                        $stockStmt->execute();
                        if ($stockStmt->affected_rows < 1) {
                            throw new Exception("Stock changed while ordering. Please try again.");
                        }

                        $removeStmt->bind_param("ss", $cartId, $userId);
                        $removeStmt->execute();
                    }
                }

                $detailStmt->close();
                $stockStmt->close();
                $removeStmt->close();

                if ($appliedCoupon) {
                    $couponUsageId = make_id('CP');
                    $couponCode = (string)$appliedCoupon['code'];
                    $couponPercentage = (int)$appliedCoupon['dispercentage'];
                    $couponExpiry = (string)$appliedCoupon['expiry_date'];
                    $couponDiscountUsed = (float)$discountAmount;
                    $couponType = "redeemed";
                    $couponUseStmt = $conn->prepare("INSERT INTO tblcoupon (coupon_id, coupon_type, order_id, cus_id, code, dispercentage, discount_amount, expiry_date, min_order, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, 1, NOW())");
                    if ($couponUseStmt) {
                        $couponUseStmt->bind_param("sssssids", $couponUsageId, $couponType, $orderId, $userId, $couponCode, $couponPercentage, $couponDiscountUsed, $couponExpiry);
                        $couponUseStmt->execute();
                        $couponUseStmt->close();
                    }
                }

                $chargeStmt = $conn->prepare("INSERT INTO tblorder_charge
                    (order_id, cus_id, subtotal, coupon_code, coupon_discount, special_discount_label, special_discount, shipping_fee, grand_total, created_at, shipping_address_id, shipping_phone_id, shipping_address_text, shipping_phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($chargeStmt) {
                    $chargeCreatedAt = date("Y-m-d H:i:s");
                    $couponCodeUsed = $appliedCoupon ? (string)$appliedCoupon['code'] : "";
                    $couponDiscountUsed = (float)$discountAmount;
                    $specialDiscountUsed = (float)$specialDiscountAmount;
                    $shippingUsed = (float)$shippingAmount;
                    $grandUsed = (float)$grandTotal;
                    $subUsed = (float)$subtotal;
                    $chargeLabel = (string)$specialDiscountLabel;
                    $shipAddressIdUsed = $selectedAddressId;
                    $shipPhoneIdUsed = $selectedPhoneId;
                    $shipAddressTextUsed = trim($shippingAddressText . ($shippingDistrict !== '' ? ', ' . $shippingDistrict : '') . ($shippingProvince !== '' ? ', ' . $shippingProvince : ''));
                    $shipPhoneTextUsed = $shippingPhoneText;
                    $chargeStmt->bind_param(
                        "ssdsdsdddsssss",
                        $orderId,
                        $userId,
                        $subUsed,
                        $couponCodeUsed,
                        $couponDiscountUsed,
                        $chargeLabel,
                        $specialDiscountUsed,
                        $shippingUsed,
                        $grandUsed,
                        $chargeCreatedAt,
                        $shipAddressIdUsed,
                        $shipPhoneIdUsed,
                        $shipAddressTextUsed,
                        $shipPhoneTextUsed
                    );
                    $chargeStmt->execute();
                    $chargeStmt->close();
                }

                // Record payment transaction for this order.
                // COD = Pending, Card = Paid.
                $paymentId = make_id('P');
                $paymentStatus = ($paymentMethod === "stripe") ? "Paid" : "Pending";
                $gatewayRef = ($paymentMethod === "stripe") ? $gatewayToken : "COD";
                $paymentAmount = (float)$grandTotal;
                $paidAt = ($paymentMethod === "stripe") ? date("Y-m-d H:i:s") : null;
                $createdAt = date("Y-m-d H:i:s");
                $payStmt = $conn->prepare("INSERT INTO tblpayment (payment_id, order_id, cus_id, method, gateway_ref, amount, currency, payment_status, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'LKR', ?, ?, ?)");
                if ($payStmt) {
                    $payStmt->bind_param("sssssdsss", $paymentId, $orderId, $userId, $paymentMethod, $gatewayRef, $paymentAmount, $paymentStatus, $paidAt, $createdAt);
                    $payStmt->execute();
                    $payStmt->close();
                }

                mysqli_commit($conn);
                rtel_notify_order_placed($conn, $orderId);
                $adminCustomerName = (string)$userId;
                $adminCustomerStmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(name), ''), cus_id) AS customer_name FROM tblcustomer WHERE cus_id = ? LIMIT 1");
                if ($adminCustomerStmt) {
                    $adminCustomerStmt->bind_param("s", $userId);
                    $adminCustomerStmt->execute();
                    $adminCustomerRow = $adminCustomerStmt->get_result()->fetch_assoc();
                    $adminCustomerStmt->close();
                    if ($adminCustomerRow) {
                        $adminCustomerName = (string)($adminCustomerRow['customer_name'] ?? $adminCustomerName);
                    }
                }
                rtel_notify_admin_new_order($conn, $orderId, $adminCustomerName);
                if ($paymentMethod === "stripe") {
                    rtel_notify_payment_invoice($conn, $orderId);
                }
                // Use PRG pattern to prevent browser form resubmission warnings on back.
                $_SESSION['checkout_order_message'] = "Order placed successfully. Your order id is " . $orderId . ".";
                $_SESSION['checkout_order_id'] = $orderId;
                header("Location: checkout.php?success=1");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $orderError = $e->getMessage();
            }
        }
        }
    }
}

$availableCoupons = [];
$couponListRes = mysqli_query($conn, "SELECT code, dispercentage, expiry_date, min_order
                                      FROM tblcoupon
                                      WHERE coupon_type = 'available'
                                        AND status = 1
                                        AND coupon_scope IN ('all','checkout')
                                        AND expiry_date >= CURDATE()
                                        AND code NOT IN (
                                            SELECT code FROM tblcoupon WHERE coupon_type = 'redeemed' AND cus_id = '" . mysqli_real_escape_string($conn, $userId) . "'
                                        )
                                      ORDER BY dispercentage DESC, expiry_date ASC");
if ($couponListRes) {
    while ($row = mysqli_fetch_assoc($couponListRes)) {
        $availableCoupons[] = $row;
    }
}

require "header.php";
?>

<style>
/* Checkout page visual refresh for better readability and hierarchy. */
.checkout-shell {
  background: linear-gradient(180deg, #f8f9fb 0%, #eef1f7 100%);
  border-radius: 14px;
  padding: 20px;
}
.checkout-panel {
  border: 0;
  border-radius: 14px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
  height: 100%;
}
.checkout-panel .card-body {
  padding: 18px;
}
.checkout-shell .row > [class*="col-"] {
  margin-bottom: 12px;
}
.checkout-shell .form-control,
.checkout-shell .form-control-sm {
  border-radius: 8px;
  min-height: 38px;
}
.checkout-shell .form-control:focus,
.checkout-shell .form-control-sm:focus {
  border-color: #111;
  box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.08);
}
.checkout-title {
  font-weight: 700;
  margin-bottom: 14px;
}
.checkout-table thead th {
  background: #111;
  color: #fff;
  border: 0;
}
.checkout-table tbody td {
  vertical-align: middle;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.summary-row.total {
  font-weight: 700;
  font-size: 1.05rem;
  border-top: 1px solid #eceff3;
  padding-top: 10px;
  margin-top: 10px;
}
.checkout-success {
  border: 1px solid #d4edda;
  background: #f4fff6;
  border-radius: 12px;
  padding: 18px;
}
.checkout-success .lead {
  margin-bottom: 10px;
}
/* Active coupon card visual state for better clarity. */
.js-coupon-card.coupon-active {
  border-color: #111 !important;
  background: #f3f5f8;
  box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.08);
}
.js-coupon-card {
  border-color: #e5e7eb !important;
  min-height: 72px;
}
.js-coupon-card .btn {
  min-width: 88px;
}
.checkout-table td,
.checkout-table th {
  padding-top: 10px;
  padding-bottom: 10px;
}
.checkout-table tbody tr:last-child td {
  border-bottom: 0;
}
.checkout-shell .form-check {
  margin-bottom: 6px;
}
#stripeGatewayPanel {
  min-height: 130px;
}
#stripeCardElement {
  min-height: 42px;
  border-radius: 8px;
}
.checkout-password-wrap {
  position: relative;
}
.checkout-password-wrap .form-control {
  padding-right: 44px;
}
.checkout-password-toggle {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  width: 28px;
  height: 28px;
  border: 1px solid #e3e3e3;
  border-radius: 50%;
  background: #fff;
  color: #555;
  font-size: 14px;
  line-height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  z-index: 3;
}
.checkout-password-toggle:hover {
  color: #111;
  border-color: #cfcfcf;
  background: #f9f9f9;
}
@media (max-width: 991.98px) {
  .checkout-shell {
    padding: 14px;
  }
  .checkout-panel .card-body {
    padding: 14px;
  }
}
</style>

<div class="hero-wrap hero-bread" style="background-image: url(../images/banner1.jpg);">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Checkout</span></p>
        <h1 class="mb-0 bread">Checkout</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section">
  <div class="container checkout-shell">
    <?php if ($orderMessage !== ""): ?>
      <!-- Success state with clear next step and auto redirect. -->
      <div class="checkout-success mb-4">
        <div class="lead"><strong><?php echo htmlspecialchars($orderMessage, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <p class="mb-2">Redirecting to your orders page in <span id="checkoutRedirectSeconds">5</span> seconds...</p>
        <a href="my_orders.php" class="btn btn-dark btn-sm">View My Orders</a>
      </div>
    <?php endif; ?>
    <?php if ($orderError !== ""): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($orderError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (count($items) === 0 && $orderMessage === ""): ?>
      <div class="alert alert-warning">No items selected. <a href="cart.php">Go back to cart</a> and choose products.</div>
    <?php else: ?>
      <form method="post" action="checkout.php" id="checkoutForm">
        <?php foreach ($selectedCartIds as $cid): ?>
          <input type="hidden" name="cart_ids[]" value="<?php echo htmlspecialchars((string)$cid, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
        <input type="hidden" name="coupon_code" id="coupon_code_hidden" value="<?php echo htmlspecialchars($couponCodeInput, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="shipping_address_id" id="shipping_address_id_hidden" value="<?php echo htmlspecialchars($selectedAddressId, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="shipping_phone_id" id="shipping_phone_id_hidden" value="<?php echo htmlspecialchars($selectedPhoneId, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="row">
          <div class="col-12">
            <div class="card checkout-panel mb-4">
              <div class="card-body">
                <div class="checkout-title">Selected Items</div>
                <div class="table-responsive">
                  <table class="table checkout-table mb-0">
                    <thead>
                  <tr class="text-center">
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                    <tr class="text-center">
                      <td class="text-left">
                        <?php if (!empty($item['is_bundle'])): ?>
                          <strong><?php echo htmlspecialchars((string)($item['bundle_name'] ?? 'Bundle'), ENT_QUOTES, 'UTF-8'); ?></strong>
                          <?php
                            $entries = json_decode((string)($item['bundle_items_json'] ?? '[]'), true);
                            if (!is_array($entries)) $entries = [];
                          ?>
                          <?php foreach ($entries as $en): ?>
                            <div><small>- <?php echo htmlspecialchars((string)($en['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($en['selected_feature']) ? (' (' . htmlspecialchars((string)$en['selected_feature'], ENT_QUOTES, 'UTF-8') . ')') : ''; ?></small></div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <strong><?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                          <?php if (!empty($item['selected_feature'])): ?>
                            <div><small>Feature: <?php echo htmlspecialchars((string)$item['selected_feature'], ENT_QUOTES, 'UTF-8'); ?></small></div>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars(format_rs((float)$item['unit_price']), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)$item['quantity']; ?></td>
                      <td><?php echo htmlspecialchars(format_rs((float)$item['line_total']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-lg-6">
            <div class="card checkout-panel mb-4">
              <div class="card-body">
                <div class="checkout-title">Available Coupons</div>
                <?php if (count($availableCoupons) > 0): ?>
                  <?php foreach ($availableCoupons as $coupon): ?>
                    <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2 js-coupon-card" data-code="<?php echo htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8'); ?>">
                      <div>
                        <strong><?php echo htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small class="d-block text-muted">
                          <?php echo (int)$coupon['dispercentage']; ?>% OFF | Min <?php echo htmlspecialchars(format_rs((float)$coupon['min_order']), ENT_QUOTES, 'UTF-8'); ?> | Exp: <?php echo htmlspecialchars((string)$coupon['expiry_date'], ENT_QUOTES, 'UTF-8'); ?>
                        </small>
                      </div>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-dark js-copy-coupon"
                        data-code="<?php echo htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-dispercentage="<?php echo (int)$coupon['dispercentage']; ?>"
                        data-min-order="<?php echo htmlspecialchars((string)$coupon['min_order'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-expiry="<?php echo htmlspecialchars((string)$coupon['expiry_date'], ENT_QUOTES, 'UTF-8'); ?>"
                      >Use</button>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-danger js-remove-coupon"
                        data-code="<?php echo htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8'); ?>"
                        style="display:none;"
                      >Remove</button>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="mb-0 text-muted">No active coupons available now.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card checkout-panel mb-3">
              <div class="card-body">
              <div class="checkout-title">Order Summary</div>
              <div class="mb-3">
                <label class="small d-block"><strong>Shipping Address</strong></label>
                <select id="shipping_address_select" class="form-control form-control-sm">
                  <option value="">Select address</option>
                  <?php foreach ($addressOptions as $ao): ?>
                    <option
                      value="<?php echo htmlspecialchars((string)$ao['address_id'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-province="<?php echo htmlspecialchars((string)$ao['province'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-district="<?php echo htmlspecialchars((string)$ao['district'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-address="<?php echo htmlspecialchars((string)$ao['address'], ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo (string)$ao['address_id'] === $selectedAddressId ? 'selected' : ''; ?>
                    ><?php echo htmlspecialchars((string)$ao['address'] . ' - ' . (string)$ao['district'] . ', ' . (string)$ao['province'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="small d-block"><strong>Shipping Phone</strong></label>
                <select id="shipping_phone_select" class="form-control form-control-sm">
                  <option value="">Select phone</option>
                  <?php foreach ($phoneOptions as $po): ?>
                    <option value="<?php echo htmlspecialchars((string)$po['phone_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string)$po['phone_id'] === $selectedPhoneId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$po['phone'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="summary-row"><span>Subtotal</span><span><?php echo htmlspecialchars(format_rs((float)$subtotal), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <div class="mb-3">
                <small id="couponStatusMsg" class="<?php echo ($appliedCoupon ? 'text-success' : ($couponMessage !== '' ? 'text-danger' : 'text-muted')); ?>">
                  <?php
                    if ($couponMessage !== "") {
                        echo htmlspecialchars($couponMessage, ENT_QUOTES, 'UTF-8');
                    } else {
                        echo "Select a coupon card to apply discount instantly.";
                    }
                  ?>
                </small>
              </div>
              <div class="summary-row"><span>Discount</span><span id="discountAmountText">- <?php echo htmlspecialchars(format_rs((float)$discountAmount), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <div class="summary-row"><span>Special Discount</span><span id="specialDiscountAmountText">- <?php echo htmlspecialchars(format_rs((float)$specialDiscountAmount), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <?php if ($specialDiscountLabel !== ""): ?>
                <small class="text-success d-block mb-2"><?php echo htmlspecialchars($specialDiscountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
              <div class="summary-row"><span>Delivery</span><span><?php echo htmlspecialchars(format_rs((float)$shippingAmount), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <?php if ($freeDeliveryApplied): ?>
                <small class="text-success d-block mb-2"><?php echo htmlspecialchars($freeDeliveryReason, ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
              <?php if ($shippingProvince !== "" || $shippingDistrict !== ""): ?>
                <small class="text-muted d-block mb-2">Shipping area: <?php echo htmlspecialchars($shippingProvince . ($shippingDistrict !== "" ? " / " . $shippingDistrict : ""), ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>

              <!-- Payment method selector -->
              <div class="mb-3 mt-3">
                <label class="mb-2 d-block"><strong>Payment Method</strong></label>
                <div class="form-check">
                  <input class="form-check-input js-payment-method" type="radio" name="payment_method" id="pay_cod" value="cod" <?php echo $paymentMethod === "cod" ? "checked" : ""; ?>>
                  <label class="form-check-label" for="pay_cod">Cash on Delivery</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input js-payment-method" type="radio" name="payment_method" id="pay_stripe" value="stripe" <?php echo $paymentMethod === "stripe" ? "checked" : ""; ?>>
                  <label class="form-check-label" for="pay_stripe">Stripe (Card)</label>
                </div>
              </div>

              <div id="stripeGatewayPanel" class="border rounded p-3 mb-3" style="display:none;">
                <div class="small font-weight-bold mb-2">Secure Stripe Payment</div>
                <div id="stripeCardElement" class="form-control" style="padding-top:10px;height:42px;"></div>
                <button type="button" class="btn btn-outline-dark btn-sm mt-2" id="verifyStripeBtn">Pay with Stripe</button>
                <small id="stripePaymentNotice" class="d-block mt-2 text-muted">Complete Stripe payment in LKR before placing order.</small>
              </div>
              <input type="hidden" name="gateway_token" id="gateway_token" value="<?php echo htmlspecialchars($gatewayToken, ENT_QUOTES, 'UTF-8'); ?>">

              <hr>
              <div class="summary-row total"><span>Total</span><span id="grandTotalText"><?php echo htmlspecialchars(format_rs((float)$grandTotal), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <p>
                <button type="submit" name="place_order" value="1" class="btn btn-black py-3 px-4 w-100" id="placeOrderBtn">Place Order</button>
              </p>
              <p><a href="cart.php" class="btn btn-outline-dark w-100">Back to Cart</a></p>
              </div>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Auto-apply coupon when user clicks "Use" on a coupon card.
  const couponButtons = document.querySelectorAll(".js-copy-coupon");
  const removeCouponButtons = document.querySelectorAll(".js-remove-coupon");
  const couponCards = document.querySelectorAll(".js-coupon-card");
  const couponHiddenInput = document.getElementById("coupon_code_hidden");
  const discountText = document.getElementById("discountAmountText");
  const grandTotalText = document.getElementById("grandTotalText");
  const couponStatusMsg = document.getElementById("couponStatusMsg");
  let appliedCouponCode = <?php echo json_encode((string)$couponCodeInput); ?>;
  const baseSubtotal = <?php echo json_encode((float)$subtotal); ?>;
  const shippingFee = <?php echo json_encode((float)$shippingAmount); ?>;
  const baseSpecialDiscount = <?php echo json_encode((float)$specialDiscountAmount); ?>;
  const specialDiscountText = document.getElementById("specialDiscountAmountText");
  const shippingAddressSelect = document.getElementById("shipping_address_select");
  const shippingPhoneSelect = document.getElementById("shipping_phone_select");
  const shippingAddressHidden = document.getElementById("shipping_address_id_hidden");
  const shippingPhoneHidden = document.getElementById("shipping_phone_id_hidden");

  function formatRs(value) {
    return "Rs. " + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function todayYmd() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return y + "-" + m + "-" + day;
  }

  function applyDiscountView(discountValue) {
    const discount = Number(discountValue || 0);
    const total = Math.max(0, baseSubtotal - discount - baseSpecialDiscount + shippingFee);
    discountText.textContent = "- " + formatRs(discount);
    if (specialDiscountText) specialDiscountText.textContent = "- " + formatRs(baseSpecialDiscount);
    grandTotalText.textContent = formatRs(total);
  }

  function setCouponUiState(selectedCode) {
    couponCards.forEach(function (card) {
      const code = card.getAttribute("data-code") || "";
      const useBtn = card.querySelector(".js-copy-coupon");
      const removeBtn = card.querySelector(".js-remove-coupon");
      if (!useBtn || !removeBtn) return;

      if (!selectedCode) {
        card.classList.remove("coupon-active");
        useBtn.style.display = "inline-block";
        removeBtn.style.display = "none";
        useBtn.disabled = false;
        return;
      }

      if (code === selectedCode) {
        card.classList.add("coupon-active");
        useBtn.style.display = "none";
        removeBtn.style.display = "inline-block";
      } else {
        card.classList.remove("coupon-active");
        // Hide other "Use" actions while one coupon is active.
        useBtn.style.display = "none";
        removeBtn.style.display = "none";
      }
    });
  }

  couponButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      if (!couponHiddenInput || !discountText || !grandTotalText || !couponStatusMsg) return;
      const code = button.getAttribute("data-code") || "";
      const percentage = Number(button.getAttribute("data-dispercentage") || "0");
      const minOrder = Number(button.getAttribute("data-min-order") || "0");
      const expiry = button.getAttribute("data-expiry") || "";
      if (!code) return;

      // Enforce single coupon usage.
      if (appliedCouponCode && appliedCouponCode !== code) {
        couponStatusMsg.textContent = "Only one coupon can be applied at a time.";
        couponStatusMsg.className = "text-danger";
        return;
      }

      if (expiry && expiry < todayYmd()) {
        couponStatusMsg.textContent = "Coupon has expired.";
        couponStatusMsg.className = "text-danger";
        couponHiddenInput.value = "";
        applyDiscountView(0);
        appliedCouponCode = "";
        setCouponUiState("");
        return;
      }
      if (baseSubtotal < minOrder) {
        couponStatusMsg.textContent = "This coupon requires minimum order of " + formatRs(minOrder) + ".";
        couponStatusMsg.className = "text-danger";
        couponHiddenInput.value = "";
        applyDiscountView(0);
        appliedCouponCode = "";
        setCouponUiState("");
        return;
      }

      // Correct percentage math: e.g. 10% of 118,600 = 11,860.00
      const discount = Math.round((baseSubtotal * (percentage / 100)) * 100) / 100;

      couponHiddenInput.value = code;
      appliedCouponCode = code;
      applyDiscountView(discount);
      couponStatusMsg.textContent = "Coupon " + code + " applied successfully.";
      couponStatusMsg.className = "text-success";
      setCouponUiState(code);
    });
  });

  removeCouponButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const code = button.getAttribute("data-code") || "";
      if (!code || appliedCouponCode !== code) return;
      couponHiddenInput.value = "";
      appliedCouponCode = "";
      applyDiscountView(0);
      couponStatusMsg.textContent = "Coupon removed.";
      couponStatusMsg.className = "text-muted";
      setCouponUiState("");
    });
  });

  // Initialize UI on page load based on currently applied coupon.
  setCouponUiState(appliedCouponCode);

  // Payment method controls.
  const paymentMethodEls = document.querySelectorAll(".js-payment-method");
  const stripePanel = document.getElementById("stripeGatewayPanel");
  const verifyStripeBtn = document.getElementById("verifyStripeBtn");
  const gatewayToken = document.getElementById("gateway_token");
  const stripePaymentNotice = document.getElementById("stripePaymentNotice");
  const placeOrderBtn = document.getElementById("placeOrderBtn");
  const payCod = document.getElementById("pay_cod");
  const payStripe = document.getElementById("pay_stripe");
  const stripeAmount = <?php echo json_encode((float)$grandTotal); ?>;
  let stripe = null;
  let stripeCardElement = null;
  let stripeClientSecret = "";
  let stripeDemoMode = false;
  document.querySelectorAll(".checkout-password-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var targetId = btn.getAttribute("data-target");
      var input = targetId ? document.getElementById(targetId) : null;
      if (!input) return;
      var isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";
      btn.innerHTML = isHidden ? "&#128064;" : "&#128065;";
      btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
      btn.setAttribute("title", isHidden ? "Hide password" : "Show password");
    });
  });

  function currentMethod() {
    if (payStripe && payStripe.checked) return "stripe";
    return "cod";
  }

  // Toggle card panel visibility and order button availability.
  function syncPaymentUI() {
    const method = currentMethod();
    if (stripePanel) {
      stripePanel.style.display = method === "stripe" ? "block" : "none";
    }
    if (placeOrderBtn) {
      placeOrderBtn.disabled = (method === "stripe" && (!gatewayToken || !gatewayToken.value));
    }
    if (stripePaymentNotice && method !== "stripe") {
      stripePaymentNotice.textContent = "Complete Stripe payment in LKR before placing order.";
      stripePaymentNotice.className = "d-block mt-2 text-muted";
    }
    if (method === "stripe" && !stripeDemoMode && !stripeCardElement) {
      setupStripe();
    }
  }

  function in_array_like(value, arr) {
    return Array.isArray(arr) && arr.indexOf(value) !== -1;
  }

  paymentMethodEls.forEach(function (el) {
    el.addEventListener("change", function () {
      if (gatewayToken) {
        gatewayToken.value = "";
      }
      syncPaymentUI();
    });
  });

  function setupStripe() {
    if (!window.Stripe || !stripePanel) return Promise.resolve(false);
    return fetch("payment_gateway_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "stripe_config" })
    })
      .then(function (res) { return res.json(); })
      .then(function (cfg) {
        if (!cfg || !cfg.success) {
          if (stripePaymentNotice) {
            stripePaymentNotice.textContent = (cfg && cfg.message) ? cfg.message : "Stripe is not configured.";
            stripePaymentNotice.className = "d-block mt-2 text-danger";
          }
          return false;
        }
        if (cfg.demo_mode) {
          stripeDemoMode = true;
          stripeClientSecret = "DEMO_CLIENT_SECRET";
          var stripeCardContainer = document.getElementById("stripeCardElement");
          if (stripeCardContainer) stripeCardContainer.style.display = "none";
          if (verifyStripeBtn) verifyStripeBtn.textContent = "Verify Demo Payment";
          if (stripePaymentNotice) {
            stripePaymentNotice.textContent = "Stripe demo mode enabled. Click Verify Payment to simulate success.";
            stripePaymentNotice.className = "d-block mt-2 text-warning";
          }
          return true;
        }
        stripeDemoMode = false;
        var stripeCardContainerLive = document.getElementById("stripeCardElement");
        if (stripeCardContainerLive) stripeCardContainerLive.style.display = "block";
        if (verifyStripeBtn) verifyStripeBtn.textContent = "Pay with Stripe";
        if (!cfg.publishable_key) {
          if (stripePaymentNotice) {
            stripePaymentNotice.textContent = "Stripe publishable key is missing.";
            stripePaymentNotice.className = "d-block mt-2 text-danger";
          }
          return false;
        }
        stripe = Stripe(cfg.publishable_key);
        const elements = stripe.elements();
        stripeCardElement = elements.create("card", { hidePostalCode: true });
        stripeCardElement.mount("#stripeCardElement");
        return fetch("payment_gateway_api.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "stripe_create_intent", amount: stripeAmount })
        });
      })
      .then(function (res) {
        if (!res || typeof res.json !== "function") return false;
        return res.json();
      })
      .then(function (intent) {
        if (!intent || intent.success !== true) {
          if (stripePaymentNotice) {
            stripePaymentNotice.textContent = (intent && intent.message) ? intent.message : "Unable to initialize Stripe payment.";
            stripePaymentNotice.className = "d-block mt-2 text-danger";
          }
          return false;
        }
        stripeClientSecret = String(intent.client_secret || "");
        return stripeClientSecret !== "";
      })
      .catch(function () {
        if (stripePaymentNotice) {
          stripePaymentNotice.textContent = "Unable to connect Stripe gateway.";
          stripePaymentNotice.className = "d-block mt-2 text-danger";
        }
        return false;
      });
  }

  if (verifyStripeBtn) {
    verifyStripeBtn.addEventListener("click", async function () {
      if (!stripeDemoMode && (!stripe || !stripeCardElement || !stripeClientSecret)) {
        const ok = await setupStripe();
        if (!ok) {
          syncPaymentUI();
          return;
        }
      }
      if (stripeDemoMode) {
        var demoRef = "DEMO_STRIPE_" + Date.now();
        if (gatewayToken) gatewayToken.value = demoRef;
        if (stripePaymentNotice) {
          stripePaymentNotice.textContent = "Stripe demo payment verified. Ref: " + demoRef;
          stripePaymentNotice.className = "d-block mt-2 text-success";
        }
        syncPaymentUI();
        return;
      }
      const result = await stripe.confirmCardPayment(stripeClientSecret, {
        payment_method: { card: stripeCardElement }
      });
      if (result.error) {
        if (gatewayToken) gatewayToken.value = "";
        if (stripePaymentNotice) {
          stripePaymentNotice.textContent = result.error.message || "Stripe payment failed.";
          stripePaymentNotice.className = "d-block mt-2 text-danger";
        }
        syncPaymentUI();
        return;
      }
      const pi = result.paymentIntent || {};
      if (pi.status !== "succeeded") {
        if (gatewayToken) gatewayToken.value = "";
        if (stripePaymentNotice) {
          stripePaymentNotice.textContent = "Stripe payment not completed.";
          stripePaymentNotice.className = "d-block mt-2 text-danger";
        }
        syncPaymentUI();
        return;
      }
      if (gatewayToken) gatewayToken.value = String(pi.id || "STRIPE_OK");
      if (stripePaymentNotice) {
        stripePaymentNotice.textContent = "Stripe payment verified. Ref: " + String(pi.id || "");
        stripePaymentNotice.className = "d-block mt-2 text-success";
      }
      syncPaymentUI();
    });
  }

  syncPaymentUI();
  if (shippingAddressSelect && shippingAddressHidden) {
    shippingAddressSelect.addEventListener("change", function () {
      shippingAddressHidden.value = String(shippingAddressSelect.value || "");
    });
  }
  if (shippingPhoneSelect && shippingPhoneHidden) {
    shippingPhoneSelect.addEventListener("change", function () {
      shippingPhoneHidden.value = String(shippingPhoneSelect.value || "");
    });
  }
  var checkoutForm = document.getElementById("checkoutForm");
  if (checkoutForm) {
    checkoutForm.addEventListener("submit", function (e) {
      if (shippingAddressHidden && !String(shippingAddressHidden.value || "").trim()) {
        e.preventDefault();
        alert("Please select shipping address.");
        return;
      }
      if (shippingPhoneHidden && !String(shippingPhoneHidden.value || "").trim()) {
        e.preventDefault();
        alert("Please select shipping phone number.");
      }
    });
  }

  // Post-checkout redirect helper.
  var redirectCounter = document.getElementById("checkoutRedirectSeconds");
  <?php if ($orderMessage !== ""): ?>
  var seconds = 5;
  var timer = setInterval(function () {
    seconds -= 1;
    if (redirectCounter) {
      redirectCounter.textContent = String(seconds);
    }
    if (seconds <= 0) {
      clearInterval(timer);
      window.location.href = "my_orders.php";
    }
  }, 1000);
  <?php endif; ?>
});
</script>

<?php require "footer.php"; ?>
