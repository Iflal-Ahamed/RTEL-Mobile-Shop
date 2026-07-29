<?php include('connection.php'); ?>
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
    rtel_render_sidebar_nav('brand.php');
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
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Brand</h3>
                        </div>
                        <div class="col-12 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Brand</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add Brand</h5>
                        </div>
                        <div class="card-body">
                            <form id="brandForm" method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Brand Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required maxlength="50">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="image" class="form-label">Brand Logo</label>
                                            <input class="form-control" type="file" id="image" name="image" required accept=".jpg,.jpeg,.png,.webp">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" required maxlength="250"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-dark me-1 mb-1" name="add">Add</button>
                                        <button type="reset" class="btn btn-light-secondary me-1 mb-1">Reset</button>
                                    </div>
                                </div>
                            </form>
                            <hr>
                            <br>
                            
                            <h5 class="card-title">All Brands</h5>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="brandSelectAll">
                                    <label class="form-check-label" for="brandSelectAll">Select all on page</label>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-dark me-1" id="brandExportSelected">Export Selected PDF</button>
                                    <button type="button" class="btn btn-sm btn-dark" id="brandExportAll">Export All Brands PDF</button>
                                </div>
                            </div>
                            
                            
                            <div class="row justify-content-end">
                                <div class="col-lg-4 mb-1 ">
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text" id="basic-addon1"><i class="bi bi-search"></i></span>
                                        <input type="text" id="search" class="form-control" placeholder="Search...">
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table" id="table1">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-check2-square"></i></th>
                                            <th>No</th>
                                            <th>Logo</th>
                                            <th>Brand Name</th>
                                            <th>Description</th>
                                        <th>Products</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="brandTable">
                                    </tbody>
                                </table>
                                <nav><ul class="pagination justify-content-start" id="pagination"></ul></nav>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <div class="modal fade" id="editModal">
            <div class="modal-dialog">
                <form id="editForm" class="modal-content">
                <div class="modal-header">
                    <h5>Edit Brand</h5>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">

                    <input type="text" name="name" id="edit_name" class="form-control mb-2" required maxlength="50">

                    <textarea name="description" id="edit_desc" class="form-control mb-2" maxlength="250"></textarea>

                    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Update</button>
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
    <script src="assets/js/admin.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

</body>
</html>