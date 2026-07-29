<?php
/**
 * Transactional emails for R-TEL (registration, orders, status, invoices).
 */
require_once __DIR__ . "/mail_helper.php";
require_once __DIR__ . "/../includes/rtel_db_helpers.php";

function rtel_mail_wrap_html($title, $innerHtml)
{
    return "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($title, ENT_QUOTES, "UTF-8") . "</title></head><body style=\"font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#222;line-height:1.5;\">"
        . "<div style=\"max-width:560px;margin:0 auto;padding:16px;\">"
        . "<h2 style=\"margin:0 0 12px;\">R-TEL</h2>"
        . $innerHtml
        . "<p style=\"margin-top:24px;font-size:12px;color:#666;\">This is an automated message. Please do not reply to this email.</p>"
        . "</div></body></html>";
}

function rtel_notify_registration_welcome($customerEmail, $customerName)
{
    $name = htmlspecialchars($customerName, ENT_QUOTES, "UTF-8");
    $body = "<p>Hi {$name},</p><p>Welcome to <strong>R-TEL</strong>! Your account was created successfully.</p>"
        . "<p>You can log in anytime with your email and password to shop, track orders, and manage your profile.</p>";
    return rtel_send_html_email(
        $customerEmail,
        "Welcome to R-TEL — registration successful",
        rtel_mail_wrap_html("Welcome", $body)
    );
}

/**
 * Loads order header + lines + customer email for notifications.
 */
function rtel_fetch_order_mail_context(mysqli $conn, $orderId)
{
    $orderId = trim((string)$orderId);
    if ($orderId === "") {
        return null;
    }
    $custNameCol = rtel_customer_display_name_column($conn);
    $sql = "SELECT o.order_id, o.ordered_date, o.status, o.cus_id, c.email, c.`{$custNameCol}` AS customer_name
            FROM tblorder o
            JOIN tblcustomer c ON o.cus_id = c.cus_id
            WHERE o.order_id = ? LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) {
        return null;
    }
    $st->bind_param("s", $orderId);
    $st->execute();
    $head = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$head) {
        return null;
    }
    $lines = [];
    $ls = $conn->prepare(
        "SELECT od.quantity, od.unitprice, COALESCE(p.name, 'Product') AS product_name
         FROM tblorder_details od
         LEFT JOIN tblproduct p ON od.product_id = p.product_id
         WHERE od.order_id = ?"
    );
    if ($ls) {
        $ls->bind_param("s", $orderId);
        $ls->execute();
        $r = $ls->get_result();
        while ($r && $row = $r->fetch_assoc()) {
            $lines[] = $row;
        }
        $ls->close();
    }
    $pay = null;
    $ps = $conn->prepare("SELECT method, amount, currency, payment_status, paid_at, gateway_ref FROM tblpayment WHERE order_id = ? LIMIT 1");
    if ($ps) {
        $ps->bind_param("s", $orderId);
        $ps->execute();
        $pay = $ps->get_result()->fetch_assoc();
        $ps->close();
    }
    return ["head" => $head, "lines" => $lines, "payment" => $pay];
}

function rtel_format_rs($n)
{
    return "Rs. " . number_format((float)$n, 2);
}

