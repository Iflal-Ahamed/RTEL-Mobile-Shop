<?php
/**
 * Real AI model integration with provider fallback support.
 *
 * Configure ONE of these (checked in order):
 * - GROQ_API_KEY   → https://api.groq.com/openai/v1/chat/completions
 * - OPENAI_API_KEY → https://api.openai.com/v1/chat/completions (or override base URL)
 * - GEMINI_API_KEY → https://generativelanguage.googleapis.com/v1beta/models/*:generateContent
 *
 * Optional env vars:
 * - AI_MODEL          → override default model id (e.g. llama-3.1-8b-instant, gpt-4o-mini)
 * - OPENAI_API_BASE   → full base for OpenAI-compatible API (must end before /chat/completions)
 */

require_once __DIR__ . "/chat_training_rules.php";

$GLOBALS['rtel_last_ai_reply_source'] = 'fallback';
$GLOBALS['rtel_last_ai_provider'] = '';

function rtel_read_ai_secret($keyName)
{
    $v = trim((string)(getenv($keyName) ?: ''));
    if ($v !== '') {
        return $v;
    }
    $v = trim((string)($_SERVER[$keyName] ?? $_ENV[$keyName] ?? ''));
    if ($v !== '') {
        return $v;
    }
    $local = dirname(__DIR__) . '/config/config.local.php';
    if (is_readable($local)) {
        $cfg = include $local;
        if (is_array($cfg) && !empty($cfg[$keyName])) {
            return trim((string)$cfg[$keyName]);
        }
    }
    return '';
}

/**
 * Returns active AI configuration or null if no API key is set.
 */
function getAiConfiguration()
{
    $groqKey = rtel_read_ai_secret("GROQ_API_KEY");
    if ($groqKey !== "") {
        $model = trim((string)(getenv("AI_MODEL") ?: ($_SERVER["AI_MODEL"] ?? $_ENV["AI_MODEL"] ?? "")));
        if ($model === "") {
            $local = dirname(__DIR__) . '/config/config.local.php';
            if (is_readable($local)) {
                $cfg = include $local;
                if (is_array($cfg) && !empty($cfg["AI_MODEL"])) {
                    $model = trim((string)$cfg["AI_MODEL"]);
                }
            }
        }
        if (!$model || trim($model) === "") {
            $model = "llama-3.1-8b-instant";
        }
        return [
            "provider" => "groq",
            "url" => "https://api.groq.com/openai/v1/chat/completions",
            "api_key" => trim($groqKey),
            "model" => trim($model),
        ];
    }

    $openaiKey = rtel_read_ai_secret("OPENAI_API_KEY");
    if ($openaiKey !== "") {
        $model = trim((string)(getenv("AI_MODEL") ?: ($_SERVER["AI_MODEL"] ?? $_ENV["AI_MODEL"] ?? "")));
        if ($model === "") {
            $local = dirname(__DIR__) . '/config/config.local.php';
            if (is_readable($local)) {
                $cfg = include $local;
                if (is_array($cfg) && !empty($cfg["AI_MODEL"])) {
                    $model = trim((string)$cfg["AI_MODEL"]);
                }
            }
        }
        if (!$model || trim($model) === "") {
            $model = "gpt-4o-mini";
        }
        $base = trim((string)(getenv("OPENAI_API_BASE") ?: ($_SERVER["OPENAI_API_BASE"] ?? $_ENV["OPENAI_API_BASE"] ?? "")));
        if ($base === "") {
            $local = dirname(__DIR__) . '/config/config.local.php';
            if (is_readable($local)) {
                $cfg = include $local;
                if (is_array($cfg) && !empty($cfg["OPENAI_API_BASE"])) {
                    $base = trim((string)$cfg["OPENAI_API_BASE"]);
                }
            }
        }
        if (!$base || trim($base) === "") {
            $base = "https://api.openai.com/v1";
        }
        $base = rtrim(trim($base), "/");
        return [
            "provider" => "openai",
            "url" => $base . "/chat/completions",
            "api_key" => trim($openaiKey),
            "model" => trim($model),
        ];
    }

    $geminiKey = rtel_read_ai_secret("GEMINI_API_KEY");
    if ($geminiKey !== "") {
        $model = trim((string)(getenv("GEMINI_MODEL") ?: ($_SERVER["GEMINI_MODEL"] ?? $_ENV["GEMINI_MODEL"] ?? "")));
        if ($model === "") {
            $local = dirname(__DIR__) . '/config/config.local.php';
            if (is_readable($local)) {
                $cfg = include $local;
                if (is_array($cfg) && !empty($cfg["GEMINI_MODEL"])) {
                    $model = trim((string)$cfg["GEMINI_MODEL"]);
                }
            }
        }
        if (!$model || trim($model) === "") {
            $model = "gemini-1.5-flash";
        }
        return [
            "provider" => "gemini",
            "url" => "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent?key=" . rawurlencode(trim($geminiKey)),
            "api_key" => trim($geminiKey),
            "model" => trim($model),
        ];
    }

    return null;
}

/**
 * Calls the real LLM with messages array (OpenAI format).
 * Returns assistant text or null on failure.
 */
