<?php
/** Deals: this week's price drops (product cards) and the promotion codes running today. */
$pageTitle = 'Price drops this week';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';

$drops = price_drops($conn, 48, 7);
$promos = pg_live_promotions($conn);
$cardPromos = storefront_active_promotions($conn, 10);
?>
<div class="deals pg-page">
  <div class="container">
    <header class="deals-head">
      <div class="pg-head">
        <h1 class="pg-title">Price drops this week</h1>
        <p class="pg-lead">Parts whose rate fell in the last seven days, biggest drop first. Rates move with the market, so a drop can reverse next week.</p>
      </div>
      <?php if ($drops): ?>
      <p class="deals-head__fig"><span class="pg-num"><?= count($drops) ?></span> parts cheaper than last week</p>
      <?php endif; ?>
    </header>

    <?php if ($drops): ?>
      <div class="pg-grid-cards deals-grid"><?= pg_cards($drops, $cardPromos) ?></div>
    <?php else: ?>
      <div class="pg-empty">
        <h2>No price drops this week</h2>
        <p>Rates have held or gone up since last week. Check today's board for the full picture, or save parts to your wishlist and look again in a few days.</p>
        <a class="btn--primary" href="<?= pg_e(site_url('rates.php')) ?>">See today's rates</a>
      </div>
    <?php endif; ?>
  </div>

  <section class="deals-codes" aria-labelledby="codes-title">
    <div class="container">
      <div class="section-head">
        <h2 class="section-head__title" id="codes-title">Codes you can use today</h2>
        <a class="section-head__link" href="<?= pg_e(site_url('help/payments.php')) ?>">How discounts work</a>
      </div>
      <?php if ($promos): ?>
      <ul class="deals-codes__list">
        <?php foreach ($promos as $pr): ?>
        <li class="deals-code">
          <span class="deals-code__code"><?= pg_e($pr['code']) ?></span>
          <div class="deals-code__text">
            <p class="deals-code__title"><?= pg_e($pr['title']) ?></p>
            <p class="deals-code__terms"><?= pg_e($pr['sentence']) ?><?php if ($pr['ends']): ?> Ends <?= pg_e($pr['ends']) ?>.<?php endif; ?></p>
          </div>
          <a class="deals-code__link" href="<?= pg_e($pr['link']) ?>"><?= $pr['kind'] === 'free_shipping' ? 'Shop' : 'See what it covers' ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="deals-codes__how">Type the code in your cart before checkout. It is checked again when you place the order.</p>
      <?php else: ?>
        <p class="pg-muted">No codes are running right now. Drops above are already in the price, no code needed.</p>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
