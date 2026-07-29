<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "connection.php";
require_once __DIR__ . "/includes/rtel_product_variants.php";
$userId = $_SESSION["user_id"];

$items = [];
$stmt = $conn->prepare(
    "SELECT w.wishlist_id, w.added_date, w.selected_feature, p.product_id, p.name, p.price, p.quantity AS stock_qty, i.image_1
     FROM tblwish_list w
     JOIN tblproduct p ON w.product_id = p.product_id
     LEFT JOIN tblimage i ON p.product_id = i.product_id
     WHERE w.cus_id = ?
     ORDER BY w.added_date DESC, w.wishlist_id DESC"
);
if ($stmt) {
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

require "header.php";
?>

<style>
  .bundle-variant-select {
    height: 34px;
    min-width: 140px;
    max-width: 220px;
    border-radius: 999px;
    border: 1px solid #d7e4ff;
    background: #f8fbff;
    font-size: 12px;
    color: #1e3a8a;
    padding: 4px 10px;
  }
  .js-wishlist-add-cart,
  .js-wishlist-add-cart.btn,
  .js-wishlist-add-cart.btn:hover,
  .js-wishlist-add-cart.btn:focus,
  .js-wishlist-add-cart.btn:active,
  .js-wishlist-add-cart.btn:disabled {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
  }
</style>

<div class="hero-wrap hero-bread" style="background-image: url(../images/banner1.jpg);">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Wishlist</span></p>
        <h1 class="mb-0 bread">My Wishlist</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section ftco-cart">
  <div class="container">
    <div class="row">
      <div class="col-md-12 ftco-animate">
        <div class="cart-list">
          <table class="table">
            <thead class="thead-dark">
              <tr class="text-center">
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th>Product name</th>
                <th>Price</th>
                <th>Available</th>
                <th>Added Date</th>
                <th>&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($items) === 0): ?>
                <tr><td colspan="7" class="text-center py-4">Your wishlist is empty.</td></tr>
              <?php endif; ?>
              <?php foreach ($items as $item): ?>
                <?php
                  $wlPid = (string)$item['product_id'];
                  $wlVG = rtel_pv_variant_groups_cart($conn, $wlPid);
                  $wlNeeds = rtel_pv_product_needs_variant_choice($conn, $wlPid);
                  $wlSel = trim((string)($item['selected_feature'] ?? ''));
                  $wlOk = rtel_pv_variant_selection_complete($conn, $wlPid, $wlSel);
                  $wlColorSel = rtel_pv_extract_variant_piece($wlSel, 'color');
                  $wlStorageSel = rtel_pv_extract_variant_piece($wlSel, 'storage');
                  $wlGenericSel = ($wlColorSel === '' && $wlStorageSel === '') ? $wlSel : '';
                  $stockQty = (int)($item['stock_qty'] ?? 0);
                ?>
                <tr class="text-center">
                  <td class="product-remove">
                    <a href="product_action.php?action=remove_wishlist&wishlist_id=<?php echo urlencode((string)$item['wishlist_id']); ?>">
                      <span class="ion-ios-close"></span>
                    </a>
                  </td>
                  <td class="image-prod">
                    <div class="img" style="background-image:url('../images/<?php echo htmlspecialchars((string)($item['image_1'] ?? '')); ?>');"></div>
                  </td>
                  <td class="product-name">
                    <h3><a href="product.php?product_id=<?php echo urlencode((string)$item['product_id']); ?>"><?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <?php if ($wlNeeds): ?>
                      <div class="js-wishlist-variant-row mb-2 text-left d-inline-block" style="min-width:220px;" data-wishlist-id="<?php echo htmlspecialchars((string)$item['wishlist_id'], ENT_QUOTES, 'UTF-8'); ?>" data-product-id="<?php echo htmlspecialchars($wlPid, ENT_QUOTES, 'UTF-8'); ?>">
                        <small class="text-muted d-block mb-1">Choose variant (required to move to cart)</small>
                        <div class="d-flex flex-wrap justify-content-start" style="gap:6px;">
                          <?php if (count($wlVG['color']) > 0): ?>
                            <select class="form-control form-control-sm bundle-variant-select js-wishlist-line-variant js-wishlist-line-color">
                              <option value="">Color</option>
                              <?php foreach ($wlVG['color'] as $opt): ?>
                                <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($wlColorSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                              <?php endforeach; ?>
                            </select>
                          <?php endif; ?>
                          <?php if (count($wlVG['storage']) > 0): ?>
                            <select class="form-control form-control-sm bundle-variant-select js-wishlist-line-variant js-wishlist-line-storage">
                              <option value="">Storage</option>
                              <?php foreach ($wlVG['storage'] as $opt): ?>
                                <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($wlStorageSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                              <?php endforeach; ?>
                            </select>
                          <?php endif; ?>
                          <?php if (count($wlVG['color']) === 0 && count($wlVG['storage']) === 0 && count($wlVG['generic']) > 0): ?>
                            <select class="form-control form-control-sm bundle-variant-select js-wishlist-line-variant js-wishlist-line-generic">
                              <option value="">Variant</option>
                              <?php foreach ($wlVG['generic'] as $opt): ?>
                                <option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($wlGenericSel, (string)$opt) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option>
                              <?php endforeach; ?>
                            </select>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php elseif (!empty($item['selected_feature'])): ?>
                      <p class="mb-0"><small>Feature: <?php echo htmlspecialchars((string)$item['selected_feature'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                    <?php endif; ?>
                  </td>
                  <td class="price"><?php echo "Rs. " . number_format((float)$item['price'], 2); ?></td>
                  <td class="price">
                    <?php if ($stockQty > 0): ?>
                      <?php echo $stockQty; ?>
                    <?php else: ?>
                      <span class="text-danger">0 (Out of stock)</span>
                    <?php endif; ?>
                  </td>
                  <td class="price"><?php echo htmlspecialchars((string)$item['added_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($stockQty > 0): ?>
                      <button type="button" class="btn btn-dark py-2 px-3 js-wishlist-add-cart" data-wishlist-id="<?php echo htmlspecialchars((string)$item['wishlist_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($wlNeeds && !$wlOk) ? 'disabled' : ''; ?>>Add to Cart</button>
                    <?php else: ?>
                      <span class="text-muted">Unavailable</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require "footer.php"; ?>