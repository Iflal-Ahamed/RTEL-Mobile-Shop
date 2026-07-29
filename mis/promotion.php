<?php
require_once __DIR__ . '/includes/auth.php';
rtel_require_admin_auth();
if (!rtel_admin_can_access_page('promotion.php') && !rtel_admin_can_access_page('coupon.php')) {
    http_response_code(403);
    exit('Access denied.');
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
        .table td, .table th { vertical-align: middle; }
        .record-actions { display:inline-flex; gap:8px; }
        .status-pill { border-radius:999px; padding:4px 10px; font-size:12px; color:#fff; }
        .status-on { background:#198754; }
        .status-off { background:#dc3545; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('promotion.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Homepage promotions</h3>
                        <p class="text-muted small mb-0">Banners and tiles shown on the website home page (and linked promotions page). Scoped product/category offers stay under <a href="coupon.php">Coupons &amp; Discounts</a>.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Promotions</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="offerSearch" class="form-control" placeholder="Search promotions">
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-8 text-lg-end">
                                <button type="button" class="btn btn-outline-dark btn-sm me-2" id="offerExportCurrent">Export Current Page PDF</button>
                                <button type="button" class="btn btn-dark btn-sm me-2" id="offerExportAll">Export All PDF</button>
                                <button type="button" class="btn btn-primary btn-sm" id="newRecordBtn"><i class="bi bi-plus-circle me-1"></i>New</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover text-center">
                                <thead id="offerTableHead"></thead>
                                <tbody id="offerTableBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="offerMeta"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="offerPagination"></ul></nav>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="recordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordModalTitle">Homepage promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <input type="hidden" id="f_id">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label mb-1">Title</label><input class="form-control" id="hp_title"></div>
                        <div class="col-md-6"><label class="form-label mb-1">Status</label><select class="form-select" id="hp_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-md-6"><label class="form-label mb-1">Image</label><input class="form-control" id="hp_image" placeholder="banner1.jpg"></div>
                        <div class="col-md-6"><label class="form-label mb-1">Link URL</label><input class="form-control" id="hp_link" placeholder="promotions.php"></div>
                        <div class="col-12"><label class="form-label mb-1">Description</label><textarea class="form-control" rows="2" id="hp_description"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="saveRecordBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function(){
    const state = { kind: "home_promotion", page: 1, perPage: 10, totalPages: 1, search: "", rows: [] };
    const body = document.getElementById("offerTableBody");
    const head = document.getElementById("offerTableHead");
    const pagination = document.getElementById("offerPagination");
    const meta = document.getElementById("offerMeta");
    const searchInput = document.getElementById("offerSearch");
    const modal = new bootstrap.Modal(document.getElementById("recordModal"));
    const esc = (v) => String(v ?? "").replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));

    function renderHead() {
        head.innerHTML = "<tr><th>Title</th><th>Image</th><th>Link</th><th>Description</th><th>Status</th><th>Action</th></tr>";
    }

    function statusPill(status){ return `<span class="status-pill ${Number(status)===1?'status-on':'status-off'}">${Number(status)===1?'Active':'Inactive'}</span>`; }

    async function loadRows(page = 1){
        state.page = page;
        renderHead();
        body.innerHTML = `<tr><td colspan="6" class="text-center">Loading...</td></tr>`;
        const url = `api/coupon_api.php?action=list&kind=home_promotion&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Failed to load records", "error");
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;
        if (!state.rows.length) body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>`;
        else {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.title)}</strong></td>
                <td>${esc(r.image || "-")}</td>
                <td>${esc(r.link_url || "promotions.php")}</td>
                <td><small class="text-muted">${esc(r.description || "")}</small></td>
                <td>${statusPill(r.status)}</td>
                <td><div class="record-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='PromoUI.edit(${JSON.stringify(r).replace(/'/g,"&#39;")})'><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="PromoUI.remove(${Number(r.id)})"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`).join("");
        }
        meta.textContent = `Page ${state.page} of ${state.totalPages} • Total records: ${total}`;
        renderPagination();
    }

    function renderPagination(){
        let html = "";
        const prevDisabled = state.page <= 1 ? "disabled":"";
        const nextDisabled = state.page >= state.totalPages ? "disabled":"";
        html += `<li class="page-item ${prevDisabled}"><a class="page-link" href="#" data-p="${state.page-1}">&laquo;</a></li>`;
        const start = Math.max(1, state.page - 2), end = Math.min(state.totalPages, state.page + 2);
        for (let p=start; p<=end; p++) html += `<li class="page-item ${p===state.page?'active':''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
        html += `<li class="page-item ${nextDisabled}"><a class="page-link" href="#" data-p="${state.page+1}">&raquo;</a></li>`;
        pagination.innerHTML = html;
        pagination.querySelectorAll("a[data-p]").forEach(a => a.addEventListener("click", function(e){
            e.preventDefault(); const p = Number(this.getAttribute("data-p")||"1");
            if (p>=1 && p<=state.totalPages && p!==state.page) loadRows(p);
        }));
    }

    function resetForm(){
        document.getElementById("recordForm").reset();
        document.getElementById("f_id").value = "";
        document.getElementById("recordModalTitle").textContent = "New homepage promotion";
    }

    function openNew(){ resetForm(); modal.show(); }
    function openEdit(row){
        resetForm();
        document.getElementById("f_id").value = row.id || "";
        document.getElementById("recordModalTitle").textContent = "Edit homepage promotion";
        document.getElementById("hp_title").value = row.title || "";
        document.getElementById("hp_image").value = row.image || "";
        document.getElementById("hp_link").value = row.link_url || "promotions.php";
        document.getElementById("hp_description").value = row.description || "";
        document.getElementById("hp_status").value = String(row.status ?? "1");
        modal.show();
    }

    async function saveRecord(){
        const fd = new FormData();
        const id = document.getElementById("f_id").value || "";
        fd.append("action", "save_home_promotion");
        fd.append("id", id);
        fd.append("title", document.getElementById("hp_title").value);
        fd.append("image", document.getElementById("hp_image").value);
        fd.append("link_url", document.getElementById("hp_link").value);
        fd.append("description", document.getElementById("hp_description").value);
        fd.append("status", document.getElementById("hp_status").value);
        const res = await fetch("api/coupon_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to save", "error");
        modal.hide();
        Swal.fire("Saved", data.message || "Saved successfully", "success");
        loadRows(state.page);
    }

    async function removeRecord(id){
        const ok = await Swal.fire({ title: "Delete this promotion?", icon: "warning", showCancelButton: true, confirmButtonText: "Delete" });
        if (!ok.isConfirmed) return;
        const fd = new FormData();
        fd.append("action", "delete_home_promotion");
        fd.append("id", String(id || ""));
        const res = await fetch("api/coupon_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to delete", "error");
        Swal.fire("Deleted", data.message || "Deleted", "success");
        loadRows(state.page);
    }

    function exportRows(title, rows, fileName){
        if (!window.jspdf || !window.jspdf.jsPDF) return Swal.fire("Error", "PDF library missing", "error");
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: "landscape" });
        doc.text(title, 14, 14);
        const head = [["Title","Image","Link URL","Description","Status"]];
        const bodyRows = rows.map(r => [r.title || "", r.image || "", r.link_url || "promotions.php", r.description || "", Number(r.status)===1?"Active":"Inactive"]);
        doc.autoTable({ startY: 20, head, body: bodyRows });
        doc.save(fileName);
    }

    document.getElementById("newRecordBtn").addEventListener("click", openNew);
    document.getElementById("saveRecordBtn").addEventListener("click", saveRecord);
    let timer;
    searchInput.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => { state.search = searchInput.value.trim(); loadRows(1); }, 300);
    });
    document.getElementById("offerExportCurrent").addEventListener("click", () => {
        if (!state.rows.length) return Swal.fire("Info", "No rows to export", "info");
        exportRows("Homepage promotions - Current Page", state.rows, "home-promotion-current-page.pdf");
    });
    document.getElementById("offerExportAll").addEventListener("click", async () => {
        const res = await fetch(`api/coupon_api.php?action=list&kind=home_promotion&page=1&per_page=5000&search=${encodeURIComponent(state.search)}`);
        const data = await res.json();
        if (!data || data.status !== "success" || !Array.isArray(data.rows) || !data.rows.length) return Swal.fire("Info", "No rows to export", "info");
        exportRows("Homepage promotions - All", data.rows, "home-promotion-all.pdf");
    });

    window.PromoUI = { edit: openEdit, remove: removeRecord };
    loadRows(1);
})();
</script>
</body>
</html>
