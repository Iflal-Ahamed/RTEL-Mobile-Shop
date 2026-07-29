<?php
/**
 * Compare AI API (GSMArena-style + DB availability)
 * =================================================
 * This endpoint compares 2 or more products using GSMArena spec pages.
 * After web comparison, it checks whether each product is available in R-TEL DB.
 */
header("Content-Type: application/json; charset=utf-8");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../connection.php";
require_once __DIR__ . "/chat_ai_provider.php";
$GLOBALS['rtel_compare_reply_source'] = 'fallback';
$GLOBALS['rtel_compare_ai_provider'] = '';

/**
 * Sends JSON response and exits.
 */
function compareRespond($payload)
{
    echo json_encode($payload);
    exit();
}

/** Basic HTTP GET helper used for GSMArena requests. */
function compareHttpGet($url, $timeout = 12)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "User-Agent: R-TEL-Compare-AI/1.0"
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return is_string($body) ? $body : "";
}

/** Normalizes free-text product names input. */
function normalizeProductNames($input)
{
    $items = [];
    if (is_array($input)) {
        $items = $input;
    } else {
        $raw = (string)$input;
        $parts = preg_split('/[\r\n,|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $items = is_array($parts) ? $parts : [];
    }

    $out = [];
    foreach ($items as $v) {
        $name = trim((string)$v);
        if ($name === "" || mb_strlen($name) < 2) {
            continue;
        }
        $out[] = $name;
    }
    $out = array_values(array_unique($out));
    return array_slice($out, 0, 6);
}

function nameTokens($text)
{
    $t = mb_strtolower((string)$text);
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);
    $parts = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) return [];
    $stop = ["the", "and", "or", "phone", "mobile", "smartphone", "with", "new", "5g"];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === "" || mb_strlen($p) < 2 || in_array($p, $stop, true)) continue;
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function isCandidateRelated($query, $candidateName)
{
    $qTokens = nameTokens($query);
    $cTokens = nameTokens($candidateName);
    $qNums = numericTokens($query);
    $cNums = numericTokens($candidateName);
    if (count($qTokens) === 0 || count($cTokens) === 0) return false;
    $hits = 0;
    foreach ($qTokens as $t) {
        if (in_array($t, $cTokens, true)) $hits++;
    }
    $tokenRatio = $hits / max(1, count($qTokens));
    $numericGate = (count($qNums) > 0) ? (count(array_diff($qNums, $cNums)) === 0) : true;
    return $numericGate && ($tokenRatio >= 0.6);
}

function gsmCandidateScore($query, $candidateName)
{
    $qNorm = normalizeForCompare($query);
    $cNorm = normalizeForCompare($candidateName);
    $qTokens = nameTokens($query);
    $cTokens = nameTokens($candidateName);
    $qNums = numericTokens($query);
    $cNums = numericTokens($candidateName);

    $hits = 0;
    foreach ($qTokens as $t) {
        if (in_array($t, $cTokens, true)) $hits++;
    }
    $ratio = (count($qTokens) > 0) ? ($hits / count($qTokens)) : 0.0;
    $numericRatio = (count($qNums) > 0) ? (count(array_intersect($qNums, $cNums)) / max(1, count($qNums))) : 1.0;
    $exact = ($qNorm !== '' && $qNorm === $cNorm) ? 1.0 : 0.0;
    $phrase = ($qNorm !== '' && mb_strpos($cNorm, $qNorm) !== false) ? 1.0 : 0.0;
    $extraPenalty = max(0, count($cTokens) - count($qTokens)) * 0.05;
    return ($exact * 5.0) + ($phrase * 2.0) + ($ratio * 2.0) + ($numericRatio * 1.5) - $extraPenalty;
}

/**
 * Extracts first GSMArena device URL from search results page.
 * Example search URL: https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=<query>
 */
