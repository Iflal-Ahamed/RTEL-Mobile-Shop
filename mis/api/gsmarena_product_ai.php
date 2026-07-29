<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
rtel_require_admin_auth();
require_once __DIR__ . '/../connection.php';

function respond($arr)
{
    echo json_encode($arr);
    exit;
}

function clean_text($text)
{
    $t = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', (string)$t);
    return trim((string)$t);
}

function http_get($url, $timeout = 12)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, max(4, (int)$timeout));
    curl_setopt($ch, CURLOPT_USERAGENT, 'R-TEL-GSMArena-Admin/1.0');
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $body === '' || $code >= 400) {
        return '';
    }
    return $body;
}

function detail_url($query)
{
    $q = trim((string)$query);
    if ($q === '') return '';
    $searchUrl = 'https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=' . urlencode($q);
    $html = http_get($searchUrl, 12);
    if ($html === '') return '';
    if (!preg_match('/href="([^"]+-\d+\.php)"/i', $html, $m)) {
        return '';
    }
    return 'https://www.gsmarena.com/' . ltrim((string)$m[1], '/');
}

function parse_pairs($html)
{
    $pairs = [];
    if (!preg_match_all('/<td[^>]*class="ttl"[^>]*>(.*?)<\/td>\s*<td[^>]*class="nfo"[^>]*>(.*?)<\/td>/is', (string)$html, $rows, PREG_SET_ORDER)) {
        return $pairs;
    }
    foreach ($rows as $r) {
        $k = mb_strtolower(clean_text($r[1] ?? ''));
        $v = clean_text($r[2] ?? '');
        if ($k === '' || $v === '') continue;
        if (!isset($pairs[$k])) $pairs[$k] = [];
        $pairs[$k][] = $v;
    }
    return $pairs;
}

function pick(array $pairs, array $keys, $pattern = '')
{
    foreach ($keys as $key) {
        $k = mb_strtolower(trim((string)$key));
        if (!isset($pairs[$k])) continue;
        foreach ((array)$pairs[$k] as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            if ($pattern !== '' && !preg_match($pattern, $v)) continue;
            return $v;
        }
    }
    return '';
}

function parse_specs($html)
{
    $pairs = parse_pairs($html);
    $map = [
        'Announced' => pick($pairs, ['announced']),
        'Display Type' => pick($pairs, ['type'], '/oled|amoled|lcd|retina|display|hz|resolution/i'),
        'Display Size' => pick($pairs, ['size'], '/inch|inches|cm2|ppi|resolution/i'),
        'OS' => pick($pairs, ['os']),
        'Chipset' => pick($pairs, ['chipset']),
        'CPU' => pick($pairs, ['cpu']),
        'GPU' => pick($pairs, ['gpu']),
        'Memory' => pick($pairs, ['internal', 'card slot']),
        'Main Camera' => pick($pairs, ['triple', 'dual', 'quad', 'penta', 'single'], '/mp|camera|wide|ultrawide|telephoto|video/i'),
        'Selfie Camera' => pick($pairs, ['single', 'dual'], '/mp|camera|video|hdr/i'),
        'Battery' => pick($pairs, ['type'], '/mah|li-po|li-ion|battery/i'),
        'Charging' => pick($pairs, ['charging']),
    ];
    $out = [];
    foreach ($map as $name => $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $out[] = ['name' => $name, 'value' => $value];
        }
    }
    return $out;
}

$name = trim((string)($_GET['name'] ?? ''));
$model = trim((string)($_GET['model'] ?? ''));
$query = trim($name . ' ' . $model);
if ($query === '') {
    respond(['status' => 'error', 'message' => 'Product name is required.']);
}

$url = detail_url($query);
if ($url === '') {
    respond(['status' => 'error', 'message' => 'No GSMArena result found for this product.']);
}
$html = http_get($url, 14);
if ($html === '') {
    respond(['status' => 'error', 'message' => 'Unable to load GSMArena product page.']);
}

$description = '';
if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"\s*\/?>/i', $html, $mDesc)) {
    $description = clean_text($mDesc[1] ?? '');
}
$specs = parse_specs($html);

respond([
    'status' => 'success',
    'source' => 'GSMArena',
    'query' => $query,
    'url' => $url,
    'description' => $description,
    'specs' => $specs,
]);
