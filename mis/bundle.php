<?php
include 'connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bundle_schema.php';
rtel_require_admin_page_access('bundle.php');
rtel_ensure_bundle_schema($conn);

$products = [];
$pr = $conn->query("SELECT product_id, name, modal FROM tblproduct WHERE status = 1 ORDER BY name ASC");
if ($pr) {
    while ($row = $pr->fetch_assoc()) $products[] = $row;
}
$availableModals = [];
$mr = $conn->query("SELECT DISTINCT TRIM(modal) AS modal_name FROM tblproduct WHERE status = 1 AND TRIM(COALESCE(modal,'')) <> '' ORDER BY modal_name ASC");
if ($mr) {
    while ($row = $mr->fetch_assoc()) {
        $m = trim((string)($row['modal_name'] ?? ''));
        if ($m !== '') $availableModals[] = $m;
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
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <link rel="shortcut icon" href="../web/images/logo.jpg" type="image/x-icon">
    <style>
        .bundle-modal .modal-dialog {
            max-width: 880px;
        }
        .bundle-modal .modal-content {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, .18);
        }
        .bundle-modal .modal-header {
            border-bottom: 1px solid #edf2f7;
            padding: 14px 18px;
        }
        .bundle-modal .modal-body {
            padding: 16px 18px 10px;
        }
        .bundle-modal .modal-footer {
            border-top: 1px solid #edf2f7;
            padding: 12px 18px;
        }
        .bundle-edit-section {
            border: 1px solid #e8edf5;
            border-radius: 12px;
            background: #fbfdff;
            padding: 14px;
            margin-bottom: 12px;
        }
        .bundle-edit-section h6 {
            margin-bottom: 12px;
            font-weight: 700;
            color: #1f2937;
        }
        .bundle-modal .form-label {
            font-weight: 700;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/includes/sidebar-nav.php';
rtel_render_sidebar_nav('bundle.php');
?>
<div id="app">
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last"><h3>Bundle Builder</h3></div>
                    <div class="col-12 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Bundle Builder</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Create Bundle</h5>
                        <form id="bundleForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Bundle Name</label>
                                    <input type="text" class="form-control" name="bundle_name" required maxlength="150">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Modal Name</label>
                                    <select class="form-select js-bundle-model-select" id="bundle_model_create" name="bundle_model" required>
                                        <option value="">Select modal</option>
                                        <?php foreach ($availableModals as $m): ?>
                                            <option value="<?php echo htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Bundle Price</label>
                                    <input type="number" class="form-control" name="bundle_price" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" name="expiry_date">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Bundle Image</label>
                                    <input type="file" class="form-control" name="bundle_image" accept=".jpg,.jpeg,.png,.webp" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Products (multi select)</label>
                                    <select class="form-select js-bundle-products-select" id="bundle_products_create" name="product_ids[]" multiple required>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo htmlspecialchars((string)$p['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string)$p['name'] . (!empty($p['modal']) ? (' (' . $p['modal'] . ')') : ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-dark me-1 mb-1">Add</button>
                                    <button type="reset" class="btn btn-light-secondary me-1 mb-1">Reset</button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <h5 class="card-title">All Bundles</h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="bundleSelectAll">
                                <label class="form-check-label" for="bundleSelectAll">Select all on page</label>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-dark me-1" id="bundleExportSelected">Export Selected PDF</button>
                                <button type="button" class="btn btn-sm btn-dark" id="bundleExportAll">Export All Bundles PDF</button>
                            </div>
                        </div>
                        <div class="row justify-content-end">
                            <div class="col-lg-4 mb-1">
                                <div class="input-group input-group-sm mb-3">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="bundleSearch" class="form-control" placeholder="Search by bundle name">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="bundleTableMain">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-check2-square"></i></th>
                                        <th>No</th>
                                        <th>Image</th>
                                        <th>Bundle Name</th>
                                        <th>Modal</th>
                                        <th>Bundle Price</th>
                                        <th>Expiry</th>
                                        <th>Products</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="bundleTable"></tbody>
                            </table>
                            <nav><ul class="pagination justify-content-start" id="bundlePagination"></ul></nav>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade bundle-modal" id="bundleEditModal">
    <div class="modal-dialog modal-lg">
        <form id="bundleEditForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="mb-0">Edit Bundle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="bundle_edit_id">
                <input type="hidden" name="old_image" id="bundle_edit_old_image">
                <div class="bundle-edit-section">
                    <h6>Bundle Details</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Bundle Name</label>
                            <input type="text" class="form-control" id="bundle_edit_name" name="bundle_name" required maxlength="150">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Modal Name</label>
                            <select class="form-select js-bundle-model-select" id="bundle_edit_model" name="bundle_model" required>
                                <option value="">Select modal</option>
                                <?php foreach ($availableModals as $m): ?>
                                    <option value="<?php echo htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Bundle Price</label>
                            <input type="number" class="form-control" id="bundle_edit_price" name="bundle_price" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" id="bundle_edit_expiry" name="expiry_date">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Bundle Image (optional replace)</label>
                            <input type="file" class="form-control" id="bundle_edit_image" name="bundle_image" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                <div class="bundle-edit-section">
                    <h6>Products in Bundle</h6>
                    <div class="col-12 mb-2">
                        <label class="form-label">Select Products</label>
                        <select class="form-select js-bundle-products-select" id="bundle_edit_products" name="product_ids[]" multiple required>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo htmlspecialchars((string)$p['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string)$p['name'] . (!empty($p['modal']) ? (' (' . $p['modal'] . ')') : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Search products and remove tags easily.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success">Update Bundle</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="assets/js/admin.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".js-bundle-products-select").forEach(function (el) {
        if (el.tomselect) return;
        new TomSelect(el, {
            plugins: ["remove_button"],
            maxItems: null,
            closeAfterSelect: false,
            placeholder: "Search and select products...",
            hideSelected: true
        });
    });
    document.querySelectorAll(".js-bundle-model-select").forEach(function (el) {
        if (el.tomselect) return;
        new TomSelect(el, {
            maxItems: 1,
            create: false,
            placeholder: "Select modal..."
        });
    });
});
</script>
</body>
</html>