function findGsmArenaDeviceUrl($productName)
{
    $candidates = findGsmArenaCandidates($productName, 1);
    if (!empty($candidates[0]["url"])) return (string)$candidates[0]["url"];
    return "";
}

/**
 * Returns top GSMArena candidate devices from search results.
 */
function findGsmArenaCandidates($productName, $limit = 5)
{
    $q = trim((string)$productName);
    if ($q === "") return [];
    $searchUrl = "https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=" . rawurlencode($q);
    $html = compareHttpGet($searchUrl, 14);
    if ($html === "") return [];
    $matches = [];
    // Preferred: makers list block.
    if (preg_match('/<div class="makers">(.*?)<\/div>/is', $html, $blockMatch)) {
        $block = (string)$blockMatch[1];
        if (preg_match_all('/<a\s+href="([^"]+\.php)".*?<strong>(.*?)<\/strong>/is', $block, $m1, PREG_SET_ORDER)) {
            $matches = $m1;
        }
    }
    // Fallback 1: parse any result anchors with strong tags.
    if (count($matches) === 0 && preg_match_all('/<a\s+href="([^"]+-\d+\.php)".*?<strong>(.*?)<\/strong>/is', $html, $m2, PREG_SET_ORDER)) {
        $matches = $m2;
    }
    // Fallback 2: parse list item title text if <strong> not present.
    if (count($matches) === 0 && preg_match_all('/<a\s+href="([^"]+-\d+\.php)"[^>]*>\s*([^<][^<]{2,120})\s*<\/a>/is', $html, $m3, PREG_SET_ORDER)) {
        $matches = $m3;
    }
    if (count($matches) === 0) return [];

    $out = [];
    $seen = [];
    foreach ($matches as $row) {
        $href = trim((string)($row[1] ?? ""));
        $name = cleanGsmText($row[2] ?? "");
        if ($href === "" || $name === "") continue;
        $url = stripos($href, "http") === 0 ? $href : ("https://www.gsmarena.com/" . ltrim($href, "/"));
        $k = strtolower($name);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = ["name" => $name, "url" => $url];
        if (count($out) >= max(1, (int)$limit)) break;
    }
    return $out;
}

/**
 * Cleans HTML fragments to readable plain text.
 */
function cleanGsmText($text)
{
    $t = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, "UTF-8");
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim((string)$t);
}

function parseGsmKeyValuePairs($html)
{
    $pairs = [];
    if (!preg_match_all('/<td[^>]*class="ttl"[^>]*>(.*?)<\/td>\s*<td[^>]*class="nfo"[^>]*>(.*?)<\/td>/is', (string)$html, $m, PREG_SET_ORDER)) {
        return $pairs;
    }
    foreach ($m as $row) {
        $k = mb_strtolower(cleanGsmText($row[1] ?? ""));
        $v = cleanGsmText($row[2] ?? "");
        if ($k === '' || $v === '') {
            continue;
        }
        if (!isset($pairs[$k])) {
            $pairs[$k] = [];
        }
        $pairs[$k][] = $v;
    }
    return $pairs;
}

function pickSpecValue(array $pairs, array $keys, callable $matcher = null)
{
    foreach ($keys as $k) {
        $kk = mb_strtolower(trim((string)$k));
        if (!isset($pairs[$kk]) || !is_array($pairs[$kk])) {
            continue;
        }
        foreach ($pairs[$kk] as $v) {
            $vv = trim((string)$v);
            if ($vv === '') {
                continue;
            }
            if ($matcher && !$matcher($vv)) {
                continue;
            }
            return $vv;
        }
    }
    return '';
}

/**
 * Parses key specs from a GSMArena device page.
 * Output keys are normalized so UI can show side-by-side rows.
 */