function rtel_notify_order_placed(mysqli $conn, $orderId)
{
    $ctx = rtel_fetch_order_mail_context($conn, $orderId);
    if (!$ctx) {
        return false;
    }
    $email = (string)$ctx["head"]["email"];
    $name = htmlspecialchars((string)$ctx["head"]["customer_name"], ENT_QUOTES, "UTF-8");
    $oid = htmlspecialchars((string)$ctx["head"]["order_id"], ENT_QUOTES, "UTF-8");
    $date = htmlspecialchars((string)$ctx["head"]["ordered_date"], ENT_QUOTES, "UTF-8");
    $rows = "";
    $sub = 0.0;
    foreach ($ctx["lines"] as $ln) {
        $q = (int)$ln["quantity"];
        $u = (float)$ln["unitprice"];
        $line = $q * $u;
        $sub += $line;
        $pn = htmlspecialchars((string)$ln["product_name"], ENT_QUOTES, "UTF-8");
        $rows .= "<tr><td>{$pn}</td><td style=\"text-align:center;\">{$q}</td><td style=\"text-align:right;\">" . rtel_format_rs($u) . "</td><td style=\"text-align:right;\">" . rtel_format_rs($line) . "</td></tr>";
    }
    $pay = $ctx["payment"];
    $payLine = $pay
        ? ("<p><strong>Payment:</strong> " . htmlspecialchars((string)$pay["method"], ENT_QUOTES, "UTF-8")
            . " — " . htmlspecialchars((string)$pay["payment_status"], ENT_QUOTES, "UTF-8")
            . " (" . rtel_format_rs((float)$pay["amount"]) . ")</p>")
        : "";
    $inner = "<p>Hi {$name},</p><p>Thank you for your order. We have received it and will process it soon.</p>"
        . "<p><strong>Order ID:</strong> {$oid}<br><strong>Date:</strong> {$date}</p>"
        . "<table border=\"1\" cellpadding=\"8\" cellspacing=\"0\" style=\"border-collapse:collapse;width:100%;font-size:14px;\">"
        . "<thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Line</th></tr></thead><tbody>{$rows}</tbody></table>"
        . "<p style=\"margin-top:8px;\"><strong>Items subtotal:</strong> " . rtel_format_rs($sub) . "</p>"
        . $payLine;
    return rtel_send_html_email($email, "R-TEL — Order received ({$oid})", rtel_mail_wrap_html("Order placed", $inner));
}

function rtel_notify_order_status(mysqli $conn, $orderId, $newStatus, $reason = "")
{
    $ctx = rtel_fetch_order_mail_context($conn, $orderId);
    if (!$ctx) {
        return false;
    }
    $email = (string)$ctx["head"]["email"];
    $name = htmlspecialchars((string)$ctx["head"]["customer_name"], ENT_QUOTES, "UTF-8");
    $oid = htmlspecialchars((string)$orderId, ENT_QUOTES, "UTF-8");
    $st = htmlspecialchars($newStatus, ENT_QUOTES, "UTF-8");
    $reasonHtml = $reason !== "" ? ("<p><strong>Note:</strong> " . htmlspecialchars($reason, ENT_QUOTES, "UTF-8") . "</p>") : "";
    $inner = "<p>Hi {$name},</p><p>Your order <strong>{$oid}</strong> status is now: <strong>{$st}</strong>.</p>{$reasonHtml}"
        . "<p>You can review details under <strong>My Orders</strong> on the R-TEL website.</p>";
    return rtel_send_html_email($email, "R-TEL — Order {$oid} is now: {$newStatus}", rtel_mail_wrap_html("Order status", $inner));
}

function rtel_notify_customer_access_status($customerEmail, $customerName, $isBlocked, $note = "")
{
    $email = trim((string)$customerEmail);
    if ($email === "") {
        return false;
    }
    $name = htmlspecialchars((string)$customerName, ENT_QUOTES, "UTF-8");
    $statusText = $isBlocked ? "Blocked" : "Active";
    $subject = $isBlocked
        ? "R-TEL account access update — account blocked"
        : "R-TEL account access update — account unblocked";
    $reasonHtml = trim((string)$note) !== ""
        ? "<p><strong>Note:</strong> " . htmlspecialchars((string)$note, ENT_QUOTES, "UTF-8") . "</p>"
        : "";
    $inner = $isBlocked
        ? "<p>Hi {$name},</p><p>Your R-TEL customer account status has been updated to <strong>{$statusText}</strong>.</p>{$reasonHtml}<p>You cannot sign in while your account remains blocked. Please contact support for assistance.</p>"
        : "<p>Hi {$name},</p><p>Your R-TEL customer account status has been updated to <strong>{$statusText}</strong>.</p>{$reasonHtml}<p>You can now sign in and continue shopping on R-TEL.</p>";
    return rtel_send_html_email($email, $subject, rtel_mail_wrap_html("Account access update", $inner));
}

