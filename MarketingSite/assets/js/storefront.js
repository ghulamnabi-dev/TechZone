/**
 * Storefront behaviour shared by every page (loaded with defer from includes/header.php):
 *  - wishlist hearts   [data-wish="<id>"]           → POST api/wishlist.php (guests too)
 *  - compare checkboxes [data-compare="<id>"]        → localStorage "tz.compare", sticky "Compare (n)" bar
 *  - "/" focuses the header search; ⌘K / Ctrl+K too
 *  - Components mega-menu opens on hover for mouse users (click/keyboard via Bootstrap)
 *  - [data-autosubmit] forms submit when a checkbox/radio inside changes (catalogue filters)
 *  - [data-stock-alert] forms → POST api/stock_alert.php ("Notify me")
 * Page hooks for other pages: window.TZ.compare.{get,set,toggle,clear}, window.TZ.wishCount(n).
 */
(function () {
    'use strict';
    var TZ = window.TZ = window.TZ || {};
    var url = function (rel) { return typeof window.marketingUrl === 'function' ? window.marketingUrl(rel) : rel; };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var toast = function (msg, type) { if (typeof window.showNotification === 'function') window.showNotification(msg, type || 'success'); };

    /* ---------- wishlist ---------- */
    TZ.wishCount = function (n) {
        $$('.wish-count').forEach(function (el) { el.textContent = n; el.hidden = !n; });
        $$('.wish-link').forEach(function (el) { el.setAttribute('aria-label', 'Wishlist' + (n ? ', ' + n + ' saved' : '')); });
        $$('.wish-count-text').forEach(function (el) { el.textContent = n ? n + ' saved' : ''; });
    };
    var setWish = function (id, saved) {
        $$('[data-wish="' + id + '"]').forEach(function (b) {
            b.setAttribute('aria-pressed', saved ? 'true' : 'false');
            var l = b.querySelector('.wish__label');
            if (l) l.textContent = saved ? l.getAttribute('data-on') : l.getAttribute('data-off');
        });
    };
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-wish]') : null;
        if (!b) return;
        e.preventDefault();
        if (b.getAttribute('aria-busy') === 'true') return;
        var id = b.getAttribute('data-wish');
        var was = b.getAttribute('aria-pressed') === 'true';
        setWish(id, !was);   // optimistic
        b.setAttribute('aria-busy', 'true');
        var fd = new FormData();
        fd.append('product_id', id);
        fd.append('action', 'toggle');
        fetch(url('api/wishlist.php'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) throw new Error(j.message || 'Could not update your wishlist');
                setWish(id, !!j.saved);
                TZ.wishCount(j.count);
                toast(j.message);
            })
            .catch(function (err) { setWish(id, was); toast(err.message || 'Could not update your wishlist', 'error'); })
            .then(function () { b.removeAttribute('aria-busy'); });
    });

    /* ---------- compare (ids in localStorage "tz.compare", read by compare.php?ids=) ---------- */
    var KEY = 'tz.compare', MAX = 4;
    var read = function () {
        try {
            var v = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(v) ? v.map(Number).filter(function (n) { return n > 0; }).slice(0, MAX) : [];
        } catch (e) { return []; }
    };
    var write = function (ids) { try { localStorage.setItem(KEY, JSON.stringify(ids)); } catch (e) { /* private mode */ } };
    var bar = document.getElementById('compareBar');
    var paint = function () {
        var ids = read();
        $$('[data-compare]').forEach(function (c) {
            var on = ids.indexOf(+c.getAttribute('data-compare')) !== -1;
            if (c.type === 'checkbox') c.checked = on; else c.setAttribute('aria-pressed', on ? 'true' : 'false');
            var card = c.closest('.pcard');
            if (card) card.classList.toggle('is-comparing', on);
        });
        if (!bar) return;
        var show = ids.length >= 2;
        bar.hidden = !show;
        document.body.classList.toggle('has-compare-bar', show);
        $$('.compare-bar__count, .compare-bar__n', bar).forEach(function (el) { el.textContent = ids.length; });
        var go = bar.querySelector('[data-compare-go]');
        if (go) go.href = go.getAttribute('href').split('?')[0] + '?ids=' + ids.join(',');
    };
    TZ.compare = {
        get: read,
        set: function (ids) { write(ids.slice(0, MAX)); paint(); },
        toggle: function (id, on) {
            id = +id;
            var ids = read().filter(function (x) { return x !== id; });
            if (on) {
                if (ids.length >= MAX) { toast('You can compare up to ' + MAX + ' parts at a time', 'warning'); paint(); return false; }
                ids.push(id);
            }
            write(ids); paint(); return true;
        },
        clear: function () { write([]); paint(); }
    };
    document.addEventListener('change', function (e) {
        var c = e.target;
        if (c && c.matches && c.matches('input[type=checkbox][data-compare]')) TZ.compare.toggle(c.getAttribute('data-compare'), c.checked);
    });
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('button[data-compare]') : null;
        if (t) { TZ.compare.toggle(t.getAttribute('data-compare'), t.getAttribute('aria-pressed') !== 'true'); return; }
        if (e.target.closest && e.target.closest('[data-compare-clear]')) TZ.compare.clear();
    });
    window.addEventListener('storage', function (e) { if (e.key === KEY) paint(); });
    paint();

    /* ---------- "/" and ⌘K focus the search ---------- */
    document.addEventListener('keydown', function (e) {
        var t = e.target, typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
        var slash = e.key === '/' && !typing && !e.metaKey && !e.ctrlKey && !e.altKey;
        var cmdK = (e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey);
        if (!slash && !cmdK) return;
        var input = $$('#siteSearch, #siteSearchBar').filter(function (i) { return i.offsetParent !== null; })[0];
        if (!input) return;
        e.preventDefault();
        input.focus();
        input.select();
    });

    /* ---------- mega-menu: hover for mouse users ---------- */
    if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        $$('[data-mega]').forEach(function (li) {
            var btn = li.querySelector('[data-bs-toggle="dropdown"]');
            var timer = null;
            var dd = function () { return window.bootstrap && window.bootstrap.Dropdown ? window.bootstrap.Dropdown.getOrCreateInstance(btn) : null; };
            li.addEventListener('mouseenter', function () { clearTimeout(timer); var d = dd(); if (d && !li.querySelector('.dropdown-menu.show')) d.show(); });
            li.addEventListener('mouseleave', function () { clearTimeout(timer); timer = setTimeout(function () { var d = dd(); if (d) d.hide(); }, 160); });
        });
    }

    /* ---------- auto-submitting filter forms ---------- */
    $$('form[data-autosubmit]').forEach(function (f) {
        f.addEventListener('submit', function () {   // keep URLs clean: no "min=&max="
            $$('input[type=number]', f).forEach(function (i) { if (i.value === '') i.disabled = true; });
        });
        window.addEventListener('pageshow', function () { $$('input[type=number]', f).forEach(function (i) { i.disabled = false; }); });
        f.addEventListener('change', function (e) {
            var t = e.target;
            if (!t || !(t.type === 'checkbox' || t.type === 'radio')) return;
            if (window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches) return;   // mobile: "Show results"
            if (f.requestSubmit) f.requestSubmit(); else f.submit();
        });
    });

    /* ---------- "Notify me" back-in-stock forms ---------- */
    $$('form[data-stock-alert]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            var out = f.querySelector('.js-alert-msg');
            var btn = f.querySelector('button[type=submit]');
            if (btn) btn.disabled = true;
            fetch(url('api/stock_alert.php'), { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (out) { out.textContent = j.message || (j.success ? 'Done' : 'Something went wrong'); out.className = 'js-alert-msg notify__msg ' + (j.success ? 'is-ok' : 'is-error'); }
                    if (j.success) f.classList.add('is-done');
                })
                .catch(function () { if (out) { out.textContent = 'Something went wrong. Please try again.'; out.className = 'js-alert-msg notify__msg is-error'; } })
                .then(function () { if (btn && !f.classList.contains('is-done')) btn.disabled = false; });
        });
    });
})();
