<?php
include 'config.php';
include 'connection.php';
require "header.php";
?>

<style>
  :root {
    --mono-0: #ffffff;
    --mono-1: #f6f6f6;
    --mono-2: #e9e9e9;
    --mono-3: #cfcfcf;
    --mono-6: #4b4b4b;
    --mono-8: #1d1d1d;
    --mono-9: #0f0f0f;
  }

  .hero .slider-item .overlay {
    background: linear-gradient(120deg, rgba(0, 0, 0, .55), rgba(30, 30, 30, .25));
  }

  .hero h1 {
    text-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    letter-spacing: .6px;
  }

  .hero .subheading {
    color: #f1f1f1 !important;
  }

  .section-shell {
    background: linear-gradient(180deg, var(--mono-0), var(--mono-1));
    border: 1px solid var(--mono-2);
    border-radius: 16px;
    padding: 14px 14px 8px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06);
  }

  .section-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: var(--mono-9);
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    letter-spacing: .2px;
    margin-bottom: 10px;
  }

  .search-wrapper {
    width: 100% !important;
    max-width: 430px;
    z-index: 5000;
  }

  .search-form,
  .search-wrapper {
    position: relative;
  }

  .search-form {
    z-index: 5500;
  }

  .search-zone,
  .search-zone .container,
  .search-zone .row,
  .search-zone .sidebar,
  .search-zone .sidebar-box {
    position: relative;
    z-index: 7000;
    overflow: visible;
  }

  #searchInput {
    border: 1px solid #d9d9d9;
    border-radius: 12px;
    height: 48px;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.05);
  }

  #searchInput:focus {
    border-color: #191919;
    box-shadow: 0 0 0 3px rgba(20, 20, 20, 0.08);
  }

  .search-dropdown {
    z-index: 6000 !important;
    border-radius: 12px;
    border: 1px solid #d9d9d9 !important;
    box-shadow: 0 18px 35px rgba(0, 0, 0, 0.16) !important;
    max-height: 320px !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    scrollbar-width: thin;
    scrollbar-color: #a0a0a0 #f3f3f3;
  }

  .search-dropdown::-webkit-scrollbar {
    width: 9px;
  }
  .search-dropdown::-webkit-scrollbar-track {
    background: #f3f3f3;
  }
  .search-dropdown::-webkit-scrollbar-thumb {
    background: #a0a0a0;
    border-radius: 10px;
  }
  .search-dropdown::-webkit-scrollbar-thumb:hover {
    background: #7f7f7f;
  }

  .ftco-category,
  .ftco-category .container,
  .brand-carousel-wrapper,
  .brand-carousel-viewport,
  .brand-carousel-container,
  #brandContainer {
    position: relative;
    z-index: 1;
  }

  .cat-arrow {
    background: linear-gradient(180deg, #fff, #ececec);
    border: 1px solid #d8d8d8;
    color: #1b1b1b;
    box-shadow: 0 6px 14px rgba(0, 0, 0, .12);
  }

  .cat-arrow:hover {
    background: #111;
    color: #fff;
    border-color: #111;
  }

  .quick-link {
    display: inline-block;
    padding: 7px 14px;
    border: 1px solid #151515;
    border-radius: 999px;
    color: #151515;
    font-weight: 600;
    transition: .2s ease;
  }

  .quick-link:hover {
    background: #111;
    color: #fff !important;
    text-decoration: none;
  }

  .promo-shell {
    background: linear-gradient(180deg, #fff, #f8f8f8);
    border: 1px solid #e6e6e6;
    border-radius: 16px;
    padding: 14px 12px 10px;
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.07);
  }

  #couponContainer > [class*="col-"],
  #promotionContainer > [class*="col-"] {
    margin-bottom: 12px;
  }
  .coupon-carousel .item {
    padding: 2px 6px 10px;
  }
  .coupon-carousel .owl-nav {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 4px;
  }
  .coupon-carousel .owl-nav button {
    width: 34px;
    height: 34px;
    border-radius: 50% !important;
    border: 1px solid #d8d8d8 !important;
    background: #fff !important;
    color: #111 !important;
  }
  .coupon-carousel .owl-nav button:hover {
    background: #111 !important;
    color: #fff !important;
    border-color: #111 !important;
  }
  .coupon-carousel .owl-dots {
    margin-top: 0 !important;
  }
  .promotion-carousel .item {
    padding: 2px 6px 10px;
  }
  .promotion-carousel .owl-nav {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 4px;
  }
  .promotion-carousel .owl-nav button {
    width: 34px;
    height: 34px;
    border-radius: 50% !important;
    border: 1px solid #d8d8d8 !important;
    background: #fff !important;
    color: #111 !important;
  }
  .promotion-carousel .owl-nav button:hover {
    background: #111 !important;
    color: #fff !important;
    border-color: #111 !important;
  }

  /* Add breathing space between AI personalized product rows/cards. */
  #personalizedContainer > [class*="col-"] {
    margin-bottom: 16px;
  }

  #couponContainer .card,
  #promotionContainer .card,
  #couponContainer .coupon-card,
  #promotionContainer .promo-card,
  #promotionContainer .promotion-card {
    border: 1px solid #dfdfdf !important;
    border-radius: 14px !important;
    box-shadow: 0 10px 20px rgba(0, 0, 0, .08) !important;
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease;
  }

  #couponContainer .card:hover,
  #promotionContainer .card:hover,
  #couponContainer .coupon-card:hover,
  #promotionContainer .promo-card:hover,
  #promotionContainer .promotion-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 24px rgba(0, 0, 0, .14) !important;
  }

  #couponContainer .badge,
  #promotionContainer .badge {
    background: #111 !important;
    color: #fff !important;
    border-radius: 999px !important;
    padding: 6px 10px !important;
    font-weight: 600;
  }

  .ai-cta-wrap {
    background: linear-gradient(125deg, rgba(0, 0, 0, .8), rgba(45, 45, 45, .55));
    border-radius: 14px;
    padding: 26px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
  }

  .ai-cta-btn {
    background: #fff;
    color: #111;
    border: 0;
    font-weight: 700;
    border-radius: 999px;
    padding: 12px 24px;
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.25);
  }

  .ai-cta-btn:hover {
    background: #111;
    color: #fff;
  }
