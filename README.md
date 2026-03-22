# TechZone.pk — Full-Stack eCommerce (Portfolio Project)

A complete **PHP + MySQL eCommerce system** with a **customer storefront** and a secure **admin dashboard**. Built for portfolio use on **GitHub**, **LinkedIn**, and **Upwork**.

---

## Overview

| | |
|---|---|
| **Customer site** | Homepage, product catalog, cart, checkout, login, orders, reviews |
| **Admin panel** | Dashboard with analytics, orders, products, categories, customers, messages, promotions |
| **Tech stack** | PHP, MySQL (PDO), Bootstrap 5, JavaScript, Chart.js |
| **Security** | Prepared statements, CSRF protection, session auth, password hashing |

---

## Screenshots

Add portfolio images under `screenshots/` and reference them here:

| Suggested file | What to capture |
|------------------|-----------------|
| `01-homepage.png` | Marketing homepage (hero + featured products) |
| `02-products.png` | Product listing or product detail |
| `03-cart.png` | Cart or checkout (optional) |
| `04-admin-dashboard.png` | Admin dashboard (stats + charts) |
| `05-admin-orders.png` | Admin orders table |
| `06-admin-products.png` | Admin products or edit modal (optional) |

Capture locally, then embed in this section, for example:

| Customer site | Admin panel |
|---------------|-------------|
| *Add `screenshots/01-homepage.png`* | *Add `screenshots/04-admin-dashboard.png`* |
| *Add `screenshots/02-products.png`* | *Add `screenshots/05-admin-orders.png`* |

---

## Features

### Customer website
- Homepage with hero and featured products
- Product browsing, search, category filters
- Cart and checkout (JazzCash / Cash on delivery)
- Customer login, registration, profile, order history
- Product reviews and promotions

### Admin panel
- **Dashboard**: Revenue, orders, users, products, categories; Chart.js (sales, categories); recent orders; top products; low stock alert
- **Orders**: List, filter, status (Pending → Processing → Shipped → Delivered → Cancelled), order detail modal
- **Products**: CRUD, images, categories, variations
- **Categories**: CRUD, images, product count
- **Customers**, **Contact messages**, **Reviews**, **Promotions**, **Profile**

### Technical
- **Security**: PDO prepared statements, CSRF on forms, session-based auth, route protection
- **Shared core**: `core/database.php`, `core/security.php`, `core/inventory.php`
- **APIs**: JSON endpoints for dashboard, orders, products, categories, customers, messages, reviews, promotions

---

## Tech stack

| Layer   | Technology |
|--------|------------|
| Backend | PHP, PDO, MySQL |
| Frontend | Bootstrap 5, Font Awesome, vanilla JavaScript |
| Charts | Chart.js |
| Auth | Session-based (admin + customer) |

---

## Project structure

```
TechZone/
├── Marketing Site/       # Customer storefront
│   ├── index.php, about.php, contact.php   # Top-level pages
│   ├── shop/             # products.php, product-details.php, cart.php, checkout.php
│   ├── auth/             # login, signup, logout, forgot/reset password
│   ├── account/          # profile, orders, order-details
│   ├── assets/css/       # style.css (main stylesheet)
│   ├── assets/js/        # common.js, session.js, form-validation.js
│   ├── assets/images/    # e.g. hero/slide1.png — use asset_url() in PHP
│   ├── database/         # complete_database_schema.sql
│   ├── tools/            # e.g. verify-assets.php (dev helper)
│   ├── api/              # Cart, login, contact, etc.
│   └── config/           # DB, auth, paths.php (site_url helpers)
├── Admin Portal/         # Admin dashboard
│   ├── index.php         # Login
│   ├── dashboard.php     # Analytics
│   ├── orders.php, products.php, categories.php, ...
│   ├── assets/js/        # Page scripts
│   ├── api/              # JSON APIs
│   └── config/
├── core/                 # Shared
│   ├── database.php      # PDO connection
│   ├── security.php      # CSRF helpers
│   └── inventory.php     # Stock/variations
├── docs/                 # Architecture, security, portfolio guides
└── screenshots/          # Portfolio screenshots
```

**Marketing JS:** Scripts live in `Marketing Site/assets/js/`. Shared scripts are loaded from `includes/header.php`; use `marketing_script_url()` from `includes/js-assets.php` for `src` URLs and cache busting.

**Older databases:** If `orders.status` does not include `Cancelled`, run this once in phpMyAdmin or mysql:

```sql
ALTER TABLE orders
MODIFY COLUMN status ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending';
```

---

## Quick start

1. **Requirements**: PHP 7.4+, MySQL, Apache (e.g. XAMPP).

2. **Database**
   - Create DB: `techzone_db` (or set `DB_NAME` in env).
   - Import: `Marketing Site/database/complete_database_schema.sql`
   ```bash
   mysql -u root -p techzone_db < "Marketing Site/database/complete_database_schema.sql"
   ```

3. **Config**  
   Edit `core/database.php` if needed, or set env: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.

4. **Run**
   - Put project in web root (e.g. `htdocs/TechZone`).
   - **Customer site**: `http://localhost/TechZone/Marketing%20Site/`
   - **Admin**: `http://localhost/TechZone/Admin%20Portal/`  
   - Default admin: **admin@techzone.pk** / **admin1234** (if schema inserts it).

---

## Database

Main tables: `admins`, `customers`, `products`, `categories`, `orders`, `order_items`, `reviews`, `promotions`, `contact_messages`, `product_variations`.  
Full schema: **`Marketing Site/database/complete_database_schema.sql`**.

---

## Documentation

| Doc | Purpose |
|-----|--------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | MVC-style structure and migration notes |
| [docs/SECURITY.md](docs/SECURITY.md) | Prepared statements, CSRF, validation |
| [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) | Sessions and auth patterns |
| [docs/PORTFOLIO.md](docs/PORTFOLIO.md) | Presenting on GitHub, LinkedIn, Upwork |
| [docs/LINKEDIN.md](docs/LINKEDIN.md) | Copy-paste text for LinkedIn profile |
| [docs/OPTIONAL_FEATURES.md](docs/OPTIONAL_FEATURES.md) | REST API, roles, email, exports |

---

## For recruiters & portfolio

- **One-liner**: Full-stack eCommerce in PHP/MySQL with customer store and admin dashboard; secure auth, CSRF, prepared statements; Chart.js analytics; responsive UI.
- **Link**: Add this repo (and optional live demo) to your LinkedIn and resume.
- **Talking points**: See [docs/PORTFOLIO.md](docs/PORTFOLIO.md) and [docs/LINKEDIN.md](docs/LINKEDIN.md).

---

## License

Portfolio / learning use. Adjust branding and data as needed.
