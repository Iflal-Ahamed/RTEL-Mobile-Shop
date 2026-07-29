<?php
if (!function_exists('rtel_image_url')) {
    require_once __DIR__ . '/../includes/rtel_paths.php';
}
if (!function_exists('rtel_header_logo_url')) {
    function rtel_header_logo_url()
    {
        $root = realpath(__DIR__ . '/..');
        if ($root) {
            $a = $root . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'header_logo.jpg';
            $b = $root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'header_logo.jpg';
            if (is_file($a) || is_file($b)) {
                return rtel_image_url('header_logo.jpg');
            }
        }
        return rtel_image_url('logo.jpg');
    }
}
if (!function_exists('rtel_favicon_url')) {
    function rtel_favicon_url()
    {
        $root = realpath(__DIR__ . '/..');
        if ($root) {
            $a = $root . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'favicon.png';
            $b = $root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'favicon.png';
            if (is_file($a) || is_file($b)) {
                return rtel_image_url('favicon.png');
            }
        }
        return rtel_image_url('logo.jpg');
    }
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$seasonalEffectEnabled = 1;
$seasonalEffectTheme = 'auto';
$seasonalEffectEmojis = '';
include_once "connection.php";
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcontact (
        no INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        whatsapp VARCHAR(255) NOT NULL DEFAULT '',
        insta VARCHAR(255) NOT NULL DEFAULT '',
        fb VARCHAR(255) NOT NULL DEFAULT ''
    )");
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_enabled TINYINT(1) NOT NULL DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_theme VARCHAR(30) NOT NULL DEFAULT 'auto'");
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS seasonal_effect_emojis TEXT NULL");
    $resSeason = mysqli_query($conn, "SELECT seasonal_effect_enabled, seasonal_effect_theme, seasonal_effect_emojis FROM tblcontact ORDER BY no ASC LIMIT 1");
    if ($resSeason && ($rowSeason = mysqli_fetch_assoc($resSeason))) {
        $seasonalEffectEnabled = (int)($rowSeason['seasonal_effect_enabled'] ?? 1) === 1 ? 1 : 0;
        $theme = strtolower(trim((string)($rowSeason['seasonal_effect_theme'] ?? 'auto')));
        $allowed = ['auto', 'stars', 'snow', 'christmas', 'hearts', 'flowers', 'halloween'];
        $seasonalEffectTheme = in_array($theme, $allowed, true) ? $theme : 'auto';
        $seasonalEffectEmojis = trim((string)($rowSeason['seasonal_effect_emojis'] ?? ''));
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_lang = isset($_COOKIE['site_lang']) ? $_COOKIE['site_lang'] : 'en';
$supported_langs = ['en', 'ta', 'si'];
if (!in_array($current_lang, $supported_langs, true)) {
    $current_lang = 'en';
}
$lang_labels = [
    'en' => 'Languages',
    'ta' => 'மொழிகள்',
    'si' => 'භාෂා'
];

$navCartCount = 0;
$navWishlistCount = 0;
$navCustomerName = '';
$navGreetingName = 'Customer';
$navIsRegularCustomer = false;
$navProfileImageUrl = '';
$isLoggedInCustomer = isset($_SESSION['user_id']) && trim((string)$_SESSION['user_id']) !== '';
if ($isLoggedInCustomer) {
    if (!function_exists('rtel_customer_display_name_column')) {
        require_once __DIR__ . '/includes/rtel_db_helpers.php';
    }
    $userId = (string)$_SESSION['user_id'];
    $custCol = rtel_customer_display_name_column($conn);

    $cartCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblcart WHERE cus_id = ?");
    if ($cartCountStmt) {
        $cartCountStmt->bind_param("s", $userId);
        $cartCountStmt->execute();
        $cartCountRow = $cartCountStmt->get_result()->fetch_assoc();
        $navCartCount = (int)($cartCountRow['total'] ?? 0);
        $cartCountStmt->close();
    }

    $wishlistCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tblwish_list WHERE cus_id = ?");
    if ($wishlistCountStmt) {
        $wishlistCountStmt->bind_param("s", $userId);
        $wishlistCountStmt->execute();
        $wishlistCountRow = $wishlistCountStmt->get_result()->fetch_assoc();
        $navWishlistCount = (int)($wishlistCountRow['total'] ?? 0);
        $wishlistCountStmt->close();
    }

    mysqli_query($conn, "ALTER TABLE tblcustomer ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NOT NULL DEFAULT ''");
    $profileStmt = $conn->prepare("SELECT `{$custCol}` AS customer_name, profile_image, (SELECT COUNT(*) FROM tblorder WHERE cus_id = ?) AS order_count FROM tblcustomer WHERE cus_id = ? LIMIT 1");
    if ($profileStmt) {
        $profileStmt->bind_param("ss", $userId, $userId);
        $profileStmt->execute();
        $profileRow = $profileStmt->get_result()->fetch_assoc();
        if (!$profileRow) {
            // Session may be stale after DB cleanup; treat as guest immediately.
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
            $isLoggedInCustomer = false;
            $profileStmt->close();
        } else {
        $navCustomerName = trim((string)($profileRow['customer_name'] ?? ''));
        if ($navCustomerName === '') {
            $navCustomerName = 'Customer';
        }
        $parts = preg_split('/\s+/', $navCustomerName);
        $firstName = trim((string)($parts[0] ?? $navCustomerName));
        if ($firstName === '') $firstName = 'Customer';
        $navGreetingName = mb_substr($firstName, 0, 12);
        $navIsRegularCustomer = ((int)($profileRow['order_count'] ?? 0) >= 3);
        $profileImage = trim((string)($profileRow['profile_image'] ?? ''));
        if ($profileImage !== '') {
            $safeImage = basename($profileImage);
            $root = realpath(__DIR__ . '/..');
            $fullPath = $root ? ($root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'customer_profiles' . DIRECTORY_SEPARATOR . $safeImage) : '';
            if ($fullPath !== '' && is_file($fullPath)) {
                $navProfileImageUrl = '../images/customer_profiles/' . rawurlencode($safeImage) . '?v=' . (string)@filemtime($fullPath);
            }
        }
        $profileStmt->close();
        }
    } else {
        $navCustomerName = 'Customer';
        $navGreetingName = 'Customer';
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>">
  <head>
    <title>R-tel Mobile Shop</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <script>
      (function () {
        try {
          var saved = localStorage.getItem("rtel_theme_mode");
          var theme = (saved === "dark" || saved === "light")
            ? saved
            : ((window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light");
          document.documentElement.setAttribute("data-theme", theme);
        } catch (e) {}
      })();
    </script>
    
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
    <link rel="stylesheet" href="css/animate.css">
    
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/magnific-popup.css">

    <link rel="stylesheet" href="css/aos.css">

    <link rel="stylesheet" href="css/ionicons.min.css">

    <link rel="stylesheet" href="css/bootstrap-datepicker.css">
    <link rel="stylesheet" href="css/jquery.timepicker.css">

    
    <link rel="stylesheet" href="css/flaticon.css">
    <link rel="stylesheet" href="css/icomoon.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/language.js" defer></script>
	<link rel="shortcut icon" href="<?php echo htmlspecialchars(rtel_favicon_url(), ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
	<style>
		.chat-wrapper {
    width: 100%;
    max-width: 1200px;   /* 🔥 wider card */
    margin: 30px auto;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    height: 90vh;        /* 🔥 taller chat */
    background: #f5f5f5;
}
/* chat area */
.chat-box {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}


/* avatar */
.avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
}

/* timestamp */
.time {
    font-size: 10px;
    opacity: 0.6;
    margin-top: 4px;
}

/* input area fixed */
.chat-input-area {
    display: flex;
    padding: 10px;
    background: #fff;
    border-top: 1px solid #ddd;
}

.chat-input-area input {
    flex: 1;
    padding: 12px;
    border: none;
    outline: none;
    font-size: 15px;
}

.chat-input-area button {
    padding: 12px 18px;
    background: #000;
    color: #fff;
    border: none;
    cursor: pointer;
}

/* typing indicator */
.typing {
    display: none;
    font-size: 12px;
    padding: 5px 15px;
    color: #666;
}

.msg {
    padding: 10px;
    margin: 5px;
    border-radius: 10px;
    max-width: 70%;
}

.user {
    background: #007bff;
    color: white;
    margin-left: auto;
}

.ai {
    background: #eee;
}

.product-card {
    display: flex;
    gap: 10px;
    background: white;
    padding: 10px;
    margin: 8px;
    border-radius: 10px;
    align-items: center;
}

.nav-count-badge {
    display: inline-flex;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    margin-left: 6px;
    padding: 0 6px;
    color: #fff;
    background: #111;
}
.nav-profile-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 6px;
    border: 1px solid rgba(255,255,255,.4);
}

.search-form,
.search-wrapper,
.sidebar,
.sidebar-box {
    position: relative;
    overflow: visible !important;
}

.search-form {
    z-index: 9000;
}

.search-zone,
.search-zone .container,
.search-zone .row,
.search-zone .col-lg-6,
.search-zone .sidebar,
.search-zone .sidebar-box {
    position: relative;
    z-index: 9500;
    overflow: visible !important;
}

.search-wrapper {
    width: 100% !important;
    max-width: 460px;
}

#searchInput {
    height: 48px;
    border: 1px solid #d6dbe2;
    border-radius: 12px;
    padding: 10px 14px;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
    background: linear-gradient(180deg, #ffffff, #fcfcfc);
}

#searchInput:focus {
    border-color: #111;
    box-shadow: 0 0 0 3px rgba(10, 10, 10, 0.1);
    outline: none;
}

.search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    z-index: 9999 !important;
    background: #fff;
    border: 1px solid #dfdfdf;
    border-radius: 12px;
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.16);
    max-height: 320px;
    overflow-y: auto;
    overflow-x: hidden;
    display: none;
    scrollbar-width: thin;
    scrollbar-color: #9a9a9a #f1f1f1;
}

.search-dropdown::-webkit-scrollbar {
    width: 9px;
}

.search-dropdown::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.search-dropdown::-webkit-scrollbar-thumb {
    background: #9a9a9a;
    border-radius: 10px;
}

.search-dropdown::-webkit-scrollbar-thumb:hover {
    background: #777;
}

.search-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    color: #1f1f1f;
    text-decoration: none;
    border-bottom: 1px solid #f2f2f2;
    transition: background .18s ease;
}

