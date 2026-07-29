<?php
/**
 * SMTP config for PHPMailer.
 *
 * Priority:
 * 1) mail_config.local.php (if present)
 * 2) Environment variables
 * 3) Defaults
 */

$cfg = [
    "host" => getenv("MAIL_HOST") ?: "smtp.gmail.com",
    "port" => (int)(getenv("MAIL_PORT") ?: 587),
    "username" => getenv("MAIL_USERNAME") ?: "iflaliflal401@gmail.com",   //put your-email@gmail.com
    "password" => (string)(getenv("MAIL_PASSWORD") ?: ""),
    "from_email" => getenv("MAIL_FROM") ?: "iflaliflal401@gmail.com",     //put your-email@gmail.com
    "from_name" => getenv("MAIL_FROM_NAME") ?: "R-TEL Mobile Shop",
    "encryption" => strtolower((string)(getenv("MAIL_ENCRYPTION") ?: "tls")),
];

$localPath = __DIR__ . "/mail_config.local.php";
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $cfg = array_merge($cfg, $local);
    }
}

return $cfg;