function rtel_notify_payment_invoice(mysqli $conn, $orderId)
{
    $ctx = rtel_fetch_order_mail_context($conn, $orderId);
    if (!$ctx || !$ctx["payment"]) {
        return false;
    }
    $pay = $ctx["payment"];
    if (strtolower((string)$pay["payment_status"]) !== "paid") {
        return false;
    }
    $email = (string)$ctx["head"]["email"];
    $name = htmlspecialchars((string)$ctx["head"]["customer_name"], ENT_QUOTES, "UTF-8");
    $oid = htmlspecialchars((string)$orderId, ENT_QUOTES, "UTF-8");
    $rows = "";
    $sub = 0.0;
    foreach ($ctx["lines"] as $ln) {
        $q = (int)$ln["quantity"];
        $u = (float)$ln["unitprice"];
        $line = $q * $u;
        $sub += $line;
        $pn = htmlspecialchars((string)$ln["product_name"], ENT_QUOTES, "UTF-8");
        $rows .= "<tr><td>{$pn}</td><td style=\"text-align:center;\">{$q}</td><td style=\"text-align:right;\">" . rtel_format_rs($u) . "</td><td style=\"text-align:right;\">" . rtel_format_rs($line) . "</td></tr>";
    }
    $total = rtel_format_rs((float)$pay["amount"]);
    $method = htmlspecialchars((string)$pay["method"], ENT_QUOTES, "UTF-8");
    $paidAt = htmlspecialchars((string)($pay["paid_at"] ?? ""), ENT_QUOTES, "UTF-8");
    $gatewayRef = trim((string)($pay["gateway_ref"] ?? ""));
    $refLine = $gatewayRef !== "" ? ("<p><strong>Reference:</strong> " . htmlspecialchars($gatewayRef, ENT_QUOTES, "UTF-8") . "</p>") : "";

    $inner = "<p>Hi {$name},</p><p>Thank you for your payment. Below is your <strong>invoice</strong> for order <strong>{$oid}</strong>.</p>"
        . "<table border=\"1\" cellpadding=\"8\" cellspacing=\"0\" style=\"border-collapse:collapse;width:100%;font-size:14px;\">"
        . "<thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Line</th></tr></thead><tbody>{$rows}</tbody></table>"
        . "<p><strong>Items subtotal:</strong> " . rtel_format_rs($sub) . "</p>"
        . "<p><strong>Amount charged (incl. shipping/discounts as applied):</strong> {$total}</p>"
        . "<p><strong>Method:</strong> {$method}<br><strong>Status:</strong> Paid"
        . ($paidAt !== "" ? "<br><strong>Paid at:</strong> {$paidAt}" : "")
        . "</p>{$refLine}";
    return rtel_send_html_email($email, "R-TEL — Invoice for order {$oid}", rtel_mail_wrap_html("Invoice", $inner));
}

function rtel_notify_password_reset_otp($email, $otp)
{
    $e = htmlspecialchars($email, ENT_QUOTES, "UTF-8");
    $o = htmlspecialchars((string)$otp, ENT_QUOTES, "UTF-8");
    $inner = "<p>We received a request to reset the password for <strong>{$e}</strong>.</p>"
        . "<p>Your one-time code is:</p><p style=\"font-size:28px;font-weight:bold;letter-spacing:4px;\">{$o}</p>"
        . "<p>This code expires in <strong>15 minutes</strong>. If you did not request this, you can ignore this email.</p>";
    return rtel_send_html_email($email, "R-TEL — Password reset code", rtel_mail_wrap_html("OTP", $inner));
}

/**
 * Cart change digest: notify customer when cart item price drops or stock gets tight.
 * $alerts item shape:
 * [
 *   "type" => "price_drop"|"stock_reduced",
 *   "product_name" => "...",
 *   "old_value" => 0.0|int,
 *   "new_value" => 0.0|int
 * ]
 */
