<?php
/**
 * Password reset: email ? OTP (mailed) ? new password ? redirect to login.
 */
include "config.php";
include "connection.php";
// Mail module imports are grouped under /web/mail
require_once "mail/mail_notifications.php";

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

function ensure_password_reset_table(mysqli $conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_password_reset (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Backward-compatible upgrades for older table shape.
    @$conn->query("ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS account_type VARCHAR(20) NOT NULL DEFAULT 'customer'");
    @$conn->query("ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS account_id VARCHAR(50) NULL");
    @$conn->query("ALTER TABLE tbl_password_reset ADD COLUMN IF NOT EXISTS used_at DATETIME NULL");
    @$conn->query("UPDATE tbl_password_reset SET used_at = created_at WHERE used_at IS NULL AND used = 1");
}

ensure_password_reset_table($conn);

$error = "";
$info = "";
$fieldErrors = [];
$step = $_SESSION["fp_step"] ?? "email";

if (isset($_GET["start"])) {
    unset($_SESSION["fp_step"], $_SESSION["fp_email"], $_SESSION["fp_reset_id"], $_SESSION["fp_otp_ok"]);
    $step = "email";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string)($_POST["action"] ?? ""));

    if ($action === "request_otp") {
        $email = strtolower(trim((string)($_POST["email"] ?? "")));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $fieldErrors["email"] = $error;
        } elseif (!rtel_mail_is_configured()) {
            $error = "Password reset email is not configured. Set MAIL_PASSWORD or create mail/mail_config.local.php and try again.";
        } else {
            $st = $conn->prepare("SELECT cus_id FROM tblcustomer WHERE LOWER(email) = ? LIMIT 1");
            $st->bind_param("s", $email);
            $st->execute();
            $exists = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$exists) {
                $error = "No account found with that email.";
            } else {
                $inv = $conn->prepare("UPDATE tbl_password_reset SET used_at = NOW() WHERE email = ? AND account_type = 'customer' AND used_at IS NULL");
                if ($inv) {
                    $inv->bind_param("s", $email);
                    $inv->execute();
                    $inv->close();
                }

                $otp = (string)random_int(100000, 999999);
                $hash = password_hash($otp, PASSWORD_BCRYPT);
                $exp = date("Y-m-d H:i:s", time() + 900);
                $now = date("Y-m-d H:i:s");
                $accountType = 'customer';
                $accountId = (string)($exists['cus_id'] ?? '');
                $ins = $conn->prepare("INSERT INTO tbl_password_reset (email, account_type, account_id, otp_hash, expires_at, used_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)");
                if ($ins) {
                    $ins->bind_param("ssssss", $email, $accountType, $accountId, $hash, $exp, $now);
                    $ins->execute();
                    $rid = (int)$ins->insert_id;
                    $ins->close();
                    if (rtel_notify_password_reset_otp($email, $otp)) {
                        $_SESSION["fp_step"] = "otp";
                        $_SESSION["fp_email"] = $email;
                        $_SESSION["fp_reset_id"] = $rid;
                        unset($_SESSION["fp_otp_ok"]);
                        header("Location: forgot_password.php");
                        exit;
                    }
                    $error = "Could not send email. " . h(rtel_get_mail_last_error());
                } else {
                    $error = "Database error.";
                }
            }
        }
    } elseif ($action === "verify_otp") {
        $otp = trim((string)($_POST["otp"] ?? ""));
        $rid = (int)($_SESSION["fp_reset_id"] ?? 0);
        $email = (string)($_SESSION["fp_email"] ?? "");
        if ($rid < 1 || $email === "" || !preg_match('/^\d{6}$/', $otp)) {
            $error = "Invalid session or code. Start again.";
            $fieldErrors["otp"] = "Enter a valid 6-digit code.";
            unset($_SESSION["fp_step"], $_SESSION["fp_email"], $_SESSION["fp_reset_id"]);
            $step = "email";
        } else {
            $q = $conn->prepare("SELECT otp_hash, expires_at, used_at FROM tbl_password_reset WHERE reset_id = ? AND email = ? AND account_type = 'customer' LIMIT 1");
            $q->bind_param("is", $rid, $email);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
            if (!$row || !empty($row["used_at"])) {
                $error = "This reset link is no longer valid. Request a new code.";
            } elseif (strtotime((string)$row["expires_at"]) < time()) {
                $error = "Code expired. Request a new one.";
                $fieldErrors["otp"] = "Code expired. Request a new one.";
            } elseif (!password_verify($otp, (string)$row["otp_hash"])) {
                $error = "Incorrect code.";
                $fieldErrors["otp"] = "Incorrect code.";
            } else {
                $_SESSION["fp_step"] = "password";
                $_SESSION["fp_otp_ok"] = 1;
                header("Location: forgot_password.php");
                exit;
            }
        }
    } elseif ($action === "set_password") {
        $email = (string)($_SESSION["fp_email"] ?? "");
        $rid = (int)($_SESSION["fp_reset_id"] ?? 0);
        $ok = (int)($_SESSION["fp_otp_ok"] ?? 0) === 1;
        $pw = trim((string)($_POST["password"] ?? ""));
        $pw2 = trim((string)($_POST["confirm_password"] ?? ""));
        if (!$ok || $email === "" || $rid < 1) {
            $error = "Session expired. Start again from email.";
            unset($_SESSION["fp_step"], $_SESSION["fp_email"], $_SESSION["fp_reset_id"], $_SESSION["fp_otp_ok"]);
            $step = "email";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pw)) {
            $error = "Password must be 8+ chars with uppercase, lowercase, and a number.";
            $fieldErrors["password"] = $error;
        } elseif ($pw !== $pw2) {
            $error = "Password and confirm password do not match.";
            $fieldErrors["confirm_password"] = $error;
        } else {
            $q = $conn->prepare("SELECT used_at, expires_at FROM tbl_password_reset WHERE reset_id = ? AND email = ? AND account_type = 'customer' LIMIT 1");
            $q->bind_param("is", $rid, $email);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
            if (!$row || !empty($row["used_at"]) || strtotime((string)$row["expires_at"]) < time()) {
                $error = "Reset session expired. Start again.";
                unset($_SESSION["fp_step"], $_SESSION["fp_email"], $_SESSION["fp_reset_id"], $_SESSION["fp_otp_ok"]);
                $step = "email";
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $up = $conn->prepare("UPDATE tblcustomer SET password = ?, status = '1' WHERE LOWER(TRIM(email)) = ?");
                if ($up) {
                    $up->bind_param("ss", $hash, $email);
                    $up->execute();
                    $up->close();
                }
                $mark = $conn->prepare("UPDATE tbl_password_reset SET used_at = NOW() WHERE reset_id = ?");
                $mark->bind_param("i", $rid);
                $mark->execute();
                $mark->close();

                unset($_SESSION["fp_step"], $_SESSION["fp_email"], $_SESSION["fp_reset_id"], $_SESSION["fp_otp_ok"]);
                header("Location: login.php?reset=1");
                exit;
            }
        }
    }
}