</style>
	
<?php
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblweb_banner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    heading VARCHAR(255) NOT NULL,
    sub_heading VARCHAR(255) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0
)");
mysqli_query($conn, "ALTER TABLE tblweb_banner ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0");
$bannerRows = [];
$bannerRes = mysqli_query($conn, "SELECT image, heading, sub_heading FROM tblweb_banner WHERE status = 1 ORDER BY display_order ASC, id DESC");
while ($bannerRes && $b = mysqli_fetch_assoc($bannerRes)) {
    $bannerRows[] = $b;
}
if (count($bannerRows) === 0) {
    $bannerRows[] = ['image' => 'banner1.jpg', 'heading' => 'EVERYTHING IS HERE 🛍️✨', 'sub_heading' => 'Start your next favorite purchase with us 🚀'];
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblcoupon (
    coupon_id VARCHAR(20) NOT NULL PRIMARY KEY,
    coupon_ref_id INT AUTO_INCREMENT UNIQUE,
    coupon_type VARCHAR(20) NOT NULL DEFAULT 'available',
    order_id VARCHAR(20) NOT NULL DEFAULT '',
    cus_id VARCHAR(250) NOT NULL DEFAULT '',
    code VARCHAR(20) NOT NULL,
    dispercentage INT(3) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expiry_date DATE NOT NULL,
    min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_ref_id INT AUTO_INCREMENT UNIQUE");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_type VARCHAR(20) NOT NULL DEFAULT 'available'");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
mysqli_query($conn, "ALTER TABLE tblcoupon ADD COLUMN IF NOT EXISTS coupon_scope VARCHAR(20) NOT NULL DEFAULT 'all'");
mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_type_status_expiry ON tblcoupon(coupon_type, status, expiry_date)");
mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_tblcoupon_scope ON tblcoupon(coupon_scope)");
try {
    mysqli_query($conn, "INSERT INTO tblcoupon (coupon_id, coupon_type, coupon_scope, code, dispercentage, expiry_date, min_order, status, created_at)
                        SELECT CONCAT('CH', LPAD(id, 8, '0')), 'available', 'home', code, CAST(REPLACE(REPLACE(discount, '% OFF', ''), '%', '') AS UNSIGNED), exp_date, 0.00, status, NOW()
                        FROM tblhome_coupon h
                        WHERE NOT EXISTS (
                            SELECT 1 FROM tblcoupon c WHERE c.code = h.code AND c.coupon_type = 'available'
                        )");
} catch (Throwable $e) {
    // Legacy table may not exist; ignore migration error.
}
@mysqli_query($conn, "DROP TABLE IF EXISTS tblavailable_coupon");
@mysqli_query($conn, "DROP TABLE IF EXISTS tblhome_coupon");
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
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS description VARCHAR(255) NOT NULL DEFAULT ''");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS image VARCHAR(255) NOT NULL DEFAULT ''");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NOT NULL DEFAULT ''");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS scope_type VARCHAR(20) NOT NULL DEFAULT ''");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS scope_id VARCHAR(20) NOT NULL DEFAULT ''");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS offer_type VARCHAR(20) NOT NULL DEFAULT 'percent'");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS offer_value DECIMAL(10,2) NOT NULL DEFAULT 0.00");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS start_date DATE NULL");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS end_date DATE NULL");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1");
@mysqli_query($conn, "ALTER TABLE tblpromotion ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
try {
    mysqli_query($conn, "INSERT INTO tblpromotion (promotion_scope, title, description, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, created_at)
                        SELECT 'offer', title, description, scope_type, scope_id, offer_type, offer_value, start_date, end_date, status, created_at
                        FROM tblpromotion_offer o
                        WHERE NOT EXISTS (
                            SELECT 1 FROM tblpromotion p
                            WHERE p.promotion_scope='offer'
                              AND p.title=o.title
                              AND COALESCE(p.scope_type,'')=COALESCE(o.scope_type,'')
                              AND COALESCE(p.scope_id,'')=COALESCE(o.scope_id,'')
                        )");
} catch (Throwable $e) {}
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
@mysqli_query($conn, "DROP TABLE IF EXISTS tblpromotion_offer");
@mysqli_query($conn, "DROP TABLE IF EXISTS tblpromotion_home");
$activeCouponCount = 0;
$activePromotionCount = 0;
$couponCountRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblcoupon WHERE coupon_type = 'available' AND status = 1 AND coupon_scope IN ('all','home') AND expiry_date >= CURDATE()");
if ($couponCountRes && ($couponCountRow = mysqli_fetch_assoc($couponCountRes))) {
    $activeCouponCount = (int)($couponCountRow['total'] ?? 0);
}
$promotionCountRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblpromotion
    WHERE status = 1
      AND (
            (promotion_scope = 'home'
              AND (start_date IS NULL OR start_date <= CURDATE())
              AND (end_date IS NULL OR end_date >= CURDATE()))
         OR (promotion_scope = 'offer'
              AND (start_date IS NULL OR start_date <= CURDATE())
              AND (end_date IS NULL OR end_date >= CURDATE()))
      )");
if ($promotionCountRes && ($promotionCountRow = mysqli_fetch_assoc($promotionCountRes))) {
    $activePromotionCount = (int)($promotionCountRow['total'] ?? 0);
}
?>
    <section id="home-section" class="hero">
		<div class="home-slider owl-carousel">
            <?php foreach ($bannerRows as $banner): ?>
				<div class="slider-item" style="background-image: url(../images/<?php echo htmlspecialchars((string)$banner['image'], ENT_QUOTES, 'UTF-8'); ?>);">
	      			<div class="overlay"></div>
					<div class="container">
						<div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">
							<div class="col-sm-12 ftco-animate text-center">
								<h1 class="mb-2"><?php echo htmlspecialchars((string)$banner['heading'], ENT_QUOTES, 'UTF-8'); ?></h1>
								<h2 class="subheading mb-4"><?php echo htmlspecialchars((string)$banner['sub_heading'], ENT_QUOTES, 'UTF-8'); ?></h2>
							</div>
						</div>
					</div>
				</div>
            <?php endforeach; ?>
	    </div>
    </section>

	<section class="ftco-section ftco-category ftco-animate">
    <div class="container">
        <div class="category-carousel-wrapper">
            <button class="cat-arrow cat-prev">&#10094;</button>

            <div class="category-carousel-viewport">
                <div class="category-carousel-container" id="categoryContainer">
                    <?php
                    $categoryQuery = "SELECT cat_id, name, image FROM tblcategory WHERE status='1' ORDER BY cat_id ASC";
                    $categoryResult = mysqli_query($conn, $categoryQuery);

                    if ($categoryResult && mysqli_num_rows($categoryResult) > 0) {
                        while ($category = mysqli_fetch_assoc($categoryResult)) {
                            ?>
                            <?php $categoryImage = !empty($category['image']) ? $category['image'] : 'smartphone.png'; ?>
                            <a class="category-item" href="category.php?cat_id=<?= (int)$category['cat_id']; ?>">
                                <img src="../images/<?= htmlspecialchars($categoryImage); ?>" alt="<?= htmlspecialchars($category['name']); ?>" loading="lazy" onerror="this.onerror=null;this.src='../images/smartphone.png';">
                                <span><?= htmlspecialchars($category['name']); ?></span>
                            </a>
                            <?php
                        }
                    } else {
                        ?>
                        <p class="w-100 text-center mb-0">No categories available.</p>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <button class="cat-arrow cat-next">&#10095;</button>
        </div>
    </div>
</section>
<section class="ftco-animate search-zone">
    <div class="container mt-3 mb-2">
    <div class="row justify-content-end">
        <div class="col-lg-4 sidebar">
            <form class="search-form">
                <div class="search-wrapper position-relative w-100">
    <input type="text"
           id="searchInput"
           class="form-control"
           placeholder="Search products..."
           data-i18n-placeholder="placeholder_search_products"
           autocomplete="off">
    <div id="searchDropdown" class="search-dropdown"></div>
</div>
            </form>
        </div>
    </div>
</div>
</section>

<section class="ftco-section ftco-animate">
    <div class="container">
        <div class="section-shell">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-1 section-title">🏷️ Shop by Brands</h4>
        </div>
        <div class="brand-carousel-wrapper">
            <button class="cat-arrow brand-prev" id="brandPrev">&#10094;</button>
            <div class="brand-carousel-viewport">
                <div class="brand-carousel-container" id="brandContainer">
                    <!-- Brands will load here -->
                </div>
            </div>
            <button class="cat-arrow brand-next" id="brandNext">&#10095;</button>
        </div>
        </div>
    </div>
</section>

<?php if ($activeCouponCount > 0): ?>
<section class="ftco-section pt-0 pb-3">
    <div class="container">
        <div class="promo-shell">
        <div class="row">
            <div class="col-md-12 heading-section ftco-animate">
                <h4 class="mb-1 section-title">🎟️ Coupons</h4>
            </div>
        </div>
        <div class="coupon-carousel owl-carousel ftco-animate" id="couponContainer">
            <!-- coupons load here -->
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($activePromotionCount > 0): ?>
<section class="ftco-section pt-0">
    <div class="container">
        <div class="promo-shell">
        <div class="row">
            <div class="col-md-12 heading-section ftco-animate">
                <h4 class="mb-1 section-title">🔥 Promotions</h4>
            </div>
        </div>
        <div class="promotion-carousel owl-carousel ftco-animate" id="promotionContainer">
            <!-- promotions load here -->
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id'])): ?>
<section class="ftco-section pt-0">
    <div class="container">
        <div class="section-shell">
            <div class="row">
                <div class="col-md-12 heading-section ftco-animate">
                    <h4 class="mb-1 section-title">🧠 AI Personalized For You</h4>
                </div>
            </div>
            <div class="row ftco-animate" id="personalizedContainer">
                <!-- personalized products load here -->
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

    <section class="ftco-section">
    	<div class="container">
			<div class="row">
          		<div class="col-md-12 heading-section ftco-animate">
            		<h4 class="mb-1 section-title">🆕 New Arrivals</h4>
          		</div>
        	</div>   		
    	</div>
    	<div class="container">
			<div class="row">
				<div class="col-md-12 ftco-animate" style="text-align: right;">
					<a href="new_arrivals.php" class="quick-link">View More</a>
				</div>
			</div>
    		<div class="row ftco-animate" id="newarrivalsContainer">
				<!-- new arrivals load here -->
    		</div>
    	</div>
	</section>

    <section class="ftco-section">
    	<div class="container">
			<div class="row">
          		<div class="col-md-12 heading-section ftco-animate">
            		<h4 class="mb-1 section-title">💸 On-Sale Products</h4>
          		</div>
        	</div>   		
    	</div>
    	<div class="container">
			<div class="row">
				<div class="col-md-12 ftco-animate" style="text-align: right;">
					<a href="on_sale.php" class="quick-link">View More</a>
				</div>
			</div>
    		<div class="row ftco-animate" id="onsaleContainer">
    			<!-- onsale products load here -->
    		</div>
    	</div>
    </section>

    <section class="ftco-section pt-0">
    	<div class="container">
			<div class="row">
          		<div class="col-md-12 heading-section ftco-animate">
            		<h4 class="mb-1 section-title">🎁 Bundle Deals</h4>
          		</div>
        	</div>
    	</div>
    	<div class="container">
			<div class="row">
				<div class="col-md-12 ftco-animate" style="text-align: right;">
					<a href="bundles.php" class="quick-link">View More</a>
				</div>
			</div>
    		<div class="row ftco-animate" id="bundleContainer">
    			<!-- bundle cards load here -->
    		</div>
    	</div>
    </section>
		
	<section class="ftco-section img" style="background-image: url(../images/banner2.avif);">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-md-7 heading-section ftco-animate deal-of-the-day ftco-animate">
					<div class="ai-cta-wrap text-center">
					<h1 class="mb-3" style="color:white;">🤖 Chat with Our AI Assistant</h1>
                    <p class="mb-4" style="color:#f1f1f1;">Ask about products, stock, and shopping help in seconds ⚡</p>
					<div class="text-center">
						<a href="chat.php" class="btn ai-cta-btn btn-lg">
							START CHAT 💬
						</a>
					</div>
          </div>
				</div>
			</div>   		
		</div>
	</section>

    <section class="ftco-section testimony-section">
  <div class="container">

    <div class="row mb-5 pb-3">
      <div class="col-md-12 heading-section ftco-animate">
        <h4 class="mb-1 section-title">⭐ Testimony</h4>
      </div>
    </div>

    <div class="row ftco-animate">
      <div class="col-md-12">

        <div class="carousel-testimony owl-carousel" id="commentContainer">
          <!-- AJAX will inject .item here -->
        </div>

      </div>
    </div>

  </div>
</section>


	<?php require "footer.php"; ?>

  