function parseGsmArenaSpecs($deviceUrl)
{
    $html = compareHttpGet($deviceUrl, 14);
    if ($html === "") {
        return [];
    }
    $pairs = parseGsmKeyValuePairs($html);
    $specs = [];

    $specs["announced"] = pickSpecValue($pairs, ["announced"]);
    $specs["display_type"] = pickSpecValue($pairs, ["type"], function ($v) {
        return (bool)preg_match('/oled|amoled|lcd|retina|display|hz|inch|inches|resolution/i', $v);
    });
    $specs["display_size"] = pickSpecValue($pairs, ["size"], function ($v) {
        return (bool)preg_match('/inch|inches|cm2|resolution|ppi/i', $v);
    });
    $specs["os"] = pickSpecValue($pairs, ["os"]);
    $specs["chipset"] = pickSpecValue($pairs, ["chipset"]);
    $specs["cpu"] = pickSpecValue($pairs, ["cpu"]);
    $specs["gpu"] = pickSpecValue($pairs, ["gpu"]);
    $specs["memory"] = pickSpecValue($pairs, ["internal", "card slot"]);
    $specs["charging"] = pickSpecValue($pairs, ["charging"]);

    // Section-based camera extraction for better accuracy.
    if (preg_match('/<a name="maincamera">.*?<\/table>/is', $html, $mm)) {
        $camPairs = parseGsmKeyValuePairs($mm[0]);
        $specs["main_camera"] = pickSpecValue($camPairs, ["triple", "dual", "quad", "penta", "single"]);
    }
    if (preg_match('/<a name="selfiecamera">.*?<\/table>/is', $html, $sm)) {
        $selfPairs = parseGsmKeyValuePairs($sm[0]);
        $specs["selfie_camera"] = pickSpecValue($selfPairs, ["single", "dual"]);
    }
    if (preg_match('/<a name="battery">.*?<\/table>/is', $html, $bm)) {
        $batPairs = parseGsmKeyValuePairs($bm[0]);
        $specs["battery"] = pickSpecValue($batPairs, ["type"], function ($v) {
            return (bool)preg_match('/mah|li-po|li-ion|battery/i', $v);
        });
    }

    // Last-resort fallbacks.
    if (empty($specs["main_camera"])) {
        $specs["main_camera"] = pickSpecValue($pairs, ["triple", "dual", "quad", "penta", "single"]);
    }
    if (empty($specs["selfie_camera"])) {
        $specs["selfie_camera"] = pickSpecValue($pairs, ["single"], function ($v) {
            return (bool)preg_match('/mp|camera|hdr|video/i', $v);
        });
    }
    if (empty($specs["battery"])) {
        $specs["battery"] = pickSpecValue($pairs, ["type"], function ($v) {
            return (bool)preg_match('/mah|battery|li-po|li-ion/i', $v);
        });
    }

    // Remove empty values so UI can still show N/A only when truly missing.
    return array_filter($specs, function ($v) {
        return trim((string)$v) !== '';
    });
}

function empty_if_na($v)
{
    $t = trim((string)$v);
    if ($t === '') return '';
    if (preg_match('/^(n\/a|na|none|null|unknown|-+)$/i', $t)) return '';
    return $t;
}

function compare_set_spec_if_empty(array &$specs, $key, $value)
{
    $k = trim((string)$key);
    if ($k === '') return;
    $v = empty_if_na($value);
    if ($v === '') return;
    if (empty_if_na($specs[$k] ?? '') === '') {
        $specs[$k] = $v;
    }
}

function normalize_compare_text($text)
{
    $t = mb_strtolower((string)$text);
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim((string)$t);
}

