/* Seller portal — payout statements (manage/payouts.php). api/seller_portal.php GET. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var e = escapeHtml;
    var $ = function (id) { return document.getElementById(id); };
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function pct(v) { v = +v; return (v % 1 ? v.toFixed(1) : v.toFixed(0)) + '%'; }
    function day(d) { var t = parseDate(d); return t ? t.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) : ''; }

    fetch(adminUrl('api/seller_portal.php'), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) { showNotification(d.message || 'Could not load payouts', 'error'); return; }
            var s = d.summary;
            $('pDueLabel').textContent = 'Due ' + (s.due.date === s.today ? 'today' : day(s.due.date));
            $('pDue').textContent = formatPKR(s.due.net);
            $('pDueSub').textContent = s.due.weeks ? plural(s.due.weeks, 'closed week') + ' · ' + formatPKR(s.due.gross) + ' gross' : 'Nothing waiting from closed weeks';
            $('pWeek').textContent = formatPKR(s.this_week.net);
            $('pWeekSub').textContent = s.this_week.date ? 'Paid ' + day(s.this_week.date) + ' · ' + formatPKR(s.this_week.gross) + ' gross' : ' ';
            $('pPaid').textContent = formatPKR(s.paid_total);
            $('pPaidSub').textContent = s.paid_last ? 'Last paid ' + formatDate(s.paid_last.date) : 'No payouts yet';
            $('pPct').textContent = pct(d.seller.commission_pct);
            var rows = d.statements || [];
            $('pRows').innerHTML = rows.length ? rows.map(function (r) {
                var pill = r.status === 'paid' ? statusPill('paid') : (r.current ? statusPill('scheduled', 'This week') : statusPill('pending', 'Upcoming'));
                return '<tr' + (r.current ? ' class="sl-current"' : '') + '><td><div class="cell-main">' + e(r.label) + '</div><div class="cell-sub">' +
                    (r.status === 'paid' ? 'Paid ' + e(formatDate(r.paid_at)) : 'Pays ' + e(day(r.due_date))) + '</div></td>' +
                    '<td class="num tabular sl-hide-sm">' + formatNumber(r.units) + '</td>' +
                    '<td class="num tabular sl-hide-sm">' + formatPKR(r.gross) + '</td>' +
                    '<td class="num tabular sl-hide-sm">−' + formatPKR(r.commission) + ' <span class="cell-sub">' + e(pct(r.commission_pct)) + '</span></td>' +
                    '<td class="num tabular"><strong>' + formatPKR(r.net) + '</strong></td><td>' + pill + '</td>' +
                    '<td class="sl-hide-sm"><span class="ord-mono">' + e(r.reference || '') + '</span></td></tr>';
            }).join('') : '<tr><td colspan="7" class="table-empty">No statements yet. Your first one appears when an order with your items is delivered.</td></tr>';
        })
        .catch(function () { showNotification('Could not load payouts', 'error'); });
});
