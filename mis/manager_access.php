<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_logger.php';

rtel_require_admin_auth();
if (!rtel_is_super_admin()) {
    http_response_code(403);
    exit('Access denied.');
}

@mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS name VARCHAR(150) NOT NULL DEFAULT 'Admin'");
@mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL DEFAULT 'admin'");
@mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS role_label VARCHAR(50) NOT NULL DEFAULT 'Admin'");
@mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin_page_permission (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NOT NULL,
    page_key VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_admin_page (admin_id, page_key),
    KEY idx_admin_id (admin_id)
)");

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function manager_page_options()
{
    $catalog = rtel_permission_page_catalog();
    unset($catalog['manager_access.php']);
    return $catalog;
}

function read_manager_permissions($conn, $managerId)
{
    $items = [];
    $stmt = $conn->prepare("SELECT page_key FROM tbladmin_page_permission WHERE admin_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $managerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = strtolower(trim((string)($row['page_key'] ?? '')));
        }
        $stmt->close();
    }
    return array_values(array_unique($items));
}

function save_manager_permissions($conn, $managerId, $pages)
{
    $allowed = array_keys(manager_page_options());
    $clean = [];
    foreach ((array)$pages as $page) {
        $page = strtolower(trim((string)$page));
        if (in_array($page, $allowed, true)) {
            $clean[] = $page;
        }
    }
    $clean = array_values(array_unique($clean));

    $del = $conn->prepare("DELETE FROM tbladmin_page_permission WHERE admin_id = ?");
    if ($del) {
        $del->bind_param('s', $managerId);
        $del->execute();
        $del->close();
    }

    if (!$clean) {
        return;
    }

    $ins = $conn->prepare("INSERT INTO tbladmin_page_permission (admin_id, page_key, created_at) VALUES (?, ?, ?)");
    if ($ins) {
        $now = date('Y-m-d H:i:s');
        foreach ($clean as $page) {
            $ins->bind_param('sss', $managerId, $page, $now);
            $ins->execute();
        }
        $ins->close();
    }
}

function generate_manager_id($conn)
{
    for ($i = 0; $i < 8; $i++) {
        $id = 'M' . date('ymd') . random_int(100, 999);
        $stmt = $conn->prepare("SELECT admin_id FROM tbladmin WHERE admin_id = ? LIMIT 1");
        if (!$stmt) {
            return $id;
        }
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $id;
        }
    }
    return 'M' . time();
}

