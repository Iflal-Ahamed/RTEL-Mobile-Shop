<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function rtel_permission_page_catalog()
{
    return [
        'index.php' => 'Dashboard',
        'brand.php' => 'Brand',
        'category.php' => 'Category',
        'product.php' => 'Add Products',
        'allproducts.php' => 'All Products',
        'bundle.php' => 'Bundle Builder',
        'order.php' => 'Order',
        'order_manage.php' => 'Order Manage',
        'customer.php' => 'Customer',
        'customer_info.php' => 'Customer Info',
        'reports.php' => 'Reports',
        'delivery_fee.php' => 'Delivery Fee',
        'coupon.php' => 'Coupons & Discounts',
        'promotion.php' => 'Promotions (Homepage)',
        'feedback.php' => 'Feedback',
        'banner.php' => 'Banner',
        'contactinfo.php' => 'Contact Info',
        'logo.php' => 'Logo',
        'seasonal.php' => 'Seasonal Effects',
        'ai_training.php' => 'AI Training',
        'activity_log.php' => 'Activity Log',
        'profile.php' => 'Profile',
        'manager_access.php' => 'Manager Access',
    ];
}

function rtel_current_admin_type()
{
    $type = strtolower(trim((string)($_SESSION['admin_type'] ?? 'admin')));
    if ($type === '') {
        return 'admin';
    }
    return $type;
}

function rtel_is_super_admin()
{
    return rtel_current_admin_type() === 'admin';
}

function rtel_current_admin_allowed_pages()
{
    if (rtel_is_super_admin()) {
        return array_keys(rtel_permission_page_catalog());
    }
    $pages = $_SESSION['admin_allowed_pages'] ?? [];
    if (!is_array($pages)) {
        return [];
    }
    return array_values(array_unique(array_map('strtolower', array_map('trim', $pages))));
}

function rtel_admin_can_access_page($page)
{
    $page = strtolower(trim((string)$page));
    if ($page === '' || $page === 'login.php' || $page === 'forgot_password.php' || $page === 'logout.php') {
        return true;
    }

    if (rtel_is_super_admin()) {
        return true;
    }

    if ($page === 'manager_access.php') {
        return false;
    }

    return in_array($page, rtel_current_admin_allowed_pages(), true);
}

function rtel_require_admin_page_access($page = null)
{
    $resolved = strtolower((string)($page ?: basename((string)($_SERVER['PHP_SELF'] ?? ''))));
    $catalog = rtel_permission_page_catalog();
    if (!isset($catalog[$resolved]) && !rtel_is_super_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
    if (!rtel_admin_can_access_page($resolved)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function rtel_refresh_admin_permission_session($conn, $adminId, $adminType = 'admin')
{
    $adminId = trim((string)$adminId);
    $adminType = strtolower(trim((string)$adminType));
    $_SESSION['admin_type'] = ($adminType === 'manager') ? 'manager' : 'admin';

    if ($_SESSION['admin_type'] === 'admin' || $adminId === '' || !$conn) {
        $_SESSION['admin_allowed_pages'] = array_keys(rtel_permission_page_catalog());
        return;
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin_page_permission (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id VARCHAR(50) NOT NULL,
        page_key VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uniq_admin_page (admin_id, page_key),
        KEY idx_admin_id (admin_id)
    )");

    $allowed = [];
    $stmt = $conn->prepare("SELECT page_key FROM tbladmin_page_permission WHERE admin_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $allowed[] = strtolower(trim((string)($row['page_key'] ?? '')));
        }
        $stmt->close();
    }
    $_SESSION['admin_allowed_pages'] = array_values(array_filter(array_unique($allowed)));
}

function rtel_require_admin_auth()
{
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // Enforce account status on every request so deactivated managers lose access immediately.
    $adminId = trim((string)($_SESSION['admin_id'] ?? ''));
    if ($adminId !== '') {
        $connFile = dirname(__DIR__) . '/connection.php';
        if (is_file($connFile)) {
            include $connFile;
            if (isset($conn) && $conn instanceof mysqli) {
                @mysqli_query($conn, "ALTER TABLE tbladmin ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
                $stmt = $conn->prepare("SELECT COALESCE(status, 1) AS status FROM tbladmin WHERE admin_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $adminId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $isActive = (int)($row['status'] ?? 0) === 1;
                    if (!$isActive) {
                        $_SESSION = [];
                        if (ini_get('session.use_cookies')) {
                            $params = session_get_cookie_params();
                            setcookie(
                                session_name(),
                                '',
                                time() - 42000,
                                $params['path'],
                                $params['domain'],
                                (bool)$params['secure'],
                                (bool)$params['httponly']
                            );
                        }
                        session_destroy();
                        $self = strtolower((string)($_SERVER['PHP_SELF'] ?? ''));
                        if (strpos($self, '/mis/api/') !== false || strpos($self, '/mis/crud/') !== false || strpos($self, '\\mis\\api\\') !== false || strpos($self, '\\mis\\crud\\') !== false) {
                            http_response_code(401);
                            exit('Account deactivated.');
                        }
                        header('Location: login.php?deactivated=1');
                        exit;
                    }
                }
            }
        }
    }

    $self = strtolower((string)($_SERVER['PHP_SELF'] ?? ''));
    // API/CRUD endpoints perform their own explicit permission checks (e.g. brand.php, order.php).
    if (strpos($self, '/mis/api/') !== false || strpos($self, '/mis/crud/') !== false || strpos($self, '\\mis\\api\\') !== false || strpos($self, '\\mis\\crud\\') !== false) {
        return;
    }
    rtel_require_admin_page_access();
}

function rtel_current_admin_id()
{
    return trim((string)($_SESSION['admin_id'] ?? ''));
}

function rtel_current_admin_name($conn = null)
{
    $cached = trim((string)($_SESSION['admin_name'] ?? ''));
    if ($cached !== '') {
        return $cached;
    }

    $adminId = rtel_current_admin_id();
    if ($adminId === '' || !$conn) {
        return $adminId !== '' ? $adminId : 'Admin';
    }

    $stmt = $conn->prepare("SELECT name FROM tbladmin WHERE admin_id = ? LIMIT 1");
    if (!$stmt) {
        return $adminId;
    }
    $stmt->bind_param('s', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $name = trim((string)($row['name'] ?? ''));
    if ($name === '') {
        $name = $adminId;
    }
    $_SESSION['admin_name'] = $name;
    return $name;
}
