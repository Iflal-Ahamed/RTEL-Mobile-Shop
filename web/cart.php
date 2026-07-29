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
require_once "mail/mail_notifications.php";
$userId = $_SESSION["user_id"];

function format_rs($amount)
{
    return "Rs. " . number_format((float)$amount, 2);
}

function delivery_display_text($shippingAmount, $hasShippingArea, $freeDeliveryApplied)
{
    if ((float)$shippingAmount > 0) {
        return format_rs((float)$shippingAmount);
    }
    if ($hasShippingArea && $freeDeliveryApplied) {
        return "Free (Rs. 0.00)";
    }
    if ($hasShippingArea) {
        return "Rs. 0.00";
    }
    return "Pay at checkout";
}

function rtel_cart_variant_groups($conn, $productId)
{
    return rtel_pv_variant_groups_cart($conn, $productId);
}

function rtel_cart_extract_variant_piece($selected, $label)
{
    return rtel_pv_extract_variant_piece($selected, $label);
}

function rtel_cart_parse_variant_map($raw)
{
    $map = [];
    $raw = trim((string)$raw);
    if ($raw === '') return $map;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $map;
    foreach ($decoded as $pid => $val) {
        $k = trim((string)$pid);
        if ($k === '') continue;
        $map[$k] = trim((string)$val);
    }
    return $map;
}

function rtel_cart_alert_log_table_exists(mysqli $conn)
{
    static $exists = null;
    if ($exists !== null) {
        return (bool)$exists;
    }
    try {
        $res = $conn->query("SHOW TABLES LIKE 'tblcart_alert_log'");
        $exists = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $exists = false;
    }
    return (bool)$exists;
}

