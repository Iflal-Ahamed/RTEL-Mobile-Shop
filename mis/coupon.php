<?php
require_once __DIR__ . '/includes/auth.php';
rtel_require_admin_auth();
rtel_require_admin_page_access('coupon.php');
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
    <?php rtel_render_sidebar_nav('coupon.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Coupons, Discounts & Promotions</h3>
                        <p class="text-muted small mb-0">Manage coupons, special discounts, and homepage promotions shown in the website home carousel.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Coupons & Discounts</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="offerTabs">
                            <li class="nav-item"><a href="#" class="nav-link active" data-kind="coupon">Coupons</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-kind="discount">Special Discounts</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-kind="home_promotion">Website Promotions</a></li>
                        </ul>

                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="offerSearch" class="form-control" placeholder="Search records">
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
                                <tbody id="offerTableBody"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
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
                <h5 class="modal-title" id="recordModalTitle">Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <input type="hidden" id="f_id">
                    <div id="couponFields" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label mb-1">Code</label><input class="form-control" id="c_code"></div>
                            <div class="col-md-4"><label class="form-label mb-1">Discount %</label><input type="number" min="1" max="100" class="form-control" id="c_dispercentage"></div>
                            <div class="col-md-4"><label class="form-label mb-1">Min Order</label><input type="number" min="0" step="0.01" class="form-control" id="c_min_order"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Expiry Date</label><input type="date" class="form-control" id="c_expiry_date"></div>
                            <div class="col-md-3"><label class="form-label mb-1">Scope</label><select class="form-select" id="c_scope"><option value="all">All</option><option value="home">Home</option><option value="checkout">Checkout</option></select></div>
                            <div class="col-md-3"><label class="form-label mb-1">Status</label><select class="form-select" id="c_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        </div>
                    </div>
                    <div id="discountFields" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label mb-1">Title</label><input class="form-control" id="d_title"></div>
                            <div class="col-md-3"><label class="form-label mb-1">Customer Group</label><select class="form-select" id="d_group"><option value="new">New</option><option value="regular">Regular</option><option value="all">All</option></select></div>
                            <div class="col-md-3"><label class="form-label mb-1">Status</label><select class="form-select" id="d_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                            <div class="col-md-4"><label class="form-label mb-1">Discount Type</label><select class="form-select" id="d_type"><option value="percent">Percent %</option><option value="fixed">Fixed Rs</option></select></div>
                            <div class="col-md-4"><label class="form-label mb-1">Value</label><input type="number" min="0.01" step="0.01" class="form-control" id="d_value"></div>
                            <div class="col-md-4"><label class="form-label mb-1">Min Order</label><input type="number" min="0" step="0.01" class="form-control" id="d_min_order"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Start Date</label><input type="date" class="form-control" id="d_start"></div>
                            <div class="col-md-6"><label class="form-label mb-1">End Date</label><input type="date" class="form-control" id="d_end"></div>
                            <div class="col-12"><label class="form-label mb-1">Note</label><textarea class="form-control" rows="2" id="d_note"></textarea></div>
                        </div>
                    </div>
                    <div id="promotionFields" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-8"><label class="form-label mb-1">Title</label><input class="form-control" id="hp_title"></div>
                            <div class="col-md-4"><label class="form-label mb-1">Status</label><select class="form-select" id="hp_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                            <div class="col-md-12">
                                <label class="form-label mb-1">Image File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="hp_image_file" accept="image/*">
                                <input type="hidden" id="hp_image_existing">
                                <small class="text-muted d-block mt-1" id="hp_image_name">No image selected.</small>
                            </div>
                            <div class="col-12"><label class="form-label mb-1">Description</label><textarea class="form-control" rows="2" id="hp_description"></textarea></div>
                        </div>
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
    const state = { kind: "coupon", page: 1, perPage: 10, totalPages: 1, search: "", rows: [] };
    const body = document.getElementById("offerTableBody");
    const head = document.getElementById("offerTableHead");
    const pagination = document.getElementById("offerPagination");
    const meta = document.getElementById("offerMeta");
    const searchInput = document.getElementById("offerSearch");
    const modal = new bootstrap.Modal(document.getElementById("recordModal"));
    const esc = (v) => String(v ?? "").replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    const rs = (v) => "Rs. " + Number(v || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

    function renderHead() {
        if (state.kind === "coupon") head.innerHTML = "<tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Scope</th><th>Expiry</th><th>Status</th><th>Action</th></tr>";
        else if (state.kind === "discount") head.innerHTML = "<tr><th>Title</th><th>Group</th><th>Rule</th><th>Date Range</th><th>Status</th><th>Action</th></tr>";
        else head.innerHTML = "<tr><th>Title</th><th>Image</th><th>Description</th><th>Status</th><th>Action</th></tr>";
    }

    function statusPill(status){ return `<span class="status-pill ${Number(status)===1?'status-on':'status-off'}">${Number(status)===1?'Active':'Inactive'}</span>`; }
    async function loadRows(page = 1){
        state.page = page;
        renderHead();
        body.innerHTML = `<tr><td colspan="6" class="text-center">Loading...</td></tr>`;
        const url = `api/coupon_api.php?action=list&kind=${encodeURIComponent(state.kind)}&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Failed to load records", "error");
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;
        if (!state.rows.length) body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>`;
        else if (state.kind === "coupon") {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.code)}</strong></td>
                <td>${Number(r.dispercentage||0)}%</td>
                <td>${rs(r.min_order)}</td>
                <td>${esc(r.coupon_scope || "all")}</td>
                <td>${esc(r.expiry_date || "-")}</td>
                <td>${statusPill(r.status)}</td>
                <td><div class="record-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='OfferUI.edit(${JSON.stringify(r).replace(/'/g,"&#39;")})'><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="OfferUI.remove(${Number(r.id)},'coupon')"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`).join("");
        } else if (state.kind === "discount") {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.title)}</strong><div><small class="text-muted">${esc(r.note || "")}</small></div></td>
                <td>${esc(r.customer_group)}</td>
                <td>${esc(r.discount_type)}: ${r.discount_type==='percent' ? (Number(r.discount_value||0)+'%') : rs(r.discount_value)} <div><small class="text-muted">Min ${rs(r.min_order)}</small></div></td>
                <td>${esc(r.start_date||"-")} to ${esc(r.end_date||"-")}</td>
                <td>${statusPill(r.status)}</td>
                <td><div class="record-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='OfferUI.edit(${JSON.stringify(r).replace(/'/g,"&#39;")})'><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="OfferUI.remove(${Number(r.id)},'discount')"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`).join("");
        } else {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.title)}</strong><div><small class="text-muted">${esc(r.description || "")}</small></div></td>
                <td>${r.image ? `<img src="../images/${esc(r.image)}" alt="" style="width:90px;height:50px;object-fit:cover;border-radius:6px;">` : "-"}</td>
                <td><small class="text-muted">${esc(r.description || "-")}</small></td>
                <td>${statusPill(r.status)}</td>
                <td><div class="record-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='OfferUI.edit(${JSON.stringify(r).replace(/'/g,"&#39;")})'><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="OfferUI.remove(${Number(r.id)},'home_promotion')"><i class="bi bi-trash"></i></button>
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
        document.getElementById("hp_image_existing").value = "";
        document.getElementById("hp_image_name").textContent = "No image selected.";
        ["couponFields","discountFields","promotionFields"].forEach(id => document.getElementById(id).style.display = "none");
        if (state.kind === "coupon") document.getElementById("couponFields").style.display = "";
        else if (state.kind === "discount") document.getElementById("discountFields").style.display = "";
        else { document.getElementById("promotionFields").style.display = ""; }
        document.getElementById("recordModalTitle").textContent = `New ${state.kind.charAt(0).toUpperCase()+state.kind.slice(1)}`;
    }

    function openNew(){ resetForm(); modal.show(); }
    function openEdit(row){
        resetForm();
        document.getElementById("f_id").value = row.id || "";
        document.getElementById("recordModalTitle").textContent = `Edit ${state.kind.charAt(0).toUpperCase()+state.kind.slice(1)}`;
        if (state.kind === "coupon") {
            document.getElementById("c_code").value = row.code || "";
            document.getElementById("c_dispercentage").value = row.dispercentage || "";
            document.getElementById("c_min_order").value = row.min_order || "0";
            document.getElementById("c_expiry_date").value = row.expiry_date || "";
            document.getElementById("c_scope").value = row.coupon_scope || "all";
            document.getElementById("c_status").value = String(row.status ?? "1");
        } else if (state.kind === "discount") {
            document.getElementById("d_title").value = row.title || "";
            document.getElementById("d_group").value = row.customer_group || "regular";
            document.getElementById("d_type").value = row.discount_type || "percent";
            document.getElementById("d_value").value = row.discount_value || "";
            document.getElementById("d_min_order").value = row.min_order || "0";
            document.getElementById("d_start").value = row.start_date || "";
            document.getElementById("d_end").value = row.end_date || "";
            document.getElementById("d_status").value = String(row.status ?? "1");
            document.getElementById("d_note").value = row.note || "";
        } else {
            document.getElementById("hp_title").value = row.title || "";
            document.getElementById("hp_image_existing").value = row.image || "";
            document.getElementById("hp_image_name").textContent = row.image ? ("Current image: " + row.image) : "No image selected.";
            document.getElementById("hp_status").value = String(row.status ?? "1");
            document.getElementById("hp_description").value = row.description || "";
        }
        modal.show();
    }

    async function saveRecord(){
        const fd = new FormData();
        const id = document.getElementById("f_id").value || "";
        if (state.kind === "coupon") {
            fd.append("action", "save_coupon");
            fd.append("id", id);
            fd.append("code", document.getElementById("c_code").value);
            fd.append("dispercentage", document.getElementById("c_dispercentage").value);
            fd.append("min_order", document.getElementById("c_min_order").value || "0");
            fd.append("expiry_date", document.getElementById("c_expiry_date").value);
            fd.append("coupon_scope", document.getElementById("c_scope").value || "all");
            fd.append("status", document.getElementById("c_status").value);
        } else if (state.kind === "discount") {
            fd.append("action", "save_discount");
            fd.append("id", id);
            fd.append("title", document.getElementById("d_title").value);
            fd.append("customer_group", document.getElementById("d_group").value);
            fd.append("discount_type", document.getElementById("d_type").value);
            fd.append("discount_value", document.getElementById("d_value").value);
            fd.append("min_order", document.getElementById("d_min_order").value || "0");
            fd.append("start_date", document.getElementById("d_start").value);
            fd.append("end_date", document.getElementById("d_end").value);
            fd.append("status", document.getElementById("d_status").value);
            fd.append("note", document.getElementById("d_note").value);
        } else {
            fd.append("action", "save_home_promotion");
            fd.append("id", id);
            fd.append("title", document.getElementById("hp_title").value);
            const hpExisting = (document.getElementById("hp_image_existing").value || "").trim();
            const hpFileInput = document.getElementById("hp_image_file");
            const hpFile = hpFileInput && hpFileInput.files && hpFileInput.files[0] ? hpFileInput.files[0] : null;
            if (!hpFile && !hpExisting) {
                return Swal.fire("Error", "Image file is required for website promotions.", "error");
            }
            fd.append("existing_image", hpExisting);
            if (hpFile) {
                fd.append("image_file", hpFile);
            }
            fd.append("status", document.getElementById("hp_status").value);
            fd.append("description", document.getElementById("hp_description").value);
        }
        const res = await fetch("api/coupon_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to save", "error");
        modal.hide();
        Swal.fire("Saved", data.message || "Saved successfully", "success");
        loadRows(state.page);
    }

    async function removeRecord(id, kind){
        const ok = await Swal.fire({ title: "Delete this record?", icon: "warning", showCancelButton: true, confirmButtonText: "Delete" });
        if (!ok.isConfirmed) return;
        const fd = new FormData();
        if (kind === "coupon") fd.append("action", "delete_coupon");
        else if (kind === "discount") fd.append("action", "delete_discount");
        else fd.append("action", "delete_home_promotion");
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
        let head = [], bodyRows = [];
        if (state.kind === "coupon") {
            head = [["Code","Discount %","Min Order","Scope","Expiry","Status"]];
            bodyRows = rows.map(r => [r.code, String(r.dispercentage), rs(r.min_order), r.coupon_scope || "all", r.expiry_date || "-", Number(r.status)===1?"Active":"Inactive"]);
        } else if (state.kind === "discount") {
            head = [["Title","Group","Type","Value","Min Order","Start","End","Status"]];
            bodyRows = rows.map(r => [r.title, r.customer_group, r.discount_type, r.discount_type==="percent" ? (Number(r.discount_value||0)+"%") : rs(r.discount_value), rs(r.min_order), r.start_date||"-", r.end_date||"-", Number(r.status)===1?"Active":"Inactive"]);
        } else {
            head = [["Title","Image","Description","Status"]];
            bodyRows = rows.map(r => [r.title || "", r.image || "", r.description || "", Number(r.status)===1?"Active":"Inactive"]);
        }
        doc.autoTable({ startY: 20, head, body: bodyRows });
        doc.save(fileName);
    }

    document.querySelectorAll("#offerTabs .nav-link").forEach(tab => tab.addEventListener("click", function(e){
        e.preventDefault();
        document.querySelectorAll("#offerTabs .nav-link").forEach(x => x.classList.remove("active"));
        this.classList.add("active");
        state.kind = this.getAttribute("data-kind") || "coupon";
        state.page = 1;
        loadRows(1);
    }));
    document.getElementById("newRecordBtn").addEventListener("click", openNew);
    document.getElementById("saveRecordBtn").addEventListener("click", saveRecord);
    document.getElementById("hp_image_file").addEventListener("change", function () {
        const f = this.files && this.files[0] ? this.files[0] : null;
        document.getElementById("hp_image_name").textContent = f ? ("Selected: " + f.name) : "No image selected.";
    });
    let timer;
    searchInput.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => { state.search = searchInput.value.trim(); loadRows(1); }, 300);
    });
    document.getElementById("offerExportCurrent").addEventListener("click", () => {
        if (!state.rows.length) return Swal.fire("Info", "No rows to export", "info");
        exportRows(`${state.kind} - Current Page`, state.rows, `${state.kind}-current-page.pdf`);
    });
    document.getElementById("offerExportAll").addEventListener("click", async () => {
        const res = await fetch(`api/coupon_api.php?action=list&kind=${encodeURIComponent(state.kind)}&page=1&per_page=5000&search=${encodeURIComponent(state.search)}`);
        const data = await res.json();
        if (!data || data.status !== "success" || !Array.isArray(data.rows) || !data.rows.length) return Swal.fire("Info", "No rows to export", "info");
        exportRows(`${state.kind} - All`, data.rows, `${state.kind}-all.pdf`);
    });

    window.OfferUI = { edit: openEdit, remove: removeRecord };
    loadRows(1);
})();
</script>
</body>
</html>
