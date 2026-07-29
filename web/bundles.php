<?php
include "connection.php";
require "header.php";

function rtel_bundle_variant_options($featureBlob)
{
    $featureBlob = trim((string)$featureBlob);
    if ($featureBlob === '') return [];
    $parts = preg_split('/\s*\|\s*/', $featureBlob);
    $options = [];
    foreach ((array)$parts as $part) {
        $part = trim((string)$part);
        if ($part === '' || strpos($part, ':') === false) continue;
        [$name, $value] = array_map('trim', explode(':', $part, 2));
        if ($value === '') continue;
        if (preg_match('/ram|storage|rom|color|size|variant|capacity/i', $name)) {
            foreach (preg_split('/\s*[,\/]\s*/', $value) as $token) {
                $token = trim((string)$token);
                if ($token !== '') $options[] = $token;
            }
            $options[] = $value;
        }
    }
    $options = array_values(array_unique($options));
    return array_slice($options, 0, 12);
}

function rtel_bundle_split_option_list($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    $parts = preg_split('/\s*[,|]\s*/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '') $out[] = $p;
    }
    return array_values(array_unique($out));
}

function rtel_bundle_feature_is_color($featureName)
{
    $n = strtolower(trim((string)$featureName));
    return (bool)preg_match('/\b(color|colour|colours)\b/i', $n);
}

function rtel_bundle_feature_is_storage($featureName)
{
    $n = strtolower(trim(preg_replace('/\s+/', ' ', (string)$featureName)));
    return (bool)preg_match('/\b(ram|rom|storage|memory|capacity|variant|disk|ssd|hdd)\b|ram\s*\/\s*rom/i', $n);
}

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
$bundles = [];
$sql = "SELECT b.bundle_id, b.bundle_name, b.bundle_model, b.bundle_image, b.bundle_price, b.expiry_date,
               COUNT(bi.bundle_item_id) AS item_count,
               SUM(COALESCE(p.price,0)) AS list_price,
               GROUP_CONCAT(CONCAT(COALESCE(p.product_id,''), '::', COALESCE(p.name,''), '::', COALESCE(p.modal,''), '::', COALESCE(f.feature_blob,''), '::', COALESCE(i.image_1,'smartphone.png')) ORDER BY bi.sort_order SEPARATOR ' || ') AS item_details,
               COALESCE(NULLIF(MAX(b.bundle_image), ''), COALESCE(MAX(i.image_1), 'smartphone.png')) AS image_1
        FROM tblbundle b
        LEFT JOIN tblbundle_item bi ON bi.bundle_id = b.bundle_id
        LEFT JOIN tblproduct p ON p.product_id = bi.product_id AND p.status='1'
        LEFT JOIN (
            SELECT product_id, GROUP_CONCAT(CONCAT(feature_name, ':', feature_value) SEPARATOR ' | ') AS feature_blob
            FROM tblproduct_feature
            GROUP BY product_id
        ) f ON f.product_id = p.product_id
        LEFT JOIN tblimage i ON i.product_id = p.product_id
        WHERE b.status = 1
          AND (b.expiry_date IS NULL OR b.expiry_date = '' OR b.expiry_date >= CURDATE())
        GROUP BY b.bundle_id
        HAVING item_count > 0
        ORDER BY b.bundle_id DESC";
