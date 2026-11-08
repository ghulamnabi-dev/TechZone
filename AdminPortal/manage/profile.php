<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel h-100 mb-0">
            <div class="panel-head">
                <span class="avatar avatar-lg"><?php echo htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                <div>
                    <h2 class="panel-title" id="displayName">Loading…</h2>
                    <div class="panel-sub"><span class="role-badge role-<?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?>" id="displayRole"><?= htmlspecialchars($adminRoleLabel, ENT_QUOTES, 'UTF-8') ?></span> · member since <span id="memberSince">—</span></div>
                </div>
            </div>
            <div class="panel-body">
                <dl class="kv mb-4">
                    <dt>Email</dt><dd id="displayEmail">—</dd>
                    <dt>Phone</dt><dd id="displayPhone" class="tabular">—</dd>
                    <dt>Role</dt><dd><?= htmlspecialchars($adminRoleLabel, ENT_QUOTES, 'UTF-8') ?> <span class="muted">· <?= htmlspecialchars(['owner' => 'everything, including settings and staff', 'manager' => 'orders, catalogue, discounts and activity', 'staff' => 'orders, reviews and messages'][$adminRole] ?? '', ENT_QUOTES, 'UTF-8') ?></span></dd>
                    <dt>Last sign-in</dt><dd id="displayLastLogin" class="tabular">—</dd>
                </dl>
                <h3 class="form-section-title">Update profile</h3>
                <form id="updateProfileForm" novalidate>
                    <div id="profileErrorMessage" class="alert alert-danger d-none mb-3"></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="adminName">Name</label>
                            <input type="text" class="form-control" id="adminName" autocomplete="name">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="adminEmail">Email</label>
                            <input type="email" class="form-control" id="adminEmail" autocomplete="email">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="adminPhone">Phone</label>
                            <input type="text" class="form-control" id="adminPhone" placeholder="03xx-xxxxxxx" autocomplete="tel" maxlength="12">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel h-100 mb-0">
            <div class="panel-head">
                <h2 class="panel-title">Change password</h2>
            </div>
            <div class="panel-body">
                <form id="changePasswordForm" novalidate>
                    <div id="passwordErrorMessage" class="alert alert-danger d-none mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label" for="currentPassword">Current password</label>
                        <input type="password" class="form-control" id="currentPassword" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newPassword">New password</label>
                        <input type="password" class="form-control" id="newPassword" minlength="8" maxlength="72" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmPassword">Confirm new password</label>
                        <input type="password" class="form-control" id="confirmPassword" autocomplete="new-password">
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">Update password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/profile.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/profile.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