$noticeType = '';
$noticeText = '';
$selectedManager = trim((string)($_GET['manager_id'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'create_manager') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $roleLabel = trim((string)($_POST['role_label'] ?? 'Manager'));
        $pages = (array)($_POST['pages'] ?? []);

        if ($name === '' || strlen($name) < 2) {
            $noticeType = 'error';
            $noticeText = 'Manager name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $noticeType = 'error';
            $noticeText = 'Please enter a valid manager email.';
        } elseif ($password !== $confirm) {
            $noticeType = 'error';
            $noticeText = 'Password and confirm password must match.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $noticeType = 'error';
            $noticeText = 'Password must be 8+ characters with uppercase, lowercase, and a number.';
        } elseif ($roleLabel === '' || strlen($roleLabel) < 2) {
            $noticeType = 'error';
            $noticeText = 'Role label is required (example: Manager, Designer).';
        } else {
            $dup = $conn->prepare("SELECT admin_id FROM tbladmin WHERE email = ? LIMIT 1");
            $dup->bind_param('s', $email);
            $dup->execute();
            $dupRow = $dup->get_result()->fetch_assoc();
            $dup->close();

            if ($dupRow) {
                $noticeType = 'error';
                $noticeText = 'This email is already used.';
            } else {
                $managerId = generate_manager_id($conn);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO tbladmin (admin_id, name, email, password, type, role_label) VALUES (?, ?, ?, ?, 'manager', ?)");
                if ($ins) {
                    $ins->bind_param('sssss', $managerId, $name, $email, $hash, $roleLabel);
                    $ok = $ins->execute();
                    $ins->close();
                    if ($ok) {
                        save_manager_permissions($conn, $managerId, $pages);
                        rtel_admin_log_event($conn, 'manager_create', 'success', 'Created manager ' . $managerId . ' (' . $roleLabel . ')');
                        $selectedManager = $managerId;
                        $noticeType = 'success';
                        $noticeText = 'Manager created and permissions saved.';
                    } else {
                        $noticeType = 'error';
                        $noticeText = 'Failed to create manager.';
                    }
                } else {
                    $noticeType = 'error';
                    $noticeText = 'Failed to prepare manager creation.';
                }
            }
        }
    } elseif ($action === 'update_permissions') {
        $managerId = trim((string)($_POST['manager_id'] ?? ''));
        $pages = (array)($_POST['pages'] ?? []);
        $selectedManager = $managerId;

        $chk = $conn->prepare("SELECT admin_id FROM tbladmin WHERE admin_id = ? AND COALESCE(type, 'admin') = 'manager' LIMIT 1");
        $chk->bind_param('s', $managerId);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$exists) {
            $noticeType = 'error';
            $noticeText = 'Manager account not found.';
        } else {
            save_manager_permissions($conn, $managerId, $pages);
            rtel_admin_log_event($conn, 'manager_permission', 'success', 'Updated manager permissions for ' . $managerId);
            $noticeType = 'success';
            $noticeText = 'Manager permissions updated.';
        }
    } elseif ($action === 'toggle_status') {
        $managerId = trim((string)($_POST['manager_id'] ?? ''));
        $nextStatus = ((int)($_POST['next_status'] ?? 0) === 1) ? 1 : 0;
        $selectedManager = $managerId;

        $upd = $conn->prepare("UPDATE tbladmin SET status = ? WHERE admin_id = ? AND COALESCE(type, 'admin') = 'manager' LIMIT 1");
        if ($upd) {
            $upd->bind_param('is', $nextStatus, $managerId);
            $ok = $upd->execute();
            $upd->close();
            if ($ok) {
                rtel_admin_log_event($conn, 'manager_permission', 'success', ($nextStatus === 1 ? 'Activated' : 'Deactivated') . ' manager ' . $managerId);
                $noticeType = 'success';
                $noticeText = $nextStatus === 1 ? 'Manager activated.' : 'Manager deactivated.';
            } else {
                $noticeType = 'error';
                $noticeText = 'Failed to change manager status.';
            }
        } else {
            $noticeType = 'error';
            $noticeText = 'Failed to prepare status update.';
        }
    }
}

$managers = [];
$mRes = $conn->query("SELECT admin_id, name, email, COALESCE(role_label, 'Manager') AS role_label, COALESCE(status, 1) AS status FROM tbladmin WHERE COALESCE(type, 'admin') = 'manager' ORDER BY name ASC");
if ($mRes) {
    while ($row = $mRes->fetch_assoc()) {
        $managers[] = $row;
    }
}

$selectedPermissions = [];
if ($selectedManager !== '') {
    $selectedPermissions = read_manager_permissions($conn, $selectedManager);
}

