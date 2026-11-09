/* PC Builder (builder.php). State lives in the URL (?b=cpu:46,mb:8,…); api/builder.php checks
 * compatibility and totals at today's rates. No framework: small render functions over innerHTML
 * with every value escaped. */
(function () {
    'use strict';
    var cfg = window.TZ_BUILDER;
    var root = document.getElementById('builder');
    if (!cfg || !root) return;

    var SLOT_ORDER = cfg.slots.map(function (s) { return s.key; });
    var SLOT = {};
    cfg.slots.forEach(function (s) { SLOT[s.key] = s; });
    // which slot fixes which issue
    var FIX = { socket: 'mb', memory_type: 'ram', form_factor: 'case', gpu_length: 'case', cooler_height: 'cooler',
                cooler_socket: 'cooler', cooler_tdp: 'cooler', psu_draw: 'psu', psu_recommended: 'psu', no_graphics: 'gpu' };

    var state = { b: {}, ev: cfg.initial, busy: false };
    var picker = { slot: null, data: null, opener: null };

    var $ = function (id) { return document.getElementById(id); };
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var num = function (n) { return Math.round(n).toLocaleString('en-US'); };
    var pkr = function (n) { return 'Rs ' + num(n); };

    /* ---------- build <-> string ---------- */
    function parse(str) {
        var out = {};
        String(str || '').split(',').forEach(function (pair) {
            var kv = pair.trim().split(':');
            if (kv.length === 2 && SLOT[kv[0]] && /^\d+$/.test(kv[1]) && +kv[1] > 0) out[kv[0]] = +kv[1];
        });
        return out;
    }
    function encode(b) {
        return SLOT_ORDER.filter(function (k) { return b[k]; }).map(function (k) { return k + ':' + b[k]; }).join(',');
    }
    function pageUrl(b) {
        var s = encode(b);
        return cfg.page + (s ? '?b=' + s : '');
    }
    function fromParts(parts) {
        var b = {};
        Object.keys(parts || {}).forEach(function (k) { b[k] = parts[k].id; });
        return b;
    }

    /* ---------- API ---------- */
    function api(params) {
        var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
        return fetch(cfg.api + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }
    function refresh(push) {
        var b = encode(state.b);
        if (push) history.pushState({ b: b }, '', pageUrl(state.b));
        root.classList.add('is-busy');
        return api({ b: b, candidates: 0 }).then(function (d) {
            root.classList.remove('is-busy');
            if (!d || !d.success) throw new Error((d && d.message) || 'Could not check this build');
            state.ev = d.evaluation;
            if (state.ev.invalid && state.ev.invalid.length) {
                state.b = fromParts(state.ev.parts);
                history.replaceState({ b: encode(state.b) }, '', pageUrl(state.b));
                say('Some parts in this link are no longer sold. Pick them again.', 'warn');
            }
            render();
        }).catch(function (e) {
            root.classList.remove('is-busy');
            say(e.message || 'Could not reach the shop. Check your connection and try again.', 'error');
        });
    }

    /* ---------- rendering ---------- */
    function thumb(p) {
        if (p.image) return '<span class="bld-thumb"><img src="' + esc(p.image) + '" alt="" width="56" height="56" loading="lazy" decoding="async"></span>';
        return '<span class="bld-thumb bld-thumb--tile">' + (cfg.glyphs[p.part_type] || cfg.glyphs.other) + '</span>';
    }
    function stockText(p) {
        if (!p.in_stock) return '<span class="bld-stock bld-stock--out">Out of stock</span>';
        if (p.inventory <= 5) return '<span class="bld-stock bld-stock--low">Only ' + p.inventory + ' left</span>';
        return '<span class="bld-stock">In stock</span>';
    }
    function specLine(p) {
        var bits = (p.key_specs || []).slice();
        var seller = cfg.sellers[p.seller_id];
        if (seller && p.seller_id !== 1) bits.push('Sold by ' + seller);
        return bits.map(esc).join('<span class="bld-sep" aria-hidden="true"></span>');
    }
    function slotIssues(key) {
        return (state.ev.issues || []).filter(function (i) { return i.slots.indexOf(key) !== -1 && i.kind === 'compat'; });
    }
    function optionalNote(key) {
        var cpu = state.ev.parts && state.ev.parts.cpu;
        if (!cpu) return '';
        if (key === 'gpu' && cpu.attrs && cpu.attrs.igpu === '1') return 'Optional: the ' + cpu.name + ' has integrated graphics';
        if (key === 'cooler' && cpu.attrs && cpu.attrs.bundled_cooler === '1') return 'Optional: a cooler comes in the box';
        return '';
    }

    function renderSlots() {
        var parts = state.ev.parts || {};
        var html = SLOT_ORDER.map(function (key) {
            var s = SLOT[key], p = parts[key];
            var issues = slotIssues(key);
            var blocked = issues.some(function (i) { return i.level === 'blocking'; });
            var warned = !blocked && (issues.length || (p && !p.in_stock));
            var cls = 'bld-slot' + (p ? ' is-filled' : ' is-empty') + (blocked ? ' is-blocked' : '') + (warned ? ' is-warned' : '');
            if (!p) {
                var note = optionalNote(key);
                var missing = (state.ev.missing || []).indexOf(key) !== -1 && key === 'gpu' && parts.cpu;
                return '<li class="' + cls + (missing ? ' is-blocked' : '') + '" data-slot="' + key + '">' +
                    '<span class="bld-slot__label">' + esc(s.label) + '</span>' +
                    '<button type="button" class="bld-slot__choose" data-open="' + key + '">Choose ' + esc(s.noun) + '</button>' +
                    (note ? '<span class="bld-slot__note">' + esc(note) + '</span>' : '') +
                    '</li>';
            }
            return '<li class="' + cls + '" data-slot="' + key + '">' +
                '<span class="bld-slot__label">' + esc(s.label) + '</span>' +
                thumb(p) +
                '<span class="bld-slot__part">' +
                    '<a class="bld-slot__name" href="' + esc(p.url) + '">' + esc(p.name) + '</a>' +
                    '<span class="bld-slot__specs">' + specLine(p) + '</span>' +
                    (p.in_stock && p.inventory > 5 ? '' : stockText(p)) +
                '</span>' +
                '<span class="bld-slot__price bld-num">' + num(p.price) + '</span>' +
                '<span class="bld-slot__acts">' +
                    '<button type="button" class="bld-link" data-open="' + key + '" aria-label="Change ' + esc(s.label.toLowerCase()) + '">Change</button>' +
                    '<button type="button" class="bld-link bld-link--quiet" data-remove="' + key + '" aria-label="Remove ' + esc(s.label.toLowerCase()) + '">Remove</button>' +
                '</span>' +
                '</li>';
        }).join('');
        $('bldSlots').innerHTML = html;
    }

    function renderSummary() {
        var ev = state.ev, parts = ev.parts || {};
        var chosen = Object.keys(parts).length;
        var missing = (ev.missing || []).length;
        $('bldCount').textContent = chosen === 0 ? 'No parts yet'
            : chosen + (chosen === 1 ? ' part' : ' parts') + (missing ? ', ' + missing + ' still to choose' : ', ready to order');
        $('bldTotal').textContent = pkr(ev.total || 0);
        root.setAttribute('data-state', chosen ? (ev.complete ? 'complete' : 'building') : 'empty');

        // power: draw, recommendation and the chosen PSU on one scale
        var draw = ev.wattage || 0, rec = ev.recommended_psu_w || 0;
        var psu = parts.psu ? +((parts.psu.attrs || {}).wattage || 0) : 0;
        var hasLoad = !!(parts.cpu || parts.gpu);
        var scale = Math.max(draw, rec, psu, 400) * 1.12;
        $('bldDraw').textContent = hasLoad ? draw + ' W' : '—';
        $('bldRec').textContent = hasLoad ? rec + ' W' : '—';
        $('bldPowerFill').style.width = hasLoad ? (draw / scale * 100).toFixed(1) + '%' : '0';
        var recEl = $('bldPowerRec');
        recEl.style.left = (rec / scale * 100).toFixed(1) + '%';
        recEl.hidden = !hasLoad;
        var psuEl = $('bldPowerPsu');
        psuEl.hidden = !psu;
        if (psu) {
            psuEl.style.left = (psu / scale * 100).toFixed(1) + '%';
            psuEl.setAttribute('data-w', psu + ' W');
            psuEl.className = 'bld-power__psu' + (psu < draw ? ' is-low' : psu < rec ? ' is-warn' : '');
        }
        var psuRow = $('bldPsuRow');
        psuRow.hidden = !psu;
        psuRow.className = 'bld-power__row bld-power__row--sub bld-power__row--psu' + (psu < draw ? ' is-low' : psu < rec ? ' is-warn' : '');
        $('bldPsuW').textContent = psu ? psu + ' W' : '';
        $('bldPower').classList.toggle('is-idle', !hasLoad);

        // issues, with the fix
        var items = [];
        (ev.issues || []).forEach(function (i) {
            var fix = FIX[i.code];
            var label = fix ? (parts[fix] ? 'Change ' : 'Choose ') + SLOT[fix].label.toLowerCase() : '';
            items.push('<li class="bld-issue bld-issue--' + (i.level === 'blocking' ? 'block' : 'warn') + '">' +
                '<span class="bld-issue__kind">' + (i.level === 'blocking' ? 'Does not fit' : 'Check') + '</span>' +
                '<span class="bld-issue__msg">' + esc(i.message) + '</span>' +
                (fix ? '<button type="button" class="bld-issue__fix" data-open="' + fix + '">' + esc(label) + '</button>' : '') +
                '</li>');
        });
        (ev.out_of_stock || []).forEach(function (k) {
            items.push('<li class="bld-issue bld-issue--warn"><span class="bld-issue__kind">Out of stock</span>' +
                '<span class="bld-issue__msg">' + esc(parts[k].name) + ' is out of stock right now.</span>' +
                '<button type="button" class="bld-issue__fix" data-open="' + k + '">Change ' + esc(SLOT[k].label.toLowerCase()) + '</button></li>');
        });
        var todo = (ev.missing || []).filter(function (k) { return !(ev.issues || []).some(function (i) { return i.code === 'no_graphics' && k === 'gpu'; }); });
        var html = items.length ? '<ul class="bld-issues__list">' + items.join('') + '</ul>' : '';
        if (chosen && todo.length) {
            html += '<p class="bld-todo">Still to choose: ' + todo.map(function (k) {
                return '<button type="button" class="bld-todo__slot" data-open="' + k + '">' + esc(SLOT[k].label) + '</button>';
            }).join(', ') + '</p>';
        } else if (chosen && !items.length && ev.complete) {
            html += '<p class="bld-ok">Everything fits. Parts ship together from Lahore.</p>';
        } else if (!chosen) {
            html += '<p class="bld-todo">Start with a processor: the board, memory and cooler lists follow its socket.</p>';
        }
        $('bldIssues').innerHTML = html;

        var blocking = (ev.issues || []).some(function (i) { return i.level === 'blocking'; });
        var add = $('bldAdd');
        add.disabled = !chosen || blocking || (ev.out_of_stock || []).length > 0;
        add.textContent = chosen && !ev.complete && !blocking ? 'Add ' + chosen + (chosen === 1 ? ' part' : ' parts') + ' to cart' : 'Add all to cart';
        add.title = blocking ? 'Fix the parts that do not fit first' : '';
        $('bldShare').disabled = !chosen;
        $('bldReset').disabled = !chosen;
        $('bldRules').hidden = chosen > 0;

        document.querySelectorAll('.bld-preset').forEach(function (a) {
            a.setAttribute('aria-pressed', a.getAttribute('data-build') === encode(state.b) ? 'true' : 'false');
        });
    }

    function render() {
        renderSlots();
        renderSummary();
    }

    var msgTimer;
    function say(text, kind, html) {
        var el = $('bldMsg');
        el.className = 'bld-sum__msg' + (kind ? ' is-' + kind : '');
        if (html) el.innerHTML = html; else el.textContent = text;
        clearTimeout(msgTimer);
        if (kind !== 'ok-sticky') msgTimer = setTimeout(function () { el.textContent = ''; el.className = 'bld-sum__msg'; }, 6000);
    }

    /* ---------- picker ---------- */
    function openPicker(key, opener) {
        picker.slot = key;
        picker.opener = opener || document.activeElement;
        picker.data = null;
        var s = SLOT[key];
        $('bldPickerTitle').textContent = 'Choose ' + s.noun;
        $('bldPickerSub').textContent = 'Checking what fits…';
        $('bldSearch').value = '';
        $('bldShowRejected').checked = false;
        $('bldPickerBody').innerHTML = '<p class="bld-picker__loading">Loading parts…</p>';
        var el = $('bldPicker');
        el.hidden = false;
        document.documentElement.classList.add('bld-lock');
        requestAnimationFrame(function () { el.classList.add('is-open'); });
        var others = {};
        Object.keys(state.b).forEach(function (k) { if (k !== key) others[k] = state.b[k]; });
        api({ b: encode(state.b), slot: key, rejected: 1 }).then(function (d) {
            if (picker.slot !== key) return;
            var row = (d.slots || []).filter(function (r) { return r.key === key; })[0] || {};
            picker.data = { candidates: row.candidates || [], rejected: row.rejected || [] };
            renderPicker();
            setTimeout(function () { $('bldSearch').focus(); }, 30);
        }).catch(function () {
            $('bldPickerBody').innerHTML = '<p class="bld-picker__empty">Could not load parts. Close this panel and try again.</p>';
        });
    }
    function closePicker() {
        var el = $('bldPicker');
        if (el.hidden) return;
        el.classList.remove('is-open');
        document.documentElement.classList.remove('bld-lock');
        el.hidden = true;
        var back = picker.opener;
        picker.slot = null;
        if (back && document.body.contains(back)) back.focus();
        else {
            var b = root.querySelector('[data-open]');
            if (b) b.focus();
        }
    }
    function matches(p, q) {
        if (!q) return true;
        var hay = (p.name + ' ' + (p.key_specs || []).join(' ') + ' ' + Object.keys(p.attrs || {}).map(function (k) { return p.attrs[k]; }).join(' ')).toLowerCase();
        return q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
    }
    function sortList(list) {
        var dir = $('bldSort').value === 'price_desc' ? -1 : 1;
        return list.slice().sort(function (a, b) {
            if (a.in_stock !== b.in_stock) return a.in_stock ? -1 : 1;
            return dir * (a.price - b.price) || a.id - b.id;
        });
    }
    function optionRow(p, rejected) {
        var chosen = state.b[picker.slot] === p.id;
        var notes = (rejected ? p.reasons : p.warnings) || [];
        return '<li class="bld-opt' + (chosen ? ' is-chosen' : '') + (rejected ? ' is-rejected' : '') + (!p.in_stock ? ' is-out' : '') + '">' +
            thumb(p) +
            '<span class="bld-opt__main">' +
                '<span class="bld-opt__name">' + esc(p.name) + '</span>' +
                '<span class="bld-opt__specs">' + specLine(p) + '</span>' +
                stockText(p) +
                notes.map(function (n) { return '<span class="bld-opt__note' + (rejected ? ' is-block' : ' is-warn') + '">' + esc(n) + '</span>'; }).join('') +
            '</span>' +
            '<span class="bld-opt__price bld-num">' + num(p.price) + '</span>' +
            '<span class="bld-opt__act">' +
                (chosen ? '<span class="bld-opt__chosen">Chosen</span>'
                    : '<button type="button" class="' + (rejected ? 'bld-opt__pick bld-opt__pick--quiet' : 'bld-opt__pick') + '" data-pick="' + p.id + '"' +
                      ' aria-label="' + esc((rejected ? 'Choose anyway: ' : 'Choose ') + p.name) + '">' + (rejected ? 'Choose anyway' : 'Choose') + '</button>') +
            '</span>' +
            '</li>';
    }
    function renderPicker() {
        if (!picker.data) return;
        var q = $('bldSearch').value.trim().toLowerCase();
        var cands = sortList(picker.data.candidates.filter(function (p) { return matches(p, q); }));
        var rej = sortList(picker.data.rejected.filter(function (p) { return matches(p, q); }));
        var nFit = picker.data.candidates.length, nRej = picker.data.rejected.length;
        var constrained = Object.keys(state.b).some(function (k) { return k !== picker.slot; });
        $('bldPickerSub').textContent = constrained
            ? nFit + (nFit === 1 ? ' fits' : ' fit') + ' your build' + (nRej ? ', ' + nRej + " don't" : '')
            : nFit + ' to choose from, cheapest first';
        $('bldShowRejectedLabel').textContent = "Show parts that don't fit" + (nRej ? ' (' + nRej + ')' : '');
        $('bldPickerFoot').hidden = nRej === 0;
        var html = '';
        if (cands.length) html += '<ul class="bld-opts">' + cands.map(function (p) { return optionRow(p, false); }).join('') + '</ul>';
        else html += '<p class="bld-picker__empty">' + (q ? 'Nothing matches “' + esc(q) + '”.' : 'Nothing in the catalogue fits the parts you have chosen. Change an earlier part, or show the parts that don\'t fit.') + '</p>';
        if ($('bldShowRejected').checked && rej.length) {
            html += '<h3 class="bld-opts__head">Don\'t fit this build</h3><ul class="bld-opts bld-opts--rejected">' + rej.map(function (p) { return optionRow(p, true); }).join('') + '</ul>';
        }
        $('bldPickerBody').innerHTML = html;
    }
    function choose(id) {
        var key = picker.slot;
        state.b[key] = id;
        closePicker();
        refresh(true).then(function () {
            // move on to the next slot that still needs a part
            var next = SLOT_ORDER.filter(function (k) { return (state.ev.missing || []).indexOf(k) !== -1; })[0];
            var target = next ? root.querySelector('.bld-slot[data-slot="' + next + '"] [data-open]') : $('bldAdd');
            if (target) target.focus({ preventScroll: true });
        });
    }

    /* ---------- events ---------- */
    root.addEventListener('click', function (e) {
        var t = e.target.closest('[data-open],[data-remove],[data-pick],[data-close],.bld-preset');
        if (!t) return;
        if (t.hasAttribute('data-open')) { e.preventDefault(); openPicker(t.getAttribute('data-open'), t); }
        else if (t.hasAttribute('data-remove')) {
            delete state.b[t.getAttribute('data-remove')];
            refresh(true);
        } else if (t.hasAttribute('data-pick')) choose(+t.getAttribute('data-pick'));
        else if (t.hasAttribute('data-close')) closePicker();
        else if (t.classList.contains('bld-preset')) {
            e.preventDefault();
            state.b = parse(t.getAttribute('data-build'));
            refresh(true);
        }
    });
    $('bldSearch').addEventListener('input', renderPicker);
    $('bldSort').addEventListener('change', renderPicker);
    $('bldShowRejected').addEventListener('change', renderPicker);
    document.addEventListener('keydown', function (e) {
        if ($('bldPicker').hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); closePicker(); return; }
        if (e.key === 'Tab') {   // keep focus inside the panel
            var f = $('bldPicker').querySelectorAll('button:not([disabled]), input, select, a[href]');
            var vis = Array.prototype.filter.call(f, function (x) { return x.offsetParent !== null; });
            if (!vis.length) return;
            var first = vis[0], last = vis[vis.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
    window.addEventListener('popstate', function () {
        closePicker();
        state.b = parse(new URLSearchParams(location.search).get('b'));
        refresh(false);
    });

    $('bldAdd').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'add_to_cart');
        fd.append('b', encode(state.b));
        fetch(cfg.api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    say('', 'ok-sticky', esc(d.message) + '. <a href="' + esc(cfg.cart) + '">Go to cart</a>');
                    var badge = document.querySelector('.nav-cart-badge');
                    if (badge) badge.textContent = d.cart_count;
                    else {
                        var cart = document.querySelector('.cart-link');
                        if (cart) cart.insertAdjacentHTML('beforeend', '<span class="nav-cart-badge">' + (+d.cart_count) + '</span>');
                    }
                } else say(d.message || 'Could not add the build to your cart.', 'error');
            })
            .catch(function () { say('Could not reach the shop. Please try again.', 'error'); })
            .then(function () { renderSummary(); });
    });
    $('bldShare').addEventListener('click', function () {
        var url = location.origin + pageUrl(state.b);
        var input = $('bldShareUrl');
        var done = function () { say('Link copied. Anyone with it sees this build at the rates of the day they open it.', 'ok'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(done, function () { input.hidden = false; input.value = url; input.select(); });
        } else {
            input.hidden = false; input.value = url; input.select();
            try { document.execCommand('copy'); done(); } catch (err) { say('Copy the link below.', 'ok'); }
        }
    });
    $('bldReset').addEventListener('click', function () {
        state.b = {};
        $('bldShareUrl').hidden = true;
        refresh(true);
    });

    /* ---------- start ---------- */
    state.b = fromParts(state.ev.parts);
    var asked = parse(new URLSearchParams(location.search).get('b'));
    history.replaceState({ b: encode(state.b) }, '', pageUrl(state.b));
    render();
    if (Object.keys(asked).length > Object.keys(state.b).length) say('Some parts in this link are no longer sold. Pick them again.', 'warn');
})();
