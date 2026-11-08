<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';

// Logs out the customer identity only (clears cart/promo too); an admin signed in
// on the same browser stays signed in.
session_logout('customer');
$home = site_url('index.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <script>
        if (typeof Storage !== 'undefined') {
            localStorage.removeItem('customer_session');
        }
        window.location.href = <?php echo json_encode($home); ?>;
    </script>
</head>
<body>
    <p>Logging out...</p>
</body>
</html>