$pageOptions = manager_page_options();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Access - MIS</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="icon" href="../images/logo.jpg" type="image/jpeg">
    <style>
        .inline-error { font-size: 12px; color: #dc3545; min-height: 16px; }
        .inline-success { color: #198754; }
        .password-wrap { position: relative; }
        .password-wrap .form-control { padding-right: 44px; }
        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/includes/sidebar-nav.php';
rtel_render_sidebar_nav('manager_access.php');
?>
<div id="app">
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>
        <div class="page-heading">
            <h3>Manager Access Control</h3>
        </div>
        <section class="section">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Create Manager</h5></div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="create_manager">
                                <div class="mb-2">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" required minlength="2" maxlength="150">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required maxlength="150">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Role Label</label>
                                    <input type="text" name="role_label" class="form-control" required minlength="2" maxlength="50" placeholder="Manager / Designer / Supervisor">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Password</label>
                                        <div class="password-wrap">
                                            <input type="password" name="password" id="managerPassword" class="form-control" required>
                                            <button type="button" class="password-toggle" data-target="managerPassword" aria-label="Show password" title="Show password">&#128065;</button>
                                        </div>
                                        <div class="inline-error" id="managerPasswordError"></div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="password-wrap">
                                            <input type="password" name="confirm_password" id="managerConfirmPassword" class="form-control" required>
                                            <button type="button" class="password-toggle" data-target="managerConfirmPassword" aria-label="Show password" title="Show password">&#128065;</button>
                                        </div>
                                        <div class="inline-error" id="managerConfirmPasswordError"></div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Page Access</label>
                                    <div class="row">
                                        <?php foreach ($pageOptions as $key => $label): ?>
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="pages[]" value="<?php echo h($key); ?>" id="new_<?php echo h($key); ?>">
                                                    <label class="form-check-label" for="new_<?php echo h($key); ?>"><?php echo h($label); ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Create Manager</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Update Manager Permissions</h5></div>
                        <div class="card-body">
                            <form method="get" class="mb-3">
                                <label class="form-label">Select Manager</label>
                                <div class="d-flex gap-2">
                                    <select name="manager_id" class="form-select">
                                        <option value="">Choose Manager</option>
                                        <?php foreach ($managers as $m): ?>
                                            <option value="<?php echo h($m['admin_id']); ?>" <?php echo $selectedManager === $m['admin_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($m['name'] . ' - ' . $m['role_label'] . ' (' . $m['admin_id'] . ') ' . ((int)$m['status'] === 1 ? '[Active]' : '[Inactive]')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary">Load</button>
                                </div>
                            </form>
                            <?php if ($selectedManager !== ''): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="update_permissions">
                                    <input type="hidden" name="manager_id" value="<?php echo h($selectedManager); ?>">
                                    <div class="row">
                                        <?php foreach ($pageOptions as $key => $label): ?>
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="pages[]" value="<?php echo h($key); ?>" id="perm_<?php echo h($key); ?>" <?php echo in_array($key, $selectedPermissions, true) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="perm_<?php echo h($key); ?>"><?php echo h($label); ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-3">Save Permissions</button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted mb-0">Select a manager to edit assigned MIS pages.</p>
                            <?php endif; ?>
                            <?php if ($selectedManager !== ''): ?>
                                <?php
                                $currentStatus = 1;
                                foreach ($managers as $m) {
                                    if ((string)$m['admin_id'] === $selectedManager) {
                                        $currentStatus = (int)($m['status'] ?? 1);
                                        break;
                                    }
                                }
                                ?>
                                <hr>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="manager_id" value="<?php echo h($selectedManager); ?>">
                                    <input type="hidden" name="next_status" value="<?php echo $currentStatus === 1 ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $currentStatus === 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                        <?php echo $currentStatus === 1 ? 'Deactivate Manager' : 'Activate Manager'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/main.js"></script>
<script>
(function(){
    var createForm = document.querySelector('form input[name="action"][value="create_manager"]');
    var pwd = document.getElementById('managerPassword');
    var confirmPwd = document.getElementById('managerConfirmPassword');
    var errPwd = document.getElementById('managerPasswordError');
    var errConfirm = document.getElementById('managerConfirmPasswordError');
    if (!pwd || !confirmPwd || !errPwd || !errConfirm) return;

    function validatePassword() {
        var value = String(pwd.value || '');
        var strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value);
        if (value === '') {
            errPwd.textContent = '';
            errPwd.classList.remove('inline-success');
            return false;
        }
        errPwd.textContent = strong ? 'Strong password.' : 'Use 8+ characters with uppercase, lowercase, and a number.';
        errPwd.classList.toggle('inline-success', strong);
        return strong;
    }

    function validateConfirm() {
        var p = String(pwd.value || '');
        var c = String(confirmPwd.value || '');
        if (c === '') {
            errConfirm.textContent = '';
            return false;
        }
        var ok = p === c;
        errConfirm.textContent = ok ? 'Passwords match.' : 'Passwords do not match.';
        errConfirm.classList.toggle('inline-success', ok);
        return ok;
    }

    pwd.addEventListener('input', function(){ validatePassword(); validateConfirm(); });
    confirmPwd.addEventListener('input', validateConfirm);

    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.innerHTML = isHidden ? '&#128064;' : '&#128065;';
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            btn.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
        });
    });

    if (createForm && createForm.form) {
        createForm.form.addEventListener('submit', function (e) {
            var strong = validatePassword();
            var matched = validateConfirm();
            if (!strong || !matched) {
                e.preventDefault();
            }
        });
    }
})();
</script>
<?php if ($noticeType !== '' && $noticeText !== ''): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($noticeType === 'success' ? 'success' : 'error'); ?>,
    title: <?php echo json_encode($noticeText); ?>
});
</script>
<?php endif; ?>
</body>
</html>

