/* TechZone admin — shared helpers (formatting, status pills, tables, shell). */

/* ---------- formatting ---------- */
var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "Rs 7,219,469" — rounds to whole rupees, no decimals. */
function formatPKR(amount) {
    var n = Number(amount);
    if (!isFinite(n)) n = 0;
    var sign = n < 0 ? '-' : '';
    return sign + 'Rs ' + Math.round(Math.abs(n)).toLocaleString('en-US');
}
/** Signed rupee delta for price modifiers: "+Rs 500", "-Rs 250", "Rs 0". */
function formatPKRDelta(amount) {
    var n = Math.round(Number(amount) || 0);
    if (n === 0) return 'Rs 0';
    return (n > 0 ? '+' : '-') + 'Rs ' + Math.abs(n).toLocaleString('en-US');
}
function formatNumber(n) {
    return (Math.round(Number(n) || 0)).toLocaleString('en-US');
}
/* kept for older callers */
function formatCurrency(amount) { return formatPKR(amount); }

function parseDate(value) {
    if (!value) return null;
    if (value instanceof Date) return value;
    var m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (m) return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
    var d = new Date(value);
    return isNaN(d) ? null : d;
}
/** "8 Nov 2026" */
function formatDate(value) {
    var d = parseDate(value);
    if (!d) return '—';
    return d.getDate() + ' ' + MONTHS_SHORT[d.getMonth()] + ' ' + d.getFullYear();
}
/** "8 Nov 2026, 11:46" */
function formatDateTime(value) {
    var d = parseDate(value);
    if (!d) return '—';
    var hh = String(d.getHours()).padStart(2, '0');
    var mm = String(d.getMinutes()).padStart(2, '0');
    return formatDate(d) + ', ' + hh + ':' + mm;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/* ---------- status pills: one mapping for the whole admin (soft bg + dark text) ---------- */
var STATUS_TONE = {
    // orders (customer-facing status)
    pending: 'warn', processing: 'info', shipped: 'info', delivered: 'ok', cancelled: 'danger',
    // payment / fulfilment axes
    paid: 'ok', unpaid: 'warn', refunded: 'neutral', unfulfilled: 'warn', partial: 'info', fulfilled: 'ok',
    // catalogue
    active: 'ok', draft: 'neutral', archived: 'neutral', inactive: 'neutral',
    // reviews / messages / discounts
    approved: 'ok', rejected: 'danger', 'new': 'info', read: 'neutral', replied: 'ok',
    live: 'ok', scheduled: 'info', ended: 'neutral'
};
function statusPill(status, label) {
    var key = String(status || '').toLowerCase();
    var tone = STATUS_TONE[key] || 'neutral';
    var text = label || (key ? key.charAt(0).toUpperCase() + key.slice(1) : '—');
    return '<span class="pill pill-' + tone + '">' + escapeHtml(text) + '</span>';
}

/* ---------- roles: window.TZ_CAPS comes from core/permissions.php via includes/header.php ---------- */
/** True when the signed-in admin holds the capability (UI only; the API enforces it too). */
function tzCan(cap) {
    return Array.isArray(window.TZ_CAPS) && window.TZ_CAPS.indexOf(cap) !== -1;
}
/** Remove every [data-cap="x"] element (inside root) the admin cannot use. */
function applyCaps(root) {
    (root || document).querySelectorAll('[data-cap]').forEach(function (el) {
        var caps = el.getAttribute('data-cap').split(/\s+/).filter(Boolean);
        var ok = caps.some(tzCan);
        if (!ok) el.remove();
    });
}

/** Inventory with a coloured dot: 0 = danger, <= threshold (default 5) = warn, else ok. */
function stockCell(qty, threshold) {
    var n = parseInt(qty, 10) || 0;
    var t = threshold == null || threshold === '' ? 5 : (parseInt(threshold, 10) || 0);
    var tone = n <= 0 ? 'danger' : (n <= t ? 'warn' : 'ok');
    var tip = n <= 0 ? 'Out of stock' : (n <= t ? 'Low stock' : 'In stock');
    return '<span class="stock" title="' + tip + '"><span class="dot dot-' + tone + '"></span>' + formatNumber(n) + '</span>';
}

function iconButton(icon, label, attrs, extraClass) {
    return '<button type="button" class="icon-btn ' + (extraClass || '') + '" ' + (attrs || '') +
        ' data-bs-toggle="tooltip" data-bs-title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '">' +
        '<i class="fas ' + icon + '"></i></button>';
}

function emptyRow(colspan, text) {
    return '<tr><td colspan="' + colspan + '" class="table-empty">' + escapeHtml(text || 'Nothing to show') + '</td></tr>';
}

/* ---------- client-side pager ---------- */
/**
 * renderPager(el, total, page, pageSize, onChange, noun)
 * Writes "Showing 1–25 of 32 products" plus a compact page list into `el`.
 */
function renderPager(el, total, page, pageSize, onChange, noun) {
    if (!el) return;
    var pages = Math.max(1, Math.ceil(total / pageSize));
    if (total === 0) { el.innerHTML = ''; return; }
    var start = (page - 1) * pageSize + 1;
    var end = Math.min(total, page * pageSize);
    var html = '<span>Showing ' + start + '–' + end + ' of ' + total + (noun ? ' ' + noun : '') + '</span>';
    if (pages > 1) {
        html += '<ul class="pagination pagination-sm">';
        html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page - 1) + '" aria-label="Previous"><i class="fas fa-chevron-left"></i></a></li>';
        for (var i = 1; i <= pages; i++) {
            if (i === 1 || i === pages || Math.abs(i - page) <= 1) {
                html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
            } else if (Math.abs(i - page) === 2) {
                html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        html += '<li class="page-item' + (page >= pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page + 1) + '" aria-label="Next"><i class="fas fa-chevron-right"></i></a></li>';
        html += '</ul>';
    }
    el.innerHTML = html;
    el.onclick = function (e) {
        var a = e.target.closest('[data-page]');
        if (!a) return;
        e.preventDefault();
        if (a.parentElement.classList.contains('disabled') || a.parentElement.classList.contains('active')) return;
        onChange(parseInt(a.getAttribute('data-page'), 10));
    };
}

/* ---------- notifications / confirm ---------- */
function showNotification(message, type) {
    if (!type) type = 'success';
    var container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    var icon = { success: 'fa-circle-check text-success', error: 'fa-circle-exclamation text-danger', warning: 'fa-triangle-exclamation text-warning', info: 'fa-circle-info' }[type] || 'fa-circle-info';
    var n = document.createElement('div');
    n.className = 'notification notification-' + type;
    n.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
    n.querySelector('span').textContent = String(message);
    container.appendChild(n);
    setTimeout(function () { n.classList.add('show'); }, 10);
    setTimeout(function () {
        n.classList.remove('show');
        setTimeout(function () { n.remove(); }, 250);
    }, 3200);
}

function showConfirm(message, callback, okLabel) {
    var overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = '<div class="confirm-dialog" role="alertdialog" aria-modal="true"><div class="confirm-message"></div>' +
        '<div class="confirm-buttons"><button type="button" class="btn btn-secondary" data-confirm="cancel">Cancel</button>' +
        '<button type="button" class="btn btn-danger" data-confirm="ok"></button></div></div>';
    overlay.querySelector('.confirm-message').textContent = String(message);
    overlay.querySelector('[data-confirm="ok"]').textContent = okLabel || 'Confirm';
    document.body.appendChild(overlay);
    setTimeout(function () { overlay.classList.add('show'); overlay.querySelector('[data-confirm="ok"]').focus(); }, 10);
    function close(ok) {
        overlay.classList.remove('show');
        document.removeEventListener('keydown', onKey);
        setTimeout(function () {
            overlay.remove();
            if (ok && callback) callback(true);
        }, 200);
    }
    function onKey(e) { if (e.key === 'Escape') close(false); }
    document.addEventListener('keydown', onKey);
    overlay.addEventListener('click', function (e) {
        var b = e.target.closest('[data-confirm]');
        if (b) close(b.getAttribute('data-confirm') === 'ok');
        else if (e.target === overlay) close(false);
    });
}

/* ---------- CSRF: attach the session token to every same-origin write ---------- */
(function () {
    if (!window.fetch || window.__tzFetchWrapped) return;
    window.__tzFetchWrapped = true;
    var nativeFetch = window.fetch.bind(window);
    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    window.fetch = function (input, init) {
        try {
            init = init || {};
            var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var sameOrigin = !/^https?:\/\//i.test(url) || url.indexOf(location.origin) === 0;
            var t = token();
            if (t && sameOrigin && method !== 'GET' && method !== 'HEAD') {
                var headers = new Headers(init.headers || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', t);
                init.headers = headers;
                if (typeof FormData !== 'undefined' && init.body instanceof FormData && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', t);
                }
            }
        } catch (e) { /* never block a request because of the helper */ }
        return nativeFetch(input, init);
    };
})();

/* ---------- shell: off-canvas sidebar, tooltips ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    var toggle = document.querySelector('[data-sidebar-toggle]');
    function setOpen(open) {
        body.classList.toggle('sidebar-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-toggle]')) { setOpen(!body.classList.contains('sidebar-open')); return; }
        if (e.target.closest('[data-sidebar-close]')) setOpen(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    applyCaps(document);
    initCommandPalette();
    initShortcuts();
    // ⌘ on Apple keyboards, Ctrl elsewhere
    if (!/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
        document.querySelectorAll('[data-kbd-mod]').forEach(function (k) { k.textContent = k.textContent.replace('⌘', 'Ctrl '); });
    }
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) setOpen(false); });

    if (window.bootstrap && bootstrap.Tooltip) {
        new bootstrap.Tooltip(document.body, { selector: '[data-bs-toggle="tooltip"]', trigger: 'hover focus', container: 'body' });
        // a clicked button often re-renders its row; drop any tooltip left floating
        document.addEventListener('click', function () {
            document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); });
        }, true);
    }
});


/* ==========================================================================
   Contextual save bar — initSaveBar(form, { onSave, onDiscard })
   Slides in over the topbar when the form differs from its saved state, warns on
   navigation, Discard restores the snapshot, Save calls onSave (or submits the form).
   Returns { markClean(), isDirty(), refresh() }.
   ========================================================================== */
function initSaveBar(form, opts) {
    opts = opts || {};
    var bar = document.getElementById('saveBar');
    if (!form || !bar) return { markClean: function () {}, isDirty: function () { return false; }, refresh: function () {} };
    var saveBtn = bar.querySelector('[data-save-submit]');
    var discardBtn = bar.querySelector('[data-save-discard]');
    var snapshot = null;
    var dirty = false;

    function fields() {
        return Array.prototype.filter.call(form.elements, function (el) {
            return el.name || el.id;
        }).filter(function (el) { return el.type !== 'file' && el.type !== 'submit' && el.type !== 'button' && !el.hasAttribute('data-save-ignore'); });
    }
    function keyOf(el) { return el.name ? el.name + (el.type === 'radio' || el.type === 'checkbox' ? '=' + el.value : '') : '#' + el.id; }
    function read() {
        var out = {};
        fields().forEach(function (el) {
            out[keyOf(el)] = (el.type === 'checkbox' || el.type === 'radio') ? el.checked : el.value;
        });
        return out;
    }
    function same(a, b) {
        var ka = Object.keys(a), kb = Object.keys(b);
        if (ka.length !== kb.length) return false;
        for (var i = 0; i < ka.length; i++) if (a[ka[i]] !== b[ka[i]]) return false;
        return true;
    }
    function setDirty(d) {
        if (d === dirty) return;
        dirty = d;
        bar.hidden = !d;
        bar.classList.toggle('is-visible', d);
        document.body.classList.toggle('has-unsaved', d);
    }
    function refresh() { setDirty(!same(read(), snapshot)); }
    function markClean() { snapshot = read(); setDirty(false); }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    saveBtn.onclick = function () {
        if (opts.onSave) opts.onSave(); else if (form.requestSubmit) form.requestSubmit(); else form.submit();
    };
    discardBtn.onclick = function () {
        fields().forEach(function (el) {
            var v = snapshot[keyOf(el)];
            if (v === undefined) return;
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = v; else el.value = v;
            el.dispatchEvent(new Event('tz:restored', { bubbles: true }));
        });
        setDirty(false);
        if (opts.onDiscard) opts.onDiscard();
    };
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });
    document.addEventListener('keydown', function (e) {
        if (dirty && (e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveBtn.click(); }
    });
    markClean();
    return { markClean: markClean, isDirty: function () { return dirty; }, refresh: refresh };
}

/* ==========================================================================
   ⌘K command palette. Phase 1: navigation + "search in …" stubs. A page or the
   phase-2 search API can register live results with tzSearch.provider = fn(q) -> Promise<[{group,label,hint,icon,href}]>.
   ========================================================================== */
var tzSearch = { provider: null, open: null, close: null };
function initCommandPalette() {
    var root = document.getElementById('cmdk');
    if (!root) return;
    var input = document.getElementById('cmdkInput');
    var list = document.getElementById('cmdkList');
    var url = function (p) { return adminUrl('manage/' + p); };
    var PAGES = [
        { label: 'Home', icon: 'fa-house', href: url('dashboard.php'), keys: 'home dashboard' },
        { label: 'Orders', icon: 'fa-inbox', href: url('orders.php'), hint: 'G O', keys: 'orders sales' },
        { label: 'Products', icon: 'fa-tag', href: url('products.php'), hint: 'G P', keys: 'products catalogue catalog' },
        { label: 'Categories', icon: 'fa-folder', href: url('categories.php'), keys: 'categories' },
        { label: 'Inventory', icon: 'fa-boxes-stacked', href: url('inventory.php'), keys: 'inventory stock low' },
        { label: 'Customers', icon: 'fa-user-group', href: url('customers.php'), hint: 'G C', keys: 'customers people' },
        { label: 'Discounts', icon: 'fa-percent', href: url('promotions.php'), keys: 'discounts promotions promo codes coupons' },
        { label: 'Reviews', icon: 'fa-star', href: url('reviews.php'), keys: 'reviews ratings moderation' },
        { label: 'Messages', icon: 'fa-envelope', href: url('contact_messages.php'), keys: 'messages inbox contact' },
        { label: 'Analytics', icon: 'fa-chart-simple', href: url('analytics.php'), keys: 'analytics reports sales' },
        { label: 'Settings', icon: 'fa-gear', href: url('settings.php'), keys: 'settings store shipping payments staff activity', cap: ['settings.write', 'activity.view'] },
        { label: 'Profile', icon: 'fa-user', href: url('profile.php'), keys: 'profile account password' }
    ].filter(function (p) { return !p.cap || p.cap.some(tzCan); });
    var items = [];
    var sel = 0;
    var seq = 0;

    function render(groups) {
        items = [];
        var html = '';
        groups.forEach(function (g) {
            if (!g.items.length) return;
            html += '<div class="cmdk-group" role="presentation">' + escapeHtml(g.title) + '</div>';
            g.items.forEach(function (it) {
                var i = items.length;
                items.push(it);
                html += '<a class="cmdk-item" role="option" id="cmdk-opt-' + i + '" data-i="' + i + '" href="' + escapeHtml(it.href) + '" aria-selected="false">' +
                    '<i class="fas ' + escapeHtml(it.icon || 'fa-arrow-right') + '" aria-hidden="true"></i><span>' + escapeHtml(it.label) + '</span>' +
                    (it.hint ? '<span class="hint">' + escapeHtml(it.hint) + '</span>' : '') + '</a>';
            });
        });
        list.innerHTML = html || '<div class="cmdk-empty">No matches. Try an order number, a SKU or a customer\'s phone.</div>';
        select(Math.min(sel, Math.max(0, items.length - 1)));
    }
    function select(i) {
        sel = i;
        list.querySelectorAll('.cmdk-item').forEach(function (a) {
            var on = +a.getAttribute('data-i') === i;
            a.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) { a.scrollIntoView({ block: 'nearest' }); input.setAttribute('aria-activedescendant', a.id); }
        });
    }
    function update() {
        var q = input.value.trim();
        var ql = q.toLowerCase();
        var pages = PAGES.filter(function (p) { return !ql || (p.label + ' ' + p.keys).toLowerCase().indexOf(ql) !== -1; });
        var groups = [];
        if (q) {
            var enc = encodeURIComponent(q);
            var num = /^#?\d+$/.test(q) ? q.replace('#', '') : null;
            var search = [];
            if (num) search.push({ label: 'Order #' + num, icon: 'fa-receipt', href: url('order.php?id=' + num), hint: 'Open order' });
            search.push({ label: 'Search orders for “' + q + '”', icon: 'fa-inbox', href: url('orders.php?q=' + enc) });
            search.push({ label: 'Search products for “' + q + '”', icon: 'fa-tag', href: url('products.php?q=' + enc) });
            search.push({ label: 'Search customers for “' + q + '”', icon: 'fa-user-group', href: url('customers.php?q=' + enc) });
            groups.push({ title: 'Search', items: search });
        }
        groups.push({ title: q ? 'Pages' : 'Go to', items: pages });
        sel = 0;
        render(groups);
        if (q && typeof tzSearch.provider === 'function') {
            var my = ++seq;
            tzSearch.provider(q).then(function (res) {
                if (my !== seq || !res || !res.length) return;
                var byGroup = {};
                res.forEach(function (r) { (byGroup[r.group || 'Results'] = byGroup[r.group || 'Results'] || []).push(r); });
                render(Object.keys(byGroup).map(function (k) { return { title: k, items: byGroup[k] }; }).concat(groups));
            }).catch(function () {});
        }
    }
    var lastFocus = null;
    function open(prefill) {
        if (!root.hidden) return;
        lastFocus = document.activeElement;
        root.hidden = false;
        document.body.style.overflow = 'hidden';
        input.value = prefill || '';
        update();
        setTimeout(function () { input.focus(); }, 0);
    }
    function close() {
        if (root.hidden) return;
        root.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    tzSearch.open = open;
    tzSearch.close = close;
    input.addEventListener('input', update);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (items.length) select((sel + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); if (items.length) select((sel - 1 + items.length) % items.length); }
        else if (e.key === 'Enter') { e.preventDefault(); if (items[sel]) location.href = items[sel].href; }
        else if (e.key === 'Escape') { e.preventDefault(); close(); }
        else if (e.key === 'Tab') { e.preventDefault(); }
    });
    list.addEventListener('mousemove', function (e) {
        var a = e.target.closest('.cmdk-item');
        if (a && +a.getAttribute('data-i') !== sel) select(+a.getAttribute('data-i'));
    });
    root.addEventListener('click', function (e) { if (e.target.closest('[data-cmdk-close]')) close(); });
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-search-open]')) { e.preventDefault(); open(); }
    });
}

