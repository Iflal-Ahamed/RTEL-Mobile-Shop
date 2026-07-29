<?php
session_start();
include "connection.php";
require_once __DIR__ . "/../web/mail/mail_helper.php";

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ensure_admin_reset_table($conn)
{
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_password_reset (
        reset_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(250) NOT NULL,
        account_type VARCHAR(20) NOT NULL DEFAULT 'customer',
        account_id VARCHAR(50) NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_email_type_used (email, account_type, used_at),
        INDEX idx_expires (expires_at)
    )");
    @mysqli_query($conn, "ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS account_type VARCHAR(20) NOT NULL DEFAULT 'customer'");
    @mysqli_query($conn, "ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS account_id VARCHAR(50) NULL");
    @mysqli_query($conn, "ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) NULL");
}

function send_admin_reset_otp($toEmail, $adminName, $otp)
{
    $subject = "R-TEL Admin Password Reset OTP";
    $html = "<h3>Admin Password Reset Request</h3>
    <p>Hello <strong>" . h($adminName) . "</strong>,</p>
    <p>Your OTP for admin password reset is:</p>
    <p style='font-size:22px;letter-spacing:4px;'><strong>" . h($otp) . "</strong></p>
    <p>This OTP is valid for 10 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>";
    return rtel_send_html_email($toEmail, $subject, $html);
}

ensure_admin_reset_table($conn);
@mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");

// Fresh visit from login page should always start from email request step.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['mis_reset_step'] = 'request';
    unset($_SESSION['mis_reset_admin_id'], $_SESSION['mis_reset_email'], $_SESSION['mis_reset_verified']);
}

