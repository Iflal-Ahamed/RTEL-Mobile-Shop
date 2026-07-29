<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "connection.php";
require_once "mail/mail_notifications.php";

function is_ajax_request()
{
    $xrw = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($xrw === 'xmlhttprequest') return true;
    return (string)($_POST['ajax'] ?? '') === '1';
}

function ajax_json($success, $message)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message
    ]);
    exit();
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_rs($amount)
{
    return "Rs. " . number_format((float)$amount, 2);
}

function format_order_payment_customer_line(array $row)
{
    $method = strtolower(trim((string)($row['payment_method'] ?? '')));
    $pst = strtolower(trim((string)($row['payment_status'] ?? '')));
    $sr = strtoupper(trim((string)($row['status'] ?? 'PENDING')));

    if ($method === '') {
        return 'Payment: not recorded';
    }
    if ($method === 'stripe') {
        $line = 'Card / online';
        return 'Payment: ' . $line . ($pst !== '' ? ' — ' . ucfirst($pst) : '');
    }
    if ($method === 'cod') {
        if ($pst === 'paid') {
            return 'Payment: COD — Paid (cash collected)';
        }
        if (in_array($sr, ['PENDING', 'ACCEPTED'], true)) {
            return 'Payment: COD — Pending (pay cash when order is delivered)';
        }
        if (in_array($sr, ['ON THE WAY', 'ON_THE_WAY', 'SHIPPED', 'ON-WAY'], true)) {
            return 'Payment: COD — Pending (pay on delivery)';
        }
        if (in_array($sr, ['DELIVERED', 'COMPLETED'], true)) {
            return 'Payment: COD — Pending (shop will confirm collection)';
        }
        return 'Payment: COD — Pending';
    }
    return 'Payment: ' . strtoupper($method) . ($pst !== '' ? ' — ' . ucfirst($pst) : '');
}

function order_charge_breakdown_html($row)
{
    $couponCode = trim((string)($row['coupon_code'] ?? ''));
    $couponDiscount = (float)($row['coupon_discount'] ?? 0);
    $specialLabel = trim((string)($row['special_discount_label'] ?? ''));
    $specialDiscount = (float)($row['special_discount'] ?? 0);
    $shippingFee = (float)($row['shipping_fee'] ?? 0);
    $grandTotal = (float)($row['grand_total'] ?? 0);
    $lineTotal = ((float)($row['unitprice'] ?? 0) * (int)($row['quantity'] ?? 0));
    $displayTotal = $grandTotal > 0 ? $grandTotal : $lineTotal;
    $html = "<details class='order-billing mt-1'>"
        . "<summary>Billing details</summary>"
        . "<div class='order-billing-panel'>"
        . "<div>Order Total: " . h(format_rs($displayTotal)) . "</div>"
        . "<div>Coupon: " . h($couponCode !== "" ? $couponCode : "-") . " (" . h(format_rs($couponDiscount)) . ")</div>"
        . "<div>Special: " . h($specialLabel !== "" ? $specialLabel : "None") . " (" . h(format_rs($specialDiscount)) . ")</div>"
        . "<div>Delivery: " . h(format_rs($shippingFee)) . "</div>"
        . "<div><strong>Order Grand Total: " . h(format_rs($grandTotal)) . "</strong></div>"
        . "</div></details>";
    return $html;
}

$userId = (string)$_SESSION['user_id'];
$reviewMessage = $_SESSION['review_message'] ?? "";
$reviewError = $_SESSION['review_error'] ?? "";
unset($_SESSION['review_message'], $_SESSION['review_error']);

mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER ordered_date");
mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status_reason VARCHAR(255) NULL AFTER status");
mysqli_query($conn, "UPDATE tblorder SET status = 'Pending' WHERE status IS NULL OR status = ''");
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

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblratings (
    rating_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cus_id INT(11) NOT NULL,
    orderdetails_id VARCHAR(10) NOT NULL,
    order_id VARCHAR(10) NOT NULL,
    product_id VARCHAR(10) NOT NULL,
    rating INT(2) NOT NULL,
    review_text VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_review') {
    $productId = trim((string)($_POST['product_id'] ?? ''));
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    $orderDetailsId = trim((string)($_POST['orderdetails_id'] ?? ''));
    $rating = (int)($_POST['rating'] ?? 0);
    $reviewText = trim((string)($_POST['review_text'] ?? ''));

    $isAjax = is_ajax_request();
    $ok = false;
    $msg = "";
    if ($productId === "" || $orderId === "" || $orderDetailsId === "") {
        $_SESSION['review_error'] = "Invalid review request.";
        $msg = "Invalid review request.";
    } elseif ($rating < 1 || $rating > 5) {
        $_SESSION['review_error'] = "Please select star rating between 1 and 5.";
        $msg = "Please select star rating between 1 and 5.";
    } elseif ($reviewText === "") {
        $_SESSION['review_error'] = "Please enter your review.";
        $msg = "Please enter your review.";
    } else {
        $checkStmt = $conn->prepare("SELECT rating_id FROM tblratings WHERE cus_id = ? AND orderdetails_id = ? LIMIT 1");
        $existing = null;
        if ($checkStmt) {
            $checkStmt->bind_param("ss", $userId, $orderDetailsId);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
        }
        if ($existing) {
            $_SESSION['review_error'] = "You already rated this item. Use My Ratings to update it.";
            $msg = "You already rated this item. Use My Ratings to update it.";
        } else {
            $createdAt = date("Y-m-d H:i:s");
            $insert = $conn->prepare("INSERT INTO tblratings (cus_id, orderdetails_id, order_id, product_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($insert) {
                $insert->bind_param("ssssiss", $userId, $orderDetailsId, $orderId, $productId, $rating, $reviewText, $createdAt);
                if ($insert->execute()) {
                    $_SESSION['review_message'] = "Thank you! Your rating and review were submitted.";
                    $ok = true;
                    $msg = "Thank you! Your rating and review were submitted.";
                    $productNameForMail = "Product #" . $productId;
                    $productNameStmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(name), ''), ?) AS product_name FROM tblproduct WHERE product_id = ? LIMIT 1");
                    if ($productNameStmt) {
                        $productNameStmt->bind_param("ss", $productNameForMail, $productId);
                        $productNameStmt->execute();
                        $productNameRow = $productNameStmt->get_result()->fetch_assoc();
                        $productNameStmt->close();
                        if ($productNameRow) {
                            $productNameForMail = (string)($productNameRow['product_name'] ?? $productNameForMail);
                        }
                    }
                    rtel_notify_admin_new_rating($conn, $orderId, $productNameForMail, $rating, $reviewText);
                } else {
                    $_SESSION['review_error'] = "Unable to submit review right now.";
                    $msg = "Unable to submit review right now.";
                }
                $insert->close();
            } else {
                $_SESSION['review_error'] = "Unable to submit review right now.";
                $msg = "Unable to submit review right now.";
            }
        }
    }
    if ($isAjax) {
        if ($ok) {
            ajax_json(true, $msg);
        }
        if ($msg === "") $msg = (string)($_SESSION['review_error'] ?? "Unable to submit review.");
        ajax_json(false, $msg);
    }
    header("Location: my_orders.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'delete_order') {
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    $isAjax = is_ajax_request();
    $ok = false;
    $msg = "";
    if ($orderId === "") {
        $_SESSION['review_error'] = "Invalid order request.";
        $msg = "Invalid order request.";
    } else {
        $checkStatusStmt = $conn->prepare("SELECT status FROM tblorder WHERE order_id = ? AND cus_id = ? LIMIT 1");
        $currentStatus = "";
        if ($checkStatusStmt) {
            $checkStatusStmt->bind_param("ss", $orderId, $userId);
            $checkStatusStmt->execute();
            $row = $checkStatusStmt->get_result()->fetch_assoc();
            $currentStatus = strtoupper(trim((string)($row['status'] ?? '')));
            $checkStatusStmt->close();
        }
        if (in_array($currentStatus, ["ACCEPTED", "ON THE WAY", "ON_THE_WAY", "SHIPPED", "ON-WAY", "DELIVERED", "COMPLETED"], true)) {
            $_SESSION['review_error'] = "This order cannot be deleted now because shipping has started.";
            $msg = "This order cannot be deleted now because shipping has started.";
        } elseif ($currentStatus === "") {
            $_SESSION['review_error'] = "Order not found.";
            $msg = "Order not found.";
        } else {
            $deleteReason = "You deleted the order";
            $deleteStmt = $conn->prepare("UPDATE tblorder SET status = 'Deleted', status_reason = ? WHERE order_id = ? AND cus_id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("sss", $deleteReason, $orderId, $userId);
                if ($deleteStmt->execute()) {
                    $_SESSION['review_message'] = "Order deleted successfully.";
                    $ok = true;
                    $msg = "Order deleted successfully.";
                } else {
                    $_SESSION['review_error'] = "Unable to delete order.";
                    $msg = "Unable to delete order.";
                }
                $deleteStmt->close();
            }
        }
    }
    if ($isAjax) {
        if ($ok) {
            ajax_json(true, $msg);
        }
        if ($msg === "") $msg = (string)($_SESSION['review_error'] ?? "Unable to delete order.");
        ajax_json(false, $msg);
    }
    header("Location: my_orders.php");
    exit();
}

