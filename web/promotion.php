<?php
include "connection.php";

$promoId = isset($_GET['promo_id']) ? (int)$_GET['promo_id'] : 0;
$promotion = null;

if ($promoId > 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblpromotion (
        promotion_id INT AUTO_INCREMENT PRIMARY KEY,
        promotion_scope VARCHAR(20) NOT NULL DEFAULT 'offer',
        title VARCHAR(150) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        image VARCHAR(255) NOT NULL DEFAULT '',
        link_url VARCHAR(255) NOT NULL DEFAULT '',
        scope_type VARCHAR(20) NOT NULL DEFAULT '',
        scope_id VARCHAR(20) NOT NULL DEFAULT '',
        offer_type VARCHAR(20) NOT NULL DEFAULT 'percent',
        offer_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        start_date DATE NULL,
        end_date DATE NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    @mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS promotion_scope VARCHAR(20) NOT NULL DEFAULT 'offer'");
    try {
        mysqli_query($conn, "INSERT INTO tblpromotion (promotion_scope, title, description, image, link_url, status, created_at)
                            SELECT 'home', title, description, image, COALESCE(link,'promotions.php'), status, NOW()
                            FROM tblpromotion_home h
                            WHERE NOT EXISTS (
                                SELECT 1 FROM tblpromotion p
                                WHERE p.promotion_scope='home'
                                  AND p.title=h.title
                            )");
    } catch (Throwable $e) {}
    @mysqli_query($conn, "DROP TABLE IF EXISTS tblpromotion_home");
    $stmt = $conn->prepare("SELECT promotion_id AS promo_id, promotion_scope, title, description, image,
            scope_type, scope_id, offer_type, offer_value
        FROM tblpromotion
        WHERE promotion_id = ?
          AND status = 1
          AND (start_date IS NULL OR start_date = '' OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE())
          AND promotion_scope IN ('home','offer')
        LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $promoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $promotion = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

$offerShopLink = '';
$offerScopeLabel = '';
$offerText = '';
if ($promotion && ($promotion['promotion_scope'] ?? '') === 'offer') {
    $scopeType = strtolower((string)($promotion['scope_type'] ?? 'product'));
    $scopeId = (string)($promotion['scope_id'] ?? '');
    $offerScopeLabel = $scopeType . ' #' . $scopeId;
    $offerShopLink = 'index.php';
    if ($scopeType === 'brand') {
        $q = mysqli_query($conn, "SELECT name FROM tblbrand WHERE brand_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $offerScopeLabel = 'Brand: ' . $r['name'];
        }
        $offerShopLink = 'brand.php?brand_id=' . urlencode($scopeId);
    } elseif ($scopeType === 'category') {
        $q = mysqli_query($conn, "SELECT name FROM tblcategory WHERE cat_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $offerScopeLabel = 'Category: ' . $r['name'];
        }
        $offerShopLink = 'category.php?cat_id=' . urlencode($scopeId);
    } else {
        $q = mysqli_query($conn, "SELECT name FROM tblproduct WHERE product_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $offerScopeLabel = 'Product: ' . $r['name'];
        }
        $offerShopLink = 'product.php?product_id=' . urlencode($scopeId);
    }
    $offerText = strtolower((string)($promotion['offer_type'] ?? 'percent')) === 'fixed'
        ? 'Rs. ' . number_format((float)($promotion['offer_value'] ?? 0), 2) . ' OFF'
        : ((float)($promotion['offer_value'] ?? 0)) . '% OFF';
}

require "header.php";
?>

<section class="hero-wrap hero-bread" style="background-image: url('../images/banner2.avif');">
    <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
            <div class="col-md-9 ftco-animate text-center">
                <p class="breadcrumbs">
                    <span class="mr-2"><a href="index.php">Home</a></span>
                    <span class="mr-2"><a href="promotions.php">Promotions</a></span>
                    <span>Promotion Details</span>
                </p>
                <h1 class="mb-0 bread">Promotion Details</h1>
            </div>
        </div>
    </div>
</section>

<section class="ftco-section">
    <div class="container">
        <?php if ($promotion) {
            $detailScope = (string)($promotion['promotion_scope'] ?? 'home');
            $detailImg = trim((string)($promotion['image'] ?? ''));
            if ($detailImg === '') {
                $detailImg = 'smartphone.png';
            }
            ?>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <?php if ($detailScope === 'home') { ?>
                        <img src="../images/<?php echo htmlspecialchars($detailImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Promotion" class="img-fluid rounded shadow-sm" style="width:100%;max-height:420px;object-fit:cover;">
                    <?php } else { ?>
                        <div class="rounded shadow-sm bg-light d-flex align-items-center justify-content-center" style="width:100%;min-height:280px;">
                            <span class="badge badge-dark p-4" style="font-size:1.25rem;"><?php echo htmlspecialchars($offerText, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-md-6">
                    <h2><?php echo htmlspecialchars($promotion['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php if ($detailScope === 'offer') { ?>
                        <p class="mt-2 mb-3 text-muted"><?php echo htmlspecialchars($offerScopeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                    <p class="mt-3 text-muted"><?php echo nl2br(htmlspecialchars($promotion['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <div class="mt-4">
                        <?php if ($detailScope === 'offer') { ?>
                            <a href="<?php echo htmlspecialchars($offerShopLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-dark btn-sm mr-2">Shop now</a>
                        <?php } ?>
                        <a href="index.php" class="btn btn-outline-dark btn-sm mr-2">Back to Home</a>
                        <a href="promotions.php" class="btn btn-outline-dark btn-sm">All Promotions</a>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <div class="alert alert-danger">Promotion not found.</div>
            <a href="promotions.php" class="btn btn-dark btn-sm">Go to Promotions</a>
        <?php } ?>
    </div>
</section>

<?php require "footer.php"; ?>
