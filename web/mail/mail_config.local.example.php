<?php
/**
 * Local SMTP overrides (DO NOT COMMIT).
 *
 * Gmail setup:
 * - MAIL_USERNAME: your Gmail address
 * - MAIL_PASSWORD: Google App Password (16 chars)
 * - encryption: tls, port 587
 */
return [
    "host" => "smtp.gmail.com",
    "port" => 587,
    "username" => "your-email@gmail.com",
    "password" => "YOUR_GMAIL_APP_PASSWORD",
    "from_email" => "your-email@gmail.com",
    "from_name" => "R-TEL Mobile Shop",
    "encryption" => "tls",
];