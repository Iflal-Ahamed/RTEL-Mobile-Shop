(function () {
  var KEY = "rtel_theme_mode";
  var root = document.documentElement;
  var buttonId = "rtThemeToggle";

  function preferredTheme() {
    var stored = localStorage.getItem(KEY);
    if (stored === "dark" || stored === "light") return stored;
    if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) return "dark";
    return "light";
  }

  function applyTheme(theme) {
    root.setAttribute("data-theme", theme);
    var btn = document.getElementById(buttonId);
    if (btn) {
      btn.textContent = theme === "dark" ? "\u2600" : "\u263E";
      btn.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");
      btn.title = theme === "dark" ? "Light mode" : "Dark mode";
    }
  }

  function ensureThemeStylesheet() {
    var id = "rtThemeStylesheet";
    if (document.getElementById(id)) return;
    var link = document.createElement("link");
    link.id = id;
    link.rel = "stylesheet";
    link.href = "assets/css/theme.css";
    document.head.appendChild(link);
  }

  function initButton() {
    if (document.getElementById(buttonId)) return;
    var btn = document.createElement("button");
    btn.id = buttonId;
    btn.type = "button";
    btn.className = "rt-theme-toggle";
    btn.addEventListener("click", function () {
      var next = (root.getAttribute("data-theme") === "dark") ? "light" : "dark";
      localStorage.setItem(KEY, next);
      applyTheme(next);
    });
    document.body.appendChild(btn);
    applyTheme(preferredTheme());
  }

  ensureThemeStylesheet();
  applyTheme(preferredTheme());
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initButton);
  } else {
    initButton();
  }
})();