$allOrders = [];
$sql = "SELECT o.order_id, o.ordered_date, o.status, o.status_reason, od.orderdetails_id, od.product_id, od.quantity, od.unitprice, od.selected_feature,
               p.name AS product_name, i.image_1, pay.method AS payment_method, pay.payment_status,
               COALESCE(ch.coupon_code, '') AS coupon_code,
               COALESCE(ch.coupon_discount, 0) AS coupon_discount,
               COALESCE(ch.special_discount_label, '') AS special_discount_label,
               COALESCE(ch.special_discount, 0) AS special_discount,
               COALESCE(ch.shipping_fee, 0) AS shipping_fee,
               COALESCE(ch.grand_total, 0) AS grand_total
        FROM tblorder o
        JOIN tblorder_details od ON o.order_id = od.order_id
        LEFT JOIN tblproduct p ON od.product_id = p.product_id
        LEFT JOIN tblimage i ON od.product_id = i.product_id
        LEFT JOIN tblpayment pay ON o.order_id = pay.order_id
        LEFT JOIN tblorder_charge ch ON o.order_id = ch.order_id
        WHERE o.cus_id = ?
        ORDER BY o.ordered_date DESC, o.order_id DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $allOrders[] = $row;
    }
    $stmt->close();
}

$processingOrders = [];
$onWayOrders = [];
$completedOrders = [];
$canceledOrders = [];

foreach ($allOrders as $order) {
    $status = strtoupper(trim((string)($order['status'] ?? 'PENDING')));
    if ($status === "PENDING" || $status === "ACCEPTED") {
        $processingOrders[] = $order;
        continue;
    }
    if ($status === "ON THE WAY" || $status === "ON_THE_WAY" || $status === "SHIPPED" || $status === "ON-WAY") {
        $onWayOrders[] = $order;
        continue;
    }
    if ($status === "DELIVERED" || $status === "COMPLETED") {
        $completedOrders[] = $order;
        continue;
    }
    if ($status === "DELETED" || $status === "REJECTED") {
        $canceledOrders[] = $order;
        continue;
    }
    $processingOrders[] = $order;
}

