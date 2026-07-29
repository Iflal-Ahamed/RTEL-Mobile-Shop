<?php
include 'connection.php';
require_once __DIR__ . '/includes/product_schema.php';
require_once __DIR__ . '/includes/activity_logger.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rtel_clean_feature_name($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr((string)$value, 0, 120);
}

function rtel_clean_feature_value($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr((string)$value, 0, 255);
}

$swal = null;

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblproduct_feature (
    feature_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    feature_name VARCHAR(120) NOT NULL,
    feature_value TEXT NOT NULL
)");

rtel_sync_product_relationships($conn);
// Brand is optional in UI; enforce nullable brand_id in DB to avoid insert failures.
@mysqli_query($conn, "ALTER TABLE tblproduct MODIFY brand_id INT(11) NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $category = (int)($_POST['category'] ?? 0);
    $brand = (int)($_POST['brand'] ?? 0);
    $name = trim((string)($_POST['pname'] ?? ''));
    $model = trim((string)($_POST['modal'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $cpriceRaw = trim((string)($_POST['cprice'] ?? ''));
    $cprice = ($cpriceRaw === '') ? 0.0 : (float)$cpriceRaw;
    $quantity = (int)($_POST['quantity'] ?? 0);

    $colors = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['colors'] ?? [])))));
    $ramOptions = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['ram_options'] ?? [])))));
    $romOptions = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['rom_options'] ?? [])))));
    $featureNames = (array)($_POST['feature_name'] ?? []);
    $featureValues = (array)($_POST['feature_value'] ?? []);
    $normalizedFeatures = [];
    $featureSeen = [];
    foreach ($featureNames as $idx => $fNameRaw) {
        $fName = rtel_clean_feature_name($fNameRaw);
        $fValue = rtel_clean_feature_value((string)($featureValues[$idx] ?? ''));
        if ($fName === '' || $fValue === '') {
            continue;
        }
        $uniq = strtolower($fName . '|' . $fValue);
        if (isset($featureSeen[$uniq])) {
            continue;
        }
        $featureSeen[$uniq] = true;
        $normalizedFeatures[] = ['name' => $fName, 'value' => $fValue];
    }

    if ($category <= 0 || $name === '' || $model === '') {
        $swal = ['icon' => 'error', 'title' => 'Validation failed', 'text' => 'Category, name, and model are required.'];
    } elseif ($price <= 0 || $cprice < 0) {
        $swal = ['icon' => 'error', 'title' => 'Validation failed', 'text' => 'Price must be greater than 0, and compare price cannot be negative.'];
    } elseif ($quantity < 0) {
        $swal = ['icon' => 'error', 'title' => 'Validation failed', 'text' => 'Quantity cannot be negative.'];
    } else {
        $imageNames = [];
        $tmpPaths = [];
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $uploadedCount = 0;
        $slotKeys = ['image_1', 'image_2', 'image_3', 'image_4', 'image_5'];
        foreach ($slotKeys as $idx => $slotKey) {
            if (!isset($_FILES[$slotKey]) || !is_array($_FILES[$slotKey])) {
                continue;
            }
            $fileName = trim((string)($_FILES[$slotKey]['name'] ?? ''));
            $errorCode = (int)($_FILES[$slotKey]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fileName === '' || $errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                $swal = ['icon' => 'error', 'title' => 'Upload failed', 'text' => 'One or more selected images failed to upload.'];
                break;
            }
            $uploadedCount++;
            $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $swal = ['icon' => 'error', 'title' => 'Invalid image', 'text' => 'Only jpg, jpeg, png, webp are allowed.'];
                break;
            }
            $newName = date('YmdHis') . '_' . random_int(1000, 9999) . '_' . $idx . '.' . $ext;
            $imageNames[] = $newName;
            $tmpPaths[] = (string)($_FILES[$slotKey]['tmp_name'] ?? '');
        }
        // Backward compatibility for previous multi-file field name.
        if (!$swal && $uploadedCount === 0 && isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
            foreach ((array)$_FILES['images']['name'] as $idx => $fileName) {
                $fileName = trim((string)$fileName);
                if ($fileName === '') continue;
                $uploadedCount++;
                if ($uploadedCount > 5) break;
                $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $swal = ['icon' => 'error', 'title' => 'Invalid image', 'text' => 'Only jpg, jpeg, png, webp are allowed.'];
                    break;
                }
                $newName = date('YmdHis') . '_' . random_int(1000, 9999) . '_m' . $idx . '.' . $ext;
                $imageNames[] = $newName;
                $tmpPaths[] = (string)$_FILES['images']['tmp_name'][$idx];
            }
        }

        if (!$swal && count($imageNames) === 0) {
            $swal = ['icon' => 'error', 'title' => 'Validation failed', 'text' => 'Please upload at least 1 product image.'];
        }
        if (!$swal && count($imageNames) > 5) {
            $swal = ['icon' => 'error', 'title' => 'Validation failed', 'text' => 'Maximum 5 images are allowed.'];
        }

        if (!$swal) {
            mysqli_begin_transaction($conn);
            $moved = [];
            try {
                $idRes = $conn->query(
                    "SELECT (
                        GREATEST(
                            IFNULL((SELECT MAX(CAST(product_id AS UNSIGNED)) FROM tblproduct), 0),
                            IFNULL((SELECT MAX(CAST(product_id AS UNSIGNED)) FROM tblimage), 0),
                            IFNULL((SELECT MAX(CAST(product_id AS UNSIGNED)) FROM tblproduct_feature), 0)
                        ) + 1
                    ) AS next_id"
                );
                if (!$idRes) {
                    throw new Exception('Failed to generate product ID.');
                }
                $productId = (int)($idRes->fetch_assoc()['next_id'] ?? 0);
                if ($productId <= 0) {
                    throw new Exception('Failed to generate product ID.');
                }

                $inserted = false;
                $attempt = 0;
                while (!$inserted && $attempt < 5) {
                    $attempt++;
                    $insProduct = $conn->prepare("INSERT INTO tblproduct (product_id, cat_id, brand_id, name, modal, description, price, cprice, quantity, added_date, status) VALUES (?, ?, NULLIF(?,0), ?, ?, ?, ?, ?, ?, NOW(), 1)");
                    if (!$insProduct) {
                        throw new Exception('Failed to prepare product insert.');
                    }
                    $insProduct->bind_param("iiisssddi", $productId, $category, $brand, $name, $model, $description, $price, $cprice, $quantity);
                    if ($insProduct->execute()) {
                        $inserted = true;
                        $insProduct->close();
                        break;
                    }
                    $errNo = (int)$insProduct->errno;
                    $insProduct->close();
                    if ($errNo === 1062) {
                        $productId++;
                        continue;
                    }
                    throw new Exception('Failed to insert product.');
                }
                if (!$inserted) {
                    throw new Exception('Failed to insert product after retry.');
                }

                foreach ($imageNames as $i => $imgName) {
                    $dest = __DIR__ . '/../images/' . $imgName;
                    if (!move_uploaded_file($tmpPaths[$i], $dest)) {
                        throw new Exception('Failed to move uploaded image.');
                    }
                    $moved[] = $dest;
                }
                while (count($imageNames) < 5) {
                    $imageNames[] = $imageNames[0];
                }

                $insImage = $conn->prepare("INSERT INTO tblimage (product_id, image_1, image_2, image_3, image_4, image_5) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$insImage) {
                    throw new Exception('Failed to prepare image insert.');
                }
                $insImage->bind_param("isssss", $productId, $imageNames[0], $imageNames[1], $imageNames[2], $imageNames[3], $imageNames[4]);
                if (!$insImage->execute()) {
                    throw new Exception('Failed to insert product images.');
                }
                $insImage->close();

                $insFeature = $conn->prepare("INSERT INTO tblproduct_feature (product_id, feature_name, feature_value) VALUES (?, ?, ?)");
                if ($insFeature) {
                    foreach ($colors as $color) {
                        $featureName = 'Color';
                        $featureValue = $color;
                        $insFeature->bind_param("iss", $productId, $featureName, $featureValue);
                        $insFeature->execute();
                    }
                    foreach ($normalizedFeatures as $pair) {
                        $fName = (string)$pair['name'];
                        $fValue = (string)$pair['value'];
                        $insFeature->bind_param("iss", $productId, $fName, $fValue);
                        $insFeature->execute();
                    }
                    foreach ($ramOptions as $ram) {
                        $ram = rtel_clean_feature_value($ram);
                        if ($ram === '') {
                            continue;
                        }
                        $featureName = 'RAM Option';
                        $featureValue = $ram;
                        $insFeature->bind_param("iss", $productId, $featureName, $featureValue);
                        $insFeature->execute();
                    }
                    foreach ($romOptions as $rom) {
                        $rom = rtel_clean_feature_value($rom);
                        if ($rom === '') {
                            continue;
                        }
                        $featureName = 'ROM Option';
                        $featureValue = $rom;
                        $insFeature->bind_param("iss", $productId, $featureName, $featureValue);
                        $insFeature->execute();
                    }
                    $insFeature->close();
                }

                mysqli_commit($conn);
                rtel_admin_log_event($conn, 'product_add', 'success', 'Added product #' . $productId . ': ' . $name);
                $swal = ['icon' => 'success', 'title' => 'Product added', 'text' => 'Product and specs saved successfully.'];
            } catch (Exception $e) {
                mysqli_rollback($conn);
                foreach ($moved as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
                rtel_admin_log_event($conn, 'product_add', 'failed', 'Failed adding product: ' . $name . ' (' . $e->getMessage() . ')');
                $swal = ['icon' => 'error', 'title' => 'Save failed', 'text' => $e->getMessage()];
            }
        }
    }
}

