/* TechZone admin — "Adjust stock" dialog shared by the product page and the inventory page.
 *
 *   openStockAdjust({ id, name, sku, inventory }, function onDone(result) {})
 *   result = api/inventory.php POST response: { inventory, adjustment, … }
 *
 * Stock only moves through api/inventory.php -> adjust_inventory() (the ledger), never directly.
 */
(function () {
    'use strict';
    var REASONS = [
        { v: 'received', label: 'Received', hint: 'New stock from a supplier', dir: 1 },
        { v: 'returned', label: 'Returned', hint: 'A customer sent it back in sellable condition', dir: 1 },
        { v: 'damaged', label: 'Damaged', hint: 'Broken, faulty or lost', dir: -1 },
        { v: 'correction', label: 'Correction', hint: 'The shelf count differs from the system', dir: 0 }
    ];
    var modalEl = null, modal = null, current = null, done = null, busy = false;

    function build() {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'stockAdjustModal';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-labelledby', 'stockAdjustTitle');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
            '<div class="modal-header"><div><h2 class="modal-title" id="stockAdjustTitle">Adjust stock</h2><div class="panel-sub" id="stockAdjustProduct"></div></div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
            '<form id="stockAdjustForm" novalidate><div class="modal-body">' +
            '<div class="prd-adjust-now"><span class="ink-2">Stock</span><span><strong id="stockAdjustFrom">0</strong><i class="fas fa-arrow-right prd-adjust-arrow" aria-hidden="true"></i><strong id="stockAdjustTo">0</strong></span></div>' +
            '<div class="prd-grid2">' +
            '<div class="prd-field"><label class="form-label" for="stockAdjustReason">Reason</label><select class="form-select" id="stockAdjustReason" name="reason">' +
            REASONS.map(function (r) { return '<option value="' + r.v + '">' + r.label + '</option>'; }).join('') +
            '</select><div class="form-text" id="stockAdjustReasonHint"></div></div>' +
            '<div class="prd-field"><label class="form-label" for="stockAdjustDelta">Change</label>' +
            '<div class="prd-stepper"><button type="button" class="btn btn-secondary" data-step="-1" aria-label="Remove one"><i class="fas fa-minus"></i></button>' +
            '<input type="number" class="form-control" id="stockAdjustDelta" name="delta" step="1" value="1" inputmode="numeric" aria-describedby="stockAdjustDeltaHint">' +
            '<button type="button" class="btn btn-secondary" data-step="1" aria-label="Add one"><i class="fas fa-plus"></i></button></div>' +
            '<div class="form-text" id="stockAdjustDeltaHint">Positive adds stock, negative removes it.</div></div>' +
            '<div class="prd-field"><label class="form-label" for="stockAdjustRef">Reference <span class="prd-hint">optional</span></label>' +
            '<input type="text" class="form-control" id="stockAdjustRef" name="reference" maxlength="80" placeholder="PO-2026-1104, RMA 12…"></div>' +
            '<div class="prd-field"><label class="form-label" for="stockAdjustNote">Note <span class="prd-hint">optional</span></label>' +
            '<input type="text" class="form-control" id="stockAdjustNote" name="note" maxlength="200" placeholder="What happened"></div>' +
            '</div></div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="submit" class="btn btn-primary" id="stockAdjustSave">Adjust stock</button></div></form>' +
            '</div></div>';
        document.body.appendChild(modalEl);
        modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        var form = modalEl.querySelector('form');
        var reason = form.querySelector('#stockAdjustReason');
        var delta = form.querySelector('#stockAdjustDelta');
        reason.addEventListener('change', function () {
            var r = REASONS.filter(function (x) { return x.v === reason.value; })[0];
            var n = Math.abs(parseInt(delta.value, 10) || 1);
            if (r.dir) delta.value = String(r.dir * n);
            preview();
        });
        delta.addEventListener('input', preview);
        form.addEventListener('click', function (e) {
            var b = e.target.closest('[data-step]');
            if (!b) return;
            var v = (parseInt(delta.value, 10) || 0) + parseInt(b.getAttribute('data-step'), 10);
            delta.value = String(v);
            delta.dispatchEvent(new Event('input', { bubbles: true }));
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });
        modalEl.addEventListener('shown.bs.modal', function () { delta.focus(); delta.select(); });
    }

    function preview() {
        var form = modalEl.querySelector('form');
        var d = parseInt(form.querySelector('#stockAdjustDelta').value, 10) || 0;
        var r = REASONS.filter(function (x) { return x.v === form.querySelector('#stockAdjustReason').value; })[0];
        var from = parseInt(current.inventory, 10) || 0;
        var to = from + d;
        modalEl.querySelector('#stockAdjustFrom').textContent = formatNumber(from);
        var toEl = modalEl.querySelector('#stockAdjustTo');
        toEl.textContent = formatNumber(to);
        toEl.style.color = to < 0 ? 'var(--danger)' : (d > 0 ? 'var(--ok)' : (d < 0 ? 'var(--ink)' : ''));
        modalEl.querySelector('#stockAdjustReasonHint').textContent = r.hint;
        var save = modalEl.querySelector('#stockAdjustSave');
        save.textContent = d > 0 ? 'Add ' + formatNumber(d) + ' to stock' : (d < 0 ? 'Remove ' + formatNumber(-d) + ' from stock' : 'Adjust stock');
    }

    function submit() {
        if (busy) return;
        var form = modalEl.querySelector('form');
        var body = {
            product_id: current.id,
            delta: form.querySelector('#stockAdjustDelta').value.trim(),
            reason: form.querySelector('#stockAdjustReason').value,
            reference: form.querySelector('#stockAdjustRef').value.trim(),
            note: form.querySelector('#stockAdjustNote').value.trim()
        };
        busy = true;
        var save = modalEl.querySelector('#stockAdjustSave');
        save.disabled = true;
        tzJson('POST', adminUrl('api/inventory.php'), body).then(function (res) {
            busy = false;
            save.disabled = false;
            if (!res.ok) {
                var loose = res.data.errors ? tzShowErrors(form, res.data.errors) : [];
                if (!res.data.errors || loose.length) showNotification(loose[0] || res.data.message || 'Could not adjust stock', 'error');
                return;
            }
            modal.hide();
            var d = parseInt(body.delta, 10);
            showNotification('Stock adjusted: ' + (d > 0 ? '+' : '') + d + ' ' + body.reason + ' · now ' + formatNumber(res.data.inventory), 'success');
            if (done) done(res.data);
        });
    }

    window.openStockAdjust = function (product, onDone) {
        if (!tzCan('inventory.adjust')) return;
        if (!modalEl) build();
        current = product;
        done = onDone;
        var form = modalEl.querySelector('form');
        form.reset();
        tzClearErrors(form);
        form.querySelector('#stockAdjustDelta').value = '1';
        modalEl.querySelector('#stockAdjustProduct').textContent = (product.sku ? product.sku + ' · ' : '') + product.name;
        preview();
        modal.show();
    };
})();
