/* TechZone admin — AG Grid Community wrapper.
 *
 *   var grid = createAdminGrid(el, {
 *       columns:     [...],            // AG Grid colDefs; `kind` picks a shared renderer (see KINDS)
 *       rows:        [...],            // optional initial rows (otherwise the grid shows "loading")
 *       storageKey:  'tz.grid.orders', // column order/width/sort/visibility + page size persist here
 *       noun:        ['order', 'orders'],
 *       emptyText:   'No orders match these filters.',
 *       exportName:  'orders',         // CSV: techzone-orders-YYYY-MM-DD.csv
 *       toolbar:     { search, count, exportBtn, resetBtn },   // elements (or selectors)
 *       rowActions:  function (row) { return [{ icon, label, attrs, tone }] },
 *       onRowOpen:   function (row) {},                        // row click / Enter
 *       selectable:  true,                                     // checkbox column + bulk bar
 *       bulkActions: [{ label, icon, tone, menu:[{label,value}], confirm, perRow(row, value) -> Promise<bool>, done(ok, failed, value) -> string }],
 *       onBulkDone:  function () {},                           // usually reloads data
 *       onSelectionChange: function (rows) {}
 *   });
 *   grid.setRows(rows); grid.setLoading(true); grid.setColumnFilter(colId, model); grid.api
 *
 * Every renderer builds DOM nodes; data only ever reaches the page through textContent or escapeHtml().
 */
