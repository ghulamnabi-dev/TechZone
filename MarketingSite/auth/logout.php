<?php
session_start();
session_destroy();
require_once __DIR__ . '/../config/paths.php';
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
