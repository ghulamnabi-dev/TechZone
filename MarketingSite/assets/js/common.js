/**
 * Common utility functions
 * Shared across the application
 */

/**
 * Show side notification (toast)
 * @param {string} message - The message to display
 * @param {string} type - Type of notification: 'success', 'error', 'info', 'warning'
 */
function showNotification(message, type) {
    if (!type) type = 'success';
    
    // Create notification element
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    
    // Add icon based on type
    var icon = '';
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle me-2"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            break;
        case 'info':
            icon = '<i class="fas fa-info-circle me-2"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            break;
    }
    
    notification.innerHTML = icon + message;
    
    // Get or create notification container
    var container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        document.body.appendChild(container);
    }
    
    container.appendChild(notification);
    
    // Show notification with animation
    setTimeout(function() {
        notification.classList.add('show');
    }, 10);
    
    // Hide and remove notification after 3 seconds
    setTimeout(function() {
        notification.classList.remove('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}


/* ---------- CSRF: attach the session token to every same-origin write ----------
 * Same helper as AdminPortal/assets/js/common.js: reads <meta name="csrf-token">
 * and adds the X-CSRF-Token header (plus a csrf_token field for FormData /
 * URLSearchParams bodies) to non-GET fetch() calls to this site. */
(function () {
    if (!window.fetch || window.__tzFetchWrapped) return;
    window.__tzFetchWrapped = true;
    var nativeFetch = window.fetch.bind(window);
    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    window.fetch = function (input, init) {
        try {
            init = init || {};
            var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var sameOrigin = !/^https?:\/\//i.test(url) || url.indexOf(location.origin) === 0;
            var t = token();
            if (t && sameOrigin && method !== 'GET' && method !== 'HEAD') {
                var headers = new Headers(init.headers || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', t);
                init.headers = headers;
                if (typeof FormData !== 'undefined' && init.body instanceof FormData && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', t);
                } else if (typeof URLSearchParams !== 'undefined' && init.body instanceof URLSearchParams && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', t);
                }
            }
        } catch (e) { /* never block a request because of the helper */ }
        return nativeFetch(input, init);
    };
})();
