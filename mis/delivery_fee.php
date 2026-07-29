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
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('delivery_fee.php'); ?>
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Delivery Fee</h3>
                        <p class="text-muted small mb-0">Manage district-wise delivery fee and free-delivery rules for customer groups.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Delivery Fee</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label mb-1">Province</label>
                                <select id="feeProvince" class="form-select">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1">District</label>
                                <select id="feeDistrict" class="form-select">
                                    <option value="">Select District</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1">Delivery Fee (Rs.)</label>
                                <input type="number" id="feeAmount" class="form-control" min="0" step="0.01" placeholder="e.g. 350.00">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="feeIsFree">
                                    <label class="form-check-label" for="feeIsFree">Free delivery for this district</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-dark btn-sm" id="saveFeeBtn">Save Delivery Fee</button>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Free Delivery Options</h6>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="freeForNew">
                            <label class="form-check-label" for="freeForNew">Free delivery for new customers</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="freeForRegular">
                            <label class="form-check-label" for="freeForRegular">Free delivery for regular customers</label>
                        </div>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="saveFreeRulesBtn">Save Free-Delivery Settings</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="feeSearch" class="form-control" placeholder="Search province or district">
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-8 text-lg-end">
                                <button type="button" class="btn btn-outline-dark btn-sm me-2" id="feeExportCurrent">Export Current Page PDF</button>
                                <button type="button" class="btn btn-dark btn-sm" id="feeExportAll">Export All PDF</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover text-center">
                                <thead>
                                    <tr>
                                        <th>Province</th>
                                        <th>District</th>
                                        <th>Fee</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="feeTableBody">
                                    <tr><td colspan="4" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="feeMeta"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="feePagination"></ul></nav>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="editFeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Delivery Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label mb-1">Province</label>
                    <input type="text" class="form-control" id="editFeeProvince" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">District</label>
                    <input type="text" class="form-control" id="editFeeDistrict" readonly>
                </div>
                <div>
                    <label class="form-label mb-1">Delivery Fee (Rs.)</label>
                    <input type="number" class="form-control" id="editFeeAmount" min="0" step="0.01" placeholder="e.g. 350.00">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="editFeeIsFree">
                        <label class="form-check-label" for="editFeeIsFree">Free delivery for this district</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="updateFeeBtn">Update Fee</button>
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
    const provinceDistrictMap = {
        western: ["Colombo", "Gampaha", "Kalutara"],
        central: ["Kandy", "Matale", "Nuwara Eliya"],
        southern: ["Galle", "Matara", "Hambantota"],
        northern: ["Jaffna", "Kilinochchi", "Mannar", "Mullaitivu", "Vavuniya"],
        eastern: ["Trincomalee", "Batticaloa", "Ampara"],
        northwestern: ["Kurunegala", "Puttalam"],
        northcentral: ["Anuradhapura", "Polonnaruwa"],
        uva: ["Badulla", "Monaragala"],
        sabaragamuwa: ["Kegalle", "Ratnapura"]
    };
    const provinceLabel = {
        western: "Western Province",
        central: "Central Province",
        southern: "Southern Province",
        northern: "Northern Province",
        eastern: "Eastern Province",
        northwestern: "North Western Province",
        northcentral: "North Central Province",
        uva: "Uva Province",
        sabaragamuwa: "Sabaragamuwa Province"
    };

    const state = { page: 1, perPage: 10, totalPages: 1, search: "", rows: [] };
    const body = document.getElementById("feeTableBody");
    const pagination = document.getElementById("feePagination");
    const meta = document.getElementById("feeMeta");
    const searchInput = document.getElementById("feeSearch");
    const provinceSelect = document.getElementById("feeProvince");
    const districtSelect = document.getElementById("feeDistrict");
    const feeInput = document.getElementById("feeAmount");
    const feeIsFree = document.getElementById("feeIsFree");
    const freeForNew = document.getElementById("freeForNew");
    const freeForRegular = document.getElementById("freeForRegular");
    const saveFeeBtn = document.getElementById("saveFeeBtn");
    const editFeeModalEl = document.getElementById("editFeeModal");
    const editFeeModal = new bootstrap.Modal(editFeeModalEl);
    const editFeeProvince = document.getElementById("editFeeProvince");
    const editFeeDistrict = document.getElementById("editFeeDistrict");
    const editFeeAmount = document.getElementById("editFeeAmount");
    const editFeeIsFree = document.getElementById("editFeeIsFree");
    const updateFeeBtn = document.getElementById("updateFeeBtn");

    function esc(v){ return String(v || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s])); }
    function rs(v){ return "Rs. " + Number(v || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }

    function initProvinceOptions() {
        Object.keys(provinceDistrictMap).forEach((k) => {
            const opt = document.createElement("option");
            opt.value = provinceLabel[k] || k;
            opt.textContent = provinceLabel[k] || k;
            opt.dataset.key = k;
            provinceSelect.appendChild(opt);
        });
    }

    function syncDistrictOptions() {
        districtSelect.innerHTML = '<option value="">Select District</option>';
        const selected = provinceSelect.options[provinceSelect.selectedIndex];
        const key = selected ? selected.dataset.key : "";
        const districts = key && provinceDistrictMap[key] ? provinceDistrictMap[key] : [];
        districts.forEach((d) => {
            const opt = document.createElement("option");
            opt.value = d;
            opt.textContent = d;
            districtSelect.appendChild(opt);
        });
    }

    async function loadFees(page = 1) {
        state.page = page;
        body.innerHTML = `<tr><td colspan="4" class="text-center">Loading...</td></tr>`;
        const url = `api/delivery_fee_api.php?action=list&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data || data.status !== "success") {
            Swal.fire("Error", data?.message || "Failed to load delivery fees", "error");
            return;
        }
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;
        freeForNew.checked = Number(data.settings?.free_for_new || 0) === 1;
        freeForRegular.checked = Number(data.settings?.free_for_regular || 0) === 1;

        if (!state.rows.length) {
            body.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No delivery fee records found.</td></tr>`;
        } else {
            body.innerHTML = state.rows.map((r) => `
                <tr>
                    <td>${esc(r.province)}</td>
                    <td>${esc(r.district)}</td>
                    <td>${Number(r.rate || 0) <= 0 ? "<span class='badge bg-success'>Free</span>" : rs(r.rate)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="DeliveryFeeUI.edit('${esc(r.province)}','${esc(r.district)}','${Number(r.rate || 0)}')">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="DeliveryFeeUI.remove('${esc(r.province)}','${esc(r.district)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join("");
        }

        meta.textContent = `Page ${state.page} of ${state.totalPages} • Total records: ${total}`;
        renderPagination();
    }

    function renderPagination() {
        let html = "";
        const prevDisabled = state.page <= 1 ? "disabled" : "";
        const nextDisabled = state.page >= state.totalPages ? "disabled" : "";
        html += `<li class="page-item ${prevDisabled}"><a class="page-link" href="#" data-p="${state.page-1}">&laquo;</a></li>`;
        const start = Math.max(1, state.page - 2);
        const end = Math.min(state.totalPages, state.page + 2);
        for (let p=start; p<=end; p++) {
            html += `<li class="page-item ${p===state.page ? "active" : ""}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
        }
        html += `<li class="page-item ${nextDisabled}"><a class="page-link" href="#" data-p="${state.page+1}">&raquo;</a></li>`;
        pagination.innerHTML = html;
        pagination.querySelectorAll("a[data-p]").forEach((a) => {
            a.addEventListener("click", function(e){
                e.preventDefault();
                const p = Number(this.getAttribute("data-p") || "1");
                if (p >= 1 && p <= state.totalPages && p !== state.page) loadFees(p);
            });
        });
    }

    function openEditModal(province, district, rate) {
        editFeeProvince.value = String(province || "");
        editFeeDistrict.value = String(district || "");
        editFeeAmount.value = String(Number(rate || 0).toFixed(2));
        if (editFeeIsFree) {
            editFeeIsFree.checked = Number(rate || 0) <= 0;
            editFeeAmount.disabled = editFeeIsFree.checked;
        }
        editFeeModal.show();
    }

    async function saveFee() {
        const province = String(provinceSelect.value || "").trim();
        const district = String(districtSelect.value || "").trim();
        const rateRaw = String(feeInput.value || "").trim();
        const isFree = !!(feeIsFree && feeIsFree.checked);
        if (!province || !district) return Swal.fire("Warning", "Please select province and district.", "warning");
        if (!isFree && (rateRaw === "" || isNaN(Number(rateRaw)))) return Swal.fire("Warning", "Please enter valid delivery fee.", "warning");
        const rate = isFree ? 0 : Number(rateRaw);
        if (rate < 0) return Swal.fire("Warning", "Delivery fee cannot be negative.", "warning");

        const fd = new FormData();
        fd.append("action", "save_rate");
        fd.append("province", province);
        fd.append("district", district);
        fd.append("rate", rate.toFixed(2));
        const res = await fetch("api/delivery_fee_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to save delivery fee", "error");
        Swal.fire("Saved", data.message || "Delivery fee saved.", "success");
        feeInput.value = "";
        if (feeIsFree) {
            feeIsFree.checked = false;
            feeInput.disabled = false;
        }
        loadFees(state.page);
    }

    async function updateFeeFromModal() {
        const province = String(editFeeProvince.value || "").trim();
        const district = String(editFeeDistrict.value || "").trim();
        const rateRaw = String(editFeeAmount.value || "").trim();
        const isFree = !!(editFeeIsFree && editFeeIsFree.checked);
        if (!province || !district) return Swal.fire("Warning", "Invalid province/district.", "warning");
        if (!isFree && (rateRaw === "" || isNaN(Number(rateRaw)))) return Swal.fire("Warning", "Please enter valid delivery fee.", "warning");
        const rate = isFree ? 0 : Number(rateRaw);
        if (rate < 0) return Swal.fire("Warning", "Delivery fee cannot be negative.", "warning");

        const fd = new FormData();
        fd.append("action", "save_rate");
        fd.append("province", province);
        fd.append("district", district);
        fd.append("rate", rate.toFixed(2));
        const res = await fetch("api/delivery_fee_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to update delivery fee", "error");
        editFeeModal.hide();
        Swal.fire("Updated", data.message || "Delivery fee updated.", "success");
        loadFees(state.page);
    }

    async function saveFreeRules() {
        const fd = new FormData();
        fd.append("action", "save_free_rules");
        fd.append("free_for_new", freeForNew.checked ? "1" : "0");
        fd.append("free_for_regular", freeForRegular.checked ? "1" : "0");
        const res = await fetch("api/delivery_fee_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to save settings", "error");
        Swal.fire("Updated", data.message || "Settings updated.", "success");
    }

    async function removeFee(province, district) {
        const ok = await Swal.fire({
            title: "Delete this delivery fee?",
            text: `${province} / ${district}`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete"
        });
        if (!ok.isConfirmed) return;

        const fd = new FormData();
        fd.append("action", "delete_rate");
        fd.append("province", province);
        fd.append("district", district);
        const res = await fetch("api/delivery_fee_api.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data || data.status !== "success") return Swal.fire("Error", data?.message || "Unable to delete", "error");
        Swal.fire("Deleted", data.message || "Delivery fee deleted.", "success");
        loadFees(state.page);
    }

    function exportRows(title, rows, fileName) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            Swal.fire("Error", "PDF library missing", "error");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: "landscape" });
        doc.text(title, 14, 14);
        doc.autoTable({
            startY: 20,
            head: [["Province", "District", "Delivery Fee"]],
            body: rows.map((r) => [r.province, r.district, rs(r.rate)])
        });
        doc.save(fileName);
    }

    let timer;
    searchInput.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            state.search = searchInput.value.trim();
            loadFees(1);
        }, 300);
    });
    provinceSelect.addEventListener("change", syncDistrictOptions);
    if (feeIsFree) {
        feeIsFree.addEventListener("change", function () {
            feeInput.disabled = feeIsFree.checked;
            if (feeIsFree.checked) feeInput.value = "0.00";
        });
    }
    if (editFeeIsFree) {
        editFeeIsFree.addEventListener("change", function () {
            editFeeAmount.disabled = editFeeIsFree.checked;
            if (editFeeIsFree.checked) editFeeAmount.value = "0.00";
        });
    }
    saveFeeBtn.addEventListener("click", saveFee);
    updateFeeBtn.addEventListener("click", updateFeeFromModal);
    document.getElementById("saveFreeRulesBtn").addEventListener("click", saveFreeRules);
    document.getElementById("feeExportCurrent").addEventListener("click", () => {
        if (!state.rows.length) return Swal.fire("Info", "No rows to export", "info");
        exportRows("Delivery Fee - Current Page", state.rows, "delivery-fee-current-page.pdf");
    });
    document.getElementById("feeExportAll").addEventListener("click", async () => {
        const res = await fetch(`api/delivery_fee_api.php?action=list&page=1&per_page=5000&search=${encodeURIComponent(state.search)}`);
        const data = await res.json();
        if (!data || data.status !== "success" || !Array.isArray(data.rows) || !data.rows.length) {
            Swal.fire("Info", "No rows to export", "info");
            return;
        }
        exportRows("Delivery Fee - All", data.rows, "delivery-fee-all.pdf");
    });

    window.DeliveryFeeUI = { remove: removeFee, edit: openEditModal };
    initProvinceOptions();
    syncDistrictOptions();
    loadFees(1);
})();
</script>
</body>
</html>
