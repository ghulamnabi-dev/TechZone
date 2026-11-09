/* Settings: Store · Shipping · Payments · Staff (manage/settings.php; Activity lives in activity.js).
 * APIs: api/settings.php, api/shipping.php, api/staff.php. Every value reaches the DOM escaped. */
(function () {
    'use strict';
    var CFG = window.TZ_SETTINGS || {};

    /* ---------- helpers ---------- */
    function api(method, path, body) {
        var init = { method: method, headers: { Accept: 'application/json' } };
        if (body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
        return fetch(adminUrl('api/' + path), init).then(function (r) {
            return r.json().catch(function () { return { success: false, message: 'Unexpected response (' + r.status + ')' }; })
                .then(function (d) { d = d || {}; d._status = r.status; return d; });
        }).catch(function () { return { success: false, message: 'Network error. Check your connection and try again.' }; });
    }
    function $(id) { return document.getElementById(id); }
    function feedbackFor(input) {
        if (!input) return null;
        var own = $(input.id + 'Error');
        if (own) return own;
        var p = input.closest('.input-group') || input;
        var n = p.nextElementSibling;
        while (n && !n.classList.contains('invalid-feedback')) n = n.nextElementSibling;
        return n || (p.parentElement && p.parentElement.querySelector('.invalid-feedback'));
    }
    function setError(input, msg) {
        if (!input) return;
        input.classList.toggle('is-invalid', !!msg);
        if (msg) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
        var fb = feedbackFor(input);
        if (fb) fb.textContent = msg || '';
    }
    function clearErrors(root) {
        root.querySelectorAll('.is-invalid').forEach(function (el) { setError(el, ''); });
        root.querySelectorAll('.invalid-feedback').forEach(function (el) { el.textContent = ''; });
    }
    function showErrors(root, errors, map) {
        var first = null;
        Object.keys(errors || {}).forEach(function (k) {
            var el = $((map && map[k]) || k);
            if (el) { setError(el, errors[k]); first = first || el; }
            else { var fb = $(((map && map[k]) || k) + 'Error'); if (fb) fb.textContent = errors[k]; }
        });
        if (first && first.focus) first.focus();
    }
    function money(v) { return v === null || v === undefined || v === '' ? '—' : formatPKR(v); }
    function busy(btn, on, label) {
        if (!btn) return;
        if (on) { btn.dataset.label = btn.innerHTML; btn.disabled = true; btn.textContent = label || 'Saving…'; }
        else { btn.disabled = false; if (btn.dataset.label) btn.innerHTML = btn.dataset.label; }
    }
    function saveBtn() { return document.querySelector('#saveBar [data-save-submit]'); }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = CFG.tab;
        if (tab === 'store') initStore();
        else if (tab === 'payments') initPayments();
        else if (tab === 'shipping') initShipping();
        else if (tab === 'staff') initStaff();
    });

    /* ======================================================================
       Store
       ====================================================================== */
    function initStore() {
        var form = $('storeForm');
        var notice = $('store_notice');
        var KEYS = ['store_name', 'store_legal_name', 'store_phone', 'store_whatsapp', 'store_email', 'store_address', 'store_city', 'store_hours', 'store_notice', 'low_stock_threshold'];
        function preview() {
            var v = notice.value.trim();
            $('noticeCount').textContent = notice.value.length + ' / 160';
            var p = $('noticePreview');
            p.textContent = v || 'Hidden — no notice on the storefront';
            p.classList.toggle('is-empty', !v);
        }
        notice.addEventListener('input', preview);
        form.addEventListener('tz:restored', preview);
        preview();

        function validate() {
            var ok = true;
            clearErrors(form);
            if (!$('store_name').value.trim()) { setError($('store_name'), 'Store name is required.'); ok = false; }
            var em = $('store_email').value.trim();
            if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { setError($('store_email'), 'Enter a valid email address.'); ok = false; }
            ['store_phone', 'store_whatsapp'].forEach(function (id) {
                var v = $(id).value.trim();
                if (v && !/^\+?[0-9][0-9 \-]{6,18}[0-9]$/.test(v)) { setError($(id), 'Use digits, spaces or dashes, e.g. 042-35761234.'); ok = false; }
            });
            var t = $('low_stock_threshold').value;
            if (t === '' || !/^\d+$/.test(t) || +t > 1000) { setError($('low_stock_threshold'), 'Use a whole number from 0 to 1000.'); ok = false; }
            return ok;
        }
        form.addEventListener('input', function (e) { if (e.target.classList.contains('is-invalid')) setError(e.target, ''); });
        var bar = initSaveBar(form, { onSave: save });
        form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
        function save() {
            if (!validate()) { form.querySelector('.is-invalid').focus(); return; }
            var values = {};
            KEYS.forEach(function (k) { values[k] = k === 'low_stock_threshold' ? parseInt($(k).value, 10) : $(k).value.trim(); });
            busy(saveBtn(), true);
            api('PUT', 'settings.php', { section: 'store', values: values }).then(function (d) {
                busy(saveBtn(), false);
                if (!d.success) {
                    if (d.errors) showErrors(form, d.errors);
                    showNotification(d.message || 'Could not save', 'error');
                    return;
                }
                Object.keys(d.store || {}).forEach(function (k) { if ($(k) && k !== 'currency') $(k).value = d.store[k]; });
                preview();
                bar.markClean();
                showNotification(d.changed && d.changed.length ? 'Store settings saved' : 'Nothing to save', d.changed && d.changed.length ? 'success' : 'info');
            });
        }
    }

    /* ======================================================================
       Payments
       ====================================================================== */
    function initPayments() {
        var form = $('paymentsForm');
        var cod = $('cod_enabled'), jazz = $('jazzcash_enabled'), limit = $('cod_limit'), mid = $('jazzcash_merchant_id');
        var state = null, clearMid = false, bar = null;
        // a hidden flag so "Remove" makes the form dirty like any other edit
        var flag = document.createElement('input');
        flag.type = 'hidden'; flag.name = 'clear_merchant_id'; flag.value = '0';
        form.appendChild(flag);

        function fill(p) {
            state = p;
            cod.checked = !!p.cod_enabled;
            jazz.checked = !!p.jazzcash_enabled;
            limit.value = p.cod_limit ? p.cod_limit : '';
            mid.value = '';
            mid.placeholder = p.has_merchant_id ? p.jazzcash_merchant_id + '  (saved)' : 'e.g. MC12345';
            $('midClear').hidden = !p.has_merchant_id;
            $('midHelp').textContent = p.has_merchant_id ? 'Saved and shown masked. Type a new ID to replace it.' : 'From your JazzCash merchant portal. Optional for manual collection.';
            clearMid = false; flag.value = '0';
            sync();
        }
        function sync() {
            document.querySelectorAll('.set-method').forEach(function (m) {
                var on = m.querySelector('.set-switch input').checked;
                m.classList.toggle('is-off', !on);
            });
            limit.disabled = !cod.checked;
            var err = !cod.checked && !jazz.checked ? 'Keep at least one payment method on, or customers cannot check out.' : '';
            $('methodsError').hidden = !err;
            $('methodsError').textContent = err;
            renderPreview();
        }
        function renderPreview() {
            var lim = parseInt(limit.value, 10) || 0;
            var html = '';
            if (cod.checked) html += '<div class="set-pv"><span class="set-pv-radio"></span><span><strong>Cash on delivery</strong><small>' +
                (lim ? 'Only on orders up to ' + escapeHtml(formatPKR(lim)) : 'Pay the courier when your order arrives') + '</small></span></div>';
            if (jazz.checked) html += '<div class="set-pv"><span class="set-pv-radio"></span><span><strong>JazzCash</strong><small>Pay from your JazzCash mobile account</small></span></div>';
            $('checkoutPreview').innerHTML = html || '<p class="muted mb-0">No payment methods — checkout would be closed.</p>';
        }
        [cod, jazz, limit].forEach(function (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); });
        form.addEventListener('tz:restored', function () { clearMid = flag.value === '1'; sync(); });
        $('midClear').addEventListener('click', function () {
            clearMid = true; flag.value = '1'; mid.value = '';
            mid.placeholder = 'Will be removed when you save';
            $('midClear').hidden = true;
            if (bar) bar.refresh();
        });
        mid.addEventListener('input', function () { setError(mid, ''); });

        api('GET', 'settings.php').then(function (d) {
            if (!d.success) { showNotification(d.message || 'Could not load payment settings', 'error'); return; }
            fill(d.payments);
            bar = initSaveBar(form, { onSave: save, onDiscard: function () { fill(state); } });
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); save(); });

        function save() {
            clearErrors(form);
            if (!cod.checked && !jazz.checked) { sync(); cod.focus(); return; }
            var lim = limit.value.trim();
            if (lim !== '' && !/^\d+$/.test(lim)) { setError(limit, 'Use a whole rupee amount (empty means no limit).'); limit.focus(); return; }
            var m = mid.value.trim();
            if (m && !/^[A-Za-z0-9_\-]{4,40}$/.test(m)) { setError(mid, 'Merchant ID is 4–40 letters, digits, dashes or underscores.'); mid.focus(); return; }
            var values = { cod_enabled: cod.checked, jazzcash_enabled: jazz.checked, cod_limit: lim === '' ? 0 : parseInt(lim, 10) };
            if (clearMid) values.clear_merchant_id = true; else if (m) values.jazzcash_merchant_id = m;
            busy(saveBtn(), true);
            api('PUT', 'settings.php', { section: 'payments', values: values }).then(function (d) {
                busy(saveBtn(), false);
                if (!d.success) {
                    if (d.errors) {
                        if (d.errors.cod_enabled) { $('methodsError').hidden = false; $('methodsError').textContent = d.errors.cod_enabled; delete d.errors.cod_enabled; }
                        showErrors(form, d.errors);
                    }
                    showNotification(d.message || 'Could not save', 'error');
                    return;
                }
                fill(d.payments);
                bar.markClean();
                showNotification(d.changed && d.changed.length ? 'Payment settings saved' : 'Nothing to save', d.changed && d.changed.length ? 'success' : 'info');
            });
        }
    }

    /* ======================================================================
       Shipping zones
       ====================================================================== */
    function initShipping() {
        var zones = [];
        var modalEl = $('zoneModal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var form = $('zoneForm');
        var box = $('zoneCities'), cityInput = $('zoneCityInput');
        var chips = [];

        function load() {
            return api('GET', 'shipping.php').then(function (d) {
                if (!d.success) { $('zoneList').innerHTML = '<div class="set-loading">Could not load zones.</div>'; return; }
                zones = d.zones || [];
                render();
            });
        }
        function cityList() {
            var dl = $('testCityList');
            var all = [];
            zones.forEach(function (z) { all = all.concat(z.cities); });
            dl.innerHTML = all.sort().map(function (c) { return '<option value="' + escapeHtml(c) + '">'; }).join('');
        }
        function render() {
            cityList();
            var def = zones.filter(function (z) { return z.is_default; })[0];
            if (!zones.length) {
                $('zoneList').innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-truck"></i></div><h3 class="empty-state-title">No shipping zones</h3>' +
                    '<p class="empty-state-text">Checkout charges a flat Rs 500 (free over Rs 5,000) until you add one.</p></div>';
                return;
            }
            $('zoneList').innerHTML = '<div class="set-zone-head" aria-hidden="true"><span>Zone</span><span>Cities</span><span class="num">Rate</span><span class="num">Free over</span><span>Delivery</span><span></span></div>' +
                zones.map(function (z) {
                    var r = z.rate || {};
                    var shown = z.cities.slice(0, 8);
                    var more = z.cities.length - shown.length;
                    return '<div class="set-zone" data-zone="' + z.id + '">' +
                        '<div class="set-zone-name"><strong>' + escapeHtml(z.name) + '</strong>' + (z.is_default ? '<span class="pill pill-info no-dot">Default</span>' : '') +
                        '<small>' + z.cities.length + ' cit' + (z.cities.length === 1 ? 'y' : 'ies') + (z.is_default ? ' · + every unlisted city' : '') + '</small></div>' +
                        '<div class="set-zone-cities">' + (shown.length ? shown.map(function (c) { return '<span class="chip">' + escapeHtml(c) + '</span>'; }).join('') : '<span class="muted">Only unlisted cities</span>') +
                        (more > 0 ? '<button type="button" class="chip set-more" data-zone-more="' + z.id + '">+' + more + ' more</button>' : '') + '</div>' +
                        '<div class="num tabular" data-label="Rate">' + money(r.amount) + '</div>' +
                        '<div class="num tabular" data-label="Free over">' + (r.free_over == null ? '<span class="muted">Never</span>' : money(r.free_over)) + '</div>' +
                        '<div class="ink-2" data-label="Delivery">' + escapeHtml(r.eta || '—') + '</div>' +
                        '<div class="set-zone-actions">' +
                        iconButton('fa-pen', 'Edit ' + z.name, 'data-zone-edit="' + z.id + '"') +
                        (z.is_default ? '' : iconButton('fa-trash', 'Delete ' + z.name, 'data-zone-delete="' + z.id + '"', 'danger')) +
                        '</div></div>';
                }).join('') +
                (def ? '' : '<div class="set-zone-warn"><i class="fas fa-triangle-exclamation"></i> No default zone: unlisted cities pay the fallback Rs 500.</div>');
        }
        $('zoneList').addEventListener('click', function (e) {
            var b;
            if ((b = e.target.closest('[data-zone-edit]'))) openZone(find(+b.getAttribute('data-zone-edit')));
            else if ((b = e.target.closest('[data-zone-delete]'))) delZone(find(+b.getAttribute('data-zone-delete')));
            else if ((b = e.target.closest('[data-zone-more]'))) {
                var z = find(+b.getAttribute('data-zone-more'));
                var wrap = b.parentElement;
                wrap.innerHTML = z.cities.map(function (c) { return '<span class="chip">' + escapeHtml(c) + '</span>'; }).join('');
            }
        });
        function find(id) { return zones.filter(function (z) { return z.id === id; })[0]; }
        function owner(city, exceptId) {
            var k = city.trim().toLowerCase();
            return zones.filter(function (z) { return z.id !== exceptId && z.cities.some(function (c) { return c.toLowerCase() === k; }); })[0];
        }

        /* chips */
        function renderChips() {
            box.querySelectorAll('.set-chip').forEach(function (c) { c.remove(); });
            var id = +$('zoneId').value || 0;
            var moving = [];
            chips.forEach(function (c, i) {
                var o = owner(c, id);
                if (o) moving.push(c + ' (from ' + o.name + ')');
                var el = document.createElement('span');
                el.className = 'set-chip' + (o ? ' is-moving' : '');
                el.innerHTML = '<span></span><button type="button" aria-label="Remove ' + escapeHtml(c) + '" data-chip-remove="' + i + '"><i class="fas fa-xmark"></i></button>';
                el.firstChild.textContent = c;
                box.insertBefore(el, cityInput);
            });
            var note = $('zoneMoveNote');
            note.hidden = !moving.length;
            note.innerHTML = moving.length ? '<i class="fas fa-right-left"></i> Saving moves ' + escapeHtml(moving.join(', ')) + ' to this zone.' : '';
        }
        function addChip(raw) {
            raw.split(',').forEach(function (part) {
                var c = part.trim().replace(/\s+/g, ' ');
                if (!c) return;
                if (c.length > 60) { setError(box, 'City names are at most 60 characters.'); $('zoneCitiesError').textContent = 'City names are at most 60 characters.'; return; }
                c = c.replace(/\S+/g, function (w) { return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase(); });
                if (chips.some(function (x) { return x.toLowerCase() === c.toLowerCase(); })) return;
                chips.push(c);
            });
            box.classList.remove('is-invalid'); $('zoneCitiesError').textContent = '';
            renderChips();
        }
        cityInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); if (cityInput.value.trim()) { addChip(cityInput.value); cityInput.value = ''; } }
            else if (e.key === 'Backspace' && !cityInput.value && chips.length) { chips.pop(); renderChips(); }
        });
        cityInput.addEventListener('blur', function () { if (cityInput.value.trim()) { addChip(cityInput.value); cityInput.value = ''; } });
        cityInput.addEventListener('paste', function (e) {
            var t = (e.clipboardData || window.clipboardData).getData('text');
            if (t && /[,\n]/.test(t)) { e.preventDefault(); addChip(t.replace(/\n/g, ',')); }
        });
        box.addEventListener('click', function (e) {
            var b = e.target.closest('[data-chip-remove]');
            if (b) { chips.splice(+b.getAttribute('data-chip-remove'), 1); renderChips(); cityInput.focus(); return; }
            if (e.target === box) cityInput.focus();
        });

        function openZone(z) {
            clearErrors(form);
            box.classList.remove('is-invalid');
            $('zoneId').value = z ? z.id : '';
            $('zoneName').value = z ? z.name : '';
            $('zoneAmount').value = z && z.rate ? Math.round(z.rate.amount) : '';
            $('zoneFree').value = z && z.rate && z.rate.free_over != null ? Math.round(z.rate.free_over) : '';
            $('zoneEta').value = z && z.rate ? (z.rate.eta || '') : '';
            $('zoneDefault').checked = !!(z && z.is_default);
            $('zoneDefault').disabled = !!(z && z.is_default);
            chips = z ? z.cities.slice() : [];
            cityInput.value = '';
            renderChips();
            $('zoneModalTitle').textContent = z ? 'Edit ' + z.name : 'Add zone';
            $('zoneSave').textContent = z ? 'Save zone' : 'Add zone';
            modal.show();
        }
        modalEl.addEventListener('shown.bs.modal', function () { $('zoneName').focus(); });
        $('zoneAdd').addEventListener('click', function () { openZone(null); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (cityInput.value.trim()) { addChip(cityInput.value); cityInput.value = ''; }
            clearErrors(form);
            box.classList.remove('is-invalid');
            var id = +$('zoneId').value || 0;
            var bad = false;
            var name = $('zoneName').value.trim();
            if (!name) { setError($('zoneName'), 'Give the zone a name.'); bad = true; }
            else if (zones.some(function (z) { return z.id !== id && z.name.toLowerCase() === name.toLowerCase(); })) { setError($('zoneName'), 'Another zone already has this name.'); bad = true; }
            if (!chips.length && !$('zoneDefault').checked) { box.classList.add('is-invalid'); $('zoneCitiesError').textContent = 'Add at least one city (or make this the default zone).'; bad = true; }
            var amount = $('zoneAmount').value.trim();
            if (amount === '' || isNaN(+amount) || +amount < 0 || +amount > 100000) { setError($('zoneAmount'), 'Enter a rate from Rs 0 to Rs 100,000.'); bad = true; }
            var free = $('zoneFree').value.trim();
            if (free !== '' && (isNaN(+free) || +free < 0)) { setError($('zoneFree'), 'Enter an amount, or leave empty for never free.'); bad = true; }
            if (bad) { var f = form.querySelector('.is-invalid'); if (f) (f === box ? cityInput : f).focus(); return; }
            var body = { name: name, cities: chips, amount: +amount, free_over: free === '' ? null : +free, eta: $('zoneEta').value.trim(), is_default: $('zoneDefault').checked };
            if (id) body.id = id;
            var btn = $('zoneSave');
            busy(btn, true);
            api(id ? 'PUT' : 'POST', 'shipping.php', body).then(function (d) {
                busy(btn, false);
                if (!d.success) {
                    if (d.errors) {
                        if (d.errors.cities) { box.classList.add('is-invalid'); $('zoneCitiesError').textContent = d.errors.cities; }
                        showErrors(form, d.errors, { name: 'zoneName', amount: 'zoneAmount', free_over: 'zoneFree', eta: 'zoneEta', is_default: 'zoneDefault' });
                        if (d.errors.is_default) $('zoneDefaultError').textContent = d.errors.is_default;
                    }
                    showNotification(d.message || 'Could not save the zone', 'error');
                    return;
                }
                zones = d.zones || zones;
                render();
                modal.hide();
                var moved = (d.moved || []).map(function (m) { return m.city; });
                showNotification((id ? 'Zone saved' : 'Zone added') + (moved.length ? ' · moved ' + moved.join(', ') : ''));
            });
        });

        function delZone(z) {
            if (!z) return;
            var def = zones.filter(function (x) { return x.is_default; })[0];
            var msg = 'Delete the ' + z.name + ' zone? ' + (z.cities.length ? 'Its ' + z.cities.length + ' cit' + (z.cities.length === 1 ? 'y' : 'ies') + ' will ship at ' +
                (def ? 'the ' + def.name + ' rate (' + money(def.rate && def.rate.amount) + ')' : 'the fallback Rs 500') + '.' : '');
            showConfirm(msg, function () {
                api('DELETE', 'shipping.php?id=' + z.id).then(function (d) {
                    if (!d.success) { showNotification(d.message || 'Could not delete the zone', 'error'); return; }
                    zones = d.zones || [];
                    render();
                    showNotification('Zone deleted');
                });
            }, 'Delete zone');
        }

        /* Test a city */
        $('cityTest').addEventListener('submit', function (e) {
            e.preventDefault();
            var city = $('testCity').value.trim();
            setError($('testCity'), '');
            if (!city) { setError($('testCity'), 'Enter a city to test.'); $('testCity').focus(); return; }
            var sub = Math.max(0, parseInt($('testSubtotal').value, 10) || 0);
            api('GET', 'shipping.php?city=' + encodeURIComponent(city) + '&subtotal=' + sub).then(function (d) {
                var out = $('cityResult');
                out.hidden = false;
                if (!d.success) { out.innerHTML = '<span class="text-danger">' + escapeHtml(d.message || 'Could not check') + '</span>'; return; }
                var q = d.quote || {};
                var zone = q.zone ? q.zone.name : 'No zone (fallback rate)';
                var how = d.matched ? escapeHtml(city) + ' is listed in <strong>' + escapeHtml(zone) + '</strong>.' :
                    (q.zone ? escapeHtml(city) + ' isn\'t listed anywhere, so it uses the default zone <strong>' + escapeHtml(zone) + '</strong>.' : 'No zone and no default zone: the fallback rate applies.');
                out.innerHTML = '<div class="set-test-zone"><i class="fas fa-location-dot"></i><span>' + how + '</span></div>' +
                    '<dl class="set-test-dl"><div><dt>Shipping</dt><dd class="tabular" data-test-fee>' + (q.fee > 0 ? formatPKR(q.fee) : 'Free') + '</dd></div>' +
                    '<div><dt>Rate</dt><dd class="tabular">' + money(q.amount) + '</dd></div>' +
                    '<div><dt>Free over</dt><dd class="tabular">' + (q.free_over == null ? 'Never' : money(q.free_over)) + '</dd></div>' +
                    '<div><dt>Delivery</dt><dd>' + escapeHtml(q.eta || '—') + '</dd></div></dl>' +
                    (q.remaining_for_free ? '<p class="muted mb-0">' + formatPKR(q.remaining_for_free) + ' more in the cart ships free.</p>' : '');
                out.setAttribute('data-zone', q.zone ? q.zone.name : '');
            });
        });

        load();
    }

    /* ======================================================================
       Staff & roles
       ====================================================================== */
    function initStaff() {
        var me = CFG.me;
        var staff = [];
        var grid = createAdminGrid($('staffGrid'), {
            storageKey: 'tz.grid.staff.v1',
            noun: ['person', 'people'],
            emptyText: 'No staff yet. Invite someone to share the work.',
            exportName: 'staff',
            toolbar: { search: '#staffSearch', count: '#staffCount' },
            columns: [
                { field: 'name', headerName: 'Name', kind: 'thumb', minWidth: 240, flex: 1, mobileWidth: 170,
                  cellRendererParams: { avatar: function (r) { return (r.name || '?').charAt(0).toUpperCase(); }, sub: function (r) { return r.email + (r.id === me ? ' · you' : ''); } },
                  getQuickFilterText: function (p) { return p.data.name + ' ' + p.data.email + ' ' + p.data.role; } },
                { field: 'role', headerName: 'Role', width: 130, filter: 'agTextColumnFilter',
                  cellRenderer: function (p) { var s = document.createElement('span'); s.className = 'role-badge role-' + p.value; s.textContent = ({ owner: 'Owner', manager: 'Manager', staff: 'Staff' })[p.value] || p.value; return s; } },
                { field: 'last_login_at', headerName: 'Last sign-in', kind: 'datetime', width: 180,
                  cellRenderer: function (p) { var s = document.createElement('span'); s.className = 'ink-2'; s.textContent = p.value ? formatDateTime(p.value) : 'Never signed in'; return s; } },
                { field: 'is_active', headerName: 'Status', width: 130,
                  valueGetter: function (p) { return +p.data.is_active ? 'active' : 'inactive'; },
                  cellRenderer: function (p) { var w = document.createElement('span'); w.className = 'cell-pill'; w.innerHTML = statusPill(p.value, p.value === 'active' ? 'Active' : 'Deactivated'); return w; } },
                { field: 'created_at', headerName: 'Added', kind: 'date', width: 130 }
            ],
            maxActions: 3,
            rowActions: function (r) {
                if (r.id === me) return [];
                var a = [{ icon: 'fa-user-tag', label: 'Change role', attrs: 'data-staff-action="role" data-staff-id="' + r.id + '"' },
                         { icon: 'fa-key', label: 'Reset password', attrs: 'data-staff-action="reset" data-staff-id="' + r.id + '"' }];
                if (+r.is_active) a.push({ icon: 'fa-user-slash', label: 'Deactivate', attrs: 'data-staff-action="deactivate" data-staff-id="' + r.id + '"', tone: 'danger' });
                else a.push({ icon: 'fa-user-check', label: 'Reactivate', attrs: 'data-staff-action="reactivate" data-staff-id="' + r.id + '"' });
                return a;
            },
            getRowClass: function (p) { return p.data && !+p.data.is_active ? 'set-row-off' : ''; }
        });
        function setStaff(list) {
            staff = (list || []).map(function (r) { r.id = +r.id; return r; });
            grid.setRows(staff);
        }
        function load() { api('GET', 'staff.php').then(function (d) { if (d.success) setStaff(d.staff); else showNotification(d.message || 'Could not load staff', 'error'); }); }
        function find(id) { return staff.filter(function (s) { return s.id === id; })[0]; }

        $('staffGrid').addEventListener('click', function (e) {
            var b = e.target.closest('[data-staff-action]');
            if (!b) return;
            var s = find(+b.getAttribute('data-staff-id'));
            if (!s) return;
            var act = b.getAttribute('data-staff-action');
            if (act === 'role') openRole(s);
            else if (act === 'reset') showConfirm('Reset the password for ' + s.name + '? Their current password stops working right away and you\'ll get a new temporary one to share.', function () {
                api('POST', 'staff.php', { action: 'reset_password', id: s.id }).then(function (d) {
                    if (!d.success) { showNotification(d.message || 'Could not reset the password', 'error'); return; }
                    setStaff(d.staff);
                    showTemp(s.name, d.email, d.temp_password, 'New temporary password for ' + s.name + ':');
                    showNotification('Password reset');
                });
            }, 'Reset password');
            else if (act === 'deactivate') showConfirm('Deactivate ' + s.name + '? They are signed out on their next click and can\'t sign in until you reactivate them. Their history stays in the activity log.', function () {
                setActive(s, false);
            }, 'Deactivate');
            else if (act === 'reactivate') setActive(s, true);
        });
        function setActive(s, on) {
            api('PUT', 'staff.php', { id: s.id, is_active: on }).then(function (d) {
                if (!d.success) { showNotification(d.message || 'Could not update', 'error'); return; }
                setStaff(d.staff);
                showNotification(on ? s.name + ' reactivated' : s.name + ' deactivated');
            });
        }

        /* invite */
        var inviteModal = bootstrap.Modal.getOrCreateInstance($('inviteModal'));
        var inviteForm = $('inviteForm');
        $('staffInvite').addEventListener('click', function () {
            inviteForm.reset();
            clearErrors(inviteForm);
            inviteModal.show();
        });
        $('inviteModal').addEventListener('shown.bs.modal', function () { $('inviteName').focus(); });
        inviteForm.addEventListener('input', function (e) { if (e.target.classList.contains('is-invalid')) setError(e.target, ''); });
        inviteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(inviteForm);
            var name = $('inviteName').value.trim(), email = $('inviteEmail').value.trim();
            var role = (inviteForm.querySelector('input[name="inviteRole"]:checked') || {}).value || 'staff';
            var bad = false;
            if (!name) { setError($('inviteName'), 'Enter their name.'); bad = true; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setError($('inviteEmail'), 'Enter a valid email address.'); bad = true; }
            else if (staff.some(function (s) { return s.email.toLowerCase() === email.toLowerCase(); })) { setError($('inviteEmail'), 'Someone on the team already uses this email.'); bad = true; }
            if (bad) { inviteForm.querySelector('.is-invalid').focus(); return; }
            var btn = $('inviteSubmit');
            busy(btn, true, 'Creating…');
            api('POST', 'staff.php', { action: 'invite', name: name, email: email, role: role }).then(function (d) {
                busy(btn, false);
                if (!d.success) {
                    if (d.errors) showErrors(inviteForm, d.errors, { name: 'inviteName', email: 'inviteEmail' });
                    showNotification(d.message || 'Could not create the account', 'error');
                    return;
                }
                setStaff(d.staff);
                inviteModal.hide();
                showTemp(name, d.email, d.temp_password, name + ' can sign in now with this temporary password:');
                showNotification(name + ' invited');
            });
        });

        /* temp password, shown once */
        var tempModal = bootstrap.Modal.getOrCreateInstance($('tempModal'));
        function showTemp(name, email, pw, intro) {
            $('tempIntro').textContent = intro;
            $('tempPassword').textContent = pw;
            $('tempEmail').textContent = email || '';
            $('tempUrl').textContent = CFG.loginUrl || '';
            setTimeout(function () { tempModal.show(); }, 200);
        }
        $('tempModal').addEventListener('hidden.bs.modal', function () { $('tempPassword').textContent = ''; });
        $('tempCopy').addEventListener('click', function () {
            var pw = $('tempPassword').textContent;
            var done = function () { showNotification('Password copied', 'info'); };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(pw).then(done, function () { selectTemp(); });
            else selectTemp();
        });
        function selectTemp() {
            var r = document.createRange(); r.selectNodeContents($('tempPassword'));
            var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
            showNotification('Press Ctrl+C to copy', 'info');
        }

        /* role */
        var roleModal = bootstrap.Modal.getOrCreateInstance($('roleModal'));
        function openRole(s) {
            $('roleId').value = s.id;
            $('roleName').textContent = s.name;
            $('roleSelect').value = s.role;
            $('roleError').textContent = '';
            roleModal.show();
        }
        $('roleForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var s = find(+$('roleId').value);
            var role = $('roleSelect').value;
            if (!s) return;
            if (role === s.role) { roleModal.hide(); return; }
            api('PUT', 'staff.php', { id: s.id, role: role }).then(function (d) {
                if (!d.success) { $('roleError').textContent = d.message || 'Could not change the role'; return; }
                setStaff(d.staff);
                roleModal.hide();
                showNotification(s.name + ' is now ' + ({ owner: 'an Owner', manager: 'a Manager', staff: 'Staff' })[role]);
            });
        });

        load();
    }
})();
