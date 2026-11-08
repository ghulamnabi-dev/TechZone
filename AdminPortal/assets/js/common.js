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

/* ---------- status pills: one mapping for the whole admin ---------- */
var STATUS_TONE = {
    pending: 'warn', processing: 'info', shipped: 'accent', delivered: 'ok', cancelled: 'danger',
    active: 'ok', inactive: 'neutral', approved: 'ok', rejected: 'danger',
    'new': 'info', read: 'neutral', replied: 'accent', archived: 'neutral',
    live: 'ok', scheduled: 'neutral', ended: 'neutral'
};
function statusPill(status, label) {
    var key = String(status || '').toLowerCase();
    var tone = STATUS_TONE[key] || 'neutral';
    var text = label || (key ? key.charAt(0).toUpperCase() + key.slice(1) : '—');
    return '<span class="pill pill-' + tone + '">' + escapeHtml(text) + '</span>';
}

/** Inventory with a coloured dot: 0 = danger, <=5 = warn, else ok. */
function stockCell(qty) {
    var n = parseInt(qty, 10) || 0;
    var tone = n <= 0 ? 'danger' : (n <= 5 ? 'warn' : 'ok');
    var tip = n <= 0 ? 'Out of stock' : (n <= 5 ? 'Low stock' : 'In stock');
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
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) setOpen(false); });

    if (window.bootstrap && bootstrap.Tooltip) {
        new bootstrap.Tooltip(document.body, { selector: '[data-bs-toggle="tooltip"]', trigger: 'hover focus', container: 'body' });
        // a clicked button often re-renders its row; drop any tooltip left floating
        document.addEventListener('click', function () {
            document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); });
        }, true);
    }
});
