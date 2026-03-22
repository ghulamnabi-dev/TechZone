function formatCurrency(amount) {
    return 'Rs. ' + amount.toLocaleString();
}

function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString();
}

function showNotification(message, type) {
    if (!type) type = 'success';
    
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.innerHTML = message;
    
    var container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        document.body.appendChild(container);
    }
    container.appendChild(notification);
    
    setTimeout(function() {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(function() {
        notification.classList.remove('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}

function showConfirm(message, callback) {
    var overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = '<div class="confirm-dialog"><div class="confirm-message">' + message + '</div><div class="confirm-buttons"><button class="btn btn-secondary" id="confirm-cancel">Cancel</button><button class="btn btn-danger" id="confirm-ok">OK</button></div></div>';
    
    document.body.appendChild(overlay);
    
    setTimeout(function() {
        overlay.classList.add('show');
    }, 10);
    
    document.getElementById('confirm-cancel').onclick = function() {
        overlay.classList.remove('show');
        setTimeout(function() {
            overlay.remove();
        }, 300);
    };
    
    document.getElementById('confirm-ok').onclick = function() {
        overlay.classList.remove('show');
        setTimeout(function() {
            overlay.remove();
            if (callback) callback(true);
        }, 300);
    };
    
    overlay.onclick = function(e) {
        if (e.target == overlay) {
            overlay.classList.remove('show');
            setTimeout(function() {
                overlay.remove();
            }, 300);
        }
    };
}

// AGGRESSIVE Fix modal z-index issues globally for Bootstrap 5
(function() {
    // Inject CSS to override Bootstrap's inline styles
    var style = document.createElement('style');
    style.textContent = `
        .modal-backdrop { z-index: 1040 !important; }
        .modal-backdrop.show { z-index: 1040 !important; }
        .modal-backdrop.showing { z-index: 1040 !important; }
        .modal { z-index: 1055 !important; }
        .modal.show { z-index: 1055 !important; }
        .modal.showing { z-index: 1055 !important; }
        #variationFormModal { z-index: 1060 !important; }
        #variationFormModal.show { z-index: 1060 !important; }
        #variationFormModal.showing { z-index: 1060 !important; }
    `;
    document.head.appendChild(style);
    
    // Continuous fix function that runs repeatedly
    function forceFixAllModals() {
        // Fix all modals
        var modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            if (modal.classList.contains('show') || modal.classList.contains('showing')) {
                var modalZIndex = '1055';
                if (modal.id === 'variationFormModal') {
                    modalZIndex = '1060';
                }
                // Force z-index with setProperty for higher priority
                modal.style.setProperty('z-index', modalZIndex, 'important');
            }
        });
        
        // Fix all backdrops - MUST be below modals
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(backdrop) {
            // Check if variation modal is open
            var variationModal = document.getElementById('variationFormModal');
            var backdropZIndex = '1040';
            if (variationModal && variationModal.classList.contains('show')) {
                backdropZIndex = '1055';
            }
            // Force z-index with setProperty for higher priority
            backdrop.style.setProperty('z-index', backdropZIndex, 'important');
        });
    }
    
    // Run fix immediately
    forceFixAllModals();
    
    // Run fix continuously every 50ms when modals are open
    var fixInterval = setInterval(function() {
        if (document.querySelector('.modal.show') || document.querySelector('.modal-backdrop.show')) {
            forceFixAllModals();
        }
    }, 50);
    
    // Watch for backdrop additions using MutationObserver
    var backdropObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.classList && node.classList.contains('modal-backdrop')) {
                    forceFixAllModals();
                }
            });
        });
    });
    
    // Start observing body for backdrop additions
    if (document.body) {
        backdropObserver.observe(document.body, {
            childList: true,
            subtree: false
        });
    }
    
    // Fix modals when Bootstrap shows them
    document.addEventListener('show.bs.modal', function(e) {
        setTimeout(forceFixAllModals, 0);
    });
    
    // Also fix when modal is shown (for programmatic shows)
    document.addEventListener('shown.bs.modal', function(e) {
        setTimeout(forceFixAllModals, 0);
    });
    
    // Fix on any DOM changes
    var domObserver = new MutationObserver(function() {
        if (document.querySelector('.modal.show') || document.querySelector('.modal-backdrop.show')) {
            forceFixAllModals();
        }
    });
    
    if (document.body) {
        domObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }
})();

