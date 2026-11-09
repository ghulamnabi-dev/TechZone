/* TechZone admin — order page. Renders every card from GET api/orders.php?id=N; every staff action
 * is POST api/orders.php {id, action, …} (CSRF header added by common.js) followed by a re-render.
 * All data reaches the DOM through escapeHtml() / statusPill(). Runs on DOMContentLoaded (common.js loads after this file). */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var root = document.getElementById('ordPage');
    if (!root) return;
    var ORDER_ID = parseInt(root.getAttribute('data-order-id'), 10);
    var D = null;          // last GET payload
    var busy = false;
    var e = escapeHtml;

    var ROLE = { owner: 'Owner', manager: 'Manager', staff: 'Staff', seller: 'Seller', customer: 'Customer' };
    var SELLER = window.TZ_ROLE === 'seller';   // seller view: only their lines, no payment / contact details
    var PAY = { cash_on_delivery: 'Cash on delivery', jazzcash: 'JazzCash', easypaisa: 'Easypaisa', bank_transfer: 'Bank transfer' };
    var TL_ICON = {
        placed: 'fa-cart-shopping', confirmed: 'fa-circle-check', payment_recorded: 'fa-money-bill-wave', fulfilled: 'fa-box',
        shipped: 'fa-truck', shipment: 'fa-truck', delivered: 'fa-house-circle-check', cancelled: 'fa-ban', note: 'fa-note-sticky',
        refund_requested: 'fa-rotate-left'
    };
    var TL_TONE = { placed: 'neutral', confirmed: 'info', payment_recorded: 'ok', fulfilled: 'info', shipped: 'info', shipment: 'info', delivered: 'ok', cancelled: 'danger', note: 'warn' };

    function payLabel(m) { return PAY[m] || (m ? String(m).replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); }) : '—'); }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function variant(it) {
        var v = [];
        if (it.size) v.push('Size: ' + it.size);
        if (it.color) v.push('Colour: ' + it.color);
        return v.join(' · ');
    }
    function btn(action, label, icon, cls) {
        return '<button type="button" class="btn ' + (cls || 'btn-secondary') + '" data-ord-action="' + action + '">' +
            (icon ? '<i class="fas ' + icon + '" aria-hidden="true"></i> ' : '') + e(label) + '</button>';
    }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }

    /* ---------------- data ---------------- */
    function load() {
        return fetch(adminUrl('api/orders.php?id=' + ORDER_ID))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'Could not load the order');
                D = d;
                render();
                root.setAttribute('aria-busy', 'false');
            })
            .catch(function (err) { showNotification(err.message || 'Could not load the order', 'error'); });
    }

    /** POST an action; toast the server's past-tense message; re-render. Resolves true on success. */
    function act(action, extra, okMsg) {
        if (busy) return Promise.resolve(false);
        busy = true;
        document.body.classList.add('ord-busy');
        var body = Object.assign({ id: ORDER_ID, action: action }, extra || {});
        return fetch(adminUrl('api/orders.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
            .then(function (res) {
                if (!res.j.success) { showNotification(res.j.message || 'That didn’t work', 'error'); return false; }
                showNotification(okMsg || res.j.message || 'Saved', 'success');
                return load().then(function () { return true; });
            })
            .catch(function () { showNotification('Network error. Please try again.', 'error'); return false; })
            .then(function (ok) { busy = false; document.body.classList.remove('ord-busy'); return ok; });
    }

    /* ---------------- render ---------------- */
    function render() {
        var o = D.order;
        var A = D.actions || {};

        // header pills
        var h1 = document.querySelector('.page-head .page-title');
        if (h1) {
            var pills = document.getElementById('ordHeadPills');
            if (!pills) {
                pills = document.createElement('span');
                pills.id = 'ordHeadPills';
                pills.className = 'ord-head-pills';
                h1.insertAdjacentElement('afterend', pills);
                h1.parentNode.classList.add('ord-head');
            }
            pills.innerHTML = SELLER ? statusPill(o.status) + statusPill(o.fulfillment_status, 'Your items: ' + o.fulfillment_status)
                : statusPill(o.status) + statusPill(o.payment_status, o.payment_status === 'pending' ? 'Payment pending' : null) + statusPill(o.fulfillment_status);
        }

        // actions bar
        var primary = A.confirm ? 'confirm' : (A.fulfil && !SELLER ? 'fulfil' : (A.ship ? 'ship' : (A.fulfil ? 'fulfil' : (A.deliver ? 'deliver' : null))));
        var bar = '';
        var defs = [
            ['confirm', 'Confirm', 'fa-check'],
            ['payment', 'Record payment', 'fa-money-bill-wave'],
            ['fulfil', SELLER ? 'Fulfil your items' : 'Fulfil', 'fa-box'],
            ['ship', SELLER ? 'Ship your items' : 'Ship', 'fa-truck'],
            ['deliver', 'Mark delivered', 'fa-house-circle-check']
        ];
        defs.forEach(function (d) {
            if (A[d[0]]) bar += btn(d[0], d[1], d[2], d[0] === primary ? 'btn-primary' : 'btn-secondary');
        });
        if (A.cancel) bar += btn('cancel', 'Cancel', 'fa-ban', 'btn-secondary ord-btn-danger');
        if (!SELLER) bar += '<div class="dropdown d-inline-block"><button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="ordPrintBtn">' +
            '<i class="fas fa-print" aria-hidden="true"></i> Print</button><ul class="dropdown-menu dropdown-menu-end">' +
            '<li><a class="dropdown-item" target="_blank" rel="noopener" href="' + e(adminUrl('manage/order-print.php?id=' + ORDER_ID + '&type=invoice&autoprint=1')) + '"><i class="fas fa-file-invoice"></i> Invoice</a></li>' +
            '<li><a class="dropdown-item" target="_blank" rel="noopener" href="' + e(adminUrl('manage/order-print.php?id=' + ORDER_ID + '&type=packing&autoprint=1')) + '"><i class="fas fa-boxes-packing"></i> Packing slip</a></li></ul></div>';
        var actions = document.getElementById('ordActions');
        if (actions) actions.innerHTML = bar;

        renderItems(A);
        if (document.getElementById('ordPayment')) renderPayment(A);
        renderShipping(A);
        renderTimeline();
        renderCustomer();
        renderNotes();
        renderSummary();
    }

    function renderItems(A) {
        var o = D.order;
        document.getElementById('ordItemsPill').innerHTML = statusPill(o.fulfillment_status);
        document.getElementById('ordItemsAction').innerHTML = A.fulfil ? btn('fulfil', 'Fulfil items', null, 'btn-secondary btn-sm') : '';
        var html = '<ul class="ord-lines">';
        (D.items || []).forEach(function (it) {
            var q = parseInt(it.quantity, 10) || 0, f = parseInt(it.fulfilled_qty, 10) || 0;
            var badge = f >= q && q > 0 ? statusPill('fulfilled', 'Fulfilled') : (f > 0 ? statusPill('partial', f + ' of ' + q + ' fulfilled') : '');
            var img = it.image ? '<img class="ord-thumb" src="' + e(it.image) + '" alt="" loading="lazy" width="44" height="44">' : '<span class="ord-thumb ord-thumb-ph"><i class="fas fa-image" aria-hidden="true"></i></span>';
            var v = variant(it);
            html += '<li class="ord-line">' + img +
                '<div class="ord-line-main"><div class="ord-line-name">' +
                (it.product_id ? '<a href="' + e(adminUrl('manage/product.php?id=' + parseInt(it.product_id, 10))) + '">' + e(it.product_name) + '</a>' : e(it.product_name)) + '</div>' +
                '<div class="ord-line-sub">' + [v ? e(v) : '', it.sku ? '<span class="ord-sku">' + e(it.sku) + '</span>' : '',
                    !SELLER && it.seller_id && parseInt(it.seller_id, 10) !== 1 ? '<span class="ord-seller"><i class="fas fa-store" aria-hidden="true"></i> Sold by ' + e(it.seller_name || 'a marketplace seller') + '</span>' : ''].filter(Boolean).join(' · ') + '</div>' +
                (badge ? '<div class="ord-line-badge">' + badge + '</div>' : '') + '</div>' +
                '<div class="ord-line-qty tabular">' + formatPKR(it.price) + ' <span class="ord-x">×</span> ' + formatNumber(q) + '</div>' +
                '<div class="ord-line-total tabular">' + formatPKR(it.subtotal) + '</div></li>';
        });
        if (!(D.items || []).length) html += '<li class="ord-empty">No line items.</li>';
        html += '</ul>';
        if (SELLER) {
            html += '<div class="ord-seller-total"><span>Your subtotal <span class="ord-hint">' + e(plural(o.units || 0, 'item')) + '</span></span><strong class="tabular">' + formatPKR(o.subtotal) + '</strong></div>' +
                '<p class="ord-hint ord-seller-note"><i class="fas fa-lock" aria-hidden="true"></i> Only your items on this order are shown. TechZone handles payment and the rest of the order.</p>';
        }
        document.getElementById('ordItems').innerHTML = html;
    }

    function renderPayment(A) {
        var o = D.order;
        document.getElementById('ordPayPill').innerHTML = statusPill(o.payment_status);
        document.getElementById('ordPayAction').innerHTML = A.payment ? btn('payment', 'Record payment', null, 'btn-secondary btn-sm') : '';
        var ship = parseFloat(o.shipping_fee) || 0, disc = parseFloat(o.discount_amount) || 0;
        var zone = D.zone ? D.zone.name : null;
        var h = '<dl class="ord-sum">';
        var units = (D.items || []).reduce(function (s, it) { return s + (parseInt(it.quantity, 10) || 0); }, 0);
        h += '<div><dt>Subtotal <span class="ord-hint">' + plural(units, 'item') + '</span></dt><dd class="tabular">' + formatPKR(o.subtotal) + '</dd></div>';
        h += '<div><dt>Shipping' + (zone ? ' <span class="ord-hint">' + e(zone) + '</span>' : '') + '</dt><dd class="tabular">' + (ship > 0 ? formatPKR(ship) : 'Free') + '</dd></div>';
        if (disc > 0 || o.promo_code) h += '<div><dt>Discount' + (o.promo_code ? ' <span class="code-chip">' + e(o.promo_code) + '</span>' : '') + '</dt><dd class="tabular">−' + formatPKR(disc) + '</dd></div>';
        h += '<div class="ord-sum-total"><dt>Total</dt><dd class="tabular">' + formatPKR(o.total) + '</dd></div>';
        h += '</dl><dl class="ord-kv ord-pay-meta">';
        h += '<dt>Method</dt><dd>' + e(payLabel(o.payment_method)) + '</dd>';
        h += '<dt>Status</dt><dd>' + statusPill(o.payment_status) + '</dd>';
        if (D.payment) {
            h += '<dt>Received</dt><dd>' + formatPKR(D.payment.amount) + ' via ' + e(payLabel(D.payment.method)) +
                (D.payment.reference ? ' · ref <span class="ord-mono">' + e(D.payment.reference) + '</span>' : '') +
                '<div class="ord-hint">' + e(formatDateTime(D.payment.at)) + (D.payment.by ? ' by ' + e(D.payment.by) : '') + '</div></dd>';
        } else if (o.payment_status === 'pending' && o.status !== 'Cancelled') {
            h += '<dt>Due</dt><dd>' + formatPKR(o.total) + (o.payment_method === 'cash_on_delivery' ? ' <span class="ord-hint">collected by the courier on delivery</span>' : '') + '</dd>';
        }
        h += '</dl>';
        document.getElementById('ordPayment').innerHTML = h;
    }

    function shipList() {
        var list = D.shipments || [];
        if (!list.length) return '';
        return '<ul class="ord-ship-list">' + list.map(function (s) {
            var by = SELLER ? (s.own ? 'You' : 'TechZone') : (s.seller_id ? (s.seller_name || 'Seller') : 'TechZone');
            return '<li><div class="ord-ship-head"><strong>' + (s.courier ? e(s.courier) : '<span class="ord-hint">Courier not recorded</span>') + '</strong>' +
                (s.tracking_number ? ' <span class="ord-mono">' + e(s.tracking_number) + '</span>' : '') +
                ' ' + (s.delivered_at ? statusPill('delivered') : statusPill('shipped')) + '</div>' +
                '<div class="ord-hint">Shipped by ' + e(by) + ' · ' + e(formatDateTime(s.shipped_at)) + (s.delivered_at ? ' · delivered ' + e(formatDateTime(s.delivered_at)) : '') + '</div></li>';
        }).join('') + '</ul>';
    }

    function renderShipping(A) {
        var o = D.order, s = D.shipment, c = D.customer || {};
        if (SELLER) {
            var mine = (D.shipments || []).filter(function (x) { return x.own; })[0];
            var anyHouse = (D.shipments || []).some(function (x) { return !x.own; });
            document.getElementById('ordShipPill').innerHTML = mine || anyHouse ? (s && s.delivered_at ? statusPill('delivered') : statusPill('shipped'))
                : (o.status === 'Cancelled' ? statusPill('cancelled') : statusPill('pending', 'Not shipped'));
            document.getElementById('ordShipAction').innerHTML = A.ship ? btn('ship', 'Ship your items', null, 'btn-secondary btn-sm') : '';
            document.getElementById('ordShipping').innerHTML = '<dl class="ord-kv"><dt>Ship to</dt><dd>' + e(o.customer_name) + '<br>' + e(o.city || '') +
                '<div class="ord-hint">The customer’s address and phone go on the courier slip TechZone sends you.</div></dd></dl>' +
                (shipList() || '<p class="ord-hint mb-0 mt-3">Not shipped yet. Ship your items once they are packed; the customer sees the courier and tracking number.</p>');
            return;
        }
        var pill = s ? (s.delivered_at ? statusPill('delivered') : statusPill('shipped')) : (o.status === 'Cancelled' ? statusPill('cancelled') : statusPill('pending', 'Not shipped'));
        document.getElementById('ordShipPill').innerHTML = pill;
        var act = '';
        if (A.ship) act = btn('ship', 'Ship', null, 'btn-secondary btn-sm');
        else if (A.tracking) act = btn('tracking', 'Edit tracking', null, 'btn-secondary btn-sm');
        document.getElementById('ordShipAction').innerHTML = act;
        var h = '<dl class="ord-kv">';
        h += '<dt>Zone</dt><dd>' + (D.zone ? e(D.zone.name) : '<span class="ord-hint">No zone</span>') + ' · ' + ((parseFloat(o.shipping_fee) || 0) > 0 ? formatPKR(o.shipping_fee) : 'Free shipping') + '</dd>';
        h += '<dt>Ship to</dt><dd>' + e(o.customer_name) + '<br>' + (c.address ? e(c.address) + '<br>' : '') + e(o.city) + '<br><span class="tabular">' + e(o.customer_phone) + '</span></dd>';
        if (s) {
            h += '<dt>Courier</dt><dd>' + (s.courier ? e(s.courier) : '<span class="ord-hint">Not recorded</span>') + '</dd>';
            h += '<dt>Tracking</dt><dd>' + (s.tracking_number ? '<span class="ord-mono">' + e(s.tracking_number) + '</span>' : '<span class="ord-hint">—</span>') + '</dd>';
            h += '<dt>Shipped</dt><dd>' + e(formatDateTime(s.shipped_at)) + '</dd>';
            if (s.delivered_at) h += '<dt>Delivered</dt><dd>' + e(formatDateTime(s.delivered_at)) + '</dd>';
        }
        h += '</dl>';
        if ((D.shipments || []).length > 1 || (D.shipments || []).some(function (x) { return x.seller_id; })) h += '<h3 class="ord-sub-title">Shipments</h3>' + shipList();
        document.getElementById('ordShipping').innerHTML = h;
    }

    function tlDetails(t) {
        var p = t.payload || {}, bits = [];
        if (t.type === 'fulfilled' && Array.isArray(p.items)) {
            bits.push(p.items.map(function (i) { return e(i.quantity + ' × ' + i.name); }).join('<br>'));
        }
        if ((t.type === 'shipped' || t.type === 'shipment') && (p.courier || p.tracking)) {
            bits.push('Courier <strong>' + e(p.courier || '—') + '</strong>' + (p.tracking ? ' · Tracking <span class="ord-mono">' + e(p.tracking) + '</span>' : ''));
        }
        if (t.type === 'placed' && p.items) {
            bits.push(e(plural(parseInt(p.items, 10) || 0, 'item') + (p.payment_method ? ' · ' + payLabel(p.payment_method) : '') + (p.shipping_zone ? ' · ' + p.shipping_zone : '') + (p.promo_code ? ' · code ' + p.promo_code : '')));
        }
        if (t.type === 'cancelled' && p.restocked != null) bits.push(e(plural(parseInt(p.restocked, 10) || 0, 'item') + ' restocked'));
        return bits.length ? '<div class="ord-tl-detail">' + bits.join('<br>') + '</div>' : '';
    }

    function renderTimeline() {
        var list = (D.timeline || []).slice().reverse();
        var h = '';
        list.forEach(function (t) {
            var isNote = t.kind === 'note' || (t.type === 'note' && !(t.payload || {}).tracking_update);
            var type = isNote ? 'note' : t.type;
            var who = t.actor_type === 'system' ? 'TechZone' : (t.actor_name || (t.actor_type === 'customer' ? 'Customer' : 'Staff'));
            var role = t.actor_role ? ROLE[t.actor_role] || t.actor_role : '';
            var label = isNote ? 'Note' : t.label;
            h += '<li class="ord-tl ord-tl--' + e(type) + '">' +
                '<span class="ord-tl-dot tone-' + (TL_TONE[type] || 'neutral') + '"><i class="fas ' + (TL_ICON[type] || 'fa-circle') + '" aria-hidden="true"></i></span>' +
                '<div class="ord-tl-body"><div class="ord-tl-head"><strong class="ord-tl-label">' + e(label) + '</strong>' +
                '<time class="ord-tl-time" datetime="' + e(t.at) + '">' + e(formatDateTime(t.at)) + '</time></div>' +
                '<div class="ord-tl-actor">' + e(who) + (role ? ' <span class="ord-role">' + e(role) + '</span>' : '') + '</div>' +
                (isNote ? '<div class="ord-tl-note">' + e(t.label) + '</div>' : tlDetails(t)) +
                '</div></li>';
        });
        document.getElementById('ordTimeline').innerHTML = h || '<li class="ord-empty">Nothing has happened yet.</li>';
    }

    function renderCustomer() {
        var o = D.order, c = D.customer;
        var h = '';
        if (SELLER) {
            document.getElementById('ordCustomer').innerHTML = '<div class="ord-cust-head"><span class="avatar" aria-hidden="true">' + e(String(o.customer_name || '?').charAt(0).toUpperCase()) +
                '</span><div><strong>' + e(o.customer_name) + '</strong><div class="ord-hint">' + e(o.city || '') + '</div></div></div>' +
                '<p class="ord-hint mb-0 mt-2">TechZone keeps the customer’s contact details. Message TechZone on the order if something is unclear.</p>';
            return;
        }
        if (c) {
            h += '<div class="ord-cust-head"><span class="avatar" aria-hidden="true">' + e(String(c.name || '?').charAt(0).toUpperCase()) + '</span><div>' +
                '<a class="ord-cust-name" href="' + e(adminUrl('manage/customer.php?id=' + parseInt(c.id, 10))) + '">' + e(c.name) + '</a>' +
                '<div class="ord-hint">' + plural(c.orders_count, 'order') + ' · ' + formatPKR(c.lifetime_value) + ' lifetime</div></div></div>';
        } else {
            h += '<div class="ord-cust-head"><span class="avatar" aria-hidden="true">' + e(String(o.customer_name || '?').charAt(0).toUpperCase()) + '</span><div><strong>' + e(o.customer_name) + '</strong><div class="ord-hint">Guest checkout</div></div></div>';
        }
        h += '<dl class="ord-kv ord-kv-tight">';
        h += '<dt>Email</dt><dd><a href="mailto:' + e(o.customer_email) + '">' + e(o.customer_email) + '</a></dd>';
        h += '<dt>Phone</dt><dd><a class="tabular" href="tel:' + e(String(o.customer_phone || '').replace(/[^\d+]/g, '')) + '">' + e(o.customer_phone) + '</a></dd>';
        h += '<dt>City</dt><dd>' + e(o.city) + '</dd>';
        if (c) {
            h += '<dt>Orders</dt><dd class="tabular">' + formatNumber(c.orders_count) + '</dd>';
            h += '<dt>Lifetime</dt><dd class="tabular">' + formatPKR(c.lifetime_value) + '</dd>';
        }
        h += '</dl>';
        if (c) h += '<a class="ord-link" href="' + e(adminUrl('manage/customer.php?id=' + parseInt(c.id, 10))) + '">View customer <i class="fas fa-arrow-right" aria-hidden="true"></i></a>';
        document.getElementById('ordCustomer').innerHTML = h;
    }

    function renderNotes() {
        var h = '';
        (D.notes || []).forEach(function (n) {
            h += '<li><div class="ord-note-body">' + e(n.body) + '</div><div class="ord-hint">' + e(n.admin_name || 'Staff') +
                (n.admin_role ? ' · ' + e(ROLE[n.admin_role] || n.admin_role) : '') + ' · ' + e(formatDateTime(n.created_at)) + '</div></li>';
        });
        document.getElementById('ordNotes').innerHTML = h || '<li class="ord-empty">No notes yet.</li>';
    }

    function renderSummary() {
        var o = D.order;
        var h = '<dl class="ord-kv ord-kv-tight">';
        h += '<dt>Channel</dt><dd>' + (SELLER ? 'TechZone marketplace' : 'Storefront') + '</dd>';
        if (SELLER) h += '<dt>Subtotal</dt><dd class="tabular">' + formatPKR(o.subtotal) + '</dd>';
        h += '<dt>Placed</dt><dd>' + e(formatDateTime(o.order_date)) + '</dd>';
        h += '<dt>Updated</dt><dd>' + e(formatDateTime(o.updated_at)) + '</dd>';
        if (o.promo_code) h += '<dt>Discount</dt><dd><span class="code-chip">' + e(o.promo_code) + '</span></dd>';
        if (o.cancelled_at) h += '<dt>Cancelled</dt><dd>' + e(formatDateTime(o.cancelled_at)) + (o.cancel_reason ? '<div class="ord-hint">' + e(o.cancel_reason) + '</div>' : '') + '</dd>';
        h += '</dl>';
        document.getElementById('ordSummary').innerHTML = h;
    }

    /* ---------------- modals ---------------- */
    function openPayment() {
        var o = D.order;
        var sel = document.getElementById('ordPayMethod');
        var methods = D.payment_methods || PAY;
        sel.innerHTML = Object.keys(methods).map(function (k) { return '<option value="' + e(k) + '">' + e(methods[k]) + '</option>'; }).join('');
        sel.value = methods[o.payment_method] ? o.payment_method : Object.keys(methods)[0];
        var amt = document.getElementById('ordPayAmount');
        amt.value = Math.round(parseFloat(o.total) || 0);
        amt.classList.remove('is-invalid');
        document.getElementById('ordPayRef').value = '';
        modal('ordPayModal').show();
    }
    if (document.getElementById('ordPayForm')) document.getElementById('ordPayForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var amt = document.getElementById('ordPayAmount');
        var v = parseFloat(amt.value);
        if (!(v > 0)) { amt.classList.add('is-invalid'); amt.focus(); return; }
        act('payment', { amount: v, method: document.getElementById('ordPayMethod').value, reference: document.getElementById('ordPayRef').value.trim() }, 'Payment recorded')
            .then(function (ok) { if (ok) modal('ordPayModal').hide(); });
    });

    function openFulfil() {
        var h = '<table class="table mini-table mb-0 ord-fulfil-table"><thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Fulfilled</th><th class="num">Fulfil now</th></tr></thead><tbody>';
        (D.items || []).forEach(function (it) {
            var q = parseInt(it.quantity, 10) || 0, f = parseInt(it.fulfilled_qty, 10) || 0, left = q - f;
            var v = variant(it);
            h += '<tr><td><div class="cell-main">' + e(it.product_name) + '</div>' + (v ? '<div class="cell-sub">' + e(v) + '</div>' : '') + '</td>' +
                '<td class="num">' + q + '</td><td class="num">' + f + '</td><td class="num">' +
                (left > 0 ? '<input type="number" class="form-control form-control-sm ord-qty" min="0" max="' + left + '" value="' + left + '" data-item="' + parseInt(it.id, 10) + '" aria-label="Quantity to fulfil for ' + e(it.product_name) + '">'
                    : statusPill('fulfilled', 'Done')) + '</td></tr>';
        });
        h += '</tbody></table>';
        document.getElementById('ordFulfilList').innerHTML = h;
        updateFulfilTotal();
        modal('ordFulfilModal').show();
    }
    function updateFulfilTotal() {
        var n = 0;
        document.querySelectorAll('#ordFulfilList .ord-qty').forEach(function (i) { n += Math.max(0, parseInt(i.value, 10) || 0); });
        document.getElementById('ordFulfilTotal').textContent = plural(n, 'item') + ' to fulfil';
    }
    document.getElementById('ordFulfilList').addEventListener('input', updateFulfilTotal);
    document.getElementById('ordFulfilForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var items = {}, n = 0, bad = false;
        document.querySelectorAll('#ordFulfilList .ord-qty').forEach(function (i) {
            var v = parseInt(i.value, 10) || 0, max = parseInt(i.getAttribute('max'), 10);
            i.classList.toggle('is-invalid', v < 0 || v > max);
            if (v < 0 || v > max) bad = true;
            if (v > 0) { items[i.getAttribute('data-item')] = v; n += v; }
        });
        if (bad) return;
        if (!n) { showNotification('Choose at least one item to fulfil', 'warning'); return; }
        act('fulfil', { items: items }).then(function (ok) { if (ok) modal('ordFulfilModal').hide(); });
    });

    var shipMode = 'ship';
    function openShip(mode) {
        shipMode = mode;
        var s = D.shipment || {};
        var couriers = (D.couriers || ['TCS', 'Leopards', 'M&P']).concat(['Other']);
        var sel = document.getElementById('ordShipCourier');
        sel.innerHTML = couriers.map(function (c) { return '<option value="' + e(c) + '">' + e(c) + '</option>'; }).join('');
        var cur = mode === 'tracking' ? (s.courier || 'TCS') : 'TCS';
        var known = couriers.indexOf(cur) !== -1 && cur !== 'Other';
        sel.value = known ? cur : 'Other';
        document.getElementById('ordShipOther').value = known ? '' : cur;
        document.getElementById('ordShipOtherWrap').hidden = known;
        document.getElementById('ordShipTracking').value = mode === 'tracking' ? (s.tracking_number || '') : '';
        var note = document.getElementById('ordShipNote');
        if (note) note.value = '';
        var noteWrap = document.getElementById('ordShipNoteWrap');
        if (noteWrap) noteWrap.hidden = mode === 'tracking';
        ['ordShipTracking', 'ordShipOther'].forEach(function (id) { document.getElementById(id).classList.remove('is-invalid'); });
        var left = (D.items || []).reduce(function (sum, it) { return sum + Math.max(0, (parseInt(it.quantity, 10) || 0) - (parseInt(it.fulfilled_qty, 10) || 0)); }, 0);
        document.getElementById('ordShipModalTitle').textContent = mode === 'tracking' ? 'Edit tracking' : (SELLER ? 'Ship your items on #' + ORDER_ID : 'Ship order #' + ORDER_ID);
        document.getElementById('ordShipSubmit').textContent = mode === 'tracking' ? 'Save tracking' : (SELLER ? 'Ship your items' : 'Ship order');
        document.getElementById('ordShipText').textContent = mode === 'tracking'
            ? 'The customer sees the new tracking number on their order page.'
            : (left > 0 ? 'Shipping marks ' + (SELLER ? 'your remaining ' : 'the remaining ') + plural(left, 'item') + ' as fulfilled. ' : '') +
              (SELLER ? 'Only your items go in this shipment; TechZone ships the rest of the order. ' : '') + 'The customer sees the courier and tracking number on their order page.';
        modal('ordShipModal').show();
    }
    document.getElementById('ordShipCourier').addEventListener('change', function () {
        var other = this.value === 'Other';
        document.getElementById('ordShipOtherWrap').hidden = !other;
        if (other) document.getElementById('ordShipOther').focus();
    });
    document.getElementById('ordShipForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var sel = document.getElementById('ordShipCourier').value;
        var otherEl = document.getElementById('ordShipOther');
        var trEl = document.getElementById('ordShipTracking');
        var courier = sel === 'Other' ? otherEl.value.trim() : sel;
        var ok = true;
        otherEl.classList.toggle('is-invalid', sel === 'Other' && !courier);
        if (sel === 'Other' && !courier) ok = false;
        trEl.classList.toggle('is-invalid', !trEl.value.trim());
        if (!trEl.value.trim()) ok = false;
        if (!ok) return;
        var payload = { courier: courier, tracking: trEl.value.trim() };
        var note = document.getElementById('ordShipNote');
        if (shipMode === 'ship' && note && note.value.trim()) payload.note = note.value.trim();
        act(shipMode === 'tracking' ? 'tracking' : 'ship', payload, shipMode === 'tracking' ? 'Tracking updated' : (SELLER ? 'Your items shipped' : 'Order shipped'))
            .then(function (done) { if (done) modal('ordShipModal').hide(); });
    });

    function openCancel() {
        var n = D.restock_units || 0;
        document.getElementById('ordCancelText').innerHTML = '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Restocks ' + e(plural(n, 'item')) + '</strong>' +
            '<div>Order #' + ORDER_ID + ' is cancelled for good: stock goes back to inventory and the customer sees “Cancelled” on their order page.' +
            (D.order.payment_status === 'paid' ? ' The payment is not refunded automatically.' : '') + '</div></div>';
        document.getElementById('ordCancelReason').value = 'Customer request';
        document.getElementById('ordCancelOther').value = '';
        document.getElementById('ordCancelOtherWrap').hidden = true;
        document.getElementById('ordCancelOther').classList.remove('is-invalid');
        modal('ordCancelModal').show();
    }
    if (document.getElementById('ordCancelForm')) document.getElementById('ordCancelReason').addEventListener('change', function () {
        var other = this.value === 'Other';
        document.getElementById('ordCancelOtherWrap').hidden = !other;
        if (other) document.getElementById('ordCancelOther').focus();
    });
    if (document.getElementById('ordCancelForm')) document.getElementById('ordCancelForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var reason = document.getElementById('ordCancelReason').value;
        var other = document.getElementById('ordCancelOther');
        if (reason === 'Other') {
            if (!other.value.trim()) { other.classList.add('is-invalid'); other.focus(); return; }
            reason = 'Other: ' + other.value.trim();
        }
        act('cancel', { reason: reason }, 'Order cancelled').then(function (ok) { if (ok) modal('ordCancelModal').hide(); });
    });

    var noteForm = document.getElementById('ordNoteForm');
    if (noteForm) {
        noteForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var ta = document.getElementById('ordNoteBody');
            var body = ta.value.trim();
            if (!body) { ta.focus(); return; }
            act('note', { body: body }, 'Note added').then(function (ok) { if (ok) ta.value = ''; });
        });
    }

    /* ---------------- action dispatch ---------------- */
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-ord-action]');
        if (!b || !D) return;
        var a = b.getAttribute('data-ord-action');
        if (a === 'confirm') act('confirm', null, 'Order confirmed');
        else if (a === 'deliver') act('deliver', null, 'Order marked delivered');
        else if (a === 'payment') openPayment();
        else if (a === 'fulfil') openFulfil();
        else if (a === 'ship') openShip('ship');
        else if (a === 'tracking') openShip('tracking');
        else if (a === 'cancel') openCancel();
    });

    load();
});
