<?php
/** Seller application form → api/seller_apply.php (CSRF, throttled) → seller_applications. */
$pageTitle = 'Apply to sell';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pages.php';

$markets = ['Hafeez Centre', 'Hall Road', 'Saddar', 'Other'];
$categories = ['Processors', 'Motherboards', 'Memory', 'Graphics cards', 'Storage', 'Power supplies', 'Cases and cooling', 'Monitors', 'Peripherals', 'Networking', 'Laptops', 'Furniture'];
?>
<div class="sell-apply pg-page">
  <div class="container sell-apply__grid">
    <div class="sell-apply__main">
      <ol class="pg-crumbs"><li><a href="<?= pg_e(site_url('sell/index.php')) ?>">Sell on TechZone</a></li><li aria-current="page">Apply</li></ol>
      <h1 class="pg-title">Apply to sell</h1>
      <p class="pg-lead">Tell us about your shop. We call within two working days to arrange a visit.</p>

      <form class="pg-form sell-form" id="sellerApplyForm" method="post" action="<?= pg_e(site_url('api/seller_apply.php')) ?>" data-pg-form="sellerApplyDone" novalidate>
        <?= csrf_field() ?>
        <div class="pg-form__row">
          <div class="field"><label class="field__label" for="sa_shop">Shop name</label><input class="field__input" id="sa_shop" name="shop_name" maxlength="120" autocomplete="organization" required></div>
          <div class="field"><label class="field__label" for="sa_owner">Owner's name</label><input class="field__input" id="sa_owner" name="owner_name" maxlength="100" autocomplete="name" required></div>
        </div>
        <div class="pg-form__row">
          <div class="field"><label class="field__label" for="sa_phone">Mobile number</label><input class="field__input" id="sa_phone" name="phone" type="tel" inputmode="tel" placeholder="0300-1234567" maxlength="12" autocomplete="tel" required><span class="field__hint">We call this number to arrange the visit.</span></div>
          <div class="field"><label class="field__label" for="sa_email">Email</label><input class="field__input" id="sa_email" name="email" type="email" maxlength="150" autocomplete="email" required></div>
        </div>
        <div class="pg-form__row">
          <div class="field"><label class="field__label" for="sa_market">Market</label>
            <select class="field__input" id="sa_market" name="market" required>
              <option value="">Choose a market</option>
              <?php foreach ($markets as $m): ?><option><?= pg_e($m) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label class="field__label" for="sa_city">City</label><input class="field__input" id="sa_city" name="city" value="Lahore" maxlength="80" autocomplete="address-level2" required></div>
        </div>
        <fieldset class="pg-fieldset">
          <legend>What do you sell?</legend>
          <div class="pg-checks">
            <?php foreach ($categories as $i => $c): ?>
              <label class="pg-check"><input type="checkbox" name="categories[]" value="<?= pg_e($c) ?>"> <?= pg_e($c) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="field"><label class="field__label" for="sa_ntn">NTN <span class="pg-muted">(optional)</span></label><input class="field__input sell-form__short" id="sa_ntn" name="ntn" maxlength="20" placeholder="1234567-8"></div>
        <div class="field"><label class="field__label" for="sa_msg">Anything else <span class="pg-muted">(optional)</span></label><textarea class="field__input" id="sa_msg" name="message" maxlength="2000" rows="4" placeholder="Brands you carry, how many parts you keep in stock, your shop number"></textarea></div>
        <div class="sell-form__foot">
          <button type="submit" class="btn--primary">Send application</button>
          <p class="pg-form__status" data-pg-status role="status" aria-live="polite"></p>
        </div>
        <p class="sell-form__fine">By applying you agree to be contacted about selling on TechZone. We do not share your details.</p>
      </form>

      <div class="sell-done" id="sellerApplyDone" hidden>
        <h2 class="pg-h2">Application received</h2>
        <p>Thank you. Your reference is <strong class="pg-num sell-done__ref" data-pg-fill="reference">TZS</strong>. We will call you within two working days to arrange a visit to your shop.</p>
        <p class="pg-muted">Keep your CNIC and, if you have one, your NTN certificate ready for the visit.</p>
        <a class="btn--secondary" href="<?= pg_e(site_url('sellers/index.php')) ?>">See the shops already selling</a>
      </div>
    </div>

    <aside class="sell-apply__side pg-band-black">
      <h2 class="sell-apply__side-title">The terms, in short</h2>
      <dl class="sell-apply__terms">
        <div><dt>Fee</dt><dd>8% per sale, nothing to list</dd></div>
        <div><dt>Payouts</dt><dd>Every Monday, for orders delivered by Sunday</dd></div>
        <div><dt>Delivery</dt><dd>Our couriers pick up from your shop</dd></div>
        <div><dt>Payments</dt><dd>Cash on delivery and JazzCash, collected by us</dd></div>
      </dl>
      <p class="sell-apply__help">Questions first? WhatsApp <a href="<?= pg_e(pg_whatsapp_url(pg_store()['whatsapp'], 'Hello, I have a question about selling on TechZone.')) ?>" target="_blank" rel="noopener"><?= pg_e(pg_store()['whatsapp_text']) ?></a></p>
    </aside>
  </div>
</div>
<script src="<?= pg_e(marketing_script_url('pages.js')) ?>" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
