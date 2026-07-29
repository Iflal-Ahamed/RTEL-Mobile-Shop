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
        .customer-status-badge { font-size: 12px; border-radius: 999px; padding: 4px 10px; }
        .status-new { background: #6c757d; color: #fff; }
        .status-regular { background: #198754; color: #fff; }
        .status-blocked { background: #dc3545; color: #fff; }
        .table td, .table th { vertical-align: middle; }
        .customer-action-group { display: inline-flex; align-items: center; gap: 8px; }
        .customer-action-btn { border: 0; background: none; padding: 0 4px; color: #344767; }
        .customer-action-btn:hover { color: #111; }
        .customer-inline-id { font-weight: 700; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('customer.php'); ?>
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Customers</h3>
                        <p class="text-muted small mb-0">Manage customer groups, block/unblock access, and keep lists clean with modal details.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Customer</li>
                            </ol>
                            <div class="text-lg-end mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm me-2" id="customerExportCurrent">Export Current Page PDF</button>
                                <button type="button" class="btn btn-primary btn-sm" id="customerExportAll">Export All PDF</button>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="customerTabs">
                            <li class="nav-item"><a href="#" class="nav-link active" data-filter="all">All (<span id="tabCountAll">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="new">New (<span id="tabCountNew">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="regular">Regular (<span id="tabCountRegular">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="promotion">Promotion Eligible (<span id="tabCountPromotion">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="blocked">Blocked (<span id="tabCountBlocked">0</span>)</a></li>
                        </ul>

                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="customerSearch" class="form-control" placeholder="Search customer id, name, email">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover text-start" id="customerTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Orders</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="customerTableBody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="customerMeta"></small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="customerPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="customerDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>ID:</strong> <span id="cdId">-</span></div>
                    <div class="col-md-6"><strong>Name:</strong> <span id="cdName">-</span></div>
                    <div class="col-md-6"><strong>Type:</strong> <span id="cdType">-</span></div>
                    <div class="col-md-6"><strong>Blocked:</strong> <span id="cdBlocked">-</span></div>
                    <div class="col-md-6"><strong>Email:</strong> <span id="cdEmail">-</span></div>
                    <div class="col-md-6"><strong>Phone:</strong> <span id="cdPhone">-</span></div>
                    <div class="col-md-6"><strong>Gender:</strong> <span id="cdGender">-</span></div>
                    <div class="col-md-6"><strong>DOB:</strong> <span id="cdDob">-</span></div>
                    <div class="col-md-6"><strong>Total Orders:</strong> <span id="cdOrders">-</span></div>
                    <div class="col-md-6"><strong>Recent Orders (30d):</strong> <span id="cdRecentOrders">-</span></div>
                    <div class="col-md-6"><strong>Total Spent:</strong> <span id="cdTotalSpent">-</span></div>
                    <div class="col-md-6"><strong>Address 1:</strong> <span id="cdAddress1">-</span></div>
                    <div class="col-md-6"><strong>Address 2:</strong> <span id="cdAddress2">-</span></div>
                    <div class="col-md-6"><strong>District:</strong> <span id="cdDistrict">-</span></div>
                    <div class="col-md-6"><strong>Province:</strong> <span id="cdProvince">-</span></div>
                    <div class="col-12"><strong>Full Address:</strong> <span id="cdAddress">-</span></div>
                    <div class="col-12"><strong>Status Note:</strong> <span id="cdNote">-</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
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
    const state = { page: 1, perPage: 10, totalPages: 1, search: "", type: "all", rows: [] };
    const body = document.getElementById('customerTableBody');
    const pagination = document.getElementById('customerPagination');
    const meta = document.getElementById('customerMeta');
    const searchInput = document.getElementById('customerSearch');
    const detailModal = new bootstrap.Modal(document.getElementById('customerDetailModal'));

    function esc(v){ return String(v || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s])); }
    function statusClass(type){
        const t = String(type || '').toLowerCase();
        if (t === 'regular') return 'status-regular';
        if (t === 'blocked') return 'status-blocked';
        return 'status-new';
    }
    function updateTabCounts(counts){
        document.getElementById('tabCountAll').textContent = String((counts && counts.all) || 0);
        document.getElementById('tabCountNew').textContent = String((counts && counts.new) || 0);
        document.getElementById('tabCountRegular').textContent = String((counts && counts.regular) || 0);
        document.getElementById('tabCountPromotion').textContent = String((counts && counts.promotion) || 0);
        document.getElementById('tabCountBlocked').textContent = String((counts && counts.blocked) || 0);
    }

    async function loadCustomers(page = 1){
        state.page = page;
        body.innerHTML = `<tr><td colspan="5" class="text-center">Loading...</td></tr>`;
        const url = `api/customer_api.php?action=list&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}&type=${encodeURIComponent(state.type)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Failed to load customers', 'error');
            return;
        }
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;
        updateTabCounts(data.counts || null);

        if (!state.rows.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No customers found.</td></tr>`;
        } else {
            body.innerHTML = state.rows.map(r => {
                const type = esc(r.customer_type || 'New');
                const statusBadge = `<span class="customer-status-badge ${statusClass(r.customer_type)}">${type}</span>`;
                const promoHint = r.promotion_eligible ? `<div><small class="text-success">Promo eligible</small></div>` : '';
                const toggleBtn = r.is_blocked
                    ? `<button class="customer-action-btn" title="Unblock" onclick="CustomerUI.toggleBlock('${esc(r.cus_id)}', false)"><i class="bi bi-person-check-fill text-success"></i></button>`
                    : `<button class="customer-action-btn" title="Block" onclick="CustomerUI.toggleBlock('${esc(r.cus_id)}', true)"><i class="bi bi-person-x-fill text-danger"></i></button>`;
                return `<tr>
                    <td><span class="customer-inline-id">${esc(r.cus_id)}</span></td>
                    <td><strong>${esc(r.name || '-')}</strong><br><small class="text-muted">${esc(r.email || '-')}</small></td>
                    <td>${esc(r.phone_1 || '-')}</td>
                    <td>${Number(r.order_count || 0)}</td>
                    <td>${statusBadge}${promoHint}</td>
                    <td>
                        <div class="customer-action-group">
                            <button class="customer-action-btn" title="Info" onclick="CustomerUI.view('${esc(r.cus_id)}')"><i class="bi bi-info-circle-fill"></i></button>
                            ${toggleBtn}
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        meta.textContent = `Page ${state.page} of ${state.totalPages} • Total customers: ${total}`;
        renderPagination();
    }

    function renderPagination(){
        const pages = state.totalPages;
        let html = '';
        const prevDisabled = state.page <= 1 ? 'disabled' : '';
        const nextDisabled = state.page >= pages ? 'disabled' : '';
        html += `<li class="page-item ${prevDisabled}"><a class="page-link" href="#" data-p="${state.page-1}">&laquo;</a></li>`;
        const start = Math.max(1, state.page - 2);
        const end = Math.min(pages, state.page + 2);
        for (let p=start; p<=end; p++) {
            html += `<li class="page-item ${p===state.page?'active':''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
        }
        html += `<li class="page-item ${nextDisabled}"><a class="page-link" href="#" data-p="${state.page+1}">&raquo;</a></li>`;
        pagination.innerHTML = html;
        pagination.querySelectorAll('a[data-p]').forEach(a => {
            a.addEventListener('click', function(e){
                e.preventDefault();
                const p = Number(this.getAttribute('data-p') || '1');
                if (p >= 1 && p <= state.totalPages && p !== state.page) loadCustomers(p);
            });
        });
    }

    async function view(cusId){
        const res = await fetch(`api/customer_api.php?action=detail&cus_id=${encodeURIComponent(cusId)}`);
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Unable to load customer details', 'error');
            return;
        }
        const d = data.detail || {};
        document.getElementById('cdId').textContent = d.cus_id || '-';
        document.getElementById('cdName').textContent = d.name || '-';
        document.getElementById('cdType').textContent = d.customer_type || '-';
        document.getElementById('cdBlocked').textContent = d.is_blocked ? 'Yes' : 'No';
        document.getElementById('cdEmail').textContent = d.email || '-';
        document.getElementById('cdPhone').textContent = d.phone_1 || '-';
        document.getElementById('cdGender').textContent = d.gender || '-';
        document.getElementById('cdDob').textContent = d.dob || '-';
        document.getElementById('cdOrders').textContent = String(d.order_count || 0);
        document.getElementById('cdRecentOrders').textContent = String(d.recent_order_count || 0);
        document.getElementById('cdTotalSpent').textContent = d.total_spent_formatted || 'Rs. 0.00';
        document.getElementById('cdAddress1').textContent = d.address_1 || '-';
        document.getElementById('cdAddress2').textContent = d.address_2 || '-';
        document.getElementById('cdDistrict').textContent = d.district || '-';
        document.getElementById('cdProvince').textContent = d.province || '-';
        document.getElementById('cdAddress').textContent = d.full_address || '-';
        document.getElementById('cdNote').textContent = d.status_reason || '-';
        detailModal.show();
    }

    async function toggleBlock(cusId, shouldBlock){
        const label = shouldBlock ? 'block' : 'unblock';
        const ask = await Swal.fire({
            title: `Are you sure to ${label} this customer?`,
            input: 'text',
            inputPlaceholder: 'Optional note for email',
            showCancelButton: true,
            confirmButtonText: shouldBlock ? 'Block' : 'Unblock'
        });
        if (!ask.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'toggle_block');
        fd.append('cus_id', cusId);
        fd.append('blocked', shouldBlock ? '1' : '0');
        fd.append('note', String(ask.value || '').trim());
        const res = await fetch('api/customer_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Unable to update customer', 'error');
            return;
        }
        Swal.fire('Updated', data.message || 'Customer updated', 'success');
        loadCustomers(state.page);
    }

    function exportRows(title, rows, fileName){
        if (!window.jspdf || !window.jspdf.jsPDF) {
            Swal.fire('Error', 'PDF library missing', 'error');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape' });
        doc.text(title, 14, 14);
        doc.autoTable({
            startY: 20,
            head: [['Customer ID', 'Name', 'Email', 'Phone', 'Orders', 'Type']],
            body: rows.map(r => [r.cus_id, r.name || '-', r.email || '-', r.phone_1 || '-', String(r.order_count || 0), r.customer_type || 'New'])
        });
        doc.save(fileName);
    }

    document.getElementById('customerExportCurrent').addEventListener('click', () => {
        if (!state.rows.length) return Swal.fire('Info', 'No rows to export', 'info');
        exportRows('Customers - Current Page', state.rows, 'customers-current-page.pdf');
    });

    document.getElementById('customerExportAll').addEventListener('click', async () => {
        const res = await fetch(`api/customer_api.php?action=list&page=1&per_page=5000&search=${encodeURIComponent(state.search)}&type=${encodeURIComponent(state.type)}`);
        const data = await res.json();
        if (!data || data.status !== 'success' || !Array.isArray(data.rows) || !data.rows.length) {
            Swal.fire('Info', 'No rows to export', 'info');
            return;
        }
        exportRows('Customers - All', data.rows, 'customers-all.pdf');
    });

    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            state.search = searchInput.value.trim();
            loadCustomers(1);
        }, 300);
    });

    document.querySelectorAll('#customerTabs .nav-link[data-filter]').forEach((tab) => {
        tab.addEventListener('click', function(e){
            e.preventDefault();
            document.querySelectorAll('#customerTabs .nav-link').forEach((t) => t.classList.remove('active'));
            this.classList.add('active');
            state.type = this.getAttribute('data-filter') || 'all';
            loadCustomers(1);
        });
    });

    const query = new URLSearchParams(window.location.search);
    const initialSearch = String(query.get('search') || '').trim();
    if (initialSearch) {
        state.search = initialSearch;
        searchInput.value = initialSearch;
    }

    window.CustomerUI = { view, toggleBlock };
    loadCustomers(1);
})();
</script>
</body>
</html>
