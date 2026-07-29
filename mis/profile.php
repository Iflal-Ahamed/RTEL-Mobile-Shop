<?php
session_start();
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';

rtel_require_admin_auth();
rtel_require_admin_page_access('profile.php');

$adminId = trim((string)$_SESSION['admin_id']);
$noticeType = '';
$noticeText = '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin (
    admin_id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(150) NOT NULL DEFAULT 'Admin',
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'update_info') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($name === '' || strlen($name) < 2) {
            $noticeType = 'error';
            $noticeText = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $noticeType = 'error';
            $noticeText = 'Please enter a valid email address.';
        } else {
            $dup = $conn->prepare("SELECT admin_id FROM tbladmin WHERE email = ? AND admin_id <> ? LIMIT 1");
            $dup->bind_param('ss', $email, $adminId);
            $dup->execute();
            $dupRow = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($dupRow) {
                $noticeType = 'error';
                $noticeText = 'This email is already used by another admin.';
            } else {
                $up = $conn->prepare("UPDATE tbladmin SET name = ?, email = ? WHERE admin_id = ? LIMIT 1");
                $up->bind_param('sss', $name, $email, $adminId);
                $ok = $up->execute();
                $up->close();
                if ($ok) {
                    $_SESSION['admin_email'] = $email;
                    $noticeType = 'success';
                    $noticeText = 'Profile information updated successfully.';
                } else {
                    $noticeType = 'error';
                    $noticeText = 'Unable to update profile information.';
                }
            }
        }
    } elseif ($action === 'update_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $noticeType = 'error';
            $noticeText = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $noticeType = 'error';
            $noticeText = 'New password and confirm password do not match.';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,64}$/', $newPassword)) {
            $noticeType = 'error';
            $noticeText = 'Password must be 8-64 characters and include letters and numbers.';
        } else {
            $q = $conn->prepare("SELECT password FROM tbladmin WHERE admin_id = ? LIMIT 1");
            $q->bind_param('s', $adminId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();

            if (!$row || !password_verify($currentPassword, (string)$row['password'])) {
                $noticeType = 'error';
                $noticeText = 'Current password is incorrect.';
            } elseif (password_verify($newPassword, (string)$row['password'])) {
                $noticeType = 'error';
                $noticeText = 'New password must be different from current password.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $up = $conn->prepare("UPDATE tbladmin SET password = ? WHERE admin_id = ? LIMIT 1");
                $up->bind_param('ss', $hash, $adminId);
                $ok = $up->execute();
                $up->close();
                if ($ok) {
                    $noticeType = 'success';
                    $noticeText = 'Password updated successfully.';
                } else {
                    $noticeType = 'error';
                    $noticeText = 'Unable to update password.';
                }
            }
        }
    }
}

$admin = null;
$stmt = $conn->prepare("SELECT admin_id, name, email FROM tbladmin WHERE admin_id = ? LIMIT 1");
$stmt->bind_param('s', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin) {
    $admin = ['admin_id' => $adminId, 'name' => 'Admin', 'email' => (string)($_SESSION['admin_email'] ?? '')];
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
    <style>
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 64px; }
        .pw-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            background: #fff;
            padding: 3px 8px;
            font-size: 12px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/includes/sidebar-nav.php';
rtel_render_sidebar_nav('profile.php');
?>
<div id="app">
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>

        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>My Profile</h3></div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Profile</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">My Information</h5>
                                <form method="POST" id="infoForm" novalidate>
                                    <input type="hidden" name="action" value="update_info">
                                    <div class="mb-3">
                                        <label class="form-label">Admin ID</label>
                                        <input type="text" class="form-control" value="<?php echo h($admin['admin_id'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name" required minlength="2" maxlength="150" value="<?php echo h($admin['name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required maxlength="150" value="<?php echo h($admin['email'] ?? ''); ?>">
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">Update Info</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Reset Password</h5>
                                <form method="POST" id="passwordForm" novalidate>
                                    <input type="hidden" name="action" value="update_password">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" class="form-control" name="current_password" id="current_password" required>
                                            <button type="button" class="pw-toggle" data-toggle-target="current_password">Show</button>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">New Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8" maxlength="64" pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}">
                                            <button type="button" class="pw-toggle" data-toggle-target="new_password">Show</button>
                                        </div>
                                    </div>
                                    <div class="mb-2"><small id="passwordMsg" style="font-size:12px;color:#6c757d;">Use 8-64 characters with letters and numbers.</small></div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="8" maxlength="64">
                                            <button type="button" class="pw-toggle" data-toggle-target="confirm_password">Show</button>
                                        </div>
                                        <small id="confirmMsg" style="font-size:12px;"></small>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="reset" class="btn btn-light-secondary">Reset</button>
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
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
document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-toggle-target');
        var input = document.getElementById(id);
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
    });
});

var passwordForm = document.getElementById('passwordForm');
var newPasswordInput = document.getElementById('new_password');
var confirmInput = document.getElementById('confirm_password');
var passwordMsg = document.getElementById('passwordMsg');
var confirmMsg = document.getElementById('confirmMsg');

function checkPasswordStrength() {
    if (!newPasswordInput || !passwordMsg) return false;
    var value = newPasswordInput.value || '';
    var strong = /^(?=.*[A-Za-z])(?=.*\d).{8,64}$/.test(value);
    if (!value) {
        passwordMsg.textContent = 'Use 8-64 characters with letters and numbers.';
        passwordMsg.style.color = '#6c757d';
    } else if (strong) {
        passwordMsg.textContent = 'Strong password';
        passwordMsg.style.color = '#28a745';
    } else {
        passwordMsg.textContent = 'Need letters, numbers, and at least 8 characters.';
        passwordMsg.style.color = '#dc3545';
    }
    return strong;
}

function checkConfirmPassword() {
    if (!newPasswordInput || !confirmInput || !confirmMsg) return false;
    var n = newPasswordInput.value || '';
    var c = confirmInput.value || '';
    if (!c) {
        confirmMsg.textContent = '';
        return false;
    }
    if (n === c) {
        confirmMsg.textContent = 'Passwords match';
        confirmMsg.style.color = '#28a745';
        return true;
    }
    confirmMsg.textContent = 'Passwords do not match';
    confirmMsg.style.color = '#dc3545';
    return false;
}

if (newPasswordInput) {
    newPasswordInput.addEventListener('input', function () {
        checkPasswordStrength();
        checkConfirmPassword();
    });
}
if (confirmInput) {
    confirmInput.addEventListener('input', checkConfirmPassword);
}

if (passwordForm) {
    passwordForm.addEventListener('submit', function (e) {
        var strong = checkPasswordStrength();
        var matched = checkConfirmPassword();
        if (!strong || !matched) {
            e.preventDefault();
            Swal.fire({icon:'error', title:'Please fix password validation errors.'});
        }
    });
}
</script>
<?php if ($noticeType !== '' && $noticeText !== ''): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($noticeType === 'success' ? 'success' : 'error'); ?>,
    title: <?php echo json_encode($noticeText); ?>,
    confirmButtonText: 'OK'
});
</script>
<?php endif; ?>
</body>
</html>
