<?php
$pageTitle = 'About';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/pages.php';
$store = pg_store();
$sellerCount = (int) $conn->query("SELECT COUNT(*) FROM sellers WHERE status = 'active' AND id <> 1")->fetchColumn();
$partCount = (int) $conn->query('SELECT COUNT(*) FROM products p WHERE ' . catalog_visible_sql())->fetchColumn();
?>
<div class="pg-about">
  <section class="pg-about__hero pg-band-black">
    <div class="container">
      <h1 class="pg-about__title">A shop in Hafeez Centre since <?= (int) $store['since'] ?>.</h1>
      <p class="pg-about__lead">TechZone is run by the people behind the counter at <?= pg_e($store['address_line']) ?>. The website does what the counter does: tells you today's rate, checks the parts fit, and sends them to you.</p>
    </div>
  </section>

  <div class="container pg-about__body">
    <dl class="pg-about__facts">
      <div><dt>At the shop since</dt><dd class="pg-num"><?= (int) $store['since'] ?></dd></div>
      <div><dt>Parts on the board today</dt><dd class="pg-num"><?= $partCount ?></dd></div>
      <div><dt>Vetted shops selling with us</dt><dd class="pg-num"><?= $sellerCount ?></dd></div>
    </dl>

    <div class="pg-about__text">
      <section>
        <h2 class="pg-h3">Who runs it</h2>
        <p>TechZone Traders is a family-run computer shop on the second floor of Hafeez Centre, Gulberg III. We sell processors, graphics cards, boards, memory, storage, monitors and peripherals over the counter, and since 2025 online to the rest of Pakistan. The same team answers the phone, packs the orders and handles warranty claims.</p>
      </section>
      <section>
        <h2 class="pg-h3">Why rates first</h2>
        <p>Component prices in Lahore move every week with the dollar and with stock. Buyers used to walk Hafeez Centre and Hall Road asking each shop for its rate. We put ours on a board, with last week's figure next to it, so you can see whether today is a good day to buy.</p>
      </section>
      <section>
        <h2 class="pg-h3">The marketplace</h2>
        <p>Some parts we don't stock are sold by other shops from Hafeez Centre, Hall Road and DHA. We visit every shop before it can list, check that its stock is original with the brand's warranty, and keep the ordering, payment, delivery and returns in our hands, so buying from them works exactly like buying from us. Every card says who it is sold by.</p>
      </section>
      <section>
        <h2 class="pg-h3">What we won't do</h2>
        <p>We don't sell refurbished parts as new, grey-import cards without saying so, or parts we can't stand behind. If a combination won't work, the builder says so before you pay.</p>
      </section>
      <p class="pg-about__links"><a class="btn--primary" href="<?= pg_e(site_url('store.php')) ?>">Visit the shop</a> <a class="btn--secondary" href="<?= pg_e(site_url('sellers/index.php')) ?>">Meet the sellers</a></p>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
