<?php
include 'config.php';
include 'connection.php';
require_once __DIR__ . '/includes/rtel_db_helpers.php';
require_once __DIR__ . '/includes/rtel_web_cache.php';
require_once __DIR__ . '/ai/personalization_engine.php';
require_once __DIR__ . '/ai/chat_ai_provider.php';
require_once __DIR__ . '/mail/mail_notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keep product page fast: strict total budget for remote web calls on a cold load.
if (!isset($GLOBALS['rtel_remote_deadline'])) {
    $GLOBALS['rtel_remote_deadline'] = microtime(true) + 1.4; // seconds
}

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rs($n)
{
    return 'Rs. ' . number_format((float)$n, 2);
}

function rtel_ensure_price_alert_table(mysqli $conn)
{
    // Table creation is managed outside runtime code.
    return;
}

function rtel_price_alert_table_exists(mysqli $conn)
{
    static $exists = null;
    if ($exists !== null) {
        return (bool)$exists;
    }
    try {
        $res = $conn->query("SHOW TABLES LIKE 'tblprice_alert'");
        $exists = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $exists = false;
    }
    return (bool)$exists;
}

function rtel_fetch_product_alert_state(mysqli $conn, $cusId, $productId)
{
    $state = [
        'price_drop' => ['enabled' => false, 'target_price' => 0.0],
        'restock' => ['enabled' => false],
    ];
    if (!rtel_price_alert_table_exists($conn)) {
        return $state;
    }
    $stmt = $conn->prepare("SELECT alert_type, target_price FROM tblprice_alert WHERE cus_id = ? AND product_id = ? AND status = 1");
    if (!$stmt) {
        return $state;
    }
    $stmt->bind_param("ss", $cusId, $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $type = strtolower(trim((string)($row['alert_type'] ?? '')));
        if ($type === 'price_drop') {
            $state['price_drop']['enabled'] = true;
            $state['price_drop']['target_price'] = (float)($row['target_price'] ?? 0);
        } elseif ($type === 'restock') {
            $state['restock']['enabled'] = true;
        }
    }
    $stmt->close();
    return $state;
}

function fetch_url_text($url, $timeoutSeconds = 12, $respectDeadline = true)
{
    $deadline = isset($GLOBALS['rtel_remote_deadline']) ? (float)$GLOBALS['rtel_remote_deadline'] : 0.0;
    if ($respectDeadline && $deadline > 0) {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0.05) {
            return '';
        }
        $timeoutSeconds = min((float)$timeoutSeconds, max(1.0, $remaining));
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, max(1, (int)$timeoutSeconds));
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36');
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$body || $code >= 400) {
        return '';
    }
    return (string)$body;
}

/**
 * Pulls the first matching GSMArena page meta description for this phone.
 */
