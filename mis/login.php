<?php
session_start();
include "connection.php";
require_once __DIR__ . '/includes/auth.php';
if (!function_exists('rtel_image_url')) {
    require_once __DIR__ . '/../includes/rtel_paths.php';
}

/**
 * Escape for safe HTML output.
 */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Collects client IP for activity logs.
 */
function get_client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim((string)$parts[0]);
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// Ensure admin activity log table exists.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin_log (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NULL,
    event_type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
)");
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

/**
 * Writes one admin login activity row.
 */
function log_admin_activity($conn, $adminId, $eventType, $status, $note)
{
    $ip = get_client_ip();
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $now = date('Y-m-d H:i:s');
    $adminId = trim((string)$adminId);
    // Support both legacy and new tbladmin_log schemas.
    $hasEventType = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM tbladmin_log LIKE 'event_type'");
    if ($colCheck) {
        $hasEventType = ($colCheck->num_rows > 0);
        $colCheck->free();
    }

    if ($hasEventType) {
        $stmt = $conn->prepare("INSERT INTO tbladmin_log (admin_id, event_type, status, ip_address, user_agent, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $adminId, $eventType, $status, $ip, $ua, $note, $now);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    // Legacy format from original schema in Rtel.sql
    $legacyId = 'L' . substr((string)time(), -5) . substr((string)random_int(1000, 9999), -4);
    $entityType = 'auth';
    $entityId = '-';
    $activityDate = date('Y-m-d');
    $legacyAction = $eventType . ':' . $status;
    $legacyNote = substr($note, 0, 50);
    if ($legacyNote !== '') {
        $legacyAction = substr($legacyAction . ' ' . $legacyNote, 0, 50);
    }

    $stmt = $conn->prepare("INSERT INTO tbladmin_log (adminlog_id, admin_id, action_type, entity_type, entity_id, activity_date) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $legacyId, $adminId, $legacyAction, $entityType, $entityId, $activityDate);
        $stmt->execute();
        $stmt->close();
    }
}

$error = '';
$success = (string)($_SESSION['mis_login_success'] ?? '');
unset($_SESSION['mis_login_success']);
$formUsername = trim((string)($_POST['username'] ?? ($_COOKIE['admin_username'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnlogin'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    $formUsername = $username;

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_\-@.+]{3,100}$/', $username)) {
        $error = 'Invalid username/email format.';
    } else {
        $stmt = $conn->prepare("SELECT admin_id, email, password, COALESCE(type, 'admin') AS type, COALESCE(role_label, 'Admin') AS role_label, COALESCE(status, 1) AS status FROM tbladmin WHERE admin_id = ? OR email = ? LIMIT 1");
        if (!$stmt) {
            $error = 'Database error while logging in.';
        } else {
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $error = 'Invalid username or password.';
                log_admin_activity($conn, $username, 'login', 'failed', 'Unknown username');
            } elseif ((int)($row['status'] ?? 1) !== 1) {
                $error = 'This account is deactivated. Contact admin.';
                log_admin_activity($conn, $username, 'login', 'failed', 'Account deactivated');
            } elseif (!password_verify($password, (string)$row['password'])) {
                $error = 'Invalid username or password.';
                log_admin_activity($conn, $username, 'login', 'failed', 'Password mismatch');
            } else {
                // Store minimal session identity for admin area authorization checks.
                $_SESSION['admin_id'] = (string)$row['admin_id'];
                $_SESSION['admin_email'] = (string)($row['email'] ?? '');
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_role_label'] = trim((string)($row['role_label'] ?? 'Admin'));
                rtel_refresh_admin_permission_session($conn, (string)$row['admin_id'], (string)($row['type'] ?? 'admin'));

                // Remember only username (never password).
                if (isset($_POST['remember'])) {
                    setcookie('admin_username', $username, time() + (86400 * 30), '/');
                } else {
                    setcookie('admin_username', '', time() - 3600, '/');
                }

                log_admin_activity($conn, $username, 'login', 'success', 'Admin login successful');
                header('Location: index.php');
                exit;
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
    <title>Login - R-tel Admin Dashboard</title>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem("rtel_theme_mode");
                var theme = (saved === "dark" || saved === "light")
                    ? saved
                    : ((window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light");
                document.documentElement.setAttribute("data-theme", theme);
            } catch (e) {}
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="icon" href="<?php echo htmlspecialchars(rtel_image_url('logo.jpg'), ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg">

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Nunito", sans-serif;
            background: radial-gradient(circle at 15% 20%, #4b5563 0%, #1f2937 38%, #0f172a 100%);
            padding: 20px;
        }
        .auth-shell {
            width: 100%;
            max-width: 980px;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.35);
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .auth-side {
            padding: 34px 30px;
            background: linear-gradient(140deg, #0f172a 0%, #111827 50%, #1f2937 100%);
            color: #f8fafc;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-side h3 {
            font-weight: 800;
            margin-bottom: 10px;
        }
        .auth-side p {
            opacity: .9;
            margin-bottom: 0;
            line-height: 1.6;
        }
        .auth-card {
            padding: 34px 30px;
        }
        .auth-logo {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e2e8f0;
            display: block;
            margin: 0 auto 14px;
        }
        .auth-title {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 2px;
            color: #0f172a;
        }
        .auth-subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 22px;
            font-size: 14px;
        }
        .input-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #334155;
        }
        .auth-input {
            border-radius: 10px;
            border: 1px solid #d5dde8;
            height: 46px;
            padding-left: 42px;
            font-size: 14px;
        }
        .auth-input:focus {
            border-color: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.12);
        }
        .form-control-icon {
            left: 12px;
            color: #64748b;
            pointer-events: none;
        }
        .password-wrap { position: relative; }
        .password-toggle {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            cursor: pointer;
            padding: 5px 9px;
            border-radius: 8px;
            font-weight: 700;
            z-index: 6;
        }
        .password-wrap input { padding-right: 66px; }
        .auth-actions {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .auth-links {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 13px;
        }
        .auth-links a {
            color: #334155;
            text-decoration: none;
            font-weight: 700;
        }
        .auth-links a:hover { text-decoration: underline; }
        .btn-login {
            height: 46px;
            border-radius: 10px;
            font-weight: 800;
            letter-spacing: .4px;
        }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; }
            .auth-side { display: none; }
            .auth-card { padding: 28px 22px; }
        }
    </style>
</head>

<body>
    <div class="auth-shell">
        <div class="auth-side">
            <h3>R-TEL Admin Portal</h3>
            <p>Welcome back. Sign in to manage products, orders, customers, promotions, and system settings in one place.</p>
        </div>
        <div class="auth-card">
            <img class="auth-logo" src="<?php echo htmlspecialchars(rtel_image_url('logo.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
            <h2 class="auth-title">Log in</h2>
            <p class="auth-subtitle">Use your admin account to continue</p>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="input-label">Email or Username</label>
                    <div class="form-group position-relative has-icon-left mb-0">
                        <input type="text" class="form-control auth-input" placeholder="Enter your email or username" name="username" required autofocus value="<?php echo h($formUsername); ?>">
                        <div class="form-control-icon">
                            <i class="bi bi-person"></i>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="input-label">Password</label>
                    <div class="form-group position-relative has-icon-left mb-0">
                        <div class="password-wrap">
                            <input type="password" id="loginPassword" class="form-control auth-input" placeholder="Enter your password" name="password" required>
                            <button type="button" class="password-toggle" data-toggle-target="loginPassword">Show</button>
                        </div>
                        <div class="form-control-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                    </div>
                </div>

                <div class="auth-actions">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" value="" id="rememberMe" name="remember" <?php echo isset($_COOKIE['admin_username']) ? 'checked' : ''; ?>>
                        <label class="form-check-label text-gray-600" for="rememberMe">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="small fw-bold text-dark">Forgot password?</a>
                </div>

                <input name="btnlogin" id="login" class="btn btn-dark btn-block shadow-lg mt-3 btn-login" type="submit" value="LOGIN">
            </form>
            <div class="auth-links">
                <a href="index.php">Back</a>
                <a href="../web/index.php">Go to website</a>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var toggles = document.querySelectorAll('.password-toggle');
            for (var i = 0; i < toggles.length; i++) {
                toggles[i].addEventListener('click', function () {
                    var targetId = this.getAttribute('data-toggle-target');
                    var input = document.getElementById(targetId);
                    if (!input) return;
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    this.textContent = show ? 'Hide' : 'Show';
                });
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($error !== ''): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Login failed',
            text: <?php echo json_encode((string)$error); ?>,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2200,
            timerProgressBar: true
        });
    </script>
    <?php elseif ($success !== ''): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: <?php echo json_encode((string)$success); ?>,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true
        });
    </script>
    <?php endif; ?>
    <script src="assets/js/theme-toggle.js"></script>
</body>

</html>

<?php mysqli_close($conn); ?>