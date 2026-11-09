/* Activity log (Settings → Activity, api/activity.php) and "Your recent activity" on Profile (#myActivity).
 * Rows expand to a before/after diff; changed keys are highlighted. Filters live in the URL so a view can be shared. */
(function () {
    'use strict';
    var ENTITY = {
        order: { icon: 'fa-receipt', label: 'Order', href: function (id) { return 'manage/order.php?id=' + encodeURIComponent(id); }, name: function (id) { return 'Order #' + id; } },
        product: { icon: 'fa-tag', label: 'Product', href: function (id) { return 'manage/product.php?id=' + encodeURIComponent(id); }, name: function (id) { return 'Product #' + id; } },
        customer: { icon: 'fa-user', label: 'Customer', href: function (id) { return 'manage/customer.php?id=' + encodeURIComponent(id); }, name: function (id) { return 'Customer #' + id; } },
        promotion: { icon: 'fa-percent', label: 'Discount', href: function () { return 'manage/promotions.php'; }, name: function (id) { return 'Discount #' + id; } },
        review: { icon: 'fa-star', label: 'Review', href: function () { return 'manage/reviews.php'; }, name: function (id) { return 'Review #' + id; } },
        contact_message: { icon: 'fa-envelope', label: 'Message', href: function () { return 'manage/contact_messages.php'; }, name: function (id) { return 'Message #' + id; } },
        setting: { icon: 'fa-gear', label: 'Setting', cap: 'settings.write',
                   href: function (id) { return 'manage/settings.php?tab=' + (/^(cod_|jazzcash_)/.test(id) ? 'payments' : 'store'); },
                   name: function (id) { return String(id).replace(/_/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); }); } },
        shipping_zone: { icon: 'fa-truck', label: 'Shipping zone', cap: 'shipping.write', href: function () { return 'manage/settings.php?tab=shipping'; }, name: function (id) { return 'Zone #' + id; } },
        admin: { icon: 'fa-user-shield', label: 'Staff', cap: 'staff.manage', href: function () { return 'manage/settings.php?tab=staff'; }, name: function (id) { return 'Staff #' + id; } }
    };
    var ROLE = { owner: 'Owner', manager: 'Manager', staff: 'Staff' };

    function entityOf(type) { return ENTITY[type] || { icon: 'fa-circle', label: type, name: function (id) { return type + ' #' + id; } }; }
    function entityHtml(r) {
        var e = entityOf(r.entity_type);
        var text = e.name(r.entity_id);
        var can = e.href && (!e.cap || tzCan(e.cap));
        return can ? '<a class="act-entity" href="' + escapeHtml(adminUrl(e.href(r.entity_id))) + '"><i class="fas ' + e.icon + '" aria-hidden="true"></i>' + escapeHtml(text) + '</a>'
                   : '<span class="act-entity"><i class="fas ' + e.icon + '" aria-hidden="true"></i>' + escapeHtml(text) + '</span>';
    }
    function verb(action) {
        var v = String(action || '').split('.').slice(1).join(' ').replace(/_/g, ' ');
        return v ? v.charAt(0).toUpperCase() + v.slice(1) : action;
    }
    function ago(v) {
        var d = parseDate(v);
        if (!d) return '';
        var s = (Date.now() - d.getTime()) / 1000;
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' h ago';
        if (s < 86400 * 7) return Math.floor(s / 86400) + ' d ago';
        return formatDate(d);
    }
    function fmt(v) {
        if (v === null || v === undefined || v === '') return '<span class="muted">—</span>';
        if (v === true) return 'Yes';
        if (v === false) return 'No';
        if (typeof v === 'object') return '<code>' + escapeHtml(JSON.stringify(v)) + '</code>';
        return escapeHtml(String(v));
    }
    function same(a, b) { return JSON.stringify(a === undefined ? null : a) === JSON.stringify(b === undefined ? null : b); }

    /** before/after table: union of keys, changed rows highlighted */
    function diffHtml(r) {
        var b = r.before || {}, a = r.after || {};
        var keys = [];
        Object.keys(b).concat(Object.keys(a)).forEach(function (k) { if (keys.indexOf(k) === -1) keys.push(k); });
        var meta = '<div class="act-meta"><span><i class="fas fa-clock"></i> ' + escapeHtml(formatDateTime(r.created_at)) + '</span>' +
            '<span><i class="fas fa-code"></i> <code>' + escapeHtml(r.action) + '</code></span>' +
            (r.ip ? '<span><i class="fas fa-network-wired"></i> ' + escapeHtml(r.ip) + '</span>' : '') + '</div>';
        if (!keys.length) return meta + '<p class="act-nodiff">No field-level details were recorded for this change.</p>';
        var created = r.before === null && r.after !== null, removed = r.after === null && r.before !== null;
        var rows = keys.map(function (k) {
            var changed = !same(b[k], a[k]);
            return '<tr class="' + (changed ? 'act-changed' : '') + '"><th scope="row">' + escapeHtml(k.replace(/_/g, ' ')) + '</th>' +
                '<td class="act-before">' + (created ? '<span class="muted">—</span>' : fmt(b[k])) + '</td>' +
                '<td class="act-after">' + (removed ? '<span class="muted">—</span>' : fmt(a[k])) + '</td></tr>';
        }).join('');
        return meta + '<div class="table-scroll"><table class="act-diff"><thead><tr><th scope="col">Field</th><th scope="col">Before</th><th scope="col">After</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function rowHtml(r, compact) {
        var actor = r.actor_name || 'System';
        var initial = (actor || '?').charAt(0).toUpperCase();
        return '<div class="act-row" data-act-id="' + r.id + '">' +
            '<button type="button" class="act-head" aria-expanded="false" aria-controls="act-d-' + r.id + '">' +
            '<span class="act-time" title="' + escapeHtml(formatDateTime(r.created_at)) + '"><span>' + escapeHtml(ago(r.created_at)) + '</span><small>' + escapeHtml(formatDateTime(r.created_at)) + '</small></span>' +
            (compact ? '' : '<span class="act-actor"><span class="grid-avatar" aria-hidden="true">' + escapeHtml(initial) + '</span><span><strong>' + escapeHtml(actor) + '</strong>' +
                (r.actor_role ? '<span class="role-badge role-' + escapeHtml(r.actor_role) + '">' + escapeHtml(ROLE[r.actor_role] || r.actor_role) + '</span>' : '<small>Automatic</small>') + '</span></span>') +
            '<span class="act-action"><span class="chip">' + escapeHtml(verb(r.action)) + '</span></span>' +
            '<span class="act-summary">' + escapeHtml(r.summary) + '</span>' +
            '<i class="fas fa-chevron-down act-caret" aria-hidden="true"></i>' +
            '</button>' +
            '<div class="act-entity-cell">' + entityHtml(r) + '</div>' +
            '<div class="act-detail" id="act-d-' + r.id + '" hidden></div></div>';
    }

    function bindExpand(list, rowsById) {
        list.addEventListener('click', function (e) {
            var head = e.target.closest('.act-head');
            if (!head) return;
            var row = head.closest('.act-row');
            var det = row.querySelector('.act-detail');
            var open = head.getAttribute('aria-expanded') !== 'true';
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
            row.classList.toggle('is-open', open);
            if (open && !det.innerHTML) det.innerHTML = diffHtml(rowsById()[row.getAttribute('data-act-id')]);
            det.hidden = !open;
        });
    }

    /* ---------- full page ---------- */
    function initPage() {
        var list = document.getElementById('actList');
        var f = {
            q: document.getElementById('actSearch'), actor: document.getElementById('actActor'), entity: document.getElementById('actEntity'),
            type: document.getElementById('actType'), from: document.getElementById('actFrom'), to: document.getElementById('actTo')
        };
        var params = new URLSearchParams(location.search);
        var page = Math.max(1, parseInt(params.get('page'), 10) || 1);
        var PER = 25;
        var byId = {};
        var facetsLoaded = false;
        var seq = 0;
        Object.keys(f).forEach(function (k) { if (params.get(k)) f[k].value = params.get(k); });

        function query(extra) {
            var p = new URLSearchParams();
            Object.keys(f).forEach(function (k) { var v = f[k].value.trim(); if (v) p.set(k, v); });
            Object.keys(extra || {}).forEach(function (k) { p.set(k, extra[k]); });
            return p;
        }
        function syncUrl() {
            var p = query();
            p.set('tab', 'activity');
            if (page > 1) p.set('page', page);
            history.replaceState(null, '', location.pathname + '?' + p.toString());
            document.getElementById('actExport').href = adminUrl('api/activity.php?' + query({ format: 'csv' }).toString());
            var any = Object.keys(f).some(function (k) { return f[k].value.trim(); });
            document.getElementById('actClear').hidden = !any;
        }
        function fillFacets(fc) {
            if (facetsLoaded || !fc) return;
            facetsLoaded = true;
            var keep = { actor: f.actor.value, entity: f.entity.value, type: f.type.value };
            f.actor.innerHTML = '<option value="">Everyone</option>' + (fc.actors || []).map(function (a) {
                return '<option value="' + a.id + '">' + escapeHtml(a.name) + ' · ' + escapeHtml(ROLE[a.role] || a.role) + '</option>';
            }).join('') + '<option value="system">System (automatic)</option>';
            f.entity.innerHTML = '<option value="">All entities</option>' + (fc.entities || []).map(function (t) {
                return '<option value="' + escapeHtml(t) + '">' + escapeHtml(entityOf(t).label) + '</option>';
            }).join('');
            var groups = {};
            (fc.actions || []).forEach(function (a) { var g = a.split('.')[0]; (groups[g] = groups[g] || []).push(a); });
            f.type.innerHTML = '<option value="">All actions</option>' + Object.keys(groups).map(function (g) {
                return '<optgroup label="' + escapeHtml(entityOf(g === 'staff' ? 'admin' : (g === 'discount' ? 'promotion' : (g === 'message' ? 'contact_message' : (g === 'shipping' ? 'shipping_zone' : g)))).label) + '">' +
                    '<option value="' + escapeHtml(g) + '.">All ' + escapeHtml(g) + ' actions</option>' +
                    groups[g].map(function (a) { return '<option value="' + escapeHtml(a) + '">' + escapeHtml(verb(a)) + '</option>'; }).join('') + '</optgroup>';
            }).join('');
            Object.keys(keep).forEach(function (k) { if (keep[k]) f[k].value = keep[k]; });
        }
        function validDates() {
            var err = '';
            if (f.from.value && f.to.value && f.from.value > f.to.value) err = 'The start date is after the end date.';
            var box = document.getElementById('actDateError');
            box.hidden = !err; box.textContent = err;
            f.from.classList.toggle('is-invalid', !!err); f.to.classList.toggle('is-invalid', !!err);
            return !err;
        }
        function load() {
            if (!validDates()) return;
            syncUrl();
            var my = ++seq;
            list.classList.add('is-loading');
            fetch(adminUrl('api/activity.php?' + query({ page: page, per: PER }).toString()), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (my !== seq) return;
                    list.classList.remove('is-loading');
                    if (!d.success) { list.innerHTML = '<div class="set-loading">' + escapeHtml(d.message || 'Could not load activity') + '</div>'; return; }
                    fillFacets(d.facets);
                    byId = {};
                    (d.rows || []).forEach(function (r) { byId[r.id] = r; });
                    if (!d.rows.length) {
                        var filtered = Object.keys(f).some(function (k) { return f[k].value.trim(); });
                        list.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-clock-rotate-left"></i></div><h3 class="empty-state-title">' +
                            (filtered ? 'Nothing matches these filters' : 'No activity yet') + '</h3><p class="empty-state-text">' +
                            (filtered ? 'Try a wider date range or clear the filters.' : 'Every change staff make — orders, products, settings — is recorded here.') + '</p></div>';
                    } else {
                        list.innerHTML = '<div class="act-colhead" aria-hidden="true"><div class="act-colhead-main"><span>When</span><span>Who</span><span>Action</span><span>What happened</span><span></span></div><span>Entity</span></div>' +
                            d.rows.map(function (r) { return rowHtml(r, false); }).join('');
                    }
                    renderPager(document.getElementById('actPager'), d.total, d.page, d.per, function (p) { page = p; load(); list.scrollIntoView({ block: 'start', behavior: 'smooth' }); }, d.total === 1 ? 'change' : 'changes');
                })
                .catch(function () { if (my === seq) { list.classList.remove('is-loading'); list.innerHTML = '<div class="set-loading">Could not load activity. Check your connection.</div>'; } });
        }
        bindExpand(list, function () { return byId; });
        var t = 0;
        f.q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { page = 1; load(); }, 250); });
        ['actor', 'entity', 'type', 'from', 'to'].forEach(function (k) { f[k].addEventListener('change', function () { page = 1; load(); }); });
        document.getElementById('actClear').addEventListener('click', function () {
            Object.keys(f).forEach(function (k) { f[k].value = ''; });
            page = 1; load();
        });
        load();
    }

    /* ---------- profile: your recent activity ---------- */
    function initMine(box) {
        var byId = {};
        fetch(adminUrl('api/activity.php?mine=1&per=10'), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) { box.innerHTML = '<div class="set-loading">Could not load your activity.</div>'; return; }
                (d.rows || []).forEach(function (r) { byId[r.id] = r; });
                box.innerHTML = d.rows.length ? d.rows.map(function (r) { return rowHtml(r, true); }).join('') :
                    '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-clock-rotate-left"></i></div><h3 class="empty-state-title">Nothing yet</h3><p class="empty-state-text">Changes you make — fulfilling an order, editing a product — appear here.</p></div>';
                var more = document.getElementById('myActivityMore');
                if (more) more.hidden = !(d.total > d.rows.length && tzCan('activity.view'));
            })
            .catch(function () { box.innerHTML = '<div class="set-loading">Could not load your activity.</div>'; });
        bindExpand(box, function () { return byId; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('actList')) initPage();
        var mine = document.getElementById('myActivity');
        if (mine) initMine(mine);
    });
})();
