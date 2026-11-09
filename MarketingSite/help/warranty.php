<?php
$pageTitle = 'Warranty and claims';
require __DIR__ . '/_top.php';
help_start('warranty', 'Warranty and claims', 'Every part carries its brand\'s official warranty in Pakistan. We handle the claim with the distributor, so you deal with one shop.');
$customer = null;
if (isset($_SESSION['customer_id'])) {
    $st = $conn->prepare('SELECT name, email, phone FROM customers WHERE id = ?');
    $st->execute([(int) $_SESSION['customer_id']]);
    $customer = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<div class="help-table-wrap">
<table class="help-table">
  <caption>Typical warranty periods</caption>
  <thead><tr><th scope="col">Part</th><th scope="col" class="help-r">Warranty</th></tr></thead>
  <tbody>
    <tr><th scope="row">Processors</th><td class="help-r">3 years</td></tr>
    <tr><th scope="row">Graphics cards and motherboards</th><td class="help-r">3 years</td></tr>
    <tr><th scope="row">Memory</th><td class="help-r">Lifetime (limited)</td></tr>
    <tr><th scope="row">NVMe drives</th><td class="help-r">3 to 5 years</td></tr>
    <tr><th scope="row">Power supplies</th><td class="help-r">3 to 7 years</td></tr>
    <tr><th scope="row">Monitors</th><td class="help-r">1 to 3 years</td></tr>
    <tr><th scope="row">Peripherals, cases, cooling</th><td class="help-r">1 to 2 years</td></tr>
  </tbody>
</table>
</div>
<p class="help-note">The exact period is printed on the product page and the box. Warranty does not cover physical damage, burn marks, bent pins, liquid, or a removed serial sticker.</p>
<h2 class="pg-h3">How a claim works</h2>
<p class="help-p">Send the claim below. We reply within a working day with what to do next: usually bringing or sending the part to the shop with its box. Most distributors in Lahore repair or replace within 10 to 20 working days; we keep you posted and return the part to you free of charge.</p>

<section class="help-claim" aria-labelledby="claim-title">
  <h2 class="pg-h2" id="claim-title">Start a warranty claim</h2>
  <form class="pg-form" id="warrantyForm" method="post" action="<?= pg_e(site_url('api/warranty_claim.php')) ?>" data-pg-form="warrantyDone" novalidate>
    <?= csrf_field() ?>
    <div class="pg-form__row">
      <div class="field"><label class="field__label" for="wc_name">Your name</label><input class="field__input" id="wc_name" name="name" maxlength="100" autocomplete="name" value="<?= pg_e($customer['name'] ?? '') ?>" required></div>
      <div class="field"><label class="field__label" for="wc_phone">Mobile number</label><input class="field__input" id="wc_phone" name="phone" type="tel" placeholder="0300-1234567" maxlength="12" autocomplete="tel" value="<?= pg_e($customer['phone'] ?? '') ?>" required></div>
    </div>
    <div class="pg-form__row">
      <div class="field"><label class="field__label" for="wc_email">Email</label><input class="field__input" id="wc_email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= pg_e($customer['email'] ?? '') ?>" required></div>
      <div class="field"><label class="field__label" for="wc_order">Order number</label><input class="field__input" id="wc_order" name="order_id" inputmode="numeric" placeholder="For example 1042" maxlength="10" required><span class="field__hint">On your order email and in your account.</span></div>
    </div>
    <div class="field"><label class="field__label" for="wc_product">Which part?</label><input class="field__input" id="wc_product" name="product" maxlength="150" placeholder="For example ASUS Dual RTX 4060 8GB" required></div>
    <div class="field"><label class="field__label" for="wc_fault">What is wrong?</label><textarea class="field__input" id="wc_fault" name="fault" rows="5" maxlength="3000" placeholder="What happens, since when, and what you have tried" required></textarea></div>
    <div class="sell-form__foot">
      <button class="btn--primary" type="submit">Send claim</button>
      <p class="pg-form__status" data-pg-status role="status" aria-live="polite"></p>
    </div>
  </form>
  <div class="sell-done" id="warrantyDone" hidden>
    <h3 class="pg-h3">Claim received</h3>
    <p>Your claim reference is <strong class="pg-num sell-done__ref" data-pg-fill="reference">WC</strong>. We will reply by email or WhatsApp within a working day. Keep the part in its box until then.</p>
  </div>
</section>
<script src="<?= pg_e(marketing_script_url('pages.js')) ?>" defer></script>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
