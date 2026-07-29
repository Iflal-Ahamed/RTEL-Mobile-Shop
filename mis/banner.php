<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('banner.php');
$conn->set_charset('utf8mb4');
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblweb_banner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    heading VARCHAR(255) NOT NULL,
    sub_heading VARCHAR(255) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0
)");
mysqli_query($conn, "ALTER TABLE tblweb_banner ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0");

$notice = ['type' => '', 'text' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $heading = trim((string)($_POST['heading'] ?? ''));
        $subHeading = trim((string)($_POST['sub_heading'] ?? ''));
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $imageName = '';

        if ($heading === '') {
            $notice = ['type' => 'danger', 'text' => 'Heading is required.'];
        } else {
            if (isset($_FILES['image']) && is_array($_FILES['image']) && (int)($_FILES['image']['error'] ?? 4) !== 4) {
                $tmp = (string)($_FILES['image']['tmp_name'] ?? '');
                $orig = (string)($_FILES['image']['name'] ?? '');
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    $notice = ['type' => 'danger', 'text' => 'Invalid image format. Use jpg, png, webp, or gif.'];
                } elseif (is_uploaded_file($tmp)) {
                    $imageName = 'banner_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file($tmp, dirname(__DIR__) . '/images/' . $imageName)) {
                        $notice = ['type' => 'danger', 'text' => 'Image upload failed.'];
                    }
                }
            }

            if ($notice['type'] === '') {
                if ($id > 0) {
                    if ($imageName !== '') {
                        $stmt = $conn->prepare("UPDATE tblweb_banner SET heading=?, sub_heading=?, image=?, display_order=? WHERE id=?");
                        $stmt->bind_param('sssii', $heading, $subHeading, $imageName, $displayOrder, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE tblweb_banner SET heading=?, sub_heading=?, display_order=? WHERE id=?");
                        $stmt->bind_param('ssii', $heading, $subHeading, $displayOrder, $id);
                    }
                    $ok = $stmt && $stmt->execute();
                    if ($stmt) $stmt->close();
                    rtel_admin_log_event($conn, 'settings_update', $ok ? 'success' : 'failed', 'Banner ' . ($ok ? 'updated' : 'update failed') . ' #' . $id);
                    $notice = $ok ? ['type' => 'success', 'text' => 'Banner updated successfully.'] : ['type' => 'danger', 'text' => 'Failed to update banner.'];
                } else {
                    if ($imageName === '') {
                        $notice = ['type' => 'danger', 'text' => 'Banner image is required for new record.'];
                    } else {
                        $stmt = $conn->prepare("INSERT INTO tblweb_banner (image, heading, sub_heading, status, display_order) VALUES (?, ?, ?, 1, ?)");
                        $stmt->bind_param('sssi', $imageName, $heading, $subHeading, $displayOrder);
                        $ok = $stmt && $stmt->execute();
                        if ($stmt) $stmt->close();
                        rtel_admin_log_event($conn, 'settings_update', $ok ? 'success' : 'failed', 'Banner ' . ($ok ? 'added' : 'add failed') . ': ' . $heading);
                        $notice = $ok ? ['type' => 'success', 'text' => 'Banner added successfully.'] : ['type' => 'danger', 'text' => 'Failed to add banner.'];
                    }
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $nextStatus = ((int)($_POST['status'] ?? 0) === 1) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE tblweb_banner SET status=? WHERE id=?");
        $stmt->bind_param('ii', $nextStatus, $id);
        $ok = $stmt && $stmt->execute();
        if ($stmt) $stmt->close();
        rtel_admin_log_event($conn, 'settings_update', $ok ? 'success' : 'failed', 'Banner status ' . ($ok ? 'updated' : 'update failed') . ' #' . $id);
        $notice = $ok ? ['type' => 'success', 'text' => 'Banner status updated.'] : ['type' => 'danger', 'text' => 'Failed to update status.'];
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM tblweb_banner WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt && $stmt->execute();
        if ($stmt) $stmt->close();
        rtel_admin_log_event($conn, 'settings_update', $ok ? 'success' : 'failed', 'Banner ' . ($ok ? 'deleted' : 'delete failed') . ' #' . $id);
        $notice = $ok ? ['type' => 'success', 'text' => 'Banner deleted.'] : ['type' => 'danger', 'text' => 'Failed to delete banner.'];
    }
}

$rows = [];
$res = mysqli_query($conn, "SELECT id, image, heading, sub_heading, status, display_order FROM tblweb_banner ORDER BY display_order ASC, id DESC");
while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
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
    <?php rtel_render_sidebar_nav('banner.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Banner Settings</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Banner</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Add Banner</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" value="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Heading</label>
                                    <textarea name="heading" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sub Heading</label>
                                    <textarea name="sub_heading" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Banner Image</label>
                                    <input type="file" name="image" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" name="display_order" class="form-control" min="0" value="0">
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button type="reset" class="btn btn-light-secondary">Reset</button>
                                    <button type="submit" class="btn btn-primary">Add Banner</button>
                                </div>
                            </div>
                        </form>

                        <hr>
                        <h5 class="card-title">All Banners</h5>
                        <div class="table-responsive">
                            <table class="table table-hover text-start">
                                <thead><tr><th>ID</th><th>Image</th><th>Heading</th><th>Sub Heading</th><th>Order</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php if (count($rows) === 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No banners found.</td></tr>
                                <?php else: foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?php echo (int)$row['id']; ?></td>
                                        <td><img src="../images/<?php echo htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Banner" style="width:120px;height:60px;object-fit:cover;border-radius:6px;"></td>
                                        <td><?php echo htmlspecialchars($row['heading'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['sub_heading'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$row['display_order']; ?></td>
                                        <td><span class="badge <?php echo (int)$row['status'] === 1 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$row['status'] === 1 ? 'Active' : 'Disabled'; ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 js-open-edit"
                                                data-id="<?php echo (int)$row['id']; ?>"
                                                data-heading="<?php echo htmlspecialchars($row['heading'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-subheading="<?php echo htmlspecialchars($row['sub_heading'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-order="<?php echo (int)$row['display_order']; ?>"
                                                data-image="<?php echo htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editBannerModal"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo (int)$row['status'] === 1 ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning me-1"><?php echo (int)$row['status'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this banner?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="editBannerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="editBannerId" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Update Banner</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Heading</label>
              <textarea class="form-control" name="heading" id="editBannerHeading" rows="3" required></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sub Heading</label>
              <textarea class="form-control" name="sub_heading" id="editBannerSubHeading" rows="3"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Display Order</label>
              <input type="number" class="form-control" name="display_order" id="editBannerOrder" min="0" value="0">
            </div>
            <div class="col-md-8">
              <label class="form-label">Banner Image (optional)</label>
              <input type="file" class="form-control" name="image">
            </div>
            <div class="col-12">
              <img id="editBannerPreview" src="" alt="Banner preview" style="width:200px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/main.js"></script>
<script>
document.querySelectorAll('.js-open-edit').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.getElementById('editBannerId').value = btn.getAttribute('data-id') || '0';
    document.getElementById('editBannerHeading').value = btn.getAttribute('data-heading') || '';
    document.getElementById('editBannerSubHeading').value = btn.getAttribute('data-subheading') || '';
    document.getElementById('editBannerOrder').value = btn.getAttribute('data-order') || '0';
    var img = btn.getAttribute('data-image') || '';
    document.getElementById('editBannerPreview').src = img ? ('../images/' + img) : '';
  });
});
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