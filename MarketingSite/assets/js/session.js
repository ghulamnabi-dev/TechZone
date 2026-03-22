/**
 * Session Management Module
 * Handles frontend session state and UI updates
 */
const SessionManager = {
    // Storage keys
    STORAGE_KEY: 'customer_session',
    
    // Current session data
    sessionData: null,

    _base: function () {
        return (typeof window.MARKETING_SITE_BASE !== 'undefined' && window.MARKETING_SITE_BASE) ? window.MARKETING_SITE_BASE : '';
    },

    _url: function (rel) {
        if (typeof window.marketingUrl === 'function') {
            return window.marketingUrl(rel);
        }
        rel = String(rel || '').replace(/^\/+/, '');
        var b = this._base();
        return (b ? b + '/' : '') + rel;
    },
    
    /**
     * Initialize session manager
     */
    init: function() {
        this.loadSessionFromStorage();
        this.checkSessionStatus();
        this.updateUI();
        
        // Check session periodically (every 5 minutes)
        setInterval(() => {
            this.checkSessionStatus();
        }, 5 * 60 * 1000);
    },
    
    /**
     * Load session data from localStorage
     */
    loadSessionFromStorage: function() {
        try {
            const stored = localStorage.getItem(this.STORAGE_KEY);
            if (stored) {
                this.sessionData = JSON.parse(stored);
            }
        } catch (e) {
            console.error('Error loading session from storage:', e);
            this.clearSession();
        }
    },
    
    /**
     * Save session data to localStorage
     */
    saveSessionToStorage: function(sessionData) {
        try {
            this.sessionData = sessionData;
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(sessionData));
        } catch (e) {
            console.error('Error saving session to storage:', e);
        }
    },
    
    /**
     * Clear session data from localStorage
     */
    clearSession: function() {
        this.sessionData = null;
        try {
            localStorage.removeItem(this.STORAGE_KEY);
        } catch (e) {
            console.error('Error clearing session from storage:', e);
        }
    },
    
    /**
     * Check session status with server
     */
    checkSessionStatus: function() {
        fetch(this._url('api/check_session.php'))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.logged_in) {
                        // User is logged in
                        this.saveSessionToStorage({
                            logged_in: true,
                            customer: data.customer
                        });
                    } else {
                        // User is not logged in
                        this.clearSession();
                    }
                    this.updateUI();
                }
            })
            .catch(error => {
                console.error('Error checking session status:', error);
            });
    },
    
    /**
     * Set session after login/signup
     */
    setSession: function(customerData) {
        this.saveSessionToStorage({
            logged_in: true,
            customer: customerData
        });
        this.updateUI();
    },
    
    /**
     * Check if user is logged in
     */
    isLoggedIn: function() {
        return this.sessionData && this.sessionData.logged_in === true;
    },
    
    /**
     * Get current customer data
     */
    getCustomer: function() {
        if (this.isLoggedIn()) {
            return this.sessionData.customer;
        }
        return null;
    },
    
    /**
     * Logout - clear session and redirect
     */
    logout: function() {
        // Clear frontend session first
        this.clearSession();
        
        // Call logout endpoint to clear server session
        fetch(this._url('auth/logout.php'), {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(() => {
                // Redirect to home page
                window.location.href = this._url('index.php');
            })
            .catch(error => {
                console.error('Error during logout:', error);
                // Still redirect even if fetch fails
                window.location.href = this._url('index.php');
            });
    },
    
    /**
     * Update UI based on session state
     */
    updateUI: function() {
        const isLoggedIn = this.isLoggedIn();
        const customer = this.getCustomer();
        
        // Find navigation elements
        const navItems = document.querySelectorAll('.navbar-nav li');
        let loginLi = null;
        let signupLi = null;
        let userDropdownLi = null;
        let userDropdown = null;
        
        navItems.forEach(li => {
            const loginLink = li.querySelector('a[href*="login.php"]');
            const signupLink = li.querySelector('a[href*="signup.php"]');
            const dropdown = li.querySelector('#navUserDropdown');
            
            if (loginLink) loginLi = li;
            if (signupLink) signupLi = li;
            if (dropdown) {
                userDropdownLi = li;
                userDropdown = dropdown;
            }
        });
        
        if (isLoggedIn && customer) {
            // Hide login/signup links
            if (loginLi) loginLi.style.display = 'none';
            if (signupLi) signupLi.style.display = 'none';
            
            // Show and update user dropdown
            if (userDropdownLi) {
                userDropdownLi.style.display = 'block';
                if (userDropdown) {
                    // Update dropdown text with customer name
                    userDropdown.innerHTML = '<i class="fas fa-user-circle"></i> <span class="nav-username d-none d-xl-inline">' + this.escapeHtml(customer.name) + '</span>';
                }
            }
            
            // Update cart badge if needed (cart count is handled server-side)
            this.updateCartBadge();
        } else {
            // Show login/signup links
            if (loginLi) loginLi.style.display = 'block';
            if (signupLi) signupLi.style.display = 'block';
            
            // Hide user dropdown
            if (userDropdownLi) {
                userDropdownLi.style.display = 'none';
            }
        }
        
        // Update protected content
        this.updateProtectedContent();
    },
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Update cart badge (if cart count API exists)
     */
    updateCartBadge: function() {
        // Cart badge is managed server-side via PHP session
        // This is a placeholder for future enhancement
    },
    
    /**
     * Update protected content based on login status
     */
    updateProtectedContent: function() {
        const isLoggedIn = this.isLoggedIn();
        
        // Update checkout buttons
        const checkoutButtons = document.querySelectorAll('.checkout-button, a[href*="checkout"]');
        checkoutButtons.forEach(btn => {
            if (!isLoggedIn && btn.href && btn.href.indexOf('checkout') !== -1) {
                btn.href = this._url('auth/login.php?redirect=checkout');
                if (btn.textContent.includes('Checkout')) {
                    btn.textContent = 'Login to Checkout';
                }
            }
        });
        
        // Update "Login to Checkout" buttons
        const loginToCheckoutButtons = document.querySelectorAll('a[href*="login.php"][href*="redirect=checkout"]');
        loginToCheckoutButtons.forEach(btn => {
            if (isLoggedIn) {
                btn.href = this._url('shop/checkout.php');
                btn.textContent = 'Proceed to Checkout';
            }
        });
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        SessionManager.init();
    });
} else {
    SessionManager.init();
}