function findRtelProductForCompareByName(mysqli $conn, $requestedName)
{
    $res = mysqli_query($conn, "SELECT p.product_id, p.name, p.modal, COALESCE(b.name,'') AS brand_name
                                FROM tblproduct p
                                LEFT JOIN tblbrand b ON p.brand_id=b.brand_id
                                WHERE p.status = 1
                                LIMIT 250");
    if (!$res) return null;
    $reqNorm = normalize_compare_text($requestedName);
    $reqTokens = dbMatchKeywords($reqNorm);
    $reqNums = numericTokens($reqNorm);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rowNorm = normalize_compare_text((string)($r['name'] ?? '') . ' ' . (string)($r['modal'] ?? '') . ' ' . (string)($r['brand_name'] ?? ''));
        $rows[] = [
            'product_id' => (int)($r['product_id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'modal' => (string)($r['modal'] ?? ''),
            'brand_name' => (string)($r['brand_name'] ?? ''),
            'score' => [
                'phrase' => ($reqNorm !== '' && mb_strpos($rowNorm, $reqNorm) !== false) ? 1 : 0,
                'num' => tokenOverlapRatio($reqNums, numericTokens($rowNorm)),
                'tok' => tokenOverlapRatio($reqTokens, dbMatchKeywords($rowNorm)),
                'norm' => $rowNorm
            ]
        ];
    }
    if (count($rows) === 0) return null;
    usort($rows, function ($a, $b) {
        if ((int)$a['score']['phrase'] !== (int)$b['score']['phrase']) {
            return (int)$b['score']['phrase'] <=> (int)$a['score']['phrase'];
        }
        if ((float)$a['score']['num'] !== (float)$b['score']['num']) {
            return (float)$b['score']['num'] <=> (float)$a['score']['num'];
        }
        return (float)$b['score']['tok'] <=> (float)$a['score']['tok'];
    });
    $best = $rows[0];
    $goodEnough = ((int)$best['score']['phrase'] === 1)
        || ((float)$best['score']['num'] >= 1.0 && (float)$best['score']['tok'] >= 0.6)
        || ((float)$best['score']['tok'] >= 0.75);
    return $goodEnough ? $best : null;
}

function buildRtelCompareSpecs(mysqli $conn, $requestedName)
{
    $best = findRtelProductForCompareByName($conn, $requestedName);
    if (!$best || (int)($best['product_id'] ?? 0) <= 0) {
        return null;
    }
    $pid = (int)$best['product_id'];
    $specs = [];
    $featureQ = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature WHERE product_id = ? ORDER BY feature_id ASC");
    if ($featureQ) {
        $featureQ->bind_param("i", $pid);
        $featureQ->execute();
        $fr = $featureQ->get_result();
        while ($fr && $row = $fr->fetch_assoc()) {
            $fname = normalize_compare_text((string)($row['feature_name'] ?? ''));
            $fval = (string)($row['feature_value'] ?? '');
            if ($fname === '' || empty_if_na($fval) === '') continue;
            if (preg_match('/\bannounced|release\b/', $fname)) compare_set_spec_if_empty($specs, 'announced', $fval);
            if (preg_match('/\bos\b|operating system/', $fname)) compare_set_spec_if_empty($specs, 'os', $fval);
            if (preg_match('/chipset|soc|processor/', $fname)) compare_set_spec_if_empty($specs, 'chipset', $fval);
            if (preg_match('/\bcpu\b/', $fname)) compare_set_spec_if_empty($specs, 'cpu', $fval);
            if (preg_match('/\bgpu\b|graphics/', $fname)) compare_set_spec_if_empty($specs, 'gpu', $fval);
            if (preg_match('/display type|panel/', $fname)) compare_set_spec_if_empty($specs, 'display_type', $fval);
            if (preg_match('/display|screen|size|resolution/', $fname)) compare_set_spec_if_empty($specs, 'display_size', $fval);
            if (preg_match('/\bram\b|storage|memory|rom/', $fname)) compare_set_spec_if_empty($specs, 'memory', $fval);
            if (preg_match('/main camera|rear camera|camera/', $fname)) compare_set_spec_if_empty($specs, 'main_camera', $fval);
            if (preg_match('/selfie|front camera/', $fname)) compare_set_spec_if_empty($specs, 'selfie_camera', $fval);
            if (preg_match('/battery|mah/', $fname)) compare_set_spec_if_empty($specs, 'battery', $fval);
            if (preg_match('/charging|charge|watt|fast charge/', $fname)) compare_set_spec_if_empty($specs, 'charging', $fval);
        }
        $featureQ->close();
    }
    if (count($specs) === 0) return null;
    return [
        'product_id' => $pid,
        'resolved_name' => trim((string)$best['name']) !== '' ? (string)$best['name'] : $requestedName,
        'specs' => $specs
    ];
}

/**
 * Gets GSMArena-based research/spec object for one product.
 */
function fetchGsmArenaResearchForProduct($name)
{
    $candidates = findGsmArenaCandidates($name, 5);
    $scoredCandidates = [];
    foreach ($candidates as $c) {
        $cn = (string)($c["name"] ?? "");
        if ($cn === "") {
            continue;
        }
        $c["_score"] = gsmCandidateScore($name, $cn);
        $scoredCandidates[] = $c;
    }
    if (count($scoredCandidates) > 1) {
        usort($scoredCandidates, function ($a, $b) {
            return (float)($b["_score"] ?? 0) <=> (float)($a["_score"] ?? 0);
        });
    }
    $validCandidates = [];
    foreach ($scoredCandidates as $c) {
        $cn = (string)($c["name"] ?? "");
        if ($cn !== "" && isCandidateRelated($name, $cn)) {
            $validCandidates[] = $c;
        }
    }
    if (count($validCandidates) > 1) {
        usort($validCandidates, function ($a, $b) {
            return (float)($b["_score"] ?? 0) <=> (float)($a["_score"] ?? 0);
        });
    }
    if (count($validCandidates) === 0 && count($scoredCandidates) > 0) {
        $top = $scoredCandidates[0];
        $topName = (string)($top["name"] ?? "");
        $qTokens = nameTokens($name);
        $cTokens = nameTokens($topName);
        $qNums = numericTokens($name);
        $cNums = numericTokens($topName);
        $tokenOverlap = tokenOverlapRatio($qTokens, $cTokens);
        $numOverlap = (count($qNums) > 0) ? tokenOverlapRatio($qNums, $cNums) : 1.0;
        $score = (float)($top["_score"] ?? 0.0);
        $brandLikeQuery = containsKnownPhoneBrand($name);
        $hasEnoughSignal = (count($qTokens) >= 2) || (count($qNums) > 0);
        if ($brandLikeQuery && $hasEnoughSignal && $tokenOverlap >= 0.6 && $numOverlap >= 0.5 && $score >= 1.2) {
            $validCandidates[] = $top;
        }
    }
    $url = !empty($validCandidates[0]["url"]) ? (string)$validCandidates[0]["url"] : "";
    $resolvedName = "";
    if (!empty($validCandidates[0]["name"])) {
        $resolvedName = (string)$validCandidates[0]["name"];
    }
    if ($url === "") {
        $dbFallback = null;
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $dbFallback = buildRtelCompareSpecs($GLOBALS['conn'], $name);
        }
        if (is_array($dbFallback) && !empty($dbFallback['specs'])) {
            return [
                "name" => $name,
                "gsmarena_url" => "",
                "resolved_name" => (string)($dbFallback['resolved_name'] ?? $name),
                "specs" => (array)($dbFallback['specs'] ?? []),
                "invalid_product" => false,
                "spec_source" => "rtel_db",
                "suggestions" => array_map(function ($x) {
                    return (string)($x["name"] ?? "");
                }, $scoredCandidates)
            ];
        }
        return [
            "name" => $name,
            "gsmarena_url" => "",
            "resolved_name" => "",
            "specs" => [],
            "invalid_product" => true,
            "suggestions" => array_map(function ($x) {
                return (string)($x["name"] ?? "");
            }, $scoredCandidates)
        ];
    }
    $gsmSpecs = parseGsmArenaSpecs($url);
    $dbFallback = null;
    if ((count($gsmSpecs) < 4) && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $dbFallback = buildRtelCompareSpecs($GLOBALS['conn'], $name);
    }
    if (is_array($dbFallback) && !empty($dbFallback['specs'])) {
        foreach ((array)$dbFallback['specs'] as $k => $v) {
            compare_set_spec_if_empty($gsmSpecs, $k, $v);
        }
    }
    return [
        "name" => $name,
        "resolved_name" => $resolvedName !== "" ? $resolvedName : $name,
        "gsmarena_url" => $url,
        "specs" => $gsmSpecs,
        "invalid_product" => false,
        "spec_source" => (count($gsmSpecs) > 0 ? "gsmarena_or_merged" : "unknown"),
        "suggestions" => array_map(function ($x) {
            return (string)($x["name"] ?? "");
        }, $scoredCandidates)
    ];
}

/** Extracts keyword tokens from product name for DB matching. */
function dbMatchKeywords($text)
{
    $text = mb_strtolower((string)$text);
    $text = preg_replace("/[^\p{L}\p{N}\s]+/u", " ", $text);
    $words = preg_split("/\s+/u", $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($words)) return [];
    $stop = ["the", "and", "or", "phone", "mobile", "model", "with", "new"];
    $out = [];
    foreach ($words as $w) {
        if (mb_strlen($w) < 2 || in_array($w, $stop, true)) continue;
        $out[] = $w;
    }
    return array_slice(array_values(array_unique($out)), 0, 8);
}

function normalizeForCompare($text)
{
    $text = mb_strtolower((string)$text);
    $text = preg_replace("/[^\p{L}\p{N}\s]+/u", " ", $text);
    $text = preg_replace("/\s+/u", " ", $text);
    return trim((string)$text);
}

function numericTokens($text)
{
    preg_match_all('/\d+/u', (string)$text, $m);
    if (!isset($m[0]) || !is_array($m[0])) return [];
    return array_values(array_unique(array_map('strval', $m[0])));
}

function tokenOverlapRatio(array $a, array $b)
{
    if (count($a) === 0 || count($b) === 0) return 0.0;
    $hits = 0;
    foreach ($a as $t) {
        if (in_array($t, $b, true)) $hits++;
    }
    return $hits / max(1, count($a));
}

function containsKnownPhoneBrand($text)
{
    $t = mb_strtolower((string)$text);
    $brands = ['apple', 'iphone', 'samsung', 'galaxy', 'xiaomi', 'redmi', 'poco', 'oneplus', 'google', 'pixel', 'vivo', 'oppo', 'realme', 'nokia', 'motorola', 'sony', 'honor', 'huawei'];
    foreach ($brands as $b) {
        if (mb_strpos($t, $b) !== false) return true;
    }
    return false;
}

/** Scores a DB product row for name matching. */
function scoreDbRow(array $row, array $keywords)
{
    if (count($keywords) === 0) return 0;
    $hay = mb_strtolower((string)($row["name"] ?? "") . " " . (string)($row["modal"] ?? "") . " " . (string)($row["brand_name"] ?? ""));
    $score = 0;
    foreach ($keywords as $kw) {
        if ($kw !== "" && mb_strpos($hay, $kw) !== false) $score++;
    }
    return $score;
}

/**
 * Finds best matching product in R-TEL DB and returns availability details.
 */
function findRtelAvailability($conn, $productName)
{
    $keywords = dbMatchKeywords($productName);
    $requestedNorm = normalizeForCompare($productName);
    $requestedTokens = dbMatchKeywords($requestedNorm);
    $requestedNums = numericTokens($requestedNorm);
    $sql = "SELECT p.product_id, p.name, p.modal, p.price, p.quantity, p.status,
                   COALESCE(b.name, '') AS brand_name, i.image_1
            FROM tblproduct p
            LEFT JOIN tblbrand b ON p.brand_id = b.brand_id AND b.status = 1
            LEFT JOIN tblimage i ON p.product_id = i.product_id
            WHERE p.status = 1
            LIMIT 80";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return null;
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row["match_score"] = scoreDbRow($row, $keywords);
        $rowNorm = normalizeForCompare((string)($row["name"] ?? "") . " " . (string)($row["modal"] ?? "") . " " . (string)($row["brand_name"] ?? ""));
        $row["match_norm"] = $rowNorm;
        $row["token_overlap"] = tokenOverlapRatio($requestedTokens, dbMatchKeywords($rowNorm));
        $row["num_overlap"] = tokenOverlapRatio($requestedNums, numericTokens($rowNorm));
        $row["exact_phrase"] = ($requestedNorm !== "" && mb_strpos($rowNorm, $requestedNorm) !== false) ? 1 : 0;
        $rows[] = $row;
    }
    if (count($rows) === 0) {
        return null;
    }
    usort($rows, function ($a, $b) {
        if ((int)($a["exact_phrase"] ?? 0) !== (int)($b["exact_phrase"] ?? 0)) {
            return (int)$b["exact_phrase"] <=> (int)$a["exact_phrase"];
        }
        if ((float)($a["num_overlap"] ?? 0) !== (float)($b["num_overlap"] ?? 0)) {
            return (float)$b["num_overlap"] <=> (float)$a["num_overlap"];
        }
        if ((float)($a["token_overlap"] ?? 0) !== (float)($b["token_overlap"] ?? 0)) {
            return (float)$b["token_overlap"] <=> (float)$a["token_overlap"];
        }
        if ((int)$a["match_score"] !== (int)$b["match_score"]) {
            return (int)$b["match_score"] <=> (int)$a["match_score"];
        }
        return (float)$a["price"] <=> (float)$b["price"];
    });

    $best = $rows[0];
    $matchScore = (int)($best["match_score"] ?? 0);
    $tokenOverlap = (float)($best["token_overlap"] ?? 0.0);
    $numOverlap = (float)($best["num_overlap"] ?? 0.0);
    $exactPhrase = ((int)($best["exact_phrase"] ?? 0) === 1);
    $variantWords = ['pro', 'plus', 'ultra', 'max', 'mini', 'lite', 'prime', 'note', 'fe'];
    $requestedWordSet = dbMatchKeywords($requestedNorm);
    $bestWordSet = dbMatchKeywords((string)($best["match_norm"] ?? ''));
    $requestedVariants = array_values(array_intersect($variantWords, $requestedWordSet));
    $bestVariants = array_values(array_intersect($variantWords, $bestWordSet));
    $variantGate = true;
    if (count($requestedVariants) > 0) {
        $variantGate = (count(array_diff($requestedVariants, $bestVariants)) === 0);
    }
    $hasRequestedNums = count($requestedNums) > 0;
    // Be slightly tolerant with numeric/model overlaps to avoid false "unavailable".
    $numericGate = $hasRequestedNums ? ($numOverlap >= 0.5) : true;
    $strongTextGate = $exactPhrase || ($tokenOverlap >= 0.6) || ($matchScore >= 2);
    $matched = ($matchScore > 0) && $numericGate && $strongTextGate && $variantGate;
    $inStock = ((int)($best["quantity"] ?? 0) > 0);
    return [
        "requested_name" => $productName,
        "matched" => $matched,
        "product_id" => (int)$best["product_id"],
        "name" => (string)$best["name"],
        "modal" => (string)($best["modal"] ?? ""),
        "price" => (float)($best["price"] ?? 0),
        "quantity" => (int)($best["quantity"] ?? 0),
        "brand_name" => (string)($best["brand_name"] ?? ""),
        "available_in_rtel" => ($matched && $inStock),
        "image" => (string)(($best["image_1"] ?? "") ?: "smartphone.png"),
        "product_url" => ($matched && $inStock) ? ("product.php?product_id=" . (int)$best["product_id"]) : ""
    ];
}

