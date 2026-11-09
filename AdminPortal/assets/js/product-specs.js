/* TechZone admin — product page "Specifications" card (core/attributes.php).
 *
 * window.tzSpecs:
 *   render(form, rows)  draw the card for an attribute form ({part_type, part_name, builder, fields})
 *                       and the product's [{key, value}] rows; rows that are not fields become extra rows
 *   collect()           [{key, value}] in display order for the save payload; tags each control with
 *                       data-field="attributes.<i>" so the API's errors land under the right input
 *   rows()              the same rows without tagging (used to carry values across a category change)
 *   form(categoryId)    Promise of the attribute form for a category (api/products.php?attribute_form=1)
 *
 * Values are never dropped silently: a value whose field disappears (category changed) moves to
 * "More specifications"; a stored value outside a fixed list stays selected and the API explains it.
 */
(function () {
    'use strict';
    var $ = function (s) { return document.getElementById(s); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var HIGHLIGHTS = ['key_spec_1', 'key_spec_2'];
    var current = { fields: [] };
    var extraSeq = 0;
    var canWrite = function () { return typeof tzCan === 'function' ? tzCan('products.write') : false; };

    function control(f, value) {
        var name = 'attr.' + f.key;
        var id = 'prdAttr_' + f.key;
        var dis = canWrite() ? '' : ' disabled';
        if (f.type === 'flag') {
            return '<select class="form-select" id="' + id + '" name="' + name + '" data-attr-key="' + f.key + '"' + dis + '>'
                + '<option value="">Not set</option><option value="1"' + (value === '1' ? ' selected' : '') + '>Yes</option>'
                + '<option value="0"' + (value === '0' ? ' selected' : '') + '>No</option></select>';
        }
        if (f.type === 'enum') {
            var opts = f.options.slice();
            if (value && !opts.some(function (o) { return o.value === value; })) opts.push({ value: value, label: value + ' (not in the list)' });
            return '<select class="form-select" id="' + id + '" name="' + name + '" data-attr-key="' + f.key + '"' + dis + '><option value="">Choose…</option>'
                + opts.map(function (o) { return '<option value="' + esc(o.value) + '"' + (o.value === value ? ' selected' : '') + '>' + esc(o.label) + '</option>'; }).join('')
                + '</select>';
        }
        if (f.type === 'multi') {
            var on = String(value || '').split(',').map(function (s) { return s.trim(); });
            return '<div class="prd-spec-multi" id="' + id + '" data-attr-key="' + f.key + '" data-multi role="group" aria-labelledby="' + id + '_l">'
                + f.options.map(function (o) {
                    return '<label class="prd-spec-chip"><input type="checkbox" class="form-check-input" name="' + name + '" value="' + esc(o.value) + '"'
                        + (on.indexOf(o.value) > -1 ? ' checked' : '') + dis + '><span>' + esc(o.label) + '</span></label>';
                }).join('') + '</div>';
        }
        var numeric = f.type === 'int' || f.type === 'decimal';
        var list = f.suggest && f.suggest.length ? ' list="' + id + '_s"' : '';
        var input = '<input type="text" class="form-control' + (numeric ? ' tabular' : '') + '" id="' + id + '" name="' + name + '" data-attr-key="' + f.key + '"'
            + ' value="' + esc(value || '') + '" maxlength="' + (numeric ? 9 : 255) + '" autocomplete="off"'
            + (numeric ? ' inputmode="' + (f.type === 'int' ? 'numeric' : 'decimal') + '"' : '') + list + dis + '>';
        if (list) input += '<datalist id="' + id + '_s">' + f.suggest.map(function (s) { return '<option value="' + esc(s) + '">'; }).join('') + '</datalist>';
        return f.unit ? '<div class="input-group">' + input + '<span class="input-group-text">' + esc(f.unit) + '</span></div>' : input;
    }

    function fieldHtml(f, value) {
        var id = 'prdAttr_' + f.key;
        var forAttr = f.type === 'multi' ? '' : ' for="' + id + '"';
        return '<div class="prd-field">'
            + '<label class="form-label" id="' + id + '_l"' + forAttr + '>' + esc(f.label)
            + (f.builder ? ' <span class="prd-spec-req" title="Needed by the PC builder"><i class="fas fa-microchip" aria-hidden="true"></i><span class="visually-hidden">needed by the PC builder</span></span>' : '')
            + '</label>' + control(f, value)
            + (f.hint ? '<div class="form-text">' + esc(f.hint) + '</div>' : '')
            + '</div>';
    }

    function extraHtml(key, value) {
        var n = ++extraSeq;
        var dis = canWrite() ? '' : ' disabled';
        return '<div class="prd-spec-row" data-extra>'
            + '<input type="text" class="form-control" name="attrx.k.' + n + '" value="' + esc(key) + '" placeholder="Name, e.g. warranty" aria-label="Specification name" list="prdSpecKeys" maxlength="40" autocomplete="off" data-extra-key' + dis + '>'
            + '<input type="text" class="form-control" name="attrx.v.' + n + '" value="' + esc(value) + '" placeholder="Value, e.g. 3 years" aria-label="Specification value" maxlength="255" autocomplete="off" data-extra-value' + dis + '>'
            + (canWrite() ? '<button type="button" class="btn btn-icon" data-extra-remove aria-label="Remove this specification" title="Remove"><i class="fas fa-xmark"></i></button>' : '')
            + '</div>';
    }

    function valueOf(el) {
        if (el.hasAttribute('data-multi')) {
            return Array.prototype.map.call(el.querySelectorAll('input:checked'), function (c) { return c.value; }).join(',');
        }
        return el.value.trim();
    }

    function gather(tag) {
        var out = [];
        var push = function (key, value, el) {
            if (tag && el) el.setAttribute('data-field', 'attributes.' + out.length);
            out.push({ key: key, value: value });
        };
        $('prdSpecFields').querySelectorAll('[data-attr-key]').forEach(function (el) { push(el.getAttribute('data-attr-key'), valueOf(el), el); });
        HIGHLIGHTS.forEach(function (k, i) { var el = $(i ? 'prdKs2' : 'prdKs1'); push(k, el.value.trim(), el); });
        $('prdSpecExtra').querySelectorAll('[data-extra]').forEach(function (row) {
            var k = row.querySelector('[data-extra-key]').value.trim();
            var v = row.querySelector('[data-extra-value]').value.trim();
            if (k === '' && v === '') return;
            push(k, v, row.querySelector(k === '' ? '[data-extra-key]' : '[data-extra-value]'));
        });
        return out;
    }

    function render(form, rows) {
        current = form || { fields: [] };
        extraSeq = 0;   // stable row names, so a re-render of the saved state matches the save bar's snapshot
        var values = {};
        var order = [];
        (rows || []).forEach(function (r) { if (r && r.key && !(r.key in values)) { values[r.key] = r.value; order.push(r.key); } });
        var fieldKeys = current.fields.map(function (f) { return f.key; });

        $('prdSpecFields').innerHTML = current.fields.map(function (f) { return fieldHtml(f, values[f.key] || ''); }).join('');
        $('prdSpecFields').hidden = !current.fields.length;
        $('prdKs1').value = values.key_spec_1 || '';
        $('prdKs2').value = values.key_spec_2 || '';
        var extras = order.filter(function (k) { return fieldKeys.indexOf(k) < 0 && HIGHLIGHTS.indexOf(k) < 0; });
        $('prdSpecExtra').innerHTML = extras.map(function (k) { return extraHtml(k, values[k]); }).join('');

        var sub = $('prdSpecsSub');
        var note = $('prdSpecsNote');
        if (current.builder) {
            sub.textContent = current.part_name + ' fields · used by the PC builder';
            note.innerHTML = '<i class="fas fa-microchip" aria-hidden="true"></i> Fields marked like this decide what the builder lets this part pair with. They are required before the product goes Active.';
            note.hidden = false;
        } else if (current.fields.length) {
            sub.textContent = 'Fields other products in this category use';
            note.hidden = true;
        } else {
            sub.textContent = 'Product page and catalogue filters';
            note.textContent = $('prdCategory') && $('prdCategory').value
                ? 'No other product in this category has specifications yet. Add the ones shoppers compare below.'
                : 'Choose a category to get its specification fields.';
            note.hidden = false;
        }
        var add = $('prdSpecAdd');
        if (add) add.hidden = !canWrite();
        [$('prdKs1'), $('prdKs2')].forEach(function (el) { el.disabled = !canWrite(); });
        placeholders();

        // suggestions for extra-row names: every label the specs table knows, minus fields shown above
        var dl = $('prdSpecKeys');
        if (dl && current.known) dl.innerHTML = current.known.filter(function (k) { return fieldKeys.indexOf(k) < 0; }).map(function (k) { return '<option value="' + esc(k) + '">'; }).join('');
    }

    /** Highlights default to the first two specs: show that as the placeholder. */
    function placeholders() {
        var vals = [];
        current.fields.forEach(function (f) {
            if (vals.length >= 2) return;
            var el = document.querySelector('[data-attr-key="' + f.key + '"]');
            var v = el ? valueOf(el) : '';
            if (!v || f.type === 'flag') return;
            vals.push(f.type === 'enum' ? (f.options.filter(function (o) { return o.value === v; })[0] || { label: v }).label : v + (f.unit ? (/^[-°]/.test(f.unit) ? '' : ' ') + f.unit : ''));
        });
        $('prdKs1').placeholder = vals[0] ? 'Defaults to “' + vals[0] + '”' : 'e.g. AM5';
        $('prdKs2').placeholder = vals[1] ? 'Defaults to “' + vals[1] + '”' : 'e.g. 6 cores';
    }

    function fetchForm(categoryId) {
        return tzJson('GET', adminUrl('api/products.php?attribute_form=1&category_id=' + encodeURIComponent(categoryId || ''))).then(function (res) {
            return res.ok ? res.data.form : { fields: [] };
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var card = $('prdSpecs');
        if (!card) return;
        $('prdSpecAdd').addEventListener('click', function () {
            $('prdSpecExtra').insertAdjacentHTML('beforeend', extraHtml('', ''));
            var rows = $('prdSpecExtra').querySelectorAll('[data-extra]');
            rows[rows.length - 1].querySelector('input').focus();
            card.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
        });
        $('prdSpecExtra').addEventListener('click', function (e) {
            var rm = e.target.closest('[data-extra-remove]');
            if (!rm) return;
            rm.closest('[data-extra]').remove();
            card.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
        });
        $('prdSpecFields').addEventListener('input', placeholders);
        $('prdSpecFields').addEventListener('change', placeholders);
    });

    window.tzSpecs = {
        render: render,
        collect: function () { return gather(true); },
        rows: function () { return gather(false); },
        form: fetchForm,
        current: function () { return current; }
    };
})();
