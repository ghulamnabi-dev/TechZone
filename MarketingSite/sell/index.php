<?php
/** Sell on TechZone: the landing page for shops (docs/STOREFRONT.md "Marketplace"). */
$pageTitle = 'Sell on TechZone';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pages.php';

$sellerCount = (int) $conn->query("SELECT COUNT(*) FROM sellers WHERE status = 'active' AND id <> 1")->fetchColumn();
$apply = site_url('sell/apply.php');
$faq = [
    ['Who can apply?', 'Any registered computer shop with a physical counter in Lahore\'s markets: Hafeez Centre, Hall Road, Saddar and nearby plazas. Shops in other cities can apply too; we visit Lahore shops first.'],
    ['What does it cost?', 'Nothing to join and nothing to list. We keep 8% of each sale, including delivery we charge the customer. There are no monthly fees, and you can pause your shop at any time.'],
    ['When do I get paid?', 'Every Monday, for orders delivered by the previous Sunday, to your bank account or JazzCash wallet. Your weekly statement shows each order, our 8% and the amount paid.'],
    ['Who collects the cash on delivery?', 'Our couriers collect it and we settle it with your Monday payout, so you never chase a courier for cash.'],
    ['Who handles returns and warranty?', 'Customers return through TechZone within 7 days. We check the part at Hafeez Centre; if the fault is real, the return comes out of that order. Warranty claims go to the brand\'s official distributor as usual, and we help the customer through it.'],
    ['Do I need an NTN?', 'No. An NTN speeds up verification and lets us show "Tax registered" on your page, but a CNIC and a shop you can show us are enough to start.'],
];
?>
<div class="sell">
  <section class="sell-hero pg-band-black">
    <div class="container sell-hero__row">
      <div class="sell-hero__text">
        <h1 class="sell-hero__title">Sell on TechZone</h1>
        <p class="sell-hero__lead">Your stock on the rate board buyers check before they walk into Hafeez Centre. We bring the orders, deliver across Pakistan and collect the cash; you keep the counter.</p>
        <div class="sell-hero__acts">
          <a class="btn--on-black" href="<?= pg_e($apply) ?>">Apply to sell</a>
          <a class="sell-hero__link" href="<?= pg_e(site_url('sellers/index.php')) ?>">See the <?= $sellerCount ?> shops already selling</a>
        </div>
      </div>
      <dl class="sell-terms">
        <div><dt>Fee per sale</dt><dd class="pg-num">8%</dd></div>
        <div><dt>Listing fee</dt><dd class="pg-num">Rs 0</dd></div>
        <div><dt>Payouts</dt><dd class="pg-num">Every Monday</dd></div>
      </dl>
    </div>
  </section>

  <section class="container sell-section">
    <div class="sell-cols">
      <div>
        <h2 class="pg-h2">Who it's for</h2>
        <p class="sell-p">Computer shops in Hafeez Centre, Hall Road and Saddar that already sell components, monitors and peripherals over the counter and on WhatsApp, and want the same stock in front of buyers outside Lahore without running a website.</p>
        <p class="sell-p pg-muted">We accept shops with a physical counter, original stock with the brand's warranty, and someone who answers the phone during market hours.</p>
      </div>
      <div>
        <h2 class="pg-h2">What you get</h2>
        <ul class="sell-list">
          <li><strong>Listing on the rate board.</strong> Your prices appear on rates, the catalogue and the PC builder, marked "Sold by" your shop.</li>
          <li><strong>Orders in one place.</strong> A seller account shows only your orders and your lines, ready to pack.</li>
          <li><strong>Delivery and cash collection.</strong> Our couriers pick up from your shop and collect cash on delivery anywhere in Pakistan.</li>
          <li><strong>Payouts every Monday.</strong> Delivered orders, minus 8%, with a statement you can check line by line.</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="sell-steps pg-band-signal">
    <div class="container">
      <h2 class="pg-h2">How it works</h2>
      <ol class="sell-steps__list">
        <li><span class="sell-steps__n pg-num">1</span><h3>Apply</h3><p>Fill in the form: shop name, market, what you sell. It takes five minutes.</p></li>
        <li><span class="sell-steps__n pg-num">2</span><h3>Verify</h3><p>We call within two working days and visit your counter with your CNIC and, if you have one, your NTN.</p></li>
        <li><span class="sell-steps__n pg-num">3</span><h3>List</h3><p>We open your seller account. Add parts with today's price and stock, or send us your list on WhatsApp.</p></li>
        <li><span class="sell-steps__n pg-num">4</span><h3>Sell</h3><p>Pack orders when they come in; our courier collects. Your payout arrives every Monday.</p></li>
      </ol>
    </div>
  </section>

  <section class="container sell-section sell-faq" aria-labelledby="sell-faq-title">
    <h2 class="pg-h2" id="sell-faq-title">Questions shops ask</h2>
    <div class="help-faq">
      <?php foreach ($faq as [$q, $a]): ?>
        <details class="help-qa"><summary><?= pg_e($q) ?></summary><p><?= pg_e($a) ?></p></details>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="container sell-section">
    <div class="sel-cta pg-band-black">
      <div>
        <h2 class="sel-cta__title">Ready to list your shop?</h2>
        <p>Apply now and we will call you within two working days.</p>
      </div>
      <a class="btn--on-black" href="<?= pg_e($apply) ?>">Apply to sell</a>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
