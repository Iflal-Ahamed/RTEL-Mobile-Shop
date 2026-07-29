<?php
/**
 * Admin: update order status (sends customer email via web/mail/mail_notifications.php).
 */
include "connection.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/activity_logger.php";
require_once __DIR__ . "/../web/includes/rtel_db_helpers.php";
require_once __DIR__ . "/../web/mail/mail_notifications.php";
rtel_require_admin_auth();
rtel_require_admin_page_access('order_manage.php');

$conn->set_charset("utf8mb4");
mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER ordered_date");
mysqli_query($conn, "ALTER TABLE tblorder ADD COLUMN IF NOT EXISTS status_reason VARCHAR(255) NULL AFTER status");

$message = "";
$error = "";

$allowedStatuses = ["Pending", "Accepted", "On the way", "Delivered", "Rejected"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $orderId = trim((string)($_POST["order_id"] ?? ""));
    $newStatus = trim((string)($_POST["new_status"] ?? ""));
    $reason = trim((string)($_POST["status_reason"] ?? ""));
    if ($orderId === "") {
        $error = "Order ID required.";
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $error = "Invalid status.";
    } else {
        $sel = $conn->prepare("SELECT status FROM tblorder WHERE order_id = ? LIMIT 1");
        $oldStatus = "";
        if ($sel) {
            $sel->bind_param("s", $orderId);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();
            $oldStatus = $row ? (string)$row["status"] : "";
        }
        if ($oldStatus === "") {
            $error = "Order not found.";
        } else {
            $up = $conn->prepare("UPDATE tblorder SET status = ?, status_reason = ? WHERE order_id = ?");
            if ($up) {
                $reasonVal = $reason !== "" ? $reason : "";
                $up->bind_param("sss", $newStatus, $reasonVal, $orderId);
                $up->execute();
                $up->close();
                if (strcasecmp($oldStatus, $newStatus) !== 0) {
                    rtel_notify_order_status($conn, $orderId, $newStatus, $reason);
                }
                rtel_admin_log_event($conn, 'order_status', 'success', 'Order ' . $orderId . ': ' . $oldStatus . ' -> ' . $newStatus . ($reason !== '' ? (' (' . $reason . ')') : ''));
                $message = "Order " . htmlspecialchars($orderId, ENT_QUOTES, "UTF-8") . " updated to " . htmlspecialchars($newStatus, ENT_QUOTES, "UTF-8") . ".";
            } else {
                $error = "Update failed.";
            }
        }
    }
}

$orders = [];
$custNameCol = rtel_customer_display_name_column($conn);
$res = mysqli_query(
    $conn,
    "SELECT o.order_id, o.ordered_date, o.status, o.cus_id, c.`{$custNameCol}` AS customer_name, c.email,
            COALESCE(NULLIF(ch.grand_total, 0), (SELECT SUM(od.quantity * od.unitprice) FROM tblorder_details od WHERE od.order_id = o.order_id), 0) AS line_total
     FROM tblorder o
     LEFT JOIN tblcustomer c ON o.cus_id = c.cus_id
     LEFT JOIN tblorder_charge ch ON o.order_id = ch.order_id
     ORDER BY o.ordered_date DESC, o.order_id DESC
     LIMIT 100"
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order status — R-TEL Admin</title>
  <script>
    (function () {
      try {
        var saved = localStorage.getItem("rtel_theme_mode");
        var theme = (saved === "dark" || saved === "light")
          ? saved
          : ((window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light");
        document.documentElement.setAttribute("data-theme", theme);
      } catch (e) {}
    })();
  </script>
  <link rel="icon" href="../images/logo.jpg" type="image/jpeg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="bg-light py-4">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Order status &amp; customer email</h4>
      <a href="order.php" class="btn btn-outline-secondary btn-sm">Back to Order page</a>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div><?php endif; ?>

    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Email</th>
              <th>Subtotal</th>
              <th>Current status</th>
              <th>Change</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><?= htmlspecialchars($o["order_id"], ENT_QUOTES, "UTF-8") ?></td>
                <td><?= htmlspecialchars((string)$o["ordered_date"], ENT_QUOTES, "UTF-8") ?></td>
                <td><?= htmlspecialchars((string)($o["customer_name"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                <td class="small"><?= htmlspecialchars((string)($o["email"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                <td><?= number_format((float)($o["line_total"] ?? 0), 2) ?></td>
                <td><?= htmlspecialchars((string)($o["status"] ?? "Pending"), ENT_QUOTES, "UTF-8") ?></td>
                <td>
                  <form method="post" class="d-flex flex-wrap gap-1 align-items-center">
                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($o["order_id"], ENT_QUOTES, "UTF-8") ?>">
                    <select name="new_status" class="form-select form-select-sm" style="width:140px;">
                      <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= htmlspecialchars($s, ENT_QUOTES, "UTF-8") ?>" <?= strcasecmp((string)($o["status"] ?? ""), $s) === 0 ? "selected" : "" ?>><?= htmlspecialchars($s, ENT_QUOTES, "UTF-8") ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="status_reason" class="form-control form-control-sm" style="width:160px;" placeholder="Reason (reject)">
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($orders) === 0): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="small text-muted mt-2">Changing status emails the customer (when SMTP is configured). Use <strong>Rejected</strong> with an optional reason for cancellations.</p>
  </div>
  <script src="assets/js/theme-toggle.js"></script>
</body>
</html>