$step = $_SESSION["fp_step"] ?? "email";

require "header.php";

$bannerTitle = "Forgot Password";
$bannerSubtitle = "Request Reset Code";
if ($step === "otp") {
    $bannerSubtitle = "OTP Verification";
} elseif ($step === "password") {
    $bannerSubtitle = "Create New Password";
}
?>

<style>
  .forgot-password-wrap {
    position: relative;
  }
  .forgot-password-wrap .form-control {
    padding-right: 44px;
  }
  .forgot-password-toggle {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border: 1px solid #e3e3e3;
    border-radius: 50%;
    background: #fff;
    color: #555;
    font-size: 14px;
    line-height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    z-index: 3;
  }
  .forgot-password-toggle:hover {
    color: #111;
    border-color: #cfcfcf;
    background: #f9f9f9;
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs">
          <span class="mr-2"><a href="index.php">Home</a></span>
          <span class="mr-2"><a href="login.php">Login</a></span>
          <span><?= h($bannerTitle) ?></span>
        </p>
        <h1 class="mb-0 bread"><?= h($bannerSubtitle) ?></h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section ftco-no-pb ftco-no-pt bg-light py-5">
  <div class="container" style="max-width: 520px;">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h4 class="mb-3">Forgot password</h4>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
          <div class="alert alert-info"><?= h($info) ?></div>
        <?php endif; ?>

        <?php if ($step === "email"): ?>
          <p class="text-muted small">Enter the email on your account. We will send a 6-digit code.</p>
          <form method="post" action="forgot_password.php">
            <input type="hidden" name="action" value="request_otp">
            <div class="form-group mb-3">
              <label for="email">Email</label>
              <input type="email" class="form-control<?= isset($fieldErrors["email"]) ? " is-invalid" : "" ?>" id="email" name="email" required autocomplete="email">
              <?php if (isset($fieldErrors["email"])): ?>
                <div class="invalid-feedback d-block"><?= h($fieldErrors["email"]) ?></div>
              <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-dark btn-block">Send code</button>
          </form>
        <?php elseif ($step === "otp"): ?>
          <p class="text-muted small">We sent a code to <strong><?= h($_SESSION["fp_email"] ?? "") ?></strong>.</p>
          <form method="post" action="forgot_password.php">
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group mb-3">
              <label for="otp">6-digit code</label>
              <input type="text" class="form-control<?= isset($fieldErrors["otp"]) ? " is-invalid" : "" ?>" id="otp" name="otp" pattern="\d{6}" maxlength="6" required inputmode="numeric" autocomplete="one-time-code">
              <?php if (isset($fieldErrors["otp"])): ?>
                <div class="invalid-feedback d-block"><?= h($fieldErrors["otp"]) ?></div>
              <?php endif; ?>
              <small id="otpMsg" class="form-text"></small>
            </div>
            <button type="submit" class="btn btn-dark btn-block">Verify</button>
          </form>
          <p class="mt-3 mb-0 small"><a href="forgot_password.php?start=1">Use a different email</a></p>
        <?php elseif ($step === "password"): ?>
          <p class="text-muted small">Choose a new password for <strong><?= h($_SESSION["fp_email"] ?? "") ?></strong>.</p>
          <form method="post" action="forgot_password.php">
            <input type="hidden" name="action" value="set_password">
            <div class="form-group mb-2">
              <label for="password">New password</label>
              <div class="forgot-password-wrap">
                <input type="password" class="form-control<?= isset($fieldErrors["password"]) ? " is-invalid" : "" ?>" id="password" name="password" required autocomplete="new-password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Password must be 8+ chars with uppercase, lowercase, and a number.">
                <button type="button" class="forgot-password-toggle" data-target="password" aria-label="Show password" title="Show password">&#128065;</button>
              </div>
              <?php if (isset($fieldErrors["password"])): ?>
                <div class="invalid-feedback d-block"><?= h($fieldErrors["password"]) ?></div>
              <?php endif; ?>
              <small id="passwordMsg" class="form-text"></small>
            </div>
            <div class="form-group mb-3">
              <label for="confirm_password">Confirm password</label>
              <div class="forgot-password-wrap">
                <input type="password" class="form-control<?= isset($fieldErrors["confirm_password"]) ? " is-invalid" : "" ?>" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Password must be 8+ chars with uppercase, lowercase, and a number.">
                <button type="button" class="forgot-password-toggle" data-target="confirm_password" aria-label="Show password" title="Show password">&#128065;</button>
              </div>
              <?php if (isset($fieldErrors["confirm_password"])): ?>
                <div class="invalid-feedback d-block"><?= h($fieldErrors["confirm_password"]) ?></div>
              <?php endif; ?>
              <small id="confirmMsg" class="form-text"></small>
            </div>
            <button type="submit" class="btn btn-dark btn-block">Update password</button>
          </form>
        <?php endif; ?>

        <p class="text-center mt-4 mb-0 small"><a href="login.php">Back to login</a></p>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  var otpInput = document.getElementById("otp");
  var otpMsg = document.getElementById("otpMsg");
  if (otpInput && otpMsg) {
    otpInput.addEventListener("input", function () {
      this.value = this.value.replace(/\D/g, "").slice(0, 6);
      if (!this.value) {
        otpMsg.textContent = "";
      } else if (this.value.length < 6) {
        otpMsg.textContent = this.value.length + " / 6 digits";
        otpMsg.style.color = "#6c757d";
      } else {
        otpMsg.textContent = "Code looks good";
        otpMsg.style.color = "#28a745";
      }
    });
  }

  var passwordInput = document.getElementById("password");
  var passwordMsg = document.getElementById("passwordMsg");
  var confirmInput = document.getElementById("confirm_password");
  var confirmMsg = document.getElementById("confirmMsg");

  function checkPasswordStrength() {
    if (!passwordInput || !passwordMsg) return false;
    var value = passwordInput.value;
    var strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value);
    if (!value) {
      passwordMsg.textContent = "Use 8+ chars with uppercase, lowercase, and a number.";
      passwordMsg.style.color = "#6c757d";
    } else if (strong) {
      passwordMsg.textContent = "Strong password";
      passwordMsg.style.color = "#28a745";
    } else {
      passwordMsg.textContent = "Need uppercase, lowercase, number, and 8+ chars.";
      passwordMsg.style.color = "#dc3545";
    }
    return strong;
  }

  function checkConfirmPassword() {
    if (!passwordInput || !confirmInput || !confirmMsg) return true;
    if (!confirmInput.value) {
      confirmMsg.textContent = "";
      return false;
    }
    if (passwordInput.value === confirmInput.value) {
      confirmMsg.textContent = "Passwords match";
      confirmMsg.style.color = "#28a745";
      return true;
    }
    confirmMsg.textContent = "Passwords do not match";
    confirmMsg.style.color = "#dc3545";
    return false;
  }

  if (passwordInput) passwordInput.addEventListener("input", function () { checkPasswordStrength(); checkConfirmPassword(); });
  if (confirmInput) confirmInput.addEventListener("input", checkConfirmPassword);
  document.querySelectorAll(".forgot-password-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var targetId = btn.getAttribute("data-target");
      var input = targetId ? document.getElementById(targetId) : null;
      if (!input) return;
      var isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";
      btn.innerHTML = isHidden ? "&#128064;" : "&#128065;";
      btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
      btn.setAttribute("title", isHidden ? "Hide password" : "Show password");
    });
  });

  var passwordForm = document.querySelector('form input[name="action"][value="set_password"]');
  if (passwordForm) {
    var form = passwordForm.closest("form");
    form.addEventListener("submit", function (e) {
      var strong = checkPasswordStrength();
      var matched = checkConfirmPassword();
      if (!strong || !matched) {
        e.preventDefault();
      }
    });
  }
});
</script>

<?php require "footer.php"; ?>