.search-item:last-child {
    border-bottom: none;
}

.search-item img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 8px;
}

.search-item:hover {
    background: #f7f7f7;
}

.search-item-title {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.2;
}

/* MOBILE */
@media (max-width: 768px) {
    .chat-wrapper {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        max-width: 100%;
    }

    .msg {
        max-width: 85%;
    }
}

/* Keep product cards same size across listing pages. */
.product {
    height: 100%;
    display: flex;
    flex-direction: column;
}
.product .img-prod {
    min-height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.product .img-prod img {
    width: 100%;
    height: 240px;
    object-fit: cover;
}
.product .text {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.product .text h3 {
    min-height: 48px;
}
@media (max-width: 767.98px) {
    .product .img-prod {
        min-height: 200px;
    }
    .product .img-prod img {
        height: 200px;
    }
    .product .text h3 {
        min-height: auto;
    }
}

/* Seasonal fall effects (auto by month) */
#seasonal-fall-layer {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: 9998;
}
.seasonal-fall-item {
    position: absolute;
    top: -12vh;
    user-select: none;
    will-change: transform, opacity;
    animation-name: seasonalFallDown, seasonalSway;
    animation-timing-function: linear, ease-in-out;
    animation-iteration-count: 1, infinite;
}
@keyframes seasonalFallDown {
    0% { transform: translateY(0); opacity: 0; }
    10% { opacity: 1; }
    100% { transform: translateY(115vh); opacity: 0.95; }
}
@keyframes seasonalSway {
    0% { margin-left: -6px; }
    50% { margin-left: 8px; }
    100% { margin-left: -6px; }
}
@media (prefers-reduced-motion: reduce) {
    #seasonal-fall-layer { display: none !important; }
}

	</style>
  </head>
  <body class="goto-here">
    <nav class="navbar navbar-expand-lg navbar-dark ftco_navbar bg-dark ftco-navbar-light" id="ftco-navbar">
	    <div class="container">
	      <a class="navbar-brand d-flex align-items-center" href="index.php">
	        <img src="<?php echo htmlspecialchars(rtel_header_logo_url(), ENT_QUOTES, 'UTF-8'); ?>" alt="R-tel logo" style="width:32px;height:32px;object-fit:cover;border-radius:50%;margin-right:10px;">
	        <span data-i18n="brand_name">R-tel Mobile Shop</span>
	      </a>
	      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
	        <span class="oi oi-menu"></span> <span data-i18n="menu_label">Menu</span>
	      </button>

	      <div class="collapse navbar-collapse" id="ftco-nav">
			<ul class="navbar-nav ml-auto">

			<li class="nav-item <?php if($current_page == 'index.php') echo 'active'; ?>">
				<a href="index.php" class="nav-link" data-i18n="nav_home">Home</a>
			</li>
			<li class="nav-item <?php if($current_page == 'shop.php') echo 'active'; ?>">
				<a href="shop.php" class="nav-link">Shop</a>
			</li>


			<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="dropdown04" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span id="languageDropdownLabel"><?php echo htmlspecialchars($lang_labels[$current_lang], ENT_QUOTES, 'UTF-8'); ?></span></a>
						<div class="dropdown-menu" aria-labelledby="dropdown04">
							<a class="dropdown-item language-option" href="#" data-lang="en" data-i18n="lang_english">English</a>
							<a class="dropdown-item language-option" href="#" data-lang="ta" data-i18n="lang_tamil">Tamil</a>
							<a class="dropdown-item language-option" href="#" data-lang="si" data-i18n="lang_sinhala">Sinhala</a>
						</div>
            	</li>
			<li class="nav-item <?php if($current_page == 'chat.php') echo 'active'; ?>">
				<a href="chat.php" class="nav-link" data-i18n="nav_ai_assistant">AI-Assistant</a>
			</li>

			<?php if($isLoggedInCustomer) { ?>

				<!-- LOGGED IN USER -->
				<li class="nav-item">
					<a href="wishlist.php" class="nav-link">
						<span class="icon-heart" style="color:red;"></span> <span data-i18n="nav_wishlist">Wishlist</span>
                        <span class="nav-count-badge" id="wishlistCountBadge"><?php echo (int)$navWishlistCount; ?></span>
					</a>
				</li>

				<li class="nav-item">
					<a href="cart.php" class="nav-link">
						<span class="icon-shopping_cart"></span> <span data-i18n="nav_cart">Cart</span>
                        <span class="nav-count-badge" id="cartCountBadge"><?php echo (int)$navCartCount; ?></span>
					</a>
				</li>

                <li class="nav-item <?php if($current_page == 'my_profile.php') echo 'active'; ?>">
					<a href="my_profile.php" class="nav-link">
                        <?php if ($navProfileImageUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($navProfileImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="nav-profile-avatar">
                        <?php endif; ?>
                        Hi, <?php echo htmlspecialchars($navGreetingName, ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($navIsRegularCustomer): ?><span title="Regular customer" aria-label="Regular customer">👑</span><?php endif; ?>
                        <span aria-hidden="true">→</span>
                    </a>
				</li>

			<?php } else { ?>

				<!-- GUEST USER -->

				<li class="nav-item <?php if($current_page == 'register.php') echo 'active'; ?>">
					<a href="register.php" class="nav-link" data-i18n="nav_register">Register</a>
				</li>

				<li class="nav-item <?php if($current_page == 'login.php') echo 'active'; ?>">
					<a href="login.php" class="nav-link" data-i18n="nav_login">Login</a>
				</li>


				<!-- BLOCKED FEATURES (redirect to register/login) -->

				<li class="nav-item">
					<a href="login.php" class="nav-link">
						<span class="icon-heart"></span> <span data-i18n="nav_wishlist">Wishlist</span>
					</a>
				</li>

				<li class="nav-item">
					<a href="login.php" class="nav-link">
						<span class="icon-shopping_cart"></span> <span data-i18n="nav_cart">Cart</span>
					</a>
				</li>

			<?php } ?>

		</ul>
	      </div>
	    </div>
	  </nav>
      <?php if ($seasonalEffectEnabled === 1): ?>
      <div id="seasonal-fall-layer" aria-hidden="true"></div>
      <script>
      (function () {
          const layer = document.getElementById('seasonal-fall-layer');
          if (!layer) return;
          if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

          const month = new Date().getMonth() + 1;
          const forcedTheme = <?php echo json_encode($seasonalEffectTheme); ?>;
          const customEmojiRaw = <?php echo json_encode($seasonalEffectEmojis); ?>;
          let symbols = ['\u2728', '\u2605', '\u22c6']; // default: stars
          let color = '#ffd43b';

          function applyTheme(theme) {
              if (theme === 'stars') {
                  symbols = ['\u2728', '\u2605', '\u22c6'];
                  color = '#ffd43b';
              } else if (theme === 'snow') {
                  symbols = ['\u2744\ufe0f', '\u2728'];
                  color = '#f8fafc';
              } else if (theme === 'christmas') {
                  symbols = ['\u2744\ufe0f', '\ud83c\udf84', '\ud83c\udf81'];
                  color = '#ffffff';
              } else if (theme === 'hearts') {
                  symbols = ['\u2764\ufe0f', '\u2728'];
                  color = '#ff5f6d';
              } else if (theme === 'flowers') {
                  symbols = ['\ud83c\udf38', '\ud83c\udf3c', '\u2728'];
                  color = '#ff66c4';
              } else if (theme === 'halloween') {
                  symbols = ['\ud83c\udf83', '\u2728'];
                  color = '#ff9f1c';
              } else {
                  if (month === 12) applyTheme('christmas');
                  else if (month === 1) applyTheme('snow');
                  else if (month === 2) applyTheme('hearts');
                  else if (month >= 3 && month <= 5) applyTheme('flowers');
                  else if (month === 10) applyTheme('halloween');
                  else applyTheme('stars');
              }
          }
          applyTheme(forcedTheme);
          const customSymbols = String(customEmojiRaw || '').trim().split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
          if (customSymbols.length > 0) {
              symbols = customSymbols;
          }

          const isMobile = window.innerWidth < 768;
          const maxItems = isMobile ? 20 : 40;
          let activeCount = 0;

          function spawnItem() {
              if (activeCount >= maxItems) return;
              const node = document.createElement('span');
              node.className = 'seasonal-fall-item';
              node.textContent = symbols[Math.floor(Math.random() * symbols.length)];
              node.style.left = Math.random() * 100 + 'vw';
              node.style.fontSize = (Math.random() * 12 + 12) + 'px';
              node.style.opacity = String(Math.random() * 0.5 + 0.45);
              node.style.color = color;
              const fallDuration = Math.random() * 6 + 7; // 7s - 13s
              const swayDuration = Math.random() * 2 + 2.5; // 2.5s - 4.5s
              node.style.animationDuration = fallDuration + 's, ' + swayDuration + 's';
              layer.appendChild(node);
              activeCount++;

              window.setTimeout(function () {
                  if (node.parentNode) node.parentNode.removeChild(node);
                  activeCount = Math.max(0, activeCount - 1);
              }, Math.round(fallDuration * 1000) + 400);
          }

          const burst = isMobile ? 4 : 8;
          for (let i = 0; i < burst; i++) spawnItem();
          window.setInterval(spawnItem, isMobile ? 650 : 420);
      })();
      </script>
      <?php endif; ?>