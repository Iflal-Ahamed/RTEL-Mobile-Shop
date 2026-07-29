<?php
session_start();
include 'config.php';
include "connection.php";
// Mail module imports are grouped under /web/mail
require_once "mail/mail_notifications.php";
require "header.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function make_contact_id(mysqli $conn, $table, $prefix, $idColumn)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$idColumn);
    $safePrefix = preg_replace('/[^A-Z]/', '', strtoupper((string)$prefix));
    if ($safeTable === '' || $safeCol === '' || $safePrefix === '') {
        return strtoupper($prefix) . sprintf('%03d', random_int(1, 999));
    }
    $sql = "SELECT MAX(CAST(SUBSTRING(`{$safeCol}`, 2) AS UNSIGNED)) AS max_no FROM `{$safeTable}` WHERE `{$safeCol}` LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $safePrefix . sprintf('%03d', random_int(1, 999));
    }
    $like = $safePrefix . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $next = (int)($row['max_no'] ?? 0) + 1;
    return $safePrefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function rtel_index_exists(mysqli $conn, $tableName, $indexName)
{
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbName = $dbRes ? (string)($dbRes->fetch_assoc()['db_name'] ?? '') : '';
    if ($dbName === '') {
        return false;
    }
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sss', $dbName, $tableName, $indexName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['c'] ?? 0) > 0);
}

$error = "";
$success = false;

$allowedProvinces = [
    "western" => ["Colombo", "Gampaha", "Kalutara"],
    "central" => ["Kandy", "Matale", "Nuwara Eliya"],
    "southern" => ["Galle", "Matara", "Hambantota"],
    "northern" => ["Jaffna", "Kilinochchi", "Mannar", "Mullaitivu", "Vavuniya"],
    "eastern" => ["Trincomalee", "Batticaloa", "Ampara"],
    "northwestern" => ["Kurunegala", "Puttalam"],
    "northcentral" => ["Anuradhapura", "Polonnaruwa"],
    "uva" => ["Badulla", "Monaragala"],
    "sabaragamuwa" => ["Kegalle", "Ratnapura"]
];
$allowedGenders = ["male", "female", "no"];

