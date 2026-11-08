<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

checkCustomerLogin();
$pageTitle = 'My Profile';

$customer_id = (int) $_SESSION['customer_id'];

function load_profile_customer(PDO $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT id, name, email, password, phone, city, address, created_at FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$customer = load_profile_customer($conn, $customer_id);
if (!$customer) {
    session_logout('customer');
    header('Location: ' . site_url('auth/login.php'));
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_profile']) || isset($_POST['change_password']))) {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    }
}

// Handle profile update
if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = v_str($_POST['name'] ?? '', 100, false);
    $email   = v_email($_POST['email'] ?? '');
    $phone   = v_str($_POST['phone'] ?? '', 30);
    $city    = v_str($_POST['city'] ?? '', 100);
    $address = v_str($_POST['address'] ?? '', 500);

    if ($name === null || trim((string) ($_POST['email'] ?? '')) === '') {
        $error = 'Name and email are required';
    } elseif ($email === null) {
        $error = 'Please enter a valid email address';
    } elseif ($phone === null || $city === null || $address === null) {
        $error = 'One of the fields is too long';
    } else {
        try {
            // Check if email is already taken by another customer
            $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customer_id]);
            if ($stmt->fetch()) {
                $error = 'Email is already registered to another account';
            } else {
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, city = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $city, $address, $customer_id]);
                $success = 'Profile updated successfully';
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_email'] = $email;
                $customer = load_profile_customer($conn, $customer_id) ?? $customer;
            }
        } catch (Throwable $e) {
            log_exception($e, 'customer-profile');
            $error = 'We could not update your profile. Please try again.';
        }
    }
}

// Handle password change
if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new_password     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm_password = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = 'All password fields are required';
    } elseif (!hash_equals($new_password, $confirm_password)) {
        $error = 'New passwords do not match';
    } elseif ($policyError = password_policy_error($new_password)) {
        $error = $policyError;
    } elseif (empty($customer['password'])) {
        $error = 'Password feature not available. Please contact support.';
    } elseif (!password_verify($current_password, $customer['password'])) {
        $error = 'Current password is incorrect';
    } else {
        try {
            $conn->beginTransaction();
            $conn->prepare("UPDATE customers SET password = ? WHERE id = ?")
                 ->execute([password_hash($new_password, PASSWORD_DEFAULT), $customer_id]);
            // A password change invalidates any outstanding reset links
            $conn->prepare("DELETE FROM customer_password_resets WHERE customer_id = ?")->execute([$customer_id]);
            $conn->commit();
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $success = 'Password changed successfully';
            $customer = load_profile_customer($conn, $customer_id) ?? $customer;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            log_exception($e, 'customer-password');
            $error = 'We could not change your password. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page">
    <div class="page-title">
        <h1>Your account</h1>
        <span class="page-title__count"><?php echo htmlspecialchars($customer['email']); ?></span>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="panel mb-4">
                <h2 class="panel__title">Profile</h2>
                <div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="pf_name">Full name</label>
                                <input type="text" class="form-control" id="pf_name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="pf_email">Email</label>
                                <input type="email" class="form-control" id="pf_email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="pf_phone">Phone</label>
                                <input type="tel" class="form-control" id="pf_phone" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="pf_city">City</label>
                                <input type="text" class="form-control" id="pf_city" name="city" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pf_address">Address</label>
                            <textarea class="form-control" id="pf_address" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-ink">Save changes</button>
                    </form>
                </div>
            </section>
            
            <section class="panel">
                <h2 class="panel__title">Change password</h2>
                <div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="pf_current_password">Current password</label>
                            <input type="password" class="form-control" id="pf_current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pf_new_password">New password</label>
                            <input type="password" class="form-control" id="pf_new_password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <p class="form-hint">At least 8 characters</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pf_confirm_password">Confirm new password</label>
                            <input type="password" class="form-control" id="pf_confirm_password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
                        </div>
                        <button type="submit" name="change_password" class="btn btn-ink">Change password</button>
                    </form>
                </div>
            </section>
        </div>
        
        <div class="col-lg-4">
            <section class="panel mb-4">
                <h2 class="panel__title">Account</h2>
                <div>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Member since</small>
                        <p class="mb-0"><strong><?php echo date('F Y', strtotime($customer['created_at'])); ?></strong></p>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="<?php echo htmlspecialchars(site_url('account/orders.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-line">Order history</a>
                        <a href="<?php echo htmlspecialchars(site_url('shop/cart.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-line">Cart</a>
                    </div>
                </div>
            </section>
            
            <?php if (!empty($customer['phone']) || !empty($customer['city']) || !empty($customer['address'])): ?>
            <section class="panel">
                <h2 class="panel__title">Delivery address</h2>
                <div>
                    <?php if (!empty($customer['phone'])): ?>
                        <p class="mb-2"><?php echo htmlspecialchars($customer['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customer['city'])): ?>
                        <p class="mb-2"><?php echo htmlspecialchars($customer['city']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customer['address'])): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
