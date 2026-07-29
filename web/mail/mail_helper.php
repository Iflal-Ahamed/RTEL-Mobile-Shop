<?php
/**
 * PHPMailer wrapper (vendor copied under web/vendor/phpmailer/phpmailer).
 */

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Last mail error for debugging UI/endpoints.
 */
$GLOBALS["rtel_mail_last_error"] = "";

function rtel_get_mail_last_error()
{
    return (string)($GLOBALS["rtel_mail_last_error"] ?? "");
}

function rtel_set_mail_last_error($msg)
{
    $GLOBALS["rtel_mail_last_error"] = (string)$msg;
}

/**
 * Normalized config (trims values; strips spaces in app-password).
 */
function rtel_get_mail_config()
{
    $cfg = require __DIR__ . "/mail_config.php";
    $cfg["host"] = trim((string)($cfg["host"] ?? ""));
    $cfg["username"] = trim((string)($cfg["username"] ?? ""));
    $cfg["from_email"] = trim((string)($cfg["from_email"] ?? ""));
    $cfg["from_name"] = trim((string)($cfg["from_name"] ?? "R-TEL Mobile Shop"));
    $cfg["encryption"] = strtolower(trim((string)($cfg["encryption"] ?? "tls")));
    $cfg["port"] = (int)($cfg["port"] ?? 587);
    $rawPass = trim((string)($cfg["password"] ?? ""));
    // Gmail app passwords are often copied with spaces; remove them safely.
    $cfg["password"] = str_replace(" ", "", $rawPass);
    return $cfg;
}

/**
 * Returns true when SMTP password is configured so sending can be attempted.
 */
function rtel_mail_is_configured()
{
    $cfg = rtel_get_mail_config();
    $password = (string)$cfg["password"];
    if ($password === "" || $cfg["username"] === "") {
        return false;
    }
    if ($password === "PASTE_GMAIL_APP_PASSWORD_HERE" || stripos($password, "APP_PASSWORD") !== false) {
        return false;
    }
    return true;
}

/**
 * Sends one HTML email. Returns true on success, false on failure (logged).
 */
function rtel_send_html_email($to, $subject, $htmlBody, $altBody = "")
{
    rtel_set_mail_last_error("");
    $to = trim((string)$to);
    if ($to === "" || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        rtel_set_mail_last_error("Invalid recipient email.");
        return false;
    }
    if (!rtel_mail_is_configured()) {
        $msg = "R-TEL mail: not configured (set MAIL_PASSWORD/app password).";
        error_log($msg);
        rtel_set_mail_last_error($msg);
        return false;
    }

    // PHPMailer lives under web/vendor, while this file is web/mail.
    $base = __DIR__ . "/../vendor/phpmailer/phpmailer/";
    if (!file_exists($base . "PHPMailer.php")) {
        // Fallback for older layouts if present.
        $base = __DIR__ . "/vendor/phpmailer/phpmailer/";
    }
    require_once $base . "Exception.php";
    require_once $base . "PHPMailer.php";
    require_once $base . "SMTP.php";

    $cfg = rtel_get_mail_config();
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg["username"];
        $mail->Password = $cfg["password"];
        $mail->Port = $cfg["port"];
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        if ($cfg["encryption"] === "ssl") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom($cfg["from_email"], $cfg["from_name"]);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody !== "" ? $altBody : strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody));
        $mail->send();
        return true;
    } catch (Exception $e) {
        $err = "R-TEL mail error: " . $mail->ErrorInfo;
        error_log($err);
        rtel_set_mail_last_error($err);
        return false;
    }
}
