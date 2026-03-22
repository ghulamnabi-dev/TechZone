<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';

checkCustomerLogin();
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';

$customer_id = $_SESSION['customer_id'];

// Get customer details
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required';
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
                // Reload customer data
                $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$customer_id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check current password if password column exists
        if (isset($customer['password']) && !empty($customer['password'])) {
            if (!password_verify($current_password, $customer['password'])) {
                $error = 'Current password is incorrect';
            } else {
                try {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $customer_id]);
                    $success = 'Password changed successfully';
                } catch (Exception $e) {
                    $error = 'Error changing password: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Password feature not available. Please contact support.';
        }
    }
}
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
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
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