function rtel_notify_cart_change_digest($customerEmail, $customerName, array $alerts)
{
    $email = trim((string)$customerEmail);
    if ($email === "" || count($alerts) === 0) {
        return false;
    }
    $name = htmlspecialchars((string)$customerName, ENT_QUOTES, "UTF-8");
    $rows = "";
    foreach ($alerts as $a) {
        $type = strtolower(trim((string)($a["type"] ?? "")));
        $productName = htmlspecialchars((string)($a["product_name"] ?? "Product"), ENT_QUOTES, "UTF-8");
        if ($type === "price_drop") {
            $old = rtel_format_rs((float)($a["old_value"] ?? 0));
            $new = rtel_format_rs((float)($a["new_value"] ?? 0));
            $rows .= "<tr><td>{$productName}</td><td>Price dropped</td><td>{$old} → {$new}</td></tr>";
        } elseif ($type === "stock_reduced") {
            $old = (int)($a["old_value"] ?? 0);
            $new = (int)($a["new_value"] ?? 0);
            $rows .= "<tr><td>{$productName}</td><td>Stock updated</td><td>{$old} → {$new}</td></tr>";
        }
    }
    if ($rows === "") {
        return false;
    }
    $inner = "<p>Hi {$name},</p>"
        . "<p>Some items in your cart have changed. We wanted to notify you quickly.</p>"
        . "<table border=\"1\" cellpadding=\"8\" cellspacing=\"0\" style=\"border-collapse:collapse;width:100%;font-size:14px;\">"
        . "<thead><tr><th>Product</th><th>Change</th><th>Details</th></tr></thead><tbody>{$rows}</tbody></table>"
        . "<p style=\"margin-top:10px;\">Please review your cart before checkout.</p>";
    return rtel_send_html_email($email, "R-TEL — Update on your cart items", rtel_mail_wrap_html("Cart update", $inner));
}

/**
 * Product watch alerts (price drop / restock).
 */
function rtel_notify_product_watch_alert($customerEmail, $customerName, $productName, $productUrl, $alertType, $oldValue = null, $newValue = null)
{
    $email = trim((string)$customerEmail);
    if ($email === "") {
        return false;
    }
    $name = htmlspecialchars((string)$customerName, ENT_QUOTES, "UTF-8");
    $productNameHtml = htmlspecialchars((string)$productName, ENT_QUOTES, "UTF-8");
    $safeUrl = trim((string)$productUrl);
    $cta = $safeUrl !== ""
        ? '<p><a href="' . htmlspecialchars($safeUrl, ENT_QUOTES, "UTF-8") . '" style="display:inline-block;padding:10px 14px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;">View Product</a></p>'
        : '';

    $alertType = strtolower(trim((string)$alertType));
    if ($alertType === "price_drop") {
        $old = $oldValue === null ? "" : rtel_format_rs((float)$oldValue);
        $new = $newValue === null ? "" : rtel_format_rs((float)$newValue);
        $detail = ($old !== "" && $new !== "") ? ("<p><strong>Price:</strong> {$old} → {$new}</p>") : "";
        $inner = "<p>Hi {$name},</p><p>Great news! <strong>{$productNameHtml}</strong> has a price drop.</p>{$detail}{$cta}";
        return rtel_send_html_email($email, "R-TEL — Price dropped for {$productName}", rtel_mail_wrap_html("Price drop alert", $inner));
    }

    if ($alertType === "restock") {
        $inner = "<p>Hi {$name},</p><p><strong>{$productNameHtml}</strong> is back in stock now.</p>{$cta}";
        return rtel_send_html_email($email, "R-TEL — Back in stock: {$productName}", rtel_mail_wrap_html("Restock alert", $inner));
    }
    return false;
}

function rtel_fetch_admin_notification_recipients(mysqli $conn)
{
    @mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL DEFAULT 'admin'");
    @mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");

    $emails = [];
    $res = $conn->query("SELECT email, COALESCE(NULLIF(TRIM(name), ''), admin_id) AS admin_name
                         FROM tbladmin
                         WHERE COALESCE(type, 'admin') = 'admin' AND COALESCE(status, 1) = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = [
                'email' => $email,
                'name' => (string)($row['admin_name'] ?? 'Admin'),
            ];
        }
        $res->free();
    }
    return $emails;
}

