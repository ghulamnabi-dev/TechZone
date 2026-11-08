# Architecture

TechZone is deliberately framework-free PHP. The structure is **two thin front-ends over one shared core**: every page and every JSON endpoint starts by including its app's `config/database.php`, which pulls in `core/bootstrap.php`, so the same environment handling, error handling, security headers, session hardening and database connection apply everywhere.

```
            MarketingSite/ (customers)              AdminPortal/ (staff)
            ├── index.php, shop/, account/, auth/   ├── index.php → auth/login.php
            ├── api/*.php   JSON (cart, promo, …)   ├── manage/*.php  pages
            ├── includes/   header, footer, format  ├── api/*.php     JSON (CRUD, stats)
            └── assets/     css, js, images         └── assets/       css, js
                         │                                   │
                         └──────────── core/ ────────────────┘
      bootstrap.php   env → errors → headers → session, included by both config/database.php
      database.php    PDO from DATABASE_URL / MYSQL_URL or DB_* vars (12-factor), emulation off
      security.php    CSRF: token issue, hash_equals check, header / field / JSON body, strict mode
      session.php     secure cookie params, id regeneration after login, scoped logout
      auth.php        login throttling (per IP + session), constant-time password check, policy
      validation.php  v_str / v_int / v_id / v_email / v_enum / v_link / v_phone …
      uploads.php     image upload validation (finfo, type whitelist, size, random names), safe delete
      orders.php      place_order(): one transaction, FOR UPDATE locks, re-pricing, guarded decrement
      inventory.php   stock and variation pricing shared by storefront and admin
      http.php        JSON helpers, security headers, safe redirects, absolute URLs
      errors.php      env()/env_flag(), logging, generic 500s, APP_DEBUG switch
                                          │
                                   MariaDB / MySQL
                          database/schema.sql · database/seed.sql
```

## Request flow

1. A page or API file includes `config/database.php` → `core/bootstrap.php` runs once per request: loads `errors.php` (so nothing leaks), sends security headers, starts the hardened session, connects PDO (`$conn`).
2. Auth guards: `config/auth.php` (admin) / `config/customer_auth.php` (customer) check the session and redirect or return 401 JSON.
3. State-changing requests call `require_csrf_token()` (HTML forms) or `enforce_csrf_json()` (fetch). The token comes from a `<meta name="csrf-token">` that `assets/js/common.js` attaches to every non-GET request as `X-CSRF-Token`.
4. Input goes through `validation.php` helpers; SQL is always a prepared statement; responses go through `json_response()` / `json_error()` with real status codes.
5. Checkout is the one multi-table write and lives in `core/orders.php::place_order()` so the storefront page stays small and the transaction boundary is explicit.

## Conventions

- Prices are stored as `DECIMAL(10,2)` and formatted at the edge: `format_pkr()` in PHP, `formatPKR()` in JS (`Rs 7,254,015`, no decimals).
- Order status is a MySQL `ENUM('Pending','Processing','Shipped','Delivered','Cancelled')`; the admin API whitelists transitions against the same list.
- Product images live in `AdminPortal/uploads/`; the stored value is a path relative to the portal root, resolved by `paths.php` helpers in both apps.
- Configuration is environment-only (`.env.example` documents every key); the repo contains no credentials.
- Both front-ends render server-side PHP for pages and use small per-page vanilla JS modules against the JSON `api/` folders — no build step.

## What is intentionally not here

No MVC framework, ORM, dependency container or templating engine. For a project of this size the shared `core/` gives the same benefits (one place for security, one place for data access rules) without hiding how HTTP, sessions and SQL actually work — which is the point of the project. See [SECURITY.md](SECURITY.md) for the threat model and known gaps.
