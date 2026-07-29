<?php
/**
 * Demo payment gateway API endpoint.
 * This validates card details and returns a gateway reference token.
 *
 * NOTE:
 * - This is a safe simulated gateway flow for development.
 * - Replace with real gateway SDK (PayHere/Stripe/PayPal) in production.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");

/**
 * Sends JSON response and exits.
 */
function send_json($payload)
{
    echo json_encode($payload);
    exit();
}

function load_local_config()
{
    $path = __DIR__ . "/config/config.local.php";
    if (is_file($path)) {
        $cfg = include $path;
        if (is_array($cfg)) {
            return $cfg;
        }
    }
    return [];
}

function stripe_secret_key()
{
    $cfg = load_local_config();
    $k = trim((string)($cfg["STRIPE_SECRET_KEY"] ?? getenv("STRIPE_SECRET_KEY") ?? ""));
    return $k;
}

function stripe_publishable_key()
{
    $cfg = load_local_config();
    $k = trim((string)($cfg["STRIPE_PUBLISHABLE_KEY"] ?? getenv("STRIPE_PUBLISHABLE_KEY") ?? ""));
    return $k;
}

function stripe_minor_unit($currency)
{
    $currency = strtolower(trim((string)$currency));
    // Stripe zero-decimal currencies.
    $zeroDecimal = ["bif","clp","djf","gnf","jpy","kmf","krw","mga","pyg","rwf","ugx","vnd","vuv","xaf","xof","xpf"];
    return in_array($currency, $zeroDecimal, true) ? 1 : 100;
}

function stripe_api_post($endpoint, array $fields, $secretKey)
{
    $ch = curl_init("https://api.stripe.com/v1/" . ltrim((string)$endpoint, "/"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secretKey,
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $body === "") {
        return [false, "No response from Stripe."];
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        return [false, "Invalid Stripe response."];
    }
    if ($httpCode >= 400) {
        $msg = (string)($json["error"]["message"] ?? "Stripe request failed.");
        return [false, $msg];
    }
    return [true, $json];
}

/**
 * Performs Luhn check for card number validity.
 */
function is_luhn_valid($number)
{
    $number = preg_replace('/\D+/', '', (string)$number);
    if ($number === '') {
        return false;
    }
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) === 0;
}

// Only logged users can verify payment.
if (!isset($_SESSION['user_id'])) {
    send_json(["success" => false, "message" => "Login required for payment verification."]);
}

$data = json_decode(file_get_contents("php://input"), true);
$action = strtolower(trim((string)($data["action"] ?? "")));
$userId = (string)$_SESSION['user_id'];

if ($action === "stripe_config") {
    $pk = stripe_publishable_key();
    if ($pk === "") {
        send_json([
            "success" => true,
            "demo_mode" => true,
            "publishable_key" => "",
            "message" => "Stripe demo mode enabled (no publishable key)."
        ]);
    }
    send_json(["success" => true, "demo_mode" => false, "publishable_key" => $pk]);
}

if ($action === "stripe_create_intent") {
    $secret = stripe_secret_key();
    if ($secret === "") {
        send_json([
            "success" => true,
            "demo_mode" => true,
            "client_secret" => "demo_client_secret_" . strtoupper(substr(uniqid(), -8)),
            "payment_intent_id" => "demo_pi_" . strtoupper(substr(uniqid(), -8)),
            "message" => "Stripe demo intent created."
        ]);
    }
    $amount = (float)($data["amount"] ?? 0);
    // Always use Sri Lankan Rupees for this project.
    $currency = "lkr";
    $minor = (int)round($amount * stripe_minor_unit($currency));
    if ($minor <= 0) {
        send_json(["success" => false, "message" => "Invalid payment amount."]);
    }
    [$ok, $res] = stripe_api_post("payment_intents", [
        "amount" => $minor,
        "currency" => $currency,
        "automatic_payment_methods[enabled]" => "true",
        "metadata[user_id]" => $userId
    ], $secret);
    if (!$ok) {
        send_json(["success" => false, "message" => (string)$res]);
    }
    $clientSecret = (string)($res["client_secret"] ?? "");
    if ($clientSecret === "") {
        send_json(["success" => false, "message" => "Stripe client secret missing."]);
    }
    send_json([
        "success" => true,
        "client_secret" => $clientSecret,
        "payment_intent_id" => (string)($res["id"] ?? "")
    ]);
}

$cardNumber = trim((string)($data["card_number"] ?? ""));
$expiry = trim((string)($data["expiry"] ?? ""));
$cvv = trim((string)($data["cvv"] ?? ""));
$amount = (float)($data["amount"] ?? 0);

// Basic validation for payment fields.
if ($amount <= 0) {
    send_json(["success" => false, "message" => "Invalid payment amount."]);
}
if (!preg_match('/^\d{13,19}$/', preg_replace('/\D+/', '', $cardNumber))) {
    send_json(["success" => false, "message" => "Invalid card number format."]);
}
if (!is_luhn_valid($cardNumber)) {
    send_json(["success" => false, "message" => "Card validation failed."]);
}
if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
    send_json(["success" => false, "message" => "Invalid expiry format. Use MM/YY."]);
}
if (!preg_match('/^\d{3,4}$/', $cvv)) {
    send_json(["success" => false, "message" => "Invalid CVV."]);
}

// Generate gateway reference for verified payment.
$gatewayRef = "GW-" . strtoupper(substr(uniqid(), -10));
send_json([
    "success" => true,
    "message" => "Payment verified successfully.",
    "gateway_ref" => $gatewayRef
]);