/* ==========================================================================
   Keyboard shortcuts: ⌘K / Ctrl+K and / open search, G then O/P/C/H navigate, ? shows the sheet.
   ========================================================================== */
function initShortcuts() {
    var pendingG = 0;
    var GO = { o: 'orders.php', p: 'products.php', c: 'customers.php', h: 'dashboard.php' };
    function typing(t) {
        return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
    }
    function sheet() {
        var m = document.getElementById('shortcutsModal');
        if (m && window.bootstrap) bootstrap.Modal.getOrCreateInstance(m).show();
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-shortcuts-open]')) { e.preventDefault(); sheet(); }
    });
    document.addEventListener('keydown', function (e) {
        var k = (e.key || '').toLowerCase();
        if ((e.metaKey || e.ctrlKey) && k === 'k') {
            e.preventDefault();
            var root = document.getElementById('cmdk');
            if (root && !root.hidden) tzSearch.close(); else if (tzSearch.open) tzSearch.open();
            return;
        }
        if (e.metaKey || e.ctrlKey || e.altKey || typing(e.target) || document.querySelector('.modal.show')) return;
        var cmdk = document.getElementById('cmdk');
        if (cmdk && !cmdk.hidden) return;
        if (k === '/') { e.preventDefault(); if (tzSearch.open) tzSearch.open(); return; }
        if (e.key === '?') { e.preventDefault(); sheet(); return; }
        if (pendingG && GO[k]) {
            e.preventDefault();
            pendingG = 0;
            location.href = adminUrl('manage/' + GO[k]);
            return;
        }
        if (k === 'g') { pendingG = setTimeout(function () { pendingG = 0; }, 1200); return; }
        pendingG = 0;
    });
}
