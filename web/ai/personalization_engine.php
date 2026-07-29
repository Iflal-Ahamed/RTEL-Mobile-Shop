<?php
/**
 * AI Personalization Engine (rule-weighted scoring)
 * -------------------------------------------------
 * This module computes personalized product suggestions using:
 * - browsing events (view, wishlist, add-to-cart)
 * - purchase history (order details)
 * - recency decay (recent interactions carry more score)
 *
 * The scoring is deterministic and explainable, suitable for project demos/evaluation.
 */

require_once __DIR__ . '/chat_ai_provider.php';

$GLOBALS['rtel_ai_personalization_mode'] = 'fallback';
$GLOBALS['rtel_ai_personalization_provider'] = '';

/**
 * Ensure required behavior table exists.
 */
function rtel_ai_sync_timezone(mysqli $conn)
{
    // Keep AI event timestamps aligned with local project timezone (Sri Lanka).
    date_default_timezone_set('Asia/Colombo');
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

function rtel_ai_normalize_product_id($productId)
{
    if (is_array($productId)) {
        // Handle payloads like product_id[]=... safely without array-to-string warnings.
        $productId = reset($productId);
    }
    if (is_object($productId) && !method_exists($productId, '__toString')) {
        return "";
    }
    $raw = trim((string)$productId);
    if ($raw === "") {
        return "";
    }
    // Accept values like "3", "003", "product_3", "PRODUCT_12".
    if (preg_match('/(\d+)/', $raw, $m)) {
        return (string)((int)$m[1]);
    }
    return $raw;
}

function rtel_ai_tokens($text)
{
    if (is_array($text)) {
        $text = implode(' ', array_map(function ($v) {
            if (is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))) {
                return (string)$v;
            }
            return '';
        }, $text));
    } elseif (is_object($text) && !method_exists($text, '__toString')) {
        $text = '';
    }
    $text = strtolower(trim((string)$text));
    if ($text === "") return [];
    $text = preg_replace('/[^a-z0-9\s]+/i', ' ', $text);
    $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = ['the','and','for','with','new','pro','max','plus','ultra','mobile','phone','model','gb'];
    $out = [];
    foreach ($parts as $p) {
        if (strlen($p) < 2 || in_array($p, $stop, true)) continue;
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function rtel_ai_ensure_behavior_table(mysqli $conn)
{
    rtel_ai_sync_timezone($conn);
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblai_user_behavior (
        behavior_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        cus_id VARCHAR(250) NOT NULL,
        product_id VARCHAR(20) NOT NULL,
        event_type VARCHAR(30) NOT NULL,
        event_weight DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
        event_date DATETIME NOT NULL,
        INDEX idx_ai_user_date (cus_id, event_date),
        INDEX idx_ai_product (product_id)
    )");
}

function rtel_ai_ensure_search_history_table(mysqli $conn)
{
    rtel_ai_sync_timezone($conn);
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblai_user_search_history (
        search_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        cus_id VARCHAR(250) NOT NULL,
        search_text VARCHAR(255) NOT NULL,
        search_tokens VARCHAR(255) NOT NULL DEFAULT '',
        search_date DATETIME NOT NULL,
        INDEX idx_ai_search_user_date (cus_id, search_date)
    )");
}

function rtel_ai_personalization_real_ai_enabled()
{
    $v = strtolower(trim((string)(getenv('AI_PERSONALIZATION_REAL_AI') ?: ($_SERVER['AI_PERSONALIZATION_REAL_AI'] ?? $_ENV['AI_PERSONALIZATION_REAL_AI'] ?? ''))));
    if ($v !== '') {
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
    // Auto-enable when an AI provider is configured.
    if (function_exists('getAiConfiguration')) {
        $cfg = getAiConfiguration();
        if (is_array($cfg) && !empty($cfg['api_key'])) {
            return true;
        }
    }
    return false;
}

function rtel_ai_extract_id_array_from_text($text)
{
    if (is_array($text)) {
        $text = json_encode($text);
    } elseif (is_object($text) && !method_exists($text, '__toString')) {
        return [];
    }
    $raw = trim((string)$text);
    if ($raw === '') return [];
    $start = strpos($raw, '[');
    $end = strrpos($raw, ']');
    if ($start === false || $end === false || $end <= $start) return [];
    $json = substr($raw, $start, $end - $start + 1);
    $arr = json_decode($json, true);
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $id) {
        $id = rtel_ai_normalize_product_id($id);
        if ($id !== '') $out[] = $id;
    }
    return array_values(array_unique($out));
}

function rtel_ai_rerank_with_real_ai($cusId, array $rows, array $userBrandAffinity, array $userCategoryAffinity, array $userTokens, $limit)
{
    $GLOBALS['rtel_ai_personalization_mode'] = 'fallback';
    $GLOBALS['rtel_ai_personalization_provider'] = '';
    if (!rtel_ai_personalization_real_ai_enabled()) return $rows;
    if (!function_exists('getAiConfiguration') || !function_exists('callRealAiModel')) return $rows;
    $cfg = getAiConfiguration();
    if (!$cfg || empty($cfg['api_key'])) return $rows;
    if (count($rows) < 2) return $rows;

    $cand = array_slice($rows, 0, min(24, count($rows)));
    $brandPref = array_keys(array_slice($userBrandAffinity, 0, 5, true));
    $catPref = array_keys(array_slice($userCategoryAffinity, 0, 5, true));
    arsort($userTokens);
    $tokenPref = array_slice(array_keys($userTokens), 0, 10);

    $payload = [];
    foreach ($cand as $r) {
        $payload[] = [
            'product_id' => (string)($r['product_id'] ?? ''),
            'name' => (string)($r['name'] ?? ''),
            'model' => (string)($r['modal'] ?? ''),
            'brand_id' => (string)($r['brand_id'] ?? ''),
            'category_id' => (string)($r['cat_id'] ?? ''),
            'price' => (float)($r['price'] ?? 0),
            'compare_price' => (float)($r['cprice'] ?? 0),
            'quantity' => (int)($r['quantity'] ?? 0),
            'ai_score' => (float)($r['_ai_score'] ?? 0),
        ];
    }

    $messages = [
        [
            'role' => 'system',
            'content' => "You are a product recommendation reranker for an ecommerce mobile shop. Return ONLY a JSON array of product_id values ordered best-to-worst. No markdown, no extra text."
        ],
        [
            'role' => 'user',
            'content' => "User: " . (string)$cusId
                . "\nPreferred brand_ids: " . json_encode($brandPref)
                . "\nPreferred category_ids: " . json_encode($catPref)
                . "\nPreferred tokens: " . json_encode($tokenPref)
                . "\nCandidates JSON:\n" . json_encode($payload)
                . "\nNeed top " . (int)$limit . " ids."
        ]
    ];

    $answer = callRealAiModel($messages, 0.2);
    $orderedIds = rtel_ai_extract_id_array_from_text($answer);
    if (count($orderedIds) === 0) return $rows;
    $GLOBALS['rtel_ai_personalization_mode'] = 'ai';
    $GLOBALS['rtel_ai_personalization_provider'] = (string)($cfg['provider'] ?? '');

    $rank = array_flip($orderedIds);
    usort($rows, function ($a, $b) use ($rank) {
        $ida = (string)($a['product_id'] ?? '');
        $idb = (string)($b['product_id'] ?? '');
        $ha = array_key_exists($ida, $rank);
        $hb = array_key_exists($idb, $rank);
        if ($ha && $hb) return $rank[$ida] <=> $rank[$idb];
        if ($ha) return -1;
        if ($hb) return 1;
        $sa = (float)($a['_ai_score'] ?? 0);
        $sb = (float)($b['_ai_score'] ?? 0);
        if ($sa !== $sb) return $sb <=> $sa;
        return (int)($b['quantity'] ?? 0) <=> (int)($a['quantity'] ?? 0);
    });
    return $rows;
}

function rtel_ai_personalization_last_mode()
{
    return (string)($GLOBALS['rtel_ai_personalization_mode'] ?? 'fallback');
}

function rtel_ai_personalization_last_provider()
{
    return (string)($GLOBALS['rtel_ai_personalization_provider'] ?? '');
}

/**
 * Returns default event weight by event type.
 */
function rtel_ai_event_weight($eventType)
{
    $eventType = strtolower(trim((string)$eventType));
    $map = [
        "view" => 1.0,
        "wishlist" => 3.0,
        "add_cart" => 5.0,
        "purchase" => 7.0
    ];
    return isset($map[$eventType]) ? (float)$map[$eventType] : 1.0;
}

/**
 * Track one user-product interaction.
 */
function rtel_ai_track_behavior(mysqli $conn, $cusId, $productId, $eventType, $weight = null)
{
    rtel_ai_sync_timezone($conn);
    $cusId = trim((string)$cusId);
    $productId = rtel_ai_normalize_product_id($productId);
    $eventType = strtolower(trim((string)$eventType));
    if ($cusId === "" || $productId === "" || $eventType === "") {
        return false;
    }
    rtel_ai_ensure_behavior_table($conn);
    $w = ($weight === null) ? rtel_ai_event_weight($eventType) : (float)$weight;
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO tblai_user_behavior (cus_id, product_id, event_type, event_weight, event_date) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("sssds", $cusId, $productId, $eventType, $w, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function rtel_ai_track_search(mysqli $conn, $cusId, $searchText)
{
    rtel_ai_sync_timezone($conn);
    $cusId = trim((string)$cusId);
    $searchText = trim((string)$searchText);
    if ($cusId === "" || $searchText === "") return false;
    $tokens = rtel_ai_tokens($searchText);
    if (count($tokens) === 0) return false;
    rtel_ai_ensure_search_history_table($conn);
    $tokCsv = implode(' ', array_slice($tokens, 0, 12));
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO tblai_user_search_history (cus_id, search_text, search_tokens, search_date) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("ssss", $cusId, $searchText, $tokCsv, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function rtel_ai_recent_context(mysqli $conn, $cusId)
{
    $out = [
        "recent_brand_affinity" => [],
        "recent_category_affinity" => [],
        "recent_tokens" => []
    ];
    $cusId = trim((string)$cusId);
    if ($cusId === "") return $out;

    // Recent browsing history (views/wishlist/cart) carries stronger intent.
    $sql = "SELECT p.brand_id, p.cat_id, p.name, p.modal,
                   SUM(b.event_weight * (1 / (1 + TIMESTAMPDIFF(DAY, b.event_date, NOW()) / 4.0))) AS rs
            FROM tblai_user_behavior b
            JOIN tblproduct p ON CAST(p.product_id AS CHAR) = REPLACE(LOWER(b.product_id), 'product_', '')
            WHERE b.cus_id = ?
              AND b.event_type IN ('view','wishlist','add_cart')
              AND b.event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.product_id, p.brand_id, p.cat_id, p.name, p.modal
            ORDER BY rs DESC
            LIMIT 80";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $cusId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $r = $res->fetch_assoc()) {
            $bid = (string)($r["brand_id"] ?? "");
            $cid = (string)($r["cat_id"] ?? "");
            $score = (float)($r["rs"] ?? 0);
            if ($bid !== "") $out["recent_brand_affinity"][$bid] = ($out["recent_brand_affinity"][$bid] ?? 0) + $score;
            if ($cid !== "") $out["recent_category_affinity"][$cid] = ($out["recent_category_affinity"][$cid] ?? 0) + $score;
            $tok = rtel_ai_tokens((string)($r["name"] ?? '') . ' ' . (string)($r["modal"] ?? ''));
            foreach ($tok as $t) {
                $out["recent_tokens"][$t] = ($out["recent_tokens"][$t] ?? 0) + $score;
            }
        }
        $stmt->close();
    }

    // Recent search text intent.
    rtel_ai_ensure_search_history_table($conn);
    $stmtS = $conn->prepare("SELECT search_tokens, search_date
                             FROM tblai_user_search_history
                             WHERE cus_id = ?
                               AND search_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             ORDER BY search_id DESC
                             LIMIT 40");
    if ($stmtS) {
        $stmtS->bind_param("s", $cusId);
        $stmtS->execute();
        $res = $stmtS->get_result();
        while ($res && $r = $res->fetch_assoc()) {
            $ageDays = 1;
            if (!empty($r["search_date"])) {
                $ageDays = max(1, (int)floor((time() - strtotime((string)$r["search_date"])) / 86400));
            }
            $w = 1 / (1 + ($ageDays / 5.0));
            $tokens = rtel_ai_tokens((string)($r["search_tokens"] ?? ""));
            foreach ($tokens as $t) {
                $out["recent_tokens"][$t] = ($out["recent_tokens"][$t] ?? 0) + (2.8 * $w);
            }
        }
        $stmtS->close();
    }
    return $out;
}

function rtel_ai_recent_viewed_ids(mysqli $conn, $cusId, $limit = 10)
{
    $cusId = trim((string)$cusId);
    if ($cusId === "") return [];
    $limit = max(1, (int)$limit);
    rtel_ai_ensure_behavior_table($conn);
    $ids = [];
    $stmt = $conn->prepare("SELECT REPLACE(LOWER(product_id), 'product_', '') AS pid
                            FROM tblai_user_behavior
                            WHERE cus_id = ?
                              AND event_type = 'view'
                              AND event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            ORDER BY behavior_id DESC
                            LIMIT " . $limit);
    if ($stmt) {
        $stmt->bind_param("s", $cusId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $r = $res->fetch_assoc()) {
            $pid = trim((string)($r["pid"] ?? ""));
            if ($pid !== "" && !in_array($pid, $ids, true)) {
                $ids[] = $pid;
            }
        }
        $stmt->close();
    }
    return $ids;
}

/**
 * Get top preferred brands from recent interactions + purchases.
 */
function rtel_ai_top_user_brands(mysqli $conn, $cusId, $limit = 4)
{
    rtel_ai_sync_timezone($conn);
    $cusId = trim((string)$cusId);
    if ($cusId === "") return [];
    $limit = max(1, (int)$limit);
    rtel_ai_ensure_behavior_table($conn);

    // Behavior brand scores (with recency half-life style decay).
    $behavior = [];
    $sqlB = "SELECT p.brand_id,
                    SUM(b.event_weight * (1 / (1 + TIMESTAMPDIFF(DAY, b.event_date, NOW()) / 15.0))) AS score
             FROM tblai_user_behavior b
             JOIN tblproduct p ON CAST(p.product_id AS CHAR) = REPLACE(LOWER(b.product_id), 'product_', '')
             WHERE b.cus_id = ?
             GROUP BY p.brand_id";
    $stmtB = $conn->prepare($sqlB);
    if ($stmtB) {
        $stmtB->bind_param("s", $cusId);
        $stmtB->execute();
        $res = $stmtB->get_result();
        while ($res && $r = $res->fetch_assoc()) {
            $bid = (string)($r["brand_id"] ?? "");
            if ($bid === "") continue;
            $behavior[$bid] = (float)($r["score"] ?? 0);
        }
        $stmtB->close();
    }

    // Purchase brand scores (strong signal).
    $purchase = [];
    $sqlP = "SELECT p.brand_id, SUM(od.quantity) AS qty_score
             FROM tblorder o
             JOIN tblorder_details od ON o.order_id = od.order_id
             JOIN tblproduct p ON p.product_id = od.product_id
             WHERE o.cus_id = ?
             GROUP BY p.brand_id";
    $stmtP = $conn->prepare($sqlP);
    if ($stmtP) {
        $stmtP->bind_param("s", $cusId);
        $stmtP->execute();
        $res = $stmtP->get_result();
        while ($res && $r = $res->fetch_assoc()) {
            $bid = (string)($r["brand_id"] ?? "");
            if ($bid === "") continue;
            $purchase[$bid] = (float)($r["qty_score"] ?? 0);
        }
        $stmtP->close();
    }

    // Merge weighted scores.
    $scores = [];
    $allKeys = array_unique(array_merge(array_keys($behavior), array_keys($purchase)));
    foreach ($allKeys as $k) {
        $scores[$k] = (($behavior[$k] ?? 0) * 1.0) + (($purchase[$k] ?? 0) * 2.2);
    }
    arsort($scores);
    return array_slice(array_keys($scores), 0, $limit);
}

/**
 * Build personalized products for homepage.
 */
function rtel_ai_personalized_products(mysqli $conn, $cusId, $limit = 8)
{
    rtel_ai_sync_timezone($conn);
    $cusId = trim((string)$cusId);
    $limit = max(1, (int)$limit);
    if ($cusId === "") return [];

    // Strict per-user mode:
    // If this user has no own behavior and no own purchase history yet,
    // do not return generic fallback products (which can look like data leak).
    $hasSignals = false;
    $sigBehavior = $conn->prepare("SELECT COUNT(*) AS c FROM tblai_user_behavior WHERE cus_id = ? LIMIT 1");
    if ($sigBehavior) {
        $sigBehavior->bind_param("s", $cusId);
        $sigBehavior->execute();
        $sigRow = $sigBehavior->get_result()->fetch_assoc();
        $sigBehavior->close();
        if ((int)($sigRow["c"] ?? 0) > 0) {
            $hasSignals = true;
        }
    }
    if (!$hasSignals) {
        $sigPurchase = $conn->prepare("SELECT COUNT(*) AS c FROM tblorder WHERE cus_id = ? LIMIT 1");
        if ($sigPurchase) {
            $sigPurchase->bind_param("s", $cusId);
            $sigPurchase->execute();
            $sigRow = $sigPurchase->get_result()->fetch_assoc();
            $sigPurchase->close();
            if ((int)($sigRow["c"] ?? 0) > 0) {
                $hasSignals = true;
            }
        }
    }
    if (!$hasSignals) {
        return [];
    }

    $preferredBrands = rtel_ai_top_user_brands($conn, $cusId, 4);

    $sql = "SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity, p.brand_id, p.cat_id,
                   COALESCE(i.image_1, 'smartphone.png') AS image_1,
                   COALESCE(b.name, '') AS brand_name,
                   COALESCE(c.name, '') AS category_name
            FROM tblproduct p
            LEFT JOIN tblimage i ON p.product_id = i.product_id
            LEFT JOIN tblbrand b ON p.brand_id = b.brand_id
            LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
            WHERE p.status = 1 AND p.quantity > 0
              AND p.product_id NOT IN (
                SELECT DISTINCT od.product_id
                FROM tblorder o
                JOIN tblorder_details od ON o.order_id = od.order_id
                WHERE o.cus_id = ?
              )
            ORDER BY p.quantity DESC, p.product_id DESC
            LIMIT 80";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $types = "s";
    $params = [$cusId];
    $bind = [$types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, "bind_param"], $bind);
    $stmt->execute();
    $res = $stmt->get_result();

    // Build behavior per product for final score.
    rtel_ai_ensure_behavior_table($conn);
    $behaviorScores = [];
    $stmtB = $conn->prepare("SELECT REPLACE(LOWER(product_id), 'product_', '') AS norm_product_id,
        SUM(event_weight * (1 / (1 + TIMESTAMPDIFF(DAY, event_date, NOW()) / 15.0))) AS score
        FROM tblai_user_behavior
        WHERE cus_id = ?
        GROUP BY REPLACE(LOWER(product_id), 'product_', '')");
    if ($stmtB) {
        $stmtB->bind_param("s", $cusId);
        $stmtB->execute();
        $rs = $stmtB->get_result();
        while ($rs && $r = $rs->fetch_assoc()) {
            $pid = (string)($r["norm_product_id"] ?? "");
            if ($pid === "") continue;
            $behaviorScores[$pid] = (float)($r["score"] ?? 0);
        }
        $stmtB->close();
    }

    $brandSet = array_flip($preferredBrands);
    $recentCtx = rtel_ai_recent_context($conn, $cusId);
    $userBrandAffinity = [];
    $userCategoryAffinity = [];
    $userTokens = [];

    $affStmt = $conn->prepare("SELECT p.brand_id, p.cat_id, p.name, p.modal,
        SUM(b.event_weight * (1 / (1 + TIMESTAMPDIFF(DAY, b.event_date, NOW()) / 10.0))) AS af
        FROM tblai_user_behavior b
        JOIN tblproduct p ON CAST(p.product_id AS CHAR) = REPLACE(LOWER(b.product_id), 'product_', '')
        WHERE b.cus_id = ?
        GROUP BY p.product_id, p.brand_id, p.cat_id, p.name, p.modal
        ORDER BY af DESC
        LIMIT 120");
    if ($affStmt) {
        $affStmt->bind_param("s", $cusId);
        $affStmt->execute();
        $afRes = $affStmt->get_result();
        while ($afRes && $af = $afRes->fetch_assoc()) {
            $bid = (string)($af["brand_id"] ?? "");
            $cid = (string)($af["cat_id"] ?? "");
            $score = (float)($af["af"] ?? 0);
            if ($bid !== "") $userBrandAffinity[$bid] = ($userBrandAffinity[$bid] ?? 0) + $score;
            if ($cid !== "") $userCategoryAffinity[$cid] = ($userCategoryAffinity[$cid] ?? 0) + $score;
            $tok = rtel_ai_tokens((string)($af["name"] ?? '') . ' ' . (string)($af["modal"] ?? ''));
            foreach ($tok as $t) {
                $userTokens[$t] = ($userTokens[$t] ?? 0) + $score;
            }
        }
        $affStmt->close();
    }

    $rows = [];
    while ($res && $row = $res->fetch_assoc()) {
        $pid = (string)($row["product_id"] ?? "");
        $score = 0.0;
        $score += (float)($behaviorScores[$pid] ?? 0) * 1.4;
        if (isset($brandSet[(string)($row["brand_id"] ?? "")])) {
            $score += 8.0;
        }
        $bid = (string)($row["brand_id"] ?? "");
        $cid = (string)($row["cat_id"] ?? "");
        if ($bid !== "") $score += (float)($userBrandAffinity[$bid] ?? 0) * 0.9;
        if ($cid !== "") $score += (float)($userCategoryAffinity[$cid] ?? 0) * 0.65;
        if ($bid !== "") $score += (float)($recentCtx["recent_brand_affinity"][$bid] ?? 0) * 1.15;
        if ($cid !== "") $score += (float)($recentCtx["recent_category_affinity"][$cid] ?? 0) * 0.95;

        $rowTokens = rtel_ai_tokens((string)($row["name"] ?? '') . ' ' . (string)($row["modal"] ?? ''));
        $tokenBoost = 0.0;
        foreach ($rowTokens as $t) {
            $tokenBoost += (float)($userTokens[$t] ?? 0);
            $tokenBoost += (float)($recentCtx["recent_tokens"][$t] ?? 0);
        }
        if ($tokenBoost > 0) {
            $score += min(12.0, $tokenBoost * 0.12);
        }
        // Small bonus for discounted in-stock products.
        $price = (float)($row["price"] ?? 0);
        $cprice = (float)($row["cprice"] ?? 0);
        if ($cprice > $price && $price > 0) {
            $score += 2.0;
        }
        $qty = (int)($row["quantity"] ?? 0);
        if ($qty >= 10) {
            $score += 1.0;
        }
        $row["_ai_score"] = $score;
        $rows[] = $row;
    }
    $stmt->close();

    usort($rows, function ($a, $b) {
        $sa = (float)($a["_ai_score"] ?? 0);
        $sb = (float)($b["_ai_score"] ?? 0);
        if ($sa !== $sb) return $sb <=> $sa;
        return (int)($b["quantity"] ?? 0) <=> (int)($a["quantity"] ?? 0);
    });
    // Optional hybrid mode: deterministic score first, then real-AI rerank.
    $rows = rtel_ai_rerank_with_real_ai($cusId, $rows, $userBrandAffinity, $userCategoryAffinity, $userTokens, $limit);
    $topRows = array_slice($rows, 0, $limit);

    // Guarantee: include at least one recently viewed in-stock product when possible.
    $recentViewed = rtel_ai_recent_viewed_ids($conn, $cusId, 12);
    if (count($recentViewed) > 0 && count($rows) > 0) {
        $topIds = [];
        foreach ($topRows as $r) {
            $topIds[] = (string)($r["product_id"] ?? "");
        }
        $hasRecentInTop = false;
        foreach ($recentViewed as $rv) {
            if (in_array($rv, $topIds, true)) {
                $hasRecentInTop = true;
                break;
            }
        }
        if (!$hasRecentInTop) {
            $recentCandidate = null;
            foreach ($rows as $r) {
                $pid = (string)($r["product_id"] ?? "");
                if (in_array($pid, $recentViewed, true)) {
                    $recentCandidate = $r;
                    break;
                }
            }
            if ($recentCandidate !== null) {
                if (count($topRows) >= $limit && $limit > 0) {
                    array_pop($topRows);
                }
                array_unshift($topRows, $recentCandidate);
            }
        }
    }
    return array_slice($topRows, 0, $limit);
}

