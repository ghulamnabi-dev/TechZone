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
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
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
    total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    payment_method  VARCHAR(50)  NOT NULL DEFAULT 'cash_on_delivery',
    promo_code      VARCHAR(50)  DEFAULT NULL,
    order_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_status (status),
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
