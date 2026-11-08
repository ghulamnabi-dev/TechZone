-- =============================================================================
-- TechZone e-commerce -- CANONICAL DATABASE SCHEMA
-- =============================================================================
-- This file is the single source of truth for the TechZone database
-- (MarketingSite storefront + AdminPortal + core/).
--
-- It was reconstructed from every SQL statement in the PHP code base
-- (SELECT / INSERT / UPDATE / DELETE / ad-hoc CREATE / ALTER). Every column the
-- code reads or writes is defined here, including the tables that some pages
-- create on the fly (customer_password_resets, admin_password_resets,
-- promotions, product_variations) and the columns that are added at runtime
-- (customers.password, order_items.size/color, product_variations.is_default,
-- products.is_active), so those runtime fallbacks become no-ops.
--
-- Idempotent: safe to run repeatedly (CREATE TABLE IF NOT EXISTS).
-- Usage:   mysql -u <user> -p techzone_db < database/schema.sql
-- Charset: utf8mb4 / utf8mb4_unicode_ci, engine InnoDB.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Admin users (AdminPortal login: email + password)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    username    VARCHAR(50)  NOT NULL,
    email       VARCHAR(150) NOT NULL,
    phone       VARCHAR(30)  DEFAULT NULL,
    password    VARCHAR(255) NOT NULL,              -- password_hash()
    role        ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff',   -- core/permissions.php
    is_active   TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = cannot sign in
    last_login_at DATETIME DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Storefront customers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    password    VARCHAR(255) DEFAULT NULL,          -- password_hash()
    phone       VARCHAR(30)  DEFAULT NULL,
    city        VARCHAR(100) DEFAULT NULL,
    address     TEXT         DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_city (city),
    KEY idx_customers_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Product categories (image = bare filename inside AdminPortal/uploads/)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description TEXT         DEFAULT NULL,
    image       VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Products (imagelink = "uploads/<file>" relative to AdminPortal/, or full URL)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    imagelink   VARCHAR(500) DEFAULT NULL,
    inventory   INT NOT NULL DEFAULT 0,
    sold        INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,      -- storefront visibility, kept = (status = 'active')
    status      ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
    sku         VARCHAR(40)  DEFAULT NULL,
    compare_at_price DECIMAL(12,2) DEFAULT NULL,    -- "was" price shown struck through
    cost_price  DECIMAL(12,2) DEFAULT NULL,         -- for margin, never shown to customers
    low_stock_threshold INT NOT NULL DEFAULT 5,
    seo_title   VARCHAR(200) DEFAULT NULL,
    seo_description VARCHAR(320) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_status (status),
    KEY idx_products_category (category_id),
    KEY idx_products_active (is_active),
    KEY idx_products_sold (sold),
    KEY idx_products_created (created_at),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Product variations (size / color), price_modifier added to base price
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED NOT NULL,
    variation_type  ENUM('size','color') NOT NULL,
    variation_value VARCHAR(100) NOT NULL,
    price_modifier  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inventory       INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    display_order   INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_variation (product_id, variation_type, variation_value),
    CONSTRAINT fk_product_variations_product FOREIGN KEY (product_id)
        REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Orders (customer details are snapshotted at checkout)