(function () {
    'use strict';

    var MOBILE_MAX = 991.98;
    var PLACEHOLDER_IMG = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    var FONT = 'Inter, "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif';

    function isMobile() { return window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches; }
    function $(x) { return typeof x === 'string' ? document.querySelector(x) : x; }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = String(text);
        return n;
    }
    function fromHtml(html) {          // only ever fed markup built with escapeHtml()/statusPill()/iconButton()
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstChild;
    }
    function store(key, val) {
        try {
            if (val === undefined) return JSON.parse(localStorage.getItem(key) || 'null');
            if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(val));
        } catch (e) { return null; }
    }
    function todayStamp() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    /* ---------- theme: Quartz, every colour from our tokens ---------- */
    var theme = null;
    function adminTheme() {
        if (theme) return theme;
        theme = agGrid.themeQuartz.withPart(agGrid.colorSchemeDark).withParams({
            browserColorScheme: 'dark',
            backgroundColor: 'var(--surface)',
            foregroundColor: 'var(--ink)',
            textColor: 'var(--ink)',
            subtleTextColor: 'var(--ink-3)',
            chromeBackgroundColor: 'var(--surface-2)',
            headerBackgroundColor: 'var(--surface-2)',
            headerTextColor: 'var(--ink-3)',
            headerFontSize: 11,
            headerFontWeight: 600,
            headerHeight: 40,
            headerColumnResizeHandleColor: 'var(--line-strong)',
            borderColor: 'var(--line)',
            wrapperBorder: false,
            wrapperBorderRadius: 0,
            rowBorder: { color: 'var(--line)' },
            columnBorder: false,
            headerRowBorder: { color: 'var(--line-strong)' },
            footerRowBorder: { color: 'var(--line)' },
            pinnedColumnBorder: { color: 'var(--line)' },
            oddRowBackgroundColor: 'var(--surface)',
            rowHoverColor: 'var(--surface-2)',
            selectedRowBackgroundColor: 'rgba(255, 212, 0, .10)',
            accentColor: 'var(--accent)',
            rangeSelectionBorderColor: 'var(--accent)',
            focusShadow: '0 0 0 2px rgba(255, 212, 0, .45)',
            inputFocusShadow: '0 0 0 3px rgba(255, 212, 0, .22)',
            inputFocusBorder: { color: 'var(--accent)' },
            inputBackgroundColor: 'var(--bg)',
            inputBorder: { color: 'var(--line-strong)' },
            menuBackgroundColor: 'var(--surface-2)',
            menuBorder: { color: 'var(--line-strong)' },
            menuShadow: 'var(--shadow-pop)',
            menuTextColor: 'var(--ink)',
            pickerButtonBackgroundColor: 'var(--bg)',
            pickerListBackgroundColor: 'var(--surface-2)',
            checkboxUncheckedBackgroundColor: 'var(--bg)',
            checkboxUncheckedBorderColor: 'var(--line-strong)',
            checkboxCheckedBackgroundColor: 'var(--accent)',
            checkboxCheckedBorderColor: 'var(--accent)',
            checkboxCheckedShapeColor: 'var(--accent-ink)',
            checkboxIndeterminateBackgroundColor: 'var(--accent)',
            checkboxIndeterminateBorderColor: 'var(--accent)',
            checkboxIndeterminateShapeColor: 'var(--accent-ink)',
            toggleButtonOnBackgroundColor: 'var(--accent)',
            iconColor: 'var(--ink-3)',
            fontFamily: FONT,
            fontSize: 13.5,
            dataFontSize: 13.5,
            rowHeight: 48,
            listItemHeight: 32,
            cellHorizontalPadding: 14,
            borderRadius: 6,
            spacing: 8
        });
        return theme;
    }

    /* ---------- shared renderers (DOM, escaped) ---------- */
    var R = {
        text: function (p) { return p.valueFormatted != null ? p.valueFormatted : (p.value == null || p.value === '' ? '—' : String(p.value)); },
        muted: function (p) {
            var s = el('span', 'ink-2', p.value == null || p.value === '' ? '—' : p.value);
            return s;
        },
        money: function (p) { return el('span', 'tabular', formatPKR(p.value)); },
        number: function (p) { return el('span', 'tabular', formatNumber(p.value)); },
        date: function (p) { return el('span', 'ink-2', formatDate(p.value)); },
        datetime: function (p) { return el('span', 'ink-2', formatDateTime(p.value)); },
        status: function (p) {
            if (p.value == null || p.value === '') return el('span', 'muted', '—');
            var wrap = el('span', 'cell-pill');
            wrap.appendChild(fromHtml(statusPill(p.value)));
            return wrap;
        },
        stock: function (p) { return fromHtml(stockCell(p.value)); },
        bool: function (p) {
            var on = p.value == 1 || p.value === true || p.value === '1';
            var s = el('span', on ? 'bool-yes' : 'bool-no');
            s.innerHTML = on ? '<i class="fas fa-check"></i>' : '<i class="fas fa-minus"></i>';
            s.setAttribute('aria-label', on ? 'Yes' : 'No');
            return s;
        },
        chip: function (p) { return el('span', 'chip', p.value || '—'); },
        code: function (p) { return p.value ? el('span', 'code-chip', p.value) : el('span', 'muted', '—'); },
        id: function (p) { return el('span', 'id-cell', '#' + (p.value == null ? '' : p.value)); },
        /* 48px image + bold name + muted sub-line. cellRendererParams: { image(row), sub(row), icon, avatar(row) } */
        thumb: function (p) {
            var o = p.colDef.cellRendererParams || {};
            var row = p.data || {};
            var wrap = el('div', 'grid-thumb');
            if (o.avatar) {
                wrap.appendChild(el('span', 'grid-avatar', o.avatar(row)));
            } else if (o.image) {
                var src = o.image(row);
                if (src) {
                    var img = el('img', 'thumb');
                    img.alt = ''; img.loading = 'lazy'; img.src = src;
                    img.onerror = function () { this.onerror = null; this.src = PLACEHOLDER_IMG; };
                    wrap.appendChild(img);
                } else {
                    var ph = el('span', 'thumb thumb-ph');
                    ph.innerHTML = '<i class="fas ' + escapeHtml(o.icon || 'fa-image') + '"></i>';
                    wrap.appendChild(ph);
                }
            }
            var txt = el('div', 'txt');
            txt.appendChild(el('div', 'cell-main' + (o.weight === 500 ? ' w500' : '') + (o.wrap ? ' wrap2' : ''), p.value == null ? '' : p.value));
            var sub = o.sub ? o.sub(row) : '';
            if (sub) txt.appendChild(el('div', 'cell-sub', sub));
            wrap.appendChild(txt);
            return wrap;
        },
        /* two-line text cell without an image */
        stack: function (p) {
            var o = p.colDef.cellRendererParams || {};
            var txt = el('div', 'txt grid-stack');
            txt.appendChild(el('div', 'cell-main' + (o.strong && o.strong(p.data) ? '' : ' w500'), p.value == null || p.value === '' ? '—' : p.value));
            var sub = o.sub ? o.sub(p.data || {}) : '';
            if (sub) txt.appendChild(el('div', 'cell-sub', sub));
            return txt;
        },
        stars: function (p) {
            var n = Math.max(0, Math.min(5, parseInt(p.value, 10) || 0));
            var s = el('span', 'stars');
            s.setAttribute('aria-label', n + ' out of 5');
            s.appendChild(document.createTextNode('★★★★★'.slice(0, n)));
            s.appendChild(el('span', 'off', '★★★★★'.slice(0, 5 - n)));
            return s;
        }
    };

    function dateComparator(filterDate, cellValue) {
        var d = parseDate(cellValue);
        if (!d) return -1;
        var day = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        return day < filterDate ? -1 : (day > filterDate ? 1 : 0);
    }
    function dateSort(a, b) {
        var x = parseDate(a), y = parseDate(b);
        return (x ? x.getTime() : 0) - (y ? y.getTime() : 0);
    }

    /* kind -> colDef defaults */
    var KINDS = {
        text: { filter: 'agTextColumnFilter' },
        muted: { filter: 'agTextColumnFilter', cellRenderer: R.muted },
        id: { filter: 'agNumberColumnFilter', cellRenderer: R.id, width: 96, comparator: function (a, b) { return (+a || 0) - (+b || 0); } },
        money: { filter: 'agNumberColumnFilter', cellRenderer: R.money, type: 'rightAligned', cellClass: 'num', width: 140,
            valueGetter: null, comparator: function (a, b) { return (+a || 0) - (+b || 0); } },
        number: { filter: 'agNumberColumnFilter', cellRenderer: R.number, type: 'rightAligned', cellClass: 'num', width: 110,
            comparator: function (a, b) { return (+a || 0) - (+b || 0); } },
        date: { filter: 'agDateColumnFilter', filterParams: { comparator: dateComparator }, cellRenderer: R.date, comparator: dateSort, width: 130 },
        datetime: { filter: 'agDateColumnFilter', filterParams: { comparator: dateComparator }, cellRenderer: R.datetime, comparator: dateSort, width: 170 },
        status: { filter: 'agTextColumnFilter', filterParams: { filterOptions: ['equals', 'notEqual', 'contains'], defaultOption: 'equals', maxNumConditions: 1 }, cellRenderer: R.status, width: 136 },
        stock: { filter: 'agNumberColumnFilter', cellRenderer: R.stock, width: 124, comparator: function (a, b) { return (+a || 0) - (+b || 0); } },
        bool: { filter: 'agTextColumnFilter', cellRenderer: R.bool, width: 100 },
        chip: { filter: 'agTextColumnFilter', filterParams: { defaultOption: 'equals' }, cellRenderer: R.chip },
        code: { filter: 'agTextColumnFilter', cellRenderer: R.code, width: 140 },
        thumb: { filter: 'agTextColumnFilter', cellRenderer: R.thumb, minWidth: 200, flex: 1 },
        stack: { filter: 'agTextColumnFilter', cellRenderer: R.stack, minWidth: 180 },
        stars: { filter: 'agNumberColumnFilter', cellRenderer: R.stars, width: 120, comparator: function (a, b) { return (+a || 0) - (+b || 0); } }
    };

    /* ---------- floating row menu (mobile: actions collapse into one button) ---------- */
    function closeMenus() {
        document.querySelectorAll('.grid-row-menu').forEach(function (m) { m.remove(); });
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.grid-row-menu') && !e.target.closest('[data-grid-more]')) closeMenus();
        else if (e.target.closest('.grid-row-menu [data-menu-item]')) setTimeout(closeMenus, 0);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });
    window.addEventListener('scroll', closeMenus, true);

    function openRowMenu(host, anchor, actions) {
        closeMenus();
        var menu = el('div', 'grid-row-menu dropdown-menu show');
        menu.setAttribute('role', 'menu');
        actions.forEach(function (a) {
            // attrs come from page code (ids / fixed action names), labels are escaped
            var b = fromHtml('<button type="button" role="menuitem" data-menu-item class="dropdown-item' + (a.tone === 'danger' ? ' text-danger' : '') + '" ' + (a.attrs || '') + '>' +
                '<i class="fas ' + escapeHtml(a.icon) + '"></i>' + escapeHtml(a.label) + '</button>');
            menu.appendChild(b);
        });
        host.appendChild(menu);           // inside the grid host so the page's delegated click handler sees it
        var r = anchor.getBoundingClientRect();
        var w = menu.offsetWidth, h = menu.offsetHeight;
        var left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w));
        var top = r.bottom + 4;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 4);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        var first = menu.querySelector('button');
        if (first) first.focus();
    }

    /* ---------- the wrapper ---------- */
    window.createAdminGrid = function (host, opts) {
        host = $(host);
        opts = opts || {};
        var tb = opts.toolbar || {};
        var noun = opts.noun || ['row', 'rows'];
        var key = opts.storageKey || null;
        var ctrl = { api: null };
        var totalRows = 0;
        var restoring = false;
        var wasMobile = false;

        host.classList.add('admin-grid');

        /* bulk bar */
        var bulkBar = null;
        if (opts.selectable && opts.bulkActions && opts.bulkActions.length) {
            bulkBar = el('div', 'grid-bulkbar');
            bulkBar.hidden = true;
            bulkBar.setAttribute('role', 'region');
            bulkBar.setAttribute('aria-label', 'Bulk actions');
            host.parentNode.insertBefore(bulkBar, host);
        }

        function rowActionsFor(row) { return opts.rowActions ? (opts.rowActions(row) || []).filter(Boolean) : []; }

        function actionsRenderer(p) {
            var acts = rowActionsFor(p.data);
            if (!acts.length) return '';
            if (isMobile()) {
                var more = fromHtml('<button type="button" class="icon-btn" data-grid-more aria-label="Row actions" aria-haspopup="menu"><i class="fas fa-ellipsis"></i></button>');
                more.addEventListener('click', function (e) { e.stopPropagation(); openRowMenu(host, more, acts); });
                return more;
            }
            var wrap = el('div', 'actions');
            wrap.innerHTML = acts.map(function (a) {
                if (a.href) {
                    return '<a class="icon-btn" href="' + escapeHtml(a.href) + '" ' + (a.attrs || '') + ' data-bs-toggle="tooltip" data-bs-title="' + escapeHtml(a.label) + '" aria-label="' + escapeHtml(a.label) + '"><i class="fas ' + escapeHtml(a.icon) + '"></i></a>';
                }
                return iconButton(a.icon, a.label, a.attrs || '', a.tone || '');
            }).join('');
            return wrap;
        }
        function actionsWidth() {
            var n = 0;
            (ctrl.rows || []).forEach(function (r) { n = Math.max(n, rowActionsFor(r).length); });
            if (!n && opts.rowActions) n = opts.maxActions || 2;
            return isMobile() ? 56 : Math.max(64, n * 34 + 26);
        }

        /* column defs */
        var cols = (opts.columns || []).map(function (c, i) {
            var base = KINDS[c.kind] ? Object.assign({}, KINDS[c.kind]) : {};
            delete base.valueGetter;
            var def = Object.assign(base, c);
            if (base.filterParams && c.filterParams) def.filterParams = Object.assign({}, base.filterParams, c.filterParams);
            delete def.kind;
            if (!def.colId) def.colId = def.field || ('c' + i);
            if (def.mobileWidth) delete def.mobileWidth;
            if (i === 0) def.minWidth = Math.min(def.minWidth || 80, c.mobileWidth || 150);
            return def;
        });
        var firstColId = cols.length ? cols[0].colId : null;
        var mobileWidth = (opts.columns && opts.columns[0] && opts.columns[0].mobileWidth) || 150;
        if (opts.rowActions) {
            cols.push({
                colId: 'actions', headerName: '', cellRenderer: actionsRenderer, sortable: false, filter: false,
                resizable: false, suppressMovable: true, lockVisible: true, width: 120, minWidth: 56,
                cellClass: 'grid-actions-cell', headerClass: 'grid-actions-head', suppressHeaderMenuButton: true,
                suppressNavigable: false, suppressSizeToFit: true
            });
        }

        function saveState() {
            if (!key || restoring || !ctrl.api) return;
            var state = ctrl.api.getColumnState().map(function (s) {
                var o = Object.assign({}, s);
                delete o.pinned;            // pinning is responsive, never persisted
                if (o.colId === firstColId && isMobile()) { delete o.width; delete o.flex; }
                return o;
            });
            store(key, { columns: state, pageSize: ctrl.api.paginationGetPageSize() });
        }
        function applyResponsive() {
            if (!ctrl.api) return;
            var m = isMobile();
            var st = [];
            // pinned columns wider than the viewport get unpinned by the grid, so on phones the
            // first column drops its flex and takes a fixed width in the same state change
            if (firstColId) {
                var first = { colId: firstColId, pinned: m ? 'left' : null };
                if (m) { first.flex = null; first.width = mobileWidth; }
                else if (wasMobile) {
                    var saved = ((key && store(key)) || {}).columns || [];
                    var sv = saved.filter(function (x) { return x.colId === firstColId; })[0];
                    first.flex = sv ? sv.flex : (cols[0].flex || null);
                    first.width = sv && sv.width ? sv.width : (cols[0].width || 200);
                }
                st.push(first);
            }
            st.push({ colId: 'ag-Grid-SelectionColumn', pinned: m ? 'left' : null });
            // actions stay pinned right at every width so they never scroll away
            if (opts.rowActions) st.push({ colId: 'actions', pinned: 'right', width: actionsWidth() });
            restoring = true;
            ctrl.api.applyColumnState({ state: st });
            restoring = false;
            wasMobile = m;
            ctrl.api.refreshCells({ columns: ['actions'], force: true });
        }
        function restoreState() {
            var saved = key ? store(key) : null;
            if (saved && saved.columns && saved.columns.length) {
                restoring = true;
                ctrl.api.applyColumnState({ state: saved.columns.filter(function (s) { return s.colId !== 'actions'; }), applyOrder: true });
                restoring = false;
            }
            if (saved && saved.pageSize) ctrl.api.setGridOption('paginationPageSize', saved.pageSize);
            applyResponsive();
        }

        function updateCount() {
            if (!tb.count || !ctrl.api) return;
            var shown = ctrl.api.getDisplayedRowCount();
            var word = totalRows === 1 ? noun[0] : noun[1];
            $(tb.count).textContent = (shown === totalRows ? formatNumber(totalRows) : formatNumber(shown) + ' of ' + formatNumber(totalRows)) + ' ' + word;
        }

        /* bulk bar */
        var busy = false;
        function selectedRows() { return ctrl.api ? ctrl.api.getSelectedRows() : []; }
        function renderBulkBar() {
            if (!bulkBar || busy) return;
            var rows = selectedRows();
            bulkBar.hidden = rows.length === 0;
            host.classList.toggle('has-selection', rows.length > 0);
            if (!rows.length) { bulkBar.innerHTML = ''; return; }
            var h = '<span class="bulk-count"><strong>' + rows.length + '</strong> selected</span>';
            opts.bulkActions.forEach(function (a, i) {
                h += '<span class="bulk-sep" aria-hidden="true">·</span>';
                if (a.menu) {
                    h += '<div class="dropdown d-inline-block"><button type="button" class="bulk-btn dropdown-toggle' + (a.tone === 'danger' ? ' danger' : '') + '" data-bs-toggle="dropdown" aria-expanded="false">' +
                        (a.icon ? '<i class="fas ' + escapeHtml(a.icon) + '"></i>' : '') + escapeHtml(a.label) + '</button><ul class="dropdown-menu">';
                    a.menu.forEach(function (m, j) {
                        h += '<li><button type="button" class="dropdown-item" data-bulk="' + i + '" data-bulk-value="' + j + '">' + escapeHtml(m.label) + '</button></li>';
                    });
                    h += '</ul></div>';
                } else {
                    h += '<button type="button" class="bulk-btn' + (a.tone === 'danger' ? ' danger' : '') + '" data-bulk="' + i + '">' +
                        (a.icon ? '<i class="fas ' + escapeHtml(a.icon) + '"></i>' : '') + escapeHtml(a.label) + '</button>';
                }
            });
            h += '<span class="bulk-sep" aria-hidden="true">·</span><button type="button" class="bulk-btn bulk-clear" data-bulk-clear>Clear</button>';
            h += '<span class="bulk-progress" aria-live="polite"></span>';
            bulkBar.innerHTML = h;
        }
        if (bulkBar) {
            bulkBar.addEventListener('click', function (e) {
                if (busy) return;
                if (e.target.closest('[data-bulk-clear]')) { ctrl.api.deselectAll(); return; }
                var b = e.target.closest('[data-bulk]');
                if (!b) return;
                var a = opts.bulkActions[+b.getAttribute('data-bulk')];
                var vIdx = b.getAttribute('data-bulk-value');
                var value = vIdx != null ? a.menu[+vIdx].value : undefined;
                var rows = selectedRows();
                var go = function () { runBulk(a, rows, value); };
                if (a.confirm) {
                    var msg = typeof a.confirm === 'function' ? a.confirm(rows, value) : a.confirm;
                    showConfirm(msg, go, a.confirmLabel || a.label);
                } else go();
            });
        }
        function runBulk(a, rows, value) {
            busy = true;
            var prog = bulkBar.querySelector('.bulk-progress');
            bulkBar.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            bulkBar.classList.add('is-busy');
            var ok = 0, failed = 0, i = 0;
            function step() {
                if (i >= rows.length) return finish();
                var row = rows[i++];
                if (prog) prog.textContent = (a.progress || 'Updating') + ' ' + i + ' of ' + rows.length + '…';
                Promise.resolve().then(function () { return a.perRow(row, value); })
                    .then(function (res) { if (res === false) failed++; else ok++; }, function () { failed++; })
                    .then(step);
            }
            function finish() {
                busy = false;
                bulkBar.classList.remove('is-busy');
                var msg = a.done ? a.done(ok, failed, value) : (ok + ' ' + (ok === 1 ? noun[0] : noun[1]) + ' updated');
                showNotification(msg, failed && !ok ? 'error' : (failed ? 'warning' : 'success'));
                ctrl.api.deselectAll();
                renderBulkBar();
                if (opts.onBulkDone) opts.onBulkDone();
            }
            step();
        }

        var gridOptions = {
            theme: adminTheme(),
            columnDefs: cols,
            rowData: opts.rows || null,
            loading: !opts.rows,
            defaultColDef: {
                sortable: true, resizable: true, filter: true, floatingFilter: false,
                suppressHeaderFilterButton: false, minWidth: 80, cellRenderer: null
            },
            getRowId: function (p) { return String(p.data.id); },
            animateRows: true,
            pagination: true,
            rowHeight: opts.rowHeight || 48,
            paginationPageSize: 25,
            paginationPageSizeSelector: [10, 25, 50, 100],
            domLayout: 'autoHeight',
            suppressDragLeaveHidesColumns: true,
            enableCellTextSelection: false,
            popupParent: document.body,
            tooltipShowDelay: 400,
            overlayNoRowsTemplate: '<div class="grid-empty"><span class="empty-state-icon"><i class="fas fa-inbox"></i></span><div>' + escapeHtml(opts.emptyText || 'Nothing to show.') + '</div></div>',
            // 36.2's transient "Exporting…" overlay can stay up after a small export and block the grid; exports here are instant
            suppressOverlays: ['exporting'],
            localeText: { noMatchingRows: opts.emptyText || 'Nothing matches these filters.', noRowsToShow: opts.emptyText || 'Nothing to show.' },
            overlayLoadingTemplate: '<div class="grid-loading"><span class="spinner-border spinner-border-sm text-primary" role="status"></span> Loading…</div>',
            rowSelection: opts.selectable ? {
                mode: 'multiRow', checkboxes: true, headerCheckbox: true, selectAll: 'currentPage',
                enableClickSelection: false
            } : undefined,
            selectionColumnDef: opts.selectable ? { width: 52, minWidth: 52, maxWidth: 52, resizable: false, suppressHeaderMenuButton: true, pinned: null } : undefined,
            onGridReady: function () { restoreState(); },
            onFirstDataRendered: function () { applyResponsive(); },
            onRowDataUpdated: function () { totalRows = ctrl.rows ? ctrl.rows.length : 0; updateCount(); if (ctrl.api) ctrl.api.applyColumnState({ state: [{ colId: 'actions', width: actionsWidth() }] }); },
            onModelUpdated: updateCount,
            onFilterChanged: function () { updateCount(); if (opts.onFilterChanged) opts.onFilterChanged(ctrl.api.getFilterModel()); },
            onColumnResized: function (e) { if (e.finished && e.source !== 'api' && e.source !== 'flex') saveState(); },
            onColumnMoved: function (e) { if (e.finished !== false) saveState(); },
            onSortChanged: saveState,
            onColumnVisible: saveState,
            onPaginationChanged: function (e) { if (e.newPageSize) saveState(); },
            onSelectionChanged: function () {
                renderBulkBar();
                if (opts.onSelectionChange) opts.onSelectionChange(selectedRows());
            },
            onRowClicked: function (e) {
                var t = e.event && e.event.target;
                if (!t || !opts.onRowOpen) return;
                if (t.closest('.icon-btn, button, a, input, label, .ag-selection-checkbox, .ag-checkbox, [col-id="ag-Grid-SelectionColumn"], [col-id="actions"]')) return;
                opts.onRowOpen(e.data);
            },
            onCellKeyDown: function (e) {
                if (!e.event || e.event.key !== 'Enter' || !opts.onRowOpen) return;
                var id = e.column && e.column.getColId();
                if (id === 'ag-Grid-SelectionColumn') return;
                if (id === 'actions' && e.event.target.closest && e.event.target.closest('.icon-btn')) return;
                e.event.preventDefault();
                opts.onRowOpen(e.data);
            },
            getRowClass: opts.getRowClass,
            processCellForClipboard: null
        };
        ctrl.rows = opts.rows || [];
        ctrl.api = agGrid.createGrid(host, gridOptions);
        totalRows = ctrl.rows.length;

        /* toolbar hookups */
        if (tb.search) {
            var t;
            $(tb.search).addEventListener('input', function () {
                var v = this.value;
                clearTimeout(t);
                t = setTimeout(function () { ctrl.api.setGridOption('quickFilterText', v); }, 120);
            });
        }
        if (tb.exportBtn) $(tb.exportBtn).addEventListener('click', function () { ctrl.exportCsv(); });
        if (tb.resetBtn) $(tb.resetBtn).addEventListener('click', function () { ctrl.resetColumns(); });

        var mq = window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)');
        var onMq = function () { closeMenus(); applyResponsive(); };
        if (mq.addEventListener) mq.addEventListener('change', onMq); else mq.addListener(onMq);

        ctrl.setRows = function (rows) {
            ctrl.rows = rows || [];
            totalRows = ctrl.rows.length;
            ctrl.api.setGridOption('rowData', ctrl.rows);
            ctrl.api.setGridOption('loading', false);
            updateCount();
            renderBulkBar();
        };
        ctrl.setLoading = function (on) { ctrl.api.setGridOption('loading', !!on); };
        ctrl.getSelected = selectedRows;
        ctrl.clearSelection = function () { ctrl.api.deselectAll(); };
        ctrl.setColumnFilter = function (colId, model) {
            return Promise.resolve(ctrl.api.setColumnFilterModel(colId, model || null)).then(function () { ctrl.api.onFilterChanged(); });
        };
        ctrl.resetColumns = function () {
            store(key, null);
            restoring = true;
            ctrl.api.resetColumnState();
            ctrl.api.setGridOption('paginationPageSize', 25);
            restoring = false;
            applyResponsive();
            showNotification('Columns reset to default', 'info');
        };
        ctrl.exportCsv = function () {
            var keys = ctrl.api.getAllDisplayedColumns()
                .map(function (c) { return c.getColId(); })
                .filter(function (id) { return id !== 'actions' && id !== 'ag-Grid-SelectionColumn'; });
            ctrl.api.exportDataAsCsv({
                fileName: 'techzone-' + (opts.exportName || 'export') + '-' + todayStamp() + '.csv',
                columnKeys: keys,
                processCellCallback: function (p) {
                    var cd = p.column.getColDef();
                    if (cd.csvValue) return cd.csvValue(p.node.data);
                    return p.value == null ? '' : p.value;
                }
            });
        };
        ctrl.refresh = function () { ctrl.api.refreshCells({ force: true }); };
        /** keep a <select> in step with a column filter: choosing applies the model, clearing the filter in the header resets the select */
        ctrl.bindSelect = function (select, colId, toModel) {
            select = $(select);
            select.addEventListener('change', function () {
                ctrl.setColumnFilter(colId, select.value ? toModel(select.value) : null);
            });
            var prev = opts.onFilterChanged;
            opts.onFilterChanged = function (fm) {
                if (prev) prev(fm);
                if (select.value && !fm[colId]) select.value = '';
            };
        };
        return ctrl;
    };

    window.adminGridRenderers = R;
})();
