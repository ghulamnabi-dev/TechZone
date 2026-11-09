    </main><!-- /.admin-page-content -->
</div><!-- /.admin-content -->
<?php
if (!function_exists('admin_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
$tzE = $tzE ?? static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!-- ⌘K search: api/search.php + recent searches + jump-to pages (assets/js/common.js initCommandPalette) -->
<div class="cmdk" id="cmdk" hidden>
    <div class="cmdk-backdrop" data-cmdk-close></div>
    <div class="cmdk-panel" role="dialog" aria-modal="true" aria-labelledby="cmdkLabel">
        <label class="cmdk-input" id="cmdkLabel">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" id="cmdkInput" placeholder="<?= function_exists('is_seller') && is_seller() ? 'Search your orders and products…' : 'Search orders, products, customers…' ?>" autocomplete="off" spellcheck="false"
                   role="combobox" aria-expanded="true" aria-controls="cmdkList" aria-autocomplete="list">
            <kbd>Esc</kbd>
        </label>
        <div class="cmdk-list" id="cmdkList" role="listbox" aria-label="Results"></div>
        <div class="cmdk-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> to move</span>
            <span><kbd>↵</kbd> to open</span>
            <span class="ms-auto"><?= function_exists('is_seller') && is_seller() ? 'Order numbers and SKUs work too' : 'Order numbers, SKUs and phone numbers work too' ?></span>
        </div>
    </div>
</div>

<!-- Keyboard shortcuts sheet -->
<div class="modal fade" id="shortcutsModal" tabindex="-1" aria-labelledby="shortcutsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="shortcutsTitle">Keyboard shortcuts</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="shortcut-list">
                    <div><dt>Search</dt><dd><kbd data-kbd-mod>⌘K</kbd> or <kbd>/</kbd></dd></div>
                    <div><dt>Go to Home</dt><dd><kbd>G</kbd> then <kbd>H</kbd></dd></div>
                    <div><dt>Go to Orders</dt><dd><kbd>G</kbd> then <kbd>O</kbd></dd></div>
                    <div><dt>Go to Products</dt><dd><kbd>G</kbd> then <kbd>P</kbd></dd></div>
                    <?php if (!(function_exists('is_seller') && is_seller())): ?><div><dt>Go to Customers</dt><dd><kbd>G</kbd> then <kbd>C</kbd></dd></div><?php endif; ?>
                    <div><dt>Save changes</dt><dd><kbd data-kbd-mod>⌘S</kbd> when the save bar is showing</dd></div>
                    <div><dt>Show this sheet</dt><dd><kbd>?</kbd></dd></div>
                    <div><dt>Close a dialog</dt><dd><kbd>Esc</kbd></dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $tzE(admin_url('assets/js/common.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/common.js') ?>"></script>
</body>
</html>
