<?php
$pageTitle = 'Profile Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="form-container">
            <h5 class="mb-4">Profile Information</h5>
            <div class="mb-3">
                <p><strong>Name:</strong> <span id="displayName">Loading...</span></p>
                <p><strong>Email:</strong> <span id="displayEmail">Loading...</span></p>
                <p><strong>Phone:</strong> <span id="displayPhone">Loading...</span></p>
                <p><strong>Member Since:</strong> <span id="memberSince">Loading...</span></p>
            </div>
            
            <h5 class="mb-4 mt-4">Update Profile</h5>
            <form id="updateProfileForm" novalidate>
                <div id="profileErrorMessage" class="alert alert-danger d-none mb-3"></div>
                
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" id="adminName" autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="adminEmail" autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" id="adminPhone" placeholder="03xx-xxxxxxx" autocomplete="tel" maxlength="12">
                </div>
                <button type="submit" class="btn btn-primary w-100">Update Profile</button>
            </form>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="form-container">
            <h5 class="mb-4">Change Password</h5>
            <form id="changePasswordForm" novalidate>
                <div id="passwordErrorMessage" class="alert alert-danger d-none mb-3"></div>
                
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="currentPassword" autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" id="newPassword" autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirmPassword" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-warning w-100">Change Password</button>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/profile.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

