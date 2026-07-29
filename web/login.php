<?php
session_start();
include 'config.php';
include "connection.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_ajax_request() {
    $xrw = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($xrw === 'xmlhttprequest') return true;
    return (string)($_POST['ajax'] ?? '') === '1';
}

function login_json($success, $message, $redirect = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'redirect' => (string)$redirect
    ]);
    exit();
}

$error = "";
$successMsg = "";
if (isset($_GET["reset"]) && $_GET["reset"] === "1") {
    $successMsg = "Your password was updated. You can sign in now.";
}
$formData = ["email" => ""];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $isAjax = is_ajax_request();
    $formData["email"] = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password === "") {
        $error = "Please enter your password.";
    } else {
        $stmt = $conn->prepare("SELECT cus_id, name, email, password, status FROM tblcustomer WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $error = "DB error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $formData["email"]);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            $blockedRaw = strtolower(trim((string)($user["status"] ?? "")));
            $isBlocked = in_array($blockedRaw, ["1", "blocked", "block"], true);
            if (!$user || !password_verify($password, $user["password"])) {
                $error = "Invalid email or password.";
            } elseif ($isBlocked) {
                $error = "Your account is blocked. Please contact support.";
            } else {
                $_SESSION["user_id"] = $user["cus_id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                if ($isAjax) {
                    login_json(true, "Login successful.", "index.php");
                }
                header("Location: index.php");
                exit;
            }
        }
    }
    if ($isAjax) {
        login_json(false, $error !== "" ? $error : "Unable to login right now.");
    }
}

require "header.php";
?>

<style>
  .login-layout {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
  }
  .login-image-wrap {
    background: #f6f6f6;
    display: flex;
    min-height: 640px;
  }
  .login-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .login-card {
    border: 0;
    background: #fff;
  }
  .login-card .card-body {
    padding: 2rem;
  }
  .login-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 6px;
  }
  .login-subtitle {
    color: #6c757d;
    margin-bottom: 1.2rem;
  }
  .login-form .form-group {
    margin-bottom: 1rem;
  }
  .login-form label {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
  }
  .login-form .form-control {
    height: 48px;
    border: 1px solid #d6dbe1;
    border-radius: 8px;
    box-shadow: none;
  }
  .login-form .form-control:focus {
    border-color: #111;
    box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.08);
  }
  .login-actions .btn {
    width: 100%;
    height: 50px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
  }
  .login-actions .btn-light {
    border: 1px solid #d5d5d5;
    background: #fff;
  }
  .login-password-wrap {
    position: relative;
  }
  .login-password-wrap .form-control {
    padding-right: 44px;
  }
  .login-password-toggle {
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
  .login-password-toggle:hover {
    color: #111;
    border-color: #cfcfcf;
    background: #f9f9f9;
  }
  .remember-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 2px 0 8px;
  }
  .remember-wrap label {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
  }
  @media (max-width: 991px) {
    .login-image-wrap img {
      min-height: 280px;
      max-height: 380px;
      object-fit: contain;
    }
    .login-card .card-body {
      padding: 1.4rem 1.1rem;
    }
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Login</span></p>
        <h1 class="mb-0 bread">Login Form</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section ftco-no-pb ftco-no-pt bg-light py-4">
  <div class="container">
    <div class="login-layout">
      <div class="row no-gutters align-items-stretch">
        <div class="col-lg-5 login-image-wrap">
          <img src="../images/login.webp" alt="Login">
        </div>
        <div class="col-lg-7 d-flex ftco-animate">
          <div class="card login-card w-100">
            <div class="card-body">
              <?php if ($error): ?>
                <div class="alert alert-danger mb-3"><?= h($error) ?></div>
              <?php endif; ?>
              <?php if ($successMsg): ?>
                <div class="alert alert-success mb-3"><?= h($successMsg) ?></div>
              <?php endif; ?>

              <h4 class="login-title">Welcome back</h4>
              <p class="login-subtitle">Sign in to continue shopping.</p>

              <form method="post" action="login.php" class="login-form" id="loginForm" novalidate>
                <div class="row">
                  <div class="col-12">
                    <div class="form-group">
                      <label for="email">Email Address</label>
                      <input type="email" id="email" class="form-control" name="email" value="<?= h($formData["email"]) ?>" required>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="form-group">
                      <label for="password">Password</label>
                      <div class="login-password-wrap">
                        <input type="password" id="password" class="form-control" name="password" required>
                        <button type="button" class="login-password-toggle" data-target="password" aria-label="Show password" title="Show password">&#128065;</button>
                      </div>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="remember-wrap">
                      <input type="checkbox" name="remember" id="remember">
                      <label for="remember">Remember Me</label>
                    </div>
                  </div>
                  <div class="col-12 mt-2">
                    <div class="row login-actions">
                      <div class="col-md-6 mb-2 mb-md-0">
                        <button type="submit" class="btn btn-dark" name="login">Login</button>
                      </div>
                      <div class="col-md-6">
                        <button type="reset" class="btn btn-light">Reset</button>
                      </div>
                    </div>
                    <p class="text-center mt-2 mb-1">
                      <a href="forgot_password.php"><u>Forgot password?</u></a>
                    </p>
                    <p class="text-center mt-3 mb-0">
                      Don't you have account?
                      <a href="register.php"><u>Register here</u></a>
                    </p>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  var loginForm = document.getElementById("loginForm");
  var emailInput = document.getElementById("email");
  var passwordInput = document.getElementById("password");
  var activeToast = null;

  function showErrorToast(msg) {
    if (typeof Swal === "undefined") return;
    Swal.close();
    activeToast = Swal.fire({
      icon: "error",
      title: String(msg || "Login failed."),
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 5000,
      timerProgressBar: true
    });
  }

  function clearToast() {
    if (typeof Swal === "undefined") return;
    Swal.close();
    activeToast = null;
  }

  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      e.preventDefault();
      clearToast();
      var fd = new FormData(loginForm);
      fd.append("ajax", "1");
      fetch("login.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success) {
          var go = String((data && data.redirect) || "index.php");
          window.location.href = go;
          return;
        }
        showErrorToast((data && data.message) ? data.message : "Invalid email or password.");
      })
      .catch(function () {
        showErrorToast("Unable to login right now.");
      });
    });
  }

  document.querySelectorAll(".login-form input, .login-form .form-group, .login-form label, .login-form button").forEach(function (el) {
    el.addEventListener("focus", clearToast, true);
    el.addEventListener("click", clearToast, true);
    el.addEventListener("input", clearToast, true);
  });

  document.querySelectorAll(".login-password-toggle").forEach(function (btn) {
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
});
</script>

<?php require "footer.php"; ?>