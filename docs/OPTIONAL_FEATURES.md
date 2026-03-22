# Optional Advanced Features (Portfolio Differentiators)

Ideas to extend TechZone.pk and stand out in a portfolio. Each can be implemented incrementally.

---

## 1. REST API for products and orders

Expose a **versioned API** (e.g. `/api/v1/products`, `/api/v1/orders`) that returns JSON and uses **API keys** or **Bearer tokens** for auth (separate from admin session).

- **Products**: `GET /api/v1/products` (list with filters), `GET /api/v1/products/:id` (single).
- **Orders**: `GET /api/v1/orders` (admin only or scoped by customer token), `POST /api/v1/orders` (create order from cart).

Use the same **models** (Product, Order) as the admin so logic stays in one place. Document the API (e.g. in `docs/API.md` or with OpenAPI/Swagger) for portfolio clarity.

---

## 2. Admin role permissions

Add a **roles** table (e.g. `admin_roles`: id, name) and an **admin_role_id** (or role name) on **admins**. Define permissions per role (e.g. “manage_orders”, “manage_products”, “view_analytics”).

- **Middleware**: Before each admin action, check `$_SESSION['admin_role']` or load permissions and allow/deny.
- **UI**: Hide or disable menu items and buttons the current role cannot use.

Example: “Super Admin” sees everything; “Support” can only view/update orders and contact messages.

---

## 3. Email notifications for orders

On **order placement** and on **status change** (e.g. Shipped, Delivered), send an email to the customer (and optionally to admin).

- Use **PHPMailer** or a service (SendGrid, Mailgun).
- Store templates (e.g. “Order confirmed”, “Order shipped”) as PHP files or in DB.
- Queue emails in a simple `email_queue` table and process via cron or after response to avoid slowing the request.

---

## 4. Dashboard export reports

Allow admins to **export** dashboard data (e.g. orders, sales by month) as **CSV** or **PDF**.

- **CSV**: Build an array from the same queries used on the dashboard, then output headers and rows (escape values).
- **PDF**: Use a library (e.g. TCPDF, Dompdf) to render a simple report (tables, totals).  
Add an “Export” button on the dashboard or orders page that calls a script with optional date range and format.

---

## 5. Activity logs

Log **who did what** in the admin (e.g. “Admin X updated order #123”, “Admin Y deleted product #45”).

- **Table**: `admin_activity_log (id, admin_id, action, entity_type, entity_id, details, created_at)`.
- **Helper**: `logActivity($conn, $adminId, 'order_status_update', 'order', $orderId, ['old' => 'Pending', 'new' => 'Shipped'])`.
- Call the helper from order update, product delete, etc. Optionally show “Recent activity” on the dashboard.

---

## Summary

| Feature           | Benefit for portfolio                          |
|-------------------|-------------------------------------------------|
| REST API          | Shows API design and token-based auth           |
| Role permissions  | Shows access control and authorization          |
| Email notifications | Shows real-world integration and async thinking |
| Export reports    | Shows data export and PDF/CSV handling          |
| Activity logs     | Shows audit trail and accountability            |

Implementing one or two of these and documenting them in the README and docs will strengthen the project for senior roles and client work.
