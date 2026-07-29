<?php
include "connection.php";
require_once __DIR__ . '/ai/personalization_engine.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$search = trim((string)($_GET['q'] ?? ''));
$brandId = max(0, (int)($_GET['brand_id'] ?? 0));
$catId = max(0, (int)($_GET['cat_id'] ?? 0));
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($currentPage - 1) * $perPage;

$categories = [];
$catList = mysqli_query($conn, "SELECT cat_id, name FROM tblcategory WHERE status='1' ORDER BY name ASC");
if ($catList) {
    while ($r = mysqli_fetch_assoc($catList)) $categories[] = $r;
}

$brands = [];
$brandList = mysqli_query($conn, "SELECT brand_id, name FROM tblbrand WHERE status='1' ORDER BY name ASC");
if ($brandList) {
    while ($r = mysqli_fetch_assoc($brandList)) $brands[] = $r;
}

$hasProductModelColumn = false;
$modelColCheck = mysqli_query($conn, "SHOW COLUMNS FROM tblproduct LIKE 'model'");
if ($modelColCheck && mysqli_num_rows($modelColCheck) > 0) {
    $hasProductModelColumn = true;
}

$hasProductStockColumn = false;
$stockColCheck = mysqli_query($conn, "SHOW COLUMNS FROM tblproduct LIKE 'stock'");
if ($stockColCheck && mysqli_num_rows($stockColCheck) > 0) {
    $hasProductStockColumn = true;
}

$where = ["p.status='1'"];
$types = "";
$params = [];

if ($brandId > 0) {
    $where[] = "p.brand_id = ?";
    $types .= "i";
    $params[] = $brandId;
}
if ($catId > 0) {
    $where[] = "p.cat_id = ?";
    $types .= "i";
    $params[] = $catId;
}
if ($search !== "") {
    $where[] = $hasProductModelColumn ? "(p.name LIKE ? OR p.model LIKE ?)" : "(p.name LIKE ?)";
    $types .= $hasProductModelColumn ? "ss" : "s";
    $keyword = "%" . $search . "%";
    $params[] = $keyword;
    if ($hasProductModelColumn) {
        $params[] = $keyword;
    }
}

// Track logged-in search intent for AI personalization.
if ($search !== '' && !empty($_SESSION['user_id'])) {
    $trackCusId = trim((string)$_SESSION['user_id']);
    if ($trackCusId !== '') {
        rtel_ai_track_search($conn, $trackCusId, $search);
    }
}
$whereSql = implode(" AND ", $where);

$totalProducts = 0;
$countSql = "SELECT COUNT(*) AS total FROM tblproduct p WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== "") {
        $bind = [$types];
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$countStmt, "bind_param"], $bind);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $totalProducts = (int)($countRow['total'] ?? 0);
    }
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$products = [];
$modelSelect = $hasProductModelColumn ? "p.model" : "'' AS model";
$stockSelect = $hasProductStockColumn ? "p.stock" : "0 AS stock";
$listSql = "SELECT p.product_id, p.name, {$modelSelect}, p.price, p.cprice, {$stockSelect},
                   COALESCE(c.name, '') AS category_name,
                   COALESCE(b.name, '') AS brand_name,
                   COALESCE(i.image_1, '') AS image_1
            FROM tblproduct p
            LEFT JOIN tblimage i ON p.product_id = i.product_id
            LEFT JOIN tblbrand b ON p.brand_id = b.brand_id
            LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
            WHERE {$whereSql}
            ORDER BY p.product_id DESC
            LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listTypes = $types . "ii";
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind2 = [$listTypes];
    foreach ($listParams as $k => $v) $bind2[] = &$listParams[$k];
    call_user_func_array([$listStmt, "bind_param"], $bind2);
    $listStmt->execute();
    $result = $listStmt->get_result();
    while ($result && $row = mysqli_fetch_assoc($result)) $products[] = $row;
    $listStmt->close();
}

$activeBrandName = "All Brands";
foreach ($brands as $b) {
    if ((int)$b['brand_id'] === $brandId) {
        $activeBrandName = (string)$b['name'];
        break;
    }
}
$activeCategoryName = "All Categories";
foreach ($categories as $c) {
    if ((int)$c['cat_id'] === $catId) {
        $activeCategoryName = (string)$c['name'];
        break;
    }
}

function shop_query(array $override = [])
{
    $query = $_GET;
    foreach ($override as $k => $v) {
        if ($v === null || $v === '' || $v === 0) {
            unset($query[$k]);
        } else {
            $query[$k] = $v;
        }
    }
    return http_build_query($query);
}

require "header.php";
?>

