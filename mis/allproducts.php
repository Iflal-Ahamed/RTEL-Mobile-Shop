<?php
include 'connection.php';
$modalCategories = [];
$modalBrands = [];
$modalCatRes = $conn->query("SELECT cat_id, name FROM tblcategory ORDER BY name ASC");
if ($modalCatRes) {
    while ($r = $modalCatRes->fetch_assoc()) {
        $modalCategories[] = $r;
    }
}
$modalBrandRes = $conn->query("SELECT brand_id, name FROM tblbrand ORDER BY name ASC");
if ($modalBrandRes) {
    while ($r = $modalBrandRes->fetch_assoc()) {
        $modalBrands[] = $r;
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
        .image-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; margin-top:10px; }
        .image-preview-card { position:relative; border:1px solid #d9e2ef; border-radius:10px; overflow:hidden; background:#fff; }
        .image-preview-card img { width:100%; height:110px; object-fit:cover; display:block; }
        .image-remove-btn { position:absolute; top:6px; left:6px; width:24px; height:24px; border:0; border-radius:999px; background:rgba(0,0,0,.72); color:#fff; font-weight:700; line-height:1; cursor:pointer; }
        .image-preview-caption { padding:6px 8px; font-size:12px; color:#44506a; border-top:1px solid #eef2f7; }
    </style>
</head>
<body>
    <?php $initialSearch = trim((string)($_GET['search'] ?? '')); ?>
    <?php
    require_once __DIR__ . '/includes/sidebar-nav.php';
    rtel_render_sidebar_nav('allproducts.php');
    ?>
    <div id="app">
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>
            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last"><h3>All Products</h3></div>
                        <div class="col-12 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">All Products</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="productSelectAll">
                                    <label class="form-check-label" for="productSelectAll">Select all on page</label>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-dark me-1" id="productExportSelected">Export Selected PDF</button>
                                    <button type="button" class="btn btn-sm btn-dark" id="productExportAll">Export All Products PDF</button>
                                </div>
                            </div>
                            <div class="row justify-content-end">
                                <div class="col-lg-3 mb-1">
                                    <select id="productFilterBrand" class="form-select form-select-sm mb-3">
                                        <option value="">All Brands</option>
                                        <?php foreach ($modalBrands as $b): ?>
                                            <option value="<?php echo (int)$b['brand_id']; ?>"><?php echo htmlspecialchars((string)$b['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 mb-1">
                                    <select id="productFilterCategory" class="form-select form-select-sm mb-3">
                                        <option value="">All Categories</option>
                                        <?php foreach ($modalCategories as $c): ?>
                                            <option value="<?php echo (int)$c['cat_id']; ?>"><?php echo htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-4 mb-1">
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" id="productSearch" class="form-control" placeholder="Search by product name" value="<?php echo htmlspecialchars($initialSearch, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table" id="productTableMain">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-check2-square"></i></th>
                                            <th>No</th>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productTable"></tbody>
                                </table>
                                <nav><ul class="pagination justify-content-start" id="productPagination"></ul></nav>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="modal fade" id="productEditModal">
        <div class="modal-dialog modal-lg">
            <form id="productEditForm" class="modal-content">
                <div class="modal-header"><h5>Edit Product</h5></div>
                <div class="modal-body">
                    <input type="hidden" id="product_edit_id" name="id">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-section">
                                <h6>Basic Details</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Category</label>
                                        <select id="product_edit_category" name="category" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($modalCategories as $c): ?>
                                                <option value="<?php echo (int)$c['cat_id']; ?>"><?php echo htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Brand</label>
                                        <select id="product_edit_brand" name="brand" class="form-select">
                                            <option value="">No Brand (Optional)</option>
                                            <?php foreach ($modalBrands as $b): ?>
                                                <option value="<?php echo (int)$b['brand_id']; ?>"><?php echo htmlspecialchars((string)$b['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Name</label>
                                        <input type="text" id="product_edit_name" name="name" class="form-control" required maxlength="150">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Model</label>
                                        <input type="text" id="product_edit_modal" name="modal" class="form-control" required maxlength="50">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary d-none" id="editGsmFetchBtn">AI Preview</button>
                                            <button type="button" class="btn btn-sm btn-primary" id="editGsmApplyBtn">Apply Preview</button>
                                        </div>
                                        <small class="text-muted d-block mt-1">Visible only for categories: Phones, Flagship Phones, Used Phones.</small>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label class="form-label">Description</label>
                                        <textarea id="product_edit_description" name="description" class="form-control" maxlength="250"></textarea>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <div id="editGsmPreview" class="border rounded p-2 mt-2 bg-light d-none">
                                            <div id="editGsmPreviewDesc" class="text-muted small"></div>
                                            <ul id="editGsmPreviewSpecs" class="small mt-2 mb-0"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6>Price & Images</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Price</label>
                                        <input type="number" id="product_edit_price" name="price" class="form-control" min="0.01" step="0.01" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Compare Price (optional)</label>
                                        <input type="number" id="product_edit_cprice" name="cprice" class="form-control" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" id="product_edit_quantity" name="quantity" class="form-control" min="0" step="1" required>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label class="form-label">Replace Images (optional, up to 5)</label>
                                        <input type="file" name="images[]" id="product_edit_images" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple>
                                        <input type="hidden" name="keep_existing_images" id="product_edit_keep_existing_images" value="">
                                        <div id="productEditImagesPreview" class="image-preview-grid"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6>Variants</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label d-block">Available Colors</label>
                                        <div id="productEditColorsWrap"></div>
                                        <button type="button" class="btn btn-sm btn-outline-dark mt-1" id="addEditColorBtn">+ Color</button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label d-block">Available RAM</label>
                                        <div id="productEditRamWrap"></div>
                                        <button type="button" class="btn btn-sm btn-outline-dark mt-1" id="addEditRamBtn">+ RAM</button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label d-block">Available ROM</label>
                                        <div id="productEditRomWrap"></div>
                                        <button type="button" class="btn btn-sm btn-outline-dark mt-1" id="addEditRomBtn">+ ROM</button>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label class="form-label d-block">Available Features</label>
                                        <small class="text-muted d-block mb-2">Example: Bluetooth version, Water resistance, Charging wattage.</small>
                                        <div id="productEditFeaturesWrap"></div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-dark mt-1" id="addEditFeatureBtn">+ Feature</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="addEditFeatureValueBtn">+ Add Value</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-success">Update Product</button></div>
            </form>
        </div>
    </div>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
    (function () {
        const input = document.getElementById('productSearch');
        if (!input || !input.value.trim()) return;
        setTimeout(() => {
            input.dispatchEvent(new Event('keyup', { bubbles: true }));
        }, 100);
    })();
    </script>
</body>
</html>