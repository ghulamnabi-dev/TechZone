/**
 * Home: the "Build a PC at today's rates" teaser.
 * On processor change it asks api/builder.php twice (processor alone, then processor + board +
 * graphics) and fills the build card with the same choices index.php makes on the server
 * (home_default_build()): cheapest in-stock board that fits, graphics nearest 1.8 x the processor's
 * price, cheapest in-stock memory, drive, power supply with no warning, case, and a cooler only when
 * the processor has none in the box.
 */
(function () {
    'use strict';
    /* shorter hero search placeholder on narrow screens */
    var hs = document.getElementById('heroSearch');
    if (hs && window.matchMedia) {
        var long = hs.getAttribute('placeholder'), short = hs.getAttribute('data-placeholder-short'), mq = window.matchMedia('(max-width: 599.98px)');
        var setPh = function () { hs.setAttribute('placeholder', mq.matches && short ? short : long); };
        setPh();
        if (mq.addEventListener) mq.addEventListener('change', setPh);
    }
    var select = document.getElementById('teaserCpu');
    if (!select || !window.fetch) return;
    var api = select.getAttribute('data-builder-api');
    var card = document.querySelector('.build-card');
    var rows = document.querySelector('.js-build-rows');
    var totalEl = document.querySelector('.js-build-total');
    var openEl = document.querySelector('.js-teaser-open');
    var LABELS = { cpu: 'Processor', mb: 'Motherboard', ram: 'Memory', gpu: 'Graphics', storage: 'Storage', psu: 'Power supply', 'case': 'Case', cooler: 'Cooler' };
    var ORDER = ['cpu', 'mb', 'ram', 'gpu', 'storage', 'psu', 'case', 'cooler'];
    var seq = 0;

    var fig = function (n) { return Math.round(Math.abs(n)).toLocaleString('en-US'); };
    var pkr = function (n) { return 'Rs ' + fig(n); };
    var inStock = function (list) { return (list || []).filter(function (p) { return p.in_stock; }); };
    var slotOf = function (data, key) {
        for (var i = 0; i < data.slots.length; i++) if (data.slots[i].key === key) return data.slots[i];
        return { candidates: [], selected: null };
    };
    var get = function (b) {
        return fetch(api + '?b=' + encodeURIComponent(b), { headers: { Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (j) { if (!j.success) throw new Error(j.message || 'builder'); return j; });
    };
    var encode = function (parts) {
        return ORDER.filter(function (k) { return parts[k]; }).map(function (k) { return k + ':' + parts[k].id; }).join(',');
    };
    var setText = function (sel, txt) { var el = document.querySelector(sel); if (el) el.textContent = txt; };

    function render(parts, boards, kits) {
        var total = 0;
        rows.textContent = '';
        ORDER.forEach(function (k) {
            var p = parts[k];
            if (!p) return;
            total += +p.price;
            var row = document.createElement('div');
            row.className = 'build-card__row';
            var dt = document.createElement('dt'); dt.textContent = LABELS[k];
            var dd = document.createElement('dd'); dd.className = 'build-card__name';
            var a = document.createElement('a'); a.href = p.url; a.textContent = p.name; dd.appendChild(a);
            var pr = document.createElement('dd'); pr.className = 'build-card__price'; pr.textContent = fig(p.price);
            row.appendChild(dt); row.appendChild(dd); row.appendChild(pr);
            rows.appendChild(row);
        });
        totalEl.textContent = pkr(total);
        setText('.js-boards', boards);
        setText('.js-boards-label', boards === 1 ? 'board fits' : 'boards fit');
        setText('.js-kits', kits);
        setText('.js-kits-label', kits === 1 ? 'memory kit' : 'memory kits');
        openEl.href = openEl.getAttribute('data-href') + '?b=' + encode(parts);
    }

    select.addEventListener('change', function () {
        var my = ++seq;
        var cpuId = select.value;
        card.setAttribute('aria-busy', 'true');
        card.classList.add('is-loading');
        var parts = {}, boards = 0;
        get('cpu:' + cpuId).then(function (d1) {
            if (my !== seq) return null;
            parts.cpu = slotOf(d1, 'cpu').selected;
            var mbs = slotOf(d1, 'mb').candidates;
            boards = mbs.length;
            parts.mb = inStock(mbs)[0] || null;
            var target = (parts.cpu ? +parts.cpu.price : 0) * 1.8;
            var gpus = inStock(slotOf(d1, 'gpu').candidates).slice().sort(function (a, b) {
                return (Math.abs(a.price - target) - Math.abs(b.price - target)) || (a.id - b.id);
            });
            parts.gpu = gpus[0] || null;
            parts.storage = inStock(slotOf(d1, 'storage').candidates)[0] || null;
            return get(encode({ cpu: parts.cpu, mb: parts.mb, gpu: parts.gpu }));
        }).then(function (d2) {
            if (!d2 || my !== seq) return;
            var kits = slotOf(d2, 'ram').candidates;
            parts.ram = parts.mb ? (inStock(kits)[0] || null) : null;
            parts.psu = inStock(slotOf(d2, 'psu').candidates).filter(function (p) { return !(p.warnings || []).length; })[0] || null;
            parts['case'] = inStock(slotOf(d2, 'case').candidates)[0] || null;
            parts.cooler = null;
            if (parts.cpu && (parts.cpu.attrs || {}).bundled_cooler !== '1') {
                var maxH = parts['case'] ? +((parts['case'].attrs || {}).max_cooler_mm || 0) : 0;
                parts.cooler = inStock(slotOf(d2, 'cooler').candidates).filter(function (c) {
                    var a = c.attrs || {};
                    return !maxH || a.type !== 'air' || +(a.height_mm || 0) <= maxH;
                })[0] || null;
            }
            render(parts, boards, parts.mb ? kits.length : 0);
        }).catch(function () {
            if (my !== seq) return;
            setText('.js-boards', '–');
            setText('.js-kits', '–');
        }).then(function () {
            if (my !== seq) return;
            card.setAttribute('aria-busy', 'false');
            card.classList.remove('is-loading');
        });
    });
})();
