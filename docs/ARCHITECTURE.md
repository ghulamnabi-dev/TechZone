# TechZone.pk – Project Architecture (MVC-Style)

This document describes a **cleaner, MVC-style architecture** you can adopt to make the codebase more maintainable and portfolio-ready. The current project uses a **page-based + API** structure; the structure below is a target you can migrate toward incrementally.

---

## Recommended Folder Structure

```
TechZone/
├── config/
│   ├── database.php      # PDO connection (from core/)
│   ├── config.php        # App config (base URL, env, debug)
│   └── auth.php          # Auth config / session defaults
│
├── models/
│   ├── Product.php       # Product CRUD, search, category filter
│   ├── Order.php         # Order CRUD, status update, items
│   ├── Category.php      # Category CRUD, product count
│   ├── User.php          # Admin + Customer (login, roles)
│   └── ContactMessage.php
│
├── controllers/
│   ├── ProductController.php
│   ├── OrderController.php
│   ├── CategoryController.php
│   ├── AuthController.php
│   └── DashboardController.php
│
├── views/
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── products.php
│   │   ├── orders.php
│   │   ├── categories.php
│   │   └── layout.php
│   └── marketing/
│       ├── home.php
│       ├── products.php
│       └── layout.php
│
├── admin/                 # Entry points (thin)
│   ├── index.php          # Login
│   ├── dashboard.php      # require layout + DashboardController
│   ├── products.php
│   └── api/               # JSON API (calls controllers)
│       ├── products.php
│       ├── orders.php
│       └── dashboard_stats.php
│
├── public/                # Marketing site entry + assets
│   ├── index.php
│   ├── products.php
│   ├── assets/
│   └── api/
│
├── core/                  # Shared helpers (keep existing)
│   ├── database.php
│   ├── security.php       # CSRF, sanitize
│   └── inventory.php
│
└── docs/
    ├── ARCHITECTURE.md
    ├── SECURITY.md
    └── AUTHENTICATION.md
```

---

## How Controllers, Models, and Views Work

### 1. **Models** (data layer)

- **One class per main entity** (Product, Order, Category, User).
- Responsibilities:
  - Talk to the database (using a single `PDO` instance from config).
  - Use **prepared statements** for all queries.
  - Return arrays or simple value objects, not HTML.
- Example flow:

```php
// models/Order.php
class Order {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getAll(?string $status = null, ?string $search = null): array {
        $sql = "SELECT * FROM orders WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
        if ($search) { $sql .= " AND (customer_name LIKE ? OR customer_email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= " ORDER BY order_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
```

### 2. **Controllers** (logic layer)

- Receive request (e.g. from `api/orders.php` or an admin page).
- Call **models** for data; optionally use **core/security** (e.g. CSRF, validation).
- For **API**: return JSON.
- For **pages**: set variables and include a **view** (or return data for a single-page JS app).

Example API controller usage:

```php
// api/orders.php (entry point)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/Order.php';
checkLogin(true);
header('Content-Type: application/json');
$orderModel = new Order($conn);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if ($id) {
        $order = $orderModel->getById($id);
        $order['items'] = $orderModel->getItems($id);
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        $orders = $orderModel->getAll($_GET['status'] ?? null, $_GET['search'] ?? null);
        echo json_encode(['success' => true, 'orders' => $orders]);
    }
}
```

### 3. **Views** (presentation layer)

- **No business logic**; only PHP for looping and escaping.
- Receive variables set by the controller (e.g. `$orders`, `$stats`).
- Output HTML; use a **layout** (e.g. `layout.php`) for header/footer.

```php
// views/admin/orders.php
<?php foreach ($orders as $order): ?>
<tr>
    <td>#<?= (int)$order['id'] ?></td>
    <td><?= htmlspecialchars($order['customer_name']) ?></td>
    ...
</tr>
<?php endforeach; ?>
```

### 4. **Communication flow**

1. **Request** → entry script (`admin/orders.php` or `admin/api/orders.php`).
2. Entry script loads **config** (DB, auth), then **controller** or inline logic.
3. **Controller** uses **models** to get/update data.
4. **Controller** either:
   - **API**: `echo json_encode(...);`
   - **Page**: `$orders = $orderModel->getAll(...); include 'views/admin/orders.php';` (or include layout that includes view).

---

## Migrating the Current Project

- You don’t have to move everything at once. Suggested order:
  1. Introduce **config** and keep **core/** as is.
  2. Add **models** for one module (e.g. Order). Use them from existing `api/orders.php`.
  3. Replace raw SQL in that API with model calls.
  4. Add a **controller** class for that module if you want (optional).
  5. Extract **views** from existing admin pages (e.g. move HTML from `orders.php` into `views/admin/orders.php` and include it).
  6. Repeat for Products, Categories, Dashboard, etc.

This keeps the app running while you refactor toward a clear MVC-style separation.