<div class="hero-wrap hero-bread" style="background-image: url('../images/banner1.jpg');">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Shop</span></p>
        <h1 class="mb-0 bread">Shop</h1>
        <p class="mb-0 mt-2"><?php echo (int)$totalProducts; ?> products • <?php echo htmlspecialchars($activeBrandName, ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($activeCategoryName, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section pt-1">
  <div class="container">
    <div class="row">
      <div class="col-lg-3 mb-4">
        <div class="sidebar-box mb-4">
          <strong>Filter Products</strong>
          <form method="get" class="mt-2">
            <div class="form-group mb-2">
              <label class="mb-1">Product Search</label>
              <input type="text" id="shopSearchInput" name="q" class="form-control" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Name or model">
            </div>
            <div class="form-group mb-2">
              <label class="mb-1">Brand</label>
              <select name="brand_id" class="form-control">
                <option value="0">All Brands</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?php echo (int)$b['brand_id']; ?>" <?php echo ((int)$b['brand_id'] === $brandId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group mb-2">
              <label class="mb-1">Category</label>
              <select name="cat_id" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo (int)$c['cat_id']; ?>" <?php echo ((int)$c['cat_id'] === $catId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-dark btn-block">Go</button>
          </form>
        </div>

        <div class="sidebar-box mb-4">
          <strong>Brand Bar</strong>
          <div class="mt-2">
            <a class="btn btn-sm <?php echo $brandId === 0 ? 'btn-dark' : 'btn-outline-dark'; ?> mb-1" href="shop.php?<?php echo shop_query(['brand_id' => 0, 'page' => 1]); ?>">All</a>
            <?php foreach ($brands as $b): ?>
              <a class="btn btn-sm <?php echo ((int)$b['brand_id'] === $brandId) ? 'btn-dark' : 'btn-outline-dark'; ?> mb-1" href="shop.php?<?php echo shop_query(['brand_id' => (int)$b['brand_id'], 'page' => 1]); ?>">
                <?php echo htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="sidebar-box mb-4">
          <strong>Category Bar</strong>
          <div class="mt-2">
            <a class="btn btn-sm <?php echo $catId === 0 ? 'btn-dark' : 'btn-outline-dark'; ?> mb-1" href="shop.php?<?php echo shop_query(['cat_id' => 0, 'page' => 1]); ?>">All</a>
            <?php foreach ($categories as $c): ?>
              <a class="btn btn-sm <?php echo ((int)$c['cat_id'] === $catId) ? 'btn-dark' : 'btn-outline-dark'; ?> mb-1" href="shop.php?<?php echo shop_query(['cat_id' => (int)$c['cat_id'], 'page' => 1]); ?>">
                <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-9">
        <?php if ($search !== ''): ?>
          <div class="alert alert-light border py-2 px-3 mb-3">
            Results for: <strong><?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></strong>
          </div>
        <?php endif; ?>
        <div class="row">
          <?php if (count($products) === 0): ?>
            <div class="col-12 text-center">
              <div class="alert alert-light border">No products match your filters.</div>
            </div>
          <?php endif; ?>

          <?php foreach ($products as $product): ?>
            <?php
              $price = (float)$product['price'];
              $comparePrice = (float)$product['cprice'];
              $hasDiscount = $comparePrice > 0 && $comparePrice > $price;
              $discount = $hasDiscount ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;
              $productImage = trim((string)($product['image_1'] ?? ''));
              if ($productImage === '') $productImage = 'logo.jpg';
            ?>
            <div class="col-md-6 col-lg-4 ftco-animate mb-4">
              <div class="product">
                <a href="product.php?product_id=<?php echo (int)$product['product_id']; ?>" class="img-prod">
                  <img class="img-fluid" src="../images/<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>" alt="img">
                  <?php if ($hasDiscount): ?><span class="status"><?php echo $discount; ?>%</span><?php endif; ?>
                  <div class="overlay"></div>
                </a>
                <div class="text py-3 pb-4 px-3 text-center">
                  <small class="text-muted d-block mb-1"><?php echo htmlspecialchars((string)$product['brand_name'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars((string)$product['category_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                  <h3><a href="product.php?product_id=<?php echo (int)$product['product_id']; ?>"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                  <div class="d-flex">
                    <div class="pricing mx-auto">
                      <p class="price">
                        <?php if ($hasDiscount): ?><span class="mr-2 price-dc"><?php echo "Rs. " . number_format($comparePrice, 2); ?></span><?php endif; ?>
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
                  <?php if ($currentPage > 1): ?>
                    <li><a href="shop.php?<?php echo shop_query(['page' => $currentPage - 1]); ?>">&lt;</a></li>
                  <?php endif; ?>
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="<?php echo $p === $currentPage ? 'active' : ''; ?>">
                      <a href="shop.php?<?php echo shop_query(['page' => $p]); ?>"><?php echo $p; ?></a>
                    </li>
                  <?php endfor; ?>
                  <?php if ($currentPage < $totalPages): ?>
                    <li><a href="shop.php?<?php echo shop_query(['page' => $currentPage + 1]); ?>">&gt;</a></li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var searchInput = document.getElementById('shopSearchInput');
  if (!searchInput) return;
  searchInput.focus();
  var len = searchInput.value ? searchInput.value.length : 0;
  try { searchInput.setSelectionRange(len, len); } catch (e) {}
});
</script>

<?php require "footer.php"; ?>
