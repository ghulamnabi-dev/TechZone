/* Seller portal — store profile (manage/store.php): about, phone, WhatsApp, address with the save bar
 * and inline validation. api/seller_portal.php GET / PUT. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var e = escapeHtml;
    var $ = function (id) { return document.getElementById(id); };
    var form = $('stForm');
    var saveBar = null;
    var saving = false;
    var F = { about: 'stAbout', phone: 'stPhone', whatsapp: 'stWhatsapp', address: 'stAddress' };

    function counter() { $('stAboutCount').textContent = $('stAbout').value.length + ' / 1000'; }
    function setState() { $('stSave').disabled = saving || !(saveBar && saveBar.isDirty()); }
    function fill(s) {
        Object.keys(F).forEach(function (k) { $(F[k]).value = s[k] == null ? '' : s[k]; });
        counter();
        var pct = +s.commission_pct;
        $('stFacts').innerHTML =
            '<dt>Shop</dt><dd><strong>' + e(s.name) + '</strong></dd>' +
            '<dt>Market</dt><dd>' + e([s.market, s.city].filter(Boolean).join(', ')) + '</dd>' +
            '<dt>Page</dt><dd class="ord-mono">techzone.pk/sellers/' + e(s.slug) + '</dd>' +
            '<dt>Status</dt><dd>' + statusPill(s.status === 'active' ? 'active' : 'inactive', s.status.charAt(0).toUpperCase() + s.status.slice(1)) + '</dd>' +
            '<dt>Commission</dt><dd class="tabular">' + e((pct % 1 ? pct.toFixed(1) : pct.toFixed(0)) + '%') + ' per delivered sale</dd>' +
            '<dt>Rating</dt><dd class="tabular">' + (+s.rating ? (+s.rating).toFixed(1) + ' ★' : 'No ratings yet') + '</dd>' +
            '<dt>Joined</dt><dd>' + e(formatDate(s.joined_at)) + '</dd>';
    }
    function load() {
        return tzJson('GET', adminUrl('api/seller_portal.php')).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load your store profile', 'error'); return; }
            fill(res.data.seller);
            saveBar.markClean();
            setState();
        });
    }
    function save() {
        if (saving) return;
        var b = {};
        Object.keys(F).forEach(function (k) { b[k] = $(F[k]).value.trim(); });
        var ce = {};
        if (b.phone && !/^[0-9+][0-9\-\s()]{6,19}$/.test(b.phone)) ce.phone = 'Enter a phone number such as 042-35761234 or 0300-1234567.';
        var wa = b.whatsapp.replace(/\D+/g, '');
        if (b.whatsapp && !/^\d{10,15}$/.test(/^03\d{9}$/.test(wa) ? '92' + wa.slice(1) : wa)) ce.whatsapp = 'Enter the WhatsApp number with country code, e.g. 923001234567.';
        if (Object.keys(ce).length) { tzShowErrors(form, ce); return; }
        saving = true; setState();
        tzJson('PUT', adminUrl('api/seller_portal.php'), b).then(function (res) {
            saving = false;
            if (!res.ok) {
                setState();
                var loose = res.data.errors ? tzShowErrors(form, res.data.errors) : [];
                showNotification(loose[0] || res.data.message || 'Could not save your store profile', 'error');
                return;
            }
            tzClearErrors(form);
            fill(res.data.seller);
            saveBar.markClean();
            setState();
            showNotification(res.data.changed && res.data.changed.length ? 'Store profile saved' : 'No changes to save', 'success');
        });
    }
    saveBar = initSaveBar(form, { onSave: save, onDiscard: function () { tzClearErrors(form); counter(); setState(); } });
    form.addEventListener('input', function () { counter(); setState(); });
    form.addEventListener('submit', function (ev) { ev.preventDefault(); save(); });
    $('stSave').addEventListener('click', save);
    load();
});
