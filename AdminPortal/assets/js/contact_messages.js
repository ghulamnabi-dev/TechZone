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

function getMessageStatusClass(status) {
    const key = (status || '').toLowerCase();
    if (key === 'new') return 'badge-status badge-status-new';
    if (key === 'read') return 'badge-status badge-status-read';
    if (key === 'replied') return 'badge-status badge-status-replied';
    if (key === 'archived') return 'badge-status badge-status-archived';
    return 'badge-status badge-status-read';
}

function loadMessages() {
    const container = document.getElementById('messagesContainer');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    let url = adminUrl('api/contact_messages.php') + `?page=${currentPage}&limit=10`;
    if (currentStatus) {
        url += `&status=${currentStatus}`;
    }
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessages(data.messages);
            displayPagination(data.total, data.page, data.limit);
        } else {
            container.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed to load messages') + '</div>';
        }
    })
    .catch(error => {
        container.innerHTML = '<div class="alert alert-danger">Error loading messages</div>';
    });
}

function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    
    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h5 class="empty-state-title mb-1">No messages yet</h5>
                <p class="empty-state-text mb-0">When customers use the contact form, their messages will show up here for follow-up.</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
    
    messages.forEach(msg => {
        const statusClass = getMessageStatusClass(msg.status);
        
        const statusText = msg.status.charAt(0).toUpperCase() + msg.status.slice(1);
        const date = new Date(msg.created_at).toLocaleDateString() + ' ' + new Date(msg.created_at).toLocaleTimeString();
        
        html += `
            <tr>
                <td>${escapeHtml(msg.name)}</td>
                <td>${escapeHtml(msg.email)}</td>
                <td>${escapeHtml(msg.subject)}</td>
                <td><span class="${statusClass}">${statusText}</span></td>
                <td>${date}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewMessage(${msg.id})">View</button>
                    ${msg.status !== 'read' ? `<button class="btn btn-sm btn-outline-success me-1" onclick="updateStatus(${msg.id}, 'read')">Mark Read</button>` : ''}
                    ${msg.status !== 'archived' ? `<button class="btn btn-sm btn-outline-secondary me-1" onclick="updateStatus(${msg.id}, 'archived')">Archive</button>` : ''}
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteMessage(${msg.id})">Delete</button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayPagination(total, page, limit) {
    const pagination = document.getElementById('pagination');
    const totalPages = Math.ceil(total / limit);
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<li class="page-item ${page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changePage(${page - 1}); return false;">Previous</a></li>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
            html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a></li>`;
        } else if (i === page - 3 || i === page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changePage(${page + 1}); return false;">Next</a></li>`;
    
    pagination.innerHTML = html;
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
                
                document.getElementById('messageDetails').innerHTML = `
                    <div class="mb-3">
                        <strong>Name:</strong> ${escapeHtml(message.name)}
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> <a href="mailto:${escapeHtml(message.email)}">${escapeHtml(message.email)}</a>
                    </div>
                    <div class="mb-3">
                        <strong>Subject:</strong> ${escapeHtml(message.subject)}
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> <span class="${statusClass}">${message.status.charAt(0).toUpperCase() + message.status.slice(1)}</span>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> ${date}
                    </div>
                    <div class="mb-3">
                        <strong>Message:</strong>
                        <div class="border p-3 mt-2" style="white-space: pre-wrap;">${escapeHtml(message.message)}</div>
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('messageModal'));
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
            showNotification('Status updated successfully', 'success');
            if (reload) {
                loadMessages();
            }
            if (status === 'replied') {
                bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
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
    if (!confirm('Are you sure you want to delete this message?')) {
        return;
    }
    
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

