<?php
/**
 * Inline script: ADMIN_PORTAL_BASE + adminUrl() for fetch() to api/* from nested routes.
 */
if (!function_exists('admin_portal_base_path')) {
    require_once __DIR__ . '/../config/paths.php';
}
?>
<script>
window.ADMIN_PORTAL_BASE = <?php echo json_encode(admin_portal_base_path()); ?>;
window.adminUrl = function (rel) {
    rel = String(rel || '').replace(/^\/+/, '');
    var b = window.ADMIN_PORTAL_BASE || '';
    return (b ? b + '/' : '') + rel;
};
</script>
