    </main><!-- /.admin-page-content -->
</div><!-- /.admin-content -->
<?php
if (!function_exists('admin_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars(admin_url('assets/js/common.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