$step = (string)($_SESSION['mis_reset_step'] ?? 'request');
$error = '';
$success = '';
$emailInput = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request') {
        $emailInput = trim((string)($_POST['email'] ?? ''));

        if ($emailInput === '') {
            $error = "Email is required.";
        } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $error = "Enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT admin_id, name, email, COALESCE(status, 1) AS status FROM tbladmin WHERE email = ? LIMIT 1");
            if (!$stmt) {
                $error = "Database error. Please try again.";
            } else {
                $stmt->bind_param("s", $emailInput);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$admin) {
                    $error = "No admin account matched this email.";
                } elseif ((int)($admin['status'] ?? 1) !== 1) {
                    $error = "This account is deactivated. Password reset is not allowed.";
                } elseif (!rtel_mail_is_configured()) {
                    $error = "Mail server is not configured yet. Add valid SMTP settings first.";
                } else {
                    $adminId = (string)$admin['admin_id'];
                    $adminName = trim((string)($admin['name'] ?? 'Admin'));
                    if ($adminName === '') {
                        $adminName = 'Admin';
                    }
                    $otp = (string)random_int(100000, 999999);
                    $otpHash = password_hash($otp, PASSWORD_BCRYPT);
                    $expiresAt = date('Y-m-d H:i:s', time() + 600);
                    $createdAt = date('Y-m-d H:i:s');
                    $accountType = 'admin';

                    $inv = $conn->prepare("UPDATE tbl_password_reset SET used_at = ? WHERE account_type = 'admin' AND account_id = ? AND email = ? AND used_at IS NULL");
                    if ($inv) {
                        $now = date('Y-m-d H:i:s');
                        $inv->bind_param("sss", $now, $adminId, $emailInput);
                        $inv->execute();
                        $inv->close();
                    }

                    $ins = $conn->prepare("INSERT INTO tbl_password_reset (email, account_type, account_id, otp_hash, expires_at, used_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)");
                    if ($ins) {
                        $ins->bind_param("ssssss", $emailInput, $accountType, $adminId, $otpHash, $expiresAt, $createdAt);
                        $okInsert = $ins->execute();
                        $ins->close();
                    } else {
                        $okInsert = false;
                    }

                    if (!$okInsert) {
                        $error = "Could not create reset request. Try again.";
                    } elseif (!send_admin_reset_otp($emailInput, $adminName, $otp)) {
                        $error = "Could not send OTP email. " . h((string)rtel_get_mail_last_error());
                    } else {
                        $_SESSION['mis_reset_admin_id'] = $adminId;
                        $_SESSION['mis_reset_email'] = $emailInput;
                        $_SESSION['mis_reset_step'] = 'verify';
                        $step = 'verify';
                        $success = "OTP sent to your email. Please verify.";
                    }
                }
            }
        }
    } elseif ($action === 'verify') {
        $inputOtp = trim((string)($_POST['otp'] ?? ''));
        $adminId = (string)($_SESSION['mis_reset_admin_id'] ?? '');
        $email = (string)($_SESSION['mis_reset_email'] ?? '');

        if ($adminId === '' || $email === '') {
            $error = "Session expired. Start again.";
            $step = 'request';
            $_SESSION['mis_reset_step'] = 'request';
        } elseif (!preg_match('/^\d{6}$/', $inputOtp)) {
            $error = "Enter a valid 6-digit OTP.";
            $step = 'verify';
        } else {
            $statusStmt = $conn->prepare("SELECT COALESCE(status, 1) AS status FROM tbladmin WHERE admin_id = ? AND email = ? LIMIT 1");
            $accountActive = false;
            if ($statusStmt) {
                $statusStmt->bind_param("ss", $adminId, $email);
                $statusStmt->execute();
                $acc = $statusStmt->get_result()->fetch_assoc();
                $statusStmt->close();
                $accountActive = ((int)($acc['status'] ?? 0) === 1);
            }
            if (!$accountActive) {
                $error = "This account is deactivated. Password reset is not allowed.";
                $step = 'request';
                $_SESSION['mis_reset_step'] = 'request';
            } else {
                $stmt = $conn->prepare("SELECT reset_id, otp_hash, expires_at, used_at FROM tbl_password_reset WHERE account_type = 'admin' AND account_id = ? AND email = ? ORDER BY reset_id DESC LIMIT 1");
                $stmt->bind_param("ss", $adminId, $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || !password_verify($inputOtp, (string)($row['otp_hash'] ?? ''))) {
                    $error = "Invalid OTP.";
                    $step = 'verify';
                } elseif (!empty($row['used_at'])) {
                    $error = "This OTP is already used.";
                    $step = 'verify';
                } elseif (strtotime((string)$row['expires_at']) < time()) {
                    $error = "OTP expired. Request a new one.";
                    $step = 'request';
                    $_SESSION['mis_reset_step'] = 'request';
                } else {
                    $_SESSION['mis_reset_verified'] = true;
                    $_SESSION['mis_reset_step'] = 'reset';
                    $step = 'reset';
                    $success = "OTP verified. Set your new password.";
                }
            }
        }
    } elseif ($action === 'reset') {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $adminId = (string)($_SESSION['mis_reset_admin_id'] ?? '');
        $email = (string)($_SESSION['mis_reset_email'] ?? '');
        $verified = !empty($_SESSION['mis_reset_verified']);

        if (!$verified || $adminId === '' || $email === '') {
            $error = "Session expired. Start again.";
            $step = 'request';
            $_SESSION['mis_reset_step'] = 'request';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 64) {
            $error = "Password must be 8 to 64 characters.";
            $step = 'reset';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,64}$/', $newPassword)) {
            $error = "Password must include at least one letter and one number.";
            $step = 'reset';
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New password and confirm password do not match.";
            $step = 'reset';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE tbladmin SET password = ? WHERE admin_id = ? AND email = ? AND COALESCE(status, 1) = 1 LIMIT 1");
            $up->bind_param("sss", $hash, $adminId, $email);
            $okUp = $up->execute();
            $affected = (int)$up->affected_rows;
            $up->close();

            if (!$okUp || $affected < 1) {
                $error = "Could not update password. Account may be deactivated.";
                $step = 'reset';
            } else {
                $mark = $conn->prepare("UPDATE tbl_password_reset SET used_at = ? WHERE account_type = 'admin' AND account_id = ? AND email = ? AND used_at IS NULL");
                $now = date('Y-m-d H:i:s');
                if ($mark) {
                    $mark->bind_param("sss", $now, $adminId, $email);
                    $mark->execute();
                    $mark->close();
                }

                unset($_SESSION['mis_reset_admin_id'], $_SESSION['mis_reset_email'], $_SESSION['mis_reset_verified'], $_SESSION['mis_reset_step']);
                $_SESSION['mis_login_success'] = "Password reset successful. Please login.";
                header("Location: login.php");
                exit;
            }
        }
    }
}

