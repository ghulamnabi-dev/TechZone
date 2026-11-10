# TechZone — a PC-hardware marketplace for Lahore

[![CI](https://github.com/ghulamnabi-dev/TechZone/actions/workflows/ci.yml/badge.svg)](https://github.com/ghulamnabi-dev/TechZone/actions/workflows/ci.yml)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![MariaDB / MySQL](https://img.shields.io/badge/MariaDB%20%2F%20MySQL-PDO-003545?logo=mariadb&logoColor=white)
![No framework](https://img.shields.io/badge/framework-none-1B1D22)
![Docker Compose](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

PC builders in Pakistan check component prices the way traders check rates — walk Hafeez Centre or Hall Road, ask *"aaj ka rate kya hai?"*, compare, then buy. **TechZone is where you check today's rate, then buy**, from TechZone itself or from vetted shops in the same markets. It is a complete commerce system in plain PHP with no framework: a storefront with a live rate board, price history and a PC builder that only shows parts that fit; a back-office modelled on how shop staff actually work; and a marketplace with a scoped seller portal and weekly payouts.

<p align="center">
  <img src="docs/screenshots/cover.png" alt="Storefront home with the live rate board, and an order page in the admin with its timeline" width="100%">
</p>

<details open>
<summary><strong>Storefront</strong> — rate board, PC builder, product pages, marketplace</summary>
<br>

| PC builder — only parts that fit, totalled at today's rates | Picking a part: incompatible ones hidden, with the reason |
|---|---|
| ![Complete AM5 build with power meter and total](docs/screenshots/03-builder.png) | ![Motherboard picker filtered to the processor's socket](docs/screenshots/04-builder-picker.png) |

| Today's rates — 7-day change and 12-week history | Product page — rate history, installments, seller, specs |
|---|---|
| ![Full rate board with sparklines](docs/screenshots/05-rates.png) | ![Samsung Odyssey G5 product page](docs/screenshots/06-product.png) |

| Catalogue with spec facets | Compare |
|---|---|
| ![Processors with socket and core-count filters](docs/screenshots/07-catalogue.png) | ![Three graphics cards side by side, differences marked](docs/screenshots/08-compare.png) |

| Sell on TechZone | Checkout |
|---|---|
| ![Seller programme landing page](docs/screenshots/09-sell.png) | ![Checkout with shipping zone and payment options](docs/screenshots/10-checkout.png) |

| Order tracking for customers | Mobile |
|---|---|
| ![Customer order page with 4-step tracker](docs/screenshots/11-track.png) | <img src="docs/screenshots/40-mobile-home.png" alt="Home on a phone" width="260"> <img src="docs/screenshots/41-mobile-product.png" alt="Product page on a phone" width="260"> |

Full home page: [docs/screenshots/02-home-full.png](docs/screenshots/02-home-full.png)

</details>

<details>
<summary><strong>Back-office</strong> — operations home, order workflow, inventory ledger, activity log, sellers</summary>
<br>

| Operations home | Order page — fulfil, pay, ship, timeline |
|---|---|
| ![Today vs yesterday, to-do cards, 30-day sales](docs/screenshots/20-admin-home.png) | ![Order with payment, shipping, notes and event timeline](docs/screenshots/21-admin-order.png) |

| Orders queue | Product editor — specifications drive the PC builder |
|---|---|
| ![Tabbed order queue on AG Grid](docs/screenshots/22-admin-orders.png) | ![Product editor with builder-aware specifications, pricing and margin, stock ledger, variants, SEO](docs/screenshots/23-admin-product.png) |

| Inventory — days of cover and adjustment history | Activity log with before/after diffs |
|---|---|
| ![Inventory levels and recent adjustments](docs/screenshots/24-admin-inventory.png) | ![Audit log row expanded to show changed fields](docs/screenshots/25-admin-activity.png) |

| Ctrl+K search | Seller applications |
|---|---|
| ![Grouped search results for orders, products, customers](docs/screenshots/26-admin-search.png) | ![Applications queue with approve and reject](docs/screenshots/27-admin-sellers.png) |

| Seller portal — only the seller's own lines | Printable invoice |
|---|---|
| ![A seller's view of a mixed order](docs/screenshots/28-seller-portal.png) | ![A4 invoice](docs/screenshots/29-invoice.png) |

</details>

## What it does

**Storefront** (`MarketingSite/`) — design contract in [`docs/STOREFRONT.md`](docs/STOREFRONT.md)
- **Today's rates** — a live board of the most-watched parts with the change since last week; every product keeps a weekly price history shown as a sparkline with its 12-week low and high. "Price drops this week" is a page of its own.
- **PC builder** — processor → motherboard → memory → graphics → storage → power → case → cooler. Compatibility rules (socket, memory type, form factor, GPU length, cooler clearance and TDP, power draw with a recommended PSU) live in `core/builder.php`; the UI only offers parts that fit, explains the ones that don't, totals the build at today's rates, and adds all parts to the cart in one request. Builds live in the URL, so they can be shared.
- **Catalogue** — category tree, attribute facets per category (socket, cores, VRAM, memory type, panel size…) with counts, seller filter, sort by biggest drop. Every product has a real photo (1000×1000 WebP, ~45 KB each); a product added without one gets a deliberate spec tile instead of a broken image.
- **Product pages** — rate history, bank installment plans, the seller with an "Ask on WhatsApp" link, wishlist and compare, back-in-stock alerts, specifications, and compatibility hints for components ("Fits AM5 boards: 3 in stock").
- **Marketplace** — "Sold by …" on every product, seller directory and pages, and a Sell on TechZone programme with an application form.
- **Orders** — cart with free-delivery progress, checkout priced by shipping zone, promo codes (percent, fixed, free shipping, scoped to a category or product), cash on delivery or JazzCash, a 4-step tracker on the customer's order page and a public Track your order page.
- **Help centre** — shipping fees and delivery times read live from the shipping zones, installment tables from settings, returns, warranty claims, FAQ; a store page for the Hafeez Centre shop.

**Back-office** (`AdminPortal/`) — design contract in [`docs/ADMIN.md`](docs/ADMIN.md), described below.

## The back-office

The admin is modelled on how shop staff actually work, not on CRUD tables (design contract: [`docs/ADMIN.md`](docs/ADMIN.md)):

- **Orders are pages with a timeline.** Confirm → record payment → fulfil (partial allowed) → ship with courier and tracking → deliver, or cancel with a reason and automatic restock. Every step is an `order_events` row with the actor; customers see the same timeline on their order page and on the public **Track your order** page.
- **Stock is a ledger.** `inventory_adjustments` is the only way stock changes (received, sold, returned, damaged, correction, cancelled order); the Inventory page shows levels, days of cover and the adjustment history.
- **Roles are enforced server-side.** Owner / Manager / Staff capabilities in `core/permissions.php`; the UI hides what you cannot do, the APIs return 403.
- **Everything is attributable.** `audit_log` records who changed what with before/after diffs; the Activity page filters and exports it.
- **Settings that real stores have:** store details, shipping zones with free-over thresholds (used by checkout), payment method toggles, staff accounts with roles.
- **Marketplace back-office.** Seller applications are approved or rejected in the admin; an approved seller gets a portal in the same shell that is scoped on the server: they see only their own products, only orders containing their products and only their own lines on those orders (no customer contact or payment details), can fulfil and ship their lines, and get weekly payout statements (gross − 8% commission) that the owner marks paid.
- **Specifications drive the storefront.** The product editor's Specifications card gives each part type its own fields (socket, memory type, form factor, card length, wattage…). Values the PC builder compares are fixed choices, and the fields it needs are required before a part can go Active, so a typo can't break compatibility. Monitors, mice and chairs get the fields their sibling products already use. Changes save with the product in one transaction and one audit entry.
- **Product editor, customer profiles, discount builder** as full pages with a contextual save bar; **⌘K** global search; notifications for new orders, payments, low stock, pending reviews.

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
- **One sign-in message** — unknown email, no password and wrong password all give `Invalid email or password.` This does not prevent account enumeration on its own: sign-up and sign-in timing still leak it (see Known limitations).
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

The seed contains 12 customers (all with the customer password above), 75 products (35 of them PC-builder parts with full compatibility attributes), 12 weekly rate points per product, four sellers (TechZone plus three partner shops), 45 orders and four promotions (`TECH10`, `WELCOME1000`, `FREESHIP`, and `SUMMER15`, which has ended). **Prices are demo data**: plausible Lahore market rates for November 2026, not live quotes. Product photos live in `AdminPortal/uploads/catalog/` as `NN-name.webp` (NN = product id); `migrate.php` links a newly dropped photo to its product without overwriting one uploaded in the admin. Photos are manufacturer and retailer product shots used for demonstration only; the brands belong to their owners. Change these credentials before exposing the app anywhere.

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
3. **Concurrent checkout** — [`tests/concurrent_checkout.php`](tests/concurrent_checkout.php) creates a throwaway product with 3 units, starts 12 separate PHP processes that each call `place_order()` at the same instant on their own database connection, and then checks the database: exactly 3 orders, 9 refusals, stock 0 and a stock ledger that adds up. Run it with `docker compose exec web php tests/concurrent_checkout.php` (add `--buyers=30 --stock=5` to change the numbers).

## Engineering highlights

- **Framework-free, but not ad hoc** — one `core/` library for bootstrap, sessions, CSRF, validation, uploads and checkout, used identically by both apps.
- **12-factor configuration** — `DATABASE_URL` or discrete variables, `.env` for development, real environment wins; failures return a generic 500 and never reveal the DSN.
- **Concurrency-safe checkout** — ordered row locks plus a guarded decrement. `tests/concurrent_checkout.php` sends 12 checkouts at once for 3 units on every push and requires exactly 3 orders and stock 0 (see `docs/SECURITY.md`).
- **Server-side pricing** — the cart stores ids and quantities; prices, variation modifiers and promo codes are recomputed from the database at order time.
- **Shipped like a service** — Docker image with production `php.ini`, hidden Apache internals and no PHP in `uploads/`; Compose seeds MariaDB on first start; CI boots the real stack.
- **Consistent API contract** — JSON everywhere with `Cache-Control: no-store` and honest status codes (401, 403, 404, 405, 409, 422, 429, 500).
- **Stock as a ledger, history as events** — stock only changes through `inventory_adjustments` (the seed and `migrate.php` both verify `SUM(delta) = inventory`); every order step is an `order_events` row; every admin write is an `audit_log` row with a before/after diff.
- **Authorization on the server, per row** — Owner / Manager / Staff / Seller capabilities in `core/permissions.php`; sellers are additionally scoped per product and per order line, and other sellers' records return 404.
- **Rules as code, not UI** — builder compatibility, shipping zones and discount eligibility are pure functions in `core/` used identically by the storefront, the APIs and the admin.

## Known limitations / roadmap

- [ ] Automated checks are end-to-end (Playwright flows and the CI smoke script); there are no PHP unit tests yet.
- [ ] JazzCash is a placeholder: the wallet number is stored with the order, but no payment gateway is integrated and nothing is charged.
- [ ] Customer logout (`auth/logout.php`) is a GET, so a cross-site link can sign a customer out (no data change).
- [ ] Sign-up answers `Email already registered` (409) and is not throttled, so registered e-mails can be discovered there.
- [ ] Sign-in timing differs for known and unknown e-mails: `verify_password_constant()` builds its dummy hash on each request, so an unknown e-mail costs two bcrypt operations and a known one costs one. The dummy hash should be a fixed constant.
- [ ] The lockout can be reset: a successful sign-in clears the failed-attempt counter for that client IP (`login_clear_failures()`), and there is no per-account counter, so signing in to one's own account between guesses avoids the lock.
- [ ] There is no automated test that one seller cannot read another seller's order.
- [ ] The CSP is report-only until the inline scripts move to files or nonces.
- [ ] `CSRF_STRICT` defaults to `0` in code; set it to `1` (as `.env.example` does) for any real deployment.
- [ ] Products have a single image; a gallery with several angles per product is not built yet.
- [ ] Prices and price history are demo data.
- [ ] Payouts are statements only; no money is moved.
- [ ] Deploy a demo with seeded data.

## License

MIT © Ghulam Nabi

Third-party: Archivo and Manrope fonts (SIL Open Font License, self-hosted in `MarketingSite/assets/fonts/`).
- Admin data grids: [AG Grid Community](https://www.ag-grid.com/) 36.2 (MIT), vendored in `AdminPortal/assets/vendor/ag-grid/` with its licence.
