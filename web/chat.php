<?php
session_start();
include 'config.php';
require "header.php";
?>

<style>
  /* Main chat page background and spacing */
  .assistant-page {
    background: linear-gradient(180deg, #f8f9fb 0%, #eef1f7 100%);
    border-radius: 14px;
    padding: 20px;
  }

  /* Chat card — compact height; message list scrolls inside */
  .assistant-card {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
  }

  .assistant-card-body {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
  }

  /* Header with subtitle */
  .assistant-card .card-header {
    flex-shrink: 0;
    background: #111;
    color: #fff;
    border: 0;
    padding: 14px 18px;
  }
  .assistant-subtitle {
    display: block;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.8);
  }

  /* Message thread with inline scroll */
  .chat-box {
    height: 420px;
    max-height: 420px;
    min-height: 0;
    flex: 0 0 auto;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: auto;
    padding: 18px;
    background: #f7f8fb;
  }

  /* Bubble styles */
  .msg {
    max-width: 78%;
    margin-bottom: 12px;
    border-radius: 14px;
    padding: 10px 12px;
    line-height: 1.4;
    position: relative;
    word-break: break-word;
    white-space: pre-wrap;
  }
  .msg.user {
    margin-left: auto;
    background: #111;
    color: #fff;
    border-bottom-right-radius: 4px;
  }
  .msg.ai {
    margin-right: auto;
    background: #fff;
    color: #1e1e1e;
    border: 1px solid #eceff4;
    border-bottom-left-radius: 4px;
  }
  .msg.ai ul {
    margin: 0 0 0 16px;
    padding: 0;
  }
  .msg.ai li {
    margin-bottom: 4px;
  }
  .time {
    margin-top: 4px;
    font-size: 10px;
    opacity: 0.7;
  }

  /* Typing indicator */
  .typing {
    display: none;
    flex-shrink: 0;
    font-size: 12px;
    color: #666;
    padding: 0 18px 10px;
    background: #f7f8fb;
  }

  /* Product suggestions card list */
  .chat-product-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin: 6px 0 14px;
  }
  .chat-product-item {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #e8ebf1;
    background: #fff;
    border-radius: 10px;
    padding: 8px;
  }
  .chat-product-item img {
    width: 58px;
    height: 58px;
    border-radius: 8px;
    object-fit: cover;
  }
  .chat-product-item .p-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
  }
  .chat-product-item .p-price {
    font-size: 13px;
    color: #444;
  }

  /* Input area */
  .chat-input-area {
    flex-shrink: 0;
    display: flex;
    gap: 8px;
    padding: 14px;
    background: #fff;
    border-top: 1px solid #eceff4;
  }
  .chat-input-area input {
    flex: 1;
    border: 1px solid #dfe3ea;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px;
    outline: none;
  }
  .chat-input-area input:focus {
    border-color: #111;
  }
  .chat-input-area button {
    background: #111;
    color: #fff;
    border: 0;
    border-radius: 10px;
    padding: 10px 16px;
    font-weight: 600;
  }

  /* Mobile tuning */
  @media (max-width: 768px) {
    .chat-box {
      height: 320px;
      max-height: 320px;
    }
    .msg { max-width: 92%; }
  }
</style>

<section class="ftco-section">
  <div class="container assistant-page">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card assistant-card">
          <div class="card-header">
            <strong>R-TEL AI Assistant</strong>
            <span class="assistant-subtitle">Products, site help, and troubleshooting — answers use your live catalog when you ask by model or budget</span>
          </div>

          <div class="assistant-card-body">
          <div id="chatBox" class="chat-box"></div>
          <div class="typing" id="typing">Assistant is thinking...</div>

          </div>

          <div class="chat-input-area">
            <input type="text" id="userInput" placeholder="e.g. Tell me about Samsung Galaxy A54, or checkout error help" autocomplete="off">
            <button type="button" id="sendBtn">Send</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
/**
 * Tracks current chat session id from backend.
 */
var currentChatSessionId = "";

/**
 * Escapes user content to avoid HTML injection.
 */
function escapeHtml(text) {
  return String(text || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/**
 * Returns current clock string for message timestamp.
 */
function getTime() {
  var d = new Date();
  return d.getHours() + ":" + String(d.getMinutes()).padStart(2, "0");
}

/**
 * Adds a chat bubble to chat history.
 */
function addMessage(text, type) {
  var chatBox = document.getElementById("chatBox");
  var msg = document.createElement("div");
  msg.className = "msg " + type;
  var bodyHtml = escapeHtml(text);
  if (type === "ai") {
    bodyHtml = formatAssistantReply(text);
  }
  msg.innerHTML = bodyHtml + '<div class="time">' + getTime() + '</div>';
  chatBox.appendChild(msg);
  chatBox.scrollTop = chatBox.scrollHeight;
}

function formatAssistantReply(text) {
  var raw = String(text || "").trim();
  if (!raw) return "";
  var lines = raw.split(/\r?\n/).map(function (line) { return line.trim(); }).filter(Boolean);
  var hasListMarkers = lines.some(function (line) {
    return /^(?:[-*•]\s+|\d+[.)]\s+)/.test(line);
  });
  // Render as list only when the reply is naturally list-like.
  if (lines.length <= 1 && !hasListMarkers) {
    return escapeHtml(raw);
  }
  var listItems = lines.map(function (line) {
    // Remove only actual list prefixes (-, *, •, 1), not numeric values like "50,000".
    var clean = line.replace(/^(?:[-*•]\s+|\d+[.)]\s+)/, "");
    return "<li>" + escapeHtml(clean) + "</li>";
  }).join("");
  return "<ul>" + listItems + "</ul>";
}

