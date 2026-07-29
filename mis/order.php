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
        .order-status-badge { font-size: 12px; border-radius: 999px; padding: 4px 10px; }
        .status-pending { background:#6c757d; color:#fff; }
        .status-accepted { background:#198754; color:#fff; }
        .status-on-the-way { background:#0d6efd; color:#fff; }
        .status-delivered { background:#20c997; color:#fff; }
        .status-completed { background:#212529; color:#fff; }
        .status-rejected { background:#dc3545; color:#fff; }
        .status-deleted { background:#6f42c1; color:#fff; }
        .order-action-group { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:center; }
        .order-action-btn { border:0; background:none; padding:0 6px; color:#344767; }
        .order-action-btn:hover { color:#111; }
        .btn-track-next {
            border: 1px solid #0d6efd;
            background: #0d6efd;
            color: #fff;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
        }
        .btn-track-next:hover { background:#0b5ed7; border-color:#0b5ed7; color:#fff; }
        .table td, .table th { vertical-align: middle; }
        .modal-order-item-img { width:52px; height:52px; border-radius:8px; object-fit:cover; }
        .order-inline-image { width:42px; height:42px; border-radius:8px; object-fit:cover; display:block; margin:0 auto 6px; }
        .order-inline-image-left { width:42px; height:42px; border-radius:8px; object-fit:cover; display:block; }
        .track-badge { font-size:12px; border-radius:999px; padding:4px 10px; font-weight:600; display:inline-block; margin-bottom:6px; }
        .track-pending { background:#6c757d; color:#fff; }
        .track-accepted { background:#198754; color:#fff; }
        .track-onway { background:#0d6efd; color:#fff; }
        .track-delivered { background:#20c997; color:#fff; }
        .track-completed { background:#212529; color:#fff; }
        .track-rejected { background:#dc3545; color:#fff; }
        .track-deleted { background:#6f42c1; color:#fff; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar-nav.php'; ?>
<div id="app">
    <?php rtel_render_sidebar_nav('order.php'); ?>
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
        </header>

        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>All Orders</h3>
                        <p class="text-muted small mb-0">Track order flow, search, view, export PDF, and auto-email customers after every status update. Orders the customer removed from <strong>My Orders</strong> appear as <strong>Deleted</strong> (see the Deleted tab).</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Orders</li>
                            </ol>
                            <div class="text-lg-end mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm me-2" id="orderExportCurrent">Export Current Page PDF</button>
                                <button type="button" class="btn btn-primary btn-sm" id="orderExportAll">Export All PDF</button>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="orderTabs">
                            <li class="nav-item"><a href="#" class="nav-link active" data-filter="all">All (<span id="tabCountAll">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="pending">Pending (<span id="tabCountPending">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="accepted">Accepted (<span id="tabCountAccepted">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="on_the_way">On the way (<span id="tabCountOnTheWay">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="delivered">Delivered (<span id="tabCountDelivered">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="completed">Completed (<span id="tabCountCompleted">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="rejected">Rejected (<span id="tabCountRejected">0</span>)</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-filter="deleted">Customer deleted (<span id="tabCountDeleted">0</span>)</a></li>
                        </ul>

                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="orderSearch" class="form-control" placeholder="Search order id, customer">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover text-start" id="orderTable">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Ordered Product</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Tracking</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="orderTableBody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="orderMeta"></small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="orderPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Order ID:</strong> <span id="odOrderId">-</span></div>
                    <div class="col-md-4"><strong>Date:</strong> <span id="odDate">-</span></div>
                    <div class="col-md-4"><strong>Status:</strong> <span id="odStatus">-</span></div>
                    <div class="col-md-4"><strong>Payment:</strong> <span id="odPayment">-</span></div>
                    <div class="col-md-4"><strong>Customer:</strong> <span id="odCustomer">-</span></div>
                    <div class="col-md-4"><strong>Email:</strong> <span id="odEmail">-</span></div>
                    <div class="col-md-4"><strong>Phone:</strong> <span id="odPhone">-</span></div>
                    <div class="col-12"><strong>Address:</strong> <span id="odAddress">-</span></div>
                    <div class="col-12" id="odReasonWrap" style="display:none;"><strong id="odReasonLabel">Reason:</strong> <span id="odReason">-</span></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-3"><strong>Subtotal:</strong> <span id="odSubtotal">Rs. 0.00</span></div>
                    <div class="col-md-3"><strong>Coupon:</strong> <span id="odCoupon">-</span></div>
                    <div class="col-md-3"><strong>Coupon Discount:</strong> <span id="odCouponDiscount">Rs. 0.00</span></div>
                    <div class="col-md-3"><strong>Special Discount:</strong> <span id="odSpecialDiscount">Rs. 0.00</span></div>
                    <div class="col-md-3"><strong>Special Label:</strong> <span id="odSpecialLabel">-</span></div>
                    <div class="col-md-3"><strong>Delivery Fee:</strong> <span id="odShipping">Rs. 0.00</span></div>
                    <div class="col-md-3"><strong>Grand Total:</strong> <span id="odGrandTotal">Rs. 0.00</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Image</th>
                                <th>Selected Feature</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody id="orderDetailItems"></tbody>
                    </table>
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
    const state = { page: 1, perPage: 10, totalPages: 1, search: "", status: "all", rows: [] };
    const body = document.getElementById('orderTableBody');
    const pagination = document.getElementById('orderPagination');
    const meta = document.getElementById('orderMeta');
    const searchInput = document.getElementById('orderSearch');
    const detailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));

    function rs(v){ return 'Rs. ' + Number(v || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function esc(v){ return String(v || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s])); }
    function statusClass(status){
        const s = String(status || '').toLowerCase();
        if (s === 'accepted') return 'status-accepted';
        if (s === 'on the way') return 'status-on-the-way';
        if (s === 'delivered') return 'status-delivered';
        if (s === 'completed') return 'status-completed';
        if (s === 'rejected') return 'status-rejected';
        if (s === 'deleted') return 'status-deleted';
        return 'status-pending';
    }
    function updateTabCounts(counts){
        document.getElementById('tabCountAll').textContent = String((counts && counts.all) || 0);
        document.getElementById('tabCountPending').textContent = String((counts && counts.pending) || 0);
        document.getElementById('tabCountAccepted').textContent = String((counts && counts.accepted) || 0);
        document.getElementById('tabCountOnTheWay').textContent = String((counts && counts.on_the_way) || 0);
        document.getElementById('tabCountDelivered').textContent = String((counts && counts.delivered) || 0);
        document.getElementById('tabCountCompleted').textContent = String((counts && counts.completed) || 0);
        document.getElementById('tabCountRejected').textContent = String((counts && counts.rejected) || 0);
        document.getElementById('tabCountDeleted').textContent = String((counts && counts.deleted) || 0);
    }
    function trackBadge(status){
        const s = String(status || '').toLowerCase();
        if (s === 'accepted') return `<span class="track-badge track-accepted">Accepted</span>`;
        if (s === 'on the way') return `<span class="track-badge track-onway">On the way</span>`;
        if (s === 'delivered') return `<span class="track-badge track-delivered">Delivered</span>`;
        if (s === 'completed') return `<span class="track-badge track-completed">Completed</span>`;
        if (s === 'rejected') return `<span class="track-badge track-rejected">Rejected</span>`;
        if (s === 'deleted') return `<span class="track-badge track-deleted">Deleted by customer</span>`;
        return `<span class="track-badge track-pending">Pending</span>`;
    }

    async function loadOrders(page = 1){
        state.page = page;
        body.innerHTML = `<tr><td colspan="6" class="text-center">Loading...</td></tr>`;
        const url = `api/order_api.php?action=list&page=${page}&per_page=${state.perPage}&search=${encodeURIComponent(state.search)}&status=${encodeURIComponent(state.status)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Failed to load orders', 'error');
            return;
        }
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.totalPages = data.pagination?.total_pages || 1;
        const total = data.pagination?.total || 0;
        updateTabCounts(data.counts || null);

        if (!state.rows.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No orders found.</td></tr>`;
        } else {
            body.innerHTML = state.rows.map(r => {
                const status = esc(r.status || 'Pending');
                const nextStatus = String(r.next_status || '');
                const nextAction = nextStatus ? `<button class="btn-track-next" title="Move to ${esc(nextStatus)}" onclick="OrderUI.decide('${esc(r.order_id)}','${esc(nextStatus)}')"><i class="bi bi-truck me-1"></i>${esc(nextStatus)}</button>` : '';
                const rejectAction = r.can_reject ? `<button class="order-action-btn" title="Reject" onclick="OrderUI.decide('${esc(r.order_id)}','Rejected')"><i class="bi bi-x-circle-fill text-danger"></i></button>` : '';
                const actionButtons = `<div class="order-action-group"><button class="order-action-btn" title="View Order" onclick="OrderUI.view('${esc(r.order_id)}')"><i class="bi bi-info-circle-fill"></i></button>${rejectAction}</div>`;
                const isDeleted = String(r.status || '').toLowerCase() === 'deleted';
                const trackingCol = `<div>${trackBadge(status)}${!isDeleted && nextAction ? `<div>${nextAction}</div>` : ''}</div>`;
                const productInfo = `<div class="d-flex align-items-start gap-2">
                    <img class="order-inline-image-left" src="../images/${esc(r.first_product_image || 'smartphone.png')}" alt="product">
                    <div>
                        <div>${esc(r.first_product_name || 'N/A')}</div>
                        <small class="text-muted d-block">${esc(r.first_selected_feature || '-')}</small>
                        ${Number(r.item_count || 0) > 1 ? `<small class="text-muted d-block">+${Number(r.item_count)-1} more item(s)</small>` : ''}
                    </div>
                </div>`;
                return `<tr>
                    <td><strong>${esc(r.order_id)}</strong><br><small>${esc(r.customer_name)}</small></td>
                    <td>${productInfo}</td>
                    <td>${esc(r.ordered_date)}</td>
                    <td>${rs(r.order_total)}</td>
                    <td>${trackingCol}</td>
                    <td>${actionButtons}</td>
                </tr>`;
            }).join('');
        }

        meta.textContent = `Page ${state.page} of ${state.totalPages} • Total orders: ${total}`;
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
                if (p >= 1 && p <= state.totalPages && p !== state.page) loadOrders(p);
            });
        });
    }

    async function decide(orderId, status){
        let reason = '';
        let codCollected = '';
        const row = (state.rows || []).find(function (r) { return String(r.order_id) === String(orderId); });
        const pm = String(row && row.payment_method || '').toLowerCase();
        const ps = String(row && row.payment_status || '').trim().toLowerCase();
        const needsCodCollected = status === 'Delivered' && pm === 'cod' && ps === 'pending';

        if (status === 'Rejected') {
            const ask = await Swal.fire({
                title: 'Reject reason',
                input: 'text',
                inputPlaceholder: 'Reason for rejection',
                showCancelButton: true,
                confirmButtonText: 'Reject'
            });
            if (!ask.isConfirmed) return;
            reason = String(ask.value || '').trim();
        } else {
            if (needsCodCollected) {
                const codAsk = await Swal.fire({
                    title: 'COD — payment collected?',
                    html: '<p>This order is <strong>Cash on Delivery</strong> and payment is still <strong>Pending</strong>.</p>'
                        + '<p class="mb-0">Confirm you received the agreed cash before marking delivered.</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, COD collected',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#198754'
                });
                if (!codAsk.isConfirmed) return;
                codCollected = '1';
            }
            const skipGenericDelivered = needsCodCollected;
            if (!skipGenericDelivered) {
                const labelMap = {
                    'Accepted': 'accept this order',
                    'On the way': 'mark this order as shipped (on the way)',
                    'Delivered': 'mark this order as delivered',
                    'Completed': 'mark this order as completed (customer received)'
                };
                const confirmText = labelMap[status] || 'update this order';
                const ok = await Swal.fire({ title: `Confirm ${confirmText}?`, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, update' });
                if (!ok.isConfirmed) return;
            }
            if (status === 'Delivered' || status === 'Completed') {
                const askNote = await Swal.fire({
                    title: status === 'Completed' ? 'Customer receive confirmation (optional)' : 'Delivery note (optional)',
                    input: 'text',
                    inputPlaceholder: status === 'Completed' ? 'Ex: Received by customer / OTP verified / call confirmed' : 'Ex: Courier handed over at gate',
                    showCancelButton: true,
                    confirmButtonText: 'Continue'
                });
                if (!askNote.isConfirmed) return;
                reason = String(askNote.value || '').trim();
            }
        }

        const fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('order_id', orderId);
        fd.append('new_status', status);
        fd.append('reason', reason);
        if (codCollected) fd.append('cod_collected', codCollected);
        const res = await fetch('api/order_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Unable to update', 'error');
            return;
        }
        Swal.fire('Updated', data.message || 'Status updated', 'success');
        loadOrders(state.page);
    }

    async function view(orderId){
        const res = await fetch(`api/order_api.php?action=detail&order_id=${encodeURIComponent(orderId)}`);
        const data = await res.json();
        if (!data || data.status !== 'success') {
            Swal.fire('Error', data?.message || 'Unable to load order details', 'error');
            return;
        }
        const h = data.header || {};
        document.getElementById('odOrderId').textContent = h.order_id || '-';
        document.getElementById('odDate').textContent = h.ordered_date || '-';
        document.getElementById('odStatus').textContent = h.status || 'Pending';
        (function(){
            var m = String(h.payment_method || '').trim().toUpperCase();
            var s = String(h.payment_status || '').trim();
            var pa = String(h.payment_paid_at || '').trim();
            var txt = '-';
            if (m !== '') {
                txt = (m === 'COD' ? 'Cash on Delivery' : m) + (s ? ' · ' + s : '');
                if (pa !== '' && /^paid$/i.test(s)) txt += ' (' + pa + ')';
            }
            document.getElementById('odPayment').textContent = txt;
        })();
        document.getElementById('odCustomer').textContent = h.customer_name || '-';
        document.getElementById('odEmail').textContent = h.email || '-';
        document.getElementById('odPhone').textContent = h.phone || '-';
        const fullAddress = [h.address_1, h.address_2, h.district, h.province].filter(Boolean).join(', ');
        document.getElementById('odAddress').textContent = fullAddress || '-';

        const reasonWrap = document.getElementById('odReasonWrap');
        const reasonLabel = document.getElementById('odReasonLabel');
        const stLower = String(h.status || '').toLowerCase();
        const hasReason = (h.status_reason || '').trim() !== '';
        if ((stLower === 'rejected' || stLower === 'deleted') && hasReason) {
            reasonWrap.style.display = '';
            if (reasonLabel) reasonLabel.textContent = stLower === 'deleted' ? 'Customer note:' : 'Reason:';
            document.getElementById('odReason').textContent = h.status_reason;
        } else {
            reasonWrap.style.display = 'none';
        }

        const items = Array.isArray(data.items) ? data.items : [];
        const charge = data.charge || {};
        document.getElementById('odSubtotal').textContent = rs(charge.subtotal || 0);
        document.getElementById('odCoupon').textContent = charge.coupon_code || '-';
        document.getElementById('odCouponDiscount').textContent = rs(charge.coupon_discount || 0);
        document.getElementById('odSpecialDiscount').textContent = rs(charge.special_discount || 0);
        document.getElementById('odSpecialLabel').textContent = charge.special_discount_label || '-';
        document.getElementById('odShipping').textContent = rs(charge.shipping_fee || 0);
        document.getElementById('odGrandTotal').textContent = rs(charge.grand_total || 0);
        document.getElementById('orderDetailItems').innerHTML = items.length ? items.map(it => `
            <tr>
                <td>${esc(it.product_name)}</td>
                <td><img class="modal-order-item-img" src="../images/${esc(it.image || 'smartphone.png')}" alt="product"></td>
                <td>${esc(it.selected_feature || '-')}</td>
                <td>${Number(it.quantity || 0)}</td>
                <td>${rs(it.unitprice || 0)}</td>
                <td>${rs(it.line_total || 0)}</td>
            </tr>
        `).join('') : `<tr><td colspan="6" class="text-center text-muted">No order items found.</td></tr>`;

        detailModal.show();
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
            head: [['Order ID', 'Customer', 'Email', 'Product', 'Date', 'Total', 'Status']],
            body: rows.map(r => [r.order_id, r.customer_name, r.email, r.first_product_name || 'N/A', r.ordered_date, rs(r.order_total), r.status])
        });
        doc.save(fileName);
    }

    document.getElementById('orderExportCurrent').addEventListener('click', () => {
        if (!state.rows.length) return Swal.fire('Info', 'No rows to export', 'info');
        exportRows('Orders - Current Page', state.rows, 'orders-current-page.pdf');
    });

    document.getElementById('orderExportAll').addEventListener('click', async () => {
        const res = await fetch(`api/order_api.php?action=list&page=1&per_page=1000&search=${encodeURIComponent(state.search)}&status=${encodeURIComponent(state.status)}`);
        const data = await res.json();
        if (!data || data.status !== 'success' || !Array.isArray(data.rows) || !data.rows.length) {
            Swal.fire('Info', 'No rows to export', 'info');
            return;
        }
        exportRows('Orders - All', data.rows, 'orders-all.pdf');
    });

    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            state.search = searchInput.value.trim();
            loadOrders(1);
        }, 300);
    });

    document.querySelectorAll('#orderTabs .nav-link[data-filter]').forEach((tab) => {
        tab.addEventListener('click', function(e){
            e.preventDefault();
            document.querySelectorAll('#orderTabs .nav-link').forEach((t) => t.classList.remove('active'));
            this.classList.add('active');
            state.status = this.getAttribute('data-filter') || 'all';
            loadOrders(1);
        });
    });

    window.OrderUI = { view, decide };
    loadOrders(1);
})();
</script>
</body>
</html>
