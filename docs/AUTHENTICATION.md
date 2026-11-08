# TechZone.pk – Authentication System

This document describes how **admin and customer authentication** works and how to extend it (e.g. Remember Me, optional forgot-password flow) for a portfolio-ready system.

---

## Current Behaviour (Session-Based)

- **Session**: `core/session.php` → `start_secure_session()` (HttpOnly, SameSite=Lax, Secure on HTTPS, strict mode), started by `core/bootstrap.php`.
- **Admin**: `AdminPortal/api/login.php` (email + password, throttled, generic error) → `session_after_login()` (`session_regenerate_id(true)`) → `$_SESSION['admin_id']`. `config/auth.php` → `checkLogin()` redirects pages and returns **401 JSON** for APIs.
- **Customer**: `MarketingSite/api/login.php`, same pattern → `$_SESSION['customer_id']`; `checkCustomerLogin()` for pages, `requireCustomerJson()` (401) for APIs.
- **Logout**: `session_logout('admin'|'customer')` removes only that identity; destroys the session and cookie when nothing is left.
- **Admin sign-up**: disabled unless `ADMIN_SIGNUP_CODE` is set (invite code required).
- **Password reset**: implemented for both sides (sha256-hashed, 1-hour, single-use tokens) — see [SECURITY.md](SECURITY.md) §5.

The sections below are the original design notes / extension ideas (Remember Me is not implemented).

---

## Secure Session Management

1. **Start session with secure options** (in a single bootstrap file used by all auth):

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Strict'
    ]);
}
```

2. **On login (admin or customer)**:
   - Validate credentials (e.g. email + password with `password_verify`).
   - Regenerate session ID to prevent fixation: `session_regenerate_id(true);`
   - Set only necessary keys: `$_SESSION['admin_id']`, `$_SESSION['admin_name']`, etc.

3. **On logout**:
   - `$_SESSION = [];`
   - `session_destroy();`
   - Optionally clear session cookie by name.
   - Redirect to login or home.

4. **Route protection (middleware)**:
   - Every admin page: `require_once 'config/auth.php'; checkLogin();`
   - Every admin API: `checkLogin(true);` so unauthenticated requests get a JSON error.

---

## “Remember Me” Option

**Idea**: Store a long-lived token in a cookie and in the database; on visit, if there is no session but a valid cookie, log the user in and create a session.

1. **Table**: e.g. `admin_remember_tokens (admin_id, token_hash, expires_at)`.
2. **On login with “Remember Me”**:
   - Generate a random token (e.g. `bin2hex(random_bytes(32))`).
   - Store `hash('sha256', $token)` and `expires_at = now + 30 days` in DB.
   - Set cookie: `setcookie('remember_admin', $token, expires_at, '/', '', true, true);`
3. **On each request** (before checking session):
   - If not logged in and `remember_admin` cookie exists:
     - Look up token hash in DB; if found and not expired, load admin, create session, optionally rotate token and extend cookie.
   - If session exists, ignore remember cookie.

**Security**: Use a long random token, store only its hash, and expire it. Rotating the token on use reduces risk if the cookie is stolen.

---

## Forgot Password

1. **Page**: “Forgot password” form with **email** only.
2. **Backend**:
   - Find admin/customer by email (do not reveal if email exists or not).
   - If found: generate a one-time token (e.g. `bin2hex(random_bytes(32))`), store its hash and expiry (e.g. 1 hour) in a table like `password_reset_tokens (user_id, token_hash, expires_at)`.
   - Send email with a link: `https://yoursite.com/admin/reset-password.php?token=...` (use the raw token in the link; store hash in DB).
3. **Reset password page**:
   - Read `token` from query string.
   - If token valid (hash matches, not expired), show form for new password.
   - On submit: validate password, update hash in DB, delete token, log user in or redirect to login.

**Email**: Use PHP’s `mail()` or a library (e.g. PHPMailer) or an external service. For portfolio, you can document the flow and stub the email send.

---

## Summary

| Feature           | Implementation notes                                      |
|-------------------|------------------------------------------------------------|
| Session auth      | checkLogin() on every admin page and API                  |
| Secure sessions   | HttpOnly, Secure, SameSite; regenerate_id on login       |
| Logout            | Clear session and destroy; redirect                       |
| Remember Me       | Cookie + DB token (hashed); validate and create session   |
| Forgot password   | Token in DB (hashed), link with token; one-time use, expiry|

Implementing these patterns shows you understand authentication and security, which strengthens your portfolio.