/**
 * Renders suggested product cards below AI message.
 */
function addProductCards(products) {
  if (!products || !products.length) return;
  var chatBox = document.getElementById("chatBox");
  var wrap = document.createElement("div");
  wrap.className = "chat-product-list";

  products.forEach(function (p) {
    var card = document.createElement("div");
    card.className = "chat-product-item";
    card.innerHTML =
      '<img src="../images/' + escapeHtml(p.image || "smartphone.png") + '" alt="product">' +
      '<div>' +
        '<div class="p-name">' + escapeHtml(p.name) + '</div>' +
        '<div class="p-price">Rs. ' + Number(p.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</div>' +
        '<a href="product.php?product_id=' + encodeURIComponent(String(p.product_id || "")) + '" class="btn btn-sm btn-outline-dark mt-1">View</a>' +
      '</div>';
    wrap.appendChild(card);
  });

  chatBox.appendChild(wrap);
  chatBox.scrollTop = chatBox.scrollHeight;
}

function parseJsonSafe(raw) {
  if (typeof raw !== "string") return {};
  try { return JSON.parse(raw); } catch (_) {}
  var start = raw.indexOf("{");
  var end = raw.lastIndexOf("}");
  if (start !== -1 && end > start) {
    try { return JSON.parse(raw.slice(start, end + 1)); } catch (_) {}
  }
  return {};
}

function logAiReplyMeta(tag, data) {
  try {
    var src = (data && data.reply_source) ? String(data.reply_source) : "fallback";
    var prov = (data && data.ai_provider) ? String(data.ai_provider) : "";
    console.info("[" + String(tag || "R-TEL AI") + "] reply_source:", src, prov ? ("provider: " + prov) : "");
  } catch (_) {}
}

/**
 * Sends user message to chat API and renders response.
 */
function sendMessage() {
  var input = document.getElementById("userInput");
  var typing = document.getElementById("typing");
  var message = input.value.trim();
  if (!message) return;

  addMessage(message, "user");
  input.value = "";
  typing.style.display = "block";

  fetch("ai/chat_api.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      message: message,
      session_id: currentChatSessionId
    })
  })
  .then(function (res) { return res.text(); })
  .then(function (raw) { return parseJsonSafe(raw); })
  .then(function (data) {
    logAiReplyMeta("R-TEL Chat", data);
    if (data && data.session_id) {
      currentChatSessionId = String(data.session_id);
    }
    typing.style.display = "none";
    if (data && data.history_cleared) {
      currentChatSessionId = "";
      var chatBox = document.getElementById("chatBox");
      chatBox.innerHTML = "";
    }
    addMessage(data.reply || "I could not process that request.", "ai");
    addProductCards(data.products || []);
  })
  .catch(function () {
    typing.style.display = "none";
    addMessage("Something went wrong while contacting assistant API.", "ai");
  });
}

/**
 * Binds UI events and adds welcome guide.
 */
document.addEventListener("DOMContentLoaded", function () {
  // Load latest saved chat history for this user.
  fetch("ai/chat_api.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "history" })
  })
  .then(function (res) { return res.text(); })
  .then(function (raw) { return parseJsonSafe(raw); })
  .then(function (data) {
    if (data && data.success && data.session_id) {
      currentChatSessionId = String(data.session_id);
    }
    var restored = false;
    if (data && data.success && Array.isArray(data.messages) && data.messages.length > 0) {
      data.messages.forEach(function (msg) {
        if (!msg || !msg.role || !msg.text) return;
        addMessage(String(msg.text), msg.role === "user" ? "user" : "ai");
      });
      restored = true;
    }
    if (!restored) {
      addMessage("Hi! Ask about any product (try a model name like \"Tell me about Samsung Galaxy A54\"), budgets, cart/checkout, orders — or describe a site bug or error and we will troubleshoot.", "ai");
    }
  })
  .catch(function () {
    addMessage("Hi! Ask about any product (try a model name like \"Tell me about Samsung Galaxy A54\"), budgets, cart/checkout, orders — or describe a site bug or error and we will troubleshoot.", "ai");
  });

  var sendBtn = document.getElementById("sendBtn");
  var input = document.getElementById("userInput");

  sendBtn.addEventListener("click", sendMessage);
  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      sendMessage();
    }
  });

});
</script>

<?php require "footer.php"; ?>