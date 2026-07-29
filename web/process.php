<?php
include "connection.php";
require_once __DIR__ . "/ai/personalization_engine.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(isset($_POST['action'])){

    if($_POST['action'] == "load_categories"){

        $query = "SELECT * FROM tblcategory WHERE status='1'";
        $run = mysqli_query($conn,$query);

        while($row=mysqli_fetch_assoc($run)){
            echo "<a class='category-item' href='category.php?cat_id=".$row['cat_id']."'>
                    <img src='../images/".$row['image']."'>
                    <span>".$row['name']."</span>
                  </a>";
        }
    }

    if($_POST['action'] == "load_brands"){

        $query = "SELECT * FROM tblbrand WHERE status='1'";
        $run = mysqli_query($conn,$query);

        while($row=mysqli_fetch_assoc($run)){
            $brandId = (string)($row['brand_id'] ?? '');
            $brandName = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $brandImg = trim((string)($row['image'] ?? ''));
            if ($brandImg === '') {
                $brandImg = 'smartphone.png';
            }
            $brandImgSafe = htmlspecialchars($brandImg, ENT_QUOTES, 'UTF-8');
            echo "<a class='brand-item' href='brand.php?brand_id=".$brandId."'>
                    <img src='../images/".$brandImgSafe."' alt='".$brandName."' loading='lazy' decoding='async' onerror=\"this.onerror=null;this.src='../images/smartphone.png';\">
                    <span>".$brandName."</span>
                  </a>";
        }
    }

    if($_POST['action'] == "load_coupons"){
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
        $query = "SELECT code, dispercentage, expiry_date, min_order
                  FROM tblcoupon
                  WHERE coupon_type = 'available' AND status = 1 AND coupon_scope IN ('all','home') AND expiry_date >= CURDATE()
                  ORDER BY dispercentage DESC, expiry_date ASC";
        $run = mysqli_query($conn, $query);
        while($row = mysqli_fetch_assoc($run)){
            $exp = date("M d, Y", strtotime((string)$row['expiry_date']));
            $code = (string)($row['code'] ?? '');
            $percent = (int)($row['dispercentage'] ?? 0);
            $minOrder = (float)($row['min_order'] ?? 0);
            echo "<div class='item'>
                    <div class='card coupon-card h-100 border-0 shadow-sm'>
                        <div class='card-body d-flex flex-column'>
                            <div class='d-flex justify-content-between align-items-start mb-2'>
                                <h6 class='mb-0'>🎟️ Coupon Offer</h6>
                                <span class='badge'>".$percent."% OFF</span>
                            </div>
                            <p class='mb-2 text-muted' style='font-size:13px;'>Minimum order: Rs. ".number_format($minOrder, 2)."</p>
                            <div class='mt-auto'>
                                <div class='d-flex justify-content-between align-items-center'>
                                    <span class='badge badge-dark'>CODE: ".htmlspecialchars($code, ENT_QUOTES, 'UTF-8')."</span>
                                    <small class='text-muted'>⏳ ".$exp."</small>
                                </div>
                                <button type='button' class='btn btn-dark btn-sm mt-3 w-100 js-copy-coupon' data-code='".htmlspecialchars($code, ENT_QUOTES, 'UTF-8')."'>Copy Code</button>
                            </div>
                        </div>
                    </div>
                  </div>";
        }
    }

    if($_POST['action'] == "load_promotions"){
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
        @mysqli_query($conn, "DROP TABLE IF EXISTS tblpromotion_offer");
        // Homepage / banner promotions (MIS → Promotions page, scope = home)
        $homeSql = "SELECT promotion_id, title, description, image, link_url
                    FROM tblpromotion
                    WHERE promotion_scope = 'home'
                      AND status = 1
                      AND (start_date IS NULL OR start_date <= CURDATE())
                      AND (end_date IS NULL OR end_date >= CURDATE())
                    ORDER BY promotion_id DESC
                    LIMIT 6";
        $homeRun = mysqli_query($conn, $homeSql);
        if ($homeRun) {
            while ($h = mysqli_fetch_assoc($homeRun)) {
                $img = trim((string)($h['image'] ?? ''));
                if ($img === '') {
                    $img = 'smartphone.png';
                }
                $titleH = htmlspecialchars((string)($h['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $descH = htmlspecialchars((string)($h['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                $imgH = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                echo "<div class='item'>
                    <div class='card promotion-card border-0 shadow-sm h-100'>
                        <img src='../images/{$imgH}' class='card-img-top' alt='' style='height:200px;object-fit:cover;'>
                        <div class='card-body d-flex flex-column'>
                            <h6 class='mb-2'>🔥 {$titleH}</h6>
                            <p class='text-muted mb-0' style='font-size:13px;'>{$descH}</p>
                        </div>
                    </div>
                  </div>";
            }
        }
        $query = "SELECT promotion_id, title, scope_type, scope_id, offer_type, offer_value, description
                  FROM tblpromotion
                  WHERE promotion_scope = 'offer'
                    AND status = 1
                    AND (start_date IS NULL OR start_date = '' OR start_date <= CURDATE())
                    AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE())
                  ORDER BY promotion_id DESC
                  LIMIT 3";
        $run = mysqli_query($conn, $query);
        while($row = mysqli_fetch_assoc($run)){
            $scopeType = strtolower((string)($row['scope_type'] ?? 'product'));
            $scopeId = (string)($row['scope_id'] ?? '');
            $scopeLabel = $scopeType . " #" . $scopeId;
            if ($scopeType === "brand") {
                $qScope = mysqli_query($conn, "SELECT name FROM tblbrand WHERE brand_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
                $scopeRow = $qScope ? mysqli_fetch_assoc($qScope) : null;
                if ($scopeRow) $scopeLabel = "Brand: " . $scopeRow['name'];
            } elseif ($scopeType === "category") {
                $qScope = mysqli_query($conn, "SELECT name FROM tblcategory WHERE cat_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
                $scopeRow = $qScope ? mysqli_fetch_assoc($qScope) : null;
                if ($scopeRow) $scopeLabel = "Category: " . $scopeRow['name'];
            } else {
                $qScope = mysqli_query($conn, "SELECT name FROM tblproduct WHERE product_id='" . mysqli_real_escape_string($conn, $scopeId) . "' LIMIT 1");
                $scopeRow = $qScope ? mysqli_fetch_assoc($qScope) : null;
                if ($scopeRow) $scopeLabel = "Product: " . $scopeRow['name'];
            }
            $offerText = strtolower((string)($row['offer_type'] ?? 'percent')) === "fixed"
                ? "Rs. " . number_format((float)$row['offer_value'], 2) . " OFF"
                : ((float)$row['offer_value']) . "% OFF";
            echo "<div class='item'>
                    <div class='card promotion-card border-0 shadow-sm h-100'>
                        <div class='card-body d-flex flex-column'>
                            <h6 class='mb-2'>🔥 ".htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8')."</h6>
                            <p class='text-muted mb-1' style='font-size:13px;'>".htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8')."</p>
                            <p class='mb-3'><span class='badge'>".htmlspecialchars($offerText, ENT_QUOTES, 'UTF-8')."</span></p>
                            <p class='text-muted mb-0 mt-auto' style='font-size:13px;'>".htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8')."</p>
                        </div>
                    </div>
                  </div>";
        }
    }

    if($_POST['action'] == "load_newarrivals"){

       $query = "SELECT p.*, i.* 
                      FROM tblproduct p 
                      JOIN tblimage i ON p.product_id = i.product_id 
                      WHERE p.status='1'
                      ORDER BY p.product_id DESC
                      LIMIT 4";

            $run = mysqli_query($conn,$query);

            while($row = mysqli_fetch_assoc($run)){
                $price = (float)$row['price'];
                $comparePrice = (float)$row['cprice'];
                $hasDiscount = $comparePrice > 0 && $comparePrice > $price;
                $discount = $hasDiscount ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;
        ?>
           
            <div class='col-md-6 col-lg-3'>
                <div class='product'>
                    <a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>' class='img-prod'>
                        <img class='img-fluid' src='../images/<?php echo htmlspecialchars($row['image_1'], ENT_QUOTES, 'UTF-8');?>' alt='img'>

                        <?php if($hasDiscount){ ?>
                        <span class='status'><?php echo $discount;?>%</span>
                        <div class='overlay'></div>
                        <?php } ?>
                    </a>

                    <div class='text py-3 pb-4 px-3 text-center'>
                        <h3><a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>'><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');?></a></h3>

                        <div class='d-flex'>
                            <div class='pricing'>
                                <p class='price'>
                                    <?php
                                    if($hasDiscount){?>
                                    <span class='mr-2 price-dc'><?php echo "Rs. " . number_format($comparePrice, 2);?></span>
                                    <?php } ?>
                                    <span class='price-sale'><?php echo "Rs. " . number_format($price, 2);?></span>
                                </p>
                            </div>
                        </div>

                        <div class='bottom-area d-flex px-3'>
                            <div class='m-auto d-flex'>
                                <?php echo "<a href='product.php?product_id=".$row['product_id']."' class='add-to-cart d-flex justify-content-center align-items-center text-center'>
                                    <span><i class='ion-ios-menu'></i></span>
                                </a>"; ?>
                                <a href='javascript:void(0)' class='buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                    <span><i class='ion-ios-cart'></i></span>
                                </a>
                                <a href='javascript:void(0)' class='heart d-flex justify-content-center align-items-center js-add-wishlist' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                    <span><i class='ion-ios-heart'></i></span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
           <?php 
        }
    }

    if($_POST['action'] == "load_onsales"){

    $query = "SELECT p.*, i.* 
                    FROM tblproduct p 
                    JOIN tblimage i ON p.product_id = i.product_id 
                    WHERE p.status='1' AND p.cprice > 0 AND p.cprice > p.price
                    ORDER BY p.product_id DESC
                    LIMIT 4";

        $run = mysqli_query($conn,$query);

        while($row = mysqli_fetch_assoc($run)){
            $price = (float)$row['price'];
            $comparePrice = (float)$row['cprice'];
            $hasDiscount = $comparePrice > 0 && $comparePrice > $price;
            $discount = $hasDiscount ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;
    ?>
        <div class='col-md-6 col-lg-3'>
            <div class='product'>
                <a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>' class='img-prod'>
                    <img class='img-fluid' src='../images/<?php echo htmlspecialchars($row['image_1'], ENT_QUOTES, 'UTF-8');?>' alt='img'>

                    <?php if ($hasDiscount) { ?><span class='status'><?php echo $discount;?>%</span><?php } ?>
                    <div class='overlay'></div>
                </a>

                <div class='text py-3 pb-4 px-3 text-center'>
                    <h3><a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>'><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');?></a></h3>

                    <div class='d-flex'>
                        <div class='pricing'>
                            <p class='price'>
                                <?php if ($hasDiscount) { ?><span class='mr-2 price-dc'><?php echo "Rs. " . number_format($comparePrice, 2);?></span><?php } ?>
                                <span class='price-sale'><?php echo "Rs. " . number_format($price, 2);?></span>
                            </p>
                        </div>
                    </div>

                    <div class='bottom-area d-flex px-3'>
                        <div class='m-auto d-flex'>
                            <a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>' class='add-to-cart d-flex justify-content-center align-items-center text-center'>
                                <span><i class='ion-ios-menu'></i></span>
                            </a>
                            <a href='javascript:void(0)' class='buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                <span><i class='ion-ios-cart'></i></span>
                            </a>
                            <a href='javascript:void(0)' class='heart d-flex justify-content-center align-items-center js-add-wishlist' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                <span><i class='ion-ios-heart'></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php 
        }
    }

    if($_POST['action'] == "load_bundles_home"){
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle (
            bundle_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_name VARCHAR(150) NOT NULL,
            bundle_model VARCHAR(120) NOT NULL DEFAULT '',
            bundle_image VARCHAR(255) NOT NULL DEFAULT '',
            bundle_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            expiry_date DATE NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )");
        mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS bundle_image VARCHAR(255) NOT NULL DEFAULT '' AFTER bundle_model");
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle_item (
            bundle_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT UNSIGNED NOT NULL,
            product_id VARCHAR(20) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0
        )");
        $sql = "SELECT b.bundle_id, b.bundle_name, b.bundle_model, b.bundle_price,
                       COUNT(bi.bundle_item_id) AS item_count,
                       SUM(COALESCE(p.price,0)) AS list_price,
                       COALESCE(NULLIF(MAX(b.bundle_image), ''), COALESCE(MAX(i.image_1), 'smartphone.png')) AS image_1
                FROM tblbundle b
                LEFT JOIN tblbundle_item bi ON bi.bundle_id = b.bundle_id
                LEFT JOIN tblproduct p ON p.product_id = bi.product_id AND p.status = '1'
                LEFT JOIN tblimage i ON i.product_id = p.product_id
                WHERE b.status = 1
                  AND (b.expiry_date IS NULL OR b.expiry_date = '' OR b.expiry_date >= CURDATE())
                GROUP BY b.bundle_id
                HAVING item_count > 0
                ORDER BY b.bundle_id DESC
                LIMIT 4";
        $run = mysqli_query($conn, $sql);
        while ($run && $row = mysqli_fetch_assoc($run)) {
            $bundlePrice = (float)($row['bundle_price'] ?? 0);
            ?>
            <div class='col-md-6 col-lg-3'>
                <div class='product'>
                    <a href='bundles.php' class='img-prod'>
                        <img class='img-fluid' src='../images/<?php echo htmlspecialchars((string)($row['image_1'] ?? 'smartphone.png'), ENT_QUOTES, 'UTF-8');?>' alt='bundle'>
                        <div class='overlay'></div>
                    </a>
                    <div class='text py-3 pb-4 px-3 text-center'>
                        <h3><a href='bundles.php'><?php echo htmlspecialchars((string)$row['bundle_name'], ENT_QUOTES, 'UTF-8');?></a></h3>
                        <small class='d-block text-muted mb-1'>Model: <?php echo htmlspecialchars((string)($row['bundle_model'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                        <div class='d-flex'>
                            <div class='pricing'>
                                <p class='price'>
                                    <span class='price-sale'><?php echo "Rs. " . number_format($bundlePrice, 2);?></span>
                                </p>
                            </div>
                        </div>
                        <div class='bottom-area d-flex px-3'>
                            <div class='m-auto d-flex'>
                                <a href='bundles.php' class='add-to-cart d-flex justify-content-center align-items-center text-center'>
                                    <span><i class='ion-ios-menu'></i></span>
                                </a>
                                <a href='javascript:void(0)' class='buy-now d-flex justify-content-center align-items-center mx-1 js-add-bundle-cart' data-bundle-id='<?php echo (int)$row['bundle_id']; ?>'>
                                    <span><i class='ion-ios-cart'></i></span>
                                </a>
                                <a href='javascript:void(0)' class='heart d-flex justify-content-center align-items-center js-add-bundle-wishlist' data-bundle-id='<?php echo (int)$row['bundle_id']; ?>'>
                                    <span><i class='ion-ios-heart'></i></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    if($_POST['action'] == "load_personalized"){
        $userId = isset($_SESSION["user_id"]) ? (string)$_SESSION["user_id"] : "";
        if ($userId === "") {
            echo "<div class='col-12'><div class='alert alert-light border mb-0'>Login to see AI personalized recommendations.</div></div>";
        } else {
            $rows = rtel_ai_personalized_products($conn, $userId, 8);
            $mode = function_exists('rtel_ai_personalization_last_mode') ? (string)rtel_ai_personalization_last_mode() : 'fallback';
            $provider = function_exists('rtel_ai_personalization_last_provider') ? (string)rtel_ai_personalization_last_provider() : '';
            echo "<div id='personalizedMeta' class='d-none' data-mode='" . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . "' data-provider='" . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . "'></div>";
            if (count($rows) === 0) {
                echo "<div class='col-12'><div class='alert alert-light border mb-0'>No personalized products yet. Browse a few products and come back.</div></div>";
            } else {
                foreach ($rows as $row) {
                    $price = (float)($row['price'] ?? 0);
                    $comparePrice = (float)($row['cprice'] ?? 0);
                    $hasDiscount = $comparePrice > 0 && $comparePrice > $price;
                    $discount = $hasDiscount ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;
                    ?>
                    <div class='col-md-6 col-lg-3'>
                        <div class='product'>
                            <a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>' class='img-prod'>
                                <img class='img-fluid' src='../images/<?php echo htmlspecialchars((string)$row['image_1'], ENT_QUOTES, 'UTF-8');?>' alt='img'>
                                <?php if($hasDiscount){ ?>
                                    <span class='status'><?php echo $discount;?>%</span>
                                    <div class='overlay'></div>
                                <?php } ?>
                            </a>
                            <div class='text py-3 pb-4 px-3 text-center'>
                                <h3><a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>'><?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8');?></a></h3>
                                <small class='d-block text-muted mb-1'><?php echo htmlspecialchars((string)($row['brand_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                <div class='d-flex'>
                                    <div class='pricing'>
                                        <p class='price'>
                                            <?php if($hasDiscount){?>
                                                <span class='mr-2 price-dc'><?php echo "Rs. " . number_format($comparePrice, 2);?></span>
                                            <?php } ?>
                                            <span class='price-sale'><?php echo "Rs. " . number_format($price, 2);?></span>
                                        </p>
                                    </div>
                                </div>
                                <div class='bottom-area d-flex px-3'>
                                    <div class='m-auto d-flex'>
                                        <a href='product.php?product_id=<?php echo (int)$row['product_id']; ?>' class='add-to-cart d-flex justify-content-center align-items-center text-center'>
                                            <span><i class='ion-ios-menu'></i></span>
                                        </a>
                                        <a href='javascript:void(0)' class='buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                            <span><i class='ion-ios-cart'></i></span>
                                        </a>
                                        <a href='javascript:void(0)' class='heart d-flex justify-content-center align-items-center js-add-wishlist' data-product-id='<?php echo htmlspecialchars((string)$row['product_id'], ENT_QUOTES, "UTF-8"); ?>'>
                                            <span><i class='ion-ios-heart'></i></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
        }
    }

   if ($_POST['action'] == "load_comments") {

    $query = "SELECT * FROM tblcomment WHERE status='1'";
    $run = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($run)) {

        echo '
        <div class="item">
            <div class="testimony-wrap p-4 pb-5 text-center">

                <div class="user-img mb-3" style="background-image: url(../images/testimony.webp)"></div>

                <span class="quote d-flex align-items-center justify-content-center">
                    <i class="icon-quote-left"></i>
                </span>

                <p class="mb-4">"'.$row['comment'].'"</p>
                <h5>- '.$row['name'].'</h5>

            </div>
        </div>';
    }
}
    if($_POST['action'] == "load_contactinfo"){
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS whatsapp_status TINYINT(1) NOT NULL DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS insta_status TINYINT(1) NOT NULL DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tblcontact ADD COLUMN IF NOT EXISTS fb_status TINYINT(1) NOT NULL DEFAULT 1");
    $query = "SELECT * FROM tblcontact LIMIT 1";

        $run = mysqli_query($conn,$query);

        while($row = mysqli_fetch_assoc($run)){
        
    ?> 
                <div class="ftco-footer-widget mb-4">
                    <h2 class="ftco-heading-2"><?php echo $row['name']; ?></h2>
                        <div class="block-23 mb-3">
                            <ul>
                                <li><span class="icon icon-map-marker"></span><span class="text"><?php echo $row['address']; ?></span></li>
                                <br>
                                <li><a href="tel:<?php echo $row['phone']; ?>"><span class="icon icon-phone"></span><span class="text"><?php echo $row['phone']; ?></span></a></li>
                                <li><a href="mail:<?php echo $row['email']; ?>"><span class="icon icon-envelope"></span><span class="text"><?php echo $row['email']; ?></span></a></li>
                            </ul>
                        </div>

                    <ul class="ftco-footer-social list-unstyled float-md-left float-lft mt-5">
                        <?php if ((int)($row['whatsapp_status'] ?? 1) === 1 && trim((string)($row['whatsapp'] ?? '')) !== ''): ?>
                        <li class=""><a href="<?php echo htmlspecialchars((string)$row['whatsapp'], ENT_QUOTES, 'UTF-8'); ?>"><span class="icon-whatsapp"></span></a></li>
                        <?php endif; ?>
                        <?php if ((int)($row['fb_status'] ?? 1) === 1 && trim((string)($row['fb'] ?? '')) !== ''): ?>
                        <li class=""><a href="<?php echo htmlspecialchars((string)$row['fb'], ENT_QUOTES, 'UTF-8'); ?>"><span class="icon-facebook"></span></a></li>
                        <?php endif; ?>
                        <?php if ((int)($row['insta_status'] ?? 1) === 1 && trim((string)($row['insta'] ?? '')) !== ''): ?>
                        <li class=""><a href="<?php echo htmlspecialchars((string)$row['insta'], ENT_QUOTES, 'UTF-8'); ?>"><span class="icon-instagram"></span></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
            <?php 
            }
        }

    }

    if ($_POST['action'] == "live_search") {

    $keyword = mysqli_real_escape_string($conn, $_POST['keyword']);

    $query = "SELECT p.*, i.image_1 
              FROM tblproduct p 
              JOIN tblimage i ON p.product_id = i.product_id
              WHERE p.status='1' 
              AND p.name LIKE '%$keyword%'
              LIMIT 8";

    $run = mysqli_query($conn, $query);

    if (mysqli_num_rows($run) > 0) {
        while ($row = mysqli_fetch_assoc($run)) {
            echo '
            <a href="product.php?product_id='.urlencode((string)$row['product_id']).'" class="search-item">
                <img src="../images/'.htmlspecialchars((string)$row['image_1'], ENT_QUOTES, 'UTF-8').'" alt="product">
                <div>
                    <div class="search-item-title">'.htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8').'</div>
                </div>
            </a>';
        }
    } else {
        echo "<div class='search-item'><div class='search-item-title'>No products found</div></div>";
    }
}

    

?>