$formData = [
    "name" => "",
    "dob" => "",
    "gender" => "",
    "email" => "",
    "pnumber" => "",
    "address" => "",
    "province" => "",
    "district" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladdress (
        address_id VARCHAR(10) NOT NULL PRIMARY KEY,
        cus_id INT(11) NOT NULL,
        address VARCHAR(250) NOT NULL,
        province VARCHAR(250) NOT NULL,
        district VARCHAR(250) NOT NULL
    )");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblphone (
        phone_id VARCHAR(10) NOT NULL PRIMARY KEY,
        cus_id INT(11) NOT NULL,
        phone VARCHAR(20) NOT NULL
    )");
    if (!rtel_index_exists($conn, 'tbladdress', 'idx_tbladdress_cus')) {
        @mysqli_query($conn, "ALTER TABLE tbladdress ADD INDEX idx_tbladdress_cus (cus_id)");
    }
    if (!rtel_index_exists($conn, 'tblphone', 'idx_tblphone_cus')) {
        @mysqli_query($conn, "ALTER TABLE tblphone ADD INDEX idx_tblphone_cus (cus_id)");
    }
    // Keep customer core table lean for contact/location fields.
    @mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS phone");
    @mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS address");
    @mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS province");
    @mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS district");

    foreach ($formData as $key => $val) {
        $formData[$key] = trim($_POST[$key] ?? "");
    }

    $password = trim($_POST["password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");

    if (strlen($formData["name"]) < 3) {
        $error = "Name must be at least 3 characters.";
    } elseif (!preg_match('/^[a-zA-Z.\-\s]+$/', $formData["name"])) {
        $error = "Name can contain only letters, spaces, dots, and hyphens.";
    } elseif (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error = "Password must be 8+ chars with uppercase, lowercase, and a number.";
    } elseif ($password !== $confirmPassword) {
        $error = "Password and confirm password do not match.";
    } elseif (!preg_match('/^0[0-9]{9}$/', $formData["pnumber"])) {
        $error = "Invalid phone number. Must be 10 digits starting with 0.";
    } elseif (!in_array($formData["gender"], $allowedGenders, true)) {
        $error = "Please select a valid gender.";
    } elseif (!array_key_exists($formData["province"], $allowedProvinces)) {
        $error = "Please select a valid province.";
    } elseif (!in_array($formData["district"], $allowedProvinces[$formData["province"]], true)) {
        $error = "Please select a valid district for the selected province.";
    } else {
        $dobDate = DateTime::createFromFormat("Y-m-d", $formData["dob"]);
        $today = new DateTime();
        $minAgeDate = (clone $today)->modify("-13 years");
        if (!$dobDate || $dobDate->format("Y-m-d") !== $formData["dob"]) {
            $error = "Please enter a valid date of birth.";
        } elseif ($dobDate > $minAgeDate) {
            $error = "You must be at least 13 years old to register.";
        }
    }

    if (!$error) {
        mysqli_begin_transaction($conn);
        $stmt = $conn->prepare(
            "INSERT INTO tblcustomer
             (email, name, password, dob, gender, status)
             VALUES (?, ?, ?, ?, ?, '0')"
        );
        if (!$stmt) {
            $error = "DB prepare error: " . $conn->error;
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt->bind_param(
                "sssss",
                $formData["email"],
                $formData["name"],
                $hashed,
                $formData["dob"],
                $formData["gender"]
            );
            if ($stmt->execute()) {
                $cusId = (int)$conn->insert_id;
                if ($cusId <= 0) {
                    $lookup = $conn->prepare("SELECT cus_id FROM tblcustomer WHERE email = ? ORDER BY cus_id DESC LIMIT 1");
                    if ($lookup) {
                        $lookup->bind_param("s", $formData["email"]);
                        $lookup->execute();
                        $r = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        $cusId = (int)($r["cus_id"] ?? 0);
                    }
                }
                if ($cusId <= 0) {
                    $error = "Unable to create customer record.";
                } else {
                    $addressId = make_contact_id($conn, 'tbladdress', 'A', 'address_id');
                    $phoneId = make_contact_id($conn, 'tblphone', 'P', 'phone_id');
                    $addressStmt = $conn->prepare("INSERT INTO tbladdress (address_id, cus_id, address, province, district) VALUES (?, ?, ?, ?, ?)");
                    $phoneStmt = $conn->prepare("INSERT INTO tblphone (phone_id, cus_id, phone) VALUES (?, ?, ?)");
                    if (!$addressStmt || !$phoneStmt) {
                        $error = "Unable to prepare address/phone insert.";
                    } else {
                        $addressStmt->bind_param("sisss", $addressId, $cusId, $formData["address"], $formData["province"], $formData["district"]);
                        $phoneStmt->bind_param("sis", $phoneId, $cusId, $formData["pnumber"]);
                        $okAddress = $addressStmt->execute();
                        $okPhone = $phoneStmt->execute();
                        $addressStmt->close();
                        $phoneStmt->close();
                        if ($okAddress && $okPhone) {
                            mysqli_commit($conn);
                            $success = true;
                            rtel_notify_registration_welcome($formData["email"], $formData["name"]);
                        } else {
                            $error = "Unable to save contact details.";
                        }
                    }
                }
            } else {
                $error = "DB error: " . $stmt->error;
            }
            $stmt->close();
        }
        if ($error !== "") {
            mysqli_rollback($conn);
        }
    }
}
?>