$res = mysqli_query($conn, $sql);
while ($res && $row = mysqli_fetch_assoc($res)) {
    $bundles[] = $row;
}
?>
<style>
.bundle-shell {
  border: 1px solid #dbe7fb;
  border-radius: 14px;
  background: linear-gradient(180deg, #f9fbff, #eff6ff);
  padding: 14px;
  margin-bottom: 14px;
}
.bundle-shell .bundle-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.bundle-card {
  border: 1px solid #d8e4ff;
  border-radius: 12px;
  background: #fff;
  padding: 12px;
  margin-bottom: 10px;
  box-shadow: 0 6px 16px rgba(30, 64, 175, .08);
}
.bundle-card-cover {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e5eaf7;
  margin-bottom: 10px;
}
.bundle-item-row {
  border: 1px solid #e5eaf7;
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 10px;
  background: #fff;
}
.bundle-item-thumb {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
}
.bundle-price-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.bundle-price-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  padding: 5px 9px;
  border-radius: 999px;
  border: 1px solid #dbeafe;
  background: #f8fbff;
  color: #1e40af;
}
.bundle-price-chip.save {
  border-color: #bbf7d0;
  background: #ecfdf3;
  color: #166534;
  font-weight: 700;
}
.bundle-variant-select {
  height: 34px;
  min-width: 170px;
  max-width: 230px;
  border-radius: 999px;
  border: 1px solid #d7e4ff;
  background: #f8fbff;
  font-size: 12px;
  color: #1e3a8a;
  padding: 4px 10px;
}
.bundle-variant-select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,.14);
}
</style>
<div class="hero-wrap hero-bread" style="background-image: url(../images/banner1.jpg);">
  <div class="container">
    <div class="row no-gutters slider-text align-items-center justify-content-center">
      <div class="col-md-9 ftco-animate text-center">
        <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Bundle Deals</span></p>
        <h1 class="mb-0 bread">Bundle Deals</h1>
      </div>
    </div>
  </div>
</div>

