let currentMessageId = null;
let messages = [];
let messagesGrid = null;

/** PUT a message's status (existing endpoint). Resolves true/false. */
function putMessageStatus(id, status) {
    return fetch(adminUrl('api/contact_messages.php'), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id, 10), status: status })
    }).then(r => r.json()).then(d => !!d.success);
}
function replyHref(msg) {
    return 'mailto:' + encodeURIComponent(msg.email || '').replace(/%40/g, '@') + '?subject=' + encodeURIComponent('Re: ' + (msg.subject || 'Your message to TechZone'));
}

document.addEventListener('DOMContentLoaded', function() {
    messagesGrid = createAdminGrid(document.getElementById('messagesContainer'), {
        storageKey: 'tz.grid.messages.v1',
        noun: ['message', 'messages'],
        emptyText: 'No messages match these filters.',
        exportName: 'messages',
        toolbar: { search: '#messageSearch', count: '#messageCount', exportBtn: '#messagesExport', resetBtn: '#messagesReset' },
        selectable: true,
        getRowClass: p => (p.data && p.data.status === 'new' ? 'row-unread' : ''),
        columns: [
            { field: 'name', headerName: 'From', kind: 'stack', width: 230, mobileWidth: 140,
              cellRendererParams: { sub: m => m.email, strong: m => m.status === 'new' } },
            { field: 'email', headerName: 'Email', kind: 'muted', width: 220, hide: true },
            { field: 'subject', headerName: 'Subject', kind: 'stack', flex: 1, minWidth: 280,
              cellRendererParams: { sub: m => m.message || '', strong: m => m.status === 'new' },
              getQuickFilterText: p => (p.data.subject || '') + ' ' + (p.data.message || '') },
            { field: 'created_at', headerName: 'Received', kind: 'datetime', width: 170, sort: 'desc' },
            { field: 'status', headerName: 'Status', kind: 'status', width: 120 }
        ],
        rowActions: msg => {
            const id = parseInt(msg.id, 10);
            const a = [{ icon: 'fa-reply', label: 'Reply by email', href: replyHref(msg) }];
            if (msg.status === 'new') a.push({ icon: 'fa-envelope-open', label: 'Mark read', attrs: `data-msg-action="read" data-msg-id="${id}"` });
            if (msg.status !== 'archived') a.push({ icon: 'fa-box-archive', label: 'Archive', attrs: `data-msg-action="archived" data-msg-id="${id}"` });
            a.push({ icon: 'fa-trash', label: 'Delete', attrs: `data-msg-action="delete" data-msg-id="${id}"`, tone: 'danger' });
            return a;
        },
        onRowOpen: msg => viewMessage(parseInt(msg.id, 10)),
        bulkActions: [
            { label: 'Mark read', icon: 'fa-envelope-open', progress: 'Updating',
              perRow: m => (m.status === 'new' ? putMessageStatus(m.id, 'read') : true),
              done: (ok, failed) => ok + ' ' + (ok === 1 ? 'message' : 'messages') + ' marked read' + (failed ? ', ' + failed + ' failed' : '') },
            { label: 'Archive', icon: 'fa-box-archive', progress: 'Archiving',
              perRow: m => (m.status === 'archived' ? true : putMessageStatus(m.id, 'archived')),
              done: (ok, failed) => ok + ' ' + (ok === 1 ? 'message' : 'messages') + ' archived' + (failed ? ', ' + failed + ' failed' : '') }
        ],
        onBulkDone: () => loadMessages(),
        onFilterChanged: fm => {
            const v = fm.status ? String(fm.status.filter || '') : '';
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.status === v));
        }
    });

    // status tabs drive the grid's filter model
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const v = this.dataset.status;
            messagesGrid.setColumnFilter('status', v ? { filterType: 'text', type: 'equals', filter: v } : null);
        });
    });

    document.getElementById('messagesContainer').addEventListener('click', function(e) {
        const b = e.target.closest('[data-msg-action]');
        if (!b) return;
        const id = parseInt(b.getAttribute('data-msg-id'), 10);
        const action = b.getAttribute('data-msg-action');
        if (action === 'delete') deleteMessage(id);
        else updateStatus(id, action);
    });

    document.getElementById('markRepliedBtn').addEventListener('click', function() {
        if (currentMessageId) updateStatus(currentMessageId, 'replied');
    });

    loadMessages();
});

function loadMessages() {
    // the API pages (limit up to 1000); the grid pages, sorts and filters client-side
    return fetch(adminUrl('api/contact_messages.php') + '?page=1&limit=1000')
    .then(response => response.json())
    .then(data => {
        messages = data.success ? (data.messages || []) : [];
        messagesGrid.setRows(messages);
        if (!data.success) showNotification(data.message || 'Failed to load messages', 'error');
    })
    .catch(() => {
        messagesGrid.setRows([]);
        showNotification('Could not load messages', 'error');
    });
}

function viewMessage(id) {
    currentMessageId = id;
    const message = messages.find(m => m.id == id);
    if (!message) return;
    document.getElementById('messageModalTitle').textContent = message.subject || 'Message';
    document.getElementById('messageDetails').innerHTML = `
        <dl class="kv mb-3">
            <dt>From</dt><dd>${escapeHtml(message.name)}</dd>
            <dt>Email</dt><dd><a href="${escapeHtml(replyHref(message))}">${escapeHtml(message.email)}</a></dd>
            <dt>Received</dt><dd>${escapeHtml(formatDateTime(message.created_at))}</dd>
            <dt>Status</dt><dd>${statusPill(message.status)}</dd>
        </dl>
        <div class="quote">${escapeHtml(message.message)}</div>
    `;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
    // opening a new message marks it read
    if (message.status === 'new') updateStatus(id, 'read', false);
}

function updateStatus(id, status, reload = true) {
    fetch(adminUrl('api/contact_messages.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id, status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (reload) showNotification('Message marked ' + status, 'success');
            loadMessages();
            if (status === 'replied') {
                const inst = bootstrap.Modal.getInstance(document.getElementById('messageModal'));
                if (inst) inst.hide();
            }
        } else {
            showNotification(data.message || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        showNotification('Error updating status', 'error');
    });
}

function deleteMessage(id) {
    showConfirm('Delete this message? This cannot be undone.', function() { doDeleteMessage(id); }, 'Delete');
}

function doDeleteMessage(id) {
    fetch(adminUrl('api/contact_messages.php'), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Message deleted successfully', 'success');
            loadMessages();
        } else {
            showNotification(data.message || 'Failed to delete message', 'error');
        }
    })
    .catch(error => {
        showNotification('Error deleting message', 'error');
    });
}
