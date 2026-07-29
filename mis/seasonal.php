<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';
rtel_require_admin_auth();
$conn->set_charset('utf8mb4');

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcontact (
    no INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(50) NOT NULL DEFAULT '',
    email VARCHAR(150) NOT NULL DEFAULT '',
    whatsapp VARCHAR(255) NOT NULL DEFAULT '',
    insta VARCHAR(255) NOT NULL DEFAULT '',
    fb VARCHAR(255) NOT NULL DEFAULT ''
)");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS whatsapp_status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS insta_status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS fb_status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_enabled TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_theme VARCHAR(30) NOT NULL DEFAULT 'auto'");
mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_emojis TEXT NULL");

$notice = ['type' => '', 'text' => ''];
$row = mysqli_query($conn, "SELECT * FROM tblcontact ORDER BY no ASC LIMIT 1");
$contact = $row ? mysqli_fetch_assoc($row) : null;
if (!$contact) {
    mysqli_query($conn, "INSERT INTO tblcontact (name,address,phone,email,whatsapp,insta,fb,whatsapp_status,insta_status,fb_status,seasonal_effect_enabled,seasonal_effect_theme,seasonal_effect_emojis)
    VALUES ('R-tel Mobile Shop','','','','','','',1,1,1,1,'auto','')");
    $row = mysqli_query($conn, "SELECT * FROM tblcontact ORDER BY no ASC LIMIT 1");
    $contact = $row ? mysqli_fetch_assoc($row) : null;
}

$themes = [
    'auto' => 'Auto by Season',
    'stars' => 'Stars',
    'snow' => 'Snow',
    'christmas' => 'Christmas',
    'hearts' => 'Hearts',
    'flowers' => 'Flowers',
    'halloween' => 'Halloween'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contact) {
    $enabled = isset($_POST['seasonal_effect_enabled']) ? 1 : 0;
    $theme = strtolower(trim((string)($_POST['seasonal_effect_theme'] ?? 'auto')));
    $customEmojis = trim((string)($_POST['seasonal_effect_emojis'] ?? ''));
    if (strlen($customEmojis) > 500) $customEmojis = substr($customEmojis, 0, 500);
    if (!isset($themes[$theme])) $theme = 'auto';

    $stmt = $conn->prepare("UPDATE tblcontact SET seasonal_effect_enabled=?, seasonal_effect_theme=?, seasonal_effect_emojis=? WHERE no=?");
    if ($stmt) {
        $stmt->bind_param('issi', $enabled, $theme, $customEmojis, $contact['no']);
        $ok = $stmt->execute();
        $stmt->close();
        rtel_admin_log_event($conn, 'settings_update', $ok ? 'success' : 'failed', 'Seasonal settings ' . ($ok ? 'updated' : 'update failed'));
        $notice = $ok ? ['type' => 'success', 'text' => 'Seasonal effects settings updated.'] : ['type' => 'danger', 'text' => 'Failed to update seasonal settings.'];
    } else {
        $notice = ['type' => 'danger', 'text' => 'Failed to prepare seasonal settings update.'];
    }
    $row = mysqli_query($conn, "SELECT * FROM tblcontact WHERE no=" . (int)$contact['no'] . " LIMIT 1");
    $contact = $row ? mysqli_fetch_assoc($row) : $contact;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R-tel Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <style>
        #seasonalPreview {
            position: relative;
            height: 180px;
            border: 1px dashed #ced4da;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        }
        .seasonal-preview-item {
            position: absolute;
            top: -24px;
            user-select: none;
            pointer-events: none;
            animation: previewFall linear 1 forwards, previewSway ease-in-out infinite;
        }
        @keyframes previewFall {
            0% { transform: translateY(0); opacity: .2; }
            10% { opacity: 1; }
            100% { transform: translateY(210px); opacity: .95; }
        }
        @keyframes previewSway {
            0% { margin-left: -6px; }
            50% { margin-left: 8px; }
            100% { margin-left: -6px; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('seasonal.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Seasonal Effects</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Seasonal Effects</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-3">Enable or disable festive falling effects on the website and choose a theme manually.</p>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="seasonal_effect_enabled" name="seasonal_effect_enabled" <?php echo ((int)($contact['seasonal_effect_enabled'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="seasonal_effect_enabled">Enable Seasonal Falling Effects</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Theme</label>
                                    <select class="form-select" name="seasonal_effect_theme" id="seasonal_effect_theme">
                                        <?php foreach ($themes as $key => $label): ?>
                                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($contact['seasonal_effect_theme'] ?? 'auto') === $key) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Auto changes by month. Manual options force one effect all year.</small>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Custom Emojis (optional)</label>
                                    <input type="text" class="form-control" name="seasonal_effect_emojis" id="seasonal_effect_emojis" value="<?php echo htmlspecialchars((string)($contact['seasonal_effect_emojis'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Example: ✨ ⭐ ❄️ 🎄 🎁">
                                    <small class="text-muted">Separate emojis with space or comma. If added, these emojis are used on website instead of theme icons.</small>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label mb-0">Live Preview</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="previewSeasonalBtn"><i class="bi bi-eye me-1"></i>Preview Effect</button>
                                    </div>
                                    <div id="seasonalPreview"></div>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Save Seasonal Settings</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/main.js"></script>
<script>
(function () {
    const previewBox = document.getElementById('seasonalPreview');
    const btn = document.getElementById('previewSeasonalBtn');
    const themeInput = document.getElementById('seasonal_effect_theme');
    const emojisInput = document.getElementById('seasonal_effect_emojis');
    if (!previewBox || !btn || !themeInput || !emojisInput) return;

    function pickThemeSymbols(theme) {
        const map = {
            stars: ['✨', '★', '⋆'],
            snow: ['❄️', '✨'],
            christmas: ['❄️', '🎄', '🎁'],
            hearts: ['❤️', '✨'],
            flowers: ['🌸', '🌼', '✨'],
            halloween: ['🎃', '✨']
        };
        if (theme === 'auto') return ['✨', '★', '⋆'];
        return map[theme] || ['✨', '★', '⋆'];
    }

    function parseCustomEmojis(raw) {
        const txt = String(raw || '').trim();
        if (!txt) return [];
        return txt.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
    }

    function spawn(symbols) {
        const node = document.createElement('span');
        node.className = 'seasonal-preview-item';
        node.textContent = symbols[Math.floor(Math.random() * symbols.length)];
        node.style.left = Math.round(Math.random() * 95) + '%';
        node.style.fontSize = (Math.random() * 10 + 14) + 'px';
        const fall = Math.random() * 2.5 + 2.5;
        const sway = Math.random() * 1.5 + 1.8;
        node.style.animationDuration = fall + 's, ' + sway + 's';
        previewBox.appendChild(node);
        setTimeout(() => { if (node.parentNode) node.parentNode.removeChild(node); }, Math.round(fall * 1000) + 300);
    }

    btn.addEventListener('click', function () {
        previewBox.innerHTML = '';
        const custom = parseCustomEmojis(emojisInput.value);
        const symbols = custom.length ? custom : pickThemeSymbols(themeInput.value);
        for (let i = 0; i < 18; i++) {
            setTimeout(() => spawn(symbols), i * 100);
        }
    });
})();
</script>
<?php if ($notice['type'] !== ''): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($notice['type'] === 'success' ? 'success' : 'error'); ?>,
    title: <?php echo json_encode($notice['text']); ?>,
    timer: 1600,
    showConfirmButton: false
});
</script>
<?php endif; ?>
</body>
</html>
