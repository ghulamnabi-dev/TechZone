/* TechZone admin — customer page (manage/customer.php). */
(function () {
    'use strict';
    var root = document.getElementById('customerPage');
    if (!root) return;
    var id = parseInt(root.getAttribute('data-id'), 10);
    var canWrite = tzCan('customers.write');
    var c = null;
    var $ = function (s) { return document.getElementById(s); };
    var TONE = { placed: 'info', confirmed: 'info', payment_recorded: 'ok', fulfilled: 'info', shipped: 'info', delivered: 'ok', cancelled: 'danger', refund_requested: 'warn' };

    function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join(''); }
    function orderUrl(oid) { return adminUrl('manage/order.php?id=' + encodeURIComponent(oid)); }
    function ago(v) {
        var d = parseDate(v); if (!d) return '';
        var days = Math.floor((Date.now() - d.getTime()) / 86400000);
        return days <= 0 ? 'today' : (days === 1 ? 'yesterday' : (days < 60 ? days + ' days ago' : Math.round(days / 30) + ' months ago'));
    }

    function renderHead() {
        $('cusAvatar').textContent = initials(c.name);
        var bits = [];
        bits.push('<span><i class="fas fa-envelope" aria-hidden="true"></i><a href="mailto:' + escapeHtml(c.email) + '">' + escapeHtml(c.email) + '</a></span>');
        if (c.phone) bits.push('<span><i class="fas fa-phone" aria-hidden="true"></i><a class="tabular" href="tel:' + escapeHtml(c.phone.replace(/[^0-9+]/g, '')) + '">' + escapeHtml(c.phone) + '</a></span>');
        if (c.city) bits.push('<span><i class="fas fa-location-dot" aria-hidden="true"></i>' + escapeHtml(c.city) + '</span>');
        $('cusContact').innerHTML = bits.join('');
    }

    function renderTags() {
        var h = (c.tags || []).map(function (t) {
            return '<span class="cus-tag' + (canWrite ? '' : ' ro') + '">' + escapeHtml(t.name) +
                (canWrite ? '<button type="button" data-tag-remove="' + parseInt(t.id, 10) + '" aria-label="Remove tag ' + escapeHtml(t.name) + '"><i class="fas fa-xmark"></i></button>' : '') + '</span>';
        }).join('');
        if (!c.tags.length && !canWrite) h = '<span class="prd-hint">No tags</span>';
        if (canWrite) {
            h += '<form class="cus-tag-add" id="cusTagForm" novalidate><label class="visually-hidden" for="cusTagInput">Add tag</label>' +
                '<input type="text" class="form-control" id="cusTagInput" name="tag" maxlength="50" placeholder="Add tag" list="cusTagList" autocomplete="off">' +
                '<button type="submit" class="btn btn-secondary">Add</button></form><datalist id="cusTagList"></datalist>';
        }
        $('cusTags').innerHTML = h;
        if (canWrite) loadTagList();
    }
    function loadTagList() {
        tzJson('GET', adminUrl('api/customers.php?tags=1')).then(function (res) {
            var dl = $('cusTagList'); if (!dl || !res.ok) return;
            var have = (c.tags || []).map(function (t) { return t.name; });
            dl.innerHTML = (res.data.tags || []).filter(function (t) { return have.indexOf(t.name) === -1; })
                .map(function (t) { return '<option value="' + escapeHtml(t.name) + '">'; }).join('');
        });
    }

    function renderOrders() {
        var o = c.orders || [];
        $('cusOrderCount').textContent = o.length;
        if (!o.length) { $('cusOrders').innerHTML = emptyRow(6, 'No orders yet. Orders placed with this email appear here.'); return; }
        $('cusOrders').innerHTML = o.map(function (x) {
            return '<tr data-href="' + escapeHtml(orderUrl(x.id)) + '"><td><a class="cell-main id-cell" href="' + escapeHtml(orderUrl(x.id)) + '">#' + escapeHtml(x.id) + '</a><div class="cell-sub prd-show-sm">' + escapeHtml(formatDate(x.order_date)) + '</div></td>' +
                '<td class="nowrap ink-2 prd-hide-sm">' + escapeHtml(formatDate(x.order_date)) + '</td>' +
                '<td>' + statusPill(x.status) + '</td>' +
                '<td class="prd-hide-sm"><span class="pills">' + statusPill(x.payment_status === 'pending' ? 'unpaid' : x.payment_status, x.payment_status === 'pending' ? 'Unpaid' : null) + statusPill(x.fulfillment_status) + '</span></td>' +
                '<td class="num prd-hide-sm">' + formatNumber(x.items) + '</td>' +
                '<td class="num">' + escapeHtml(formatPKR(x.total)) + '</td></tr>';
        }).join('');
    }

    function renderTimeline() {
        var t = c.timeline || [];
        if (!t.length) { $('cusTimeline').innerHTML = '<li><span class="dot-wrap"><span class="tl-dot"></span></span><span class="tl-text ink-2">Joined ' + escapeHtml(formatDate(c.created_at)) + '. No orders yet.</span><span></span></li>'; return; }
        $('cusTimeline').innerHTML = t.map(function (e) {
            return '<li><span class="dot-wrap"><span class="tl-dot ' + (TONE[e.type] || '') + '"></span></span>' +
                '<span class="tl-text">' + escapeHtml(e.label) + ' <a href="' + escapeHtml(orderUrl(e.order_id)) + '">#' + escapeHtml(e.order_id) + '</a></span>' +
                '<time datetime="' + escapeHtml(e.at) + '">' + escapeHtml(formatDateTime(e.at)) + '</time></li>';
        }).join('') + '<li><span class="dot-wrap"><span class="tl-dot"></span></span><span class="tl-text ink-2">Created an account</span><time>' + escapeHtml(formatDate(c.created_at)) + '</time></li>';
    }

    function renderOverview() {
        $('ovOrders').textContent = formatNumber(c.total_orders);
        $('ovCancelled').textContent = c.cancelled_orders ? '+ ' + c.cancelled_orders + ' cancelled' : '';
        $('ovLtv').textContent = formatPKR(c.total_spent);
        $('ovAvg').textContent = c.total_orders ? formatPKR(c.avg_order_value) : '—';
        $('ovSince').textContent = formatDate(c.created_at);
        $('ovFirst').textContent = c.first_order_at ? formatDate(c.first_order_at) : '—';
        $('ovLast').textContent = c.last_order_at ? formatDate(c.last_order_at) : '—';
        $('ovLastAgo').textContent = c.last_order_at ? ago(c.last_order_at) : '';
        $('cusAddress').textContent = [c.address, c.city].filter(Boolean).join('\n') || 'No address on file. It is filled in at their first checkout.';
    }

    function renderNotes() {
        var n = c.notes || [];
        $('cusNotes').innerHTML = n.length ? n.map(function (x) {
            return '<li><p>' + escapeHtml(x.body) + '</p><small>' + escapeHtml(x.admin_name || 'Former staff') + ' · ' + escapeHtml(formatDateTime(x.created_at)) + '</small></li>';
        }).join('') : '<li style="background:none;border:0;padding:0" class="prd-hint">No notes yet.</li>';
    }

    function load() {
        return tzJson('GET', adminUrl('api/customers.php?id=' + id)).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load the customer', 'error'); return; }
            c = res.data.customer;
            renderHead(); renderTags(); renderOrders(); renderTimeline(); renderOverview(); renderNotes();
        });
    }

    function post(body) { body.customer_id = id; return tzJson('POST', adminUrl('api/customers.php'), body); }

    document.addEventListener('DOMContentLoaded', function () {
        $('cusTags').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = e.target, input = $('cusTagInput');
            var tag = input.value.trim();
            if (!tag) { input.focus(); return; }
            post({ action: 'tag_add', tag: tag }).then(function (res) {
                if (!res.ok) { tzShowErrors(form, res.data.errors || { tag: res.data.message }); showNotification((res.data.errors && res.data.errors.tag) || res.data.message, 'error'); return; }
                c.tags = res.data.tags; renderTags();
                showNotification(res.data.message === 'Already tagged' ? 'Already tagged “' + tag.toLowerCase() + '”' : 'Tag added', 'success');
                $('cusTagInput').focus();
            });
        });
        $('cusTags').addEventListener('click', function (e) {
            var b = e.target.closest('[data-tag-remove]');
            if (!b) return;
            post({ action: 'tag_remove', tag_id: b.getAttribute('data-tag-remove') }).then(function (res) {
                if (!res.ok) { showNotification(res.data.message || 'Could not remove the tag', 'error'); return; }
                c.tags = res.data.tags; renderTags(); showNotification('Tag removed', 'success');
            });
        });
        var nf = $('cusNoteForm');
        if (nf) nf.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = $('cusNoteBody').value.trim();
            if (!body) { tzShowErrors(nf, { body: 'Write a note first.' }); return; }
            $('cusNoteSave').disabled = true;
            post({ action: 'note_add', body: body }).then(function (res) {
                $('cusNoteSave').disabled = false;
                if (!res.ok) { tzShowErrors(nf, res.data.errors || { body: res.data.message }); return; }
                $('cusNoteBody').value = '';
                c.notes = res.data.notes; renderNotes(); showNotification('Note added', 'success');
            });
        });
        $('cusOrders').addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            var tr = e.target.closest('tr[data-href]');
            if (tr) location.href = tr.getAttribute('data-href');
        });
        var del = $('cusDelete');
        if (del) del.addEventListener('click', function () {
            var n = c ? (c.orders || []).length : 0;
            showConfirm('Delete ' + (c ? c.name : 'this customer') + '? Their account, tags and notes are removed. ' + (n ? 'Their ' + n + ' order' + (n === 1 ? '' : 's') + ' stay, without a link to a customer.' : ''), function () {
                tzJson('DELETE', adminUrl('api/customers.php?id=' + id)).then(function (res) {
                    if (!res.ok) { showNotification(res.data.message || 'Could not delete the customer', 'error'); return; }
                    location.href = adminUrl('manage/customers.php?deleted=1');
                });
            }, 'Delete customer');
        });
        load();
    });
})();
