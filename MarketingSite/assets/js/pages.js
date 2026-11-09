/* Feature pages: wishlist removal, compare (URL <-> localStorage "tz.compare", only-differences),
 * and JSON forms with inline errors ([data-pg-form]: seller application, warranty claim).
 * The CSRF header is added by common.js's fetch wrapper. */
(function () {
    'use strict';
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var url = function (rel) { return typeof window.marketingUrl === 'function' ? window.marketingUrl(rel) : rel; };
    var toast = function (m, t) { if (typeof window.showNotification === 'function') window.showNotification(m, t || 'success'); };

    /* ---------- wishlist ---------- */
    var grid = document.getElementById('wlGrid');
    if (grid) {
        grid.addEventListener('click', function (e) {
            var b = e.target.closest('[data-wl-remove]');
            if (!b) return;
            var id = b.getAttribute('data-wl-remove');
            b.disabled = true;
            var fd = new FormData();
            fd.append('product_id', id);
            fd.append('action', 'remove');
            fetch(url('api/wishlist.php'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.success) throw new Error(j.message || 'Could not update your wishlist');
                    var item = grid.querySelector('[data-wl-item="' + id + '"]');
                    if (item) item.remove();
                    if (window.TZ && TZ.wishCount) TZ.wishCount(j.count);
                    var left = grid.querySelectorAll('[data-wl-item]').length;
                    var lead = document.getElementById('wlLead');
                    if (lead) lead.textContent = left ? left + ' saved ' + (left === 1 ? 'part.' : 'parts.') : 'Nothing saved yet.';
                    if (!left) { grid.hidden = true; document.getElementById('wlEmpty').hidden = false; }
                    toast('Removed from your wishlist');
                })
                .catch(function (err) { b.disabled = false; toast(err.message, 'error'); });
        });
        // a heart switched off on this page removes the card too
        document.addEventListener('click', function (e) {
            var h = e.target.closest && e.target.closest('#wlGrid [data-wish]');
            if (!h || h.getAttribute('aria-pressed') !== 'true') return;
            var item = h.closest('[data-wl-item]');
            setTimeout(function () { if (item && h.getAttribute('aria-pressed') === 'false') { item.remove(); if (!grid.querySelector('[data-wl-item]')) { grid.hidden = true; document.getElementById('wlEmpty').hidden = false; } } }, 900);
        });
    }

    /* ---------- compare ---------- */
    var cmp = window.TZ_COMPARE_PAGE;
    if (cmp) {
        var KEY = 'tz.compare';
        var stored = function () {
            try { var v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.map(Number).filter(function (n) { return n > 0; }) : []; }
            catch (e) { return []; }
        };
        var hasParam = /[?&]ids=/.test(location.search);
        if (!hasParam && cmp.ids.length < 2) {
            var ids = stored().slice(0, 4);
            if (ids.length >= 2) { location.replace(cmp.url + '?ids=' + ids.join(',')); return; }
        }
        if (cmp.ids.length) {   // the page is the source of truth from here on
            try { localStorage.setItem(KEY, JSON.stringify(cmp.ids)); } catch (e) { /* private mode */ }
        }
        $$('[data-cmp-remove]').forEach(function (a) {
            a.addEventListener('click', function () {
                var id = +a.getAttribute('data-cmp-remove');
                try { localStorage.setItem(KEY, JSON.stringify(cmp.ids.filter(function (x) { return x !== id; }))); } catch (e) {}
            });
        });
        var only = document.getElementById('cmpOnlyDiff');
        if (only) only.addEventListener('change', function () { document.getElementById('compare').classList.toggle('is-only-diff', only.checked); });
    }

    /* ---------- JSON forms with inline errors ---------- */
    $$('form[data-pg-form]').forEach(function (form) {
        var status = form.querySelector('[data-pg-status]');
        var clear = function (field) {
            var wrap = field.closest('.field, .pg-fieldset');
            if (!wrap) return;
            wrap.classList.remove('field--invalid');
            var err = wrap.querySelector('.field__error[data-generated]');
            if (err) err.remove();
            field.removeAttribute('aria-invalid');
        };
        form.addEventListener('input', function (e) { if (e.target.name) clear(e.target); });
        form.addEventListener('change', function (e) { if (e.target.name) clear(e.target); });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('[type=submit]');
            $$('.field__error[data-generated]', form).forEach(function (x) { x.remove(); });
            $$('.field--invalid', form).forEach(function (x) { x.classList.remove('field--invalid'); });
            if (status) { status.textContent = ''; status.className = 'pg-form__status'; }
            btn.disabled = true;
            var label = btn.textContent;
            btn.textContent = 'Sending…';
            fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    var j = res.j || {};
                    if (j.success) {
                        var done = document.getElementById(form.getAttribute('data-pg-form'));
                        if (done) {
                            $$('[data-pg-fill]', done).forEach(function (el) { el.textContent = j[el.getAttribute('data-pg-fill')] || el.textContent; });
                            form.hidden = true;
                            done.hidden = false;
                            done.setAttribute('tabindex', '-1');
                            done.focus();
                            done.scrollIntoView({ block: 'start', behavior: 'smooth' });
                        } else if (status) { status.textContent = j.message; status.className = 'pg-form__status is-ok'; }
                        return;
                    }
                    var first = null;
                    Object.keys(j.errors || {}).forEach(function (name) {
                        var field = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
                        if (!field) return;
                        var wrap = field.closest('.field, .pg-fieldset');
                        if (!wrap) return;
                        wrap.classList.add('field--invalid');
                        field.setAttribute('aria-invalid', 'true');
                        var p = document.createElement('p');
                        p.className = 'field__error';
                        p.setAttribute('data-generated', '');
                        p.id = 'err-' + name;
                        p.textContent = j.errors[name];
                        wrap.appendChild(p);
                        field.setAttribute('aria-describedby', p.id);
                        if (!first) first = field;
                    });
                    if (status) { status.textContent = j.message || 'Something went wrong. Please try again.'; status.className = 'pg-form__status is-error'; }
                    if (first) first.focus();
                })
                .catch(function () { if (status) { status.textContent = 'Could not reach the shop. Check your connection and try again.'; status.className = 'pg-form__status is-error'; } })
                .then(function () { btn.disabled = false; btn.textContent = label; });
        });
    });
})();
