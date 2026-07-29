<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../connection.php";
require_once __DIR__ . "/../includes/auth.php";
rtel_require_admin_auth();

header("Content-Type: application/json; charset=utf-8");

function out_json($payload)
{
    echo json_encode($payload);
    exit;
}

$conn->set_charset("utf8mb4");
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin_alert_read (
    admin_id VARCHAR(50) NOT NULL PRIMARY KEY,
    order_seen_at DATETIME NULL,
    feedback_seen_id INT UNSIGNED NOT NULL DEFAULT 0,
    rating_seen_id INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
)");

$adminId = trim((string)($_SESSION['admin_id'] ?? ''));
if ($adminId === '') {
    out_json(["status" => "error", "message" => "Unauthorized"]);
}

function scalar_value(mysqli $conn, $sql)
{
    $res = $conn->query($sql);
    if (!$res) return null;
    $row = $res->fetch_row();
    $res->free();
    return $row[0] ?? null;
}

function ensure_read_row(mysqli $conn, $adminId)
{
    $stmt = $conn->prepare("INSERT INTO tbladmin_alert_read (admin_id, updated_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE updated_at = updated_at");
    if ($stmt) {
        $stmt->bind_param("s", $adminId);
        $stmt->execute();
        $stmt->close();
    }
}

function get_read_state(mysqli $conn, $adminId)
{
    ensure_read_row($conn, $adminId);
    $state = [
        "order_seen_at" => null,
        "feedback_seen_id" => 0,
        "rating_seen_id" => 0,
    ];
    $stmt = $conn->prepare("SELECT order_seen_at, feedback_seen_id, rating_seen_id FROM tbladmin_alert_read WHERE admin_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $state["order_seen_at"] = $row["order_seen_at"] ?: null;
            $state["feedback_seen_id"] = (int)($row["feedback_seen_id"] ?? 0);
            $state["rating_seen_id"] = (int)($row["rating_seen_id"] ?? 0);
        }
    }
    return $state;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'summary'));
$state = get_read_state($conn, $adminId);

if ($action === 'mark_read') {
    $type = trim((string)($_POST['type'] ?? 'all'));
    $updates = [];
    $types = "";
    $params = [];

    if ($type === 'orders' || $type === 'all') {
        $updates[] = "order_seen_at = NOW()";
    }
    if ($type === 'feedbacks' || $type === 'all') {
        $maxFeedbackId = (int)(scalar_value($conn, "SELECT COALESCE(MAX(com_id),0) FROM tblcomment") ?? 0);
        $updates[] = "feedback_seen_id = ?";
        $types .= "i";
        $params[] = $maxFeedbackId;
    }
    if ($type === 'ratings' || $type === 'all') {
        $maxRatingId = (int)(scalar_value($conn, "SELECT COALESCE(MAX(rating_id),0) FROM tblratings") ?? 0);
        $updates[] = "rating_seen_id = ?";
        $types .= "i";
        $params[] = $maxRatingId;
    }
    if (!$updates) {
        out_json(["status" => "error", "message" => "Invalid alert type"]);
    }
    $updates[] = "updated_at = NOW()";
    $sql = "UPDATE tbladmin_alert_read SET " . implode(", ", $updates) . " WHERE admin_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        out_json(["status" => "error", "message" => "Unable to mark read"]);
    }
    $types .= "s";
    $params[] = $adminId;
    $bind = [$types];
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        out_json(["status" => "error", "message" => "Unable to mark read"]);
    }
    out_json(["status" => "success"]);
}

$newOrders = (int)(scalar_value($conn, "SELECT COUNT(*) FROM tblorder WHERE LOWER(COALESCE(status,'pending'))='pending'") ?? 0);
$newFeedbacks = (int)(scalar_value($conn, "SELECT COUNT(*) FROM tblcomment WHERE COALESCE(status,0)=0") ?? 0);
$newRatings = (int)(scalar_value($conn, "SELECT COUNT(*) FROM tblratings WHERE DATE(COALESCE(created_at, NOW())) = CURDATE()") ?? 0);

$orderUnread = 0;
if (!empty($state['order_seen_at'])) {
    $stmtOrderUnread = $conn->prepare("SELECT COUNT(*) AS c FROM tblorder WHERE LOWER(COALESCE(status,'pending'))='pending' AND COALESCE(ordered_date, CURDATE()) > ?");
    if ($stmtOrderUnread) {
        $seenAt = (string)$state['order_seen_at'];
        $stmtOrderUnread->bind_param("s", $seenAt);
        $stmtOrderUnread->execute();
        $row = $stmtOrderUnread->get_result()->fetch_assoc();
        $orderUnread = (int)($row['c'] ?? 0);
        $stmtOrderUnread->close();
    }
} else {
    $orderUnread = $newOrders;
}

$feedbackUnread = (int)(scalar_value($conn, "SELECT COUNT(*) FROM tblcomment WHERE COALESCE(status,0)=0 AND com_id > " . (int)$state['feedback_seen_id']) ?? 0);
$ratingUnread = (int)(scalar_value($conn, "SELECT COUNT(*) FROM tblratings WHERE rating_id > " . (int)$state['rating_seen_id']) ?? 0);

$items = [];
$resOrders = $conn->query("SELECT order_id, cus_id, ordered_date FROM tblorder WHERE LOWER(COALESCE(status,'pending'))='pending' ORDER BY ordered_date DESC, order_id DESC LIMIT 4");
while ($resOrders && ($r = $resOrders->fetch_assoc())) {
    $items[] = [
        "type" => "order",
        "title" => "Order " . (string)($r['order_id'] ?? '-'),
        "meta" => "Customer " . (string)($r['cus_id'] ?? '-') . " · " . (string)($r['ordered_date'] ?? ''),
        "url" => "order.php",
    ];
}
if ($resOrders) $resOrders->free();

$resFeedback = $conn->query("SELECT com_id, name, comment FROM tblcomment WHERE COALESCE(status,0)=0 ORDER BY com_id DESC LIMIT 4");
while ($resFeedback && ($r = $resFeedback->fetch_assoc())) {
    $items[] = [
        "type" => "feedback",
        "title" => "Feedback from " . (string)($r['name'] ?? 'User'),
        "meta" => substr((string)($r['comment'] ?? ''), 0, 70),
        "url" => "feedback.php",
    ];
}
if ($resFeedback) $resFeedback->free();

$resRatings = $conn->query("SELECT rating_id, order_id, rating, LEFT(review_text, 70) AS review_text FROM tblratings ORDER BY rating_id DESC LIMIT 4");
while ($resRatings && ($r = $resRatings->fetch_assoc())) {
    $items[] = [
        "type" => "rating",
        "title" => "Rating " . (int)($r['rating'] ?? 0) . "/5 (Order " . (string)($r['order_id'] ?? '-') . ")",
        "meta" => (string)($r['review_text'] ?? ''),
        "url" => "feedback.php",
    ];
}
if ($resRatings) $resRatings->free();

out_json([
    "status" => "success",
    "counts" => [
        "orders" => $newOrders,
        "feedbacks" => $newFeedbacks,
        "ratings" => $newRatings,
    ],
    "unread" => [
        "orders" => $orderUnread,
        "feedbacks" => $feedbackUnread,
        "ratings" => $ratingUnread,
        "total" => $orderUnread + $feedbackUnread + $ratingUnread,
    ],
    "items" => $items,
]);
?>
