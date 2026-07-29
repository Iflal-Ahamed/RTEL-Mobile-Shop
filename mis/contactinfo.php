<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('contactinfo.php');
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

$notice = ['type' => '', 'text' => ''];
$row = mysqli_query($conn, "SELECT * FROM tblcontact ORDER BY no ASC LIMIT 1");
$contact = $row ? mysqli_fetch_assoc($row) : null;

if (!$contact) {
    mysqli_query($conn, "INSERT INTO tblcontact (name,address,phone,email,whatsapp,insta,fb,whatsapp_status,insta_status,fb_status)
    VALUES ('R-tel Mobile Shop','','','','','','',1,1,1)");
    $row = mysqli_query($conn, "SELECT * FROM tblcontact ORDER BY no ASC LIMIT 1");
    $contact = $row ? mysqli_fetch_assoc($row) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contact) {
    $name = trim((string)($_POST['name'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $insta = trim((string)($_POST['insta'] ?? ''));
    $fb = trim((string)($_POST['fb'] ?? ''));
    $wStatus = isset($_POST['whatsapp_status']) ? 1 : 0;
    $iStatus = isset($_POST['insta_status']) ? 1 : 0;
    $fStatus = isset($_POST['fb_status']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE tblcontact SET name=?, address=?, phone=?, email=?, whatsapp=?, insta=?, fb=?, whatsapp_status=?, insta_status=?, fb_status=? WHERE no=?");
    $stmt->bind_param('sssssssiiii', $name, $address, $phone, $email, $whatsapp, $insta, $fb, $wStatus, $iStatus, $fStatus, $contact['no']);
    $ok = $stmt && $stmt->execute();
    if ($stmt) $stmt->close();
    if ($ok) {
        rtel_admin_log_event($conn, 'settings_update', 'success', 'Updated contact info settings');
    } else {
        rtel_admin_log_event($conn, 'settings_update', 'failed', 'Failed updating contact info settings');
    }
    $notice = $ok ? ['type' => 'success', 'text' => 'Contact information updated.'] : ['type' => 'danger', 'text' => 'Failed to update contact info.'];
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
    <link rel="shortcut icon" href="../web/images/logo.jpg" type="image/x-icon">
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('contactinfo.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Contact Info Settings</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Contact Info</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <section class="section">
                <div class="card"><div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Shop Name</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string)($contact['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars((string)($contact['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars((string)($contact['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Address</label><input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars((string)($contact['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4"><label class="form-label">WhatsApp Link</label><input type="text" class="form-control" name="whatsapp" value="<?php echo htmlspecialchars((string)($contact['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4"><label class="form-label">Facebook Link</label><input type="text" class="form-control" name="fb" value="<?php echo htmlspecialchars((string)($contact['fb'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4"><label class="form-label">Instagram Link</label><input type="text" class="form-control" name="insta" value="<?php echo htmlspecialchars((string)($contact['insta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-12"><label class="form-label">Social Icons Visibility</label>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="whatsapp_status" id="whatsapp_status" <?php echo ((int)($contact['whatsapp_status'] ?? 1) === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="whatsapp_status">Show WhatsApp</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="fb_status" id="fb_status" <?php echo ((int)($contact['fb_status'] ?? 1) === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="fb_status">Show Facebook</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="insta_status" id="insta_status" <?php echo ((int)($contact['insta_status'] ?? 1) === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="insta_status">Show Instagram</label></div>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Save Contact Settings</button></div>
                        </div>
                    </form>
                </div></div>
            </section>
        </div>
    </div>
</div>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/main.js"></script>
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