-- payment_method: 'cash_on_delivery' | 'jazzcash'
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     INT UNSIGNED DEFAULT NULL,
    customer_name   VARCHAR(100) NOT NULL,
    customer_email  VARCHAR(150) NOT NULL,
    customer_phone  VARCHAR(30)  DEFAULT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- sum of order_items.subtotal
    shipping_fee    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- subtotal + shipping_fee - discount_amount
    -- customer-facing status, derived from the staff axes below (core/orders.php::order_derive_status)
    status          ENUM('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    payment_status  ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
    fulfillment_status ENUM('unfulfilled','partial','fulfilled') NOT NULL DEFAULT 'unfulfilled',
    shipping_zone_id INT UNSIGNED DEFAULT NULL,
    payment_method  VARCHAR(50)  NOT NULL DEFAULT 'cash_on_delivery',
    promo_code      VARCHAR(50)  DEFAULT NULL,
    cancel_reason   VARCHAR(255) DEFAULT NULL,
    cancelled_at    DATETIME DEFAULT NULL,
    order_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_status (status),
    KEY idx_orders_payment (payment_status),
    KEY idx_orders_fulfillment (fulfillment_status),
    KEY idx_orders_date (order_date),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Order line items (product_name / price snapshotted)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    price        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantity     INT NOT NULL DEFAULT 1,
    fulfilled_qty INT NOT NULL DEFAULT 0,           -- partial fulfilment (core/orders.php::order_fulfil)
    subtotal     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    size         VARCHAR(100) DEFAULT NULL,
    color        VARCHAR(100) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders(id) ON DELETE CASCADE,
    -- products.php refuses to delete a product that has order_items, so RESTRICT
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)
        REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Product reviews (moderated: Pending -> Approved / Rejected)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id     INT UNSIGNED NOT NULL,
    customer_id    INT UNSIGNED DEFAULT NULL,
    customer_name  VARCHAR(100) NOT NULL,
    rating         TINYINT UNSIGNED NOT NULL,
    comment        TEXT DEFAULT NULL,
    status         ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    admin_response TEXT DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_product_customer (product_id, customer_id),
    KEY idx_reviews_status (status),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id)
        REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Promotions / promo codes (homepage banners + checkout discounts)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS promotions (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title               VARCHAR(200) NOT NULL,
    description         TEXT DEFAULT NULL,
    image_url           VARCHAR(500) DEFAULT NULL,   -- "uploads/<file>" relative to AdminPortal/
    discount_percentage DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    discount_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    promo_code          VARCHAR(50) DEFAULT NULL,
    -- discount engine (core/discounts.php). percent uses discount_percentage, fixed uses discount_amount.
    kind                ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
    applies_to          ENUM('all','category','product') NOT NULL DEFAULT 'all',
    target_id           INT UNSIGNED DEFAULT NULL,     -- categories.id / products.id for applies_to
    min_order           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    usage_limit         INT UNSIGNED DEFAULT NULL,     -- NULL = unlimited
    used_count          INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at           DATETIME DEFAULT NULL,         -- schedule; start_date/end_date kept for banners
    ends_at             DATETIME DEFAULT NULL,
    start_date          DATE DEFAULT NULL,
    end_date            DATE DEFAULT NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    display_order       INT NOT NULL DEFAULT 0,
    link_url            VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_promotions_code (promo_code),
    KEY idx_promotions_active_dates (is_active, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Contact form submissions (status: new | read | replied | archived)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    subject     VARCHAR(200) DEFAULT NULL,
    message     TEXT NOT NULL,
    status      ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_status (status),
    KEY idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Password reset tokens (sha256 of the emailed token, 1 hour expiry)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_token (token_hash),
    KEY idx_expires (expires_at),
    CONSTRAINT fk_cpr_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_token (token_hash),
    KEY idx_expires (expires_at),
    CONSTRAINT fk_apr_admin FOREIGN KEY (admin_id)
        REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Login / password-reset throttling (core/auth.php): per scope + sha256(client IP),
-- 5 failures in 15 minutes -> locked for 15 minutes.
-- scope: admin | customer | admin_reset | customer_reset | admin_signup
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope        VARCHAR(20) NOT NULL,
    ip_hash      CHAR(64) NOT NULL,
    failures     INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_login_attempts_scope_ip (scope, ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Admin rebuild (docs/ADMIN.md "Data model additions"). All additive.
-- Existing installs: run `php database/migrate.php` to add the new columns.
-- =============================================================================

-- Order timeline: everything that happens to an order (core/events.php)
CREATE TABLE IF NOT EXISTS order_events (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    type        ENUM('placed','confirmed','payment_recorded','fulfilled','shipped','delivered','cancelled','note','refund_requested') NOT NULL,
    actor_type  ENUM('customer','staff','system') NOT NULL DEFAULT 'system',
    actor_id    INT UNSIGNED DEFAULT NULL,          -- customers.id or admins.id per actor_type
    payload     JSON DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_events_order (order_id, created_at),
    KEY idx_order_events_type (type, created_at),
    CONSTRAINT fk_order_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Internal staff notes per order (never shown to the customer)
CREATE TABLE IF NOT EXISTS order_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED DEFAULT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_notes_order (order_id, created_at),
    CONSTRAINT fk_order_notes_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_notes_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_shipments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    courier         VARCHAR(60)  NOT NULL,
    tracking_number VARCHAR(80)  DEFAULT NULL,
    shipped_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at    DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_order_shipments_order (order_id),
    KEY idx_order_shipments_tracking (tracking_number),
    CONSTRAINT fk_order_shipments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock ledger: products.inventory / product_variations.inventory change ONLY through
-- core/inventory.php::adjust_inventory(), so SUM(delta) per product == products.inventory.
CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id   INT UNSIGNED NOT NULL,
    variation_id INT UNSIGNED DEFAULT NULL,
    delta        INT NOT NULL,
    reason       ENUM('received','sold','returned','damaged','correction','cancelled_order') NOT NULL,
    reference    VARCHAR(120) DEFAULT NULL,         -- "order #12", "PO 2026-11", free text
    admin_id     INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_adj_product (product_id, created_at),
    KEY idx_inv_adj_variation (variation_id),
    KEY idx_inv_adj_reason (reason),
    CONSTRAINT fk_inv_adj_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_adj_variation FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_adj_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every staff write (core/audit.php). `before` / `after` are reserved words: always quote them.
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(60)  NOT NULL,              -- "order.cancel", "product.update", "setting.update"
    entity_type VARCHAR(40)  NOT NULL,
    entity_id   VARCHAR(64)  NOT NULL,
    summary     VARCHAR(255) NOT NULL,
    `before`    JSON DEFAULT NULL,
    `after`     JSON DEFAULT NULL,
    ip          VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_admin (admin_id, created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_tags (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_tag_links (
    customer_id INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, tag_id),
    KEY idx_ctl_tag (tag_id),
    CONSTRAINT fk_ctl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctl_tag FOREIGN KEY (tag_id) REFERENCES customer_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED DEFAULT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_notes_customer (customer_id, created_at),
    CONSTRAINT fk_customer_notes_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_notes_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping: a zone is a set of cities (JSON array of names, matched case-insensitively);
-- exactly one zone should be is_default (used for any city not listed). core/shipping.php
CREATE TABLE IF NOT EXISTS shipping_zones (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80) NOT NULL,
    cities      JSON NOT NULL,
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shipping_zones_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_rates (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(80) NOT NULL DEFAULT 'Standard',
    amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- flat fee
    free_over   DECIMAL(12,2) DEFAULT NULL,             -- subtotal >= this ships free; NULL = never free
    eta         VARCHAR(40) DEFAULT NULL,               -- "1–2 days"
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipping_rates_zone (zone_id),
    CONSTRAINT fk_shipping_rates_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store settings key/value store (core/settings.php). Values are JSON-encoded scalars.
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(64) NOT NULL,
    value       TEXT DEFAULT NULL,
    updated_by  INT UNSIGNED DEFAULT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