/** Creates fallback comparison text if AI provider is unavailable. */
function buildWebComparisonDraft(array $webResearch)
{
    $lines = [];
    $lines[] = "GSMArena-style comparison generated.";
    foreach ($webResearch as $item) {
        $name = (string)($item["name"] ?? "Product");
        $hasSpecs = isset($item["specs"]) && is_array($item["specs"]) && count($item["specs"]) > 0;
        $lines[] = "- " . $name . ": " . ($hasSpecs ? "spec data loaded" : "spec data not found from GSMArena");
    }
    return implode("\n", $lines);
}

/**
 * Uses LLM (when configured) to improve wording of the web-based comparison.
 * Falls back to draft if no AI provider is configured.
 */
function generateWebCompareReply($productNames, array $webResearch, $draft)
{
    $GLOBALS['rtel_compare_reply_source'] = 'fallback';
    $GLOBALS['rtel_compare_ai_provider'] = '';
    if (!function_exists("getAiConfiguration") || !function_exists("callRealAiModel")) {
        return $draft;
    }
    $cfg = getAiConfiguration();
    if (!$cfg) {
        return $draft;
    }

    $webBlocks = [];
    foreach ($webResearch as $item) {
        $specs = isset($item["specs"]) && is_array($item["specs"]) ? json_encode($item["specs"]) : "{}";
        $webBlocks[] = "Product: " . (string)$item["name"] . "\nGSMArena URL: " . (string)($item["gsmarena_url"] ?? "") . "\nSpecs JSON: " . $specs;
    }
    $webContext = implode("\n\n---\n\n", $webBlocks);
    $messages = [
        [
            "role" => "system",
            "content" => "You are a product comparison assistant. Write in very basic English. Use only the provided GSMArena specs. Keep response short and structured."
        ],
        [
            "role" => "user",
            "content" => "Compare these products in simple English: " . implode(", ", $productNames) . "\n\nWeb research notes:\n" . $webContext . "\n\nDraft:\n" . $draft
        ]
    ];
    $aiText = callRealAiModel($messages, 0.25);
    if (is_string($aiText) && trim($aiText) !== "") {
        $GLOBALS['rtel_compare_reply_source'] = 'ai';
        $GLOBALS['rtel_compare_ai_provider'] = (string)($cfg["provider"] ?? '');
        return trim($aiText);
    }
    return $draft;
}