function fetch_gsmarena_description($query)
{
    $query = trim((string)$query);
    if ($query === '') return '';

    $detailUrl = rtel_fetch_gsmarena_detail_url($query);
    if ($detailUrl === '') {
        return '';
    }
    $detailHtml = fetch_url_text($detailUrl, 5, false);
    if ($detailHtml === '') return '';

    if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"\s*\/?>/i', $detailHtml, $d)) {
        return html_entity_decode(trim((string)$d[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

function rtel_gsm_parse_key_value_pairs($html)
{
    $pairs = [];
    if (!preg_match_all('/<td[^>]*class="ttl"[^>]*>(.*?)<\/td>\s*<td[^>]*class="nfo"[^>]*>(.*?)<\/td>/is', (string)$html, $m, PREG_SET_ORDER)) {
        return $pairs;
    }
    foreach ($m as $row) {
        $k = mb_strtolower(trim((string)html_entity_decode(strip_tags((string)($row[1] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $v = trim((string)html_entity_decode(strip_tags((string)($row[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $v = preg_replace('/\s+/u', ' ', $v);
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

function rtel_gsm_pick_spec(array $pairs, array $keys, $pattern = '')
{
    foreach ($keys as $key) {
        $k = mb_strtolower(trim((string)$key));
        if (!isset($pairs[$k]) || !is_array($pairs[$k])) {
            continue;
        }
        foreach ($pairs[$k] as $v) {
            $v = trim((string)$v);
            if ($v === '') {
                continue;
            }
            if ($pattern !== '' && !preg_match($pattern, $v)) {
                continue;
            }
            return $v;
        }
    }
    return '';
}

function rtel_fetch_gsmarena_specs($query)
{
    $url = rtel_fetch_gsmarena_detail_url($query);
    if ($url === '') {
        return [];
    }
    $html = fetch_url_text($url, 6, false);
    if ($html === '') {
        return [];
    }

    $pairs = rtel_gsm_parse_key_value_pairs($html);
    $specMap = [
        'Announced' => rtel_gsm_pick_spec($pairs, ['announced']),
        'Display Type' => rtel_gsm_pick_spec($pairs, ['type'], '/oled|amoled|lcd|retina|display|hz|inch|inches|resolution/i'),
        'Display Size' => rtel_gsm_pick_spec($pairs, ['size'], '/inch|inches|cm2|ppi|resolution/i'),
        'OS' => rtel_gsm_pick_spec($pairs, ['os']),
        'Chipset' => rtel_gsm_pick_spec($pairs, ['chipset']),
        'CPU' => rtel_gsm_pick_spec($pairs, ['cpu']),
        'GPU' => rtel_gsm_pick_spec($pairs, ['gpu']),
        'Memory' => rtel_gsm_pick_spec($pairs, ['internal', 'card slot']),
        'Main Camera' => rtel_gsm_pick_spec($pairs, ['triple', 'dual', 'quad', 'penta', 'single'], '/mp|camera|wide|ultrawide|telephoto|video/i'),
        'Selfie Camera' => rtel_gsm_pick_spec($pairs, ['single', 'dual'], '/mp|camera|video|hdr/i'),
        'Battery' => rtel_gsm_pick_spec($pairs, ['type'], '/mah|li-po|li-ion|battery/i'),
        'Charging' => rtel_gsm_pick_spec($pairs, ['charging']),
    ];
    $out = [];
    foreach ($specMap as $name => $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $out[] = ['name' => $name, 'value' => $value];
        }
    }
    return $out;
}

function rtel_fetch_gsmarena_detail_url($query)
{
    $query = trim((string)$query);
    if ($query === '') {
        return '';
    }
    $searchUrl = 'https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=' . urlencode($query);
    $searchHtml = fetch_url_text($searchUrl, 5, false);
    if ($searchHtml === '') {
        return '';
    }
    if (!preg_match('/href="([^"]+-\d+\.php)"/i', $searchHtml, $m)) {
        return '';
    }
    return 'https://www.gsmarena.com/' . ltrim((string)$m[1], '/');
}

/**
 * Fetches product-related images directly from GSMArena detail page/gallery.
 */
function rtel_fetch_gsmarena_images($query, $limit = 8)
{
    $detailUrl = rtel_fetch_gsmarena_detail_url($query);
    if ($detailUrl === '') {
        return [];
    }
    $detailHtml = fetch_url_text($detailUrl, 6, false);
    if ($detailHtml === '') {
        return [];
    }

    $galleryHtml = '';
    if (preg_match('/href="([^"]*pictures[^"]*)"/i', $detailHtml, $pm)) {
        $galleryUrl = trim((string)$pm[1]);
        if ($galleryUrl !== '') {
            if (strpos($galleryUrl, 'http') !== 0) {
                $galleryUrl = 'https://www.gsmarena.com/' . ltrim($galleryUrl, '/');
            }
            $galleryHtml = fetch_url_text($galleryUrl, 6, false);
        }
    }
    $sourceHtml = $detailHtml . "\n" . (string)$galleryHtml;

    preg_match_all('/https?:\/\/[^"\']*fdn\.gsmarena\.com\/[^"\']+\.(?:jpg|jpeg|png|webp)/i', $sourceHtml, $mm);
    $urls = isset($mm[0]) && is_array($mm[0]) ? $mm[0] : [];
    $out = [];
    $seen = [];
    foreach ($urls as $u) {
        $u = trim((string)$u);
        if ($u === '' || isset($seen[$u])) {
            continue;
        }
        $seen[$u] = true;
        $out[] = [
            'url' => $u,
            'title' => $query,
            'source' => 'GSMArena',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Fetches camera sample images for a specific phone from GSMArena pages.
 * This is used as a high-priority source before generic product images.
 */
function rtel_fetch_gsmarena_camera_sample_images($query, $limit = 10)
{
    $detailUrl = rtel_fetch_gsmarena_detail_url($query);
    if ($detailUrl === '') {
        return [];
    }
    $detailHtml = fetch_url_text($detailUrl, 7, false);
    if ($detailHtml === '') {
        return [];
    }

    $candidateUrls = [];
    $seenUrls = [];
    $addUrl = function ($u) use (&$candidateUrls, &$seenUrls) {
        $u = trim((string)$u);
        if ($u === '') return;
        if (strpos($u, 'http') !== 0) {
            $u = 'https://www.gsmarena.com/' . ltrim($u, '/');
        }
        if (isset($seenUrls[$u])) return;
        $seenUrls[$u] = true;
        $candidateUrls[] = $u;
    };

    // 1) direct camera sample links (most accurate).
    if (preg_match_all('/href="([^"]*(?:camera|samples|sample|review)[^"]*)"/i', $detailHtml, $m1)) {
        foreach ((array)($m1[1] ?? []) as $u) {
            $addUrl($u);
        }
    }
    // 2) review page often contains camera sample galleries.
    if (preg_match_all('/href="([^"]*review[^"]*\.php)"/i', $detailHtml, $m2)) {
        foreach ((array)($m2[1] ?? []) as $u) {
            $addUrl($u);
        }
    }
    // 3) picture comparison pages may still include camera sample assets.
    if (preg_match_all('/href="([^"]*piccmp[^"]*)"/i', $detailHtml, $m3)) {
        foreach ((array)($m3[1] ?? []) as $u) {
            $addUrl($u);
        }
    }

    // Keep request count bounded for latency.
    $candidateUrls = array_slice($candidateUrls, 0, 6);
    $sampleHtml = $detailHtml;
    foreach ($candidateUrls as $u) {
        $sampleHtml .= "\n" . fetch_url_text($u, 6, false);
    }

    preg_match_all('/https?:\/\/[^"\']*fdn\.gsmarena\.com\/[^"\']+\.(?:jpg|jpeg|png|webp)/i', $sampleHtml, $mm);
    $urls = isset($mm[0]) && is_array($mm[0]) ? $mm[0] : [];
    $out = [];
    $seen = [];
    foreach ($urls as $u) {
        $u = trim((string)$u);
        if ($u === '' || isset($seen[$u])) continue;
        // Favor camera/photo sample-like assets.
        if (!preg_match('/camera|sample|samples|photo|night|portrait|selfie|zoom|img|shot/i', $u)) {
            continue;
        }
        $seen[$u] = true;
        $out[] = [
            'url' => $u,
            'title' => $query . ' camera sample',
            'source' => 'GSMArena Camera Samples',
        ];
        if (count($out) >= max(1, (int)$limit)) break;
    }
    return $out;
}

function rtel_get_product_media_training_settings($conn)
{
    $out = [
        'product_media_system_prompt' => '',
        'product_media_focus_keywords' => '',
    ];
    if (!$conn) {
        return $out;
    }
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tblai_setting'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return $out;
    }
    $res = $conn->query("SELECT setting_key, setting_value FROM tblai_setting WHERE setting_key IN ('product_media_system_prompt','product_media_focus_keywords')");
    if (!$res) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $k = trim((string)($row['setting_key'] ?? ''));
        if ($k !== '' && array_key_exists($k, $out)) {
            $out[$k] = trim((string)($row['setting_value'] ?? ''));
        }
    }
    return $out;
}

/**
 * Keeps search queries clean, unique, and practical for media search.
 */
function rtel_normalize_media_query_list(array $queries, $fallbackSeed, $limit = 4)
{
    $seen = [];
    $out = [];
    foreach ($queries as $q) {
        $q = trim((string)$q);
        if ($q === '') {
            continue;
        }
        // Remove symbols/noise and keep concise phrases.
        $q = preg_replace('/[^\p{L}\p{N}\s\-\+]/u', ' ', $q);
        $q = preg_replace('/\s+/u', ' ', (string)$q);
        $q = trim((string)$q);
        if ($q === '') {
            continue;
        }
        if (mb_strlen($q) > 72) {
            $q = mb_substr($q, 0, 72);
            $q = trim((string)$q);
        }
        $k = strtolower($q);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $q;
        if (count($out) >= $limit) {
            break;
        }
    }
    // If model output is short/invalid, pad with deterministic fallback seeds.
    foreach ($fallbackSeed as $fq) {
        if (count($out) >= $limit) {
            break;
        }
        $fq = trim((string)$fq);
        if ($fq === '') {
            continue;
        }
        $k = strtolower($fq);
        if (!isset($seen[$k])) {
            $seen[$k] = true;
            $out[] = $fq;
        }
    }
    return array_slice($out, 0, $limit);
}

function generate_ai_media_queries($productName, $productModel, $description, array $training = [])
{
    // Use full product name as the main search seed (modal/SKU can be noisy).
    $seedName = trim((string)$productName);
    if ($seedName === '') {
        $seedName = trim((string)$productModel);
    }
    $baseImageFallback = [
        trim($seedName . ' camera sample photos'),
        trim($seedName . ' daylight camera test'),
        trim($seedName . ' night mode camera sample'),
        trim($seedName . ' gaming performance test'),
    ];
    $baseVideoFallback = [
        trim($seedName . ' full review'),
        trim($seedName . ' camera test'),
        trim($seedName . ' gaming test'),
        trim($seedName . ' battery drain test'),
    ];
    $fallback = [
        'image_queries' => $baseImageFallback,
        'video_queries' => $baseVideoFallback,
        'source' => 'fallback',
        'reason' => 'fallback-default',
    ];

    $prompt = "You are helping an ecommerce product page.\n"
        . "Generate concise web search queries for related image and video sections.\n"
        . "Product full name: {$productName}\n"
        . "Model/SKU (optional context only): {$productModel}\n"
        . "Description: {$description}\n\n"
        . "Return ONLY valid JSON object with this exact shape:\n"
        . "{\"image_queries\":[\"...\",\"...\",\"...\",\"...\"],\"video_queries\":[\"...\",\"...\",\"...\",\"...\"]}\n"
        . "Rules:\n"
        . "- Exactly 4 image queries and 4 video queries\n"
        . "- Keep each query short, practical, and YouTube/Google-friendly\n"
        . "- Use plain keywords, no punctuation-heavy phrases\n"
        . "- Focus on camera, display, performance, battery, review intent\n"
        . "- No markdown, no explanation text.";
    $trainingPrompt = trim((string)($training['product_media_system_prompt'] ?? ''));
    $trainingKeywords = trim((string)($training['product_media_focus_keywords'] ?? ''));
    if ($trainingPrompt !== '' || $trainingKeywords !== '') {
        $prompt .= "\nExtra training instructions:\n";
        if ($trainingPrompt !== '') {
            $prompt .= "- " . $trainingPrompt . "\n";
        }
        if ($trainingKeywords !== '') {
            $prompt .= "- Prefer these keywords when relevant: " . $trainingKeywords . "\n";
        }
    }
    if (!function_exists('getAiConfiguration') || !function_exists('callRealAiModel')) {
        $fallback['reason'] = 'ai provider module unavailable';
        return $fallback;
    }
    $cfg = getAiConfiguration();
    if (!$cfg) {
        $fallback['reason'] = 'missing GROQ_API_KEY / OPENAI_API_KEY';
        return $fallback;
    }
    $messages = [
        ["role" => "system", "content" => "Return only valid JSON. No markdown fences."],
        ["role" => "user", "content" => $prompt],
    ];
    $text = callRealAiModel($messages, 0.25);
    if (!is_string($text) || trim($text) === '') {
        $fallback['reason'] = 'ai provider empty response';
        return $fallback;
    }

    // Clean accidental markdown fences from model output.
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?/i', '', $text);
    $text = preg_replace('/```$/', '', $text);
    $obj = json_decode(trim($text), true);
    if (!is_array($obj)) {
        $fallback['reason'] = 'ai provider invalid json';
        return $fallback;
    }

    $img = isset($obj['image_queries']) && is_array($obj['image_queries']) ? $obj['image_queries'] : [];
    $vid = isset($obj['video_queries']) && is_array($obj['video_queries']) ? $obj['video_queries'] : [];
    $img = rtel_normalize_media_query_list($img, $baseImageFallback, 4);
    $vid = rtel_normalize_media_query_list($vid, $baseVideoFallback, 4);

    return [
        'image_queries' => $img,
        'video_queries' => $vid,
        'source' => (string)($cfg['provider'] ?? 'ai'),
        'reason' => 'ok via ' . (string)($cfg['provider'] ?? 'ai'),
    ];
}

/**
 * Fetches related image URLs from Wikimedia Commons (no API key required).
 */
function rtel_fetch_wikimedia_images($query, $limit = 4)
{
    $query = trim((string)$query);
    if ($query === '') {
        return [];
    }
    $url = 'https://commons.wikimedia.org/w/api.php?action=query'
        . '&generator=search'
        . '&gsrnamespace=6'
        . '&gsrsearch=' . urlencode($query)
        . '&gsrlimit=' . max(4, (int)$limit * 2)
        . '&prop=imageinfo'
        . '&iiprop=url'
        . '&iiurlwidth=1000'
        . '&format=json';
    $body = fetch_url_text($url, 7);
    if ($body === '') {
        return [];
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['query']['pages']) || !is_array($json['query']['pages'])) {
        return [];
    }
    $out = [];
    foreach ($json['query']['pages'] as $page) {
        $title = trim((string)($page['title'] ?? ''));
        $info = isset($page['imageinfo'][0]) && is_array($page['imageinfo'][0]) ? $page['imageinfo'][0] : [];
        $imgUrl = trim((string)($info['thumburl'] ?? $info['url'] ?? ''));
        if ($imgUrl === '') {
            continue;
        }
        $out[] = [
            'url' => $imgUrl,
            'title' => $title !== '' ? $title : $query,
            'source' => 'Wikimedia Commons',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Fetches product-related images from Wikipedia page thumbnails.
 */
function rtel_fetch_wikipedia_images($query, $limit = 4)
{
    $query = trim((string)$query);
    if ($query === '') {
        return [];
    }
    $url = 'https://en.wikipedia.org/w/api.php?action=query'
        . '&generator=search'
        . '&gsrsearch=' . urlencode($query)
        . '&gsrlimit=' . max(4, (int)$limit * 2)
        . '&prop=pageimages'
        . '&piprop=thumbnail'
        . '&pithumbsize=1000'
        . '&format=json';
    $body = fetch_url_text($url, 7);
    if ($body === '') {
        return [];
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['query']['pages']) || !is_array($json['query']['pages'])) {
        return [];
    }
    $out = [];
    foreach ($json['query']['pages'] as $page) {
        $title = trim((string)($page['title'] ?? ''));
        $thumb = trim((string)($page['thumbnail']['source'] ?? ''));
        if ($thumb === '') {
            continue;
        }
        $out[] = [
            'url' => $thumb,
            'title' => $title !== '' ? $title : $query,
            'source' => 'Wikipedia',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function rtel_media_context_tokens($text)
{
    $text = mb_strtolower(trim((string)$text));
    if ($text === '') {
        return [];
    }
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', (string)$text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($parts as $p) {
        if (mb_strlen($p) < 3) {
            continue;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function rtel_media_is_relevant_image(array $imgRow, array $requiredTokens, array $avoidTokens)
{
    $hay = mb_strtolower(trim((string)($imgRow['title'] ?? '')) . ' ' . trim((string)($imgRow['url'] ?? '')));
    if ($hay === '') {
        return false;
    }
    foreach ($avoidTokens as $bad) {
        $bad = trim((string)$bad);
        if ($bad !== '' && mb_strpos($hay, mb_strtolower($bad)) !== false) {
            return false;
        }
    }
    if (count($requiredTokens) === 0) {
        return true;
    }
    foreach ($requiredTokens as $t) {
        $t = trim((string)$t);
        if ($t !== '' && mb_strpos($hay, mb_strtolower($t)) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Fetches related YouTube video IDs by scraping search page results.
 */
function rtel_fetch_youtube_videos_api($query, $limit = 2)
{
    $query = trim((string)$query);
    if ($query === '') {
        return [];
    }
    $apiKey = '';
    if (function_exists('rtel_read_ai_secret')) {
        $apiKey = trim((string)rtel_read_ai_secret('YOUTUBE_API_KEY'));
    }
    if ($apiKey === '') {
        $apiKey = trim((string)(getenv('YOUTUBE_API_KEY') ?: ($_SERVER['YOUTUBE_API_KEY'] ?? $_ENV['YOUTUBE_API_KEY'] ?? '')));
    }
    if ($apiKey === '') {
        return [];
    }
    $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults='
        . max(1, (int)$limit)
        . '&q=' . urlencode($query)
        . '&key=' . urlencode($apiKey);
    $body = fetch_url_text($url, 8, false);
    if ($body === '') {
        return [];
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['items']) || !is_array($json['items'])) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($json['items'] as $item) {
        $id = trim((string)($item['id']['videoId'] ?? ''));
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $title = trim((string)($item['snippet']['title'] ?? $query));
        $out[] = [
            'id' => $id,
            'title' => $title !== '' ? $title : $query,
            'source' => 'YouTube API',
        ];
        if (count($out) >= max(1, (int)$limit)) {
            break;
        }
    }
    return $out;
}

/**
 * Fetches related YouTube video IDs by scraping search page results.
 */
function rtel_fetch_youtube_videos($query, $limit = 2)
{
    $query = trim((string)$query);
    if ($query === '') {
        return [];
    }
    // Ignore page-level remote deadline for YouTube scrape so we can avoid fallback-only cards.
    $html = fetch_url_text('https://www.youtube.com/results?search_query=' . urlencode($query), 10, false);
    if ($html === '') {
        return [];
    }
    $ids = [];
    if (preg_match_all('/"videoId":"([a-zA-Z0-9_-]{11})"/', $html, $m1)) {
        $ids = array_merge($ids, $m1[1]);
    }
    // Fallback parser for alternate HTML responses.
    if (preg_match_all('/\/watch\?v=([a-zA-Z0-9_-]{11})/i', $html, $m2)) {
        $ids = array_merge($ids, $m2[1]);
    }
    $ids = array_values(array_unique(array_filter(array_map('trim', $ids))));
    if (count($ids) === 0) {
        return [];
    }
    $seen = [];
    $out = [];
    foreach ($ids as $vid) {
        if (isset($seen[$vid])) {
            continue;
        }
        $seen[$vid] = true;
        $out[] = [
            'id' => $vid,
            'title' => $query,
            'source' => 'YouTube',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return array_slice($out, 0, max(1, (int)$limit));
}

/** Split admin-entered comma/pipe lists into unique trimmed tokens. */
function rtel_split_option_list($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/\s*[,|]\s*/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

function rtel_merge_unique_strings(array &$bucket, array $items)
{
    foreach ($items as $it) {
        $it = trim((string)$it);
        if ($it === '') {
            continue;
        }
        if (!in_array($it, $bucket, true)) {
            $bucket[] = $it;
        }
    }
}

function rtel_dedupe_products_by_id(array $rows, $limit = 8)
{
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $pid = (string)($row['product_id'] ?? '');
        if ($pid === '' || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;
        $out[] = $row;
        if (count($out) >= max(1, (int)$limit)) {
            break;
        }
    }
    return $out;
}

function rtel_add_spec_row(array &$rows, $name, $value)
{
    $name = trim((string)$name);
    $value = trim((string)$value);
    if ($name === '' || $value === '') {
        return;
    }
    $rows[] = ['name' => $name, 'value' => $value];
}

/**
 * Tokens for compatibility matching (brand/model/spec words).
 */
function rtel_tokenize_for_match($text)
{
    $text = mb_strtolower((string)$text);
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', (string)$text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = ['the', 'and', 'or', 'with', 'for', 'new', 'pro', 'plus', 'mobile', 'phone', 'smartphone', 'model'];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === '' || mb_strlen($p) < 2 || in_array($p, $stop, true)) {
            continue;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

/**
 * Scores accessory compatibility against current product context.
 */
function rtel_accessory_match_score(array $row, array $contextTokens, $brandHint)
{
    $hay = mb_strtolower(
        (string)($row['name'] ?? '') . ' ' .
        (string)($row['modal'] ?? '') . ' ' .
        (string)($row['description'] ?? '') . ' ' .
        (string)($row['category_name'] ?? '')
    );
    $score = 0;
    foreach ($contextTokens as $t) {
        if ($t !== '' && mb_strpos($hay, $t) !== false) {
            $score += 2;
        }
    }
    if ($brandHint !== '' && mb_strpos($hay, mb_strtolower($brandHint)) !== false) {
        $score += 5;
    }
    // Small preference for better-stock and active discount items.
    $qty = (int)($row['quantity'] ?? 0);
    if ($qty >= 10) $score += 1;
    if ((float)($row['cprice'] ?? 0) > (float)($row['price'] ?? 0)) $score += 1;
    return $score;
}

function rtel_detect_port_type($text)
{
    $t = mb_strtolower((string)$text);
    if ($t === '') return '';
    if (preg_match('/usb[\s\-]*c|type[\s\-]*c|typec/i', $t)) return 'usb-c';
    if (preg_match('/lightning/i', $t)) return 'lightning';
    if (preg_match('/micro[\s\-]*usb/i', $t)) return 'micro-usb';
    if (preg_match('/3\.5\s*mm|aux/i', $t)) return '3.5mm';
    return '';
}

function rtel_detect_ecosystem_type($text)
{
    $t = mb_strtolower((string)$text);
    if ($t === '') return '';
    $ios = (strpos($t, 'iphone') !== false || strpos($t, 'ios') !== false || strpos($t, 'apple') !== false);
    $android = (strpos($t, 'android') !== false || strpos($t, 'samsung') !== false || strpos($t, 'xiaomi') !== false || strpos($t, 'oneplus') !== false || strpos($t, 'oppo') !== false || strpos($t, 'vivo') !== false || strpos($t, 'realme') !== false || strpos($t, 'pixel') !== false || strpos($t, 'google') !== false);
    if ($ios && $android) return 'both';
    if ($ios) return 'ios';
    if ($android) return 'android';
    return '';
}

function rtel_accessory_compatibility_bonus(array $row, array $phoneContext)
{
    $rowText = mb_strtolower(
        (string)($row['name'] ?? '') . ' ' .
        (string)($row['modal'] ?? '') . ' ' .
        (string)($row['description'] ?? '') . ' ' .
        (string)($row['category_name'] ?? '') . ' ' .
        (string)($row['feature_blob'] ?? '')
    );
    $bonus = 0;
    $phonePort = (string)($phoneContext['port'] ?? '');
    $phoneEco = (string)($phoneContext['ecosystem'] ?? '');

    $isCableOrCharger = (bool)preg_match('/charger|charging|cable|adapter|usb|type[\s\-]*c|lightning|micro[\s\-]*usb/i', $rowText);
    if ($isCableOrCharger && $phonePort !== '') {
        $accPort = rtel_detect_port_type($rowText);
        if ($accPort !== '') {
            if ($accPort === $phonePort) {
                $bonus += 10;
            } else {
                $bonus -= 9;
            }
        }
    }

    $isAudioOrWatch = (bool)preg_match('/earbud|earphone|headphone|smart\s*watch|watch/i', $rowText);
    if ($isAudioOrWatch && $phoneEco !== '') {
        $accEco = rtel_detect_ecosystem_type($rowText);
        if ($accEco !== '') {
            if ($accEco === 'both' || $accEco === $phoneEco) {
                $bonus += 5;
            } else {
                $bonus -= 5;
            }
        }
    }

    return $bonus;
}

function rtel_phone_model_tokens($text)
{
    $text = mb_strtolower((string)$text);
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', (string)$text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = ['phone', 'phones', 'mobile', 'smartphone', 'model', 'edition', 'version', 'gb', 'ram', 'rom'];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === '' || mb_strlen($p) < 2 || in_array($p, $stop, true)) {
            continue;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function rtel_phone_model_core_tokens($text)
{
    $tokens = rtel_phone_model_tokens($text);
    $core = [];
    foreach ($tokens as $t) {
        $x = trim((string)$t);
        if ($x === '') continue;
        // Keep model-identity tokens: numbers or alpha-numeric codes (e.g., 15, a55, s24, x200).
        if (preg_match('/\d/', $x)) {
            $core[] = $x;
        }
    }
    return array_values(array_unique($core));
}

function rtel_accessory_matches_phone_model(array $row, array $phoneContext)
{
    $rowText = mb_strtolower(
        (string)($row['name'] ?? '') . ' ' .
        (string)($row['modal'] ?? '') . ' ' .
        (string)($row['description'] ?? '') . ' ' .
        (string)($row['feature_blob'] ?? '')
    );
    $modelRaw = mb_strtolower(trim((string)($phoneContext['model_raw'] ?? '')));
    if ($modelRaw !== '' && mb_strpos($rowText, $modelRaw) !== false) {
        return true;
    }
    $tokens = (array)($phoneContext['model_tokens'] ?? []);
    $coreTokens = (array)($phoneContext['model_core_tokens'] ?? []);
    if (count($coreTokens) > 0) {
        $hasCore = false;
        foreach ($coreTokens as $ct) {
            if ($ct !== '' && mb_strpos($rowText, $ct) !== false) {
                $hasCore = true;
                break;
            }
        }
        if (!$hasCore) {
            return false;
        }
    }
    if (count($tokens) === 0) {
        return false;
    }
    $hits = 0;
    foreach ($tokens as $t) {
        if ($t !== '' && mb_strpos($rowText, $t) !== false) {
            $hits++;
        }
    }
    // Two token hits is usually enough for strong model affinity (e.g., "galaxy" + "a55").
    return $hits >= 2;
}

/**
 * Detect canonical phone/accessory brand token from free text.
 */
function rtel_detect_brand_token($text)
{
    $t = mb_strtolower((string)$text);
    $map = [
        'iphone' => 'apple',
        'apple' => 'apple',
        'samsung' => 'samsung',
        'xiaomi' => 'xiaomi',
        'redmi' => 'xiaomi',
        'vivo' => 'vivo',
        'oppo' => 'oppo',
        'realme' => 'realme',
        'oneplus' => 'oneplus',
        'pixel' => 'google',
        'google' => 'google',
        'nokia' => 'nokia',
    ];
    foreach ($map as $needle => $canonical) {
        if ($needle !== '' && mb_strpos($t, $needle) !== false) {
            return $canonical;
        }
    }
    return '';
}

/**
 * True when accessory explicitly mentions another brand than current product.
 */
function rtel_is_incompatible_accessory_brand(array $row, $currentBrandToken)
{
    $currentBrandToken = trim((string)$currentBrandToken);
    if ($currentBrandToken === '') {
        return false;
    }
    $hay = (string)($row['name'] ?? '') . ' ' .
           (string)($row['modal'] ?? '') . ' ' .
           (string)($row['description'] ?? '') . ' ' .
           (string)($row['category_name'] ?? '');
    $accBrand = rtel_detect_brand_token($hay);
    return ($accBrand !== '' && $accBrand !== $currentBrandToken);
}

/** Custom feature row counts as color options (admin-defined names). */
function rtel_feature_row_is_color($featureName)
{
    $n = strtolower(trim(preg_replace('/\s+/', ' ', (string)$featureName)));
    if ($n === '') {
        return false;
    }
    return (bool)preg_match('/\b(color|colour|colours)\b/i', $n);
}

/** Custom feature row counts as storage / RAM-ROM options. */
function rtel_feature_row_is_storage($featureName)
{
    $n = strtolower(trim(preg_replace('/\s+/', ' ', (string)$featureName)));
    if ($n === '') {
        return false;
    }
    if (preg_match('/\b(color|colour|colours)\b/i', $n)) {
        return false;
    }
    return (bool)preg_match(
        '/\b(ram|rom|storage|memory|capacity|variant|disk|ssd|hdd)\b|ram\s*\/\s*rom/i',
        $n
    );
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product = null;
$images = [];
$ratingAvg = 0.0;
$ratingCount = 0;
$soldCount = 0;
$reviews = [];
$accessories = [];
$youMayLove = [];
$availableFeatures = [];
$specRows = [];
$colorOptions = [];
$storageOptions = [];

if ($productId > 0) {
    $stmt = $conn->prepare("SELECT p.product_id, p.cat_id, p.name, p.modal, p.description, p.price, p.cprice, p.quantity,
                                   COALESCE(cat.name, '') AS category_name,
                                   COALESCE(i.image_1,'') image_1, COALESCE(i.image_2,'') image_2, COALESCE(i.image_3,'') image_3,
                                   COALESCE(i.image_4,'') image_4, COALESCE(i.image_5,'') image_5
                            FROM tblproduct p
                            LEFT JOIN tblcategory cat ON p.cat_id = cat.cat_id
                            LEFT JOIN tblimage i ON p.product_id = i.product_id
                            WHERE p.product_id = ? AND p.status = '1' AND p.quantity > 0
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

require 'header.php';

if (!$product) {
    echo '<section class="ftco-section"><div class="container"><div class="alert alert-warning">Product is unavailable.</div></div></section>';
    require 'footer.php';
    exit;
}

// Track product view behavior for AI personalization.
if (!empty($_SESSION['user_id'])) {
    $trackUser = (string)$_SESSION['user_id'];
    rtel_ai_track_behavior($conn, $trackUser, (string)$productId, 'view');
}

foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5'] as $k) {
    $img = trim((string)$product[$k]);
    if ($img !== '' && !in_array($img, $images, true)) $images[] = $img;
}
if (count($images) === 0) $images[] = 'smartphone.png';

// Ratings visibility control (for admin show/hide moderation).
mysqli_query($conn, "ALTER TABLE tblratings ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");

// Ratings info (only visible ratings).
$rq = $conn->prepare("SELECT COALESCE(AVG(rating),0) avg_rating, COUNT(*) total FROM tblratings WHERE product_id = ? AND status = 1");
if ($rq) {
    $pidStr = (string)$productId;
    $rq->bind_param('s', $pidStr);
    $rq->execute();
    $r = $rq->get_result()->fetch_assoc();
    $rq->close();
    $ratingAvg = (float)($r['avg_rating'] ?? 0);
    $ratingCount = (int)($r['total'] ?? 0);
}

$soldStmt = $conn->prepare("SELECT COALESCE(SUM(od.quantity),0) AS sold_total
                            FROM tblorder_details od
                            INNER JOIN tblorder o ON o.order_id = od.order_id
                            WHERE od.product_id = ?
                              AND LOWER(COALESCE(o.status,'pending')) NOT IN ('rejected','deleted')");
if ($soldStmt) {
    $soldStmt->bind_param('i', $productId);
    $soldStmt->execute();
    $soldData = $soldStmt->get_result()->fetch_assoc();
    $soldStmt->close();
    $soldCount = (int)($soldData['sold_total'] ?? 0);
}

$customerNameColumn = rtel_customer_display_name_column($conn);

$reviewSql = "SELECT r.rating, r.review_text, r.created_at, COALESCE(c.`{$customerNameColumn}`, CONCAT('User ', r.cus_id)) AS customer_name
              FROM tblratings r
              LEFT JOIN tblcustomer c ON c.cus_id = r.cus_id
              WHERE r.product_id = ? AND r.status = 1
              ORDER BY r.created_at DESC
              LIMIT 12";
$reviewStmt = $conn->prepare($reviewSql);
if ($reviewStmt) {
    $pidStr = (string)$productId;
    $reviewStmt->bind_param('s', $pidStr);
    $reviewStmt->execute();
    $rr = $reviewStmt->get_result();
    while ($rr && $row = $rr->fetch_assoc()) {
        $reviews[] = $row;
    }
    $reviewStmt->close();
}

$featureStmt = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature WHERE product_id = ? ORDER BY feature_id ASC");
if ($featureStmt) {
    $featureStmt->bind_param('i', $productId);
    $featureStmt->execute();
    $fr = $featureStmt->get_result();
    while ($fr && $f = $fr->fetch_assoc()) {
        $n = trim((string)($f['feature_name'] ?? ''));
        $v = trim((string)($f['feature_value'] ?? ''));
        if ($n === '' || $v === '') {
            continue;
        }
        if (rtel_feature_row_is_color($n)) {
            rtel_merge_unique_strings($colorOptions, rtel_split_option_list($v));
            continue;
        }
        if (rtel_feature_row_is_storage($n)) {
            rtel_merge_unique_strings($storageOptions, rtel_split_option_list($v));
            continue;
        }
        rtel_add_spec_row($specRows, $n, $v);
        $availableFeatures[] = $n . ': ' . $v;
    }
    $featureStmt->close();
}
$availableFeatures = array_values(array_unique($availableFeatures));
$colorOptions = array_values(array_unique($colorOptions));
$storageOptions = array_values(array_unique($storageOptions));

// Strict behavior: show only explicit custom specification rows (tblproduct_feature).

$productName = trim((string)$product['name']);
$productModel = trim((string)($product['modal'] ?? ''));
$productCategory = trim((string)($product['category_name'] ?? ''));
$productCategoryNorm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$productCategory)));
$aiAllowedCategories = ['phones', 'budget phones', 'flagship phones'];
$isPhoneCategory = in_array($productCategoryNorm, $aiAllowedCategories, true);
$hideSpecsForAccessoryCategory = (bool)preg_match('/\b(tempered glass|screen protector|protector|back cover|cover|case|charger|cable|adapter|earbud|earphone|headphone|power bank)\b/i', $productCategoryNorm);
$productMediaTraining = rtel_get_product_media_training_settings($conn);
$dbDescription = trim((string)$product['description']);
$hasDescription = ($dbDescription !== '');
$alertState = [
    'price_drop' => ['enabled' => false, 'target_price' => 0.0],
    'restock' => ['enabled' => false],
];
$isLoggedInUser = !empty($_SESSION['user_id']);
if ($isLoggedInUser) {
    rtel_ensure_price_alert_table($conn);
    $currentUserId = trim((string)$_SESSION['user_id']);
    $productIdStr = (string)$productId;
    if (rtel_price_alert_table_exists($conn)) {
        $alertState = rtel_fetch_product_alert_state($conn, $currentUserId, $productIdStr);
    }

    // Fire one-time alert email when user revisits and condition is met.
    $custCol = rtel_customer_display_name_column($conn);
    $custStmt = $conn->prepare("SELECT email, `{$custCol}` AS customer_name FROM tblcustomer WHERE cus_id = ? LIMIT 1");
    $cust = null;
    if ($custStmt) {
        $custStmt->bind_param("s", $currentUserId);
        $custStmt->execute();
        $cust = $custStmt->get_result()->fetch_assoc();
        $custStmt->close();
    }
    $custEmail = trim((string)($cust['email'] ?? ''));
    $custName = trim((string)($cust['customer_name'] ?? 'Customer'));
    if ($custEmail !== '' && rtel_price_alert_table_exists($conn)) {
        $watchStmt = $conn->prepare("SELECT alert_id, alert_type, target_price, baseline_price
                                     FROM tblprice_alert
                                     WHERE cus_id = ? AND product_id = ? AND status = 1");
        if ($watchStmt) {
            $watchStmt->bind_param("ss", $currentUserId, $productIdStr);
            $watchStmt->execute();
            $watchRes = $watchStmt->get_result();
            $notifyNow = date('Y-m-d H:i:s');
            $publicProductUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/rtel/web/product.php?product_id=' . urlencode($productIdStr);
            while ($watchRes && $watch = $watchRes->fetch_assoc()) {
                $alertId = (int)($watch['alert_id'] ?? 0);
                $alertType = strtolower(trim((string)($watch['alert_type'] ?? '')));
                $targetPrice = (float)($watch['target_price'] ?? 0);
                $baselinePrice = (float)($watch['baseline_price'] ?? 0);
                $shouldNotify = false;
                if ($alertType === 'price_drop' && (float)$product['price'] > 0) {
                    $triggerPrice = $targetPrice > 0 ? $targetPrice : $baselinePrice;
                    if ($triggerPrice > 0 && (float)$product['price'] <= $triggerPrice) {
                        $shouldNotify = true;
                    }
                } elseif ($alertType === 'restock' && (int)$product['quantity'] > 0) {
                    $shouldNotify = true;
                }
                if (!$shouldNotify || $alertId <= 0) {
                    continue;
                }
                $sent = rtel_notify_product_watch_alert(
                    $custEmail,
                    $custName,
                    trim((string)$product['name']),
                    $publicProductUrl,
                    $alertType,
                    $baselinePrice,
                    (float)$product['price']
                );
                if ($sent) {
                    $upd = $conn->prepare("UPDATE tblprice_alert SET status = 0, last_notified_at = ? WHERE alert_id = ?");
                    if ($upd) {
                        $upd->bind_param("si", $notifyNow, $alertId);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
            $watchStmt->close();
            // Refresh state after any auto-disable by sent notifications.
            $alertState = rtel_fetch_product_alert_state($conn, $currentUserId, $productIdStr);
        }
    }
}

// Description from web (GSMArena) — cached to avoid slow repeat loads.
$gsmCacheKey = 'gsm_p' . (int)$productId;
$cachedGsm = rtel_web_cache_get($gsmCacheKey, 604800);
if ($cachedGsm !== null && trim((string)$cachedGsm) !== '') {
    $webDescription = $cachedGsm;
} else {
    $webDescription = fetch_gsmarena_description($productName . ' ' . $productModel);
    rtel_web_cache_set($gsmCacheKey, $webDescription);
}
$finalDescription = $webDescription !== '' ? $webDescription : ($dbDescription !== '' ? $dbDescription : 'Description not available.');

// GSMArena specs (auto-fill for specification table) — cached.
$gsmSpecCacheKey = 'gsm_spec_p' . (int)$productId;
$cachedGsmSpecs = rtel_web_cache_get($gsmSpecCacheKey, 604800);
$gsmSpecRows = [];
if ($cachedGsmSpecs !== null) {
    $decoded = json_decode((string)$cachedGsmSpecs, true);
    if (is_array($decoded) && count($decoded) > 0) {
        $gsmSpecRows = $decoded;
    }
}
if (count($gsmSpecRows) === 0) {
    $gsmSpecRows = rtel_fetch_gsmarena_specs($productName . ' ' . $productModel);
    rtel_web_cache_set($gsmSpecCacheKey, json_encode($gsmSpecRows));
}
if (count($gsmSpecRows) > 0) {
    // Merge GSMArena specs with manually entered specs (show both).
    $manualSpecRows = $specRows;
    $mergedSpecRows = [];
    $seenSpecKeys = [];
    foreach ($gsmSpecRows as $sr) {
        $sn = trim((string)($sr['name'] ?? ''));
        $sv = trim((string)($sr['value'] ?? ''));
        if ($sn === '' || $sv === '') {
            continue;
        }
        $k = mb_strtolower($sn . '|' . $sv);
        if (isset($seenSpecKeys[$k])) {
            continue;
        }
        $seenSpecKeys[$k] = true;
        $mergedSpecRows[] = ['name' => $sn, 'value' => $sv];
    }
    foreach ($manualSpecRows as $sr) {
        $sn = trim((string)($sr['name'] ?? ''));
        $sv = trim((string)($sr['value'] ?? ''));
        if ($sn === '' || $sv === '') {
            continue;
        }
        $k = mb_strtolower($sn . '|' . $sv);
        if (isset($seenSpecKeys[$k])) {
            continue;
        }
        $seenSpecKeys[$k] = true;
        $mergedSpecRows[] = ['name' => $sn, 'value' => $sv];
    }
    $specRows = $mergedSpecRows;
}

// AI media queries — cached (Gemini + GSM are slow on cold load).
$mqCacheKey = 'mq_p' . (int)$productId . '_' . md5($productName . '|' . $productModel . '|' . substr($dbDescription, 0, 400));
$cachedMq = rtel_web_cache_get($mqCacheKey, 86400);
$mediaQueries = null;
if ($cachedMq !== null) {
    $decodedMq = json_decode($cachedMq, true);
    if (is_array($decodedMq) && !empty($decodedMq['image_queries']) && !empty($decodedMq['video_queries'])) {
        $mediaQueries = $decodedMq;
    }
}
if (!is_array($mediaQueries)) {
    // Fast-path: avoid expensive LLM call on cold load if remote budget is already low.
    $remaining = (isset($GLOBALS['rtel_remote_deadline']) ? ((float)$GLOBALS['rtel_remote_deadline'] - microtime(true)) : 1.0);
    if ($remaining < 0.45) {
        $seedName = trim((string)$productName) !== '' ? trim((string)$productName) : trim((string)$productModel);
        $mediaQueries = [
            'image_queries' => [
                trim($seedName . ' camera sample photos'),
                trim($seedName . ' daylight camera test'),
                trim($seedName . ' night mode camera sample'),
                trim($seedName . ' gaming performance test'),
            ],
            'video_queries' => [
                trim($seedName . ' full review'),
                trim($seedName . ' camera test'),
                trim($seedName . ' gaming test'),
                trim($seedName . ' battery test'),
            ],
            'source' => 'fast-fallback',
            'reason' => 'render budget protected',
        ];
    } else {
        $mediaQueries = generate_ai_media_queries($productName, $productModel, $finalDescription, $productMediaTraining);
    }
    rtel_web_cache_set($mqCacheKey, json_encode($mediaQueries));
}
$topics = $mediaQueries['image_queries'];
$videoQueries = $mediaQueries['video_queries'];
$seedVideoName = trim((string)$productName) !== '' ? trim((string)$productName) : trim((string)$productModel);
if (!is_array($videoQueries) || count(array_filter(array_map('trim', (array)$videoQueries))) === 0) {
    $videoQueries = [
        trim($seedVideoName . ' full review'),
        trim($seedVideoName . ' camera test'),
        trim($seedVideoName . ' gaming test'),
        trim($seedVideoName . ' battery test'),
    ];
}
// Force phone-name context in every video query so results stay product-specific.
if ($seedVideoName !== '') {
    $seedTokens = rtel_tokenize_for_match($seedVideoName);
    $fixedVideoQueries = [];
    foreach ((array)$videoQueries as $vq) {
        $vq = trim((string)$vq);
        if ($vq === '') {
            continue;
        }
        $hasSeedContext = false;
        $qTokens = rtel_tokenize_for_match($vq);
        foreach ($seedTokens as $st) {
            if (in_array($st, $qTokens, true)) {
                $hasSeedContext = true;
                break;
            }
        }
        if (!$hasSeedContext) {
            $vq = trim($seedVideoName . ' ' . $vq);
        }
        $fixedVideoQueries[] = $vq;
    }
    $videoQueries = array_values(array_unique($fixedVideoQueries));
}
$mediaSource = (string)($mediaQueries['source'] ?? 'fallback');
$mediaReason = (string)($mediaQueries['reason'] ?? 'unknown');

// Build actual web media results (URLs/IDs), not only search query text.
$mediaResultCacheKey = 'media_results_v6_p' . (int)$productId . '_' . md5(json_encode([$productName, $productModel, $topics, $videoQueries]));
$cachedMediaResults = rtel_web_cache_get($mediaResultCacheKey, 86400);
$webImageResults = [];
$webVideoResults = [];
$cachedMediaNeedsRefresh = false;
if ($cachedMediaResults !== null) {
    $decodedMedia = json_decode($cachedMediaResults, true);
    if (is_array($decodedMedia)) {
        $webImageResults = isset($decodedMedia['images']) && is_array($decodedMedia['images']) ? $decodedMedia['images'] : [];
        $webVideoResults = isset($decodedMedia['videos']) && is_array($decodedMedia['videos']) ? $decodedMedia['videos'] : [];
        if ($isPhoneCategory) {
            $hasEmbeddedVideo = false;
            foreach ($webVideoResults as $vrow) {
                $vid = trim((string)($vrow['id'] ?? ''));
                if ($vid !== '') {
                    $hasEmbeddedVideo = true;
                    break;
                }
            }
            if (!$hasEmbeddedVideo) {
                // Do not trust stale fallback-only cache for phone products.
                $cachedMediaNeedsRefresh = true;
                $webImageResults = [];
                $webVideoResults = [];
            }
        }
    }
}
if ($cachedMediaNeedsRefresh || count($webImageResults) === 0 || count($webVideoResults) === 0) {
    $remainingBudget = (isset($GLOBALS['rtel_remote_deadline']) ? ((float)$GLOBALS['rtel_remote_deadline'] - microtime(true)) : 1.0);
    if ($remainingBudget < 0.35) {
        // Do not block render; use instant safe fallbacks and let cache help subsequent loads.
        if (count($webImageResults) === 0) {
            foreach ($topics as $t) {
                $webImageResults[] = [
                    'url' => 'https://source.unsplash.com/900x600/?' . urlencode((string)$t),
                    'title' => (string)$t,
                    'source' => 'Unsplash Fallback',
                ];
            }
        }
        if (count($webVideoResults) === 0) {
            // Try a lightweight real-video fetch first, even on tight budget,
            // so newly uploaded products do not get stuck on fallback cards.
            foreach ($videoQueries as $q) {
                $q = trim((string)$q);
                if ($q === '') {
                    continue;
                }
                $batch = rtel_fetch_youtube_videos_api($q, 1);
                if (count($batch) === 0) {
                    $batch = rtel_fetch_youtube_videos($q, 1);
                }
                foreach ($batch as $videoRow) {
                    $id = trim((string)($videoRow['id'] ?? ''));
                    if ($id === '') {
                        continue;
                    }
                    $webVideoResults[] = $videoRow;
                }
                if (count($webVideoResults) >= 4) {
                    break;
                }
            }
        }
        // Avoid caching emergency fallback results; retry on next request.
    } else {
    $seenImage = [];
    $imageSeeds = array_values(array_unique(array_filter([
        trim($productName), // full name first
        trim($productName . ' ' . $productCategory),
        trim($productCategory),
        trim($productModel), // low priority fallback only
    ])));
    foreach ($topics as $t) {
        $imageSeeds[] = $t;
    }
    $imageSeeds = array_values(array_unique(array_filter(array_map('trim', $imageSeeds))));
    $requiredImageTokens = rtel_media_context_tokens($productName . ' ' . $productCategory);
    $avoidImageTokens = [
        'galaxy (constellation)',
        'milky way',
        'nebula',
        'astronomy',
        'planet',
        'space',
        'fruit',
        'apple fruit',
        'recipe',
        'food',
        'orchard',
        'tree'
    ];
    if (stripos($productName, 'galaxy') !== false || stripos($productModel, 'galaxy') !== false) {
        $requiredImageTokens[] = 'samsung';
        $requiredImageTokens[] = 'smartphone';
    }
    if (stripos($productName, 'macbook') !== false || stripos($productModel, 'macbook') !== false) {
        $requiredImageTokens[] = 'macbook';
        $requiredImageTokens[] = 'laptop';
    }
    $requiredImageTokens = array_values(array_unique(array_filter(array_map('strtolower', $requiredImageTokens))));

    // 1) Use GSMArena camera samples first (most relevant to phone camera quality).
    $cameraSeeds = array_values(array_unique(array_filter([
        trim($productName . ' ' . $productModel),
        trim($productName),
    ])));
    foreach ($cameraSeeds as $seed) {
        $batch = rtel_fetch_gsmarena_camera_sample_images($seed, 6);
        foreach ($batch as $imgRow) {
            $u = trim((string)($imgRow['url'] ?? ''));
            if ($u === '' || isset($seenImage[$u])) {
                continue;
            }
            if (!rtel_media_is_relevant_image($imgRow, $requiredImageTokens, $avoidImageTokens)) {
                continue;
            }
            $seenImage[$u] = true;
            $webImageResults[] = $imgRow;
            if (count($webImageResults) >= 10) {
                break 2;
            }
        }
    }

    // 2) Use GSMArena product images.
    foreach ($imageSeeds as $seed) {
        $batch = rtel_fetch_gsmarena_images($seed, 4);
        foreach ($batch as $imgRow) {
            $u = trim((string)($imgRow['url'] ?? ''));
            if ($u === '' || isset($seenImage[$u])) {
                continue;
            }
            if (!rtel_media_is_relevant_image($imgRow, $requiredImageTokens, $avoidImageTokens)) {
                continue;
            }
            $seenImage[$u] = true;
            $webImageResults[] = $imgRow;
            if (count($webImageResults) >= 10) {
                break 2;
            }
        }
    }

    // 3) If still low, query GSMArena again with camera/performance intent.
    $imageIntentQueries = array_values(array_unique(array_filter(array_merge(
        $topics,
        [
            trim($productName . ' camera sample'),
            trim($productName . ' gaming performance'),
            trim($productName . ' benchmark screenshot'),
        ]
    ))));
    $extraKeywordsRaw = trim((string)($productMediaTraining['product_media_focus_keywords'] ?? ''));
    if ($extraKeywordsRaw !== '') {
        $extraKeywords = preg_split('/\s*,\s*/', $extraKeywordsRaw) ?: [];
        foreach ($extraKeywords as $kw) {
            $kw = trim((string)$kw);
            if ($kw !== '') {
                $imageIntentQueries[] = trim($productName . ' ' . $kw);
            }
        }
        $imageIntentQueries = array_values(array_unique(array_filter($imageIntentQueries)));
    }

    foreach ($imageIntentQueries as $t) {
        $batch = rtel_fetch_gsmarena_images($t, 3);
        foreach ($batch as $imgRow) {
            $u = trim((string)($imgRow['url'] ?? ''));
            if ($u === '' || isset($seenImage[$u])) {
                continue;
            }
            if (!rtel_media_is_relevant_image($imgRow, $requiredImageTokens, $avoidImageTokens)) {
                continue;
            }
            $seenImage[$u] = true;
            $webImageResults[] = $imgRow;
            if (count($webImageResults) >= 10) {
                break 2;
            }
        }
    }
    if (count($webImageResults) === 0) {
        // Hard fallback to keep UI populated even if third-party APIs throttle.
        foreach ($imageIntentQueries as $t) {
            $webImageResults[] = [
                'url' => 'https://source.unsplash.com/900x600/?' . urlencode((string)$t),
                'title' => (string)$t,
                'source' => 'Unsplash Fallback',
            ];
        }
    }

    $seenVideo = [];
    foreach ($videoQueries as $q) {
        $batch = rtel_fetch_youtube_videos_api($q, 2);
        if (count($batch) === 0) {
            $batch = rtel_fetch_youtube_videos($q, 2);
        }
        foreach ($batch as $videoRow) {
            $id = trim((string)($videoRow['id'] ?? ''));
            if ($id === '' || isset($seenVideo[$id])) {
                continue;
            }
            $seenVideo[$id] = true;
            $webVideoResults[] = $videoRow;
            if (count($webVideoResults) >= 10) {
                break 2;
            }
        }
    }
    // Keep only concrete YouTube videos (with real IDs).
    $webVideoResults = array_values(array_filter($webVideoResults, function ($v) {
        return trim((string)($v['id'] ?? '')) !== '';
    }));

    // If we have video IDs, also use their thumbnails as highly relevant image cards.
    if (count($webImageResults) < 10 && count($webVideoResults) > 0) {
        foreach ($webVideoResults as $videoRow) {
            $vid = trim((string)($videoRow['id'] ?? ''));
            if ($vid === '') {
                continue;
            }
            $thumb = 'https://i.ytimg.com/vi/' . rawurlencode($vid) . '/hqdefault.jpg';
            if (isset($seenImage[$thumb])) {
                continue;
            }
            $seenImage[$thumb] = true;
            $webImageResults[] = [
                'url' => $thumb,
                'title' => trim((string)($videoRow['title'] ?? 'Product test video thumbnail')),
                'source' => 'YouTube Thumbnail',
            ];
            if (count($webImageResults) >= 10) {
                break;
            }
        }
    }

        rtel_web_cache_set($mediaResultCacheKey, json_encode([
            'images' => $webImageResults,
            'videos' => $webVideoResults,
        ]));
    }
}

// Do not inject query-only fallback cards; show the section only for real video IDs.
$webVideoResults = array_values(array_filter((array)$webVideoResults, function ($v) {
    return trim((string)($v['id'] ?? '')) !== '';
}));

// AI/related accessory suggestions from DB.
$nameLower = strtolower($productName . ' ' . $productModel);
$brandHint = '';
foreach (['iphone', 'samsung', 'redmi', 'vivo', 'oppo', 'nokia', 'realme', 'oneplus', 'pixel', 'xiaomi'] as $b) {
    if (strpos($nameLower, $b) !== false) { $brandHint = $b; break; }
}
$currentBrandToken = rtel_detect_brand_token($productName . ' ' . $productModel);
$contextTokens = rtel_tokenize_for_match($productName . ' ' . $productModel . ' ' . $dbDescription . ' ' . implode(' ', $availableFeatures));
$phoneContext = [
    'port' => rtel_detect_port_type($productName . ' ' . $productModel . ' ' . $dbDescription . ' ' . implode(' ', $availableFeatures)),
    'ecosystem' => rtel_detect_ecosystem_type($productName . ' ' . $productModel . ' ' . $dbDescription . ' ' . implode(' ', $availableFeatures)),
    'model_raw' => trim((string)$productModel),
    'model_tokens' => rtel_phone_model_tokens($productName . ' ' . $productModel),
    'model_core_tokens' => rtel_phone_model_core_tokens($productName . ' ' . $productModel),
];
$excludedPhoneCategoriesRegex = 'smart phones|phones|budget phones|flagship phones';

if ($isPhoneCategory) {
    $accSql = "SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity,
                      COALESCE(i.image_1, 'smartphone.png') AS image_1,
                      COALESCE(p.description, '') AS description,
                      COALESCE(c.name, '') AS category_name,
                      COALESCE(f.feature_blob, '') AS feature_blob
               FROM tblproduct p
               LEFT JOIN tblimage i ON p.product_id = i.product_id
               LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
               LEFT JOIN (
                   SELECT product_id, GROUP_CONCAT(CONCAT(feature_name, ':', feature_value) SEPARATOR ' | ') AS feature_blob
                   FROM tblproduct_feature
                   GROUP BY product_id
               ) f ON f.product_id = p.product_id
               WHERE p.status = '1' AND p.quantity > 0 AND p.product_id <> ?
                 AND LOWER(COALESCE(c.name,'')) NOT REGEXP '{$excludedPhoneCategoriesRegex}'
                 AND (
                    LOWER(p.name) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                    OR LOWER(COALESCE(p.description,'')) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                    OR LOWER(COALESCE(c.name,'')) REGEXP 'accessor|cover|case|charger|glass|protector|cable|adapter|headphone|earbud|watch'
                 )";
    $accStmt = $conn->prepare($accSql);
    if ($accStmt) {
        $accStmt->bind_param('i', $productId);
        $accStmt->execute();
        $res = $accStmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            if (rtel_is_incompatible_accessory_brand($row, $currentBrandToken)) {
                continue;
            }
            $isModelMatch = rtel_accessory_matches_phone_model($row, $phoneContext);
            if (!$isModelMatch) {
                continue;
            }
            $row['_match_score'] = rtel_accessory_match_score($row, $contextTokens, $brandHint) + rtel_accessory_compatibility_bonus($row, $phoneContext) + 10;
            if ((int)$row['_match_score'] <= 0) {
                continue;
            }
            $accessories[] = $row;
        }
        $accStmt->close();
    }
}
if ($isPhoneCategory && count($accessories) === 0) {
    $fallback = $conn->prepare("SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity, COALESCE(i.image_1, 'smartphone.png') AS image_1,
                                       COALESCE(p.description, '') AS description,
                                       COALESCE(c.name, '') AS category_name,
                                       COALESCE(f.feature_blob, '') AS feature_blob
                                FROM tblproduct p
                                LEFT JOIN tblimage i ON p.product_id = i.product_id
                                LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
                                LEFT JOIN (
                                    SELECT product_id, GROUP_CONCAT(CONCAT(feature_name, ':', feature_value) SEPARATOR ' | ') AS feature_blob
                                    FROM tblproduct_feature
                                    GROUP BY product_id
                                ) f ON f.product_id = p.product_id
                                WHERE p.status='1' AND p.quantity>0 AND p.product_id<>?
                                  AND LOWER(COALESCE(c.name,'')) NOT REGEXP '{$excludedPhoneCategoriesRegex}'
                                  AND (
                                      LOWER(p.name) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                                      OR LOWER(COALESCE(p.description,'')) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                                      OR LOWER(COALESCE(c.name,'')) REGEXP 'accessor|cover|case|charger|glass|protector|cable|adapter|headphone|earbud|watch'
                                  )
                                ORDER BY p.quantity DESC, p.price ASC LIMIT 8");
    if ($fallback) {
        $fallback->bind_param('i', $productId);
        $fallback->execute();
        $fr = $fallback->get_result();
        while ($fr && $row = $fr->fetch_assoc()) {
            if (rtel_is_incompatible_accessory_brand($row, $currentBrandToken)) {
                continue;
            }
            $isModelMatch = rtel_accessory_matches_phone_model($row, $phoneContext);
            if (!$isModelMatch) {
                continue;
            }
            $modelBonus = $isModelMatch ? 10 : 0;
            $row['_match_score'] = rtel_accessory_match_score($row, $contextTokens, $brandHint) + rtel_accessory_compatibility_bonus($row, $phoneContext) + $modelBonus;
            if ((int)$row['_match_score'] <= 0) {
                continue;
            }
            $accessories[] = $row;
        }
        $fallback->close();
    }
}
if ($isPhoneCategory && count($accessories) > 0) {
    usort($accessories, function ($a, $b) {
        $sa = (int)($a['_match_score'] ?? 0);
        $sb = (int)($b['_match_score'] ?? 0);
        if ($sa !== $sb) {
            return $sb <=> $sa;
        }
        $qa = (int)($a['quantity'] ?? 0);
        $qb = (int)($b['quantity'] ?? 0);
        if ($qa !== $qb) {
            return $qb <=> $qa;
        }
        return (float)($a['price'] ?? 0) <=> (float)($b['price'] ?? 0);
    });
    $accessories = array_slice($accessories, 0, 8);
}
{
    $productCatId = (int)($product['cat_id'] ?? 0);
    if ($productCatId > 0) {
        $loveSql = "SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity,
                           COALESCE(i.image_1, 'smartphone.png') AS image_1,
                           CASE WHEN p.cat_id = ? THEN 0 ELSE 1 END AS cat_priority
                    FROM tblproduct p
                    LEFT JOIN tblimage i ON p.product_id = i.product_id
                    WHERE p.status='1'
                      AND p.product_id <> ?
                    ORDER BY cat_priority ASC, p.quantity DESC, p.price ASC
                    LIMIT 8";
        $loveStmt = $conn->prepare($loveSql);
        if ($loveStmt) {
            $loveStmt->bind_param('ii', $productCatId, $productId);
            $loveStmt->execute();
            $loveRes = $loveStmt->get_result();
            while ($loveRes && $row = $loveRes->fetch_assoc()) {
                $youMayLove[] = $row;
            }
            $loveStmt->close();
        }
    }
    if (count($youMayLove) === 0) {
        $loveFallbackSql = "SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity,
                                   COALESCE(i.image_1, 'smartphone.png') AS image_1
                            FROM tblproduct p
                            LEFT JOIN tblimage i ON p.product_id = i.product_id
                            WHERE p.status='1'
                              AND p.product_id <> ?
                            ORDER BY p.quantity DESC, p.price ASC
                            LIMIT 8";
        $loveFallback = $conn->prepare($loveFallbackSql);
        if ($loveFallback) {
            $loveFallback->bind_param('i', $productId);
            $loveFallback->execute();
            $lfRes = $loveFallback->get_result();
            while ($lfRes && $row = $lfRes->fetch_assoc()) {
                $youMayLove[] = $row;
            }
            $loveFallback->close();
        }
    }
    $youMayLove = rtel_dedupe_products_by_id($youMayLove, 8);
}
$bundleAccessories = array_slice($accessories, 0, 3);
$bundleMinItems = 1;
if ($isPhoneCategory && count($bundleAccessories) < $bundleMinItems) {
    $bundleFallback = $conn->prepare("SELECT p.product_id, p.name, p.modal, p.price, p.cprice, p.quantity, COALESCE(i.image_1, 'smartphone.png') AS image_1,
                                             COALESCE(p.description, '') AS description,
                                             COALESCE(c.name, '') AS category_name,
                                             COALESCE(f.feature_blob, '') AS feature_blob
                                      FROM tblproduct p
                                      LEFT JOIN tblimage i ON p.product_id = i.product_id
                                      LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
                                      LEFT JOIN (
                                          SELECT product_id, GROUP_CONCAT(CONCAT(feature_name, ':', feature_value) SEPARATOR ' | ') AS feature_blob
                                          FROM tblproduct_feature
                                          GROUP BY product_id
                                      ) f ON f.product_id = p.product_id
                                      WHERE p.status='1' AND p.quantity>0 AND p.product_id<>?
                                        AND LOWER(COALESCE(c.name,'')) NOT REGEXP '{$excludedPhoneCategoriesRegex}'
                                        AND (
                                            LOWER(p.name) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                                            OR LOWER(COALESCE(p.description,'')) REGEXP 'back cover|cover|case|tempered|glass|screen protector|protector|charger|charging|cable|usb|adapter|earbud|earphone|headphone|power bank|smart watch|watch'
                                            OR LOWER(COALESCE(c.name,'')) REGEXP 'accessor|cover|case|charger|glass|protector|cable|adapter|headphone|earbud|watch'
                                        )
                                      ORDER BY p.quantity DESC, p.price ASC
                                      LIMIT 6");
    if ($bundleFallback) {
        $bundleFallback->bind_param('i', $productId);
        $bundleFallback->execute();
        $br = $bundleFallback->get_result();
        while ($br && $row = $br->fetch_assoc()) {
            if (rtel_is_incompatible_accessory_brand($row, $currentBrandToken)) {
                continue;
            }
            $rowText = mb_strtolower((string)($row['name'] ?? '') . ' ' . (string)($row['modal'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['category_name'] ?? '') . ' ' . (string)($row['feature_blob'] ?? ''));
            $isModelBoundAccessory = (bool)preg_match('/back cover|cover|case|tempered|glass|screen protector|protector/i', $rowText);
            if ($isModelBoundAccessory && !rtel_accessory_matches_phone_model($row, $phoneContext)) {
                continue;
            }
            if (rtel_accessory_compatibility_bonus($row, $phoneContext) < -3) {
                continue;
            }
            $bundleAccessories[] = $row;
            if (count($bundleAccessories) >= 3) {
                break;
            }
        }
        $bundleFallback->close();
    }
}
$bundleIds = array_map(function ($row) {
    return (string)($row['product_id'] ?? '');
}, $bundleAccessories);
$bundleIds = array_values(array_filter($bundleIds, function ($v) {
    return trim((string)$v) !== '';
}));
$bundleSavings = 0.0;
foreach ($bundleAccessories as $bi) {
    $bp = (float)($bi['price'] ?? 0);
    $bc = (float)($bi['cprice'] ?? 0);
    if ($bc > $bp) {
        $bundleSavings += ($bc - $bp);
    }
}

$adminBundles = [];
$modalNeedle = trim((string)$productModel);
$bundleDebugEnabled = isset($_GET['bundle_debug']) && $_GET['bundle_debug'] === '1';
$bundleDebugRows = [];
$bundleStmt = null;
if ($modalNeedle !== '') {
    $bundleStmt = $conn->prepare("SELECT b.bundle_id, b.bundle_name, b.bundle_image, b.bundle_price, b.bundle_model, b.expiry_date, bi.product_id,
                                         COALESCE(p.name, CONCAT('Product #', bi.product_id)) AS name,
                                         COALESCE(p.modal, '') AS modal,
                                         COALESCE(p.price, 0) AS price,
                                         COALESCE(p.cprice, 0) AS cprice,
                                         COALESCE(pi.image_1, 'smartphone.png') AS image_1
                              FROM tblbundle b
                              JOIN tblbundle_item bi ON bi.bundle_id = b.bundle_id
                              LEFT JOIN tblproduct p ON p.product_id = bi.product_id
                              LEFT JOIN tblimage pi ON pi.product_id = bi.product_id
                              WHERE b.status = 1
                                AND (b.expiry_date IS NULL OR b.expiry_date = '' OR b.expiry_date >= CURDATE())
                                AND TRIM(COALESCE(b.bundle_model, '')) <> ''
                                AND (
                                    LOWER(COALESCE(b.bundle_model, '')) = LOWER(?)
                                    OR LOWER(?) LIKE CONCAT('%', LOWER(COALESCE(b.bundle_model, '')), '%')
                                    OR LOWER(COALESCE(b.bundle_model, '')) LIKE CONCAT('%', LOWER(?), '%')
                                    OR EXISTS (
                                        SELECT 1
                                        FROM tblbundle_item bi2
                                        LEFT JOIN tblproduct p2 ON p2.product_id = bi2.product_id
                                        WHERE bi2.bundle_id = b.bundle_id
                                          AND LOWER(COALESCE(p2.modal, '')) = LOWER(?)
                                    )
                                )
                              ORDER BY b.bundle_id DESC, bi.sort_order ASC, bi.bundle_item_id ASC");
}
if ($bundleStmt) {
    $bundleStmt->bind_param("ssss", $modalNeedle, $modalNeedle, $modalNeedle, $modalNeedle);
    $bundleStmt->execute();
    $bRes = $bundleStmt->get_result();
    while ($bRes && $row = $bRes->fetch_assoc()) {
        $bid = (int)$row['bundle_id'];
        if (!isset($adminBundles[$bid])) {
            $adminBundles[$bid] = [
                'bundle_id' => $bid,
                'bundle_name' => (string)$row['bundle_name'],
                'bundle_image' => (string)($row['bundle_image'] ?? ''),
                'bundle_model' => (string)($row['bundle_model'] ?? ''),
                'bundle_price' => (float)$row['bundle_price'],
                'expiry_date' => (string)($row['expiry_date'] ?? ''),
                'items' => []
            ];
        }
        $pid = (string)$row['product_id'];
        $variants = [];
        $colorVariants = [];
        $storageVariants = [];
        $vfStmt = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature
                                  WHERE product_id = ?
                                    AND LOWER(feature_name) REGEXP 'color|ram|rom|storage|variant'
                                  ORDER BY feature_id ASC");
        if ($vfStmt) {
            $vfStmt->bind_param("s", $pid);
            $vfStmt->execute();
            $vfRes = $vfStmt->get_result();
            while ($vfRes && $vf = $vfRes->fetch_assoc()) {
                $fname = (string)($vf['feature_name'] ?? '');
                $fval = (string)($vf['feature_value'] ?? '');
                if (rtel_feature_row_is_color($fname)) {
                    rtel_merge_unique_strings($colorVariants, rtel_split_option_list($fval));
                    continue;
                }
                if (rtel_feature_row_is_storage($fname)) {
                    rtel_merge_unique_strings($storageVariants, rtel_split_option_list($fval));
                    continue;
                }
                rtel_merge_unique_strings($variants, rtel_split_option_list($fval));
            }
            $vfStmt->close();
        }
        rtel_merge_unique_strings($variants, $colorVariants);
        rtel_merge_unique_strings($variants, $storageVariants);
        $adminBundles[$bid]['items'][] = [
            'product_id' => $pid,
            'name' => (string)$row['name'],
            'modal' => (string)($row['modal'] ?? ''),
            'price' => (float)($row['price'] ?? 0),
            'cprice' => (float)($row['cprice'] ?? 0),
            'image_1' => (string)($row['image_1'] ?? 'smartphone.png'),
            'variants' => $variants,
            'color_variants' => $colorVariants,
            'storage_variants' => $storageVariants
        ];
    }
    $bundleStmt->close();
}
$adminBundles = array_values($adminBundles);
$bundleTotalSavings = 0.0;
foreach ($adminBundles as $k => $b) {
    $items = (array)($b['items'] ?? []);
    $listPrice = 0.0;
    foreach ($items as $it) {
        $listPrice += (float)($it['price'] ?? 0);
    }
    $bundlePrice = (float)($b['bundle_price'] ?? 0);
    $saving = max(0.0, $listPrice - $bundlePrice);
    $adminBundles[$k]['list_price'] = $listPrice;
    $adminBundles[$k]['saving'] = $saving;
    $bundleTotalSavings += $saving;
}

if ($bundleDebugEnabled) {
    $dbgSql = "SELECT b.bundle_id, b.bundle_name, b.bundle_model, b.status, b.expiry_date
               FROM tblbundle b
               ORDER BY b.bundle_id DESC";
    $dbgRes = $conn->query($dbgSql);
    if ($dbgRes) {
        $today = date('Y-m-d');
        while ($dbg = $dbgRes->fetch_assoc()) {
            $bundleModel = trim((string)($dbg['bundle_model'] ?? ''));
            $statusOk = ((int)($dbg['status'] ?? 0) === 1);
            $expiry = trim((string)($dbg['expiry_date'] ?? ''));
            $expiryOk = ($expiry === '' || $expiry >= $today);
            $modelOk = false;
            if ($modalNeedle !== '' && $bundleModel !== '') {
                $mNeedle = mb_strtolower($modalNeedle);
                $mBundle = mb_strtolower($bundleModel);
                $modelOk = ($mBundle === $mNeedle) || (mb_strpos($mNeedle, $mBundle) !== false) || (mb_strpos($mBundle, $mNeedle) !== false);
            }
            $bundleDebugRows[] = [
                'id' => (int)$dbg['bundle_id'],
                'name' => (string)($dbg['bundle_name'] ?? ''),
                'bundle_model' => $bundleModel,
                'status_ok' => $statusOk,
                'expiry_ok' => $expiryOk,
                'model_ok' => $modelOk,
                'is_included' => ($statusOk && $expiryOk && $modelOk),
                'expiry' => $expiry,
            ];
        }
    }
}

$mainImage = $images[0];
?>

<style>
.pd-wrap { background:#fff; border:1px solid #e8e8e8; border-radius:16px; padding:18px; box-shadow:0 12px 28px rgba(0,0,0,.06); }
.pd-main { width:100%; height:430px; object-fit:contain; border:1px solid #ececec; border-radius:12px; background:#fff; }
.pd-main-wrap { position:relative; }
.pd-main.zoomable { cursor:zoom-in; }
.pd-thumbs { margin-top:12px; display:grid; grid-template-columns:repeat(auto-fit,minmax(82px,1fr)); gap:10px; }
.pd-thumbs img { width:100%; height:82px; object-fit:cover; border-radius:10px; border:1px solid #e5e5e5; cursor:pointer; }
.info-pill { display:inline-block; margin:0 8px 8px 0; padding:6px 12px; border:1px solid #ddd; border-radius:999px; font-size:13px; background:#fafafa; }
.price-big { font-size:30px; font-weight:800; color:#111; }
.price-old { text-decoration:line-through; color:#777; margin-right:10px; }
.starline { color:#f59e0b; font-size:18px; letter-spacing:1px; }
.block-card { background:#fff; border:1px solid #e9e9e9; border-radius:12px; padding:16px; }
.web-image-card { border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 8px 18px rgba(0,0,0,.06); }
.web-image-card img { width:100%; height:210px; object-fit:cover; }
.web-image-card .meta { padding:10px 12px; font-size:13px; }
.image-carousel .item { padding: 4px; }
.video-carousel .item { padding:4px; }
.video-card { border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; background:#fff; }
.video-card iframe { width:100%; height:220px; border:0; }
.video-card .meta { padding:10px 12px; font-size:13px; }
.video-link { display:block; text-decoration:none; color:inherit; }
.acc-card { border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 8px 18px rgba(0,0,0,.06); height:100%; }
.acc-card img { width:100%; height:170px; object-fit:cover; }
.acc-card .body { padding:12px; }
.acc-title { font-size:14px; font-weight:600; min-height:40px; }
.owl-nav button { background:#111 !important; color:#fff !important; border-radius:999px !important; width:34px; height:34px; }
.image-carousel,
.video-carousel,
.review-carousel,
.bundle-carousel {
  padding-bottom:44px;
}
.image-carousel .owl-nav,
.video-carousel .owl-nav,
.review-carousel .owl-nav,
.bundle-carousel .owl-nav {
  position:relative;
  margin-top:14px;
  height:34px;
}
.image-carousel .owl-nav button.owl-prev,
.video-carousel .owl-nav button.owl-prev,
.review-carousel .owl-nav button.owl-prev,
.bundle-carousel .owl-nav button.owl-prev {
  position:absolute;
  left:0;
  top:0;
}
.image-carousel .owl-nav button.owl-next,
.video-carousel .owl-nav button.owl-next,
.review-carousel .owl-nav button.owl-next,
.bundle-carousel .owl-nav button.owl-next {
  position:absolute;
  right:0;
  top:0;
}
.image-carousel .owl-dots,
.video-carousel .owl-dots,
.review-carousel .owl-dots,
.bundle-carousel .owl-dots {
  margin-top:-31px;
  text-align:center;
}
.image-carousel .owl-dot span,
.video-carousel .owl-dot span,
.review-carousel .owl-dot span,
.bundle-carousel .owl-dot span {
  width:8px;
  height:8px;
}
.bundle-carousel .owl-nav button.owl-prev,
.bundle-carousel .owl-nav button.owl-next {
  width:34px;
  height:34px;
  border-radius:50%;
  border:1px solid #cfe0ff !important;
  background:#ffffff !important;
  color:#1d4ed8 !important;
  transition:all .2s ease;
}
.bundle-carousel .owl-nav button.owl-prev:hover,
.bundle-carousel .owl-nav button.owl-next:hover {
  background:#1d4ed8 !important;
  color:#ffffff !important;
  border-color:#1d4ed8 !important;
}
.feature-pick { width:100%; border:1px solid #ddd; border-radius:10px; padding:10px 12px; }
.review-card { border:1px solid #e6e6e6; border-radius:12px; padding:14px; background:#fff; min-height:170px; }
.review-star { color:#f59e0b; letter-spacing:1px; }
.spec-table-wrap {
  border:1px solid #e8edf6;
  border-radius:12px;
  overflow:hidden;
  background:#fff;
}
.spec-table {
  margin-bottom:0;
  border-collapse:separate;
  border-spacing:0;
}
.spec-table thead th {
  background:linear-gradient(180deg,#f7f9fd 0%,#eef3fb 100%);
  color:#30415f;
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.6px;
  border-bottom:1px solid #e3eaf6;
  padding:12px;
  line-height:1.5;
}
.spec-table tbody tr:nth-child(even) {
  background:#fbfcff;
}
.spec-table tbody th,
.spec-table tbody td {
  border-top:1px solid #edf1f7;
  padding:12px;
  vertical-align:top;
  line-height:1.5;
}
.spec-table tbody th {
  width:34%;
  color:#26354f;
  font-weight:700;
  background:#f8faff;
}
.spec-table tbody td {
  color:#3b4860;
  font-weight:500;
}
.spec-toggle-btn {
  border:0;
  background:transparent;
  padding:0;
  color:#1f2f4a;
  font-weight:700;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.spec-toggle-btn:focus {
  outline:none;
}
.spec-toggle-arrow {
  display:inline-block;
  transition:transform .2s ease;
}
.spec-toggle-btn[aria-expanded="true"] .spec-toggle-arrow {
  transform:rotate(90deg);
}
.sold-pill {
  background:#e9f8ef;
  border:1px solid #b9eac8;
  color:#1f8f45;
  font-weight:700;
}
.zoom-modal .modal-dialog {
  max-width:1000px;
}
.zoom-modal .modal-content {
  background:#0f1217;
  border:0;
  border-radius:14px;
}
.zoom-toolbar {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:10px 14px;
  border-bottom:1px solid rgba(255,255,255,.1);
}
.zoom-actions .btn {
  margin-left:6px;
}
.zoom-stage {
  height:72vh;
  overflow:hidden;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#0b0e12;
}
.zoom-image {
  max-width:100%;
  max-height:100%;
  transform-origin:center center;
  transition:transform .12s linear;
  user-select:none;
  -webkit-user-drag:none;
  cursor:grab;
}
.zoom-image.dragging {
  cursor:grabbing;
}
.compare-ai-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  max-width: 360px;
  padding: 11px 18px;
  border: 0;
  border-radius: 999px;
  color: #fff !important;
  font-weight: 700;
  letter-spacing: .2px;
  background: linear-gradient(90deg, #6d28d9 0%, #4f46e5 45%, #0ea5e9 100%);
  box-shadow: 0 0 0 rgba(79,70,229,.45), 0 10px 24px rgba(14,165,233,.35);
  text-decoration: none !important;
  animation: compareGlow 1.8s ease-in-out infinite;
}
.compare-ai-btn:hover {
  color: #fff !important;
  transform: translateY(-1px);
}
@keyframes compareGlow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(79,70,229,.45), 0 10px 24px rgba(14,165,233,.35); }
  50% { box-shadow: 0 0 0 10px rgba(79,70,229,0), 0 12px 28px rgba(79,70,229,.45); }
}
.ai-modal .modal-dialog {
  max-width: 1100px;
}
.ai-modal .modal-content {
  border: 0;
  border-radius: 16px;
  overflow: hidden;
  background: linear-gradient(145deg, #f8fbff 0%, #eef4ff 45%, #f5f8ff 100%);
  color: #1e293b;
  box-shadow: 0 20px 44px rgba(30, 41, 59, .18);
}
.ai-modal-header {
  padding: 14px 18px;
  border-bottom: 1px solid rgba(37, 99, 235, .14);
  background: linear-gradient(90deg, rgba(99,102,241,.16), rgba(14,165,233,.14));
}
.ai-modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: .2px;
  color: #1e3a8a;
}
.ai-modal-subtitle {
  margin: 4px 0 0;
  color: #475569;
  font-size: 13px;
}
.ai-modal .modal-body { padding: 18px; }
.ai-input-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.ai-field-label {
  display: block;
  margin-bottom: 6px;
  color: #334155;
  font-weight: 700;
  font-size: 13px;
}
.ai-input {
  width: 100%;
  border: 1px solid #c7d9f7;
  border-radius: 10px;
  background: #ffffff;
  color: #0f172a;
  padding: 11px 12px;
}
.ai-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.14);
}
.ai-inline-error {
  margin-top: 6px;
  font-size: 12px;
  color: #b91c1c;
  font-weight: 700;
  display: none;
}
.ai-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}
.ai-cancel-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 10px;
  padding: 9px 14px;
}
.ai-go-btn {
  border: 0;
  border-radius: 10px;
  padding: 9px 14px;
  color: #fff;
  font-weight: 700;
  background: linear-gradient(90deg, #7c3aed, #2563eb);
  box-shadow: 0 10px 20px rgba(59,130,246,.35);
}
.ai-compare-result {
  margin-top: 16px;
  display: none;
}
.ai-compare-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 14px;
}
.ai-result-card {
  border: 1px solid #dbe7fb;
  border-radius: 14px;
  background: #ffffff;
  overflow: hidden;
}
.ai-result-head {
  padding: 12px 14px;
  font-size: 14px;
  font-weight: 800;
  color: #1e3a8a;
  border-bottom: 1px solid #dbe7fb;
  background: #eff6ff;
}
.ai-result-list {
  list-style: none;
  margin: 0;
  padding: 10px 12px;
  max-height: 420px;
  overflow: auto;
}
.ai-result-list li {
  display: flex;
  gap: 8px;
  padding: 10px 0;
  border-bottom: 1px dashed #e2e8f0;
  font-size: 13px;
  line-height: 1.4;
}
.ai-result-list li:last-child {
  border-bottom: 0;
}
.ai-spec-name {
  min-width: 128px;
  color: #2563eb;
  font-weight: 700;
}
.ai-spec-value {
  color: #1e293b;
}
.ai-compare-status {
  margin-top: 10px;
  font-size: 13px;
  color: #475569;
}
.ai-compare-availability {
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid #dbe7fb;
  border-radius: 10px;
  background: #f8fbff;
  display: none;
}
.ai-compare-availability .row-item {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:8px 0;
  border-bottom:1px dashed #d9e1f3;
  font-size:13px;
}
.ai-compare-availability .row-item:last-child {
  border-bottom:0;
}
.ai-compare-note-bad {
  color:#b91c1c;
  font-weight:700;
}
.ai-compare-verdict {
  margin-top: 12px;
  display: none;
  border: 1px solid #dbe7fb;
  border-radius: 10px;
  background: #f8fbff;
  padding: 10px 12px;
}
.ai-verdict-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-top: 8px;
}
.ai-verdict-item {
  border: 1px solid #d6e4ff;
  border-radius: 8px;
  padding: 8px;
  background: #fff;
  font-size: 12px;
}
.bundle-shell {
  border: 1px solid #dbe7fb;
  border-radius: 14px;
  background: linear-gradient(180deg, #f9fbff, #eff6ff);
  padding: 14px;
  margin-bottom: 14px;
}
.bundle-shell .bundle-head {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:10px;
}
.bundle-shell .bundle-savings-total {
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  padding:6px 10px;
  border-radius:999px;
  background:#ecfdf3;
  color:#166534;
  border:1px solid #bbf7d0;
  font-weight:700;
}
.bundle-card {
  border: 1px solid #d8e4ff;
  border-radius: 12px;
  background: #fff;
  padding: 12px;
  margin-bottom: 10px;
  box-shadow: 0 6px 16px rgba(30, 64, 175, .08);
}
.bundle-card-cover {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e5eaf7;
  margin-bottom: 10px;
}
.bundle-item-row {
  border: 1px solid #e5eaf7;
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 10px;
  background: #fff;
}
.bundle-item-thumb {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
}
.bundle-price-row {
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:8px;
}
.bundle-price-chip {
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  padding:5px 9px;
  border-radius:999px;
  border:1px solid #dbeafe;
  background:#f8fbff;
  color:#1e40af;
}
.bundle-price-chip.save {
  border-color:#bbf7d0;
  background:#ecfdf3;
  color:#166534;
  font-weight:700;
}
.bundle-variant-select {
  height: 34px;
  min-width: 170px;
  max-width: 210px;
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
.bundle-list {
  margin: 8px 0 12px;
  padding-left: 18px;
}
.bundle-list li {
  margin-bottom: 4px;
  color: #1e3a8a;
}
@media (max-width: 991.98px) {
  .ai-input-row {
    grid-template-columns: 1fr;
  }
  .ai-compare-grid {
    grid-template-columns: 1fr;
  }
  .ai-verdict-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 767.98px) {
  .spec-table thead {
    display:none;
  }
  .spec-table,
  .spec-table tbody,
  .spec-table tr,
  .spec-table th,
  .spec-table td {
    display:block;
    width:100%;
  }
  .spec-table tbody tr {
    border-top:1px solid #edf1f7;
    padding:10px 12px;
    background:#fff !important;
  }
  .spec-table tbody th,
  .spec-table tbody td {
    border:0;
    padding:2px 0;
    line-height:1.4;
  }
  .spec-table tbody th {
    width:100%;
    background:transparent;
    color:#2b3f60;
    font-size:13px;
  }
  .spec-table tbody td {
    color:#4a5b78;
    font-size:14px;
  }
}
</style>

<section class="ftco-section">
  <div class="container">
    <div class="pd-wrap">
      <div class="row">
        <div class="col-lg-6 mb-4 mb-lg-0">
          <div class="pd-main-wrap">
            <img id="pdMainImage" src="../images/<?php echo h($mainImage); ?>" alt="<?php echo h($productName); ?>" class="pd-main zoomable" title="Click to zoom preview">
          </div>
          <div class="pd-thumbs">
            <?php foreach ($images as $img): ?>
              <img src="../images/<?php echo h($img); ?>" alt="thumb" class="js-pd-thumb" data-full="../images/<?php echo h($img); ?>">
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-lg-6">
          <h2 class="mb-2"><?php echo h($productName); ?></h2>
          <?php if ($productModel !== '' || (int)$product['quantity'] > 0 || $soldCount > 0 || $ratingCount > 0): ?>
          <div class="mb-2">
            <?php if ($productModel !== ''): ?>
              <span class="info-pill">Model: <?php echo h($productModel); ?></span>
            <?php endif; ?>
            <?php if ((int)$product['quantity'] > 0): ?>
              <span class="info-pill">Quantity: <?php echo (int)$product['quantity']; ?></span>
            <?php endif; ?>
            <?php if ($soldCount > 0): ?>
              <span class="info-pill sold-pill">Sold: <?php echo $soldCount; ?></span>
            <?php endif; ?>
            <?php if ($ratingCount > 0): ?>
              <span class="info-pill">Ratings Count: <?php echo $ratingCount; ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="starline mb-1">
            <?php
            $stars = (int)round($ratingAvg);
            for ($i = 1; $i <= 5; $i++) echo $i <= $stars ? '★' : '☆';
            ?>
            <span style="font-size:14px;color:#555;"> <?php echo number_format($ratingAvg, 1); ?>/5</span>
          </div>

          <div class="mb-3">
            <?php if ((float)$product['cprice'] > 0 && (float)$product['cprice'] > (float)$product['price']): ?>
              <span class="price-old"><?php echo rs($product['cprice']); ?></span>
            <?php endif; ?>
            <span class="price-big"><?php echo rs($product['price']); ?></span>
          </div>

          <?php if ($hasDescription): ?>
          <div class="block-card">
            <h5 class="mb-2">Description</h5>
            <div><?php echo nl2br(h($dbDescription)); ?></div>
          </div>
          <?php endif; ?>

          <?php if (count($colorOptions) > 0 || count($storageOptions) > 0): ?>
          <div class="block-card mt-3">
            <h5 class="mb-2">Choose variant</h5>
            
            <?php if (count($colorOptions) > 0): ?>
              <label class="d-block font-weight-bold small mb-1" for="pickColor">Color</label>
              <select id="pickColor" class="feature-pick mb-3">
                <option value="">Select color</option>
                <?php foreach ($colorOptions as $copt): ?>
                  <option value="<?php echo h($copt); ?>"><?php echo h($copt); ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <?php if (count($storageOptions) > 0): ?>
              <label class="d-block font-weight-bold small mb-1" for="pickStorage">Storage (RAM / ROM)</label>
              <select id="pickStorage" class="feature-pick">
                <option value="">Select storage</option>
                <?php foreach ($storageOptions as $sopt): ?>
                  <option value="<?php echo h($sopt); ?>"><?php echo h($sopt); ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="mt-3 d-flex flex-wrap" style="gap:10px;">
            <a href="javascript:void(0)" class="btn btn-dark px-4 py-2 js-rtel-detail-cart" data-product-id="<?php echo h($product['product_id']); ?>">Add to Cart</a>
            <a href="javascript:void(0)" class="btn btn-outline-dark px-4 py-2 js-rtel-detail-wishlist" data-product-id="<?php echo h($product['product_id']); ?>">Add to Wishlist</a>
          </div>
          <?php if ($isPhoneCategory): ?>
          <div class="mt-3">
            <a href="javascript:void(0)" class="compare-ai-btn" id="openCompareAiModalBtn">
              ✨ Compare With AI
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isPhoneCategory && count($specRows) > 0 && !$hideSpecsForAccessoryCategory): ?>
      <div class="row mt-3">
        <div class="col-12">
          <div class="block-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <button type="button" class="spec-toggle-btn" id="specToggleBtn" aria-expanded="false" aria-controls="specTablePanel">
                <span class="spec-toggle-arrow">▶</span>
                <span>Specifications</span>
              </button>
              <small class="text-muted"><?php echo (int)count($specRows); ?> details</small>
            </div>
            <div class="spec-table-wrap" id="specTablePanel" style="display:none;">
              <table class="table spec-table">
                <thead>
                  <tr>
                    <th>Feature</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($specRows as $sr): ?>
                    <tr>
                      <th><?php echo h($sr['name']); ?></th>
                      <td><?php echo h($sr['value']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<div class="modal fade zoom-modal" id="productImageZoomModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="zoom-toolbar">
        <strong class="text-white">Image Preview</strong>
        <div class="zoom-actions">
          <button type="button" class="btn btn-sm btn-light" id="zoomOutBtn">-</button>
          <button type="button" class="btn btn-sm btn-light" id="zoomResetBtn">Reset</button>
          <button type="button" class="btn btn-sm btn-light" id="zoomInBtn">+</button>
          <button type="button" class="btn btn-sm btn-outline-light" data-dismiss="modal">Close</button>
        </div>
      </div>
      <div class="zoom-stage" id="zoomStage">
        <img id="zoomPreviewImage" class="zoom-image" src="" alt="Zoom preview">
      </div>
    </div>
  </div>
</div>

<div class="modal fade ai-modal" id="compareAiModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="ai-modal-header">
        <h5 class="ai-modal-title">AI Product Comparison</h5>
        <p class="ai-modal-subtitle">Compare this product with another model in seconds.</p>
      </div>
      <div class="modal-body">
        <form id="compareAiForm">
          <div class="ai-input-row">
            <div>
              <label class="ai-field-label" for="compareProductOne">Selected Product</label>
              <input type="text" id="compareProductOne" class="ai-input" value="<?php echo h($productName); ?>" readonly>
            </div>
            <div>
              <label class="ai-field-label" for="compareProductTwo">Enter Product to Compare</label>
              <input type="text" id="compareProductTwo" class="ai-input" placeholder="e.g. iPhone 15 Pro, Samsung Galaxy S24+" maxlength="150" required>
              <div class="ai-inline-error" id="compareProductTwoError">Unavailable Phone</div>
            </div>
          </div>

          <div class="ai-modal-actions">
            <button type="button" class="ai-cancel-btn" data-dismiss="modal">Cancel</button>
            <button type="submit" class="ai-go-btn">Compare Now</button>
          </div>
        </form>
        <div class="ai-compare-result" id="aiCompareResultWrap">
          <div class="table-responsive">
            <table class="table table-bordered table-sm ai-compare-table mb-2">
              <thead>
                <tr>
                  <th style="width: 34%;">Specification</th>
                  <th id="aiPrimaryHead">This Product</th>
                  <th id="aiComparedHead">Compared Product</th>
                </tr>
              </thead>
              <tbody id="aiCompareTableBody"></tbody>
            </table>
          </div>
          <div class="ai-compare-status" id="aiCompareStatus"></div>
          <div class="ai-compare-availability" id="aiCompareAvailability"></div>
          <div class="ai-compare-verdict" id="aiCompareVerdict"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($isPhoneCategory && count($webVideoResults) > 0): ?>
<section class="ftco-section pt-0">
  <div class="container">
    <h4 class="mb-3">AI Related Videos</h4>
    <div class="owl-carousel video-carousel">
      <?php foreach ($webVideoResults as $v): ?>
        <div class="item">
          <div class="video-card">
            <?php
              $videoId = trim((string)($v['id'] ?? ''));
              $videoTitle = trim((string)($v['title'] ?? 'Related video'));
              $videoQuery = trim((string)($v['query'] ?? $videoTitle));
              $embedUrl = $videoId !== ''
                ? ('https://www.youtube.com/embed/' . rawurlencode($videoId))
                : ('https://www.youtube.com/embed?listType=search&list=' . urlencode($videoQuery));
              $watchUrl = $videoId !== ''
                ? ('https://www.youtube.com/watch?v=' . rawurlencode($videoId))
                : ('https://www.youtube.com/results?search_query=' . urlencode($videoQuery));
            ?>
            <iframe loading="lazy" src="<?php echo h($embedUrl); ?>" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            <div class="meta">
              <a class="video-link" href="<?php echo h($watchUrl); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo h($videoTitle); ?>
              </a>
              <small class="text-muted">(<?php echo h((string)($v['source'] ?? 'web')); ?>)</small>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($isPhoneCategory && count($adminBundles) > 0): ?>
<?php $useBundleCarousel = count($adminBundles) >= 2; ?>
<section class="ftco-section pt-0">
  <div class="container">
    <div class="bundle-shell">
      <div class="bundle-head">
        <div>
          <h5 class="mb-1">Bundle Deals</h5>
          <small class="text-muted">Choose bundle variants and add one fixed-price bundle to cart.</small>
        </div>
      </div>
      <div class="mt-3 <?php echo $useBundleCarousel ? 'owl-carousel bundle-carousel' : ''; ?>">
        <?php foreach ($adminBundles as $b): ?>
          <?php
            $bundleCover = trim((string)($b['bundle_image'] ?? ''));
            if ($bundleCover === '' && !empty($b['items'][0]['image_1'])) {
                $bundleCover = (string)$b['items'][0]['image_1'];
            }
            if ($bundleCover === '') {
                $bundleCover = 'smartphone.png';
            }
          ?>
          <div class="bundle-card">
            <img src="../images/<?php echo h($bundleCover); ?>" alt="<?php echo h((string)$b['bundle_name']); ?>" class="bundle-card-cover">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong><?php echo h((string)$b['bundle_name']); ?></strong>
              <span class="font-weight-bold text-dark"><?php echo rs((float)$b['bundle_price']); ?></span>
            </div>
            <small class="text-muted d-block mb-2">Model: <?php echo h((string)($b['bundle_model'] ?? '')); ?><?php echo !empty($b['expiry_date']) ? (' | Exp: ' . h((string)$b['expiry_date'])) : ''; ?></small>
            <div class="bundle-price-row">
              <span class="bundle-price-chip">Bundle Price: <?php echo rs((float)$b['bundle_price']); ?></span>
              <span class="bundle-price-chip">Items Total: <?php echo rs((float)($b['list_price'] ?? 0)); ?></span>
              <?php if ((float)($b['saving'] ?? 0) > 0): ?>
                <span class="bundle-price-chip save">You Save: <?php echo rs((float)$b['saving']); ?></span>
              <?php endif; ?>
            </div>
            <small class="text-muted d-block mb-2"><?php echo (int)count((array)$b['items']); ?> item(s) in this bundle.</small>
            <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#bundleModal-<?php echo (int)$b['bundle_id']; ?>">
              Customize Bundle
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($isPhoneCategory && count($adminBundles) > 0): ?>
  <?php foreach ($adminBundles as $b): ?>
    <div class="modal fade" id="bundleModal-<?php echo (int)$b['bundle_id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?php echo h((string)$b['bundle_name']); ?></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="bundle-price-row mb-3">
              <span class="bundle-price-chip">Bundle Price: <?php echo rs((float)$b['bundle_price']); ?></span>
              <span class="bundle-price-chip">Items Total: <?php echo rs((float)($b['list_price'] ?? 0)); ?></span>
              <?php if ((float)($b['saving'] ?? 0) > 0): ?>
                <span class="bundle-price-chip save">You Save: <?php echo rs((float)$b['saving']); ?></span>
              <?php endif; ?>
            </div>
            <?php foreach ((array)$b['items'] as $it): ?>
              <div class="bundle-item-row js-bundle-item" data-bundle-id="<?php echo (int)$b['bundle_id']; ?>" data-product-id="<?php echo h((string)$it['product_id']); ?>">
                <div class="d-flex align-items-start" style="gap:10px;">
                  <img src="../images/<?php echo h((string)($it['image_1'] ?? 'smartphone.png')); ?>" alt="<?php echo h((string)$it['name']); ?>" class="bundle-item-thumb">
                  <div class="w-100">
                    <div class="font-weight-bold"><?php echo h((string)$it['name']); ?></div>
                    <?php if (!empty($it['modal'])): ?><small class="text-muted d-block"><?php echo h((string)$it['modal']); ?></small><?php endif; ?>
                    <small class="text-muted d-block mb-2"><?php echo rs((float)($it['price'] ?? 0)); ?></small>

                    <?php if (!empty($it['color_variants'])): ?>
                      <label class="small font-weight-bold mb-1">Color</label>
                      <select class="form-control form-control-sm mb-2 js-bundle-color bundle-variant-select"
                              data-bundle-id="<?php echo (int)$b['bundle_id']; ?>"
                              data-product-id="<?php echo h((string)$it['product_id']); ?>">
                        <option value="">Select color</option>
                        <?php foreach ((array)$it['color_variants'] as $v): ?>
                          <option value="<?php echo h((string)$v); ?>"><?php echo h((string)$v); ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php endif; ?>

                    <?php if (!empty($it['storage_variants'])): ?>
                      <label class="small font-weight-bold mb-1">Storage (RAM / ROM)</label>
                      <select class="form-control form-control-sm mb-2 js-bundle-storage bundle-variant-select"
                              data-bundle-id="<?php echo (int)$b['bundle_id']; ?>"
                              data-product-id="<?php echo h((string)$it['product_id']); ?>">
                        <option value="">Select storage</option>
                        <?php foreach ((array)$it['storage_variants'] as $v): ?>
                          <option value="<?php echo h((string)$v); ?>"><?php echo h((string)$v); ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php endif; ?>

                    <?php if (empty($it['color_variants']) && empty($it['storage_variants']) && !empty($it['variants'])): ?>
                      <label class="small font-weight-bold mb-1">Variant</label>
                      <select class="form-control form-control-sm js-bundle-variant bundle-variant-select"
                              data-bundle-id="<?php echo (int)$b['bundle_id']; ?>"
                              data-product-id="<?php echo h((string)$it['product_id']); ?>">
                        <option value="">Select variant (optional)</option>
                        <?php foreach ((array)$it['variants'] as $v): ?>
                          <option value="<?php echo h((string)$v); ?>"><?php echo h((string)$v); ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-1"><em>*If you don't select any varients, we will make it randomly</em></small>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
            <a href="javascript:void(0)" class="btn btn-dark js-add-admin-bundle" data-bundle-id="<?php echo (int)$b['bundle_id']; ?>">Add Bundle to Cart</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($bundleDebugEnabled): ?>
<section class="ftco-section pt-0">
  <div class="container">
    <div class="block-card">
      <h5 class="mb-2">Bundle Debug</h5>
      <small class="text-muted d-block mb-2">Product model: <?php echo h($modalNeedle); ?> | Category gate: <?php echo $isPhoneCategory ? 'ON' : 'OFF'; ?></small>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Bundle</th>
              <th>Bundle Model</th>
              <th>Status OK</th>
              <th>Expiry OK</th>
              <th>Model OK</th>
              <th>Included</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($bundleDebugRows) === 0): ?>
              <tr><td colspan="7" class="text-center">No bundles found in DB.</td></tr>
            <?php else: ?>
              <?php foreach ($bundleDebugRows as $d): ?>
                <tr>
                  <td><?php echo (int)$d['id']; ?></td>
                  <td><?php echo h((string)$d['name']); ?></td>
                  <td><?php echo h((string)$d['bundle_model']); ?></td>
                  <td><?php echo $d['status_ok'] ? 'YES' : 'NO'; ?></td>
                  <td><?php echo $d['expiry_ok'] ? 'YES' : 'NO'; ?><?php echo $d['expiry'] !== '' ? (' (' . h($d['expiry']) . ')') : ''; ?></td>
                  <td><?php echo $d['model_ok'] ? 'YES' : 'NO'; ?></td>
                  <td><?php echo $d['is_included'] ? 'YES' : 'NO'; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($isPhoneCategory && count($accessories) > 0): ?>
<section class="ftco-section pt-0">
  <div class="container">
    <h4 class="mb-3">AI Suggested Accessories</h4>
    <p class="text-muted mb-3">AI recommends relevant items for your phone back covers, tempered glass, cables, chargers and more.</p>
    <div class="row" id="aiAccessoriesContainer">
      <?php foreach ($accessories as $a): ?>
        <?php
          $aPrice = (float)($a['price'] ?? 0);
          $aCompare = (float)($a['cprice'] ?? 0);
          $aHasDiscount = $aCompare > 0 && $aCompare > $aPrice;
          $aDiscount = $aHasDiscount ? round((($aCompare - $aPrice) / $aCompare) * 100) : 0;
        ?>
        <div class="col-md-6 col-lg-3 mb-4">
          <div class="product">
            <a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>" class="img-prod">
              <img class="img-fluid" src="../images/<?php echo h($a['image_1']); ?>" alt="<?php echo h($a['name']); ?>">
              <?php if ($aHasDiscount): ?>
                <span class="status"><?php echo (int)$aDiscount; ?>%</span>
                <div class="overlay"></div>
              <?php endif; ?>
            </a>
            <div class="text py-3 pb-4 px-3 text-center">
              <h3><a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>"><?php echo h($a['name']); ?></a></h3>
              <small class="text-muted d-block mb-1"><?php echo !empty($a['modal']) ? h($a['modal']) : ''; ?></small>
              <div class="d-flex">
                <div class="pricing">
                  <p class="price">
                    <?php if ($aHasDiscount): ?>
                      <span class="mr-2 price-dc"><?php echo rs($aCompare); ?></span>
                    <?php endif; ?>
                    <span class="price-sale"><?php echo rs($aPrice); ?></span>
                  </p>
                </div>
              </div>
              <small class="text-muted d-block mb-2">Stock: <?php echo (int)$a['quantity']; ?></small>
              <div class="bottom-area d-flex px-3">
                <div class="m-auto d-flex">
                  <a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>" class="add-to-cart d-flex justify-content-center align-items-center text-center">
                    <span><i class="ion-ios-menu"></i></span>
                  </a>
                  <a href="javascript:void(0)" class="buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart" data-product-id="<?php echo h($a['product_id']); ?>">
                    <span><i class="ion-ios-cart"></i></span>
                  </a>
                  <a href="javascript:void(0)" class="heart d-flex justify-content-center align-items-center js-add-wishlist" data-product-id="<?php echo h($a['product_id']); ?>">
                    <span><i class="ion-ios-heart"></i></span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (count($youMayLove) > 0): ?>
<section class="ftco-section pt-0">
  <div class="container">
    <h4 class="mb-3">You May Love</h4>
    <p class="text-muted mb-3">Recommended products from this category you might like.</p>
    <div class="row">
      <?php foreach ($youMayLove as $a): ?>
        <?php
          $aPrice = (float)($a['price'] ?? 0);
          $aCompare = (float)($a['cprice'] ?? 0);
          $aHasDiscount = $aCompare > 0 && $aCompare > $aPrice;
          $aDiscount = $aHasDiscount ? round((($aCompare - $aPrice) / $aCompare) * 100) : 0;
        ?>
        <div class="col-md-6 col-lg-3 mb-4">
          <div class="product">
            <a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>" class="img-prod">
              <img class="img-fluid" src="../images/<?php echo h($a['image_1']); ?>" alt="<?php echo h($a['name']); ?>">
              <?php if ($aHasDiscount): ?>
                <span class="status"><?php echo (int)$aDiscount; ?>%</span>
                <div class="overlay"></div>
              <?php endif; ?>
            </a>
            <div class="text py-3 pb-4 px-3 text-center">
              <h3><a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>"><?php echo h($a['name']); ?></a></h3>
              <small class="text-muted d-block mb-1"><?php echo !empty($a['modal']) ? h($a['modal']) : ''; ?></small>
              <div class="d-flex">
                <div class="pricing">
                  <p class="price">
                    <?php if ($aHasDiscount): ?>
                      <span class="mr-2 price-dc"><?php echo rs($aCompare); ?></span>
                    <?php endif; ?>
                    <span class="price-sale"><?php echo rs($aPrice); ?></span>
                  </p>
                </div>
              </div>
              <small class="text-muted d-block mb-2">Stock: <?php echo (int)$a['quantity']; ?></small>
              <div class="bottom-area d-flex px-3">
                <div class="m-auto d-flex">
                  <a href="product.php?product_id=<?php echo (int)$a['product_id']; ?>" class="add-to-cart d-flex justify-content-center align-items-center text-center">
                    <span><i class="ion-ios-menu"></i></span>
                  </a>
                  <a href="javascript:void(0)" class="buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart" data-product-id="<?php echo h($a['product_id']); ?>">
                    <span><i class="ion-ios-cart"></i></span>
                  </a>
                  <a href="javascript:void(0)" class="heart d-flex justify-content-center align-items-center js-add-wishlist" data-product-id="<?php echo h($a['product_id']); ?>">
                    <span><i class="ion-ios-heart"></i></span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="ftco-section pt-0">
  <div class="container">
    <h4 class="mb-3">Ratings & Reviews</h4>
    <div class="owl-carousel review-carousel">
      <?php if (count($reviews) === 0): ?>
        <div class="item"><div class="review-card"><strong>No reviews yet.</strong><div class="text-muted mt-2">Be the first to review this product.</div></div></div>
      <?php else: ?>
        <?php foreach ($reviews as $rv): ?>
          <div class="item">
            <div class="review-card">
              <div class="d-flex justify-content-between">
                <strong><?php echo h($rv['customer_name']); ?></strong>
                <small><?php echo h(date('Y-m-d', strtotime((string)$rv['created_at']))); ?></small>
              </div>
              <div class="review-star mt-1">
                <?php for ($s = 1; $s <= 5; $s++) echo $s <= (int)$rv['rating'] ? '★' : '☆'; ?>
              </div>
              <div class="mt-2"><?php echo h($rv['review_text']); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var main = document.getElementById('pdMainImage');
  document.querySelectorAll('.js-pd-thumb').forEach(function (img) {
    img.addEventListener('click', function () {
      var full = img.getAttribute('data-full');
      if (main && full) main.src = full;
    });
  });

  if (window.jQuery && jQuery.fn.owlCarousel) {
    jQuery('.image-carousel').owlCarousel({
      loop: true,
      margin: 12,
      nav: true,
      dots: true,
      autoplay: true,
      autoplayTimeout: 3600,
      responsive: {
        0: { items: 1 },
        768: { items: 2 },
        1200: { items: 3 }
      }
    });

    jQuery('.video-carousel').owlCarousel({
      loop: true,
      margin: 12,
      nav: true,
      dots: true,
      responsive: {
        0: { items: 1 },
        768: { items: 2 },
        1200: { items: 3 }
      }
    });
    jQuery('.review-carousel').owlCarousel({
      loop: false,
      margin: 12,
      nav: true,
      dots: true,
      responsive: { 0: { items: 1 }, 768: { items: 2 }, 1200: { items: 3 } }
    });
    jQuery('.bundle-carousel').owlCarousel({
      loop: <?php echo count($adminBundles) > 2 ? 'true' : 'false'; ?>,
      margin: 12,
      nav: true,
      dots: true,
      responsive: {
        0: { items: 1 },
        768: { items: 1 },
        1200: { items: 2 }
      }
    });
  }

  function buildSelectedFeature() {
    var c = document.getElementById('pickColor');
    var s = document.getElementById('pickStorage');
    var parts = [];
    if (c && c.value) parts.push('Color: ' + c.value);
    if (s && s.value) parts.push('Storage: ' + s.value);
    var out = parts.join(' | ');
    return out.length > 255 ? out.substring(0, 255) : out;
  }

  var zoomModalEl = document.getElementById('productImageZoomModal');
  var zoomImg = document.getElementById('zoomPreviewImage');
  var zoomStage = document.getElementById('zoomStage');
  var zoomScale = 1;
  var zoomX = 0;
  var zoomY = 0;
  var dragStartX = 0;
  var dragStartY = 0;
  var dragging = false;

  function applyZoomTransform() {
    if (!zoomImg) return;
    zoomImg.style.transform = 'translate(' + zoomX + 'px,' + zoomY + 'px) scale(' + zoomScale + ')';
  }

  function resetZoomState() {
    zoomScale = 1;
    zoomX = 0;
    zoomY = 0;
    applyZoomTransform();
  }

  function setZoomImage(src) {
    if (!zoomImg) return;
    zoomImg.src = src || '';
    resetZoomState();
  }

  function openZoomModal(src) {
    if (!zoomModalEl || !src) return;
    setZoomImage(src);
    if (window.jQuery) {
      jQuery(zoomModalEl).modal('show');
    } else {
      zoomModalEl.style.display = 'block';
    }
  }

  if (main) {
    main.addEventListener('click', function () {
      openZoomModal(main.src);
    });
  }

  document.querySelectorAll('.js-pd-thumb').forEach(function (img) {
    img.addEventListener('dblclick', function () {
      var full = img.getAttribute('data-full');
      openZoomModal(full || img.src);
    });
  });

  document.querySelectorAll('.js-zoomable-image').forEach(function (img) {
    img.addEventListener('click', function () {
      var src = img.getAttribute('data-zoom-src') || img.getAttribute('src') || '';
      openZoomModal(src);
    });
  });

  var zoomInBtn = document.getElementById('zoomInBtn');
  var zoomOutBtn = document.getElementById('zoomOutBtn');
  var zoomResetBtn = document.getElementById('zoomResetBtn');
  if (zoomInBtn) zoomInBtn.addEventListener('click', function () {
    zoomScale = Math.min(4, zoomScale + 0.2);
    applyZoomTransform();
  });
  if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () {
    zoomScale = Math.max(1, zoomScale - 0.2);
    if (zoomScale === 1) { zoomX = 0; zoomY = 0; }
    applyZoomTransform();
  });
  if (zoomResetBtn) zoomResetBtn.addEventListener('click', resetZoomState);

  if (zoomStage) {
    zoomStage.addEventListener('wheel', function (e) {
      e.preventDefault();
      zoomScale = e.deltaY < 0 ? Math.min(4, zoomScale + 0.12) : Math.max(1, zoomScale - 0.12);
      if (zoomScale === 1) { zoomX = 0; zoomY = 0; }
      applyZoomTransform();
    }, { passive: false });
  }

  if (zoomImg) {
    zoomImg.addEventListener('mousedown', function (e) {
      if (zoomScale <= 1) return;
      dragging = true;
      zoomImg.classList.add('dragging');
      dragStartX = e.clientX - zoomX;
      dragStartY = e.clientY - zoomY;
    });
  }
  document.addEventListener('mousemove', function (e) {
    if (!dragging) return;
    zoomX = e.clientX - dragStartX;
    zoomY = e.clientY - dragStartY;
    applyZoomTransform();
  });
  document.addEventListener('mouseup', function () {
    dragging = false;
    if (zoomImg) zoomImg.classList.remove('dragging');
  });

  if (window.jQuery && zoomModalEl) {
    jQuery(zoomModalEl).on('hidden.bs.modal', function () {
      resetZoomState();
    });
  }

  var compareBtn = document.getElementById('openCompareAiModalBtn');
  var compareModalEl = document.getElementById('compareAiModal');
  var compareForm = document.getElementById('compareAiForm');
  var compareProductTwo = document.getElementById('compareProductTwo');
  var compareProductOneValue = <?php echo json_encode((string)$productName); ?>;
  var aiCompareResultWrap = document.getElementById('aiCompareResultWrap');
  var aiCompareTableBody = document.getElementById('aiCompareTableBody');
  var aiCompareStatus = document.getElementById('aiCompareStatus');
  var aiCompareAvailability = document.getElementById('aiCompareAvailability');
  var aiCompareVerdict = document.getElementById('aiCompareVerdict');
  var compareProductTwoError = document.getElementById('compareProductTwoError');
  var aiPrimaryHead = document.getElementById('aiPrimaryHead');
  var aiComparedHead = document.getElementById('aiComparedHead');

  function esc(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  var compareSpecRows = [
    { key: 'announced', label: 'Announced' },
    { key: 'display_type', label: 'Display Type' },
    { key: 'display_size', label: 'Display Size' },
    { key: 'os', label: 'OS' },
    { key: 'chipset', label: 'Chipset' },
    { key: 'cpu', label: 'CPU' },
    { key: 'gpu', label: 'GPU' },
    { key: 'memory', label: 'Memory' },
    { key: 'main_camera', label: 'Main Camera' },
    { key: 'selfie_camera', label: 'Selfie Camera' },
    { key: 'battery', label: 'Battery' },
    { key: 'charging', label: 'Charging' }
  ];

  function clearCompareCards() {
    if (aiCompareTableBody) aiCompareTableBody.innerHTML = '';
    if (aiCompareAvailability) {
      aiCompareAvailability.innerHTML = '';
      aiCompareAvailability.style.display = 'none';
    }
    if (aiCompareVerdict) {
      aiCompareVerdict.innerHTML = '';
      aiCompareVerdict.style.display = 'none';
    }
  }

  function setCompareInputError(msg) {
    if (!compareProductTwoError) return;
    var text = String(msg || '').trim();
    compareProductTwoError.textContent = text || 'Unavailable Phone';
    compareProductTwoError.style.display = text ? 'block' : 'none';
  }

  function renderCompareCards(items) {
    clearCompareCards();
    if (!Array.isArray(items) || items.length < 2) {
      if (aiCompareStatus) aiCompareStatus.textContent = 'Need two products to compare.';
      if (aiCompareResultWrap) aiCompareResultWrap.style.display = 'block';
      return;
    }
    var left = items[0] || {};
    var right = items[1] || {};
    var leftName = String(left.resolved_name || left.name || compareProductOneValue || 'This Product').trim();
    var rightName = String(right.resolved_name || right.name || (compareProductTwo ? compareProductTwo.value : '') || 'Compared Product').trim();
    var rightHasSpecs = !!(right && right.specs && typeof right.specs === 'object' && Object.keys(right.specs).length > 0);
    var rightInvalid = !right || right.invalid_product || (!String(right.gsmarena_url || '').trim() && !rightHasSpecs);
    var leftSpecs = (left && left.specs && typeof left.specs === 'object') ? left.specs : {};
    var rightSpecs = (right && right.specs && typeof right.specs === 'object') ? right.specs : {};

    if (aiPrimaryHead) aiPrimaryHead.textContent = leftName || 'This Product';
    if (aiComparedHead) aiComparedHead.textContent = rightName || 'Compared Product';

    var rowsHtml = '';
    compareSpecRows.forEach(function (row) {
      rowsHtml += '<tr>'
        + '<th>' + esc(row.label) + '</th>'
        + '<td>' + esc(leftSpecs[row.key] || 'N/A') + '</td>'
        + '<td>' + esc(rightInvalid ? 'Incorrect phone name' : (rightSpecs[row.key] || 'N/A')) + '</td>'
        + '</tr>';
    });
    if (aiCompareTableBody) aiCompareTableBody.innerHTML = rowsHtml;

    if (aiCompareStatus) aiCompareStatus.textContent = '';
    if (rightInvalid && aiCompareStatus) aiCompareStatus.textContent = '';
    if (aiCompareResultWrap) aiCompareResultWrap.style.display = 'block';
  }

  function scoreFromSpecText(text) {
    var t = String(text || '').toLowerCase();
    var nums = t.match(/\d+(\.\d+)?/g) || [];
    var max = 0;
    nums.forEach(function (n) {
      var v = parseFloat(n);
      if (!isNaN(v) && v > max) max = v;
    });
    return max;
  }

  function renderCompareVerdict(items) {
    if (!aiCompareVerdict) return;
    aiCompareVerdict.innerHTML = '';
    aiCompareVerdict.style.display = 'none';
    if (aiCompareStatus) aiCompareStatus.textContent = '';
    if (!Array.isArray(items) || items.length < 2) {
      var emptyHtml = '<strong>AI Quick Verdict</strong><div class="mt-2 small text-muted">Not enough data to build verdict.</div>';
      aiCompareVerdict.innerHTML = emptyHtml;
      aiCompareVerdict.style.display = 'block';
      return;
    }
    var left = items[0] || {};
    var right = items[1] || {};
    var leftName = String(left.resolved_name || left.name || compareProductOneValue || 'This Product').trim();
    var rightName = String(right.resolved_name || right.name || (compareProductTwo ? compareProductTwo.value : '') || 'Compared Product').trim();
    if (!left.specs || !right.specs) {
      var limitedHtml = '<strong>AI Quick Verdict</strong><div class="mt-2 small text-muted">Specs are limited for this comparison.</div>';
      aiCompareVerdict.innerHTML = limitedHtml;
      aiCompareVerdict.style.display = 'block';
      return;
    }

    var l = left.specs || {};
    var r = right.specs || {};
    var lCam = scoreFromSpecText(l.main_camera);
    var rCam = scoreFromSpecText(r.main_camera);
    var lBat = scoreFromSpecText(l.battery);
    var rBat = scoreFromSpecText(r.battery);
    var lMem = scoreFromSpecText(l.memory);
    var rMem = scoreFromSpecText(r.memory);
    var lChip = scoreFromSpecText(l.platform);
    var rChip = scoreFromSpecText(r.platform);
    var lDisplay = scoreFromSpecText(l.display);
    var rDisplay = scoreFromSpecText(r.display);

    function pickWinner(a, b, leftName, rightName) {
      if (a > b) return leftName;
      if (b > a) return rightName;
      return 'Tie';
    }
    var cameraWinner = (lCam === 0 && rCam === 0) ? 'Not enough data' : pickWinner(lCam, rCam, leftName, rightName);
    var batteryWinner = (lBat === 0 && rBat === 0) ? 'Not enough data' : pickWinner(lBat, rBat, leftName, rightName);
    var lValue = (lMem * 0.7) + (lBat * 0.3);
    var rValue = (rMem * 0.7) + (rBat * 0.3);
    var valueWinner = (lValue === 0 && rValue === 0) ? 'Not enough data' : pickWinner(lValue, rValue, leftName, rightName);
    var lGaming = (lChip * 0.5) + (lMem * 0.35) + (lBat * 0.15);
    var rGaming = (rChip * 0.5) + (rMem * 0.35) + (rBat * 0.15);
    var gamingWinner = (lGaming === 0 && rGaming === 0) ? 'Not enough data' : pickWinner(lGaming, rGaming, leftName, rightName);
    var lDaily = (lBat * 0.45) + (lDisplay * 0.25) + (lMem * 0.2) + (lCam * 0.1);
    var rDaily = (rBat * 0.45) + (rDisplay * 0.25) + (rMem * 0.2) + (rCam * 0.1);
    var dailyWinner = (lDaily === 0 && rDaily === 0) ? 'Not enough data' : pickWinner(lDaily, rDaily, leftName, rightName);

    var verdictHtml =
      '<strong>AI Quick Verdict</strong>' +
      '<div class="ai-verdict-grid">' +
        '<div class="ai-verdict-item"><strong>Best Camera:</strong><br>' + esc(cameraWinner) + '</div>' +
        '<div class="ai-verdict-item"><strong>Best Battery:</strong><br>' + esc(batteryWinner) + '</div>' +
        '<div class="ai-verdict-item"><strong>Best Value:</strong><br>' + esc(valueWinner) + '</div>' +
        '<div class="ai-verdict-item"><strong>Best for Gaming:</strong><br>' + esc(gamingWinner) + '</div>' +
        '<div class="ai-verdict-item"><strong>Best for Daily Use:</strong><br>' + esc(dailyWinner) + '</div>' +
      '</div>';
    aiCompareVerdict.innerHTML = verdictHtml;
    aiCompareVerdict.style.display = 'block';
  }

  function renderAvailability(availabilityRows) {
    if (!aiCompareAvailability) return;
    aiCompareAvailability.innerHTML = '';
    aiCompareAvailability.style.display = 'none';
    if (!Array.isArray(availabilityRows) || availabilityRows.length < 2) return;
    var compared = availabilityRows[1] || null; // only second (user-entered) phone
    if (!compared) return;
    var available = !!compared.available_in_rtel;
    var productUrl = available && compared.product_url ? String(compared.product_url) : '';
    if (!productUrl) return; // if not in DB, show nothing
    var requested = esc(String(compared.requested_name || '-'));
    var matchedName = esc(String(compared.name || ''));
    aiCompareAvailability.innerHTML = '<strong>Availability in R-TEL</strong>'
      + '<div class="row-item"><div><strong>' + requested + '</strong><small class="d-block text-success">Matched in R-TEL: ' + matchedName + '</small></div><div><a class="btn btn-sm btn-outline-primary" href="' + esc(productUrl) + '">View</a></div></div>';
    aiCompareAvailability.style.display = 'block';
  }

  if (compareBtn && compareModalEl) {
    compareBtn.addEventListener('click', function () {
      if (window.jQuery) {
        jQuery(compareModalEl).modal('show');
      } else {
        compareModalEl.style.display = 'block';
      }
    });
  }

  if (window.jQuery && compareModalEl) {
    jQuery(compareModalEl).on('shown.bs.modal', function () {
      if (compareProductTwo) compareProductTwo.focus();
    });
    jQuery(compareModalEl).on('hidden.bs.modal', function () {
      clearCompareCards();
      if (aiCompareResultWrap) aiCompareResultWrap.style.display = 'none';
      if (aiCompareStatus) aiCompareStatus.textContent = '';
    });
  }

  if (compareForm) {
    compareForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      setCompareInputError('');
      var p2 = compareProductTwo ? String(compareProductTwo.value || '').trim() : '';
      if (!p2) {
        notify('Please enter the second product name.', false);
        return;
      }
      var submitBtn = compareForm.querySelector('.ai-go-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Comparing...';
      }
      if (aiCompareStatus) aiCompareStatus.textContent = 'Please wait...';
      if (aiCompareResultWrap) aiCompareResultWrap.style.display = 'block';
      clearCompareCards();
      try {
        var response = await fetch('ai/compare_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_names: [compareProductOneValue, p2] })
        });
        var data = await response.json();
        logAiReplyMeta('R-TEL Compare', data);
        var items = (data && Array.isArray(data.web_research)) ? data.web_research : [];
        var right = items.length > 1 ? items[1] : null;
        var rightHasSpecs = !!(right && right.specs && typeof right.specs === 'object' && Object.keys(right.specs).length > 0);
        var rightInvalid = !right || right.invalid_product || (!String(right.gsmarena_url || '').trim() && !rightHasSpecs);
        if (rightInvalid) {
          setCompareInputError('Unavailable Phone');
          if (aiCompareStatus) aiCompareStatus.textContent = '';
          if (aiCompareResultWrap) aiCompareResultWrap.style.display = 'none';
          renderAvailability([]);
          return;
        }
        if (aiCompareStatus) aiCompareStatus.textContent = '';
        renderCompareCards(items);
        renderCompareVerdict(items);
        renderAvailability(data && Array.isArray(data.availability) ? data.availability : []);
      } catch (err) {
        if (aiCompareStatus) aiCompareStatus.textContent = 'Unable to compare now. Please try again.';
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Compare Now';
        }
      }
    });
  }

  function variantSelectionOk() {
    var c = document.getElementById('pickColor');
    var s = document.getElementById('pickStorage');
    if (c && c.options.length > 1 && !String(c.value || '').trim()) {
      notify('Please select a color.', false);
      return false;
    }
    if (s && s.options.length > 1 && !String(s.value || '').trim()) {
      notify('Please select a storage option.', false);
      return false;
    }
    return true;
  }

  function actionUrl(action, productId, selectedFeatureOverride) {
    var url = 'product_action.php?action=' + encodeURIComponent(action) + '&product_id=' + encodeURIComponent(productId) + '&ajax=1';
    var selected = (typeof selectedFeatureOverride === 'string') ? selectedFeatureOverride : buildSelectedFeature();
    if (selected) url += '&selected_feature=' + encodeURIComponent(selected);
    return url;
  }

  function notify(msg, ok) {
    var text = String(msg || '');
    if (typeof Swal !== 'undefined') {
      var failIcon = /^please\b/i.test(text) ? 'warning' : 'error';
      Swal.fire({
        icon: ok ? 'success' : failIcon,
        title: text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: ok ? 2200 : 4000,
        timerProgressBar: true
      });
    } else {
      alert(text);
    }
  }

  function logAiReplyMeta(tag, data) {
    try {
      var src = (data && data.reply_source) ? String(data.reply_source) : 'fallback';
      var prov = (data && data.ai_provider) ? String(data.ai_provider) : '';
      console.info('[' + String(tag || 'R-TEL AI') + '] reply_source:', src, prov ? ('provider: ' + prov) : '');
    } catch (_) {}
  }

  document.querySelectorAll('.js-rtel-detail-cart').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!variantSelectionOk()) return;
      var pid = btn.getAttribute('data-product-id');
      fetch(actionUrl('add_cart', pid)).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.redirect) { window.location.href = d.redirect; return; }
        notify((d && d.message) ? d.message : 'Added to cart', !!(d && d.success));
      }).catch(function () { notify('Unable to add to cart.', false); });
    });
  });

  document.querySelectorAll('.js-rtel-detail-wishlist').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!variantSelectionOk()) return;
      var pid = btn.getAttribute('data-product-id');
      fetch(actionUrl('add_wishlist', pid)).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.redirect) { window.location.href = d.redirect; return; }
        notify((d && d.message) ? d.message : 'Added to wishlist', !!(d && d.success));
      }).catch(function () { notify('Unable to add to wishlist.', false); });
    });
  });

  document.querySelectorAll('.js-add-admin-bundle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var bundleId = String(btn.getAttribute('data-bundle-id') || '').trim();
      if (!bundleId) {
        notify('Bundle is not available.', false);
        return;
      }
      var variants = {};
      document.querySelectorAll('.js-bundle-item[data-bundle-id="' + bundleId + '"]').forEach(function (row) {
        var pid = String(row.getAttribute('data-product-id') || '').trim();
        if (!pid) return;
        var colorSel = row.querySelector('.js-bundle-color[data-product-id="' + pid + '"]');
        var storageSel = row.querySelector('.js-bundle-storage[data-product-id="' + pid + '"]');
        var genericSel = row.querySelector('.js-bundle-variant[data-product-id="' + pid + '"]');
        var bits = [];
        var cVal = colorSel ? String(colorSel.value || '').trim() : '';
        var sVal = storageSel ? String(storageSel.value || '').trim() : '';
        var gVal = genericSel ? String(genericSel.value || '').trim() : '';
        if (cVal) bits.push('Color: ' + cVal);
        if (sVal) bits.push('Storage: ' + sVal);
        if (bits.length === 0 && gVal) bits.push(gVal);
        if (bits.length > 0) variants[pid] = bits.join(' | ');
      });
      var url = 'product_action.php?action=add_bundle_cart&ajax=1&bundle_id=' + encodeURIComponent(bundleId)
        + '&bundle_variants=' + encodeURIComponent(JSON.stringify(variants));
      fetch(url).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.redirect) { window.location.href = d.redirect; return; }
        notify((d && d.message) ? d.message : 'Bundle added to cart.', !!(d && d.success));
      }).catch(function () { notify('Unable to add bundle.', false); });
    });
  });

  var specToggleBtn = document.getElementById('specToggleBtn');
  var specTablePanel = document.getElementById('specTablePanel');
  if (specToggleBtn && specTablePanel) {
    specToggleBtn.addEventListener('click', function () {
      var expanded = specToggleBtn.getAttribute('aria-expanded') === 'true';
      specToggleBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      specTablePanel.style.display = expanded ? 'none' : 'block';
    });
  }
});
</script>

<?php require 'footer.php'; ?>
