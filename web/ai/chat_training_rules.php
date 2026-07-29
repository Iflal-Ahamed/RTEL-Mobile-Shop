<?php
/**
 * AI training/rules for the R-TEL assistant.
 * Keep all assistant behavior rules in one place so you can tune responses safely.
 */

/**
 * Returns the global system prompt used when an LLM provider key is available.
 */
function getAssistantSystemPrompt()
{
    return "You are R-TEL AI Assistant for a mobile shop website.
- Help with: all product-related questions (specs, stock, prices, comparisons when catalog data is provided), how to use the site (cart, checkout, orders, account), and troubleshooting when something fails or behaves like a bug.
- Reject topics that are unrelated to shopping or this website (e.g. general knowledge, politics, other companies’ unrelated products) politely.
- Be concise, friendly, and accurate.
- Always write in simple English (short sentences, easy words, no jargon).
- If user input is messy, broken, typo-heavy, or mixed language, first infer likely intent, then answer clearly in simple English.
- Product facts MUST come only from the \"Catalog product records\" block when it is present: use Description from website and Structured DB specs rows (tblproduct_feature) as the official spec sources—summarize or quote them; do not invent specifications not written there.
- For each catalog product you mention, clearly state that it is listed on R-TEL and whether it is in stock (use the stock quantity given). If no matching product appears in the catalog block, say it is not listed or not in stock on R-TEL—do not guess.
- If the catalog block contains exactly one product, talk only about that product (no extra recommendations from memory).
- For bugs and errors: give practical user-side steps (refresh, login, browser/cache, retry payment). Do not invent server-side fixes or internal error codes. If the issue may persist, tell them to use the site Feedback form with steps to reproduce.
- Currency format should be: Rs. 12,000.00";
}

/**
 * Returns keywords that define what is allowed.
 */
function getAllowedTopicKeywords()
{
    return [
        // product finding topics
        "phone", "phones", "mobile", "smartphone", "tablet", "watch", "budget", "under", "below", "price", "brand",
        "samsung", "galaxy", "iphone", "redmi", "xiaomi", "vivo", "oppo", "nokia", "realme", "oneplus", "pixel",
        "product", "products", "item", "items", "catalog", "stock", "warranty", "compare", "versus", "vs",
        "accessory", "accessories", "charger", "case", "cover", "cable", "earphone", "headphone",
        // system help & troubleshooting
        "register", "login", "logout", "account", "profile", "password", "wishlist", "cart", "checkout", "order",
        "orders", "shipping", "delivery", "payment", "cod", "card", "coupon", "promotion", "feedback",
        "rating", "review", "language", "search", "ai", "assistant", "site", "website", "page", "button", "link",
        "system", "thanks", "thank you",
        "hello", "hi", "hey", "how are you", "what is your name", "what is your purpose",
        "spec", "specs", "specification", "feature", "features", "detail", "details", "about", "tell me",
        // bugs & errors (match substrings users type)
        "bug", "bugs", "error", "errors", "glitch", "broken", "crash", "frozen", "freeze", "stuck",
        "not working", "doesn't work", "does not work", "won't", "cannot", "can't", "fail", "failed",
        "slow", "timeout", "loading", "blank", "404", "issue", "problem", "troubleshoot", "fix",
        "R-tel", "R-tel mobile shop",
        "hey r-tel", "hey r-tel mobile shop", "erase history", "erase all history", "clear history", "clear all history",
    ];
}

/**
 * Canned answers for core system questions (fast + consistent).
 */
function getSystemFaqRules()
{
    return [
        // Longer phrases first so specific troubleshooting wins over single words like "error"
        "checkout button does nothing" => "Try: refresh the page (F5), confirm you ticked cart items and are logged in, disable browser extensions briefly, try Incognito/another browser, clear cache for this site, then open Cart again and use Checkout. If it still fails, use Feedback in the footer with your browser name and what you clicked.",
        "not working" => "Try refreshing the page, logging out and back in, using another browser or Incognito, and clearing cache/cookies for this site. If payment or checkout fails, check card limits and try again. Report persistent issues via the Feedback form with what you tried and any error message.",
        "does not work" => "Try refreshing the page, logging out and back in, using another browser or Incognito, and clearing cache/cookies for this site. If payment or checkout fails, check card limits and try again. Report persistent issues via the Feedback form with what you tried and any error message.",
        "doesn't work" => "Try refreshing the page, logging out and back in, using another browser or Incognito, and clearing cache/cookies for this site. If payment or checkout fails, check card limits and try again. Report persistent issues via the Feedback form with what you tried and any error message.",
        "payment failed" => "Check card details, balance, and bank SMS approval. Retry once; try another card or COD if available. If it keeps failing, note the exact message and report via Feedback.",
        "site bug" => "Please describe the page and steps (and any error text). Meanwhile try refresh, another browser, and clearing cache. We track fixes through the Feedback form.",
        "bug" => "Sorry you hit an issue. Try refresh, Incognito/another browser, clear cache, and ensure you are logged in. If it repeats, use the Feedback form in the footer with steps to reproduce—we use that to fix site bugs.",
        "error" => "Note the exact message if you can. Try refresh, another browser, and logging in again. For checkout/payment errors, retry after checking card/network. Use Feedback if it keeps happening.",
        "register" => "Use the Register page to create your account, then login to use cart, wishlist, and checkout features.",
        "login" => "Open the Login page and enter your email and password. If you are new, use Register first.",
        "cart" => "In Cart, select items with checkboxes, adjust quantity, and proceed to checkout with selected items only.",
        "checkout" => "Checkout supports selected cart items, coupon application, and shipping charges based on your province/district.",
        "order" => "My Orders shows your order status. Pending orders can be deleted until admin accepts them.",
        "wishlist" => "Use the heart icon to save products to wishlist, then move them to cart anytime.",
        "rating" => "You can add star ratings and reviews for completed orders, then update/delete them in My Ratings.",
        "feedback" => "Submit feedback in the footer form. Your own submissions appear under My Feedbacks.",
        "shipping" => "Shipping charges are calculated using your saved province and district.",
        "coupon" => "Coupons can be applied at checkout if active, not expired, and minimum order amount is met.",
        "thanks" => "You are welcome! Shopping with us is a pleasure. Have a great day!",
        "hello" => "Hello! How can I help you today?",
        "hi" => "Hello! How can I help you today?",
        "hey" => "Hello! How can I help you today?",
        "how are you" => "I'm doing great! How about you?",
        "what is your name" => "I'm R-TEL AI Assistant. You can call me R-TEL.",
        "what is your purpose" => "I'm here to help you with your shopping needs. How can I assist you today?",
        "r-tel" => "R-TEL is a mobile shop website. You can find the best mobile phones and accessories here.",
        "r-tel mobile shop" => "R-TEL is a mobile shop website. You can find the best mobile phones and accessories here.",
        "hey r-tel" => "Hello! How can I help you today?",
        "hey r-tel mobile shop" => "Hello! How can I help you today?"
    ];
}

/**
 * Used when the message is support-shaped but no FAQ keyword matched.
 */
function getDefaultSupportTroubleshootingDraft()
{
    return "Here are steps that usually fix issues on R-TEL: refresh the page (F5), try Incognito or another browser, clear cache/cookies for this site, confirm you are logged in, and retry. For checkout or payment, ensure cart items are selected and try again; check card/network for payment failures. If it still happens, use the Feedback form in the footer with the page name, what you clicked, and any error text so we can investigate.";
}
