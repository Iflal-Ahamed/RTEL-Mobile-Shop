<?php
include "connection.php";
require "header.php";

$promotions = [];
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
$query = "SELECT promotion_id AS promo_id, promotion_scope, title, description, image,
                 scope_type, scope_id, offer_type, offer_value
          FROM tblpromotion
          WHERE promotion_scope IN ('home', 'offer')
            AND status = 1
            AND (start_date IS NULL OR start_date = '' OR start_date <= CURDATE())
            AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE())
          ORDER BY promotion_id DESC";
$run = mysqli_query($conn, $query);
if ($run) {
    while ($row = mysqli_fetch_assoc($run)) {
        $promotions[] = $row;
    }
}

/** @param mysqli $conn */
function rtel_promotion_scope_shop_row($conn, array $promo): array {
    $scopeType = strtolower((string)($promo['scope_type'] ?? 'product'));
    $scopeId = (string)($promo['scope_id'] ?? '');
    $scopeLabel = $scopeType . ' #' . $scopeId;
    $buttonLink = 'index.php';
    if ($scopeType === 'brand') {
        $q = mysqli_query($conn, "SELECT name FROM tblbrand WHERE brand_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $scopeLabel = 'Brand: ' . $r['name'];
        }
        $buttonLink = 'brand.php?brand_id=' . urlencode($scopeId);
    } elseif ($scopeType === 'category') {
        $q = mysqli_query($conn, "SELECT name FROM tblcategory WHERE cat_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $scopeLabel = 'Category: ' . $r['name'];
        }
        $buttonLink = 'category.php?cat_id=' . urlencode($scopeId);
    } else {
        $q = mysqli_query($conn, "SELECT name FROM tblproduct WHERE product_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
        $r = $q ? mysqli_fetch_assoc($q) : null;
        if ($r) {
            $scopeLabel = 'Product: ' . $r['name'];
        }
        $buttonLink = 'product.php?product_id=' . urlencode($scopeId);
    }
    $offerText = strtolower((string)($promo['offer_type'] ?? 'percent')) === 'fixed'
        ? 'Rs. ' . number_format((float)($promo['offer_value'] ?? 0), 2) . ' OFF'
        : ((float)($promo['offer_value'] ?? 0)) . '% OFF';
    return [$scopeLabel, $buttonLink, $offerText];
}
?>

<section class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
    <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
            <div class="col-md-9 ftco-animate text-center">
                <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Promotions</span></p>
                <h1 class="mb-0 bread">Promotions</h1>
            </div>
        </div>
    </div>
</section>

<section class="ftco-section">
    <div class="container">
        <div class="row">
            <?php if (!empty($promotions)) { ?>
                <?php foreach ($promotions as $promo) {
                    $pScope = (string)($promo['promotion_scope'] ?? 'home');
                    $cardImg = trim((string)($promo['image'] ?? ''));
                    if ($cardImg === '') {
                        $cardImg = 'smartphone.png';
                    }
                    $scopeLabel = '';
                    $shopLink = '';
                    $offerText = '';
                    if ($pScope === 'offer') {
                        [$scopeLabel, $shopLink, $offerText] = rtel_promotion_scope_shop_row($conn, $promo);
                    }
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <?php if ($pScope === 'home') { ?>
                                <img src="../images/<?php echo htmlspecialchars($cardImg, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="Promotion" style="height:220px; object-fit:cover;">
                            <?php } else { ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                                    <span class="badge badge-dark p-3" style="font-size:1rem;"><?php echo htmlspecialchars($offerText, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                            <div class="card-body d-flex flex-column">
                                <h5><?php echo htmlspecialchars($promo['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <?php if ($pScope === 'offer') { ?>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php } ?>
                                <p class="text-muted"><?php echo htmlspecialchars($promo['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-auto d-flex flex-wrap" style="gap:8px;">
                                    <a href="promotion.php?promo_id=<?php echo urlencode((string)$promo['promo_id']); ?>" class="btn btn-outline-dark btn-sm">Details</a>
                                    <?php if ($pScope === 'offer') { ?>
                                        <a href="<?php echo htmlspecialchars($shopLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-dark btn-sm">Shop now</a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="col-12">
                    <div class="alert alert-light border">No promotions available right now.</div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<?php require "footer.php"; ?>