require "header.php";
?>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs">
          <span class="mr-2"><a href="index.php">Home</a></span>
          <span class="mr-2"><a href="my_profile.php">My Profile</a></span>
          <span>My Orders</span>
        </p>
        <h1 class="mb-0 bread">My Orders</h1>
      </div>
    </div>
  </div>
</div>

<style>
  .profile-actions {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-top: 20px;
  }
  .profile-actions a {
    color: #111;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    border-radius: 8px;
    text-align: center;
  }
  .profile-actions a.active {
    background: #111;
    color: #fff;
  }
  .profile-actions a.logout-link { color: #dc3545; }

  .orders-block {
    margin-bottom: 24px;
  }
  .orders-title {
    font-weight: 600;
    margin-bottom: 8px;
  }
  .orders-head, .orders-row {
    display: grid;
    grid-template-columns: 2.5fr 1.2fr 1.2fr 1.2fr;
    align-items: center;
    gap: 12px;
  }
  .orders-head {
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 14px;
  }
  .orders-row {
    border-bottom: 1px solid #ececec;
    padding: 12px 14px;
    background: #fff;
  }
  .orders-product {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .orders-product img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 8px;
  }
  .orders-product-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
  }
  .orders-product-sub {
    font-size: 12px;
    color: #666;
    line-height: 1.2;
  }
  .orders-price, .orders-date, .orders-status {
    font-size: 14px;
  }
  .btn-status {
    display: inline-block;
    border: 0;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    color: #fff;
    background: #e00;
  }
  .badge-status {
    display: inline-block;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    color: #fff;
    font-weight: 600;
  }
  .badge-pending { background: #6c757d; }
  .badge-accepted { background: #28a745; }
  .badge-deleted { background: #dc3545; }
  .badge-onway { background: #0d6efd; }
  .badge-delivered { background: #20c997; }
  .badge-completed { background: #212529; }
  .head-processing { background: #a7ad00; }
  .head-way { background: #1027ff; }
  .head-completed { background: #008f15; }
  .head-canceled { background: #e60000; }
  .star-input {
    direction: rtl;
    display: inline-flex;
    gap: 4px;
  }
  .star-input input {
    display: none;
  }
  .star-input label {
    font-size: 24px;
    color: #cfcfcf;
    cursor: pointer;
    margin: 0;
    line-height: 1;
  }
  .star-input label:hover,
  .star-input label:hover ~ label,
  .star-input input:checked ~ label {
    color: #f5b301;
  }
  .order-billing summary {
    cursor: pointer;
    font-size: 12px;
    color: #0d6efd;
    font-weight: 600;
    outline: none;
  }
  .order-billing-panel {
    margin-top: 6px;
    padding: 8px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #ececec;
    font-size: 12px;
    color: #444;
    line-height: 1.4;
  }
  @media (max-width: 991px) {
    .profile-actions { grid-template-columns: 1fr 1fr; }
    .orders-head, .orders-row { grid-template-columns: 1fr; }
    .orders-head { display: none; }
    .orders-row { gap: 8px; }
  }
</style>

<section class="ftco-section pt-4">
  <div class="container">
    <div class="profile-actions">
      <a href="my_profile.php?tab=my-details">My Details</a>
      <a href="my_orders.php" class="active">My Orders</a>
      <a href="my_profile.php?tab=my-ratings">My Ratings</a>
      <a href="my_profile.php?tab=my-feedbacks">My Feed Backs</a>
      <a href="logout.php" class="logout-link">Logout</a>
    </div>
  </div>
</section>

<section class="ftco-section pt-2">
  <div class="container">
    <?php if ($reviewMessage !== ""): ?>
      <div class="alert alert-success"><?php echo h($reviewMessage); ?></div>
    <?php endif; ?>
    <?php if ($reviewError !== ""): ?>
      <div class="alert alert-danger"><?php echo h($reviewError); ?></div>
    <?php endif; ?>

    <?php if (count($allOrders) === 0): ?>
      <div class="alert alert-info">No orders found for your account yet.</div>
    <?php endif; ?>

    <div class="orders-block">
      <div class="orders-title" style="color:#7c8400;">On Processing Orders</div>
      <div class="orders-head head-processing">
        <div>Product Info</div><div>Price</div><div>Ordered Date</div><div>Status</div>
      </div>
      <div id="processingOrdersList">
      <?php if (count($processingOrders) === 0): ?>
        <div class="orders-row js-processing-empty"><div>No processing orders.</div><div>-</div><div>-</div><div>-</div></div>
      <?php else: ?>
        <?php foreach ($processingOrders as $row): ?>
          <?php $displayOrderTotal = ((float)($row['grand_total'] ?? 0) > 0) ? (float)$row['grand_total'] : (((float)$row['unitprice']) * ((int)$row['quantity'])); ?>
          <div class="orders-row js-processing-row">
            <div class="orders-product">
              <img src="../images/<?php echo h($row['image_1'] ?: 'smartphone.png'); ?>" alt="product">
              <div>
                <div class="orders-product-name"><?php echo h($row['product_name'] ?: ('Product #' . $row['product_id'])); ?></div>
                <div class="orders-product-sub">Order: <?php echo h($row['order_id']); ?></div>
                <div class="orders-product-sub">Qty: <?php echo (int)$row['quantity']; ?></div>
                <?php if (!empty($row['selected_feature'])): ?><div class="orders-product-sub">Feature: <?php echo h($row['selected_feature']); ?></div><?php endif; ?>
              </div>
            </div>
            <div class="orders-price">
              <?php echo h(format_rs($displayOrderTotal)); ?>
              <div class="orders-product-sub"><?php echo h(format_order_payment_customer_line($row)); ?></div>
              <?php echo order_charge_breakdown_html($row); ?>
            </div>
            <div class="orders-date"><?php echo h($row['ordered_date']); ?></div>
            <div class="orders-status">
              <?php $rowStatus = strtoupper(trim((string)($row['status'] ?? 'PENDING'))); ?>
              <?php if ($rowStatus === "ACCEPTED"): ?>
                <span class="badge-status badge-accepted">Accepted</span>
              <?php else: ?>
                <span class="badge-status badge-pending">Pending</span>
                <form method="post" action="my_orders.php" class="mt-2 mb-0 js-delete-order-form" data-order-id="<?php echo h($row['order_id']); ?>">
                  <input type="hidden" name="form_action" value="delete_order">
                  <input type="hidden" name="order_id" value="<?php echo h($row['order_id']); ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      </div>
    </div>

    <div class="orders-block">
      <div class="orders-title" style="color:#1027ff;">On the Way Orders</div>
      <div class="orders-head head-way">
        <div>Product Info</div><div>Price</div><div>Delivered Date</div><div>Status</div>
      </div>
      <?php if (count($onWayOrders) === 0): ?>
        <div class="orders-row"><div>No on-the-way orders.</div><div>-</div><div>-</div><div>-</div></div>
      <?php else: ?>
        <?php foreach ($onWayOrders as $row): ?>
          <?php $displayOrderTotal = ((float)($row['grand_total'] ?? 0) > 0) ? (float)$row['grand_total'] : (((float)$row['unitprice']) * ((int)$row['quantity'])); ?>
          <div class="orders-row">
            <div class="orders-product">
              <img src="../images/<?php echo h($row['image_1'] ?: 'smartphone.png'); ?>" alt="product">
              <div>
                <div class="orders-product-name"><?php echo h($row['product_name'] ?: ('Product #' . $row['product_id'])); ?></div>
                <div class="orders-product-sub">Order: <?php echo h($row['order_id']); ?></div>
                <div class="orders-product-sub">Qty: <?php echo (int)$row['quantity']; ?></div>
                <?php if (!empty($row['selected_feature'])): ?><div class="orders-product-sub">Feature: <?php echo h($row['selected_feature']); ?></div><?php endif; ?>
              </div>
            </div>
            <div class="orders-price">
              <?php echo h(format_rs($displayOrderTotal)); ?>
              <div class="orders-product-sub"><?php echo h(format_order_payment_customer_line($row)); ?></div>
              <?php echo order_charge_breakdown_html($row); ?>
            </div>
            <div class="orders-date"><?php echo h($row['ordered_date']); ?></div>
            <div class="orders-status">
              <span class="badge-status badge-onway">On the way</span>
              <?php if (strtolower(trim((string)($row['payment_method'] ?? ''))) === 'cod' && strtolower(trim((string)($row['payment_status'] ?? ''))) === 'pending'): ?>
                <div class="small text-muted mt-1">COD cash not marked paid yet.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="orders-block">
      <div class="orders-title" style="color:#008f15;">Completed Orders</div>
      <div class="orders-head head-completed">
        <div>Product Info</div><div>Price</div><div>Delivered Date</div><div>Received Date</div>
      </div>
      <?php if (count($completedOrders) === 0): ?>
        <div class="orders-row"><div>No completed orders.</div><div>-</div><div>-</div><div>-</div></div>
      <?php else: ?>
        <?php foreach ($completedOrders as $row): ?>
          <?php $displayOrderTotal = ((float)($row['grand_total'] ?? 0) > 0) ? (float)$row['grand_total'] : (((float)$row['unitprice']) * ((int)$row['quantity'])); ?>
          <div class="orders-row">
            <div class="orders-product">
              <img src="../images/<?php echo h($row['image_1'] ?: 'smartphone.png'); ?>" alt="product">
              <div>
                <div class="orders-product-name"><?php echo h($row['product_name'] ?: ('Product #' . $row['product_id'])); ?></div>
                <div class="orders-product-sub">Order: <?php echo h($row['order_id']); ?></div>
                <div class="orders-product-sub">Qty: <?php echo (int)$row['quantity']; ?></div>
                <?php if (!empty($row['selected_feature'])): ?><div class="orders-product-sub">Feature: <?php echo h($row['selected_feature']); ?></div><?php endif; ?>
              </div>
            </div>
            <div class="orders-price">
              <?php echo h(format_rs($displayOrderTotal)); ?>
              <div class="orders-product-sub"><?php echo h(format_order_payment_customer_line($row)); ?></div>
              <?php echo order_charge_breakdown_html($row); ?>
            </div>
            <div class="orders-date"><?php echo h($row['ordered_date']); ?></div>
            <div class="orders-date">
              <?php $doneStatus = strtoupper(trim((string)($row['status'] ?? ''))); ?>
              <span class="badge-status <?php echo $doneStatus === 'COMPLETED' ? 'badge-completed' : 'badge-delivered'; ?>">
                <?php echo h($doneStatus === 'COMPLETED' ? 'Completed' : 'Delivered'); ?>
              </span>
              <button type="button" class="btn btn-sm btn-outline-dark mt-1" data-toggle="modal" data-target="#reviewModal-<?php echo h($row['orderdetails_id']); ?>">Rate & Review</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="orders-block">
      <div class="orders-title" style="color:#e60000;">Canceled Orders</div>
      <div class="orders-head head-canceled">
        <div>Product Info</div><div>Price</div><div>Canceled Date</div><div>Reason</div>
      </div>
      <div id="canceledOrdersList">
      <?php if (count($canceledOrders) === 0): ?>
        <div class="orders-row js-canceled-empty"><div>No canceled orders.</div><div>-</div><div>-</div><div>-</div></div>
      <?php else: ?>
        <?php foreach ($canceledOrders as $row): ?>
          <?php $displayOrderTotal = ((float)($row['grand_total'] ?? 0) > 0) ? (float)$row['grand_total'] : (((float)$row['unitprice']) * ((int)$row['quantity'])); ?>
          <div class="orders-row js-canceled-row">
            <div class="orders-product">
              <img src="../images/<?php echo h($row['image_1'] ?: 'smartphone.png'); ?>" alt="product">
              <div>
                <div class="orders-product-name"><?php echo h($row['product_name'] ?: ('Product #' . $row['product_id'])); ?></div>
                <div class="orders-product-sub">Order: <?php echo h($row['order_id']); ?></div>
                <div class="orders-product-sub">Qty: <?php echo (int)$row['quantity']; ?></div>
                <?php if (!empty($row['selected_feature'])): ?><div class="orders-product-sub">Feature: <?php echo h($row['selected_feature']); ?></div><?php endif; ?>
              </div>
            </div>
            <div class="orders-price">
              <?php echo h(format_rs($displayOrderTotal)); ?>
              <div class="orders-product-sub"><?php echo h(format_order_payment_customer_line($row)); ?></div>
              <?php echo order_charge_breakdown_html($row); ?>
            </div>
            <div class="orders-date"><?php echo h($row['ordered_date']); ?></div>
            <div class="orders-date">
              <?php
                $cancelStatus = strtoupper(trim((string)($row['status'] ?? '')));
                $cancelReason = trim((string)($row['status_reason'] ?? ''));
                if ($cancelStatus === "DELETED") {
                    $reasonText = $cancelReason !== "" ? $cancelReason : "You deleted the order";
                } elseif ($cancelStatus === "REJECTED") {
                    $reasonText = $cancelReason !== "" ? $cancelReason : "Rejected by admin";
                } else {
                    $reasonText = $cancelReason !== "" ? $cancelReason : "Canceled";
                }
              ?>
              <span class="badge-status badge-deleted"><?php echo h($cancelStatus === "REJECTED" ? "Rejected" : "Deleted"); ?></span>
              <div class="mt-2"><?php echo h($reasonText); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if (count($completedOrders) > 0): ?>
  <?php foreach ($completedOrders as $row): ?>
    <div class="modal fade" id="reviewModal-<?php echo h($row['orderdetails_id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Rate & Review</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form method="post" action="my_orders.php" class="js-review-form" data-orderdetails-id="<?php echo h($row['orderdetails_id']); ?>">
            <div class="modal-body">
              <input type="hidden" name="form_action" value="save_review">
              <input type="hidden" name="order_id" value="<?php echo h($row['order_id']); ?>">
              <input type="hidden" name="product_id" value="<?php echo h($row['product_id']); ?>">
              <input type="hidden" name="orderdetails_id" value="<?php echo h($row['orderdetails_id']); ?>">

              <p class="mb-2"><strong><?php echo h($row['product_name'] ?: ('Product #' . $row['product_id'])); ?></strong></p>
              <div class="form-group">
                <label class="d-block">Rating</label>
                <div class="star-input">
                  <input type="radio" id="star5-<?php echo h($row['orderdetails_id']); ?>" name="rating" value="5" required>
                  <label for="star5-<?php echo h($row['orderdetails_id']); ?>">★</label>
                  <input type="radio" id="star4-<?php echo h($row['orderdetails_id']); ?>" name="rating" value="4">
                  <label for="star4-<?php echo h($row['orderdetails_id']); ?>">★</label>
                  <input type="radio" id="star3-<?php echo h($row['orderdetails_id']); ?>" name="rating" value="3">
                  <label for="star3-<?php echo h($row['orderdetails_id']); ?>">★</label>
                  <input type="radio" id="star2-<?php echo h($row['orderdetails_id']); ?>" name="rating" value="2">
                  <label for="star2-<?php echo h($row['orderdetails_id']); ?>">★</label>
                  <input type="radio" id="star1-<?php echo h($row['orderdetails_id']); ?>" name="rating" value="1">
                  <label for="star1-<?php echo h($row['orderdetails_id']); ?>">★</label>
                </div>
              </div>
              <div class="form-group">
                <label>Review</label>
                <textarea name="review_text" class="form-control" rows="4" required placeholder="Share your experience"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-dark">Submit Review</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  function ensureEmptyState(listSelector, rowSelector, emptyClass, emptyText) {
    const list = document.querySelector(listSelector);
    if (!list) return;
    const rowCount = list.querySelectorAll(rowSelector).length;
    let emptyRow = list.querySelector("." + emptyClass);
    if (rowCount === 0) {
      if (!emptyRow) {
        emptyRow = document.createElement("div");
        emptyRow.className = "orders-row " + emptyClass;
        emptyRow.innerHTML = "<div>" + emptyText + "</div><div>-</div><div>-</div><div>-</div>";
        list.appendChild(emptyRow);
      }
    } else if (emptyRow) {
      emptyRow.remove();
    }
  }

  function moveRowToCanceled(processingRow) {
    const canceledList = document.querySelector("#canceledOrdersList");
    if (!canceledList || !processingRow) return;

    const clone = processingRow.cloneNode(true);
    clone.classList.remove("js-processing-row");
    clone.classList.add("js-canceled-row");

    const statusCell = clone.querySelector(".orders-status") || clone.lastElementChild;
    if (statusCell) {
      statusCell.className = "orders-date";
      statusCell.innerHTML = '<span class="badge-status badge-deleted">Deleted</span><div class="mt-2">You deleted the order</div>';
    }
    const deleteForm = clone.querySelector(".js-delete-order-form");
    if (deleteForm) deleteForm.remove();

    canceledList.insertBefore(clone, canceledList.firstChild);
  }

  function showSwal(icon, text) {
    if (typeof Swal !== "undefined") {
      Swal.fire({ icon: icon, title: text, confirmButtonText: "OK" });
    } else {
      alert(text);
    }
  }

  document.querySelectorAll(".js-delete-order-form").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (typeof Swal === "undefined") return form.submit();
      Swal.fire({
        icon: "warning",
        title: "Delete this order?",
        showCancelButton: true,
        confirmButtonText: "Yes, delete"
      }).then(function (res) {
        if (!res.isConfirmed) return;
        const fd = new FormData(form);
        fd.append("ajax", "1");
        fetch("my_orders.php", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.success) {
            showSwal("error", (data && data.message) ? data.message : "Unable to delete order.");
            return;
          }
          showSwal("success", data.message || "Order deleted successfully.");
          const row = form.closest(".orders-row");
          if (row) {
            moveRowToCanceled(row);
            row.remove();
            ensureEmptyState("#processingOrdersList", ".js-processing-row", "js-processing-empty", "No processing orders.");
            ensureEmptyState("#canceledOrdersList", ".js-canceled-row", "js-canceled-empty", "No canceled orders.");
          }
        })
        .catch(function () {
          showSwal("error", "Request failed.");
        });
      });
    });
  });

  document.querySelectorAll(".js-review-form").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const fd = new FormData(form);
      fd.append("ajax", "1");
      fetch("my_orders.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          showSwal("error", (data && data.message) ? data.message : "Unable to submit review.");
          return;
        }
        showSwal("success", data.message || "Review submitted.");
        const modalEl = form.closest(".modal");
        if (modalEl && window.jQuery) {
          window.jQuery(modalEl).modal("hide");
        }
        const odId = form.getAttribute("data-orderdetails-id") || "";
        if (odId) {
          const btn = document.querySelector('[data-target="#reviewModal-' + odId + '"]');
          if (btn) {
            btn.textContent = "Reviewed";
            btn.disabled = true;
            btn.classList.remove("btn-outline-dark");
            btn.classList.add("btn-outline-success");
          }
        }
      })
      .catch(function () {
        showSwal("error", "Request failed.");
      });
    });
  });
});
</script>

<?php require "footer.php"; ?>