function rtel_cart_fetch_bundle_items($conn, $bundleId, array $variantMap = [])
{
    $out = [];
    $stmt = $conn->prepare("SELECT bi.product_id, COALESCE(p.name, CONCAT('Item ', bi.product_id)) AS name
                            FROM tblbundle_item bi
                            LEFT JOIN tblproduct p ON p.product_id = bi.product_id
                            WHERE bi.bundle_id = ?
                            ORDER BY bi.sort_order ASC, bi.bundle_item_id ASC");
    if (!$stmt) return $out;
    $stmt->bind_param("i", $bundleId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $pid = trim((string)($row['product_id'] ?? ''));
        if ($pid === '') continue;
        $out[] = [
            'product_id' => $pid,
            'name' => (string)($row['name'] ?? ''),
            'selected_feature' => isset($variantMap[$pid]) ? (string)$variantMap[$pid] : ''
        ];
    }
    $stmt->close();
    return $out;
}

$items = [];
$subtotal = 0.0;
$shippingAmount = 0.0;
$shippingProvince = "";
$shippingDistrict = "";
$freeDeliveryApplied = false;
$freeDeliveryReason = "";
$cartAlerts = [];

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

$stmt = $conn->prepare(
    "SELECT c.cart_id, c.quantity, c.price AS cart_price, c.added_date, c.selected_feature, p.product_id, p.name, p.price AS product_price, p.quantity AS stock_qty, i.image_1
     FROM tblcart c
     JOIN tblproduct p ON c.product_id = p.product_id
     LEFT JOIN tblimage i ON p.product_id = i.product_id
     WHERE c.cus_id = ?
     ORDER BY c.added_date DESC, c.cart_id DESC"
);
if ($stmt) {
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $unitPrice = isset($row["product_price"]) ? (float)$row["product_price"] : (float)$row["cart_price"];
        $cartPrice = (float)$row["cart_price"];
        $qty = (int)$row["quantity"];
        $stockQty = (int)$row["stock_qty"];
        $lineTotal = $unitPrice * (int)$row["quantity"];
        $subtotal += $lineTotal;
        $row["unit_price"] = $unitPrice;
        $row["line_total"] = $lineTotal;
        $items[] = $row;

        if ($cartPrice > 0 && $unitPrice < $cartPrice) {
            $cartAlerts[] = [
                "cart_id" => (string)$row["cart_id"],
                "type" => "price_drop",
                "product_name" => (string)$row["name"],
                "old_value" => $cartPrice,
                "new_value" => $unitPrice,
                "alert_key" => "price_drop_" . number_format($cartPrice, 2, ".", "") . "_" . number_format($unitPrice, 2, ".", "")
            ];
        }
        if ($stockQty >= 0 && $qty > $stockQty) {
            $cartAlerts[] = [
                "cart_id" => (string)$row["cart_id"],
                "type" => "stock_reduced",
                "product_name" => (string)$row["name"],
                "old_value" => $qty,
                "new_value" => $stockQty,
                "alert_key" => "stock_reduced_" . $qty . "_" . $stockQty
            ];
        }
    }
    $stmt->close();
}
$bundleStmt = $conn->prepare("SELECT cb.cart_bundle_id, cb.bundle_id, cb.bundle_name, cb.bundle_price, cb.quantity, cb.selected_variants, cb.bundle_items_json, cb.added_date,
                                     COALESCE(NULLIF(b.bundle_image, ''), 'smartphone.png') AS bundle_image
                              FROM tblcart_bundle cb
                              LEFT JOIN tblbundle b ON b.bundle_id = cb.bundle_id
                              WHERE cb.cus_id = ?
                              ORDER BY cb.added_date DESC, cb.cart_bundle_id DESC");
if ($bundleStmt) {
    $bundleStmt->bind_param("s", $userId);
    $bundleStmt->execute();
    $bundleRes = $bundleStmt->get_result();
    while ($bundleRes && $row = $bundleRes->fetch_assoc()) {
        $unitPrice = (float)($row["bundle_price"] ?? 0);
        $qty = max(1, (int)($row["quantity"] ?? 1));
        $lineTotal = $unitPrice * $qty;
        $subtotal += $lineTotal;
        $variantMap = rtel_cart_parse_variant_map((string)($row["selected_variants"] ?? "{}"));
        $bundleEntries = json_decode((string)($row["bundle_items_json"] ?? "[]"), true);
        if (!is_array($bundleEntries)) $bundleEntries = [];
        $liveItems = rtel_cart_fetch_bundle_items($conn, (int)($row["bundle_id"] ?? 0), $variantMap);
        if (count($liveItems) > 0) {
            $bundleEntries = $liveItems;
        } elseif (count($bundleEntries) > 0) {
            foreach ($bundleEntries as &$entry) {
                $pid = trim((string)($entry['product_id'] ?? ''));
                if ($pid !== '' && isset($variantMap[$pid])) {
                    $entry['selected_feature'] = (string)$variantMap[$pid];
                }
            }
            unset($entry);
        }
        $items[] = [
            "cart_id" => (string)$row["cart_bundle_id"],
            "is_bundle" => 1,
            "bundle_name" => (string)($row["bundle_name"] ?? "Bundle"),
            "bundle_items_json" => json_encode($bundleEntries),
            "selected_variants" => (string)($row["selected_variants"] ?? "{}"),
            "quantity" => $qty,
            "unit_price" => $unitPrice,
            "line_total" => $lineTotal,
            "added_date" => (string)($row["added_date"] ?? ""),
            "image_1" => (string)($row["bundle_image"] ?? "smartphone.png"),
            "stock_qty" => 9999
        ];
    }
    $bundleStmt->close();
}

$addressStmt = $conn->prepare("SELECT province, district FROM tbladdress WHERE cus_id = ? ORDER BY address_id DESC LIMIT 1");
if ($addressStmt) {
    $addressStmt->bind_param("s", $userId);
    $addressStmt->execute();
    $addressRow = $addressStmt->get_result()->fetch_assoc();
    $addressStmt->close();
    if ($addressRow) {
        $shippingProvince = trim((string)$addressRow['province']);
        $shippingDistrict = trim((string)$addressRow['district']);
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
$hasShippingArea = ($shippingProvince !== "" || $shippingDistrict !== "");
$deliveryDisplay = delivery_display_text($shippingAmount, $hasShippingArea, $freeDeliveryApplied);

if (count($cartAlerts) > 0 && rtel_cart_alert_log_table_exists($conn)) {
    $toSend = [];
    $insLog = $conn->prepare("INSERT IGNORE INTO tblcart_alert_log (cus_id, cart_id, alert_type, alert_key, created_at) VALUES (?, ?, ?, ?, ?)");
    if ($insLog) {
        $now = date("Y-m-d H:i:s");
        foreach ($cartAlerts as $a) {
            $cartId = (string)$a["cart_id"];
            $alertType = (string)$a["type"];
            $alertKey = (string)$a["alert_key"];
            $insLog->bind_param("sssss", $userId, $cartId, $alertType, $alertKey, $now);
            $insLog->execute();
            if ($insLog->affected_rows > 0) {
                $toSend[] = $a;
            }
        }
        $insLog->close();
    }
    if (count($toSend) > 0) {
        $custRes = $conn->prepare("SELECT email, name FROM tblcustomer WHERE cus_id = ? LIMIT 1");
        if ($custRes) {
            $custRes->bind_param("s", $userId);
            $custRes->execute();
            $cust = $custRes->get_result()->fetch_assoc();
            $custRes->close();
            if ($cust && !empty($cust["email"])) {
                rtel_notify_cart_change_digest((string)$cust["email"], (string)($cust["name"] ?? "Customer"), $toSend);
            }
        }
    }
}

require "header.php";
?>

<style>
  .cart-totals-wrap {
    width: 100%;
  }
  .cart-totals-wrap .cart-total {
    width: 100%;
    max-width: 100%;
  }
  .cart-checkout-btn {
    background: #000 !important;
    border-color: #000 !important;
    color: #fff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .cart-checkout-btn.btn,
  .cart-checkout-btn.btn:hover,
  .cart-checkout-btn.btn:active,
  .cart-checkout-btn.btn:focus,
  .cart-checkout-btn:hover,
  .cart-checkout-btn:active,
  .cart-checkout-btn:focus {
    background: #111 !important;
    border-color: #111 !important;
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
  }
  .cart-checkout-btn,
  .cart-checkout-btn span {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
  }
  .cart-total .d-flex {
    justify-content: space-between;
    flex-wrap: nowrap;
    gap: 12px;
  }
  .cart-total .d-flex > span {
    white-space: nowrap;
  }
  .cart-amount {
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
  }
  .bundle-variant-select {
    height: 34px;
    min-width: 170px;
    max-width: 230px;
    border-radius: 999px;
    border: 1px solid #d7e4ff;
    background: #f8fbff;
    font-size: 12px;
    color: #1e3a8a;
    padding: 4px 10px;
  }
  .bundle-variant-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.14);
  }
  .bundle-customize-btn {
    border: none !important;
    border-radius: 999px !important;
    padding: 7px 14px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #fff !important;
    background: linear-gradient(135deg, #111827, #2563eb) !important;
    box-shadow: 0 10px 20px rgba(37, 99, 235, .24);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .bundle-customize-btn:hover,
  .bundle-customize-btn:focus {
    color: #fff !important;
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(37, 99, 235, .30);
  }
  .bundle-customize-template {
    display: none;
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url(../images/banner1.jpg);">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Cart</span></p>
        <h1 class="mb-0 bread">My Cart</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section ftco-cart">
  <div class="container">
    <form action="checkout.php" method="get" id="cartCheckoutForm">
    <div class="row">
      <div class="col-md-12 ftco-animate">
        <div class="cart-list">
          <table class="table">
            <thead class="thead-dark">
              <tr class="text-center">
                <th><input type="checkbox" id="selectAllCart"></th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th>Product name</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($items) === 0): ?>
                <tr><td colspan="7" class="text-center py-4">Your cart is empty.</td></tr>
              <?php endif; ?>
              <?php foreach ($items as $item): ?>
                <?php
                  $lineNeedsVariant = false;
                  $lineVariantOk = true;
                  if (empty($item['is_bundle'])) {
                      $lineNeedsVariant = rtel_pv_product_needs_variant_choice($conn, (string)$item['product_id']);
                      $lineVariantOk = rtel_pv_variant_selection_complete($conn, (string)$item['product_id'], trim((string)($item['selected_feature'] ?? '')));
                  }
                  $canSelectStock = !empty($item['is_bundle']) || (int)($item['stock_qty'] ?? 0) > 0;
                  $checkboxDisabled = !$canSelectStock || (!empty($item['is_bundle']) ? false : ($lineNeedsVariant && !$lineVariantOk));
                ?>
                <tr class="text-center" id="cart-row-<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>">
                  <td>
                    <?php if ($canSelectStock): ?>
                      <input
                        type="checkbox"
                        class="js-cart-select"
                        name="cart_ids[]"
                        value="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-line-total="<?php echo htmlspecialchars((string)$item['line_total'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-variant-required="<?php echo (!empty($item['is_bundle']) || !$lineNeedsVariant) ? '0' : '1'; ?>"
                        data-variant-ok="<?php echo (!empty($item['is_bundle']) || !$lineNeedsVariant || $lineVariantOk) ? '1' : '0'; ?>"
                        <?php echo $checkboxDisabled ? 'disabled ' : ''; ?>
                      >
                    <?php else: ?>
                      <small class="text-muted">N/A</small>
                    <?php endif; ?>
                  </td>
                  <td class="product-remove">
                    <a href="product_action.php?action=<?php echo !empty($item['is_bundle']) ? 'remove_bundle_cart&cart_bundle_id' : 'remove_cart&cart_id'; ?>=<?php echo urlencode((string)$item['cart_id']); ?>">
                      <span class="ion-ios-close"></span>
                    </a>
                  </td>
                  <td class="image-prod">
                    <div class="img" style="background-image:url('../images/<?php echo htmlspecialchars((string)($item['image_1'] ?? '')); ?>');"></div>
                  </td>
                  <td class="product-name">
                    <?php if (!empty($item['is_bundle'])): ?>
                      <h3><?php echo htmlspecialchars((string)$item['bundle_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                      <?php
                        $bundleEntries = json_decode((string)($item['bundle_items_json'] ?? '[]'), true);
                        if (!is_array($bundleEntries)) $bundleEntries = [];
                        $bundleTemplateId = 'bundleCustomizeTemplate-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$item['cart_id']);
                      ?>
                      <div class="mb-2">
                        <?php foreach ($bundleEntries as $entry): ?>
                          <?php
                            $entrySel = trim((string)($entry['selected_feature'] ?? ''));
                          ?>
                          <small class="d-block">
                            - <?php echo htmlspecialchars((string)($entry['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($entrySel !== ''): ?>
                              <span class="text-muted">(<?php echo htmlspecialchars($entrySel, ENT_QUOTES, 'UTF-8'); ?>)</span>
                            <?php endif; ?>
                          </small>
                        <?php endforeach; ?>
                      </div>
                      <button type="button"
                              class="btn btn-sm bundle-customize-btn js-open-bundle-customize"
                              data-template-id="<?php echo htmlspecialchars($bundleTemplateId, ENT_QUOTES, 'UTF-8'); ?>"
                              data-bundle-name="<?php echo htmlspecialchars((string)$item['bundle_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="ion-ios-color-wand mr-1"></span> Customize Bundle
                      </button>
                      <small class="text-muted d-block mt-1"><em>*If you don't select any varients, we will make it randomly</em></small>
                      <div id="<?php echo htmlspecialchars($bundleTemplateId, ENT_QUOTES, 'UTF-8'); ?>" class="bundle-customize-template">
                        <div class="text-left">
                          <?php foreach ($bundleEntries as $entry): ?>
                            <?php
                              $entryPid = trim((string)($entry['product_id'] ?? ''));
                              $entrySel = trim((string)($entry['selected_feature'] ?? ''));
                              $entryGroups = rtel_cart_variant_groups($conn, $entryPid);
                              $entryColorSel = rtel_cart_extract_variant_piece($entrySel, 'color');
                              $entryStorageSel = rtel_cart_extract_variant_piece($entrySel, 'storage');
                              $entryGenericSel = ($entryColorSel === '' && $entryStorageSel === '') ? $entrySel : '';
                            ?>
                            <div class="mb-3 pb-2 border-bottom">
                              <div class="font-weight-600 mb-2"><?php echo htmlspecialchars((string)($entry['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php if ($entryPid !== '' && (count($entryGroups['color']) > 0 || count($entryGroups['storage']) > 0 || count($entryGroups['generic']) > 0)): ?>
                                <div class="d-flex flex-wrap" style="gap:8px;">
                                  <?php if (count($entryGroups['color']) > 0): ?>
                                    <select class="form-control form-control-sm js-bundle-variant-cart js-bundle-color-cart bundle-variant-select"
                                            data-cart-bundle-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-product-id="<?php echo htmlspecialchars($entryPid, ENT_QUOTES, 'UTF-8'); ?>">
                                      <option value="">Select color</option>
                                      <?php foreach ($entryGroups['color'] as $opt): ?>
                                        <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($entryColorSel, (string)$opt) === 0 ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                      <?php endforeach; ?>
                                    </select>
                                  <?php endif; ?>
                                  <?php if (count($entryGroups['storage']) > 0): ?>
                                    <select class="form-control form-control-sm js-bundle-variant-cart js-bundle-storage-cart bundle-variant-select"
                                            data-cart-bundle-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-product-id="<?php echo htmlspecialchars($entryPid, ENT_QUOTES, 'UTF-8'); ?>">
                                      <option value="">Select storage</option>
                                      <?php foreach ($entryGroups['storage'] as $opt): ?>
                                        <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($entryStorageSel, (string)$opt) === 0 ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                      <?php endforeach; ?>
                                    </select>
                                  <?php endif; ?>
                                  <?php if (count($entryGroups['color']) === 0 && count($entryGroups['storage']) === 0 && count($entryGroups['generic']) > 0): ?>
                                    <select class="form-control form-control-sm js-bundle-variant-cart js-bundle-generic-cart bundle-variant-select"
                                            data-cart-bundle-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-product-id="<?php echo htmlspecialchars($entryPid, ENT_QUOTES, 'UTF-8'); ?>">
                                      <option value="">Select variant</option>
                                      <?php foreach ($entryGroups['generic'] as $opt): ?>
                                        <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($entryGenericSel, (string)$opt) === 0 ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                      <?php endforeach; ?>
                                    </select>
                                  <?php endif; ?>
                                </div>
                              <?php elseif ($entrySel !== ''): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($entrySel, ENT_QUOTES, 'UTF-8'); ?></small>
                              <?php else: ?>
                                <small class="text-muted">No customization needed for this item.</small>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php else: ?>
                      <?php
                        $cartPid = (string)$item['product_id'];
                        $cartVG = rtel_pv_variant_groups_cart($conn, $cartPid);
                        $cartSel = trim((string)($item['selected_feature'] ?? ''));
                        $cartColorSel = rtel_pv_extract_variant_piece($cartSel, 'color');
                        $cartStorageSel = rtel_pv_extract_variant_piece($cartSel, 'storage');
                        $cartGenericSel = ($cartColorSel === '' && $cartStorageSel === '') ? $cartSel : '';
                        $cartNeedsPick = rtel_pv_product_needs_variant_choice($conn, $cartPid);
                      ?>
                      <h3><a href="product.php?product_id=<?php echo urlencode((string)$item['product_id']); ?>"><?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                      <?php if ($cartNeedsPick): ?>
                        <div class="js-cart-variant-row mb-2 text-left d-inline-block" style="min-width:220px;" data-cart-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>" data-product-id="<?php echo htmlspecialchars($cartPid, ENT_QUOTES, 'UTF-8'); ?>">
                          <small class="text-muted d-block mb-1">Choose variant (required for checkout)</small>
                          <div class="d-flex flex-wrap justify-content-start" style="gap:6px;">
                            <?php if (count($cartVG['color']) > 0): ?>
                              <select class="form-control form-control-sm bundle-variant-select js-cart-line-variant js-cart-line-color">
                                <option value="">Color</option>
                                <?php foreach ($cartVG['color'] as $opt): ?>
                                  <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($cartColorSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <?php if (count($cartVG['storage']) > 0): ?>
                              <select class="form-control form-control-sm bundle-variant-select js-cart-line-variant js-cart-line-storage">
                                <option value="">Storage</option>
                                <?php foreach ($cartVG['storage'] as $opt): ?>
                                  <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($cartStorageSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <?php if (count($cartVG['color']) === 0 && count($cartVG['storage']) === 0 && count($cartVG['generic']) > 0): ?>
                              <select class="form-control form-control-sm bundle-variant-select js-cart-line-variant js-cart-line-generic">
                                <option value="">Variant</option>
                                <?php foreach ($cartVG['generic'] as $opt): ?>
                                  <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($cartGenericSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php elseif (!empty($item['selected_feature'])): ?>
                        <p class="mb-0"><small>Feature: <?php echo htmlspecialchars((string)$item['selected_feature'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                      <?php endif; ?>
                    <?php endif; ?>
                    <p>Added on <?php echo htmlspecialchars((string)$item['added_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                  </td>
                  <td class="price"><span class="js-unit-price cart-amount"><?php echo htmlspecialchars(format_rs((float)$item['unit_price']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td class="quantity">
                    <div class="d-flex justify-content-center align-items-center">
                      <button type="button" class="btn btn-sm btn-light <?php echo !empty($item['is_bundle']) ? 'js-bundle-qty-btn js-bundle-dec' : 'js-cart-qty-btn js-cart-dec'; ?>" data-cart-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>" data-stock="<?php echo (int)$item['stock_qty']; ?>">-</button>
                      <span class="mx-3 js-cart-qty" id="cart-qty-<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int)$item['quantity']; ?></span>
                      <button type="button" class="btn btn-sm btn-light <?php echo !empty($item['is_bundle']) ? 'js-bundle-qty-btn js-bundle-inc' : 'js-cart-qty-btn js-cart-inc'; ?>" data-cart-id="<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>" data-stock="<?php echo (int)$item['stock_qty']; ?>">+</button>
                    </div>
                    <?php if (empty($item['is_bundle'])): ?>
                      <small class="text-muted d-block mt-1">Stock: <?php echo (int)$item['stock_qty']; ?></small>
                    <?php else: ?>
                      <small class="text-muted d-block mt-1">Bundle</small>
                    <?php endif; ?>
                  </td>
                  <td class="total"><span class="js-line-total cart-amount" id="cart-total-<?php echo htmlspecialchars((string)$item['cart_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(format_rs((float)$item['line_total']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12 mt-4 cart-wrap ftco-animate cart-totals-wrap">
        <div class="cart-total mb-3">
          <h3>Cart Totals</h3>
          <p class="d-flex"><span>Subtotal</span><span id="cart-subtotal" class="cart-amount"><?php echo htmlspecialchars(format_rs($subtotal), ENT_QUOTES, 'UTF-8'); ?></span></p>
          <p class="d-flex"><span>Selected Total</span><span id="selected-total" class="cart-amount"><?php echo htmlspecialchars(format_rs(0), ENT_QUOTES, 'UTF-8'); ?></span></p>
          <p class="d-flex"><span>Delivery Charge</span><span id="cart-delivery" data-shipping="<?php echo htmlspecialchars((string)$shippingAmount, ENT_QUOTES, 'UTF-8'); ?>" class="cart-amount"><?php echo htmlspecialchars($deliveryDisplay, ENT_QUOTES, 'UTF-8'); ?></span></p>
          <?php if ($hasShippingArea): ?>
            <small class="text-muted d-block mb-2">Shipping area: <?php echo htmlspecialchars($shippingProvince . ($shippingDistrict !== "" ? " / " . $shippingDistrict : ""), ENT_QUOTES, 'UTF-8'); ?></small>
            <?php if ($freeDeliveryApplied): ?>
              <small class="text-success d-block mb-2"><?php echo htmlspecialchars($freeDeliveryReason, ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          <?php else: ?>
            <small class="text-muted d-block mb-2">Add an address in My Profile to calculate exact delivery charge.</small>
          <?php endif; ?>
          <hr>
          <p class="d-flex total-price"><span>Selectable Payment</span><span id="cart-grand-total" data-shipping-fee="<?php echo htmlspecialchars((string)$shippingAmount, ENT_QUOTES, 'UTF-8'); ?>" class="cart-amount"><?php echo htmlspecialchars(format_rs(0), ENT_QUOTES, 'UTF-8'); ?></span></p>
          <p class="text-right mb-2"><button type="submit" class="btn btn-black py-3 px-4 cart-checkout-btn">Proceed to Checkout</button></p>
          <small class="text-muted">Select one or more items before checkout.</small>
        </div>
      </div>
    </div>
    </form>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const selectAll = document.getElementById("selectAllCart");
  const checkboxes = Array.from(document.querySelectorAll(".js-cart-select"));
  const selectedTotalEl = document.getElementById("selected-total");
  const selectedPayableEl = document.getElementById("cart-grand-total");
  const deliveryEl = document.getElementById("cart-delivery");
  const form = document.getElementById("cartCheckoutForm");
  const selectableCheckboxes = function () {
    return checkboxes.filter(function (cb) { return !cb.disabled; });
  };

  const updateSelectedTotal = function () {
    let total = 0;
    checkboxes.forEach(function (cb) {
      if (cb.checked) {
        total += parseFloat(cb.getAttribute("data-line-total") || "0");
      }
    });
    if (selectedTotalEl) {
      selectedTotalEl.textContent = "Rs. " + Number(total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (selectedPayableEl) {
      const shipping = Number((deliveryEl && deliveryEl.getAttribute("data-shipping")) || "0");
      const payable = total > 0 ? (total + shipping) : 0;
      selectedPayableEl.textContent = "Rs. " + Number(payable).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (selectAll) {
      const avail = selectableCheckboxes();
      const checkedCount = avail.filter(function (cb) { return cb.checked; }).length;
      selectAll.checked = avail.length > 0 && checkedCount === avail.length;
    }
  };

  if (selectAll) {
    selectAll.addEventListener("change", function () {
      selectableCheckboxes().forEach(function (cb) { cb.checked = selectAll.checked; });
      updateSelectedTotal();
    });
  }

  checkboxes.forEach(function (cb) {
    cb.addEventListener("change", updateSelectedTotal);
  });

  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".js-open-bundle-customize");
    if (!btn) return;
    e.preventDefault();
    const templateId = String(btn.getAttribute("data-template-id") || "").trim();
    const bundleName = String(btn.getAttribute("data-bundle-name") || "Bundle").trim();
    const template = templateId ? document.getElementById(templateId) : null;
    if (!template || typeof Swal === "undefined") return;
    Swal.fire({
      title: "Customize " + bundleName,
      html: template.innerHTML,
      width: 860,
      showConfirmButton: true,
      confirmButtonText: "Done",
      showCloseButton: true,
      focusConfirm: false
    });
  });

  if (form) {
    form.addEventListener("submit", function (e) {
      const hasSelection = checkboxes.some(function (cb) { return cb.checked; });
      if (!hasSelection) {
        e.preventDefault();
        if (typeof Swal !== "undefined") {
          Swal.fire({ icon: "warning", title: "Please select at least one item." });
        } else {
          alert("Please select at least one item.");
        }
        return;
      }
      const badVariant = checkboxes.some(function (cb) {
        return cb.checked && cb.getAttribute("data-variant-required") === "1" && cb.getAttribute("data-variant-ok") !== "1";
      });
      if (badVariant) {
        e.preventDefault();
        if (typeof Swal !== "undefined") {
          Swal.fire({ icon: "warning", title: "Please select required variants before checkout." });
        } else {
          alert("Please select required variants before checkout.");
        }
      }
    });
  }
});
</script>

<?php require "footer.php"; ?>