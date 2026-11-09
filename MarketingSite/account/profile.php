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
require_once __DIR__ . '/../includes/pages.php';
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="container pg-page acct">
    <?php echo pg_account_head('profile', 'Your account', $h($customer['email']) . ', member since ' . $h(date('F Y', strtotime($customer['created_at'])))); ?>

    <?php if ($error): ?>
        <div class="pg-alert pg-alert--error alert-danger" role="alert"><?php echo $h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="pg-alert pg-alert--ok alert-success" role="status"><?php echo $h($success); ?></div>
    <?php endif; ?>

    <div class="acct-grid">
        <section class="acct-panel" aria-labelledby="pf-title">
            <h2 class="acct-panel__title" id="pf-title">Profile and delivery address</h2>
            <p class="acct-panel__sub">Checkout fills these in for you.</p>
            <form method="POST" class="pg-form">
                <?= csrf_field() ?>
                <div class="pg-form__row">
                    <div class="field"><label class="field__label" for="pf_name">Full name</label><input type="text" class="field__input" id="pf_name" name="name" value="<?php echo $h($customer['name']); ?>" autocomplete="name" required></div>
                    <div class="field"><label class="field__label" for="pf_email">Email</label><input type="email" class="field__input" id="pf_email" name="email" value="<?php echo $h($customer['email']); ?>" autocomplete="email" required></div>
                </div>
                <div class="pg-form__row">
                    <div class="field"><label class="field__label" for="pf_phone">Mobile number</label><input type="tel" class="field__input" id="pf_phone" name="phone" value="<?php echo $h($customer['phone'] ?? ''); ?>" placeholder="0300-1234567" autocomplete="tel"></div>
                    <div class="field"><label class="field__label" for="pf_city">City</label><input type="text" class="field__input" id="pf_city" name="city" value="<?php echo $h($customer['city'] ?? ''); ?>" autocomplete="address-level2"></div>
                </div>
                <div class="field"><label class="field__label" for="pf_address">Address</label><textarea class="field__input" id="pf_address" name="address" rows="3" autocomplete="street-address"><?php echo $h($customer['address'] ?? ''); ?></textarea></div>
                <div><button type="submit" name="update_profile" class="btn--primary">Save changes</button></div>
            </form>
        </section>

        <section class="acct-panel" aria-labelledby="pw-title">
            <h2 class="acct-panel__title" id="pw-title">Change password</h2>
            <p class="acct-panel__sub">You stay signed in on this device.</p>
            <form method="POST" class="pg-form">
                <?= csrf_field() ?>
                <div class="field"><label class="field__label" for="pf_current_password">Current password</label><input type="password" class="field__input" id="pf_current_password" name="current_password" required autocomplete="current-password"></div>
                <div class="field"><label class="field__label" for="pf_new_password">New password</label><input type="password" class="field__input" id="pf_new_password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password"><span class="field__hint">At least 8 characters.</span></div>
                <div class="field"><label class="field__label" for="pf_confirm_password">Confirm new password</label><input type="password" class="field__input" id="pf_confirm_password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password"></div>
                <div><button type="submit" name="change_password" class="btn--secondary">Change password</button></div>
            </form>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
