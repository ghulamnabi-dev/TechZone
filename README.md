# TechZone — storefront + admin for a PC-hardware shop

[![CI](https://github.com/gn-4200/TechZone/actions/workflows/ci.yml/badge.svg)](https://github.com/gn-4200/TechZone/actions/workflows/ci.yml)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![MariaDB / MySQL](https://img.shields.io/badge/MariaDB%20%2F%20MySQL-PDO-003545?logo=mariadb&logoColor=white)
![Bootstrap 5](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)
![Docker Compose](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

**TechZone** is a small online shop for PC hardware and accessories, written in plain PHP with no framework. Customers browse a catalogue with size/colour variations, fill a cart, apply promo codes and check out with cash on delivery or a JazzCash placeholder; shop staff run the business from a separate admin portal with a KPI dashboard, an order queue, product and promotion management, review moderation and analytics. Most of the effort went into the unglamorous parts: CSRF, sessions, login throttling, upload validation and a checkout that cannot oversell.

<p align="center">
  <img src="docs/screenshots/cover.png" alt="TechZone — storefront home on the left, admin dashboard on the right" width="100%">
</p>

<details>
<summary><strong>More screens</strong> — catalogue, product, cart, checkout, admin orders, products, analytics, mobile</summary>
<br>

| Product catalogue | Product details with colour variations |
|---|---|
| ![Paged product grid with search, category filter and sort](docs/screenshots/02-store-products.png) | ![Product page with colour options, stock and reviews](docs/screenshots/03-store-product.png) |

| Cart | Checkout (COD / JazzCash) |
|---|---|
| ![Cart with two items and a promo-code box](docs/screenshots/04-store-cart.png) | ![Shipping form and payment method](docs/screenshots/05-store-checkout.png) |

| Customer order history | Admin — orders |
|---|---|
| ![My Orders list with status badges](docs/screenshots/06-store-orders.png) | ![Order table with search and status filter](docs/screenshots/11-admin-orders.png) |

| Admin — order details and status update | Admin — product editor |
|---|---|
| ![Order modal with customer, items and Update status](docs/screenshots/12-admin-order-modal.png) | ![Product modal with image, stock and variations](docs/screenshots/14-admin-product-modal.png) |

| Admin — products | Admin — analytics |
|---|---|
| ![Product table with stock dots and status](docs/screenshots/13-admin-products.png) | ![Top sellers and revenue by category](docs/screenshots/15-admin-analytics.png) |

| Admin — promotions | Full pages |
|---|---|
| ![Promotions with codes, date ranges and live/ended status](docs/screenshots/16-admin-promotions.png) | [Store home](docs/screenshots/01-store-home.png) · [Admin dashboard](docs/screenshots/10-admin-dashboard.png) |

| Mobile — storefront | Mobile — admin |
|---|---|
| <img src="docs/screenshots/20-mobile-store.png" alt="Storefront home on a phone" width="260"> | <img src="docs/screenshots/21-mobile-admin.png" alt="Admin dashboard on a phone" width="260"> |

</details>

## What it does

**Storefront** (`MarketingSite/`)
- **Catalogue** — eight categories, search, category filter, sort and paging; product pages with stock, average rating and customer reviews.
- **Variations** — size and colour options per product, each with its own stock and price modifier.
- **Cart and promo codes** — session cart (max 50 lines, 1–100 per add), percentage or fixed-amount codes validated again at checkout.
- **Checkout** — cash on delivery or JazzCash (the wallet number is collected; no gateway is called, see limitations).
- **Accounts** — sign-up, login, profile, password change, forgot/reset password, order history and order details.
- **Reviews and contact** — logged-in customers can review a product once (reviews start as Pending until an admin approves them); a contact form feeds the admin inbox.

**Admin portal** (`AdminPortal/`)
- **Dashboard** — revenue, orders, customers and product KPIs, month-over-month change, a 12-month sales chart, category breakdown, recent orders, top sellers and low-stock list.
- **Orders** — search and filter; status is one of Pending → Processing → Shipped → Delivered, or Cancelled.
- **Products** — create/edit with image upload, inventory, active flag and size/colour variations; **categories** with images.
- **Customers, reviews, messages** — read customers, moderate reviews, work through contact messages.
- **Promotions** — banners and codes with discount, date range, ordering and live/ended status.
- **Analytics** — top-selling products and revenue by category.

## Architecture

No framework, by design: the project shows what the plumbing looks like when you write it yourself. A shared `core/` holds everything security-relevant and is included by both front-ends, so a fix lands once.

```
 Browser ──► Apache / php -S ──┬─► MarketingSite/  pages + api/*.php (JSON)  ─┐
                               │                                              ├─► core/ ──► PDO ──► MariaDB / MySQL
                               └─► AdminPortal/    pages + api/*.php (JSON)  ─┘   bootstrap · database · session · security (CSRF)
                                   (admin session, checkLogin on every API)       auth · validation · uploads · orders · inventory · http · errors
```

```
TechZone/
├── core/             shared PHP: bootstrap, database, session, security, auth, validation,
│                     uploads, orders (checkout), inventory, http, errors
├── MarketingSite/    storefront: index, shop/, account/, auth/, api/ (cart, login, promo, reviews…)
├── AdminPortal/      admin: auth/, manage/ (dashboard, orders, products…), api/ (JSON CRUD), uploads/
├── database/         schema.sql (idempotent) + seed.sql (demo data)
├── docs/             SECURITY.md, AUTHENTICATION.md, ARCHITECTURE.md, screenshots/
├── Dockerfile        php:8.3-apache, production php.ini, hardened Apache
├── docker-compose.yml  MariaDB 11 (schema + seed on first start) + web
└── .github/workflows CI: lint + smoke test
```

Pages render server-side; the admin tables, dashboard and cart interactions call the JSON endpoints in each `api/` folder. `docs/ARCHITECTURE.md` walks through the request flow and the responsibilities of each `core/` module.

## Security

This is the part of the project I care most about. Everything below is implemented in the code; [`docs/SECURITY.md`](docs/SECURITY.md) has the file-by-file detail and the known gaps.

- **SQL injection** — PDO prepared statements everywhere, with emulation turned off (`ATTR_EMULATE_PREPARES => false`) and exceptions on. The only concatenated values are `LIMIT`/`OFFSET`, clamped to integers; `LIKE` terms are escaped.
- **CSRF** — one 256-bit token per session, compared with `hash_equals`. Every POST/PUT/PATCH/DELETE must carry it as an `X-CSRF-Token` header, a form field or a JSON key; wrong tokens are always a 403. `CSRF_STRICT=1` makes the token mandatory (the recommended setting); the default transition mode still accepts token-less requests from the same `Origin`.
- **Sessions** — `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, strict mode, cookies only; `session_regenerate_id(true)` after every login and password change. Admin and customer identities live under separate keys and log out independently.
- **Login throttling** — 5 failures in 15 minutes locks the scope for 15 minutes (HTTP 429), counted per client IP (stored as a sha256 hash) and per session, for admin, customer, reset and sign-up scopes.
- **No account enumeration** — unknown email, no password and wrong password all give `Invalid email or password.`; a dummy `password_verify()` keeps timing alike.
- **Password reset** — 256-bit tokens, only the sha256 is stored, one hour expiry, single use (deleted in the same transaction that sets the password).
- **Admin sign-up is closed** — it returns 403 unless `ADMIN_SIGNUP_CODE` is set, then needs the matching invite code (`hash_equals`); wrong codes are throttled.
- **Uploads** — MIME detected with `finfo` (client type and name ignored), JPEG/PNG/WebP only, `getimagesize()` must agree, 2 MB cap, random 128-bit file names; deletion is confined to the uploads folder with `realpath()`. PHP execution is disabled there in the Docker image.
- **Headers** — `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS on HTTPS, plus a `Content-Security-Policy-Report-Only` policy.
- **No credential leaks** — APIs select explicit columns from `customers` and `admins`, so hashes and reset tokens are never serialised; database errors are logged and clients see a generic message unless `APP_DEBUG=1`.
- **Checkout cannot oversell** — `place_order()` runs in one transaction: product and variation rows are locked with `SELECT … FOR UPDATE` in id order, demand is summed against locked stock, every line is re-priced from the database, then stock is decremented with a guarded `UPDATE … WHERE inventory >= ?`. Any failure rolls the whole order back.

## Run it

### Option A — Docker

```bash
cp .env.example .env
docker compose up --build
```

| | URL |
|---|---|
| Storefront | http://localhost:8080/MarketingSite/ |
| Admin portal | http://localhost:8080/AdminPortal/ |

MariaDB applies `database/schema.sql` and `database/seed.sql` on the first start (when the data volume is empty); uploads live in the `uploads` volume.

### Option B — local PHP + MariaDB

Requirements: PHP 8.3 with `pdo_mysql`, and MariaDB or MySQL.

```bash
mysql -uroot -e "CREATE DATABASE techzone_db CHARACTER SET utf8mb4"
mysql -uroot techzone_db < database/schema.sql
mysql -uroot techzone_db < database/seed.sql
DATABASE_URL=mysql://user:pass@127.0.0.1:3306/techzone_db php -S localhost:8080
```

Then open `http://localhost:8080/MarketingSite/` or `/AdminPortal/`.

### Demo logins

| Role | Login | Password |
|---|---|---|
| Admin | `admin@techzone.pk` | `Admin@1234` |
| Customer | `ali.raza@example.pk` | `Customer@123` |

The seed contains 12 customers (all with the customer password above), 32 products, 40 orders and three promotions (`TECH10`, `WELCOME1000`, `SUMMER15`, the last one ended). Change these credentials before exposing the app anywhere.

### Configuration

Read from the environment (real variables win over `.env`); see [`.env.example`](.env.example).

| Variable | Default | Notes |
|---|---|---|
| `DATABASE_URL` | — | `mysql://user:pass@host:port/db`; or `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` |
| `APP_URL` | `http://localhost:8080` | Base URL used in password-reset links |
| `APP_DEBUG` | `0` | `1` shows error details; local only |
| `CSRF_STRICT` | `1` in `.env.example` (code default `0`) | `1` requires the token on every state-changing request |
| `ADMIN_SIGNUP_CODE` | empty | Admin sign-up returns 403 until set; then it is the invite code |
| `PASSWORD_RESET_EMAIL` | `0` | `1` sends the reset link with `mail()` |
| `PASSWORD_RESET_SHOW_LINK` | `0` | `1` prints the reset link on screen (demos only; defaults to `APP_DEBUG`) |
| `TRUST_PROXY` | `0` | `1` trusts `X-Forwarded-For` / `-Proto` behind a proxy |
| `ENABLE_DEV_TOOLS` | `0` | `1` enables `MarketingSite/tools/verify-assets.php` |

## Tests & CI

There is no unit-test suite yet. [`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push and pull request:

1. **Lint** — `php -l` on every PHP file (PHP 8.3) and `composer validate --strict`.
2. **Smoke test** — starts MariaDB 11 as a service, applies `schema.sql` and `seed.sql`, launches `php -S`, and curls real endpoints: public pages return 200, `/` redirects, the dev tool is a 404, `/AdminPortal/api/customers.php` is a 401 when logged out. It then logs in as admin, checks that the customers API returns data without a `password` field, that the dashboard API works, that a cross-origin `PUT` to the orders API is rejected with 403, and that a bad customer login returns the generic error. The PHP error log must stay empty.

## Engineering highlights

- **Framework-free, but not ad hoc** — one `core/` library for bootstrap, sessions, CSRF, validation, uploads and checkout, used identically by both apps.
- **12-factor configuration** — `DATABASE_URL` or discrete variables, `.env` for development, real environment wins; failures return a generic 500 and never reveal the DSN.
- **Concurrency-safe checkout** — ordered row locks plus a guarded decrement; a 12-concurrent-checkout test against 3 units produced exactly 3 orders and stock 0 (see `docs/SECURITY.md`).
- **Server-side pricing** — the cart stores ids and quantities; prices, variation modifiers and promo codes are recomputed from the database at order time.
- **Shipped like a service** — Docker image with production `php.ini`, hidden Apache internals and no PHP in `uploads/`; Compose seeds MariaDB on first start; CI boots the real stack.
- **Consistent API contract** — JSON everywhere with `Cache-Control: no-store` and honest status codes (401, 403, 404, 405, 409, 422, 429, 500).

## Known limitations / roadmap

- [ ] No automated unit or integration tests beyond the CI smoke script.
- [ ] JazzCash is a placeholder: the wallet number is stored with the order, but no payment gateway is integrated and nothing is charged.
- [ ] Customer logout (`auth/logout.php`) is a GET, so a cross-site link can sign a customer out (no data change).
- [ ] The CSP is report-only until the inline scripts move to files or nonces.
- [ ] `CSRF_STRICT` defaults to `0` in code; set it to `1` (as `.env.example` does) for any real deployment.
- [ ] Order status is validated against the five allowed values, but any status can be set from any other, and cancelling does not return stock.
- [ ] Products have a single image.
- [ ] Deploy a demo with seeded data.

## License

MIT © Ghulam Nabi
