<?php
$notice = ['type' => '', 'text' => ''];
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('logo.php');
$rootPath = dirname(__DIR__);
$logoPath = $rootPath . '/images/header_logo.jpg';
$webLogoPath = $rootPath . '/web/images/header_logo.jpg';
$faviconPath = $rootPath . '/images/favicon.png';
$webFaviconPath = $rootPath . '/web/images/favicon.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assetType = (string)($_POST['asset_type'] ?? 'logo');
    $inputName = $assetType === 'favicon' ? 'favicon' : 'logo';
    if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
        $notice = ['type' => 'danger', 'text' => 'Please choose an image.'];
    } else {
        $err = (int)($_FILES[$inputName]['error'] ?? 4);
        if ($err !== 0) {
            $notice = ['type' => 'danger', 'text' => 'Upload failed.'];
        } else {
            $tmp = (string)($_FILES[$inputName]['tmp_name'] ?? '');
            $ext = strtolower(pathinfo((string)($_FILES[$inputName]['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $notice = ['type' => 'danger', 'text' => 'Invalid image type. Use jpg, png, webp, or gif.'];
            } elseif (!is_uploaded_file($tmp)) {
                $notice = ['type' => 'danger', 'text' => 'Invalid upload request.'];
            } else {
                $primaryPath = $assetType === 'favicon' ? $faviconPath : $logoPath;
                $secondaryPath = $assetType === 'favicon' ? $webFaviconPath : $webLogoPath;
                $ok = move_uploaded_file($tmp, $primaryPath);
                if ($ok) {
                    @copy($primaryPath, $secondaryPath);
                }
                if ($ok) {
                    rtel_admin_log_event($conn ?? null, 'settings_update', 'success', $assetType === 'favicon' ? 'Updated favicon' : 'Updated header logo');
                    $notice = ['type' => 'success', 'text' => $assetType === 'favicon' ? 'Favicon updated successfully.' : 'Header logo updated successfully.'];
                } else {
                    rtel_admin_log_event($conn ?? null, 'settings_update', 'failed', $assetType === 'favicon' ? 'Failed updating favicon' : 'Failed updating header logo');
                    $notice = ['type' => 'danger', 'text' => 'Unable to save file.'];
                }
            }
        }
    }
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
    <link rel="shortcut icon" href="../web/images/logo.jpg" type="image/x-icon">
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('logo.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Header Logo Settings</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Header Logo</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <section class="section">
                <div class="card"><div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Current Header Logo</label>
                            <div><img id="logoPreviewMain" src="../images/header_logo.jpg?ts=<?php echo time(); ?>" alt="Current logo" style="width:96px;height:96px;object-fit:cover;border-radius:50%;border:1px solid #ddd;"></div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div style="width:120px;height:90px;background:#ffffff;border:1px solid #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <img id="logoPreviewLight" src="../images/header_logo.jpg?ts=<?php echo time(); ?>" alt="Logo preview light" style="width:56px;height:56px;object-fit:cover;border-radius:50%;">
                                </div>
                                <div style="width:120px;height:90px;background:#111111;border:1px solid #222;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <img id="logoPreviewDark" src="../images/header_logo.jpg?ts=<?php echo time(); ?>" alt="Logo preview dark" style="width:56px;height:56px;object-fit:cover;border-radius:50%;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="asset_type" value="logo">
                                <label class="form-label">Upload New Header Logo</label>
                                <input type="file" class="form-control mb-2" name="logo" id="logoInput" required>
                                <button type="submit" class="btn btn-primary">Update Header Logo</button>
                            </form>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-md-6">
                            <label class="form-label">Current Favicon</label>
                            <div style="width:90px;height:90px;border:1px solid #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <img id="faviconPreview" src="../images/favicon.png?ts=<?php echo time(); ?>" alt="Current favicon" style="width:48px;height:48px;object-fit:contain;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="asset_type" value="favicon">
                                <label class="form-label">Upload New Favicon</label>
                                <input type="file" class="form-control mb-2" name="favicon" id="faviconInput" required>
                                <button type="submit" class="btn btn-outline-primary">Update Favicon</button>
                            </form>
                        </div>
                    </div>
                </div></div>
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
    var logoInput = document.getElementById('logoInput');
    if (logoInput) {
        logoInput.addEventListener('change', function () {
            var f = logoInput.files && logoInput.files[0];
            if (!f) return;
            var url = URL.createObjectURL(f);
            ['logoPreviewMain', 'logoPreviewLight', 'logoPreviewDark'].forEach(function(id){
                var img = document.getElementById(id);
                if (img) img.src = url;
            });
        });
    }
    var faviconInput = document.getElementById('faviconInput');
    if (faviconInput) {
        faviconInput.addEventListener('change', function () {
            var f = faviconInput.files && faviconInput.files[0];
            if (!f) return;
            var url = URL.createObjectURL(f);
            var img = document.getElementById('faviconPreview');
            if (img) img.src = url;
        });
    }
})();
</script>
<?php if ($notice['type'] !== ''): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($notice['type'] === 'success' ? 'success' : 'error'); ?>,
    title: <?php echo json_encode($notice['text']); ?>,
    confirmButtonText: 'OK'
});
</script>
<?php endif; ?>
</body>
</html>
