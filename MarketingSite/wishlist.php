<?php
/** Wishlist: saved parts (session for guests, the account once signed in). core/wishlist.php. */
$pageTitle = 'Wishlist';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';
require_once __DIR__ . '/../core/wishlist.php';

$ids = wishlist_ids($conn);
$items = [];
if ($ids) {
    $byId = [];
    foreach (pg_products($conn, 'p.id IN (' . catalog_placeholders($ids) . ')', $ids) as $r) {
        $byId[(int) $r['id']] = $r;
    }
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $items[] = $byId[$id];
        }
    }
}
$guest = !isset($_SESSION['customer_id']);
$drops = count(array_filter($items, static fn($r) => ($r['change']['direction'] ?? '') === 'drop'));
?>
<div class="wl pg-page" id="wishlist">
  <div class="container">
    <header class="wl-head">
      <div class="pg-head">
        <h1 class="pg-title">Wishlist</h1>
        <p class="pg-lead" id="wlLead"><?php if ($items): ?><?= count($items) ?> saved <?= count($items) === 1 ? 'part' : 'parts' ?><?php if ($drops): ?>, <?= $drops ?> cheaper than last week<?php endif; ?>.<?php else: ?>Nothing saved yet.<?php endif; ?>
          <?php if ($guest && $items): ?>Saved on this device. <a class="pg-link" href="<?= pg_e(site_url('auth/login.php?redirect=' . rawurlencode(site_url('wishlist.php')))) ?>">Sign in</a> to keep them on your account.<?php endif; ?></p>
      </div>
      <?php if (count($items) >= 2): ?>
        <a class="btn--secondary" href="<?= pg_e(site_url('compare.php?ids=' . implode(',', array_slice(array_map(static fn($r) => (int) $r['id'], $items), 0, 4)))) ?>">Compare the first <?= min(4, count($items)) ?></a>
      <?php endif; ?>
    </header>

    <div class="pg-grid-cards wl-grid" id="wlGrid"<?= $items ? '' : ' hidden' ?>>
      <?php foreach ($items as $p): ?>
        <div class="wl-item" data-wl-item="<?= (int) $p['id'] ?>">
          <?= pg_cards([$p]) ?>
          <button type="button" class="wl-remove" data-wl-remove="<?= (int) $p['id'] ?>">Remove from wishlist</button>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pg-empty wl-empty" id="wlEmpty"<?= $items ? ' hidden' : '' ?>>
      <h2>Your wishlist is empty</h2>
      <p>Tap the heart on any part to save it here. Saved parts show this week's price change, so you can wait for a drop before you buy.</p>
      <a class="btn--primary" href="<?= pg_e(site_url('rates.php')) ?>">See today's rates</a>
      <a class="btn--secondary" href="<?= pg_e(site_url('deals.php')) ?>">Price drops</a>
    </div>
  </div>
</div>
<script src="<?= pg_e(marketing_script_url('pages.js')) ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
