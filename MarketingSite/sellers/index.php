<?php
/** Sellers directory: TechZone and the vetted shops selling through it. */
$pageTitle = 'Sellers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pages.php';

$sellers = $conn->query("SELECT s.*, COUNT(p.id) AS products, SUM(p.inventory > 0) AS in_stock
    FROM sellers s LEFT JOIN products p ON p.seller_id = s.id AND " . catalog_visible_sql() . "
    WHERE s.status = 'active'
    GROUP BY s.id ORDER BY s.id = 1 DESC, COUNT(p.id) DESC, s.name")->fetchAll(PDO::FETCH_ASSOC);
$markets = array_unique(array_column($sellers, 'market'));
?>
<div class="sel pg-page">
  <div class="container">
    <header class="sel-head">
      <div class="pg-head">
        <h1 class="pg-title">Sellers</h1>
        <p class="pg-lead"><?= count($sellers) ?> shops from <?= pg_e(pg_list_and($markets)) ?>. Every seller is visited and checked before listing, and every order is paid through TechZone, with cash on delivery and the same 7-day returns.</p>
      </div>
      <a class="btn--secondary" href="<?= pg_e(site_url('sell/index.php')) ?>">Sell on TechZone</a>
    </header>

    <?php if (!$sellers): ?>
      <div class="pg-empty"><h2>No sellers listed right now</h2><p>Every part in the catalogue is sold by TechZone directly.</p></div>
    <?php else: ?>
    <table class="sel-table">
      <caption class="visually-hidden">Sellers on TechZone</caption>
      <thead>
        <tr><th scope="col">Shop</th><th scope="col">Market</th><th scope="col" class="sel-hide-sm">City</th><th scope="col" class="sel-r">Rating</th><th scope="col" class="sel-r">Products</th><th scope="col" class="sel-r sel-hide-sm">On TechZone since</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sellers as $s): ?>
        <tr>
          <th scope="row" class="sel-name">
            <a href="<?= pg_e(site_url('sellers/view.php?slug=' . rawurlencode($s['slug']))) ?>"><?= pg_e($s['name']) ?></a>
            <?php if ((int) $s['id'] === 1): ?><span class="sel-house">Our own shop</span><?php endif; ?>
            <span class="sel-about"><?= pg_e($s['about'] ?? '') ?></span>
          </th>
          <td><?= pg_e($s['market']) ?></td>
          <td class="sel-hide-sm"><?= pg_e($s['city']) ?></td>
          <td class="sel-r"><span class="pg-num sel-rating"><?= pg_e(number_format((float) $s['rating'], 1)) ?></span><span class="sel-of"> / 5</span></td>
          <td class="sel-r"><span class="pg-num sel-count"><?= (int) $s['products'] ?></span></td>
          <td class="sel-r sel-hide-sm"><?= pg_e(pg_month_year($s['joined_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <section class="sel-cta pg-band-black">
      <div>
        <h2 class="sel-cta__title">Have a shop in Hafeez Centre, Hall Road or Saddar?</h2>
        <p>List your stock next to ours. 8% per sale, no listing fee, payouts every Monday.</p>
      </div>
      <a class="btn--on-black" href="<?= pg_e(site_url('sell/apply.php')) ?>">Apply to sell</a>
    </section>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
