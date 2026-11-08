let currentPage = 1;
let currentStatus = '';
let currentMessageId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadMessages();
    
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.dataset.status;
            currentPage = 1;
            loadMessages();
        });
    });
    
    // Mark as replied button
    document.getElementById('markRepliedBtn').addEventListener('click', function() {
        if (currentMessageId) {
            updateStatus(currentMessageId, 'replied');
        }
    });
});

function loadMessages() {
    const container = document.getElementById('messagesContainer');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    let url = adminUrl('api/contact_messages.php') + `?page=${currentPage}&limit=25`;
    if (currentStatus) {
        url += `&status=${encodeURIComponent(currentStatus)}`;
    }
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessages(data.messages);
            displayPagination(data.total, data.page, data.limit);
        } else {
            container.innerHTML = '<div class="empty-state">' + escapeHtml(data.message || 'Failed to load messages') + '</div>';
        }
    })
    .catch(error => {
        container.innerHTML = '<div class="empty-state">Could not load messages</div>';
    });
}

function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');

    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <h5 class="empty-state-title mb-1">No messages here</h5>
                <p class="empty-state-text mb-0">Messages sent through the storefront contact form show up here.</p>
            </div>`;
        return;
    }

    let html = '<div class="table-scroll"><table class="table table-hover"><thead><tr><th>From</th><th>Subject</th><th>Received</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>';
    messages.forEach(msg => {
        const id = parseInt(msg.id, 10);
        const unread = msg.status === 'new';
        html += `
            <tr>
                <td class="nowrap"><div class="cell-main" style="font-weight:${unread ? 600 : 500}">${escapeHtml(msg.name)}</div><div class="cell-sub">${escapeHtml(msg.email)}</div></td>
                <td style="min-width:260px;max-width:460px"><div class="cell-clip" style="max-width:none;${unread ? 'font-weight:600' : ''}">${escapeHtml(msg.subject)}</div><div class="cell-sub cell-clip" style="max-width:none">${escapeHtml(msg.message || '')}</div></td>
                <td class="nowrap ink-2">${formatDateTime(msg.created_at)}</td>
                <td>${statusPill(msg.status)}</td>
                <td class="text-end"><div class="actions">
                    ${iconButton('fa-eye', 'Open', `onclick="viewMessage(${id})"`)}
                    ${msg.status !== 'read' && msg.status !== 'replied' ? iconButton('fa-envelope-open', 'Mark read', `onclick="updateStatus(${id}, 'read')"`) : ''}
                    ${msg.status !== 'archived' ? iconButton('fa-box-archive', 'Archive', `onclick="updateStatus(${id}, 'archived')"`) : ''}
                    ${iconButton('fa-trash', 'Delete', `onclick="deleteMessage(${id})"`, 'danger')}
                </div></td>
            </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayPagination(total, page, limit) {
    total = Number(total) || 0;
    document.getElementById('messageCount').textContent = total;
    renderPager(document.getElementById('pagination'), total, Number(page) || 1, Number(limit) || 25, changePage, 'messages');
}

function changePage(page) {
    currentPage = page;
    loadMessages();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function viewMessage(id) {
    currentMessageId = id;
    
    // Fetch all messages to find the one we need (or we could create a GET by ID endpoint)
    fetch(adminUrl('api/contact_messages.php?limit=1000'))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = data.messages.find(m => m.id == id);
            if (message) {
                const date = new Date(message.created_at).toLocaleDateString() + ' ' + new Date(message.created_at).toLocaleTimeString();
                const statusClass = getMessageStatusClass(message.status);
                
                document.getElementById('messageModalTitle').textContent = message.subject || 'Message';
                document.getElementById('messageDetails').innerHTML = `
                    <dl class="kv mb-3">
                        <dt>From</dt><dd>${escapeHtml(message.name)}</dd>
                        <dt>Email</dt><dd><a href="mailto:${escapeHtml(message.email)}">${escapeHtml(message.email)}</a></dd>
                        <dt>Received</dt><dd>${formatDateTime(message.created_at)}</dd>
                        <dt>Status</dt><dd>${statusPill(message.status)}</dd>
                    </dl>
                    <div class="quote">${escapeHtml(message.message)}</div>
                `;

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal'));
                modal.show();
                
                // Mark as read if status is new
                if (message.status === 'new') {
                    updateStatus(id, 'read', false);
                }
            }
        }
    });
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
