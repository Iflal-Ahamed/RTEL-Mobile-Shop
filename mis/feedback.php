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
        .badge-vis { font-size: 12px; border-radius: 999px; padding: 4px 10px; color:#fff; }
        .vis-on { background:#198754; }
        .vis-off { background:#dc3545; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('feedback.php'); ?>
    <div id="main">
        <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Feedback & Ratings</h3>
                        <p class="text-muted small mb-0">Control website visibility of customer feedback and product ratings.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Feedback</li>
                            </ol>
                            <div class="text-lg-end mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm me-2" id="exportCurrent">Export Current Page PDF</button>
                                <button type="button" class="btn btn-primary btn-sm" id="exportAll">Export All PDF</button>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="modTabs">
                            <li class="nav-item"><a href="#" class="nav-link active" data-kind="feedback">Feedback</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-kind="rating">Ratings</a></li>
                        </ul>
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="modSearch" class="form-control" placeholder="Search">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover text-start">
                                <thead id="modHead"></thead>
                                <tbody id="modBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="modMeta"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="modPagination"></ul></nav>
                        </div>
                    </div>
                </div>
            </section>
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
    const state = { kind: "feedback", page: 1, perPage: 10, totalPages: 1, search: "", rows: [] };
    const head = document.getElementById("modHead");
    const body = document.getElementById("modBody");
    const pagination = document.getElementById("modPagination");
    const meta = document.getElementById("modMeta");
    const searchInput = document.getElementById("modSearch");
    const esc = (v) => String(v ?? "").replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    const badge = (v) => `<span class="badge-vis ${Number(v)===1?'vis-on':'vis-off'}">${Number(v)===1?'Shown':'Hidden'}</span>`;

    function renderHead(){
        if (state.kind === "feedback") head.innerHTML = "<tr><th>Name</th><th>Feedback</th><th>Visibility</th><th>Action</th></tr>";
        else head.innerHTML = "<tr><th>Product</th><th>Rating</th><th>Review</th><th>Visibility</th><th>Action</th></tr>";
    }

    async function loadRows(page = 1){
        state.page = page;
        renderHead();
        body.innerHTML = `<tr><td colspan="${state.kind==='feedback'?4:5}" class="text-center">Loading...</td></tr>`;
        const res = await fetch(`api/feedback_api.php?action=list&kind=${encodeURIComponent(state.kind)}&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}`);
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Failed to load", "error");
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;

        if (!state.rows.length) {
            body.innerHTML = `<tr><td colspan="${state.kind==='feedback'?4:5}" class="text-center text-muted">No records found.</td></tr>`;
        } else if (state.kind === "feedback") {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.name || "-")}</strong></td>
                <td>${esc(r.comment || "-")}</td>
                <td>${badge(r.status)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ModUI.toggle(${Number(r.id)},${Number(r.status)===1?0:1})">${Number(r.status)===1?'Hide':'Show'}</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ModUI.remove(${Number(r.id)})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`).join("");
        } else {
            body.innerHTML = state.rows.map(r => `<tr>
                <td><strong>${esc(r.product_name || "-")}</strong><div><small class="text-muted">Order: ${esc(r.order_id || "-")}</small></div></td>
                <td>${"★".repeat(Number(r.rating||0))}${"☆".repeat(Math.max(0, 5-Number(r.rating||0)))}</td>
                <td>${esc(r.review_text || "-")}</td>
                <td>${badge(r.status)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ModUI.toggle(${Number(r.id)},${Number(r.status)===1?0:1})">${Number(r.status)===1?'Hide':'Show'}</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ModUI.remove(${Number(r.id)})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`).join("");
        }
        meta.textContent = `Page ${state.page} of ${state.totalPages} • Total records: ${total}`;
        renderPagination();
    }

    function renderPagination(){
        let html = "";
        const prev = state.page <= 1 ? "disabled":"";
        const next = state.page >= state.totalPages ? "disabled":"";
        html += `<li class="page-item ${prev}"><a class="page-link" href="#" data-p="${state.page-1}">&laquo;</a></li>`;
        const s = Math.max(1, state.page - 2), e = Math.min(state.totalPages, state.page + 2);
        for (let p=s; p<=e; p++) html += `<li class="page-item ${p===state.page?'active':''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
        html += `<li class="page-item ${next}"><a class="page-link" href="#" data-p="${state.page+1}">&raquo;</a></li>`;
        pagination.innerHTML = html;
        pagination.querySelectorAll("a[data-p]").forEach(a => a.addEventListener("click", function(e){
            e.preventDefault(); const p = Number(this.getAttribute("data-p")||"1");
            if (p>=1 && p<=state.totalPages && p!==state.page) loadRows(p);
        }));
    }

    async function toggleStatus(id, status){
        const fd = new FormData();
        fd.append("action","toggle_status");
        fd.append("kind", state.kind);
        fd.append("id", String(id||""));
        fd.append("status", String(status||0));
        const res = await fetch("api/feedback_api.php",{method:"POST",body:fd});
        const data = await res.json();
        if (!data || data.status!=="success") return Swal.fire("Error", data?.message || "Update failed", "error");
        Swal.fire("Updated", data.message || "Visibility updated", "success");
        loadRows(state.page);
    }

    async function removeRecord(id){
        const ok = await Swal.fire({ title: "Delete this record?", icon: "warning", showCancelButton: true, confirmButtonText: "Delete" });
        if (!ok.isConfirmed) return;
        const fd = new FormData();
        fd.append("action","delete");
        fd.append("kind", state.kind);
        fd.append("id", String(id||""));
        const res = await fetch("api/feedback_api.php",{method:"POST",body:fd});
        const data = await res.json();
        if (!data || data.status!=="success") return Swal.fire("Error", data?.message || "Delete failed", "error");
        Swal.fire("Deleted", data.message || "Deleted", "success");
        loadRows(state.page);
    }

    function exportRows(title, rows, fileName){
        if (!window.jspdf || !window.jspdf.jsPDF) return Swal.fire("Error","PDF library missing","error");
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: "landscape" });
        doc.text(title, 14, 14);
        if (state.kind === "feedback") {
            doc.autoTable({ startY: 20, head: [["Name","Feedback","Visibility"]], body: rows.map(r => [r.name || "-", r.comment || "-", Number(r.status)===1?"Shown":"Hidden"]) });
        } else {
            doc.autoTable({ startY: 20, head: [["Product","Order","Rating","Review","Visibility"]], body: rows.map(r => [r.product_name || "-", r.order_id || "-", String(r.rating || 0), r.review_text || "-", Number(r.status)===1?"Shown":"Hidden"]) });
        }
        doc.save(fileName);
    }

    document.querySelectorAll("#modTabs .nav-link").forEach(tab => tab.addEventListener("click", function(e){
        e.preventDefault();
        document.querySelectorAll("#modTabs .nav-link").forEach(t => t.classList.remove("active"));
        this.classList.add("active");
        state.kind = this.getAttribute("data-kind") || "feedback";
        state.page = 1;
        loadRows(1);
    }));
    let timer;
    searchInput.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => { state.search = searchInput.value.trim(); loadRows(1); }, 300);
    });
    document.getElementById("exportCurrent").addEventListener("click", () => {
        if (!state.rows.length) return Swal.fire("Info","No rows to export","info");
        exportRows(`${state.kind} - Current Page`, state.rows, `${state.kind}-current-page.pdf`);
    });
    document.getElementById("exportAll").addEventListener("click", async () => {
        const res = await fetch(`api/feedback_api.php?action=list&kind=${encodeURIComponent(state.kind)}&page=1&per_page=5000&search=${encodeURIComponent(state.search)}`);
        const data = await res.json();
        if (!data || data.status !== "success" || !Array.isArray(data.rows) || !data.rows.length) return Swal.fire("Info","No rows to export","info");
        exportRows(`${state.kind} - All`, data.rows, `${state.kind}-all.pdf`);
    });

    window.ModUI = { toggle: toggleStatus, remove: removeRecord };
    loadRows(1);
})();
</script>
</body>
</html>
