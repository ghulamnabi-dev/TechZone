<?php
/** One seller: their shop details and the parts they sell, filterable by category. */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/catalog.php';

$slug = is_string($_GET['slug'] ?? null) ? substr($_GET['slug'], 0, 80) : '';
$st = $conn->prepare("SELECT * FROM sellers WHERE slug = ? AND status = 'active'");
$st->execute([$slug]);
$seller = $st->fetch(PDO::FETCH_ASSOC);
if (!$seller) {
    http_response_code(404);
}
$pageTitle = $seller ? $seller['name'] : 'Seller not found';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pages.php';

if (!$seller): ?>
<div class="pg-page"><div class="container">
  <div class="pg-empty">
    <h1 class="pg-title">We could not find that seller</h1>
    <p>The link may be old, or the shop may have paused selling on TechZone. Every active shop is listed in the directory.</p>
    <a class="btn--primary" href="<?= pg_e(site_url('sellers/index.php')) ?>">All sellers</a>
  </div>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; exit; endif;

$products = pg_products($conn, 'p.seller_id = ?', [(int) $seller['id']], 'p.inventory > 0 DESC, p.price DESC');
$cats = [];
foreach ($products as $p) {
    $cats[$p['category_slug']] = ['slug' => $p['category_slug'], 'name' => $p['category_name'], 'n' => ($cats[$p['category_slug']]['n'] ?? 0) + 1];
}
$cat = is_string($_GET['cat'] ?? null) && isset($cats[$_GET['cat']]) ? $_GET['cat'] : '';
$shown = $cat === '' ? $products : array_values(array_filter($products, static fn($p) => $p['category_slug'] === $cat));
$base = 'sellers/view.php?slug=' . rawurlencode($seller['slug']);
$wa = pg_whatsapp_url((string) $seller['whatsapp'], 'Hello, I found your shop on TechZone. ');
$phoneHref = 'tel:' . preg_replace('/[^\d+]/', '', (string) $seller['phone']);
?>
<div class="sel-page">
  <header class="sel-hero pg-band-black">
    <div class="container sel-hero__row">
      <div>
        <ol class="pg-crumbs sel-hero__crumbs"><li><a href="<?= pg_e(site_url('sellers/index.php')) ?>">Sellers</a></li><li aria-current="page"><?= pg_e($seller['name']) ?></li></ol>
        <h1 class="sel-hero__name"><?= pg_e($seller['name']) ?></h1>
        <p class="sel-hero__about"><?= pg_e($seller['about'] ?? '') ?></p>
        <p class="sel-hero__since">Sold on TechZone since <?= pg_e(pg_month_year($seller['joined_at'])) ?></p>
      </div>
      <dl class="sel-facts">
        <div><dt>Rating</dt><dd><span class="pg-num sel-facts__big"><?= pg_e(number_format((float) $seller['rating'], 1)) ?></span> / 5</dd></div>
        <div><dt>Parts listed</dt><dd><span class="pg-num sel-facts__big"><?= count($products) ?></span></dd></div>
        <div class="sel-facts__wide"><dt>Shop</dt><dd><?= pg_e($seller['address']) ?>, <?= pg_e($seller['city']) ?><span class="sel-facts__market"><?= pg_e($seller['market']) ?></span></dd></div>
        <div class="sel-facts__wide sel-facts__acts">
          <a class="btn--on-black" href="<?= pg_e($wa) ?>" rel="noopener" target="_blank">Ask on WhatsApp</a>
          <a class="sel-call" href="<?= pg_e($phoneHref) ?>"><?= pg_e($seller['phone']) ?></a>
        </div>
      </dl>
    </div>
  </header>

  <div class="container pg-page">
    <div class="section-head">
      <h2 class="section-head__title"><?= $cat === '' ? 'Everything they sell' : pg_e($cats[$cat]['name']) ?></h2>
      <span class="sel-count-line"><?= count($shown) ?> <?= count($shown) === 1 ? 'part' : 'parts' ?></span>
    </div>
    <?php if (count($cats) > 1): ?>
    <nav class="sel-chips" aria-label="Category">
      <a class="chip<?= $cat === '' ? ' chip--on' : '' ?>" href="<?= pg_e(site_url($base)) ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>>All <?= count($products) ?></a>
      <?php foreach ($cats as $c): ?>
        <a class="chip<?= $cat === $c['slug'] ? ' chip--on' : '' ?>" href="<?= pg_e(site_url($base . '&cat=' . rawurlencode($c['slug']))) ?>"<?= $cat === $c['slug'] ? ' aria-current="true"' : '' ?>><?= pg_e($c['name']) ?> <?= (int) $c['n'] ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($shown): ?>
      <div class="pg-grid-cards"><?= pg_cards($shown, storefront_active_promotions($conn, 10)) ?></div>
    <?php else: ?>
      <div class="pg-empty"><h2>Nothing listed yet</h2><p><?= pg_e($seller['name']) ?> has no parts on sale right now. Ask them on WhatsApp what is in stock at the shop.</p></div>
    <?php endif; ?>
    <p class="sel-note">Orders from <?= pg_e($seller['name']) ?> are paid through TechZone: cash on delivery or JazzCash, 7-day returns and warranty claims handled by us.</p>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
