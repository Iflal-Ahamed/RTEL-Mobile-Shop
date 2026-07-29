<?php
/**
 * Shared MIS sidebar navigation.
 * Usage:
 *   $activePage = 'order.php'; // optional, auto-detected by default
 *   require_once __DIR__ . '/includes/sidebar-nav.php';
 *   rtel_render_sidebar_nav($activePage ?? null);
 */
if (!function_exists('rtel_render_sidebar_nav')) {
    require_once __DIR__ . '/auth.php';
    rtel_require_admin_auth();

    if (!function_exists('rtel_header_logo_url')) {
        function rtel_header_logo_url()
        {
            if (!function_exists('rtel_image_url')) {
                require_once dirname(__DIR__, 2) . '/includes/rtel_paths.php';
            }
            $root = realpath(dirname(__DIR__, 2));
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

    function rtel_render_sidebar_nav($activePage = null)
    {
        if (!function_exists('rtel_image_url')) {
            require_once dirname(__DIR__, 2) . '/includes/rtel_paths.php';
        }
        $current = strtolower((string)($activePage ?: basename((string)($_SERVER['PHP_SELF'] ?? ''))));
        $can = static function ($page) {
            return rtel_admin_can_access_page($page);
        };
        $isProducts = in_array($current, ['brand.php', 'category.php', 'product.php', 'allproducts.php', 'bundle.php'], true);
        $isSettings = in_array($current, ['banner.php', 'contactinfo.php', 'logo.php', 'seasonal.php', 'ai_training.php'], true);
        $isProfile = in_array($current, ['profile.php'], true);
        $hasProductsMenu = $can('brand.php') || $can('category.php') || $can('product.php') || $can('allproducts.php') || $can('bundle.php');
        $hasSettingsMenu = $can('banner.php') || $can('contactinfo.php') || $can('logo.php') || $can('seasonal.php') || $can('ai_training.php') || rtel_is_super_admin();

        $isActive = static function ($pages) use ($current) {
            return in_array($current, $pages, true) ? ' active ' : ' ';
        };
        ?>
        <style>
            #sidebar .sidebar-wrapper {
                background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
                box-shadow: 8px 0 24px rgba(2, 6, 23, 0.2);
            }
            #sidebar .header-container {
                margin: 14px 12px 10px;
                padding: 12px;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.08);
                display: flex;
                align-items: center;
                gap: 10px;
            }
            #sidebar .header-container .brand-logo .avatar {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: #fff;
                padding: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #sidebar .header-container .brand-logo img {
                object-fit: contain;
                border-radius: 8px;
            }
            #sidebar .header-container .user-name,
            #sidebar .header-container .user-name a {
                color: #f8fafc !important;
                font-size: 14px;
                font-weight: 700;
                text-decoration: none;
                line-height: 1.25;
            }
            #sidebar .sidebar-menu {
                margin-top: 8px;
            }
            #sidebar .menu .sidebar-item .sidebar-link {
                margin: 4px 10px;
                border-radius: 10px;
                color: #cbd5e1;
                transition: all .2s ease;
            }
            #sidebar .menu .sidebar-item .sidebar-link i {
                color: #93c5fd;
            }
            #sidebar .menu .sidebar-item .sidebar-link:hover {
                background: rgba(59, 130, 246, 0.12);
                color: #f8fafc;
                transform: translateX(2px);
            }
            #sidebar .menu .sidebar-item.active > .sidebar-link,
            #sidebar .menu .sidebar-item.has-sub.active > .sidebar-link {
                background: linear-gradient(90deg, rgba(37,99,235,.28), rgba(59,130,246,.18));
                color: #fff;
                border-left: 3px solid #60a5fa;
            }
            #sidebar .menu .sidebar-item.active > .sidebar-link i,
            #sidebar .menu .sidebar-item.has-sub.active > .sidebar-link i {
                color: #bfdbfe;
            }
            #sidebar .submenu {
                background: transparent;
            }
            #sidebar .submenu .submenu-item a {
                color: #a5b4c7;
                margin: 3px 18px;
                border-radius: 8px;
                transition: all .2s ease;
            }
            #sidebar .submenu .submenu-item.active a,
            #sidebar .submenu .submenu-item a:hover {
                color: #fff;
                background: rgba(59, 130, 246, 0.18);
            }
            #sidebar .logout-item .sidebar-link {
                margin-top: 10px;
                border: 1px solid rgba(239, 68, 68, 0.35);
                background: rgba(239, 68, 68, 0.12);
                color: #fecaca;
            }
            #sidebar .logout-item .sidebar-link i {
                color: #fca5a5;
            }
            #sidebar .logout-item .sidebar-link:hover {
                background: rgba(239, 68, 68, 0.2);
                color: #fff;
                border-color: rgba(239, 68, 68, 0.55);
            }
            .rtel-top-alerts {
                margin-left: auto;
                position: relative;
            }
            .rtel-alert-bell {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                border: 1px solid #dbe3ef;
                background: #fff;
                color: #111827;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                cursor: pointer;
            }
            .rtel-alert-badge {
                position: absolute;
                top: -6px;
                right: -6px;
                min-width: 18px;
                height: 18px;
                border-radius: 999px;
                background: #dc3545;
                color: #fff;
                font-size: 11px;
                line-height: 18px;
                text-align: center;
                padding: 0 5px;
                font-weight: 700;
            }
            .rtel-alert-dropdown {
                position: absolute;
                right: 0;
                top: 45px;
                width: 360px;
                max-height: 420px;
                overflow: auto;
                border: 1px solid #dbe3ef;
                border-radius: 12px;
                background: #fff;
                box-shadow: 0 12px 26px rgba(15, 23, 42, .16);
                display: none;
                z-index: 999;
            }
            .rtel-alert-dropdown.open { display: block; }
            .rtel-alert-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 12px;
                border-bottom: 1px solid #edf2f7;
            }
            .rtel-alert-head h6 { margin: 0; font-size: 14px; font-weight: 700; }
            .rtel-alert-read-all {
                border: 0;
                background: transparent;
                color: #0d6efd;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }
            .rtel-alert-list { list-style: none; padding: 0; margin: 0; }
            .rtel-alert-item {
                display: block;
                padding: 10px 12px;
                text-decoration: none;
                color: #111827;
                border-bottom: 1px solid #f1f5f9;
            }
            .rtel-alert-item:last-child { border-bottom: 0; }
            .rtel-alert-item:hover { background: #f8fbff; color: #111827; }
            .rtel-alert-item small { display: block; color: #64748b; margin-top: 2px; }
            .rtel-alert-empty { padding: 16px 12px; color: #64748b; font-size: 13px; }
            html[data-theme="dark"] .rtel-alert-bell {
                background: rgba(15, 23, 42, 0.65);
                color: #fff;
                border-color: #334155;
            }
            html[data-theme="dark"] .rtel-alert-dropdown {
                background: #0f172a;
                border-color: #334155;
            }
            html[data-theme="dark"] .rtel-alert-head {
                border-color: #334155;
            }
            html[data-theme="dark"] .rtel-alert-head h6,
            html[data-theme="dark"] .rtel-alert-item {
                color: #fff;
            }
            html[data-theme="dark"] .rtel-alert-item small,
            html[data-theme="dark"] .rtel-alert-empty {
                color: #94a3b8;
            }
            html[data-theme="dark"] .rtel-alert-item {
                border-color: #1e293b;
            }
            html[data-theme="dark"] .rtel-alert-item:hover {
                background: #1e293b;
            }
        </style>
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="header-container">
                    <div class="brand-logo">
                        <a href="../web/index.php" class="avatar avatar-xl"><img src="<?php echo htmlspecialchars(rtel_header_logo_url(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo"></a>
                    </div>
                    <div class="user-name">
                        <a href="../web/index.php" class="user-name">R-tel Admin Dashboard</a>
                    </div>
                </div>

                <div class="d-flex ">
                    <div class="toggler">
                        <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle-l"></i></a>
                    </div>
                </div>

                <div class="sidebar-menu">
                    <ul class="menu">
                        <?php if ($can('index.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['index.php']); ?>">
                            <a href="index.php" class='sidebar-link'>
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($hasProductsMenu): ?>
                        <li class="sidebar-item <?php echo $isProducts ? 'has-sub active' : 'has-sub'; ?>">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-stack"></i>
                                <span>Products</span>
                            </a>
                            <ul class="submenu <?php echo $isProducts ? 'active' : ''; ?>">
                                <?php if ($can('brand.php')): ?><li class="submenu-item <?php echo $isActive(['brand.php']); ?>"><a href="brand.php">Brand</a></li><?php endif; ?>
                                <?php if ($can('category.php')): ?><li class="submenu-item <?php echo $isActive(['category.php']); ?>"><a href="category.php">Category</a></li><?php endif; ?>
                                <?php if ($can('product.php')): ?><li class="submenu-item <?php echo $isActive(['product.php']); ?>"><a href="product.php">Add Products</a></li><?php endif; ?>
                                <?php if ($can('allproducts.php')): ?><li class="submenu-item <?php echo $isActive(['allproducts.php']); ?>"><a href="allproducts.php">All Products</a></li><?php endif; ?>
                                <?php if ($can('bundle.php')): ?><li class="submenu-item <?php echo $isActive(['bundle.php']); ?>"><a href="bundle.php">Bundle Builder</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($can('order.php') || $can('order_manage.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['order.php', 'order_manage.php']); ?>">
                            <a href="order.php" class='sidebar-link'>
                                <i class="bi bi-basket-fill"></i>
                                <span>Order</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($can('customer.php') || $can('customer_info.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['customer.php', 'customer_info.php']); ?>">
                            <a href="customer.php" class='sidebar-link'>
                                <i class="bi bi-people-fill"></i>
                                <span>Customer</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($can('reports.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['reports.php']); ?>">
                            <a href="reports.php" class='sidebar-link'>
                                <i class="bi bi-file-earmark-fill"></i>
                                <span>Reports</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($can('delivery_fee.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['delivery_fee.php']); ?>">
                            <a href="delivery_fee.php" class='sidebar-link'>
                                <i class="bi bi-truck"></i>
                                <span>Delivery Fee</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($can('coupon.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['coupon.php']); ?>">
                            <a href="coupon.php" class='sidebar-link'>
                                <i class="bi bi-percent"></i>
                                <span>Coupons & Discounts</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($can('feedback.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['feedback.php']); ?>">
                            <a href="feedback.php" class='sidebar-link'>
                                <i class="bi bi-chat-left-quote-fill"></i>
                                <span>Feed Backs & Ratings</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($hasSettingsMenu): ?>
                        <li class="sidebar-item <?php echo $isSettings ? 'has-sub active' : 'has-sub'; ?>">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-gear-fill"></i>
                                <span>Website Settings</span>
                            </a>
                            <ul class="submenu <?php echo $isSettings ? 'active' : ''; ?>">
                                <?php if ($can('banner.php')): ?><li class="submenu-item <?php echo $isActive(['banner.php']); ?>"><a href="banner.php">Banner</a></li><?php endif; ?>
                                <?php if ($can('contactinfo.php')): ?><li class="submenu-item <?php echo $isActive(['contactinfo.php']); ?>"><a href="contactinfo.php">Contact Info</a></li><?php endif; ?>
                                <?php if ($can('logo.php')): ?><li class="submenu-item <?php echo $isActive(['logo.php']); ?>"><a href="logo.php">Logo</a></li><?php endif; ?>
                                <?php if ($can('seasonal.php')): ?><li class="submenu-item <?php echo $isActive(['seasonal.php']); ?>"><a href="seasonal.php">Seasonal Effects</a></li><?php endif; ?>
                                <?php if ($can('ai_training.php')): ?><li class="submenu-item <?php echo $isActive(['ai_training.php']); ?>"><a href="ai_training.php">AI Training</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if (rtel_is_super_admin()): ?>
                        <li class="sidebar-item<?php echo $isActive(['manager_access.php']); ?>">
                            <a href="manager_access.php" class='sidebar-link'>
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Manager Access</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($can('activity_log.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['activity_log.php']); ?>">
                            <a href="activity_log.php" class='sidebar-link'>
                                <i class="bi bi-clock-history"></i>
                                <span>Activity Log</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($can('profile.php')): ?>
                        <li class="sidebar-item<?php echo $isActive(['profile.php']); ?>">
                            <a href="profile.php" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="sidebar-item logout-item">
                            <a href="logout.php" class='sidebar-link'>
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <button class="sidebar-toggler btn x"><i data-feather="x"></i></button>
            </div>
        </div>
        <script>
            (function () {
                function escapeHtml(value) {
                    return String(value || "").replace(/[&<>"']/g, function (s) {
                        return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" })[s];
                    });
                }
                function injectBell() {
                    var main = document.getElementById("main");
                    if (!main) return;
                    var header = main.querySelector("header.mb-3");
                    if (!header) return;
                    if (header.querySelector(".rtel-top-alerts")) return;

                    if (!header.style.display) {
                        header.style.display = "flex";
                        header.style.alignItems = "center";
                        header.style.gap = "10px";
                    }

                    var wrapper = document.createElement("div");
                    wrapper.className = "rtel-top-alerts";
                    wrapper.innerHTML = ''
                        + '<button type="button" class="rtel-alert-bell" id="rtelAlertBell" title="Notifications">'
                        + '  <i class="bi bi-bell-fill"></i>'
                        + '  <span class="rtel-alert-badge" id="rtelAlertBadge" style="display:none;">0</span>'
                        + '</button>'
                        + '<div class="rtel-alert-dropdown" id="rtelAlertDropdown">'
                        + '  <div class="rtel-alert-head">'
                        + '    <h6>Notifications</h6>'
                        + '    <button type="button" class="rtel-alert-read-all" id="rtelAlertReadAll">Mark all read</button>'
                        + '  </div>'
                        + '  <ul class="rtel-alert-list" id="rtelAlertList"><li class="rtel-alert-empty">Loading alerts...</li></ul>'
                        + '</div>';
                    header.appendChild(wrapper);

                    var bell = document.getElementById("rtelAlertBell");
                    var badge = document.getElementById("rtelAlertBadge");
                    var dropdown = document.getElementById("rtelAlertDropdown");
                    var list = document.getElementById("rtelAlertList");
                    var markAll = document.getElementById("rtelAlertReadAll");

                    function render(data) {
                        if (!data || data.status !== "success") return;
                        var unread = Number((data.unread && data.unread.total) || 0);
                        badge.textContent = String(unread);
                        badge.style.display = unread > 0 ? "inline-block" : "none";

                        var items = Array.isArray(data.items) ? data.items.slice(0, 10) : [];
                        if (!items.length) {
                            list.innerHTML = '<li class="rtel-alert-empty">No new alerts right now.</li>';
                            return;
                        }
                        list.innerHTML = items.map(function (item) {
                            return '<li><a class="rtel-alert-item" href="' + escapeHtml(item.url || "#") + '">'
                                + '<strong>' + escapeHtml(item.title || "Alert") + '</strong>'
                                + '<small>' + escapeHtml(item.meta || "") + '</small>'
                                + '</a></li>';
                        }).join("");
                    }

                    function fetchAlerts() {
                        fetch("api/admin_alerts_api.php?action=summary", { credentials: "same-origin" })
                            .then(function (r) { return r.json(); })
                            .then(render)
                            .catch(function () {});
                    }

                    function markReadAll() {
                        var fd = new FormData();
                        fd.append("action", "mark_read");
                        fd.append("type", "all");
                        fetch("api/admin_alerts_api.php", { method: "POST", body: fd, credentials: "same-origin" })
                            .then(function (r) { return r.json(); })
                            .then(function () { fetchAlerts(); })
                            .catch(function () {});
                    }

                    bell.addEventListener("click", function (e) {
                        e.preventDefault();
                        dropdown.classList.toggle("open");
                    });
                    document.addEventListener("click", function (e) {
                        if (!wrapper.contains(e.target)) {
                            dropdown.classList.remove("open");
                        }
                    });
                    markAll.addEventListener("click", function (e) {
                        e.preventDefault();
                        markReadAll();
                    });
                    list.addEventListener("click", function () {
                        markReadAll();
                    });

                    fetchAlerts();
                    setInterval(fetchAlerts, 25000);
                }

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", injectBell);
                } else {
                    injectBell();
                }
            })();
        </script>
        <?php
    }
}