if ($step === '') {
    $step = 'request';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - R-tel Admin</title>
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
    <link rel="icon" href="../images/logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Nunito", sans-serif;
            background: radial-gradient(circle at 20% 20%, #475569 0%, #1f2937 42%, #0f172a 100%);
            padding: 20px;
        }
        .auth-shell {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.35);
        }
        .auth-side {
            background: linear-gradient(145deg, #0f172a 0%, #1f2937 100%);
            color: #e2e8f0;
            padding: 34px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-side h4 {
            margin-bottom: 10px;
            font-weight: 800;
        }
        .auth-side p {
            margin-bottom: 0;
            opacity: .92;
            line-height: 1.6;
        }
        .step-list {
            margin-top: 18px;
            padding-left: 16px;
            margin-bottom: 0;
            font-size: 14px;
        }
        .step-list li { margin-bottom: 6px; }
        .auth-card {
            padding: 32px 30px;
        }
        .auth-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .auth-title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }
        .step-badge {
            background: #e2e8f0;
            color: #1e293b;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .auth-sub {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 18px;
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
            font-size: 14px;
        }
        .auth-input:focus {
            border-color: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.12);
        }
        .otp-input {
            text-align: center;
            letter-spacing: 9px;
            font-weight: 800;
            font-size: 22px;
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
        }
        .password-wrap input { padding-right: 66px; }
        .strength-text { font-size: 13px; margin-top: 6px; }
        .strength-weak { color: #dc2626; }
        .strength-medium { color: #ea580c; }
        .strength-strong { color: #059669; }
        .btn-main {
            height: 46px;
            border-radius: 10px;
            font-weight: 800;
            letter-spacing: .3px;
        }
        .back-link {
            display: inline-block;
            margin-top: 14px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; }
            .auth-side { display: none; }
            .auth-card { padding: 26px 20px; }
        }
    </style>
</head>
<body>
<?php
$stepNumber = 1;
if ($step === 'verify') $stepNumber = 2;
if ($step === 'reset') $stepNumber = 3;
?>
<div class="auth-shell">
    <div class="auth-side">
        <h4>Secure Password Recovery</h4>
        <p>Reset your MIS admin password in three quick steps with OTP verification.</p>
        <ol class="step-list">
            <li>Enter your registered admin email.</li>
            <li>Verify the 6-digit OTP sent to your inbox.</li>
            <li>Set a new secure password and sign in.</li>
        </ol>
    </div>
    <div class="auth-card">
        <div class="auth-top">
            <h3 class="auth-title">Forgot Password</h3>
            <span class="step-badge">Step <?php echo (int)$stepNumber; ?> of 3</span>
        </div>
        <p class="auth-sub">Follow the steps below to regain access to your admin account.</p>

        <?php if ($step === 'request'): ?>
            <form method="post">
                <input type="hidden" name="action" value="request">
                <div class="mb-3">
                    <label class="input-label">Admin Email</label>
                    <input type="email" class="form-control auth-input" name="email" required value="<?php echo h($emailInput); ?>" placeholder="Enter your registered email">
                </div>
                <button type="submit" class="btn btn-dark w-100 btn-main">Send OTP</button>
            </form>
        <?php elseif ($step === 'verify'): ?>
            <form method="post">
                <input type="hidden" name="action" value="verify">
                <div class="mb-3">
                    <label class="input-label">6-digit OTP</label>
                    <input type="text" class="form-control auth-input otp-input" name="otp" required maxlength="6" pattern="\d{6}" title="Enter 6 digit OTP" placeholder="000000" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <button type="submit" class="btn btn-dark w-100 btn-main">Verify OTP</button>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="reset">
                <div class="mb-3">
                    <label class="input-label">New Password</label>
                    <div class="password-wrap">
                        <input type="password" id="newPassword" class="form-control auth-input" name="new_password" required minlength="8" maxlength="64" pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}" title="8-64 chars, include letters and numbers" placeholder="Create a new password">
                        <button type="button" class="password-toggle" data-toggle-target="newPassword">Show</button>
                    </div>
                    <div id="passwordStrengthText" class="strength-text">Use at least 8 chars with letters and numbers.</div>
                </div>
                <div class="mb-3">
                    <label class="input-label">Confirm Password</label>
                    <div class="password-wrap">
                        <input type="password" id="confirmPassword" class="form-control auth-input" name="confirm_password" required minlength="8" maxlength="64" placeholder="Re-enter your new password">
                        <button type="button" class="password-toggle" data-toggle-target="confirmPassword">Show</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-dark w-100 btn-main">Reset Password</button>
            </form>
        <?php endif; ?>

        <a class="back-link" href="login.php">Back to login</a>
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

        var passInput = document.getElementById('newPassword');
        var strengthText = document.getElementById('passwordStrengthText');
        if (!passInput || !strengthText) return;

        function scorePassword(value) {
            var score = 0;
            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[a-z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;
            return score;
        }

        function renderStrength() {
            var value = passInput.value || '';
            var score = scorePassword(value);
            strengthText.className = 'strength-text';

            if (value.length === 0) {
                strengthText.textContent = 'Use at least 8 chars with letters and numbers.';
                return;
            }
            if (score <= 2) {
                strengthText.textContent = 'Password strength: Weak';
                strengthText.classList.add('strength-weak');
            } else if (score <= 4) {
                strengthText.textContent = 'Password strength: Medium';
                strengthText.classList.add('strength-medium');
            } else {
                strengthText.textContent = 'Password strength: Strong';
                strengthText.classList.add('strength-strong');
            }
        }

        passInput.addEventListener('input', renderStrength);
        renderStrength();
    })();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($error !== ''): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Action failed',
    text: <?php echo json_encode((string)$error); ?>,
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2400,
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
    timer: 2000,
    timerProgressBar: true
});
</script>
<?php endif; ?>
<script src="assets/js/theme-toggle.js"></script>
</body>
</html>
<?php mysqli_close($conn); ?>