function callRealAiModel(array $messages, $temperature = 0.35)
{
    $cfg = getAiConfiguration();
    if (!$cfg) {
        return null;
    }

    if (($cfg["provider"] ?? "") === "gemini") {
        $textParts = [];
        foreach ($messages as $m) {
            $role = (string)($m["role"] ?? "user");
            $content = trim((string)($m["content"] ?? ""));
            if ($content === "") {
                continue;
            }
            $label = $role === "system" ? "System" : ($role === "assistant" ? "Assistant" : "User");
            $textParts[] = $label . ": " . $content;
        }
        $payload = [
            "contents" => [[
                "parts" => [[
                    "text" => implode("\n\n", $textParts),
                ]]
            ]],
            "generationConfig" => [
                "temperature" => (float)$temperature,
            ],
        ];
        $ch = curl_init($cfg["url"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$response || $httpCode >= 400) {
            return null;
        }
        $result = json_decode($response, true);
        $text = $result["candidates"][0]["content"]["parts"][0]["text"] ?? null;
        return is_string($text) ? trim($text) : null;
    }

    $payload = [
        "model" => $cfg["model"],
        "messages" => $messages,
        "temperature" => $temperature,
    ];
    $ch = curl_init($cfg["url"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $cfg["api_key"],
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$response || $httpCode >= 400) {
        return null;
    }
    $result = json_decode($response, true);
    $text = $result["choices"][0]["message"]["content"] ?? null;
    return is_string($text) ? trim($text) : null;
}

/**
 * Builds user context string from DB products for grounding.
 */
function formatProductsForAiContext(array $products)
{
    if (count($products) === 0) {
        return "(No matching in-stock products in the R-TEL catalog for this query.)";
    }
    $blocks = [];
    foreach ($products as $p) {
        $pid = (string)($p["product_id"] ?? "");
        $name = (string)($p["name"] ?? "");
        $modal = (string)($p["modal"] ?? "");
        $brand = (string)($p["brand_name"] ?? "");
        $price = number_format((float)($p["price"] ?? 0), 2);
        $cprice = (float)($p["cprice"] ?? 0);
        $saleLine = "";
        if ($cprice > 0 && $cprice > (float)($p["price"] ?? 0)) {
            $saleLine = "\nCompare price on site: Rs. " . number_format($cprice, 2);
        }
        $qty = (int)($p["quantity"] ?? 0);
        $listed = "Listed on R-TEL: yes. In stock in system: yes (" . $qty . " unit(s) available).";
        $url = (string)($p["product_url"] ?? ("product.php?product_id=" . $pid));
        $desc = (string)($p["description"] ?? "");
        if (mb_strlen($desc) > 2200) {
            $desc = mb_substr($desc, 0, 2200) . "…";
        }
        $featureBlob = trim((string)($p["feature_blob"] ?? ""));
        if (mb_strlen($featureBlob) > 2200) {
            $featureBlob = mb_substr($featureBlob, 0, 2200) . "…";
        }
        if ($desc === "" && $featureBlob === "") {
            $desc = "(No description/spec rows stored for this product in the database.)";
        } elseif ($desc === "") {
            $desc = "(No description text stored for this product in the database.)";
        }
        $blocks[] = sprintf(
            "---\nProduct ID: %s\nName: %s\nModel/SKU field: %s\nBrand: %s\nPrice: Rs. %s%s\n%s\nProduct page path: %s\nDescription from website:\n%s\n\nStructured DB specs rows:\n%s",
            $pid,
            $name,
            $modal !== "" ? $modal : "(none)",
            $brand !== "" ? $brand : "(none)",
            $price,
            $saleLine,
            $listed,
            $url,
            $desc,
            $featureBlob !== "" ? $featureBlob : "(No feature rows in tblproduct_feature.)"
        );
    }
    return implode("\n\n", $blocks);
}

/**
 * Primary reply: uses real AI when configured; otherwise returns draft.
 *
 * @param string $userMessage User text.
 * @param string $draftReply Fallback text from rules/DB.
 * @param array $products Product rows from DB (may be empty).
 * @param string|null $faqHint Optional FAQ snippet already matched.
 */
function generateAssistantReplyWithRealAi($userMessage, $draftReply, array $products, $faqHint = null)
{
    $GLOBALS['rtel_last_ai_reply_source'] = 'fallback';
    $GLOBALS['rtel_last_ai_provider'] = '';
    $cfg = getAiConfiguration();
    if (!$cfg) {
        return $draftReply;
    }

    $productBlock = formatProductsForAiContext($products);
    $faqBlock = $faqHint ? "Suggested FAQ answer (use or paraphrase if it fits):\n" . $faqHint . "\n\n" : "";

    $nCatalog = count($products);
    $catalogHint = "";
    if ($nCatalog === 1) {
        $catalogHint = "Only one catalog product matched this query. Answer only about this product — do not list or suggest other phones unless the user asks for alternatives.\n\n";
    } elseif ($nCatalog > 1) {
        $catalogHint = "$nCatalog catalog products matched—discuss only these rows by exact name from the catalog. Do not add models that are not listed below.\n\n";
    }

    $userContent = "User question:\n" . $userMessage . "\n\n"
        . $faqBlock
        . $catalogHint
        . "Catalog product records (only use these for product facts, features, price, and availability):\n"
        . $productBlock . "\n\n"
        . "Internal draft reply (you may improve wording but keep facts):\n"
        . $draftReply;

    $messages = [
        ["role" => "system", "content" => getAssistantSystemPrompt()],
        ["role" => "user", "content" => $userContent],
    ];

    $aiText = callRealAiModel($messages, 0.35);
    if ($aiText !== null && $aiText !== "") {
        $GLOBALS['rtel_last_ai_reply_source'] = 'ai';
        $GLOBALS['rtel_last_ai_provider'] = (string)($cfg["provider"] ?? '');
        return $aiText;
    }
    return $draftReply;
}

function rtel_get_last_ai_reply_source()
{
    return (string)($GLOBALS['rtel_last_ai_reply_source'] ?? 'fallback');
}

function rtel_get_last_ai_provider()
{
    return (string)($GLOBALS['rtel_last_ai_provider'] ?? '');
}
