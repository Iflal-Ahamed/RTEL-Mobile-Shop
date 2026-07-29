<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "connection.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rtel_profile_pagination_markup($tabSlug, $pageQueryKey, $currentPage, $totalPages, $totalItems, $perPage)
{
    if ($totalPages <= 1 || $totalItems < 1) {
        return;
    }
    $from = (($currentPage - 1) * $perPage) + 1;
    $to = min($totalItems, $currentPage * $perPage);
    $qk = rawurlencode($pageQueryKey);
    $tb = rawurlencode($tabSlug);
    $basePrefix = 'my_profile.php?tab=' . $tb . '&' . $qk . '=';

    echo '<nav class="profile-pagination-nav mt-3" aria-label="Page navigation">';
    echo '<div class="small text-muted text-center mb-2">Showing ' . (int)$from . "–" . (int)$to . " of " . (int)$totalItems . '</div>';
    echo '<ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">';
    if ($currentPage > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . h($basePrefix . ($currentPage - 1)) . '">Previous</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    $slots = [];
    if ($totalPages <= 9) {
        for ($p = 1; $p <= $totalPages; $p++) {
            $slots[] = $p;
        }
    } else {
        $slots[] = 1;
        $nearLeft = max(2, $currentPage - 1);
        $nearRight = min($totalPages - 1, $currentPage + 1);
        if ($nearLeft > 2) {
            $slots[] = 'ellipsis';
        }
        for ($p = $nearLeft; $p <= $nearRight; $p++) {
            $slots[] = $p;
        }
        if ($nearRight < $totalPages - 1) {
            $slots[] = 'ellipsis';
        }
        $slots[] = $totalPages;
    }

    foreach ($slots as $s) {
        if ($s === 'ellipsis') {
            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            continue;
        }
        $pNum = (int)$s;
        $isActive = $pNum === $currentPage ? ' active' : '';
        echo '<li class="page-item' . $isActive . '"><a class="page-link" href="' . h($basePrefix . $pNum) . '">' . $pNum . '</a></li>';
    }

    if ($currentPage < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="' . h($basePrefix . ($currentPage + 1)) . '">Next</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    echo '</ul></nav>';
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

$userId = (string)$_SESSION["user_id"];
$activeTab = $_GET["tab"] ?? "my-details";
$validTabs = ["my-details", "my-orders", "my-ratings", "my-feedbacks"];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = "my-details";
}
$tabLabels = [
    "my-details" => "My Details",
    "my-orders" => "My Orders",
    "my-ratings" => "My Ratings",
    "my-feedbacks" => "My Feedbacks"
];
$currentTabLabel = $tabLabels[$activeTab] ?? "My Details";

$ratingsPerPage = 8;
$feedbacksPerPage = 8;
$ratingsPage = max(1, (int)($_GET["ratings_page"] ?? 1));
$feedbacksPage = max(1, (int)($_GET["feedbacks_page"] ?? 1));

$profileMessage = $_SESSION["profile_message"] ?? "";
$profileError = $_SESSION["profile_error"] ?? "";
$passwordMessage = $_SESSION["password_message"] ?? "";
$passwordError = $_SESSION["password_error"] ?? "";
$ratingMessage = $_SESSION["rating_message"] ?? "";
$ratingError = $_SESSION["rating_error"] ?? "";
$savedScroll = isset($_SESSION["profile_scroll"]) ? (int)$_SESSION["profile_scroll"] : 0;
unset($_SESSION["profile_message"], $_SESSION["profile_error"], $_SESSION["password_message"], $_SESSION["password_error"], $_SESSION["rating_message"], $_SESSION["rating_error"], $_SESSION["profile_scroll"]);
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

$customer = [
    "name" => "",
    "email" => "",
    "dob" => "",
    "gender" => "",
    "profile_image" => ""
];
$customerPhones = [];
$customerAddresses = [];

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblratings (
    rating_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cus_id INT(11) NOT NULL,
    orderdetails_id VARCHAR(10) NOT NULL,
    order_id VARCHAR(10) NOT NULL,
    product_id VARCHAR(10) NOT NULL,
    rating INT(2) NOT NULL,
    review_text VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
)");
$commentCols = [];
$commentColRes = mysqli_query($conn, "SHOW COLUMNS FROM tblcomment");
if ($commentColRes) {
    while ($col = mysqli_fetch_assoc($commentColRes)) {
        $commentCols[] = strtolower((string)$col["Field"]);
    }
}
if (!in_array("cus_id", $commentCols, true)) {
    mysqli_query($conn, "ALTER TABLE tblcomment ADD COLUMN cus_id INT(11) NULL AFTER com_id");
}
mysqli_query($conn, "ALTER TABLE tblcustomer ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NOT NULL DEFAULT ''");
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
@mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS phone");
@mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS address");
@mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS province");
@mysqli_query($conn, "ALTER TABLE tblcustomer DROP COLUMN IF EXISTS district");

$loadStmt = $conn->prepare("SELECT name, email, dob, gender, profile_image, password FROM tblcustomer WHERE cus_id = ? LIMIT 1");
$storedPassword = "";
if ($loadStmt) {
    $loadStmt->bind_param("s", $userId);
    $loadStmt->execute();
    $row = $loadStmt->get_result()->fetch_assoc();
    if ($row) {
        $customer["name"] = $row["name"] ?? "";
        $customer["email"] = $row["email"] ?? "";
        $customer["dob"] = $row["dob"] ?? "";
        $customer["gender"] = $row["gender"] ?? "";
        $customer["profile_image"] = $row["profile_image"] ?? "";
        $storedPassword = $row["password"] ?? "";
    }
    $loadStmt->close();
}

if (isset($_SESSION["profile_form_values"]) && is_array($_SESSION["profile_form_values"])) {
    foreach ($_SESSION["profile_form_values"] as $k => $v) {
        if (array_key_exists($k, $customer)) {
            $customer[$k] = (string)$v;
        }
    }
    unset($_SESSION["profile_form_values"]);
}
$phoneStmt = $conn->prepare("SELECT phone_id, phone FROM tblphone WHERE cus_id = ? ORDER BY phone_id DESC");
if ($phoneStmt) {
    $phoneStmt->bind_param("s", $userId);
    $phoneStmt->execute();
    $pr = $phoneStmt->get_result();
    while ($pr && $prow = $pr->fetch_assoc()) {
        $customerPhones[] = $prow;
    }
    $phoneStmt->close();
}
$addrStmt = $conn->prepare("SELECT address_id, address, province, district FROM tbladdress WHERE cus_id = ? ORDER BY address_id DESC");
if ($addrStmt) {
    $addrStmt->bind_param("s", $userId);
    $addrStmt->execute();
    $ar = $addrStmt->get_result();
    while ($ar && $arow = $ar->fetch_assoc()) {
        $customerAddresses[] = $arow;
    }
    $addrStmt->close();
}
$profileImagePreviewUrl = "";
if (trim((string)$customer["profile_image"]) !== "") {
    $safeProfileImage = basename((string)$customer["profile_image"]);
    $rootPath = realpath(__DIR__ . "/..");
    $profileAbsPath = $rootPath ? ($rootPath . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "customer_profiles" . DIRECTORY_SEPARATOR . $safeProfileImage) : "";
    if ($profileAbsPath !== "" && is_file($profileAbsPath)) {
        $profileImagePreviewUrl = "../images/customer_profiles/" . rawurlencode($safeProfileImage) . "?v=" . (string)@filemtime($profileAbsPath);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";
    $_SESSION["profile_scroll"] = isset($_POST["scroll_pos"]) ? (int)$_POST["scroll_pos"] : 0;

    if ($formAction === "upload_profile_image") {
        $file = $_FILES["profile_image"] ?? null;
        if (!$file || !isset($file["error"])) {
            $_SESSION["profile_error"] = "Please choose an image to upload.";
            header("Location: my_profile.php");
            exit();
        }
        if ((int)$file["error"] !== UPLOAD_ERR_OK) {
            $_SESSION["profile_error"] = "Image upload failed. Please try again.";
            header("Location: my_profile.php");
            exit();
        }
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ((int)($file["size"] ?? 0) > $maxBytes) {
            $_SESSION["profile_error"] = "Image is too large. Maximum size is 2MB.";
            header("Location: my_profile.php");
            exit();
        }
        $tmp = (string)($file["tmp_name"] ?? "");
        $mime = function_exists("mime_content_type") ? (string)@mime_content_type($tmp) : "";
        $extMap = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];
        if (!isset($extMap[$mime])) {
            $_SESSION["profile_error"] = "Only JPG, PNG, or WEBP images are allowed.";
            header("Location: my_profile.php");
            exit();
        }
        $ext = $extMap[$mime];
        $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$userId);
        if ($safeUser === '') $safeUser = 'cus';
        $newName = "profile_" . $safeUser . "_" . time() . "." . $ext;
        $targetDir = realpath(__DIR__ . "/..");
        $targetDir = $targetDir ? ($targetDir . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "customer_profiles") : (__DIR__ . "/../images/customer_profiles");
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newName;
        if (!@move_uploaded_file($tmp, $targetPath)) {
            $_SESSION["profile_error"] = "Unable to save uploaded image.";
            header("Location: my_profile.php");
            exit();
        }

        $oldImage = trim((string)($customer["profile_image"] ?? ""));
        $upImageStmt = $conn->prepare("UPDATE tblcustomer SET profile_image = ? WHERE cus_id = ?");
        if ($upImageStmt) {
            $upImageStmt->bind_param("ss", $newName, $userId);
            if ($upImageStmt->execute()) {
                $_SESSION["profile_message"] = "Profile image updated successfully.";
                $customer["profile_image"] = $newName;
                if ($oldImage !== "") {
                    $oldPath = $targetDir . DIRECTORY_SEPARATOR . basename($oldImage);
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            } else {
                $_SESSION["profile_error"] = "Unable to update profile image.";
            }
            $upImageStmt->close();
        } else {
            $_SESSION["profile_error"] = "Unable to prepare profile image update.";
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "update_profile") {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $dob = trim($_POST["dob"] ?? "");
        $gender = trim($_POST["gender"] ?? "");

        if ($name === "" || strlen($name) < 3 || !preg_match('/^[a-zA-Z.\-\s]+$/', $name)) {
            $profileError = "Name must be at least 3 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = "Please enter a valid email.";
        } elseif ($dob === "") {
            $profileError = "Date of birth is required.";
        } elseif (!DateTime::createFromFormat("Y-m-d", $dob)) {
            $profileError = "Please enter a valid date of birth.";
        } elseif ((new DateTime($dob)) > (new DateTime())->modify("-13 years")) {
            $profileError = "You must be at least 13 years old.";
        } elseif (!in_array($gender, ["male", "female", "no"], true)) {
            $profileError = "Please select a valid gender.";
        } else {
            $emailCheckStmt = $conn->prepare("SELECT cus_id FROM tblcustomer WHERE email = ? AND cus_id != ? LIMIT 1");
            if ($emailCheckStmt) {
                $emailCheckStmt->bind_param("ss", $email, $userId);
                $emailCheckStmt->execute();
                $dup = $emailCheckStmt->get_result()->fetch_assoc();
                $emailCheckStmt->close();
                if ($dup) {
                    $profileError = "This email is already used by another account.";
                }
            }
        }

        if ($profileError === "") {
            $updateProfileStmt = $conn->prepare("UPDATE tblcustomer SET name = ?, email = ?, dob = ?, gender = ? WHERE cus_id = ?");
            if ($updateProfileStmt) {
                $updateProfileStmt->bind_param("sssss", $name, $email, $dob, $gender, $userId);
                if ($updateProfileStmt->execute()) {
                    $_SESSION["profile_message"] = "Profile information updated successfully.";
                    $_SESSION["user_name"] = $name;
                    $_SESSION["user_email"] = $email;
                } else {
                    $_SESSION["profile_error"] = "Unable to update profile.";
                    $_SESSION["profile_form_values"] = [
                        "name" => $name,
                        "email" => $email,
                        "dob" => $dob,
                        "gender" => $gender
                    ];
                }
                $updateProfileStmt->close();
            }
        } else {
            $_SESSION["profile_error"] = $profileError;
            $_SESSION["profile_form_values"] = [
                "name" => $name,
                "email" => $email,
                "dob" => $dob,
                "gender" => $gender
            ];
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "add_phone") {
        $newPhone = trim((string)($_POST["new_phone"] ?? ""));
        if (!preg_match('/^0[0-9]{9}$/', $newPhone)) {
            $_SESSION["profile_error"] = "Phone must be 10 digits and start with 0.";
        } else {
            $dupStmt = $conn->prepare("SELECT phone_id FROM tblphone WHERE cus_id = ? AND phone = ? LIMIT 1");
            $dup = false;
            if ($dupStmt) {
                $dupStmt->bind_param("ss", $userId, $newPhone);
                $dupStmt->execute();
                $dup = (bool)$dupStmt->get_result()->fetch_assoc();
                $dupStmt->close();
            }
            if ($dup) {
                $_SESSION["profile_error"] = "This phone number is already saved.";
            } else {
                $phoneId = make_contact_id($conn, 'tblphone', 'P', 'phone_id');
                $insPhone = $conn->prepare("INSERT INTO tblphone (phone_id, cus_id, phone) VALUES (?, ?, ?)");
                if ($insPhone) {
                    $insPhone->bind_param("sss", $phoneId, $userId, $newPhone);
                    if ($insPhone->execute()) {
                        $_SESSION["profile_message"] = "Phone number added.";
                    } else {
                        $_SESSION["profile_error"] = "Unable to add phone number.";
                    }
                    $insPhone->close();
                }
            }
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "delete_phone") {
        $phoneId = trim((string)($_POST["phone_id"] ?? ""));
        $delPhone = $conn->prepare("DELETE FROM tblphone WHERE phone_id = ? AND cus_id = ?");
        if ($delPhone) {
            $delPhone->bind_param("ss", $phoneId, $userId);
            if ($delPhone->execute()) {
                $_SESSION["profile_message"] = "Phone number removed.";
            } else {
                $_SESSION["profile_error"] = "Unable to remove phone number.";
            }
            $delPhone->close();
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "add_address") {
        $newAddress = trim((string)($_POST["new_address"] ?? ""));
        $newProvince = trim((string)($_POST["new_province"] ?? ""));
        $newDistrict = trim((string)($_POST["new_district"] ?? ""));
        if ($newAddress === "") {
            $_SESSION["profile_error"] = "Address is required.";
        } elseif (!array_key_exists($newProvince, $allowedProvinces)) {
            $_SESSION["profile_error"] = "Please select a valid province.";
        } elseif (!in_array($newDistrict, $allowedProvinces[$newProvince], true)) {
            $_SESSION["profile_error"] = "Please select a valid district for the selected province.";
        } else {
            $addressId = make_contact_id($conn, 'tbladdress', 'A', 'address_id');
            $insAddr = $conn->prepare("INSERT INTO tbladdress (address_id, cus_id, address, province, district) VALUES (?, ?, ?, ?, ?)");
            if ($insAddr) {
                $insAddr->bind_param("sssss", $addressId, $userId, $newAddress, $newProvince, $newDistrict);
                if ($insAddr->execute()) {
                    $_SESSION["profile_message"] = "Address added.";
                } else {
                    $_SESSION["profile_error"] = "Unable to add address.";
                }
                $insAddr->close();
            }
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "delete_address") {
        $addressId = trim((string)($_POST["address_id"] ?? ""));
        $delAddr = $conn->prepare("DELETE FROM tbladdress WHERE address_id = ? AND cus_id = ?");
        if ($delAddr) {
            $delAddr->bind_param("ss", $addressId, $userId);
            if ($delAddr->execute()) {
                $_SESSION["profile_message"] = "Address removed.";
            } else {
                $_SESSION["profile_error"] = "Unable to remove address.";
            }
            $delAddr->close();
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "update_password") {
        $currentPassword = trim($_POST["current_password"] ?? "");
        $newPassword = trim($_POST["new_password"] ?? "");
        $confirmPassword = trim($_POST["confirm_password"] ?? "");

        if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
            $passwordError = "All password fields are required.";
        } elseif (!password_verify($currentPassword, $storedPassword)) {
            $passwordError = "Current password is incorrect.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPassword)) {
            $passwordError = "New password must be 8+ chars with uppercase, lowercase, and a number.";
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = "New password and confirm password do not match.";
        } elseif ($currentPassword === $newPassword) {
            $passwordError = "New password should be different from current password.";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $updatePwdStmt = $conn->prepare("UPDATE tblcustomer SET password = ? WHERE cus_id = ?");
            if ($updatePwdStmt) {
                $updatePwdStmt->bind_param("ss", $hashed, $userId);
                if ($updatePwdStmt->execute()) {
                    $_SESSION["password_message"] = "Password updated successfully.";
                    $storedPassword = $hashed;
                } else {
                    $_SESSION["password_error"] = "Unable to update password.";
                }
                $updatePwdStmt->close();
            }
        }
        if ($passwordError !== "") {
            $_SESSION["password_error"] = $passwordError;
        }
        header("Location: my_profile.php");
        exit();
    }

    if ($formAction === "update_rating") {
        $ratingId = (int)($_POST["rating_id"] ?? 0);
        $ratingValue = (int)($_POST["rating"] ?? 0);
        $reviewText = trim($_POST["review_text"] ?? "");
        if ($ratingId < 1 || $ratingValue < 1 || $ratingValue > 5 || $reviewText === "") {
            $_SESSION["rating_error"] = "Please provide valid star rating and review text.";
        } else {
            $updatedAt = date("Y-m-d H:i:s");
            $updateRatingStmt = $conn->prepare("UPDATE tblratings SET rating = ?, review_text = ?, updated_at = ? WHERE rating_id = ? AND cus_id = ?");
            if ($updateRatingStmt) {
                $updateRatingStmt->bind_param("issis", $ratingValue, $reviewText, $updatedAt, $ratingId, $userId);
                if ($updateRatingStmt->execute()) {
                    $_SESSION["rating_message"] = "Rating updated successfully.";
                } else {
                    $_SESSION["rating_error"] = "Unable to update rating.";
                }
                $updateRatingStmt->close();
            }
        }
        $rp = max(1, (int)($_POST["ratings_page"] ?? 1));
        header("Location: my_profile.php?tab=my-ratings&ratings_page=" . $rp);
        exit();
    }

    if ($formAction === "delete_rating") {
        $ratingId = (int)($_POST["rating_id"] ?? 0);
        if ($ratingId < 1) {
            $_SESSION["rating_error"] = "Invalid rating request.";
        } else {
            $deleteRatingStmt = $conn->prepare("DELETE FROM tblratings WHERE rating_id = ? AND cus_id = ?");
            if ($deleteRatingStmt) {
                $deleteRatingStmt->bind_param("is", $ratingId, $userId);
                if ($deleteRatingStmt->execute()) {
                    $_SESSION["rating_message"] = "Rating deleted successfully.";
                } else {
                    $_SESSION["rating_error"] = "Unable to delete rating.";
                }
                $deleteRatingStmt->close();
            }
        }
        $rp = max(1, (int)($_POST["ratings_page"] ?? 1));
        header("Location: my_profile.php?tab=my-ratings&ratings_page=" . $rp);
        exit();
    }
}

$myRatings = [];
$ratingsTotal = 0;
$ratingsTotalPages = 1;
$myFeedbacks = [];
$feedbacksTotal = 0;
$feedbacksTotalPages = 1;

if ($activeTab === "my-ratings") {
    $cntRat = $conn->prepare("SELECT COUNT(*) AS c FROM tblratings WHERE cus_id = ?");
    if ($cntRat) {
        $cntRat->bind_param("s", $userId);
        $cntRat->execute();
        $crow = $cntRat->get_result();
        $row = $crow ? $crow->fetch_assoc() : null;
        $cntRat->close();
        $ratingsTotal = (int)($row["c"] ?? 0);
    }
    $ratingsTotalPages = max(1, (int)ceil($ratingsTotal / $ratingsPerPage));
    if ($ratingsPage > $ratingsTotalPages) {
        $ratingsPage = $ratingsTotalPages;
    }
    $ratingsOffset = ($ratingsPage - 1) * $ratingsPerPage;
    $ratingsStmt = $conn->prepare(
        "SELECT r.rating_id, r.order_id, r.product_id, r.rating, r.review_text, r.created_at, r.updated_at, p.name AS product_name
         FROM tblratings r
         LEFT JOIN tblproduct p ON r.product_id = p.product_id
         WHERE r.cus_id = ?
         ORDER BY r.rating_id DESC
         LIMIT ? OFFSET ?"
    );
    if ($ratingsStmt) {
        $ratingsStmt->bind_param("sii", $userId, $ratingsPerPage, $ratingsOffset);
        $ratingsStmt->execute();
        $ratingsRes = $ratingsStmt->get_result();
        while ($ratingsRes && $rr = $ratingsRes->fetch_assoc()) {
            $myRatings[] = $rr;
        }
        $ratingsStmt->close();
    }
}

if ($activeTab === "my-feedbacks") {
    $cntFb = $conn->prepare("SELECT COUNT(*) AS c FROM tblcomment WHERE cus_id = ?");
    if ($cntFb) {
        $cntFb->bind_param("s", $userId);
        $cntFb->execute();
        $crow = $cntFb->get_result();
        $row = $crow ? $crow->fetch_assoc() : null;
        $cntFb->close();
        $feedbacksTotal = (int)($row["c"] ?? 0);
    }
    $feedbacksTotalPages = max(1, (int)ceil($feedbacksTotal / $feedbacksPerPage));
    if ($feedbacksPage > $feedbacksTotalPages) {
        $feedbacksPage = $feedbacksTotalPages;
    }
    $feedbacksOffset = ($feedbacksPage - 1) * $feedbacksPerPage;
    $feedbackStmt = $conn->prepare(
        "SELECT com_id, name, comment, status FROM tblcomment WHERE cus_id = ? ORDER BY com_id DESC LIMIT ? OFFSET ?"
    );
    if ($feedbackStmt) {
        $feedbackStmt->bind_param("sii", $userId, $feedbacksPerPage, $feedbacksOffset);
        $feedbackStmt->execute();
        $feedbackRes = $feedbackStmt->get_result();
        while ($feedbackRes && $fr = $feedbackRes->fetch_assoc()) {
            $myFeedbacks[] = $fr;
        }
        $feedbackStmt->close();
    }
}

require "header.php";
?>

<style>
  .profile-actions {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-top: 20px;
  }
  .profile-actions a {
    color: #111;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    border-radius: 8px;
    text-align: center;
    width: 100%;
  }
  .profile-actions a.active {
    background: #111;
    color: #fff;
  }
  .profile-actions a.logout-link {
    color: #dc3545;
  }
  .profile-card {
    border: 1px solid #ececec;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
  }
  .profile-card h5 {
    margin-bottom: 18px;
  }
  .rating-stars-view {
    color: #f5b301;
    letter-spacing: 2px;
    font-size: 16px;
  }
  .profile-card textarea {
    resize: vertical;
  }
  .profile-password-wrap {
    position: relative;
  }
  .profile-password-wrap .form-control {
    padding-right: 44px;
  }
  .profile-password-toggle {
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
  .profile-password-toggle:hover {
    color: #111;
    border-color: #cfcfcf;
    background: #f9f9f9;
  }
  .profile-card.has-error {
    border-color: #dc3545;
    box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.12);
    animation: profileErrorPulse 0.6s ease;
  }
  @keyframes profileErrorPulse {
    0% { transform: translateY(0); }
    40% { transform: translateY(-2px); }
    100% { transform: translateY(0); }
  }
  @media (max-width: 991px) {
    .profile-actions {
      grid-template-columns: 1fr 1fr;
    }
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs">
          <span class="mr-2"><a href="index.php">Home</a></span>
          <span class="mr-2"><a href="my_profile.php">My Profile</a></span>
          <span><?php echo h($currentTabLabel); ?></span>
        </p>
        <h1 class="mb-0 bread"><?php echo h($currentTabLabel); ?></h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section pt-4">
  <div class="container">
    <div class="profile-actions">
      <a href="my_profile.php?tab=my-details" class="<?php echo $activeTab === 'my-details' ? 'active' : ''; ?>">My Details</a>
      <a href="my_orders.php" class="<?php echo $activeTab === 'my-orders' ? 'active' : ''; ?>">My Orders</a>
      <a href="my_profile.php?tab=my-ratings" class="<?php echo $activeTab === 'my-ratings' ? 'active' : ''; ?>">My Ratings</a>
      <a href="my_profile.php?tab=my-feedbacks" class="<?php echo $activeTab === 'my-feedbacks' ? 'active' : ''; ?>">My Feedbacks</a>
      <a href="logout.php" class="logout-link">Logout</a>
    </div>
  </div>
</section>

<section class="ftco-section pt-2">
  <div class="container">
    <?php if ($activeTab === "my-details"): ?>
      <div class="profile-card <?php echo $profileError !== '' ? 'has-error' : ''; ?>">
        <h5>Update Personal Info</h5>
        <form method="post" action="my_profile.php" enctype="multipart/form-data" class="mb-4">
          <input type="hidden" name="form_action" value="upload_profile_image">
          <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
          <div class="row align-items-end">
            <div class="col-md-12 form-group mb-2">
              <?php if ($profileImagePreviewUrl !== ""): ?>
                <img src="<?php echo h($profileImagePreviewUrl); ?>" alt="Current profile image" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
              <?php else: ?>
                <small class="text-muted">No profile image uploaded yet.</small>
              <?php endif; ?>
            </div>
            <div class="col-md-6 form-group mb-2">
              <label>Profile Photo (JPG, PNG, WEBP - max 2MB)</label>
              <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
            </div>
            <div class="col-md-4 form-group mb-2 d-flex justify-content-md-end">
              <button type="submit" class="btn btn-dark">Upload Profile</button>
            </div>
          </div>
        </form>
        <form method="post" action="my_profile.php">
          <input type="hidden" name="form_action" value="update_profile">
          <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo h($customer["name"]); ?>" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo h($customer["email"]); ?>" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?php echo h($customer["dob"]); ?>" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Gender</label>
              <select name="gender" class="form-control" required>
                <option value="">Select</option>
                <option value="male" <?php echo $customer["gender"] === "male" ? "selected" : ""; ?>>Male</option>
                <option value="female" <?php echo $customer["gender"] === "female" ? "selected" : ""; ?>>Female</option>
                <option value="no" <?php echo $customer["gender"] === "no" ? "selected" : ""; ?>>Prefer not to say</option>
              </select>
            </div>
            <div class="col-12 form-group d-flex justify-content-end">
              <button type="submit" class="btn btn-dark">Update Info</button>
            </div>
          </div>
        </form>
      </div>

      <div class="profile-card">
        <h5>My Phone Numbers</h5>
        <form method="post" action="my_profile.php" class="mb-3">
          <input type="hidden" name="form_action" value="add_phone">
          <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
          <div class="row">
            <div class="col-md-9 form-group">
              <label>Add Phone</label>
              <input type="text" name="new_phone" class="form-control js-profile-new-phone" maxlength="10" placeholder="07XXXXXXXX" required>
            </div>
            <div class="col-md-3 form-group d-flex align-items-end">
              <button type="submit" class="btn btn-dark w-100">Add Phone</button>
            </div>
          </div>
        </form>
        <?php if (count($customerPhones) === 0): ?>
          <p class="mb-0 text-muted">No phone numbers added yet.</p>
        <?php else: ?>
          <?php foreach ($customerPhones as $p): ?>
            <form method="post" action="my_profile.php" class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
              <input type="hidden" name="form_action" value="delete_phone">
              <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
              <input type="hidden" name="phone_id" value="<?php echo h($p["phone_id"] ?? ""); ?>">
              <span><?php echo h($p["phone"] ?? ""); ?></span>
              <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="profile-card">
        <h5>My Addresses</h5>
        <form method="post" action="my_profile.php" class="mb-3">
          <input type="hidden" name="form_action" value="add_address">
          <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
          <div class="row">
            <div class="col-md-12 form-group">
              <label>Address</label>
              <input type="text" name="new_address" class="form-control" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Province</label>
              <select id="new_province" name="new_province" class="form-control" required>
                <option value="">Select Province</option>
                <option value="western">Western Province</option>
                <option value="central">Central Province</option>
                <option value="southern">Southern Province</option>
                <option value="northern">Northern Province</option>
                <option value="eastern">Eastern Province</option>
                <option value="northwestern">North Western Province</option>
                <option value="northcentral">North Central Province</option>
                <option value="uva">Uva Province</option>
                <option value="sabaragamuwa">Sabaragamuwa Province</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>District</label>
              <select id="new_district" name="new_district" class="form-control" required>
                <option value="">Select District</option>
              </select>
            </div>
            <div class="col-12 form-group d-flex justify-content-end">
              <button type="submit" class="btn btn-dark">Add Address</button>
            </div>
          </div>
        </form>
        <?php if (count($customerAddresses) === 0): ?>
          <p class="mb-0 text-muted">No addresses added yet.</p>
        <?php else: ?>
          <?php foreach ($customerAddresses as $a): ?>
            <form method="post" action="my_profile.php" class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
              <input type="hidden" name="form_action" value="delete_address">
              <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
              <input type="hidden" name="address_id" value="<?php echo h($a["address_id"] ?? ""); ?>">
              <span><?php echo h(($a["address"] ?? "") . ", " . ($a["district"] ?? "") . ", " . ($a["province"] ?? "")); ?></span>
              <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="profile-card <?php echo $passwordError !== '' ? 'has-error' : ''; ?>">
        <h5>Update Password</h5>
        <form method="post" action="my_profile.php">
          <input type="hidden" name="form_action" value="update_password">
          <input type="hidden" name="scroll_pos" class="js-scroll-pos" value="0">
          <div class="row">
            <div class="col-md-4 form-group">
              <label>Current Password</label>
              <div class="profile-password-wrap">
                <input type="password" id="current_password" name="current_password" class="form-control" required>
                <button type="button" class="profile-password-toggle" data-target="current_password" aria-label="Show password" title="Show password">&#128065;</button>
              </div>
            </div>
            <div class="col-md-4 form-group">
              <label>New Password</label>
              <div class="profile-password-wrap">
                <input type="password" id="new_password" name="new_password" class="form-control" required>
                <button type="button" class="profile-password-toggle" data-target="new_password" aria-label="Show password" title="Show password">&#128065;</button>
              </div>
              <small id="profilePasswordMsg" style="font-size:12px;color:#6c757d;">Use 8+ chars with uppercase, lowercase, and a number.</small>
            </div>
            <div class="col-md-4 form-group">
              <label>Confirm Password</label>
              <div class="profile-password-wrap">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                <button type="button" class="profile-password-toggle" data-target="confirm_password" aria-label="Show password" title="Show password">&#128065;</button>
              </div>
              <small id="profileConfirmMsg" style="font-size:12px;"></small>
            </div>
            <div class="col-12 form-group d-flex justify-content-end">
              <button type="submit" class="btn btn-dark">Update Password</button>
            </div>
          </div>
        </form>
      </div>
    <?php elseif ($activeTab === "my-orders"): ?>
      <div class="profile-card"><h5>My Orders</h5><p class="mb-0">Open <a href="my_orders.php">My Orders</a> to view your order history.</p></div>
    <?php elseif ($activeTab === "my-ratings"): ?>
      <div class="profile-card">
        <h5>My Ratings</h5>
        <?php if ($ratingsTotal === 0): ?>
          <p class="mb-0">You have not added any ratings yet.</p>
        <?php else: ?>
          <?php foreach ($myRatings as $rate): ?>
            <div class="border rounded p-3 mb-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <strong><?php echo h($rate["product_name"] ?: ("Product #" . $rate["product_id"])); ?></strong>
                  <div class="small text-muted">Order: <?php echo h($rate["order_id"]); ?></div>
                </div>
                <div class="rating-stars-view"><?php echo str_repeat("★", (int)$rate["rating"]) . str_repeat("☆", 5 - (int)$rate["rating"]); ?></div>
              </div>
              <form method="post" action="my_profile.php?tab=my-ratings">
                <input type="hidden" name="form_action" value="update_rating">
                <input type="hidden" name="ratings_page" value="<?php echo (int)$ratingsPage; ?>">
                <input type="hidden" name="rating_id" value="<?php echo (int)$rate["rating_id"]; ?>">
                <div class="form-row align-items-end">
                  <div class="col-md-2 form-group mb-2">
                    <label class="small">Stars</label>
                    <select name="rating" class="form-control form-control-sm" required>
                      <?php for ($s = 5; $s >= 1; $s--): ?>
                        <option value="<?php echo $s; ?>" <?php echo (int)$rate["rating"] === $s ? "selected" : ""; ?>><?php echo $s; ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>
                  <div class="col-md-7 form-group mb-2">
                    <label class="small">Review</label>
                    <textarea name="review_text" class="form-control form-control-sm" rows="2" required><?php echo h($rate["review_text"]); ?></textarea>
                  </div>
                  <div class="col-md-3 form-group mb-2 d-flex align-items-center">
                    <button type="submit" class="btn btn-dark btn-sm mr-2">Update</button>
                    <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm js-delete-rating" data-rating-id="<?php echo (int)$rate["rating_id"]; ?>">Delete</a>
                  </div>
                </div>
              </form>
              <form method="post" action="my_profile.php?tab=my-ratings" id="delete-rating-<?php echo (int)$rate["rating_id"]; ?>" class="d-none">
                <input type="hidden" name="form_action" value="delete_rating">
                <input type="hidden" name="ratings_page" value="<?php echo (int)$ratingsPage; ?>">
                <input type="hidden" name="rating_id" value="<?php echo (int)$rate["rating_id"]; ?>">
              </form>
            </div>
          <?php endforeach; ?>
          <?php rtel_profile_pagination_markup(
              "my-ratings",
              "ratings_page",
              $ratingsPage,
              $ratingsTotalPages,
              $ratingsTotal,
              $ratingsPerPage
          ); ?>
        <?php endif; ?>
      </div>
    <?php elseif ($activeTab === "my-feedbacks"): ?>
      <div class="profile-card">
        <h5>My Feedbacks</h5>
        <?php if ($feedbacksTotal === 0): ?>
          <p class="mb-0">No feedback submitted yet.</p>
        <?php else: ?>
          <?php foreach ($myFeedbacks as $fb): ?>
            <div class="border rounded p-3 mb-3">
              <div class="d-flex justify-content-between">
                <strong><?php echo h($fb["name"]); ?></strong>
                <small class="text-muted"><?php echo ((int)$fb["status"] === 1) ? "Approved" : "Pending"; ?></small>
              </div>
              <p class="mb-0 mt-2"><?php echo h($fb["comment"]); ?></p>
            </div>
          <?php endforeach; ?>
          <?php rtel_profile_pagination_markup(
              "my-feedbacks",
              "feedbacks_page",
              $feedbacksPage,
              $feedbacksTotalPages,
              $feedbacksTotal,
              $feedbacksPerPage
          ); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  var newPhoneInput = document.querySelector('input[name="new_phone"]');
  var passwordInput = document.querySelector('form input[name="new_password"]');
  var confirmInput = document.querySelector('form input[name="confirm_password"]');
  var passwordMsg = document.getElementById("profilePasswordMsg");
  var confirmMsg = document.getElementById("profileConfirmMsg");
  var allForms = document.querySelectorAll('form[action^="my_profile.php"]');

  allForms.forEach(function (form) {
    form.addEventListener("submit", function () {
      var scrollField = form.querySelector(".js-scroll-pos");
      if (scrollField) {
        scrollField.value = String(window.scrollY || window.pageYOffset || 0);
      }
    });
  });

  var savedScroll = <?php echo (int)$savedScroll; ?>;
  if (savedScroll > 0) {
    window.scrollTo(0, savedScroll);
  }

  if (newPhoneInput) {
    newPhoneInput.addEventListener("input", function () {
      this.value = this.value.replace(/\D/g, "").slice(0, 10);
    });
  }
  var provinceSelect = document.getElementById("new_province");
  var districtSelect = document.getElementById("new_district");
  var districtData = <?php echo json_encode($allowedProvinces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  function refreshDistricts() {
    if (!provinceSelect || !districtSelect) return;
    var p = String(provinceSelect.value || "").trim();
    districtSelect.innerHTML = '<option value="">Select District</option>';
    if (!p || !districtData[p]) return;
    districtData[p].forEach(function (d) {
      var opt = document.createElement("option");
      opt.value = d;
      opt.textContent = d;
      districtSelect.appendChild(opt);
    });
  }
  if (provinceSelect) provinceSelect.addEventListener("change", refreshDistricts);

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

  if (passwordInput) {
    passwordInput.addEventListener("input", function () {
      checkPasswordStrength();
      checkConfirmPassword();
    });
  }
  if (confirmInput) {
    confirmInput.addEventListener("input", checkConfirmPassword);
  }
  document.querySelectorAll(".profile-password-toggle").forEach(function (btn) {
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

  var passwordForm = document.querySelector('form input[name="form_action"][value="update_password"]');
  if (passwordForm && passwordForm.form) {
    passwordForm.form.addEventListener("submit", function (e) {
      var strong = checkPasswordStrength();
      var matched = checkConfirmPassword();
      if (!strong || !matched) {
        e.preventDefault();
      }
    });
  }

  document.querySelectorAll(".js-delete-rating").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var ratingId = btn.getAttribute("data-rating-id");
      if (!ratingId) return;
      var form = document.getElementById("delete-rating-" + ratingId);
      function submitDelete() {
        if (form) form.submit();
      }
      if (window.Swal) {
        Swal.fire({
          title: "Delete this rating?",
          text: "Your review will be permanently removed.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, delete",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#dc3545",
          cancelButtonColor: "#6c757d"
        }).then(function (result) {
          if (result.isConfirmed) submitDelete();
        });
      } else if (confirm("Delete this rating?")) {
        submitDelete();
      }
    });
  });

  function showProfileSwal(type, title, text) {
    if (!window.Swal || !text) return;
    var isError = type === "error";
    Swal.fire({
      icon: isError ? "error" : "success",
      title: title || (isError ? "Action Failed" : "Done"),
      text: text,
      confirmButtonColor: "#111",
      background: "#fff"
    });
  }

  var profileMessage = <?php echo json_encode((string)$profileMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var profileError = <?php echo json_encode((string)$profileError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var passwordMessage = <?php echo json_encode((string)$passwordMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var passwordError = <?php echo json_encode((string)$passwordError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var ratingMessage = <?php echo json_encode((string)$ratingMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var ratingError = <?php echo json_encode((string)$ratingError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  if (profileError) {
    var profileErrorTitle = /image|upload/i.test(profileError) ? "Profile Photo Upload Failed" : "Profile Update Failed";
    showProfileSwal("error", profileErrorTitle, profileError);
  } else if (profileMessage) {
    var profileSuccessTitle = /image|photo/i.test(profileMessage) ? "Profile Photo Updated" : "Profile Updated";
    showProfileSwal("success", profileSuccessTitle, profileMessage);
  } else if (passwordError) {
    showProfileSwal("error", "Password Update Failed", passwordError);
  } else if (passwordMessage) {
    showProfileSwal("success", "Password Updated", passwordMessage);
  } else if (ratingError) {
    showProfileSwal("error", "Rating Action Failed", ratingError);
  } else if (ratingMessage) {
    showProfileSwal("success", /deleted/i.test(ratingMessage) ? "Rating removed" : "Rating updated", ratingMessage);
  }
});
</script>

<?php require "footer.php"; ?>