function rtel_notify_admin_new_order(mysqli $conn, $orderId, $customerName)
{
    $admins = rtel_fetch_admin_notification_recipients($conn);
    if (!$admins) return false;
    $oid = htmlspecialchars((string)$orderId, ENT_QUOTES, "UTF-8");
    $customerName = htmlspecialchars((string)$customerName, ENT_QUOTES, "UTF-8");
    $body = "<p>A new order has been placed.</p>"
        . "<p><strong>Order ID:</strong> {$oid}<br><strong>Customer:</strong> {$customerName}</p>"
        . "<p>Please review it from MIS Orders.</p>";
    $subject = "R-TEL Admin Alert — New order {$orderId}";
    $html = rtel_mail_wrap_html("New order alert", $body);
    $sent = false;
    foreach ($admins as $admin) {
        if (rtel_send_html_email((string)$admin['email'], $subject, $html)) {
            $sent = true;
        }
    }
    return $sent;
}

function rtel_notify_admin_new_feedback(mysqli $conn, $feedbackName, $feedbackText)
{
    $admins = rtel_fetch_admin_notification_recipients($conn);
    if (!$admins) return false;
    $name = htmlspecialchars((string)$feedbackName, ENT_QUOTES, "UTF-8");
    $text = htmlspecialchars((string)$feedbackText, ENT_QUOTES, "UTF-8");
    $body = "<p>A new customer feedback was submitted.</p>"
        . "<p><strong>Name:</strong> {$name}</p>"
        . "<p><strong>Feedback:</strong><br>{$text}</p>"
        . "<p>Please moderate it in MIS Feedback page.</p>";
    $subject = "R-TEL Admin Alert — New feedback submitted";
    $html = rtel_mail_wrap_html("New feedback alert", $body);
    $sent = false;
    foreach ($admins as $admin) {
        if (rtel_send_html_email((string)$admin['email'], $subject, $html)) {
            $sent = true;
        }
    }
    return $sent;
}

function rtel_notify_feedback_thanks($customerEmail, $customerName, $feedbackText = "")
{
    $email = trim((string)$customerEmail);
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $nameSafe = htmlspecialchars(trim((string)$customerName) !== "" ? (string)$customerName : "Customer", ENT_QUOTES, "UTF-8");
    $feedbackSafe = htmlspecialchars(trim((string)$feedbackText), ENT_QUOTES, "UTF-8");
    $feedbackLine = $feedbackSafe !== ""
        ? "<p><strong>Your feedback:</strong><br>{$feedbackSafe}</p>"
        : "";
    $body = "<p>Hi {$nameSafe},</p>"
        . "<p>Thank you for your feedback to <strong>R-TEL</strong>. We really appreciate your time and support.</p>"
        . $feedbackLine
        . "<p>Our team will review it soon.</p>";
    $subject = "R-TEL — Thank you for your feedback";
    return rtel_send_html_email($email, $subject, rtel_mail_wrap_html("Thanks for your feedback", $body));
}

function rtel_notify_admin_new_rating(mysqli $conn, $orderId, $productName, $rating, $reviewText)
{
    $admins = rtel_fetch_admin_notification_recipients($conn);
    if (!$admins) return false;
    $orderId = htmlspecialchars((string)$orderId, ENT_QUOTES, "UTF-8");
    $productName = htmlspecialchars((string)$productName, ENT_QUOTES, "UTF-8");
    $rating = (int)$rating;
    $reviewText = htmlspecialchars((string)$reviewText, ENT_QUOTES, "UTF-8");
    $body = "<p>A new product rating/review was submitted.</p>"
        . "<p><strong>Order ID:</strong> {$orderId}<br><strong>Product:</strong> {$productName}<br><strong>Rating:</strong> {$rating}/5</p>"
        . "<p><strong>Review:</strong><br>{$reviewText}</p>"
        . "<p>Please review it in MIS Feedback & Ratings.</p>";
    $subject = "R-TEL Admin Alert — New rating submitted";
    $html = rtel_mail_wrap_html("New rating alert", $body);
    $sent = false;
    foreach ($admins as $admin) {
        if (rtel_send_html_email((string)$admin['email'], $subject, $html)) {
            $sent = true;
        }
    }
    return $sent;
}
