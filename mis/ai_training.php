<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';
require_once __DIR__ . '/../web/ai/chat_ai_provider.php';
rtel_require_admin_auth();
$conn->set_charset('utf8mb4');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rtel_normalize_query_list(array $queries, array $fallback, $limit = 4)
{
    $out = [];
    $seen = [];
    foreach ($queries as $q) {
        $q = trim((string)$q);
        if ($q === '') {
            continue;
        }
        $q = preg_replace('/\s+/', ' ', $q);
        $key = strtolower($q);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $q;
        if (count($out) >= $limit) {
            break;
        }
    }
    foreach ($fallback as $q) {
        if (count($out) >= $limit) {
            break;
        }
        $q = trim((string)$q);
        if ($q === '') {
            continue;
        }
        $key = strtolower($q);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $q;
        }
    }
    return array_slice($out, 0, $limit);
}

function rtel_preview_media_queries($productName, $productModel, $description, $extraPrompt, $focusKeywords)
{
    $seed = trim((string)$productName);
    if ($seed === '') {
        $seed = trim((string)$productModel);
    }
    if ($seed === '') {
        $seed = 'Smartphone';
    }
    $fallbackImage = [
        trim($seed . ' camera sample photos'),
        trim($seed . ' daylight camera test'),
        trim($seed . ' gaming performance'),
        trim($seed . ' benchmark screenshot'),
    ];
    $fallbackVideo = [
        trim($seed . ' full review'),
        trim($seed . ' camera test'),
        trim($seed . ' gaming test'),
        trim($seed . ' battery test'),
    ];
    $fallback = [
        'image_queries' => $fallbackImage,
        'video_queries' => $fallbackVideo,
        'source' => 'fallback',
        'reason' => 'preview fallback',
    ];

    $prompt = "Generate concise media search queries for ecommerce product page.\n"
        . "Product full name: {$productName}\n"
        . "Model/SKU: {$productModel}\n"
        . "Description: {$description}\n"
        . "Return ONLY JSON with keys image_queries and video_queries (4 each).\n"
        . "Focus on camera samples, gaming performance, benchmarks, battery tests.\n";
    $extraPrompt = trim((string)$extraPrompt);
    $focusKeywords = trim((string)$focusKeywords);
    if ($extraPrompt !== '') {
        $prompt .= "Extra instructions: " . $extraPrompt . "\n";
    }
    if ($focusKeywords !== '') {
        $prompt .= "Prefer keywords: " . $focusKeywords . "\n";
    }

    $cfg = function_exists('getAiConfiguration') ? getAiConfiguration() : null;
    if (!$cfg || !function_exists('callRealAiModel')) {
        return $fallback;
    }
    $resp = callRealAiModel([
        ['role' => 'system', 'content' => 'Return valid JSON only.'],
        ['role' => 'user', 'content' => $prompt],
    ], 0.25);
    if (!is_string($resp) || trim($resp) === '') {
        return $fallback;
    }
    $resp = trim($resp);
    $resp = preg_replace('/^```(?:json)?/i', '', $resp);
    $resp = preg_replace('/```$/', '', $resp);
    $obj = json_decode(trim($resp), true);
    if (!is_array($obj)) {
        return $fallback;
    }
    $img = isset($obj['image_queries']) && is_array($obj['image_queries']) ? $obj['image_queries'] : [];
    $vid = isset($obj['video_queries']) && is_array($obj['video_queries']) ? $obj['video_queries'] : [];
    return [
        'image_queries' => rtel_normalize_query_list($img, $fallbackImage, 4),
        'video_queries' => rtel_normalize_query_list($vid, $fallbackVideo, 4),
        'source' => (string)($cfg['provider'] ?? 'ai'),
        'reason' => 'preview generated',
    ];
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblai_setting (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$settingDefaults = [
    'product_media_system_prompt' => "Prioritize product-relevant media searches. Avoid ambiguous non-tech meaning. Focus on camera sample photos, gaming performance, benchmark visuals, and real review media.",
    'product_media_focus_keywords' => "camera sample,night mode,gaming performance,fps test,benchmark,battery test,display test,review",
];

$settingValues = $settingDefaults;
$notice = ['type' => '', 'text' => ''];
$previewInput = [
    'name' => '',
    'model' => '',
    'description' => '',
];
$previewResult = null;

$sel = $conn->query("SELECT setting_key, setting_value FROM tblai_setting WHERE setting_key IN ('product_media_system_prompt','product_media_focus_keywords')");
if ($sel) {
    while ($row = $sel->fetch_assoc()) {
        $k = (string)($row['setting_key'] ?? '');
        if ($k !== '' && array_key_exists($k, $settingValues)) {
            $settingValues[$k] = trim((string)($row['setting_value'] ?? ''));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prompt = trim((string)($_POST['product_media_system_prompt'] ?? ''));
    $keywords = trim((string)($_POST['product_media_focus_keywords'] ?? ''));

    if (mb_strlen($prompt) > 4000) {
        $prompt = mb_substr($prompt, 0, 4000);
    }
    if (mb_strlen($keywords) > 1000) {
        $keywords = mb_substr($keywords, 0, 1000);
    }

    $previewInput['name'] = trim((string)($_POST['preview_product_name'] ?? ''));
    $previewInput['model'] = trim((string)($_POST['preview_product_model'] ?? ''));
    $previewInput['description'] = trim((string)($_POST['preview_product_description'] ?? ''));
    $action = trim((string)($_POST['form_action'] ?? 'save'));

    $updates = [
        'product_media_system_prompt' => $prompt,
        'product_media_focus_keywords' => $keywords,
    ];

    if ($action === 'preview') {
        $previewResult = rtel_preview_media_queries(
            $previewInput['name'],
            $previewInput['model'],
            $previewInput['description'],
            $updates['product_media_system_prompt'],
            $updates['product_media_focus_keywords']
        );
        $notice = ['type' => 'info', 'text' => 'Preview generated. Save settings if this looks good.'];
    } else {
        $ok = true;
        $stmt = $conn->prepare("INSERT INTO tblai_setting (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            foreach ($updates as $k => $v) {
                $stmt->bind_param('ss', $k, $v);
                if (!$stmt->execute()) {
                    $ok = false;
                    break;
                }
            }
            $stmt->close();
        } else {
            $ok = false;
        }

        if ($ok) {
            $settingValues = array_merge($settingValues, $updates);
            rtel_admin_log_event($conn, 'settings_update', 'success', 'AI training settings updated');
            $notice = ['type' => 'success', 'text' => 'AI training settings saved successfully.'];
        } else {
            rtel_admin_log_event($conn, 'settings_update', 'failed', 'AI training settings update failed');
            $notice = ['type' => 'danger', 'text' => 'Failed to save AI training settings.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Training Settings</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <style>
        .hint-box { background:#f8f9fc; border:1px solid #e6ebf4; border-radius:10px; padding:12px; }
        .mono-small { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('ai_training.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>AI Training Settings</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">AI Training</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <section class="section">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Product Page AI Media Tuning</h5></div>
                    <div class="card-body">
                        <?php if ($notice['text'] !== ''): ?>
                            <div class="alert alert-<?php echo h($notice['type']); ?> mb-3"><?php echo h($notice['text']); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label fw-bold">System Prompt Add-on</label>
                                <textarea class="form-control" name="product_media_system_prompt" rows="5" maxlength="4000"><?php echo h($settingValues['product_media_system_prompt']); ?></textarea>
                                <small class="text-muted">This text is appended to AI media-query prompt generation in <span class="mono-small">web/product.php</span>.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Focus Keywords (comma separated)</label>
                                <input type="text" class="form-control" name="product_media_focus_keywords" maxlength="1000" value="<?php echo h($settingValues['product_media_focus_keywords']); ?>">
                                <small class="text-muted">Example: camera sample, gaming performance, benchmark, battery test, display test</small>
                            </div>
                            <div class="hint-box mb-3">
                                <div class="fw-bold mb-2">Tips</div>
                                <ul class="mb-0">
                                    <li>Keep prompt short and specific.</li>
                                    <li>Avoid brand-ambiguous words without context.</li>
                                    <li>Use tech intent terms like camera sample, gaming FPS, benchmark, night mode.</li>
                                </ul>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Preview Product Name</label>
                                    <input type="text" class="form-control" name="preview_product_name" maxlength="200" value="<?php echo h($previewInput['name']); ?>" placeholder="Samsung Galaxy S24 Ultra">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Preview Model/SKU</label>
                                    <input type="text" class="form-control" name="preview_product_model" maxlength="100" value="<?php echo h($previewInput['model']); ?>" placeholder="SM-S928B">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Preview Description</label>
                                    <input type="text" class="form-control" name="preview_product_description" maxlength="500" value="<?php echo h($previewInput['description']); ?>" placeholder="200MP camera, Snapdragon, gaming ready">
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-outline-primary" type="submit" name="form_action" value="preview"><i class="bi bi-lightning-charge"></i> Preview Queries</button>
                                <button class="btn btn-primary" type="submit" name="form_action" value="save"><i class="bi bi-save"></i> Save Settings</button>
                            </div>
                        </form>
                        <?php if (is_array($previewResult)): ?>
                            <hr>
                            <h6 class="mb-2">Preview Output</h6>
                            <div class="hint-box">
                                <div><strong>Source:</strong> <?php echo h((string)($previewResult['source'] ?? 'unknown')); ?></div>
                                <div class="mt-2"><strong>Image queries</strong></div>
                                <ul class="mb-2">
                                    <?php foreach ((array)($previewResult['image_queries'] ?? []) as $q): ?>
                                        <li><?php echo h((string)$q); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div><strong>Video queries</strong></div>
                                <ul class="mb-0">
                                    <?php foreach ((array)($previewResult['video_queries'] ?? []) as $q): ?>
                                        <li><?php echo h((string)$q); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
