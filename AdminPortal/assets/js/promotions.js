/* TechZone admin — discounts list (manage/promotions.php). The builder is manage/discount.php (discount.js). */
var promotions = [];
var promotionsGrid = null;
var PROMO_STATE_LABEL = { live: 'Live', scheduled: 'Scheduled', ended: 'Ended', inactive: 'Inactive' };

function discountUrl(id) { return adminUrl('manage/discount.php?id=' + encodeURIComponent(id)); }
function promoValue(p) {
    if (p.kind === 'free_shipping') return 'Free shipping';
    if (p.kind === 'fixed') return formatPKR(p.discount_amount) + ' off';
    var pct = Number(p.discount_percentage) || 0;
    return (pct % 1 === 0 ? pct.toFixed(0) : pct) + '% off';
}
function promoTarget(p) {
    if (p.kind === 'free_shipping' || p.applies_to === 'all') return 'Entire order';
    return (p.target_name || (p.applies_to === 'category' ? 'A category' : 'A product'));
}
function promoUsage(p) { return formatNumber(p.used_count) + ' / ' + (p.usage_limit ? formatNumber(p.usage_limit) : '∞'); }
function promoSchedule(p) {
    var s = p.starts_at || p.start_date, e = p.ends_at || p.end_date;
    if (p.state === 'scheduled') return 'Starts ' + formatDate(s);
    if (e) return (p.state === 'ended' ? 'Ended ' : 'Until ') + formatDate(e);
    return 'No end date';
}

function loadPromotions() {
    promotionsGrid.setLoading(true);
    fetch(adminUrl('api/promotions.php'))
        .then(function (r) { return r.json(); })
        .then(function (data) { promotions = data.success ? (data.promotions || []) : []; promotionsGrid.setRows(promotions); })
        .catch(function () { promotionsGrid.setRows([]); showNotification('Could not load discounts. Refresh to try again.', 'error'); });
}

function deletePromotion(id) {
    var p = promotions.filter(function (x) { return x.id == id; })[0];
    showConfirm('Delete ' + (p ? p.promo_code : 'this discount') + '? Shoppers can no longer use the code; orders that used it keep their discount.', function () {
        fetch(adminUrl('api/promotions.php'), { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
            .then(function (r) { return r.json(); })
            .then(function (d) { showNotification(d.success ? 'Discount deleted' : (d.message || 'Could not delete the discount'), d.success ? 'success' : 'error'); if (d.success) loadPromotions(); });
    }, 'Delete discount');
}

document.addEventListener('DOMContentLoaded', function () {
    promotionsGrid = createAdminGrid(document.getElementById('promotionsList'), {
        storageKey: 'tz.grid.promotions.v2',
        noun: ['discount', 'discounts'],
        emptyText: 'No discounts match these filters.',
        exportName: 'discounts',
        toolbar: { search: '#promotionSearch', count: '#promotionCount', exportBtn: '#promotionsExport', resetBtn: '#promotionsReset' },
        columns: [
            { field: 'promo_code', headerName: 'Code', kind: 'stack', flex: 1, minWidth: 200, mobileWidth: 150,
              cellRendererParams: { sub: function (p) { return p.title && p.title !== p.promo_code ? p.title : ''; } },
              getQuickFilterText: function (p) { return [p.data.promo_code, p.data.title, p.data.target_name].join(' '); } },
            { colId: 'value', headerName: 'Discount', kind: 'stack', width: 170, valueGetter: function (p) { return promoValue(p.data); },
              cellRendererParams: { sub: function (p) { return promoTarget(p); } } },
            { field: 'min_order', headerName: 'Minimum', kind: 'money', width: 120,
              cellRenderer: function (p) { var s = document.createElement('span'); s.className = +p.value ? 'tabular' : 'muted'; s.textContent = +p.value ? formatPKR(p.value) : '—'; return s; } },
            { colId: 'usage', headerName: 'Used', width: 110, type: 'rightAligned', cellClass: 'num', valueGetter: function (p) { return promoUsage(p.data); },
              comparator: function (a, b, na, nb) { return (+na.data.used_count) - (+nb.data.used_count); } },
            { colId: 'schedule', headerName: 'Schedule', kind: 'muted', width: 170, valueGetter: function (p) { return promoSchedule(p.data); } },
            { colId: 'state', headerName: 'Status', kind: 'status', width: 120, valueGetter: function (p) { return PROMO_STATE_LABEL[p.data.state] || 'Inactive'; } }
        ],
        rowActions: function (p) {
            var id = parseInt(p.id, 10);
            var w = tzCan('discounts.write');
            return [
                { icon: w ? 'fa-pen' : 'fa-eye', label: w ? 'Edit' : 'View', href: discountUrl(id), attrs: 'data-edit-promotion="' + id + '"' },
                w && { icon: 'fa-trash', label: 'Delete', attrs: 'data-delete-promotion="' + id + '"', tone: 'danger' }
            ];
        },
        onRowOpen: function (p) { location.href = discountUrl(p.id); }
    });
    promotionsGrid.bindSelect('#promotionStateFilter', 'state', function (v) { return { filterType: 'text', type: 'equals', filter: v }; });
    document.getElementById('promotionsList').addEventListener('click', function (e) {
        var ed = e.target.closest('button[data-edit-promotion]');
        if (ed) { location.href = discountUrl(ed.getAttribute('data-edit-promotion')); return; }
        var del = e.target.closest('[data-delete-promotion]');
        if (del) deletePromotion(parseInt(del.getAttribute('data-delete-promotion'), 10));
    });
    if (/[?&]deleted=1/.test(location.search)) { showNotification('Discount deleted', 'success'); history.replaceState(null, '', location.pathname); }
    loadPromotions();
});
