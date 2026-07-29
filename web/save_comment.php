<?php
session_start();
include 'connection.php';
require_once __DIR__ . '/mail/mail_notifications.php';
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_POST['ajax']) && $_POST['ajax'] === '1')
);

function send_json($payload) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

if (!isset($_POST['btnComment'])) {
    if ($isAjax) {
        send_json(['success' => false, 'type' => 'error', 'text' => 'Invalid request.']);
    }
    header("Location: index.php");
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$comment = trim($_POST['comment'] ?? '');
$returnTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$returnTo = strtok($returnTo, '#');

$commentCols = [];
$commentColRes = mysqli_query($conn, "SHOW COLUMNS FROM tblcomment");
if ($commentColRes) {
    while ($col = mysqli_fetch_assoc($commentColRes)) {
        $commentCols[] = strtolower((string)$col['Field']);
    }
}
if (!in_array('cus_id', $commentCols, true)) {
    mysqli_query($conn, "ALTER TABLE tblcomment ADD COLUMN cus_id INT(11) NULL AFTER com_id");
}

if ($name === '' || $comment === '') {
    $payload = [
        'type' => 'error',
        'text' => 'Please fill your name and feedback before submitting.'
    ];
    if ($isAjax) {
        send_json(['success' => false] + $payload);
    }
    $_SESSION['comment_notice'] = $payload;
    header("Location: " . $returnTo . "#feedback-section");
    exit();
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $payload = [
        'type' => 'error',
        'text' => 'Please enter a valid email address, or leave it blank.'
    ];
    if ($isAjax) {
        send_json(['success' => false] + $payload);
    }
    $_SESSION['comment_notice'] = $payload;
    header("Location: " . $returnTo . "#feedback-section");
    exit();
}

$safeName = mysqli_real_escape_string($conn, $name);
$safeComment = mysqli_real_escape_string($conn, $comment);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId > 0) {
    $sql = "INSERT INTO tblcomment (cus_id, name, comment, status) VALUES ('$userId', '$safeName', '$safeComment', '0')";
} else {
    $sql = "INSERT INTO tblcomment (name, comment, status) VALUES ('$safeName', '$safeComment', '0')";
}

if (mysqli_query($conn, $sql)) {
    $payload = [
        'type' => 'success',
        'text' => 'Thanks! Your feedback was submitted successfully.'
    ];
    rtel_notify_admin_new_feedback($conn, $name, $comment);
    $thankEmail = $email;
    if ($thankEmail === '') {
        $thankEmail = trim((string)($_SESSION['user_email'] ?? ''));
    }
    rtel_notify_feedback_thanks($thankEmail, $name, $comment);
} else {
    $payload = [
        'type' => 'error',
        'text' => 'Something went wrong while submitting your feedback.'
    ];
}

if ($isAjax) {
    send_json(['success' => $payload['type'] === 'success'] + $payload);
}

$_SESSION['comment_notice'] = $payload;

header("Location: " . $returnTo . "#feedback-section");
exit();
?>