<section class="ftco-section">
  <div class="container">
    <div class="bundle-shell">
      <div class="bundle-head">
        <div>
          <h5 class="mb-1">Bundle Deals</h5>
          <small class="text-muted">Choose bundle variants and add one fixed-price bundle to cart.</small>
        </div>
      </div>
      <div class="mt-3">
        <?php if (count($bundles) === 0): ?>
          <div class="alert alert-light border mb-0">No active bundles available right now.</div>
        <?php endif; ?>
        <?php foreach ($bundles as $b): ?>
          <?php
            $bundlePrice = (float)($b['bundle_price'] ?? 0);
            $listPrice = (float)($b['list_price'] ?? 0);
            $saving = max(0, $listPrice - $bundlePrice);
          ?>
          <div class="bundle-card">
            <img class="bundle-card-cover" src="../images/<?php echo htmlspecialchars((string)($b['image_1'] ?? 'smartphone.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Bundle image">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong><?php echo htmlspecialchars((string)$b['bundle_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <span class="font-weight-bold text-dark"><?php echo "Rs. " . number_format($bundlePrice, 2); ?></span>
            </div>
            <small class="text-muted d-block mb-2">Model: <?php echo htmlspecialchars((string)($b['bundle_model'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($b['expiry_date']) ? (' | Exp: ' . htmlspecialchars((string)$b['expiry_date'], ENT_QUOTES, 'UTF-8')) : ''; ?></small>
            <div class="bundle-price-row">
              <span class="bundle-price-chip">Bundle Price: <?php echo "Rs. " . number_format($bundlePrice, 2); ?></span>
              <span class="bundle-price-chip">Items Total: <?php echo "Rs. " . number_format($listPrice, 2); ?></span>
              <?php if ($saving > 0): ?>
                <span class="bundle-price-chip save">You Save: <?php echo "Rs. " . number_format($saving, 2); ?></span>
              <?php endif; ?>
              <span class="bundle-price-chip"><?php echo (int)($b['item_count'] ?? 0); ?> item(s) in this bundle</span>
            </div>
            <div class="d-flex align-items-center" style="gap:8px;">
              <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#bundleModal-<?php echo (int)$b['bundle_id']; ?>">Customize Bundle</button>
            </div>
          </div>

          <div class="modal fade" id="bundleModal-<?php echo (int)$b['bundle_id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title"><?php echo htmlspecialchars((string)$b['bundle_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                  <div class="bundle-price-row">
                    <span class="bundle-price-chip">Bundle Price: <?php echo "Rs. " . number_format($bundlePrice, 2); ?></span>
                    <span class="bundle-price-chip">Items Total: <?php echo "Rs. " . number_format($listPrice, 2); ?></span>
                    <?php if ($saving > 0): ?>
                      <span class="bundle-price-chip save">You Save: <?php echo "Rs. " . number_format($saving, 2); ?></span>
                    <?php endif; ?>
                  </div>
                  <?php
                    $itemRows = array_filter(array_map('trim', explode('||', (string)($b['item_details'] ?? ''))));
                    foreach ($itemRows as $rowText):
                      $parts = array_map('trim', explode('::', $rowText, 5));
                      $pid = (string)($parts[0] ?? '');
                      $pname = (string)($parts[1] ?? 'Item');
                      $pmodal = (string)($parts[2] ?? '');
                      $pfeat = (string)($parts[3] ?? '');
                      $pimg = (string)($parts[4] ?? 'smartphone.png');
                      $featureRows = array_filter(array_map('trim', explode('|', $pfeat)));
                      $colorOptions = [];
                      $storageOptions = [];
                      $genericOptions = [];
                      foreach ($featureRows as $featRow) {
                          if (strpos($featRow, ':') === false) continue;
                          [$fname, $fval] = array_map('trim', explode(':', $featRow, 2));
                          if ($fval === '') continue;
                          $opts = rtel_bundle_split_option_list($fval);
                          if (count($opts) === 0) $opts = [$fval];
                          if (rtel_bundle_feature_is_color($fname)) {
                              $colorOptions = array_values(array_unique(array_merge($colorOptions, $opts)));
                          } elseif (rtel_bundle_feature_is_storage($fname)) {
                              $storageOptions = array_values(array_unique(array_merge($storageOptions, $opts)));
                          } else {
                              $genericOptions = array_values(array_unique(array_merge($genericOptions, $opts)));
                          }
                      }
                      if (count($colorOptions) === 0 && count($storageOptions) === 0 && count($genericOptions) === 0) {
                          $genericOptions = rtel_bundle_variant_options($pfeat);
                      }
                  ?>
                    <div class="bundle-item-row js-bundle-item" data-bundle-id="<?php echo (int)$b['bundle_id']; ?>" data-product-id="<?php echo htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'); ?>">
                      <div class="d-flex align-items-start" style="gap:10px;">
                        <img class="bundle-item-thumb" src="../images/<?php echo htmlspecialchars($pimg !== '' ? $pimg : 'smartphone.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Item image">
                        <div class="flex-grow-1">
                          <div class="font-weight-bold"><?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?></div>
                          <?php if ($pmodal !== ''): ?><small class="text-muted d-block"><?php echo htmlspecialchars($pmodal, ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                          <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
                            <?php if (count($colorOptions) > 0): ?>
                              <select class="form-control form-control-sm js-bundle-color bundle-variant-select" data-product-id="<?php echo htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'); ?>">
                                <option value="">Select color</option>
                                <?php foreach ($colorOptions as $opt): ?><option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <?php if (count($storageOptions) > 0): ?>
                              <select class="form-control form-control-sm js-bundle-storage bundle-variant-select" data-product-id="<?php echo htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'); ?>">
                                <option value="">Select storage</option>
                                <?php foreach ($storageOptions as $opt): ?><option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <?php if (count($colorOptions) === 0 && count($storageOptions) === 0 && count($genericOptions) > 0): ?>
                              <select class="form-control form-control-sm js-bundle-variant bundle-variant-select"
                                      data-bundle-id="<?php echo (int)$b['bundle_id']; ?>"
                                      data-product-id="<?php echo htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'); ?>">
                                <option value="">Select variant</option>
                                <?php foreach ($genericOptions as $opt): ?><option value="<?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                          </div>
                          <small class="text-muted d-block mt-1"><em>*If you don't select any varients, we will make it randomly</em></small>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                  <a href="javascript:void(0)" class="btn btn-primary btn-sm js-add-bundle-cart" data-bundle-id="<?php echo (int)$b['bundle_id']; ?>">Add Bundle to Cart</a>
                  <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm js-add-bundle-wishlist" data-bundle-id="<?php echo (int)$b['bundle_id']; ?>">Add to Wishlist</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php require "footer.php"; ?>
