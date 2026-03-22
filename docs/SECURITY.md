# TechZone.pk – Security Best Practices

Security improvements and example code for the project.

---

## 1. Prepared Statements (SQL Injection Prevention)

Always use prepared statements; never concatenate user input into SQL.

```php
// BAD
$id = $_GET['id'];
$stmt = $conn->query("SELECT * FROM orders WHERE id = " . $id);

// GOOD
$id = (int) $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
```

Dynamic filters (search, status):

```php
$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];
if (!empty($_GET['status'])) {
    $sql .= " AND status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    $sql .= " AND (customer_name LIKE ? OR customer_email LIKE ?)";
    $term = '%' . $_GET['search'] . '%';
    $params[] = $term;
    $params[] = $term;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
```

---

## 2. Password Hashing

Use `password_hash()` and `password_verify()`. Never store plain-text passwords.

```php
// Registration / set password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Login
if (password_verify($inputPassword, $storedHash)) {
    // success
}
```

---

## 3. CSRF Protection

Use `core/security.php`: `csrf_token()`, `csrf_field()`, `require_csrf_token()`.

In forms: `<?= csrf_field() ?>`  
In POST handlers: call `require_csrf_token()` and catch `RuntimeException` to return 403.

---

## 4. Input Validation and Output Escaping

- Validate type and business rules (e.g. `filter_var($email, FILTER_VALIDATE_EMAIL)`).
- Escape output: `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.

---

## 5. Admin Route Protection

- Every admin page: `require_once 'config/auth.php'; checkLogin();`
- Every admin API: `checkLogin(true);` for JSON 401 response.

---

## 6. Session Hardening

- `session_regenerate_id(true)` on login.
- Logout: `$_SESSION = []; session_destroy();`
- Use cookie_httponly, cookie_secure (HTTPS), cookie_samesite in session_start.
