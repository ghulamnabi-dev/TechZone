# TechZone.pk – Security

This page describes the protections that are **implemented in the code today**, where
they live, and the settings that control them. Configuration is read from the
environment (see `.env.example`).

| Area | Where |
|------|-------|
| Bootstrap (env, errors, headers, session) | `core/bootstrap.php`, included by both `config/database.php` files |
| Error handling | `core/errors.php` |
| Security headers, JSON helpers, safe redirects | `core/http.php` |
| Session hardening, login/logout | `core/session.php` |
| CSRF | `core/security.php` |
| Login throttling, password policy | `core/auth.php` |
| Input validation | `core/validation.php` |
| Image uploads | `core/uploads.php` |
| Atomic checkout / stock | `core/orders.php` |

---

## 1. SQL injection

* Every query that takes input uses PDO prepared statements.
* PDO runs with `ATTR_EMULATE_PREPARES => false` (real server-side prepares) and
  `ERRMODE_EXCEPTION`. `ATTR_STRINGIFY_FETCHES` keeps column values as strings so the
  JSON returned to the existing JavaScript is unchanged.
* The only values concatenated into SQL are `LIMIT`/`OFFSET`, after being validated and
  clamped to integers (`v_int()`). `LIKE` search terms escape `%` and `_`.

## 2. Sensitive data in responses

* APIs select explicit columns from `customers` and `admins`. Password hashes and reset
  tokens are never returned (`AdminPortal/api/customers.php`, `api/profile.php`, both
  login endpoints).
* Database errors are logged with `error_log()`. Clients only get a generic message.
  Details are shown only when `APP_DEBUG=1`.
* A failed database connection returns a generic HTTP 500 page or JSON and never shows
  the DSN, host or user.

## 3. CSRF

* `csrf_token()` creates one random 256-bit token per session. `csrf_field()` prints the
  hidden input for HTML forms. Every page layout (storefront `includes/header.php`, admin
  `includes/header.php`, admin `auth/login.php` and `auth/signup.php`) exposes it as
  `<meta name="csrf-token">`, and `assets/js/common.js` in both apps wraps `fetch()` so
  every same-origin non-GET request sends it as `X-CSRF-Token` (and as a `csrf_token`
  field for `FormData` / `URLSearchParams` bodies).
* HTML form handlers (checkout, forgot/reset password, customer profile update and
  password change, cart add/remove/update/reorder, contact fallback) call
  `require_csrf_token()`, so the token is mandatory. Cart actions are POST only; old
  `cart.php?add=` / `?remove=` links just redirect without changing the cart.
* Every state-changing JSON/AJAX endpoint (all POST/PUT/PATCH/DELETE in
  `AdminPortal/api/` and `MarketingSite/api/`, including login and signup) calls
  `enforce_csrf_json()` and accepts the token from any of these:
  * an `X-CSRF-Token` header,
  * a `csrf_token` form field,
  * a `csrf_token` key in a JSON body.
  A wrong token is always rejected with HTTP 403.
* **Transition mode** (`CSRF_STRICT=0`, the default): a request that carries **no**
  token is accepted only when its `Origin` (or `Referer`) header names this host.
  Browsers send `Origin` on cross-site POST/PUT/DELETE, so forged requests are still
  rejected. When every `fetch()` sends the header, set `CSRF_STRICT=1` to require the
  token everywhere.
* Session cookies are `SameSite=Lax`, which adds a second layer of protection.

## 4. Sessions

* `start_secure_session()` sets the cookie to `HttpOnly`, `SameSite=Lax` and `Secure`
  (when the request is HTTPS, including behind a proxy that sends
  `X-Forwarded-Proto: https`). It also turns on `use_strict_mode` and
  `use_only_cookies`.
* After a successful admin or customer login, `session_after_login()` calls
  `session_regenerate_id(true)` to prevent session fixation. The CSRF token is rotated
  too, unless the other identity is already signed in.
* Admin and customer identities use separate keys (`admin_*`, `customer_*`) in the same
  session. `session_logout('admin' | 'customer')` removes only that identity. When no
  identity is left, it destroys the session and expires the cookie.
* Changing the password regenerates the session id and invalidates outstanding reset
  links.
* No page calls `session_start()` directly: every page includes `core/bootstrap.php`
  (directly or via `config/database.php`). The Docker image also sets these flags in
  `php.ini` as a second layer.

## 5. Authentication

* Passwords are stored with `password_hash(PASSWORD_DEFAULT)` (bcrypt) and checked with
  `password_verify()`. Hashes are upgraded on login when `password_needs_rehash()` says
  so.
* **No account enumeration**: an unknown email, an account without a password and a
  wrong password all return `Invalid email or password.` A dummy `password_verify()`
  runs for unknown emails, so the response time is the same.
