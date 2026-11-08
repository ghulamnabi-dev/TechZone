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

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="profile-header text-center text-white">
                    <div class="profile-avatar mx-auto mb-3">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="mb-1"><?php echo htmlspecialchars($customer['name']); ?></h3>
                    <p class="mb-0" style="opacity: 0.9;"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($customer['email']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Member Since</small>
                        <p class="mb-0"><strong><i class="fas fa-calendar me-2"></i><?php echo date('F Y', strtotime($customer['created_at'])); ?></strong></p>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="<?php echo htmlspecialchars(site_url('account/orders.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary"><i class="fas fa-shopping-bag me-2"></i>View Order History</a>
                        <a href="<?php echo htmlspecialchars(site_url('shop/cart.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary"><i class="fas fa-shopping-cart me-2"></i>View Cart</a>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($customer['phone']) || !empty($customer['city']) || !empty($customer['address'])): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Contact Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($customer['phone'])): ?>
                        <p class="mb-2"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($customer['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customer['city'])): ?>
                        <p class="mb-2"><i class="fas fa-city me-2 text-muted"></i><?php echo htmlspecialchars($customer['city']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customer['address'])): ?>
                        <p class="mb-0"><i class="fas fa-home me-2 text-muted"></i><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
