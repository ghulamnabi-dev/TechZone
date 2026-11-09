/* TechZone admin — form helpers for the editor pages (product, discount, customer, stock adjust).
 *
 *   tzJson(method, url, body)          fetch JSON (CSRF added by common.js) -> Promise<{ ok, status, data }>
 *   tzShowErrors(root, errors)         {field: message} -> .is-invalid + .invalid-feedback under [name=field]
 *   tzClearErrors(root)
 *   tzMoney(n)                          "Rs 12,500" (formatPKR) – kept here so pages need one import
 */
(function () {
    'use strict';

    window.tzJson = function (method, url, body) {
        var init = { method: method, headers: { 'Accept': 'application/json' } };
        if (body instanceof FormData) init.body = body;
        else if (body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
        return fetch(url, init).then(function (r) {
            return r.text().then(function (t) {
                var data = null;
                try { data = t ? JSON.parse(t) : {}; } catch (e) { data = { success: false, message: 'The server sent an unexpected response.' }; }
                return { ok: r.ok && data && data.success !== false, status: r.status, data: data || {} };
            });
        }, function () {
            return { ok: false, status: 0, data: { success: false, message: 'Network error. Check your connection and try again.' } };
        });
    };

    function fieldFor(root, name) {
        return root.querySelector('[name="' + name + '"]:not([type="hidden"])') || root.querySelector('[data-field="' + name + '"]');
    }

    window.tzClearErrors = function (root) {
        root.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); });
        root.querySelectorAll('.invalid-feedback[data-tz-error]').forEach(function (el) { el.remove(); });
    };

    /** Returns the names that could not be placed next to a field (show those in a toast). */
    window.tzShowErrors = function (root, errors) {
        tzClearErrors(root);
        var loose = [];
        var first = null;
        Object.keys(errors || {}).forEach(function (name) {
            var el = fieldFor(root, name);
            if (!el) { loose.push(errors[name]); return; }
            var anchor = el.closest('.input-group, .prd-stepper, .dsc-types, .prd-radios, .dsc-seg') || el;
            if (el.matches('input, select, textarea')) { el.classList.add('is-invalid'); el.setAttribute('aria-invalid', 'true'); }
            var fb = document.createElement('div');
            fb.className = 'invalid-feedback d-block';
            fb.setAttribute('data-tz-error', '');
            fb.textContent = String(errors[name]);
            anchor.insertAdjacentElement('afterend', fb);
            if (!first) first = el;
        });
        if (first) {
            var r = first.getBoundingClientRect();
            if (r.top < 70 || r.bottom > window.innerHeight) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            var act = document.activeElement;
            if (first.focus && !(act && /^(INPUT|TEXTAREA|SELECT)$/.test(act.tagName))) first.focus({ preventScroll: true });
        }
        return loose;
    };

    /** Clear a field's error as soon as it is edited. */
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('is-invalid')) return;
        el.classList.remove('is-invalid');
        var anchor = el.closest('.input-group, .prd-stepper, .dsc-types, .prd-radios, .dsc-seg') || el;
        var fb = anchor.nextElementSibling;
        if (fb && fb.hasAttribute('data-tz-error')) fb.remove();
    });

    window.tzMoney = function (n) { return formatPKR(n); };
})();