* **Throttling**: 5 failures within 15 minutes lock the scope for 15 minutes. Failures
  are counted per client IP in the `login_attempts` table (IP stored as a sha256 hash)
  **and** per session. The scopes are `admin`, `customer`, `admin_reset`,
  `customer_reset` and `admin_signup`, and a lock returns HTTP 429.
  `TRUST_PROXY=1` uses the first `X-Forwarded-For` address.
* **Password policy**: 8–72 characters (bcrypt ignores bytes after 72), enforced on
  signup, reset and change-password (admin API and storefront profile page), with
  matching `minlength`/JS checks in the forms.
* **Admin sign-up is closed by default.** `AdminPortal/api/signup.php` returns 403 unless
  `ADMIN_SIGNUP_CODE` is set. When it is set, the request must include the same
  `invite_code` (compared with `hash_equals`), entered in the sign-up form's
  "Invite code" field. Wrong codes are throttled, and a locked client is refused before
  the code is compared.
* **Password reset**:
  * Tokens are 256-bit random values. Only their `sha256` is stored.
  * Links expire after 1 hour, checked against the database clock.
  * A link works once: the token row is deleted in the same transaction that sets the
    new password, along with every other token for that user. Requesting a new link
    removes the old ones.
  * The forgot-password page shows the same message whether or not the details match.
  * Links are emailed when `PASSWORD_RESET_EMAIL=1`, using `APP_URL` so a spoofed `Host`
    header cannot redirect the link. They are shown on screen only when
    `PASSWORD_RESET_SHOW_LINK=1` (defaults to `APP_DEBUG`), because the email,
    name/username and phone used to verify identity are not secrets.

## 6. Input validation

`core/validation.php` provides `v_str` (trim + max length), `v_int`/`v_id`, `v_num`,
`v_email` (`filter_var`), `v_date`, `v_enum`, `v_phone` and `v_link`. Every API casts ids
to int, length-checks strings and whitelists enums (order, review and contact statuses,
variation type, payment method). Promotion links must be relative paths or `http(s)` URLs,
so `javascript:` URLs are rejected. Cart quantity is limited to 1–100 per add and the cart
to 50 lines.

## 7. File uploads

`store_uploaded_image()` is used for product, category and promotion images:

* the MIME type is detected from the file contents with `finfo`; the client's MIME type
  and file name are ignored,
* only JPEG, PNG and WebP are accepted; SVG, GIF, HTML and PHP are rejected,
* `getimagesize()` must parse the file as an image of the same type,
* the maximum size is 2 MB,
* the stored name is `bin2hex(random_bytes(16))` plus an extension derived from the MIME
  type, so no part of the user's file name or path is used.

Deleting an image (`delete_upload_if_unused()`) resolves the path with `realpath()` and
refuses anything outside `AdminPortal/uploads/`. It also skips files that another
product, category or promotion still uses. In Docker, PHP execution is disabled in the
uploads folder.

## 8. Output, redirects and headers

* Pages escape output with `htmlspecialchars()`. API messages that the UI inserts with
  `innerHTML` never contain user-supplied text.
* Login `?redirect=` accepts only `checkout` or a same-site relative path
  (`safe_redirect_path()`). `//host`, schemes and backslashes fall back to the home page.
* `send_security_headers()` runs on every request and sends `X-Content-Type-Options:
  nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, COOP, HSTS on
  HTTPS, and a `Content-Security-Policy-Report-Only` policy. The CSP is report-only
  because the UI still uses inline scripts. It removes `X-Powered-By`.
* JSON APIs send `Content-Type: application/json; charset=utf-8` and
  `Cache-Control: no-store`. They return proper status codes: 401 when not logged in,
  403 for CSRF, 404, 405, 409, 422, 429 and 500.

## 9. Errors

`core/errors.php` turns `display_errors` off unless `APP_DEBUG=1` and logs everything.
Uncaught exceptions and fatal errors become a generic HTTP 500, as JSON for API requests
or a small HTML page otherwise. Debug details are included only when `APP_DEBUG=1`.

## 10. Inventory and checkout

`place_order()` (`core/orders.php`) runs the whole checkout in one transaction:

1. It locks the product and size/color variation rows with `SELECT … FOR UPDATE`, in id
   order.
2. It checks the summed demand per row against the locked stock.
3. It re-prices every line from the database instead of trusting session prices, and
   re-validates the promo code.
4. It inserts the order and its items, then decrements stock with a guarded
   `UPDATE … WHERE inventory >= ?`.

Any error rolls back the whole order. A test with 12 concurrent checkouts for 3 units
produced exactly 3 orders and stock 0.

## 11. Developer tools

`MarketingSite/tools/verify-assets.php` returns 404 unless `ENABLE_DEV_TOOLS=1`, because
it discloses filesystem paths.

## Known gaps / next steps

* `CSRF_STRICT=1` is the recommended setting (`.env.example`); the code default when the
  variable is unset is still 0 (transition mode).
* Customer logout (`auth/logout.php`) is a GET, so a cross-site link can sign a customer
  out (no data change).
* The CSP is report-only. Enforcing it needs the inline scripts moved to files (or
  nonces).