$categories = [];
$brands = [];
$catRes = $conn->query("SELECT cat_id, name FROM tblcategory ORDER BY name ASC");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categories[] = $r;
    }
}
$brandRes = $conn->query("SELECT brand_id, name FROM tblbrand ORDER BY name ASC");
if ($brandRes) {
    while ($r = $brandRes->fetch_assoc()) {
        $brands[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R-tel Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <link rel="shortcut icon" href="../web/images/logo.jpg" type="image/x-icon">
    <style>
        .feature-row .btn { min-width: 42px; }
        .form-section {
            border: 1px solid #e6eaf0;
            border-radius: 12px;
            padding: 14px;
            background: #fbfcff;
            margin-bottom: 14px;
        }
        .form-section h6 {
            margin-bottom: 10px;
            font-weight: 700;
            color: #2e3a59;
        }
        .form-section .form-label {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .summary-panel {
            border: 1px solid #dfe5ef;
            border-radius: 12px;
            background: #ffffff;
            padding: 14px;
            position: sticky;
            top: 86px;
            box-shadow: 0 10px 24px rgba(14, 30, 64, 0.08);
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #ebeff5;
            font-size: 14px;
        }
        .summary-line:last-child { border-bottom: 0; }
        .summary-value { font-weight: 700; color: #1f2a44; }
        .summary-hint {
            border-radius: 10px;
            background: #f5f8ff;
            border: 1px solid #e3eaf8;
            padding: 10px;
            font-size: 12px;
            color: #44506a;
        }
        .gsm-preview-box {
            border: 1px solid #d9e7ff;
            background: #f8fbff;
            border-radius: 10px;
            padding: 10px;
            font-size: 13px;
        }
        .gsm-preview-list {
            max-height: 170px;
            overflow: auto;
            margin: 8px 0 0;
            padding-left: 18px;
        }
        .image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 10px; }
        .image-preview-card {
            position: relative;
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .image-preview-card img { width: 100%; height: 110px; object-fit: cover; display: block; }
        .image-remove-btn {
            position: absolute;
            top: 6px;
            left: 6px;
            width: 24px;
            height: 24px;
            border: 0;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }
        .image-preview-caption { padding: 6px 8px; font-size: 12px; color: #44506a; border-top: 1px solid #eef2f7; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/sidebar-nav.php'; rtel_render_sidebar_nav('product.php'); ?>
    <div id="app">
        <div id="main">
            <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last"><h3>Products</h3></div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Dashboard</a></li><li class="breadcrumb-item active">Products</li></ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <section>
                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Add Product</h5></div>
                        <div class="card-body">
                            <form method="POST" id="productForm" enctype="multipart/form-data">
                                <div class="row g-4">
                                    <div class="col-xl-8">
                                <div class="form-section">
                                    <h6>Basic Product Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="category" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $c): ?>
                                                    <option value="<?php echo (int)$c['cat_id']; ?>"><?php echo h($c['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Brand</label>
                                            <select class="form-select" name="brand">
                                                <option value="">No Brand (Optional)</option>
                                                <?php foreach ($brands as $b): ?>
                                                    <option value="<?php echo (int)$b['brand_id']; ?>"><?php echo h($b['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Product Name</label>
                                            <input type="text" name="pname" class="form-control" maxlength="150" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Model</label>
                                            <input type="text" name="modal" class="form-control" maxlength="50" required>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary d-none" id="gsmFetchBtn">AI Preview</button>
                                                <small class="text-muted">Visible only for categories: Phones, Flagship Phones, Used Phones.</small>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" maxlength="250" rows="3"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <div id="gsmPreview" class="gsm-preview-box mt-2 d-none">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong id="gsmPreviewTitle">AI Preview</strong>
                                                    <button type="button" class="btn btn-sm btn-primary" id="gsmApplyBtn">Apply to Form</button>
                                                </div>
                                                <div id="gsmPreviewDesc" class="mt-2 text-muted"></div>
                                                <ul id="gsmPreviewSpecs" class="gsm-preview-list"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Price, Stock & Images</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Price</label>
                                            <input type="number" name="price" id="price" class="form-control" min="0.01" step="0.01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Compare Price (optional)</label>
                                            <input type="number" name="cprice" id="cprice" class="form-control" min="0" step="0.01">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" name="quantity" id="quantity" class="form-control" min="0" step="1" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Product Images (1 to 5)</label>
                                            <input type="file" name="images[]" id="images" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple required>
                                            <div id="imagesPreviewGrid" class="image-preview-grid"></div>
                                            <small class="text-muted d-block mt-2">Select up to 5 images. Click the top-left × on a preview to remove it.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Selectable Variants & Features</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Available Colors</label>
                                            <div id="colorsWrap"></div>
                                            <button type="button" class="btn btn-sm btn-outline-dark" id="addColorBtn">+ Add Color</button>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Available RAM Options</label>
                                            <div id="ramWrap"></div>
                                            <button type="button" class="btn btn-sm btn-outline-dark" id="addRamBtn">+ Add RAM</button>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Available ROM Options</label>
                                            <div id="romWrap"></div>
                                            <button type="button" class="btn btn-sm btn-outline-dark" id="addRomBtn">+ Add ROM</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Custom Specifications (for any product type)</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <small class="text-muted d-block mb-2">Example: Screen Size, Chipset, Bluetooth Version, Strap Material, Laptop GPU, Charging Wattage.</small>
                                            <div id="featuresWrap" class="mt-2"></div>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-sm btn-outline-dark" id="addFeatureBtn">+ Add Spec</button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addFeatureValueBtn">+ Add Value</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="add" class="btn btn-primary me-2">Save Product</button>
                                    <button type="reset" class="btn btn-light-secondary">Reset</button>
                                </div>
                                    </div>
                                    <div class="col-xl-4">
                                        <div class="summary-panel">
                                            <h6 class="mb-2">Quick Summary</h6>
                                            <div class="summary-line"><span>Product</span><span id="sumName" class="summary-value">-</span></div>
                                            <div class="summary-line"><span>Model</span><span id="sumModel" class="summary-value">-</span></div>
                                            <div class="summary-line"><span>Price</span><span id="sumPrice" class="summary-value">Rs. 0.00</span></div>
                                            <div class="summary-line"><span>Compare Price</span><span id="sumCprice" class="summary-value">Rs. 0.00</span></div>
                                            <div class="summary-line"><span>Quantity</span><span id="sumQty" class="summary-value">0</span></div>
                                            <div class="summary-line"><span>Selected Images</span><span id="sumImages" class="summary-value">0</span></div>
                                            <div class="summary-line"><span>Colors</span><span id="sumColors" class="summary-value">0</span></div>
                                            <div class="summary-line"><span>RAM Options</span><span id="sumRam" class="summary-value">0</span></div>
                                            <div class="summary-line"><span>ROM Options</span><span id="sumRom" class="summary-value">0</span></div>
                                            <div class="summary-line"><span>Custom Features</span><span id="sumFeature" class="summary-value">0</span></div>
                                            <div class="summary-hint mt-3">
                                                Fill required fields first, then add variants.
                                                This panel updates live while typing.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function addSimpleRow(wrapId, inputName, placeholder) {
            const wrap = document.getElementById(wrapId);
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 mb-2 feature-row';
            row.innerHTML = `<input type="text" class="form-control" name="${inputName}" placeholder="${placeholder}" maxlength="100"><button type="button" class="btn btn-outline-danger btn-sm">-</button>`;
            row.querySelector('button').addEventListener('click', () => {
                row.remove();
                updateSummaryPanel();
            });
            row.querySelector('input').addEventListener('input', updateSummaryPanel);
            wrap.appendChild(row);
            updateSummaryPanel();
        }
        function addFeatureRow() {
            const wrap = document.getElementById('featuresWrap');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 feature-row';
            row.innerHTML = `<div class="col-md-4"><input type="text" class="form-control" name="feature_name[]" placeholder="Feature Name" maxlength="120"></div><div class="col-md-7"><input type="text" class="form-control" name="feature_value[]" placeholder="Feature Value" maxlength="255"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100">-</button></div>`;
            row.querySelector('button').addEventListener('click', () => {
                row.remove();
                updateSummaryPanel();
            });
            row.querySelectorAll('input').forEach((i) => i.addEventListener('input', updateSummaryPanel));
            wrap.appendChild(row);
            updateSummaryPanel();
        }
        function addFeatureValueRow() {
            const names = document.querySelectorAll('input[name="feature_name[]"]');
            const lastName = names.length ? String(names[names.length - 1].value || '').trim() : '';
            if (!lastName) {
                Swal.fire('Feature name required', 'Please add a feature name first, then you can add more values.', 'info');
                return;
            }
            const wrap = document.getElementById('featuresWrap');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 feature-row';
            row.innerHTML = `<div class="col-md-4"><input type="text" class="form-control" name="feature_name[]" value="${lastName.replace(/"/g, '&quot;')}" placeholder="Feature Name" maxlength="120"></div><div class="col-md-7"><input type="text" class="form-control" name="feature_value[]" placeholder="Feature Value" maxlength="255"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100">-</button></div>`;
            row.querySelector('button').addEventListener('click', () => {
                row.remove();
                updateSummaryPanel();
            });
            row.querySelectorAll('input').forEach((i) => i.addEventListener('input', updateSummaryPanel));
            wrap.appendChild(row);
            updateSummaryPanel();
        }

        function addFeatureRowWithValues(name, value) {
            const wrap = document.getElementById('featuresWrap');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 feature-row';
            const safeName = String(name || '').replace(/"/g, '&quot;');
            const safeValue = String(value || '').replace(/"/g, '&quot;');
            row.innerHTML = `<div class="col-md-4"><input type="text" class="form-control" name="feature_name[]" value="${safeName}" placeholder="Feature Name" maxlength="120"></div><div class="col-md-7"><input type="text" class="form-control" name="feature_value[]" value="${safeValue}" placeholder="Feature Value" maxlength="255"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100">-</button></div>`;
            row.querySelector('button').addEventListener('click', () => {
                row.remove();
                updateSummaryPanel();
            });
            row.querySelectorAll('input').forEach((i) => i.addEventListener('input', updateSummaryPanel));
            wrap.appendChild(row);
            updateSummaryPanel();
        }

        function isPhoneLikeCategory(text) {
            const t = String(text || '').trim().toLowerCase();
            const allowed = ['smart phones', 'phones', 'budget phones', 'flagship phones'];
            return allowed.includes(t);
        }

        function formatRs(n) {
            return 'Rs. ' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function countFilled(name) {
            return Array.from(document.querySelectorAll(`input[name="${name}"]`)).filter((el) => String(el.value || '').trim() !== '').length;
        }

        function countFeatureRows() {
            const names = Array.from(document.querySelectorAll('input[name="feature_name[]"]'));
            const values = Array.from(document.querySelectorAll('input[name="feature_value[]"]'));
            let filled = 0;
            for (let i = 0; i < Math.max(names.length, values.length); i++) {
                const n = String((names[i] && names[i].value) || '').trim();
                const v = String((values[i] && values[i].value) || '').trim();
                if (n !== '' && v !== '') filled++;
            }
            return filled;
        }

        function updateSummaryPanel() {
            const name = document.querySelector('input[name="pname"]');
            const model = document.querySelector('input[name="modal"]');
            const price = document.getElementById('price');
            const cprice = document.getElementById('cprice');
            const qty = document.getElementById('quantity');
            const imagesInput = document.getElementById('images');

            document.getElementById('sumName').textContent = String((name && name.value) || '').trim() || '-';
            document.getElementById('sumModel').textContent = String((model && model.value) || '').trim() || '-';
            document.getElementById('sumPrice').textContent = formatRs(parseFloat((price && price.value) || '0'));
            document.getElementById('sumCprice').textContent = formatRs(parseFloat((cprice && cprice.value) || '0'));
            document.getElementById('sumQty').textContent = String(parseInt((qty && qty.value) || '0', 10) || 0);
            const selectedImageCount = (imagesInput && imagesInput.files) ? imagesInput.files.length : 0;
            document.getElementById('sumImages').textContent = String(selectedImageCount);
            document.getElementById('sumColors').textContent = String(countFilled('colors[]'));
            document.getElementById('sumRam').textContent = String(countFilled('ram_options[]'));
            document.getElementById('sumRom').textContent = String(countFilled('rom_options[]'));
            document.getElementById('sumFeature').textContent = String(countFeatureRows());
        }

        let gsmPayload = null;
        let gsmLastQuery = '';
        function renderGsmPreview(data) {
            const box = document.getElementById('gsmPreview');
            const title = document.getElementById('gsmPreviewTitle');
            const desc = document.getElementById('gsmPreviewDesc');
            const specs = document.getElementById('gsmPreviewSpecs');
            if (!box || !title || !desc || !specs) return;
            title.textContent = 'AI Preview';
            desc.textContent = String(data.description || '').trim() || 'No description found.';
            specs.innerHTML = '';
            (Array.isArray(data.specs) ? data.specs : []).forEach((s) => {
                const li = document.createElement('li');
                li.textContent = `${String(s.name || '').trim()}: ${String(s.value || '').trim()}`;
                specs.appendChild(li);
            });
            box.classList.remove('d-none');
        }

        function applyGsmToForm() {
            if (!gsmPayload) return;
            const wrap = document.getElementById('featuresWrap');
            if (wrap) wrap.innerHTML = '';
            (Array.isArray(gsmPayload.specs) ? gsmPayload.specs : []).forEach((s) => {
                const n = String(s.name || '').trim();
                const v = String(s.value || '').trim();
                if (!n || !v) return;
                addFeatureRowWithValues(n, v);
            });
            updateSummaryPanel();
            Swal.fire('Applied', 'AI Preview specs applied. Add variants and any missing specs.', 'success');
        }

        document.getElementById('addColorBtn').addEventListener('click', () => addSimpleRow('colorsWrap', 'colors[]', 'Color name'));
        document.getElementById('addRamBtn').addEventListener('click', () => addSimpleRow('ramWrap', 'ram_options[]', 'RAM option (e.g. 8GB)'));
        document.getElementById('addRomBtn').addEventListener('click', () => addSimpleRow('romWrap', 'rom_options[]', 'ROM option (e.g. 128GB)'));
        document.getElementById('addFeatureBtn').addEventListener('click', addFeatureRow);
        document.getElementById('addFeatureValueBtn').addEventListener('click', addFeatureValueRow);
        const categorySelectEl = document.querySelector('select[name="category"]');
        function syncGsmButtonVisibility() {
            const gsmBtn = document.getElementById('gsmFetchBtn');
            if (!gsmBtn || !categorySelectEl) return;
            const label = String(categorySelectEl.options[categorySelectEl.selectedIndex]?.text || '');
            const allowed = isPhoneLikeCategory(label);
            gsmBtn.classList.toggle('d-none', !allowed);
            if (!allowed) {
                gsmPayload = null;
                gsmLastQuery = '';
                const box = document.getElementById('gsmPreview');
                if (box) box.classList.add('d-none');
            }
        }
        if (categorySelectEl) {
            categorySelectEl.addEventListener('change', syncGsmButtonVisibility);
            syncGsmButtonVisibility();
        }
        addSimpleRow('colorsWrap', 'colors[]', 'Color name');
        addSimpleRow('ramWrap', 'ram_options[]', 'RAM option (e.g. 8GB)');
        addSimpleRow('romWrap', 'rom_options[]', 'ROM option (e.g. 128GB)');
        addFeatureRow();
        ['pname', 'modal', 'price', 'cprice', 'quantity'].forEach((id) => {
            const el = document.getElementById(id) || document.querySelector(`input[name="${id}"]`);
            if (el) el.addEventListener('input', updateSummaryPanel);
            if (el) el.addEventListener('change', updateSummaryPanel);
        });
        let selectedImageFiles = [];
        function syncImagesInputFromSelection() {
            const input = document.getElementById('images');
            if (!input) return;
            const dt = new DataTransfer();
            selectedImageFiles.forEach((f) => dt.items.add(f));
            input.files = dt.files;
        }
        function renderImagesPreview() {
            const wrap = document.getElementById('imagesPreviewGrid');
            if (!wrap) return;
            wrap.innerHTML = '';
            selectedImageFiles.forEach((file, idx) => {
                const card = document.createElement('div');
                card.className = 'image-preview-card';
                const img = document.createElement('img');
                img.alt = 'Preview ' + (idx + 1);
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'image-remove-btn';
                del.textContent = '×';
                del.title = 'Remove image';
                del.addEventListener('click', function () {
                    selectedImageFiles.splice(idx, 1);
                    syncImagesInputFromSelection();
                    renderImagesPreview();
                    updateSummaryPanel();
                });
                const cap = document.createElement('div');
                cap.className = 'image-preview-caption';
                cap.textContent = file.name || ('Image ' + (idx + 1));
                card.appendChild(del);
                card.appendChild(img);
                card.appendChild(cap);
                wrap.appendChild(card);
                const reader = new FileReader();
                reader.onload = function (e) {
                    img.src = String((e && e.target && e.target.result) || '');
                };
                reader.readAsDataURL(file);
            });
        }
        const imagesInput = document.getElementById('images');
        if (imagesInput) {
            imagesInput.addEventListener('change', function () {
                const incoming = Array.from(imagesInput.files || []);
                if (incoming.length > 0) {
                    const existingKeys = new Set(selectedImageFiles.map((f) => [f.name, f.size, f.lastModified].join('|')));
                    incoming.forEach((f) => {
                        const key = [f.name, f.size, f.lastModified].join('|');
                        if (!existingKeys.has(key)) {
                            selectedImageFiles.push(f);
                            existingKeys.add(key);
                        }
                    });
                }
                if (selectedImageFiles.length > 5) {
                    Swal.fire('Limit reached', 'Maximum 5 images are allowed. Extra images were not added.', 'info');
                    selectedImageFiles = selectedImageFiles.slice(0, 5);
                }
                syncImagesInputFromSelection();
                renderImagesPreview();
                updateSummaryPanel();
            });
        }
        updateSummaryPanel();

        document.getElementById('productForm').addEventListener('submit', function (e) {
            const price = parseFloat(document.getElementById('price').value || '0');
            const cpriceRaw = document.getElementById('cprice').value;
            const cprice = cpriceRaw === '' ? 0 : parseFloat(cpriceRaw || '0');
            const qty = parseInt(document.getElementById('quantity').value || '-1', 10);
            const files = selectedImageFiles;
            if (price <= 0 || cprice < 0) {
                e.preventDefault();
                Swal.fire('Validation error', 'Price must be greater than 0, and compare price cannot be negative.', 'error');
                return;
            }
            if (qty < 0) {
                e.preventDefault();
                Swal.fire('Validation error', 'Quantity cannot be negative.', 'error');
                return;
            }
            if (!files || files.length < 1 || files.length > 5) {
                e.preventDefault();
                Swal.fire('Validation error', 'Please upload between 1 and 5 images.', 'error');
            }
        });
        document.getElementById('productForm').addEventListener('reset', function () {
            selectedImageFiles = [];
            const previewGrid = document.getElementById('imagesPreviewGrid');
            if (previewGrid) previewGrid.innerHTML = '';
            setTimeout(updateSummaryPanel, 10);
        });
        async function fetchGsmPreview(force = false) {
            const gsmFetchBtn = document.getElementById('gsmFetchBtn');
            const nameEl = document.querySelector('input[name="pname"]');
            const modelEl = document.querySelector('input[name="modal"]');
            const categoryEl = document.querySelector('select[name="category"]');
            const name = String((nameEl && nameEl.value) || '').trim();
            const model = String((modelEl && modelEl.value) || '').trim();
            const categoryText = categoryEl ? String(categoryEl.options[categoryEl.selectedIndex]?.text || '').toLowerCase() : '';
            if (!name) {
                if (force) Swal.fire('Missing name', 'Please enter product name first.', 'info');
                return;
            }
            const looksPhone = isPhoneLikeCategory(categoryText);
            if (!looksPhone) {
                if (force) Swal.fire('Phone only', 'This autofill is designed for phone products only.', 'info');
                return;
            }
            const queryKey = `${name}|||${model}`.toLowerCase();
            if (!force && gsmLastQuery === queryKey) {
                return;
            }
            gsmLastQuery = queryKey;
            if (gsmFetchBtn) {
                gsmFetchBtn.disabled = true;
                gsmFetchBtn.textContent = 'Fetching...';
            }
            try {
                const url = `api/gsmarena_product_ai.php?name=${encodeURIComponent(name)}&model=${encodeURIComponent(model)}`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data || data.status !== 'success') {
                    if (force) Swal.fire('Not found', data?.message || 'Unable to fetch GSMArena data.', 'warning');
                    return;
                }
                gsmPayload = data;
                renderGsmPreview(data);
            } catch (e) {
                if (force) Swal.fire('Error', 'Failed to fetch GSMArena data.', 'error');
            } finally {
                if (gsmFetchBtn) {
                    gsmFetchBtn.disabled = false;
                    gsmFetchBtn.textContent = 'AI Preview';
                }
            }
        }

        const gsmFetchBtn = document.getElementById('gsmFetchBtn');
        const gsmApplyBtn = document.getElementById('gsmApplyBtn');
        if (gsmFetchBtn) {
            gsmFetchBtn.addEventListener('click', function () { fetchGsmPreview(true); });
        }
        if (gsmApplyBtn) {
            gsmApplyBtn.addEventListener('click', applyGsmToForm);
        }
        const pnameEl = document.querySelector('input[name="pname"]');
        const pmodelEl = document.querySelector('input[name="modal"]');
        if (pnameEl) pnameEl.addEventListener('blur', () => fetchGsmPreview(false));
        if (pmodelEl) pmodelEl.addEventListener('blur', () => fetchGsmPreview(false));
    </script>
    <?php if ($swal): ?>
    <script>
        Swal.fire({
            icon: <?php echo json_encode($swal['icon']); ?>,
            title: <?php echo json_encode($swal['title']); ?>,
            text: <?php echo json_encode($swal['text']); ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>