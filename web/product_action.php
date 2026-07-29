<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "connection.php";
require_once __DIR__ . "/ai/personalization_engine.php";
require_once __DIR__ . "/includes/rtel_product_variants.php";

function ensure_column_if_missing($conn, $table, $column, $definition)
{
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return;
    }
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && $check->num_rows > 0) {
        return;
    }
    $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

function redirect_back($fallback = "index.php")
{
    $target = $_SERVER["HTTP_REFERER"] ?? $fallback;
    header("Location: " . $target);
    exit();
}

function respond_json($payload)
{
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload);
    exit();
}

function generate_id($prefix)
{
    return substr($prefix . strtoupper(uniqid()), 0, 10);
}

function get_user_counts($conn, $userId)
{
    $cartCount = 0;
    $wishlistCount = 0;

    $cartStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblcart WHERE cus_id = ?");
    if ($cartStmt) {
        $cartStmt->bind_param("s", $userId);
        $cartStmt->execute();
        $cartRow = $cartStmt->get_result()->fetch_assoc();
        $cartCount = (int)($cartRow["total"] ?? 0);
        $cartStmt->close();
    }
    $bundleStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblcart_bundle WHERE cus_id = ?");
    if ($bundleStmt) {
        $bundleStmt->bind_param("s", $userId);
        $bundleStmt->execute();
        $bundleRow = $bundleStmt->get_result()->fetch_assoc();
        $cartCount += (int)($bundleRow["total"] ?? 0);
        $bundleStmt->close();
    }

    $wishStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblwish_list WHERE cus_id = ?");
    if ($wishStmt) {
        $wishStmt->bind_param("s", $userId);
        $wishStmt->execute();
        $wishRow = $wishStmt->get_result()->fetch_assoc();
        $wishlistCount = (int)($wishRow["total"] ?? 0);
        $wishStmt->close();
    }

    return ["cart_count" => $cartCount, "wishlist_count" => $wishlistCount];
}

function ensure_price_alert_table($conn)
{
    // Table creation is managed outside runtime code.
    return;
}

function price_alert_table_exists($conn)
{
    static $exists = null;
    if ($exists !== null) return (bool)$exists;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'tblprice_alert'");
        $exists = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $exists = false;
    }
    return (bool)$exists;
}

function ensure_bundle_tables($conn)
{
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle (
        bundle_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        bundle_name VARCHAR(150) NOT NULL,
        bundle_model VARCHAR(120) NOT NULL DEFAULT '',
        bundle_image VARCHAR(255) NOT NULL DEFAULT '',
        bundle_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        expiry_date DATE NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    )");
    mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS bundle_model VARCHAR(120) NOT NULL DEFAULT '' AFTER bundle_name");
    mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS bundle_image VARCHAR(255) NOT NULL DEFAULT '' AFTER bundle_model");
    mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER bundle_price");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle_item (
        bundle_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        bundle_id INT UNSIGNED NOT NULL,
        product_id VARCHAR(20) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_bundle_item_bundle (bundle_id),
        INDEX idx_bundle_item_product (product_id)
    )");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcart_bundle (
        cart_bundle_id VARCHAR(20) NOT NULL PRIMARY KEY,
        cus_id VARCHAR(250) NOT NULL,
        bundle_id INT UNSIGNED NOT NULL,
        bundle_name VARCHAR(150) NOT NULL,
        bundle_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantity INT NOT NULL DEFAULT 1,
        selected_variants TEXT NULL,
        bundle_items_json TEXT NULL,
        added_date DATE NOT NULL,
        INDEX idx_cart_bundle_user (cus_id),
        INDEX idx_cart_bundle_bundle (bundle_id)
    )");
}

/**
 * Insert or increment a single-product cart line (used by add_cart and wishlist → cart).
 */
