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
</head>
<body>
    <?php
    require_once __DIR__ . '/includes/sidebar-nav.php';
    rtel_render_sidebar_nav('category.php');
    ?>
    <div id="app">
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last"><h3>Category</h3></div>
                        <div class="col-12 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Category</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <div class="card-body"><h5 class="card-title">Add Category</h5></div>
                        <div class="card-body">
                            <form id="categoryForm" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="form-label">Category Name</label>
                                            <input type="text" class="form-control" name="name" required maxlength="50">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="form-label">Category Image</label>
                                            <input class="form-control" type="file" name="image" required accept=".jpg,.jpeg,.png,.webp">
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-dark me-1 mb-1">Add</button>
                                        <button type="reset" class="btn btn-light-secondary me-1 mb-1">Reset</button>
                                    </div>
                                </div>
                            </form>
                            <hr>
                            <h5 class="card-title">All Categories</h5>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="categorySelectAll">
                                    <label class="form-check-label" for="categorySelectAll">Select all on page</label>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-dark me-1" id="categoryExportSelected">Export Selected PDF</button>
                                    <button type="button" class="btn btn-sm btn-dark" id="categoryExportAll">Export All Categories PDF</button>
                                </div>
                            </div>

                            <div class="row justify-content-end">
                                <div class="col-lg-4 mb-1">
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" id="categorySearch" class="form-control" placeholder="Search by category name">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table" id="categoryTableMain">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-check2-square"></i></th>
                                            <th>No</th>
                                            <th>Image</th>
                                            <th>Category Name</th>
                                            <th>Products</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categoryTable"></tbody>
                                </table>
                                <nav><ul class="pagination justify-content-start" id="categoryPagination"></ul></nav>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoryEditModal">
        <div class="modal-dialog">
            <form id="categoryEditForm" class="modal-content">
                <div class="modal-header"><h5>Edit Category</h5></div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="category_edit_id">
                    <input type="text" name="name" id="category_edit_name" class="form-control mb-2" required maxlength="50">
                    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="modal-footer"><button class="btn btn-success">Update</button></div>
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
</body>
</html>