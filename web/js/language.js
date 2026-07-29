/* Lightweight client-side translations for shared UI text */
(function () {
  const LANG_KEY = "site_lang";
  const SUPPORTED_LANGS = ["en", "ta", "si"];
  let currentLang = "en";
  let translateObserver = null;
  let translateTimer = null;
  const translateCache = new Map();

  const translations = {
    en: {
      brand_name: "R-tel Mobile Shop",
      menu_label: "Menu",
      nav_home: "Home",
      nav_languages: "Languages",
      lang_english: "English",
      lang_tamil: "Tamil",
      lang_sinhala: "Sinhala",
      nav_ai_assistant: "AI-Assistant",
      nav_wishlist: "Wishlist",
      nav_cart: "Cart",
      nav_logout: "Logout",
      nav_register: "Register",
      nav_login: "Login",
      leave_your: "Leave your",
      comments: "Comments!",
      placeholder_your_name: "Your Name",
      placeholder_search_products: "Search products...",
      placeholder_write_thoughts: "Write your thoughts...",
      send_comment: "Send Comment",
      footer_menu: "Menu",
      footer_about: "About",
      footer_help: "Help",
      footer_privacy: "Privacy Policy",
      footer_terms: "Terms & Conditions",
      copyright: "Copyright",
      all_rights_reserved: "All rights reserved"
    },
    ta: {
      brand_name: "R-tel மொபைல் கடை",
      menu_label: "மெனு",
      nav_home: "முகப்பு",
      nav_languages: "மொழிகள்",
      lang_english: "ஆங்கிலம்",
      lang_tamil: "தமிழ்",
      lang_sinhala: "சிங்களம்",
      nav_ai_assistant: "AI உதவியாளர்",
      nav_wishlist: "விருப்பப் பட்டியல்",
      nav_cart: "கூடை",
      nav_logout: "வெளியேறு",
      nav_register: "பதிவு செய்",
      nav_login: "உள்நுழை",
      leave_your: "உங்கள்",
      comments: "கருத்துகளை பதிவிடுங்கள்!",
      placeholder_your_name: "உங்கள் பெயர்",
      placeholder_search_products: "பொருட்களை தேடுங்கள்...",
      placeholder_write_thoughts: "உங்கள் கருத்தை எழுதுங்கள்...",
      send_comment: "கருத்தை அனுப்பு",
      footer_menu: "மெனு",
      footer_about: "எங்களை பற்றி",
      footer_help: "உதவி",
      footer_privacy: "தனியுரிமைக் கொள்கை",
      footer_terms: "விதிமுறைகள் & நிபந்தனைகள்",
      copyright: "பதிப்புரிமை",
      all_rights_reserved: "அனைத்து உரிமைகளும் பாதுகாக்கப்பட்டவை"
    },
    si: {
      brand_name: "R-tel ජංගම වෙළඳසැල",
      menu_label: "මෙනුව",
      nav_home: "මුල් පිටුව",
      nav_languages: "භාෂා",
      lang_english: "ඉංග්‍රීසි",
      lang_tamil: "දෙමළ",
      lang_sinhala: "සිංහල",
      nav_ai_assistant: "AI සහායකයා",
      nav_wishlist: "කැමති ලැයිස්තුව",
      nav_cart: "සාප්පු කූඩය",
      nav_logout: "ඉවත් වන්න",
      nav_register: "ලියාපදිංචි වන්න",
      nav_login: "පිවිසෙන්න",
      leave_your: "ඔබගේ",
      comments: "අදහස් ලියන්න!",
      placeholder_your_name: "ඔබගේ නම",
      placeholder_search_products: "නිෂ්පාදන සොයන්න...",
      placeholder_write_thoughts: "ඔබගේ අදහස් ලියන්න...",
      send_comment: "අදහස යවන්න",
      footer_menu: "මෙනුව",
      footer_about: "අප ගැන",
      footer_help: "උදව්",
      footer_privacy: "රහස්‍යතා ප්‍රතිපත්තිය",
      footer_terms: "නියම සහ කොන්දේසි",
      copyright: "කර්තෘ අයිතිය",
      all_rights_reserved: "සියලුම හිමිකම් ඇවිරිණි"
    }
  };

  function normalizeLang(lang) {
    return SUPPORTED_LANGS.includes(lang) ? lang : "en";
  }

  function safeGetStoredLang() {
    try {
      return localStorage.getItem(LANG_KEY) || "";
    } catch (error) {
      return "";
    }
  }

  function safeSetStoredLang(lang) {
    try {
      localStorage.setItem(LANG_KEY, lang);
    } catch (error) {
      // Ignore storage errors (private mode, storage disabled, etc.)
    }
  }

  function setLangCookie(lang) {
    document.cookie = "site_lang=" + encodeURIComponent(lang) + ";path=/;max-age=31536000";
  }

  function applyLanguage(lang) {
    const activeLang = normalizeLang(lang);
    const dict = translations[activeLang] || translations.en;
    const fallback = translations.en;
    currentLang = activeLang;

    document.documentElement.lang = activeLang;

    document.querySelectorAll("[data-i18n]").forEach(function (node) {
      const key = node.getAttribute("data-i18n");
      if (!key) return;
      node.textContent = dict[key] || fallback[key] || node.textContent;
    });

    document.querySelectorAll("[data-i18n-placeholder]").forEach(function (node) {
      const key = node.getAttribute("data-i18n-placeholder");
      if (!key) return;
      node.setAttribute("placeholder", dict[key] || fallback[key] || node.getAttribute("placeholder") || "");
    });

    const dropdownLabel = document.getElementById("languageDropdownLabel");
    if (dropdownLabel) {
      dropdownLabel.textContent = dict.nav_languages || fallback.nav_languages || "Languages";
    }

    // Translate dynamic page/content text (e.g., products loaded via AJAX).
    if (activeLang === "en") {
      restoreOriginalTexts(document.body);
    } else {
      queueAutoTranslate(document.body, activeLang);
    }
  }

  function shouldSkipNode(node) {
    if (!node || node.nodeType !== Node.ELEMENT_NODE) return true;
    const tag = node.tagName;
    if (["SCRIPT", "STYLE", "NOSCRIPT", "IFRAME", "SVG", "CODE", "PRE"].includes(tag)) return true;
    if (node.closest("[data-no-auto-translate='true']")) return true;
    return false;
  }

  function getTextNodes(root) {
    if (!root || shouldSkipNode(root)) return [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (textNode) {
        const parent = textNode.parentElement;
        if (!parent || shouldSkipNode(parent)) return NodeFilter.FILTER_REJECT;
        const value = textNode.nodeValue ? textNode.nodeValue.trim() : "";
        if (!value) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    const nodes = [];
    let cur = walker.nextNode();
    while (cur) {
      nodes.push(cur);
      cur = walker.nextNode();
    }
    return nodes;
  }

  function restoreOriginalTexts(root) {
    if (!root) return;

    const elementsWithOriginalText = root.querySelectorAll("[data-original-text]");
    elementsWithOriginalText.forEach(function (el) {
      el.textContent = el.getAttribute("data-original-text") || "";
      el.removeAttribute("data-original-text");
    });

    const elementsWithOriginalPlaceholder = root.querySelectorAll("[data-original-placeholder]");
    elementsWithOriginalPlaceholder.forEach(function (el) {
      el.setAttribute("placeholder", el.getAttribute("data-original-placeholder") || "");
      el.removeAttribute("data-original-placeholder");
    });

    const elementsWithOriginalTitle = root.querySelectorAll("[data-original-title]");
    elementsWithOriginalTitle.forEach(function (el) {
      el.setAttribute("title", el.getAttribute("data-original-title") || "");
      el.removeAttribute("data-original-title");
    });
  }

  function buildTranslationUrl(targetLang, text) {
    return "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=" +
      encodeURIComponent(targetLang) + "&dt=t&q=" + encodeURIComponent(text);
  }

  async function translateText(targetLang, text) {
    const cacheKey = targetLang + "::" + text;
    if (translateCache.has(cacheKey)) {
      return translateCache.get(cacheKey);
    }
    try {
      const response = await fetch(buildTranslationUrl(targetLang, text));
      if (!response.ok) return text;
      const data = await response.json();
      if (!Array.isArray(data) || !Array.isArray(data[0])) return text;
      const translated = data[0].map(function (part) {
        return Array.isArray(part) ? (part[0] || "") : "";
      }).join("");
      translateCache.set(cacheKey, translated || text);
      return translated;
    } catch (error) {
      return text;
    }
  }

  async function translateNodeBatch(root, targetLang) {
    if (!root || targetLang === "en" || targetLang !== currentLang) return;

    const textNodes = getTextNodes(root);
    for (let i = 0; i < textNodes.length; i++) {
      if (targetLang !== currentLang) return;
      const node = textNodes[i];
      const parent = node.parentElement;
      if (!parent) continue;
      if (parent.hasAttribute("data-i18n") || parent.hasAttribute("data-i18n-placeholder")) continue;

      const original = node.nodeValue || "";
      const trimmed = original.trim();
      if (!trimmed) continue;

      if (!parent.hasAttribute("data-original-text")) {
        parent.setAttribute("data-original-text", parent.textContent || "");
      }

      const translated = await translateText(targetLang, trimmed);
      if (translated && translated !== trimmed) {
        node.nodeValue = original.replace(trimmed, translated);
      }
    }

    const translatableAttrs = root.querySelectorAll("input[placeholder], textarea[placeholder], [title]");
    for (let i = 0; i < translatableAttrs.length; i++) {
      if (targetLang !== currentLang) return;
      const el = translatableAttrs[i];
      if (el.hasAttribute("data-i18n-placeholder") || el.hasAttribute("data-i18n")) continue;

      const placeholder = el.getAttribute("placeholder");
      if (placeholder && placeholder.trim()) {
        if (!el.hasAttribute("data-original-placeholder")) {
          el.setAttribute("data-original-placeholder", placeholder);
        }
        const translatedPlaceholder = await translateText(targetLang, placeholder.trim());
        if (translatedPlaceholder) {
          el.setAttribute("placeholder", translatedPlaceholder);
        }
      }

      const title = el.getAttribute("title");
      if (title && title.trim()) {
        if (!el.hasAttribute("data-original-title")) {
          el.setAttribute("data-original-title", title);
        }
        const translatedTitle = await translateText(targetLang, title.trim());
        if (translatedTitle) {
          el.setAttribute("title", translatedTitle);
        }
      }
    }
  }

  function queueAutoTranslate(root, lang) {
    if (translateTimer) {
      clearTimeout(translateTimer);
    }

    translateTimer = setTimeout(function () {
      translateNodeBatch(root, lang);
      translateTimer = null;
    }, 120);
  }

  function setupAutoTranslateObserver() {
    if (translateObserver || !document.body) return;
    translateObserver = new MutationObserver(function (mutations) {
      if (currentLang === "en") return;
      mutations.forEach(function (mutation) {
        if (mutation.type !== "childList") return;
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === Node.ELEMENT_NODE) {
            queueAutoTranslate(node, currentLang);
          }
        });
      });
    });

    translateObserver.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function initLanguageDropdown() {
    const options = document.querySelectorAll(".language-option[data-lang]");
    if (!options.length) return;

    options.forEach(function (option) {
      option.addEventListener("click", function (event) {
        event.preventDefault();
        const selected = normalizeLang(option.getAttribute("data-lang") || "en");
        safeSetStoredLang(selected);
        setLangCookie(selected);
        applyLanguage(selected);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const stored = safeGetStoredLang();
    const cookieMatch = document.cookie.match(/(?:^|;\s*)site_lang=([^;]+)/);
    const cookieLang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : "";
    const initialLang = normalizeLang(stored || cookieLang || "en");

    safeSetStoredLang(initialLang);
    setLangCookie(initialLang);
    initLanguageDropdown();
    setupAutoTranslateObserver();
    applyLanguage(initialLang);
  });

  // Re-apply language when page restores from bfcache.
  window.addEventListener("pageshow", function () {
    const stored = safeGetStoredLang();
    const cookieMatch = document.cookie.match(/(?:^|;\s*)site_lang=([^;]+)/);
    const cookieLang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : "";
    const effectiveLang = normalizeLang(stored || cookieLang || currentLang);
    applyLanguage(effectiveLang);
  });
})();