function rtel_add_product_to_cart_core($conn, $userId, $productId, $selectedFeature)
{
    $changed = false;
    $productId = trim((string)$productId);
    $selectedFeature = trim((string)$selectedFeature);
    if (strlen($selectedFeature) > 255) {
        $selectedFeature = substr($selectedFeature, 0, 255);
    }
    $productStmt = $conn->prepare("SELECT product_id, price FROM tblproduct WHERE product_id = ? AND status='1' LIMIT 1");
    if ($productStmt) {
        $productStmt->bind_param("s", $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        $productStmt->close();

        if ($product) {
            $existingStmt = $conn->prepare("SELECT cart_id, quantity FROM tblcart WHERE cus_id = ? AND product_id = ? AND selected_feature = ? LIMIT 1");
            if ($existingStmt) {
                $existingStmt->bind_param("sss", $userId, $productId, $selectedFeature);
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc();
                $existingStmt->close();

                if ($existing) {
                    $newQty = (int)$existing["quantity"] + 1;
                    $updateStmt = $conn->prepare("UPDATE tblcart SET quantity = ? WHERE cart_id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("is", $newQty, $existing["cart_id"]);
                        $updateStmt->execute();
                        $changed = true;
                        $updateStmt->close();
                    }
                } else {
                    $cartId = generate_id("C");
                    $qty = 1;
                    $price = (float)$product["price"];
                    $addedDate = date("Y-m-d");

                    $insertStmt = $conn->prepare("INSERT INTO tblcart (cart_id, cus_id, product_id, quantity, price, added_date, selected_feature) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        $insertStmt->bind_param("sssidss", $cartId, $userId, $productId, $qty, $price, $addedDate, $selectedFeature);
                        $insertStmt->execute();
                        $changed = true;
                        $insertStmt->close();
                    }
                }
                if ($changed) {
                    rtel_ai_track_behavior($conn, $userId, $productId, "add_cart");
                }
            }
        }
    }
    return $changed;
}

$action = $_GET["action"] ?? "";
$productId = isset($_GET["product_id"]) ? trim($_GET["product_id"]) : "";
$selectedFeature = trim((string)($_GET["selected_feature"] ?? ""));
if (strlen($selectedFeature) > 255) {
    $selectedFeature = substr($selectedFeature, 0, 255);
}
$userId = $_SESSION["user_id"] ?? "";
$isAjax = isset($_GET["ajax"]) && $_GET["ajax"] === "1";
$alertType = strtolower(trim((string)($_GET["alert_type"] ?? "")));
$targetPrice = isset($_GET["target_price"]) ? (float)$_GET["target_price"] : 0.0;

ensure_column_if_missing($conn, "tblcart", "selected_feature", "VARCHAR(255) NOT NULL DEFAULT ''");
ensure_column_if_missing($conn, "tblwish_list", "selected_feature", "VARCHAR(255) NOT NULL DEFAULT ''");
ensure_column_if_missing($conn, "tblorder_details", "selected_feature", "VARCHAR(255) NOT NULL DEFAULT ''");
ensure_bundle_tables($conn);

$authActions = ["add_cart", "add_bundle_cart", "add_wishlist", "add_bundle_wishlist", "remove_cart", "remove_bundle_cart", "remove_wishlist", "move_wishlist_to_cart", "subscribe_alert", "update_bundle_qty", "update_bundle_variants", "update_cart_feature", "update_wishlist_feature"];
if (in_array($action, $authActions, true) && $userId === "") {
    if ($isAjax) {
        respond_json(["success" => false, "message" => "Please register first.", "redirect" => "register.php"]);
    }
    header("Location: register.php");
    exit();
}

if ($action === "get_counts") {
    if ($userId === "") {
        respond_json(["success" => true, "cart_count" => 0, "wishlist_count" => 0]);
    }
    $counts = get_user_counts($conn, $userId);
    respond_json(["success" => true] + $counts);
}

if ($action === "subscribe_alert" && $productId !== "") {
    ensure_price_alert_table($conn);
    if (!price_alert_table_exists($conn)) {
        respond_json(["success" => false, "message" => "Price alerts are currently unavailable."]);
    }
    if (!in_array($alertType, ["price_drop", "restock"], true)) {
        respond_json(["success" => false, "message" => "Invalid alert type."]);
    }
    $productStmt = $conn->prepare("SELECT price FROM tblproduct WHERE product_id = ? AND status='1' LIMIT 1");
    if (!$productStmt) {
        respond_json(["success" => false, "message" => "Unable to set alert."]);
    }
    $productStmt->bind_param("s", $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();
    $productStmt->close();
    if (!$product) {
        respond_json(["success" => false, "message" => "Product not found."]);
    }
    $baselinePrice = (float)($product["price"] ?? 0);
    if ($alertType === "price_drop" && $targetPrice <= 0) {
        // Default target: notify when price drops at least 3%.
        $targetPrice = max(0.0, round($baselinePrice * 0.97, 2));
    }
    if ($alertType === "restock") {
        $targetPrice = 0.0;
    }
    $now = date("Y-m-d H:i:s");
    $sql = "INSERT INTO tblprice_alert (cus_id, product_id, alert_type, target_price, baseline_price, status, created_at, last_notified_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, NULL)
            ON DUPLICATE KEY UPDATE
                target_price = VALUES(target_price),
                baseline_price = VALUES(baseline_price),
                status = 1,
                created_at = VALUES(created_at),
                last_notified_at = NULL";
    $ins = $conn->prepare($sql);
    if (!$ins) {
        respond_json(["success" => false, "message" => "Unable to save alert."]);
    }
    $ins->bind_param("sssdds", $userId, $productId, $alertType, $targetPrice, $baselinePrice, $now);
    $ok = $ins->execute();
    $ins->close();
    if (!$ok) {
        respond_json(["success" => false, "message" => "Unable to save alert."]);
    }
    $msg = $alertType === "price_drop"
        ? ("Price alert enabled. We will email you when price is Rs. " . number_format($targetPrice, 2) . " or lower.")
        : "Restock alert enabled. We will email you when this product is back in stock.";
    $counts = get_user_counts($conn, $userId);
    respond_json(["success" => true, "message" => $msg] + $counts);
}

if ($action === "add_cart" && $productId !== "") {
    $changed = rtel_add_product_to_cart_core($conn, $userId, $productId, $selectedFeature);
    if ($isAjax) {
        $counts = get_user_counts($conn, $userId);
        respond_json([
            "success" => $changed,
            "message" => $changed ? "Added to cart successfully." : "Unable to add to cart.",
        ] + $counts);
    }
    redirect_back();
}

if ($action === "add_bundle_cart") {
    $bundleId = (int)($_GET["bundle_id"] ?? 0);
    $bundleVariantsRaw = trim((string)($_GET["bundle_variants"] ?? ""));
    $bundleVariants = [];
    if ($bundleVariantsRaw !== "") {
        $decoded = json_decode($bundleVariantsRaw, true);
        if (is_array($decoded)) {
            $bundleVariants = $decoded;
        }
    }
    if ($bundleId <= 0) {
        respond_json(["success" => false, "message" => "Invalid bundle."]);
    }
    $bundleStmt = $conn->prepare("SELECT bundle_id, bundle_name, bundle_price FROM tblbundle
                                  WHERE bundle_id = ? AND status = 1
                                    AND (expiry_date IS NULL OR expiry_date = '' OR expiry_date >= CURDATE())
                                  LIMIT 1");
    if (!$bundleStmt) {
        respond_json(["success" => false, "message" => "Unable to add bundle."]);
    }
    $bundleStmt->bind_param("i", $bundleId);
    $bundleStmt->execute();
    $bundleRow = $bundleStmt->get_result()->fetch_assoc();
    $bundleStmt->close();
    if (!$bundleRow) {
        respond_json(["success" => false, "message" => "Bundle not found."]);
    }
    $items = [];
    $itemStmt = $conn->prepare("SELECT bi.product_id, COALESCE(p.name, CONCAT('Item ', bi.product_id)) AS name
                                FROM tblbundle_item bi
                                LEFT JOIN tblproduct p ON p.product_id = bi.product_id
                                WHERE bi.bundle_id = ?
                                ORDER BY bi.sort_order ASC, bi.bundle_item_id ASC");
    if ($itemStmt) {
        $itemStmt->bind_param("i", $bundleId);
        $itemStmt->execute();
        $res = $itemStmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $pid = (string)($row["product_id"] ?? "");
            if ($pid === "") continue;
            $items[] = [
                "product_id" => $pid,
                "name" => (string)($row["name"] ?? ""),
                "selected_feature" => isset($bundleVariants[$pid]) ? trim((string)$bundleVariants[$pid]) : ""
            ];
        }
        $itemStmt->close();
    }
    if (count($items) === 0) {
        respond_json(["success" => false, "message" => "Bundle has no products."]);
    }
    $existingStmt = $conn->prepare("SELECT cart_bundle_id, quantity FROM tblcart_bundle WHERE cus_id = ? AND bundle_id = ? LIMIT 1");
    $changed = false;
    if ($existingStmt) {
        $existingStmt->bind_param("si", $userId, $bundleId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();
        if ($existing) {
            $newQty = (int)$existing["quantity"] + 1;
            $upd = $conn->prepare("UPDATE tblcart_bundle SET quantity = ?, selected_variants = ?, bundle_items_json = ? WHERE cart_bundle_id = ? AND cus_id = ?");
            if ($upd) {
                $variantsJson = json_encode($bundleVariants);
                $itemsJson = json_encode($items);
                $upd->bind_param("issss", $newQty, $variantsJson, $itemsJson, $existing["cart_bundle_id"], $userId);
                $upd->execute();
                $upd->close();
                $changed = true;
            }
        } else {
            $cartBundleId = generate_id("CB");
            $qty = 1;
            $addedDate = date("Y-m-d");
            $ins = $conn->prepare("INSERT INTO tblcart_bundle (cart_bundle_id, cus_id, bundle_id, bundle_name, bundle_price, quantity, selected_variants, bundle_items_json, added_date)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($ins) {
                $bundleName = (string)$bundleRow["bundle_name"];
                $bundlePrice = (float)$bundleRow["bundle_price"];
                $variantsJson = json_encode($bundleVariants);
                $itemsJson = json_encode($items);
                $ins->bind_param("ssisdisss", $cartBundleId, $userId, $bundleId, $bundleName, $bundlePrice, $qty, $variantsJson, $itemsJson, $addedDate);
                $ins->execute();
                $ins->close();
                $changed = true;
            }
        }
    }
    $counts = get_user_counts($conn, $userId);
    respond_json([
        "success" => $changed,
        "message" => $changed ? "Bundle added to cart." : "Unable to add bundle."
    ] + $counts);
}

if ($action === "add_wishlist" && $productId !== "") {
    $changed = false;
    $alreadyAdded = false;
    $productStmt = $conn->prepare("SELECT product_id FROM tblproduct WHERE product_id = ? AND status='1' LIMIT 1");
    if ($productStmt) {
        $productStmt->bind_param("s", $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        $productStmt->close();

        if ($product) {
            $existingStmt = $conn->prepare("SELECT wishlist_id FROM tblwish_list WHERE cus_id = ? AND product_id = ? AND selected_feature = ? LIMIT 1");
            if ($existingStmt) {
                $existingStmt->bind_param("sss", $userId, $productId, $selectedFeature);
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc();
                $existingStmt->close();

                if (!$existing) {
                    $wishlistId = generate_id("W");
                    $addedDate = date("Y-m-d");
                    $insertStmt = $conn->prepare("INSERT INTO tblwish_list (wishlist_id, cus_id, product_id, added_date, selected_feature) VALUES (?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        $insertStmt->bind_param("sssss", $wishlistId, $userId, $productId, $addedDate, $selectedFeature);
                        $insertStmt->execute();
                        $changed = true;
                        $insertStmt->close();
                    }
                    if ($changed) {
                        rtel_ai_track_behavior($conn, $userId, $productId, "wishlist");
                    }
                } else {
                    $alreadyAdded = true;
                }
            }
        }
    }
    if ($isAjax) {
        $counts = get_user_counts($conn, $userId);
        respond_json([
            "success" => ($changed || $alreadyAdded),
            "message" => $alreadyAdded ? "Already in wishlist." : ($changed ? "Added to wishlist successfully." : "Unable to add to wishlist."),
        ] + $counts);
    }
    redirect_back();
}

if ($action === "add_bundle_wishlist") {
    $bundleId = (int)($_GET["bundle_id"] ?? 0);
    if ($bundleId <= 0) {
        respond_json(["success" => false, "message" => "Invalid bundle."]);
    }
    $itemStmt = $conn->prepare("SELECT bi.product_id
                                FROM tblbundle_item bi
                                JOIN tblproduct p ON p.product_id = bi.product_id AND p.status = '1'
                                WHERE bi.bundle_id = ?
                                ORDER BY bi.sort_order ASC, bi.bundle_item_id ASC");
    if (!$itemStmt) {
        respond_json(["success" => false, "message" => "Unable to add to wishlist."]);
    }
    $itemStmt->bind_param("i", $bundleId);
    $itemStmt->execute();
    $res = $itemStmt->get_result();
    $productIds = [];
    while ($res && $row = $res->fetch_assoc()) {
        $pid = trim((string)($row["product_id"] ?? ""));
        if ($pid !== '') $productIds[] = $pid;
    }
    $itemStmt->close();
    if (count($productIds) === 0) {
        respond_json(["success" => false, "message" => "Bundle has no active products."]);
    }
    $inserted = 0;
    $addedDate = date("Y-m-d");
    foreach ($productIds as $pid) {
        $existsStmt = $conn->prepare("SELECT wishlist_id FROM tblwish_list WHERE cus_id = ? AND product_id = ? AND selected_feature = '' LIMIT 1");
        if (!$existsStmt) continue;
        $existsStmt->bind_param("ss", $userId, $pid);
        $existsStmt->execute();
        $exists = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();
        if ($exists) continue;
        $wishlistId = generate_id("W");
        $ins = $conn->prepare("INSERT INTO tblwish_list (wishlist_id, cus_id, product_id, added_date, selected_feature) VALUES (?, ?, ?, ?, '')");
        if ($ins) {
            $ins->bind_param("ssss", $wishlistId, $userId, $pid, $addedDate);
            $ins->execute();
            $ins->close();
            $inserted++;
            rtel_ai_track_behavior($conn, $userId, $pid, "wishlist");
        }
    }
    $counts = get_user_counts($conn, $userId);
    respond_json([
        "success" => true,
        "message" => $inserted > 0 ? "Bundle products added to wishlist." : "All bundle products are already in wishlist."
    ] + $counts);
}

if ($action === "remove_cart" && isset($_GET["cart_id"])) {
    $cartId = trim($_GET["cart_id"]);
    $deleteStmt = $conn->prepare("DELETE FROM tblcart WHERE cart_id = ? AND cus_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("ss", $cartId, $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    redirect_back("cart.php");
}

if ($action === "remove_bundle_cart" && isset($_GET["cart_bundle_id"])) {
    $cartBundleId = trim((string)$_GET["cart_bundle_id"]);
    $deleteStmt = $conn->prepare("DELETE FROM tblcart_bundle WHERE cart_bundle_id = ? AND cus_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("ss", $cartBundleId, $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    redirect_back("cart.php");
}

if ($action === "remove_wishlist" && isset($_GET["wishlist_id"])) {
    $wishlistId = trim($_GET["wishlist_id"]);
    $deleteStmt = $conn->prepare("DELETE FROM tblwish_list WHERE wishlist_id = ? AND cus_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("ss", $wishlistId, $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    redirect_back("wishlist.php");
}

if ($action === "move_wishlist_to_cart" && isset($_GET["wishlist_id"])) {
    $wishlistId = trim($_GET["wishlist_id"]);
    $wishStmt = $conn->prepare("SELECT product_id, COALESCE(selected_feature, '') AS selected_feature FROM tblwish_list WHERE wishlist_id = ? AND cus_id = ? LIMIT 1");
    if ($wishStmt) {
        $wishStmt->bind_param("ss", $wishlistId, $userId);
        $wishStmt->execute();
        $wishRow = $wishStmt->get_result()->fetch_assoc();
        $wishStmt->close();
        if ($wishRow) {
            $productId = (string)$wishRow["product_id"];
            $wishFeature = trim((string)($wishRow["selected_feature"] ?? ""));
            if (strlen($wishFeature) > 255) {
                $wishFeature = substr($wishFeature, 0, 255);
            }

            if (!rtel_pv_variant_selection_complete($conn, $productId, $wishFeature)) {
                if ($isAjax) {
                    respond_json(["success" => false, "message" => "Please select all required variants before adding to cart."]);
                }
                redirect_back("wishlist.php");
            }

            $deleteWishStmt = $conn->prepare("DELETE FROM tblwish_list WHERE wishlist_id = ? AND cus_id = ?");
            if ($deleteWishStmt) {
                $deleteWishStmt->bind_param("ss", $wishlistId, $userId);
                $deleteWishStmt->execute();
                $deleteWishStmt->close();
            }

            $changed = rtel_add_product_to_cart_core($conn, $userId, $productId, $wishFeature);
            if ($isAjax) {
                $counts = get_user_counts($conn, $userId);
                respond_json([
                    "success" => $changed,
                    "message" => $changed ? "Moved to cart." : "Unable to add to cart.",
                ] + $counts);
            }
            header("Location: cart.php");
            exit();
        }
    }
    redirect_back("wishlist.php");
}

if ($action === "update_cart_qty" && isset($_GET["cart_id"]) && isset($_GET["quantity"])) {
    $cartId = trim($_GET["cart_id"]);
    $requestedQty = (int)$_GET["quantity"];
    if ($requestedQty < 1) {
        $requestedQty = 1;
    }

    $itemStmt = $conn->prepare(
        "SELECT c.cart_id, c.product_id, p.price, p.quantity AS stock_qty
         FROM tblcart c
         JOIN tblproduct p ON c.product_id = p.product_id
         WHERE c.cart_id = ? AND c.cus_id = ?
         LIMIT 1"
    );
    if (!$itemStmt) {
        if ($isAjax) {
            respond_json(["success" => false, "message" => "Unable to update quantity."]);
        }
        redirect_back("cart.php");
    }

    $itemStmt->bind_param("ss", $cartId, $userId);
    $itemStmt->execute();
    $item = $itemStmt->get_result()->fetch_assoc();
    $itemStmt->close();

    if (!$item) {
        if ($isAjax) {
            respond_json(["success" => false, "message" => "Cart item not found."]);
        }
        redirect_back("cart.php");
    }

    $stockQty = max(0, (int)$item["stock_qty"]);
    if ($stockQty < 1) {
        if ($isAjax) {
            respond_json(["success" => false, "message" => "This product is out of stock."]);
        }
        redirect_back("cart.php");
    }

    $finalQty = min($requestedQty, $stockQty);
    $unitPrice = (float)$item["price"];
    $updateStmt = $conn->prepare("UPDATE tblcart SET quantity = ?, price = ? WHERE cart_id = ? AND cus_id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("idss", $finalQty, $unitPrice, $cartId, $userId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $subTotal = 0.0;
    $sumStmt = $conn->prepare(
        "SELECT SUM(c.quantity * p.price) AS subtotal
         FROM tblcart c
         JOIN tblproduct p ON c.product_id = p.product_id
         WHERE c.cus_id = ?"
    );
    if ($sumStmt) {
        $sumStmt->bind_param("s", $userId);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc();
        $subTotal = (float)($sumRow["subtotal"] ?? 0);
        $sumStmt->close();
    }
    $bundleSumStmt = $conn->prepare("SELECT SUM(quantity * bundle_price) AS subtotal FROM tblcart_bundle WHERE cus_id = ?");
    if ($bundleSumStmt) {
        $bundleSumStmt->bind_param("s", $userId);
        $bundleSumStmt->execute();
        $bundleRow = $bundleSumStmt->get_result()->fetch_assoc();
        $subTotal += (float)($bundleRow["subtotal"] ?? 0);
        $bundleSumStmt->close();
    }

    if ($isAjax) {
        $lineTotal = $finalQty * $unitPrice;
        $message = $finalQty < $requestedQty
            ? "Maximum available stock is " . $stockQty . "."
            : "Cart quantity updated.";
        respond_json([
            "success" => true,
            "message" => $message,
            "quantity" => $finalQty,
            "stock_qty" => $stockQty,
            "unit_price" => $unitPrice,
            "line_total" => $lineTotal,
            "subtotal" => $subTotal
        ]);
    }
    redirect_back("cart.php");
}

if ($action === "update_bundle_qty" && isset($_GET["cart_bundle_id"]) && isset($_GET["quantity"])) {
    $cartBundleId = trim((string)$_GET["cart_bundle_id"]);
    $requestedQty = (int)$_GET["quantity"];
    if ($requestedQty < 1) $requestedQty = 1;

    $bundleStmt = $conn->prepare("SELECT cart_bundle_id, bundle_price FROM tblcart_bundle WHERE cart_bundle_id = ? AND cus_id = ? LIMIT 1");
    if (!$bundleStmt) {
        respond_json(["success" => false, "message" => "Unable to update bundle quantity."]);
    }
    $bundleStmt->bind_param("ss", $cartBundleId, $userId);
    $bundleStmt->execute();
    $bundle = $bundleStmt->get_result()->fetch_assoc();
    $bundleStmt->close();
    if (!$bundle) {
        respond_json(["success" => false, "message" => "Bundle not found."]);
    }
    $upd = $conn->prepare("UPDATE tblcart_bundle SET quantity = ? WHERE cart_bundle_id = ? AND cus_id = ?");
    if ($upd) {
        $upd->bind_param("iss", $requestedQty, $cartBundleId, $userId);
        $upd->execute();
        $upd->close();
    }
    $subTotal = 0.0;
    $sumStmt = $conn->prepare("SELECT SUM(c.quantity * p.price) AS subtotal
                               FROM tblcart c
                               JOIN tblproduct p ON c.product_id = p.product_id
                               WHERE c.cus_id = ?");
    if ($sumStmt) {
        $sumStmt->bind_param("s", $userId);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc();
        $subTotal += (float)($sumRow["subtotal"] ?? 0);
        $sumStmt->close();
    }
    $bundleSumStmt = $conn->prepare("SELECT SUM(quantity * bundle_price) AS subtotal FROM tblcart_bundle WHERE cus_id = ?");
    if ($bundleSumStmt) {
        $bundleSumStmt->bind_param("s", $userId);
        $bundleSumStmt->execute();
        $bundleRow = $bundleSumStmt->get_result()->fetch_assoc();
        $subTotal += (float)($bundleRow["subtotal"] ?? 0);
        $bundleSumStmt->close();
    }
    $lineTotal = $requestedQty * (float)$bundle["bundle_price"];
    respond_json([
        "success" => true,
        "message" => "Bundle quantity updated.",
        "quantity" => $requestedQty,
        "line_total" => $lineTotal,
        "subtotal" => $subTotal
    ]);
}

if ($action === "update_bundle_variants" && isset($_GET["cart_bundle_id"])) {
    $cartBundleId = trim((string)$_GET["cart_bundle_id"]);
    $variantsRaw = trim((string)($_GET["bundle_variants"] ?? ""));
    $variants = [];
    if ($variantsRaw !== '') {
        $decoded = json_decode($variantsRaw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $pid => $val) {
                $k = trim((string)$pid);
                $v = trim((string)$val);
                if ($k !== '') $variants[$k] = mb_substr($v, 0, 255);
            }
        }
    }
    $rowStmt = $conn->prepare("SELECT bundle_items_json FROM tblcart_bundle WHERE cart_bundle_id = ? AND cus_id = ? LIMIT 1");
    if (!$rowStmt) {
        respond_json(["success" => false, "message" => "Unable to update variants."]);
    }
    $rowStmt->bind_param("ss", $cartBundleId, $userId);
    $rowStmt->execute();
    $row = $rowStmt->get_result()->fetch_assoc();
    $rowStmt->close();
    if (!$row) {
        respond_json(["success" => false, "message" => "Bundle not found."]);
    }
    $items = json_decode((string)($row["bundle_items_json"] ?? "[]"), true);
    if (!is_array($items)) $items = [];
    foreach ($items as &$it) {
        $pid = trim((string)($it["product_id"] ?? ""));
        if ($pid === '') continue;
        $it["selected_feature"] = isset($variants[$pid]) ? $variants[$pid] : "";
    }
    unset($it);
    $variantsJson = json_encode($variants);
    $itemsJson = json_encode($items);
    $upd = $conn->prepare("UPDATE tblcart_bundle SET selected_variants = ?, bundle_items_json = ? WHERE cart_bundle_id = ? AND cus_id = ?");
    if (!$upd) {
        respond_json(["success" => false, "message" => "Unable to update variants."]);
    }
    $upd->bind_param("ssss", $variantsJson, $itemsJson, $cartBundleId, $userId);
    $ok = $upd->execute();
    $upd->close();
    respond_json([
        "success" => $ok,
        "message" => $ok ? "Bundle variants updated." : "Unable to update variants."
    ]);
}

if ($action === "update_cart_feature" && isset($_GET["cart_id"])) {
    $cartId = trim((string)$_GET["cart_id"]);
    $feature = trim((string)($_GET["selected_feature"] ?? ""));
    if (strlen($feature) > 255) {
        $feature = substr($feature, 0, 255);
    }
    $rowStmt = $conn->prepare("SELECT c.product_id FROM tblcart c WHERE c.cart_id = ? AND c.cus_id = ? LIMIT 1");
    if (!$rowStmt) {
        respond_json(["success" => false, "message" => "Unable to update variant."]);
    }
    $rowStmt->bind_param("ss", $cartId, $userId);
    $rowStmt->execute();
    $row = $rowStmt->get_result()->fetch_assoc();
    $rowStmt->close();
    if (!$row) {
        respond_json(["success" => false, "message" => "Cart item not found."]);
    }
    $pid = trim((string)($row["product_id"] ?? ""));
    if ($pid === "") {
        respond_json(["success" => false, "message" => "Invalid product."]);
    }
    if (!rtel_pv_variant_selection_complete($conn, $pid, $feature)) {
        respond_json(["success" => false, "message" => "Please choose all required variant options."]);
    }
    $upd = $conn->prepare("UPDATE tblcart SET selected_feature = ? WHERE cart_id = ? AND cus_id = ?");
    if (!$upd) {
        respond_json(["success" => false, "message" => "Unable to update variant."]);
    }
    $upd->bind_param("sss", $feature, $cartId, $userId);
    $ok = $upd->execute();
    $upd->close();
    respond_json([
        "success" => $ok,
        "message" => $ok ? "Variant saved." : "Unable to update variant.",
        "variant_complete" => true,
    ]);
}

if ($action === "update_wishlist_feature" && isset($_GET["wishlist_id"])) {
    $wishlistId = trim((string)$_GET["wishlist_id"]);
    $feature = trim((string)($_GET["selected_feature"] ?? ""));
    if (strlen($feature) > 255) {
        $feature = substr($feature, 0, 255);
    }
    $rowStmt = $conn->prepare("SELECT w.product_id FROM tblwish_list w WHERE w.wishlist_id = ? AND w.cus_id = ? LIMIT 1");
    if (!$rowStmt) {
        respond_json(["success" => false, "message" => "Unable to update variant."]);
    }
    $rowStmt->bind_param("ss", $wishlistId, $userId);
    $rowStmt->execute();
    $row = $rowStmt->get_result()->fetch_assoc();
    $rowStmt->close();
    if (!$row) {
        respond_json(["success" => false, "message" => "Wishlist item not found."]);
    }
    $pid = trim((string)($row["product_id"] ?? ""));
    if ($pid === "") {
        respond_json(["success" => false, "message" => "Invalid product."]);
    }
    if (!rtel_pv_variant_selection_complete($conn, $pid, $feature)) {
        respond_json(["success" => false, "message" => "Please choose all required variant options."]);
    }
    $upd = $conn->prepare("UPDATE tblwish_list SET selected_feature = ? WHERE wishlist_id = ? AND cus_id = ?");
    if (!$upd) {
        respond_json(["success" => false, "message" => "Unable to update variant."]);
    }
    $upd->bind_param("sss", $feature, $wishlistId, $userId);
    $ok = $upd->execute();
    $upd->close();
    respond_json([
        "success" => $ok,
        "message" => $ok ? "Variant saved." : "Unable to update variant.",
        "variant_complete" => true,
    ]);
}

if ($isAjax) {
    respond_json(["success" => false, "message" => "Invalid action."]);
}

redirect_back();
?>