<style>
  .register-layout {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
  }
  .register-image-wrap {
    background: #f6f6f6;
    display: flex;
    height: 100%;
    min-height: 860px;
  }
  .register-image-wrap img {
    width: 100%;
    height: 100% !important;
    min-height: 100%;
    max-height: none;
    flex: 1 1 auto;
    object-fit: cover;
    display: block;
  }
  .register-card {
    border: 0;
    background: #fff;
  }
  .register-card .card-body {
    padding: 2rem;
  }
  .register-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 6px;
  }
  .register-subtitle {
    color: #6c757d;
    margin-bottom: 1.2rem;
  }
  .register-form .form-group {
    margin-bottom: 1rem;
  }
  .register-form label {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
  }
  .register-form .form-control {
    height: 48px;
    border: 1px solid #d6dbe1;
    border-radius: 8px;
    box-shadow: none;
  }
  .register-form .form-control:focus {
    border-color: #111;
    box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.08);
  }
  .register-actions .btn {
    width: 100%;
    height: 50px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
  }
  .register-actions .btn-light {
    border: 1px solid #d5d5d5;
    background: #fff;
  }
  .register-password-wrap {
    position: relative;
  }
  .register-password-wrap .form-control {
    padding-right: 44px;
  }
  .register-password-toggle {
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
  .register-password-toggle:hover {
    color: #111;
    border-color: #cfcfcf;
    background: #f9f9f9;
  }
  @media (max-width: 991px) {
    .register-image-wrap img {
      min-height: 100%;
      max-height: none;
      object-fit: cover;
    }
    .register-card .card-body {
      padding: 1.4rem 1.1rem;
    }
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Registration</span></p>
        <h1 class="mb-0 bread">Registration Form</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section ftco-no-pb ftco-no-pt bg-light py-4">
  <div class="container">
    <div class="register-layout">
      <div class="row no-gutters align-items-stretch">
        <div class="col-lg-5 p-md-5 img img-2 d-flex justify-content-center align-items-center">
          <img src="../images/login.webp" alt="Register">
        </div>
        <div class="col-lg-7 d-flex ftco-animate">
          <div class="card register-card w-100">
            <div class="card-body">
              <?php if ($success): ?>
                <div class="alert alert-success text-center mb-3">
                  <h5 class="mb-1">Registration Successful</h5>
                  <p class="mb-0">You will be redirected to login page...</p>
                </div>
                <script>
                  setTimeout(function () { window.location.href = "login.php"; }, 2000);
                </script>
              <?php else: ?>
                <h4 class="register-title">Create your account</h4>
                <p class="register-subtitle">Fill in your details to get started.</p>
                <form method="post" action="register.php" class="register-form" novalidate>
                  <div class="row">
                    <div class="col-12">
                      <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="name" class="form-control" value="<?= h($formData["name"]) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="<?= h($formData["dob"]) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                          <option value="">Select</option>
                          <option value="male" <?= $formData["gender"] === "male" ? "selected" : "" ?>>Male</option>
                          <option value="female" <?= $formData["gender"] === "female" ? "selected" : "" ?>>Female</option>
                          <option value="no" <?= $formData["gender"] === "no" ? "selected" : "" ?>>Prefer not to say</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= h($formData["email"]) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" id="pnumber" name="pnumber" class="form-control" value="<?= h($formData["pnumber"]) ?>" placeholder="07XXXXXXXX" maxlength="10" inputmode="numeric" pattern="^0[0-9]{9}$" required>
                        <small id="phoneMsg" style="font-size:12px;"></small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Password</label>
                        <div class="register-password-wrap">
                          <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                          <button type="button" class="register-password-toggle" data-target="password" aria-label="Show password" title="Show password">&#128065;</button>
                        </div>
                        <small id="passwordMsg" style="font-size:12px;color:#6c757d;">Use 8+ chars with uppercase, lowercase, and a number.</small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="register-password-wrap">
                          <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                          <button type="button" class="register-password-toggle" data-target="confirm_password" aria-label="Show password" title="Show password">&#128065;</button>
                        </div>
                        <small id="confirmMsg" style="font-size:12px;"></small>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control" value="<?= h($formData["address"]) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Province</label>
                        <select id="province" name="province" class="form-control" required>
                          <option value="">Select Province</option>
                          <option value="western" <?= $formData["province"] === "western" ? "selected" : "" ?>>Western Province</option>
                          <option value="central" <?= $formData["province"] === "central" ? "selected" : "" ?>>Central Province</option>
                          <option value="southern" <?= $formData["province"] === "southern" ? "selected" : "" ?>>Southern Province</option>
                          <option value="northern" <?= $formData["province"] === "northern" ? "selected" : "" ?>>Northern Province</option>
                          <option value="eastern" <?= $formData["province"] === "eastern" ? "selected" : "" ?>>Eastern Province</option>
                          <option value="northwestern" <?= $formData["province"] === "northwestern" ? "selected" : "" ?>>North Western Province</option>
                          <option value="northcentral" <?= $formData["province"] === "northcentral" ? "selected" : "" ?>>North Central Province</option>
                          <option value="uva" <?= $formData["province"] === "uva" ? "selected" : "" ?>>Uva Province</option>
                          <option value="sabaragamuwa" <?= $formData["province"] === "sabaragamuwa" ? "selected" : "" ?>>Sabaragamuwa Province</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>District</label>
                        <select id="district" name="district" class="form-control" data-selected-district="<?= h($formData["district"]) ?>" required>
                          <option value="">Select District</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-12 mt-2">
                      <div class="row register-actions">
                        <div class="col-md-6 mb-2 mb-md-0">
                          <button type="submit" class="btn btn-dark">Register</button>
                        </div>
                        <div class="col-md-6">
                          <button type="button" id="registerResetBtn" class="btn btn-light">Reset</button>
                        </div>
                      </div>
                      <p class="text-center mt-3 mb-0">
                        Do you have account?
                        <a href="login.php"><u>Login here</u></a>
                      </p>
                    </div>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  var phoneInput = document.getElementById("pnumber");
  var phoneMsg = document.getElementById("phoneMsg");
  var passwordInput = document.getElementById("password");
  var passwordMsg = document.getElementById("passwordMsg");
  var confirmInput = document.getElementById("confirm_password");
  var confirmMsg = document.getElementById("confirmMsg");
  var activeToast = null;
  function focusField(el) {
    if (!el || typeof el.focus !== "function") return;
    if (typeof el.scrollIntoView === "function") {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    setTimeout(function () { el.focus(); }, 50);
  }

  function showErrorToast(msg) {
    if (typeof Swal === "undefined") return;
    Swal.close();
    activeToast = Swal.fire({
      icon: "error",
      title: String(msg || "Please complete required fields."),
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


  if (phoneInput) {
    phoneInput.addEventListener("input", function () {
      this.value = this.value.replace(/\D/g, "").slice(0, 10);
      if (!phoneMsg) return;
      if (!this.value) {
        phoneMsg.textContent = "";
      } else if (!/^0/.test(this.value)) {
        phoneMsg.textContent = "Must start with 0";
        phoneMsg.style.color = "#dc3545";
      } else if (this.value.length < 10) {
        phoneMsg.textContent = this.value.length + " / 10 digits";
        phoneMsg.style.color = "#6c757d";
      } else {
        phoneMsg.textContent = "Valid number";
        phoneMsg.style.color = "#28a745";
      }
    });
  }

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

  function showRegisterError(message, focusEl) {
    showErrorToast(message);
    focusField(focusEl);
  }

  function clearRegisterError() {
    clearToast();
  }

  function validateRegistrationForm() {
    var requiredFields = [
      { el: document.querySelector('input[name="name"]'), msg: "Please enter your name." },
      { el: document.querySelector('input[name="dob"]'), msg: "Please select your date of birth." },
      { el: document.querySelector('select[name="gender"]'), msg: "Please select your gender." },
      { el: document.querySelector('input[name="email"]'), msg: "Please enter your email address." },
      { el: phoneInput, msg: "Please enter your phone number." },
      { el: passwordInput, msg: "Please enter your password." },
      { el: confirmInput, msg: "Please confirm your password." },
      { el: document.querySelector('input[name="address"]'), msg: "Please enter your address." },
      { el: document.getElementById("province"), msg: "Please select your province." },
      { el: document.getElementById("district"), msg: "Please select your district." }
    ];
    for (var i = 0; i < requiredFields.length; i++) {
      var item = requiredFields[i];
      if (!item.el) continue;
      if (!String(item.el.value || "").trim()) {
        showRegisterError(item.msg, item.el);
        return false;
      }
    }
    if (phoneInput && !/^0[0-9]{9}$/.test(String(phoneInput.value || "").trim())) {
      showRegisterError("Invalid phone number. Must be 10 digits starting with 0.", phoneInput);
      return false;
    }
    if (!checkPasswordStrength()) {
      showRegisterError("Password must be 8+ chars with uppercase, lowercase, and a number.", passwordInput);
      return false;
    }
    if (!checkConfirmPassword()) {
      showRegisterError("Password and confirm password do not match.", confirmInput);
      return false;
    }
    clearRegisterError();
    return true;
  }

  document.querySelectorAll(".register-password-toggle").forEach(function (btn) {
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

  var form = document.querySelector("form.register-form");
  var registerResetBtn = document.getElementById("registerResetBtn");
  if (form) {
    form.addEventListener("input", clearRegisterError);
    form.addEventListener("change", clearRegisterError);
    form.addEventListener("submit", function (e) {
      clearToast();
      if (!validateRegistrationForm()) {
        e.preventDefault();
      }
    });
  }
  if (registerResetBtn) {
    registerResetBtn.addEventListener("click", function () {
      clearRegisterError();
      window.location.href = "register.php";
    });
  }

  var registerServerError = <?php echo json_encode((string)$error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  if (registerServerError) {
    showErrorToast(registerServerError);
  }
});
</script>

<?php require "footer.php"; ?>
