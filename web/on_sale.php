<?php
include "connection.php";
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($currentPage - 1) * $perPage;

$categories = [];
$catList = mysqli_query($conn, "SELECT cat_id, name FROM tblcategory WHERE status='1' ORDER BY name ASC");
if ($catList) while ($r = mysqli_fetch_assoc($catList)) $categories[] = $r;

$brands = [];
$brandList = mysqli_query($conn, "SELECT brand_id, name FROM tblbrand WHERE status='1' ORDER BY name ASC");
if ($brandList) while ($r = mysqli_fetch_assoc($brandList)) $brands[] = $r;

$totalProducts = 0;
$countQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblproduct WHERE status='1' AND cprice > 0 AND cprice > price");
if ($countQuery) {
    $countRow = mysqli_fetch_assoc($countQuery);
    $totalProducts = (int)($countRow['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$products = [];
$stmt = $conn->prepare(
    "SELECT p.product_id, p.name, p.price, p.cprice, i.image_1
     FROM tblproduct p
     JOIN tblimage i ON p.product_id = i.product_id
     WHERE p.status='1' AND p.cprice > 0 AND p.cprice > p.price
     ORDER BY p.product_id DESC
     LIMIT ? OFFSET ?"
);
if ($stmt) {
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $run = $stmt->get_result();
    while ($run && $row = mysqli_fetch_assoc($run)) $products[] = $row;
    $stmt->close();
}

require "header.php";
?>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>On Sale</span></p>
        <h1 class="mb-0 bread">On-Sale Products</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-animate search-zone">
  <div class="container mt-3 mb-2">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="sidebar-box mb-2">
          <form class="search-form" onsubmit="return false;">
            <div class="search-wrapper position-relative w-100">
              <input type="text" id="searchInput" class="form-control" placeholder="Search products..." data-i18n-placeholder="placeholder_search_products" autocomplete="off">
              <div id="searchDropdown" class="search-dropdown"></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="ftco-section">
  <div class="container">
    <div class="row">
      <div class="col-lg-3 mb-4">
        <div class="sidebar-box">
          <h5>Categories</h5>
          <ul class="list-unstyled mt-3">
            <?php foreach ($categories as $c): ?>
              <li class="mb-2"><a href="category.php?cat_id=<?php echo (int)$c['cat_id']; ?>" class="text-dark"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="sidebar-box mt-4">
          <h5>Brands</h5>
          <ul class="list-unstyled mt-3">
            <?php foreach ($brands as $b): ?>
              <li class="mb-2"><a href="brand.php?brand_id=<?php echo (int)$b['brand_id']; ?>" class="text-dark"><?php echo htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="col-lg-9">
        <div class="row g-4">
          <?php if (count($products) === 0): ?>
            <div class="col-12 text-center"><p class="mb-0">No discounted products available.</p></div>
          <?php endif; ?>
          <?php foreach ($products as $product): ?>
            <?php
              $price = (float)$product['price'];
              $comparePrice = (float)$product['cprice'];
              $discount = round((($comparePrice - $price) / $comparePrice) * 100);
            ?>
            <div class="col-md-6 col-lg-4 ftco-animate mb-4">
              <div class="product">
                <a href="product.php?product_id=<?php echo (int)$product['product_id']; ?>" class="img-prod">
                  <img class="img-fluid" src="../images/<?php echo htmlspecialchars($product['image_1'], ENT_QUOTES, 'UTF-8'); ?>" alt="img">
                  <span class="status"><?php echo $discount; ?>%</span>
                  <div class="overlay"></div>
                </a>
                <div class="text py-3 pb-4 px-3 text-center">
                  <h3><a href="product.php?product_id=<?php echo (int)$product['product_id']; ?>"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                  <div class="d-flex">
                    <div class="pricing mx-auto">
                      <p class="price">
                        <span class="mr-2 price-dc"><?php echo "Rs. " . number_format($comparePrice, 2); ?></span>
                        <span class="price-sale"><?php echo "Rs. " . number_format($price, 2); ?></span>
                      </p>
                    </div>
                  </div>
                  <div class="bottom-area d-flex px-3">
                    <div class="m-auto d-flex">
                      <a href="product.php?product_id=<?php echo (int)$product['product_id']; ?>" class="add-to-cart d-flex justify-content-center align-items-center text-center">
                        <span><i class="ion-ios-menu"></i></span>
                      </a>
                      <a href="javascript:void(0)" class="buy-now d-flex justify-content-center align-items-center mx-1 js-add-cart" data-product-id="<?php echo htmlspecialchars((string)$product['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span><i class="ion-ios-cart"></i></span>
                      </a>
                      <a href="javascript:void(0)" class="heart d-flex justify-content-center align-items-center js-add-wishlist" data-product-id="<?php echo htmlspecialchars((string)$product['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span><i class="ion-ios-heart"></i></span>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="row mt-4">
            <div class="col text-center">
              <div class="block-27">
                <ul>
                  <?php if ($currentPage > 1): ?><li><a href="?page=<?php echo $currentPage - 1; ?>">&lt;</a></li><?php endif; ?>
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="<?php echo $p === $currentPage ? 'active' : ''; ?>"><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                  <?php endfor; ?>
                  <?php if ($currentPage < $totalPages): ?><li><a href="?page=<?php echo $currentPage + 1; ?>">&gt;</a></li><?php endif; ?>
                </ul>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require "footer.php"; ?>