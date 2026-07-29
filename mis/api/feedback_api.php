<?php
include __DIR__ . "/../connection.php";

header("Content-Type: application/json; charset=utf-8");

function json_out($arr){ echo json_encode($arr); exit; }
function clean_text($v, $max = 255){ $v = trim((string)$v); return substr($v, 0, $max); }

$conn->set_charset("utf8mb4");
// Visibility flags ensure only approved feedback/ratings are shown on website.
mysqli_query($conn, "ALTER TABLE tblcomment ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 0");
mysqli_query($conn, "ALTER TABLE tblratings ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");

$action = $_GET["action"] ?? $_POST["action"] ?? "";

if ($action === "list") {
    // Moderation list query for feedback or ratings with search + pagination.
    $kind = strtolower(clean_text($_GET["kind"] ?? "feedback", 20));
    $search = clean_text($_GET["search"] ?? "", 120);
    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = max(5, min(200, (int)($_GET["per_page"] ?? 10)));
    $offset = ($page - 1) * $perPage;

    if ($kind === "feedback") {
        $where = "1=1";
        $types = "";
        $params = [];
        if ($search !== "") {
            $where .= " AND (name LIKE ? OR comment LIKE ?)";
            $types .= "ss";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $countSql = "SELECT COUNT(*) AS total FROM tblcomment WHERE {$where}";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) json_out(["status"=>"error","message"=>"Count query failed"]);
        if ($types !== "") {
            $b = [$types]; foreach ($params as $k => $v) $b[] = &$params[$k];
            call_user_func_array([$countStmt, "bind_param"], $b);
        }
        $countStmt->execute();
        $total = (int)(($countStmt->get_result()->fetch_assoc()["total"] ?? 0));
        $countStmt->close();

        $sql = "SELECT com_id AS id, name, comment, status FROM tblcomment WHERE {$where} ORDER BY com_id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_out(["status"=>"error","message"=>"List query failed"]);
        $listTypes = $types . "ii";
        $listParams = $params; $listParams[] = $perPage; $listParams[] = $offset;
        $b2 = [$listTypes]; foreach ($listParams as $k => $v) $b2[] = &$listParams[$k];
        call_user_func_array([$stmt, "bind_param"], $b2);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($res && $r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        json_out(["status"=>"success","rows"=>$rows,"pagination"=>["page"=>$page,"per_page"=>$perPage,"total"=>$total,"total_pages"=>max(1,(int)ceil($total/$perPage))]]);
    }

    if ($kind === "rating") {
        $where = "1=1";
        $types = "";
        $params = [];
        if ($search !== "") {
            $where .= " AND (COALESCE(p.name,'') LIKE ? OR review_text LIKE ? OR order_id LIKE ?)";
            $types .= "sss";
            $like = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $countSql = "SELECT COUNT(*) AS total
                     FROM tblratings r
                     LEFT JOIN tblproduct p ON r.product_id = p.product_id
                     WHERE {$where}";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) json_out(["status"=>"error","message"=>"Count query failed"]);
        if ($types !== "") {
            $b = [$types]; foreach ($params as $k => $v) $b[] = &$params[$k];
            call_user_func_array([$countStmt, "bind_param"], $b);
        }
        $countStmt->execute();
        $total = (int)(($countStmt->get_result()->fetch_assoc()["total"] ?? 0));
        $countStmt->close();

        $sql = "SELECT r.rating_id AS id, r.order_id, r.product_id, r.rating, r.review_text, r.created_at, r.status,
                       COALESCE(p.name, CONCAT('Product #', r.product_id)) AS product_name
                FROM tblratings r
                LEFT JOIN tblproduct p ON r.product_id = p.product_id
                WHERE {$where}
                ORDER BY r.rating_id DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_out(["status"=>"error","message"=>"List query failed"]);
        $listTypes = $types . "ii";
        $listParams = $params; $listParams[] = $perPage; $listParams[] = $offset;
        $b2 = [$listTypes]; foreach ($listParams as $k => $v) $b2[] = &$listParams[$k];
        call_user_func_array([$stmt, "bind_param"], $b2);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($res && $r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        json_out(["status"=>"success","rows"=>$rows,"pagination"=>["page"=>$page,"per_page"=>$perPage,"total"=>$total,"total_pages"=>max(1,(int)ceil($total/$perPage))]]);
    }
}

if ($action === "toggle_status") {
    // Show/hide moderation update query for website visibility.
    $kind = strtolower(clean_text($_POST["kind"] ?? "", 20));
    $id = (int)($_POST["id"] ?? 0);
    $status = ((int)($_POST["status"] ?? 0)) ? 1 : 0;
    if ($id <= 0) json_out(["status"=>"error","message"=>"Invalid ID"]);
    if ($kind === "feedback") {
        $stmt = $conn->prepare("UPDATE tblcomment SET status = ? WHERE com_id = ?");
    } elseif ($kind === "rating") {
        $stmt = $conn->prepare("UPDATE tblratings SET status = ? WHERE rating_id = ?");
    } else {
        json_out(["status"=>"error","message"=>"Invalid type"]);
    }
    if (!$stmt) json_out(["status"=>"error","message"=>"Unable to update"]);
    $stmt->bind_param("ii", $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status"=>"error","message"=>"Update failed"]);
    json_out(["status"=>"success","message"=>"Visibility updated"]);
}

if ($action === "delete") {
    // Hard delete query for moderator-removed feedback/rating records.
    $kind = strtolower(clean_text($_POST["kind"] ?? "", 20));
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) json_out(["status"=>"error","message"=>"Invalid ID"]);
    if ($kind === "feedback") {
        $stmt = $conn->prepare("DELETE FROM tblcomment WHERE com_id = ?");
    } elseif ($kind === "rating") {
        $stmt = $conn->prepare("DELETE FROM tblratings WHERE rating_id = ?");
    } else {
        json_out(["status"=>"error","message"=>"Invalid type"]);
    }
    if (!$stmt) json_out(["status"=>"error","message"=>"Unable to delete"]);
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) json_out(["status"=>"error","message"=>"Delete failed"]);
    json_out(["status"=>"success","message"=>"Record deleted"]);
}

json_out(["status"=>"error","message"=>"Invalid action"]);