$input = json_decode(file_get_contents("php://input"), true);
$productNames = normalizeProductNames($input["product_names"] ?? []);

if (count($productNames) < 2) {
    compareRespond([
        "success" => false,
        "reply" => "Please enter at least 2 product names.",
        "web_research" => [],
        "availability" => []
    ]);
}

// 1) GSMArena-first research for each requested product.
$webResearch = [];
foreach ($productNames as $name) {
    $webResearch[] = fetchGsmArenaResearchForProduct($name);
}

// 2) Build comparison from web notes.
$draft = buildWebComparisonDraft($webResearch);
$finalReply = generateWebCompareReply($productNames, $webResearch, $draft);

// 3) After web comparison, check R-TEL DB availability per requested product.
$availability = [];
foreach ($productNames as $name) {
    $row = findRtelAvailability($conn, $name);
    if (!$row) {
        $availability[] = [
            "requested_name" => $name,
            "matched" => false,
            "available_in_rtel" => false
        ];
        continue;
    }
    $availability[] = $row;
}

compareRespond([
    "success" => true,
    "reply" => $finalReply,
    "web_research" => $webResearch,
    "availability" => $availability,
    "reply_source" => (string)($GLOBALS['rtel_compare_reply_source'] ?? 'fallback'),
    "ai_provider" => (string)($GLOBALS['rtel_compare_ai_provider'] ?? '')
]);
