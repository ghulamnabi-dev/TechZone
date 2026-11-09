<?php
$pageTitle = 'How to order';
require __DIR__ . '/_top.php';
help_start('how-to-order', 'How to order', 'Ordering takes about three minutes. You need an account so we can send updates and you can track the order.');
?>
<ol class="help-steps">
  <li><h2>Add parts to your cart</h2><p>Use the catalogue, today's rates or the <a class="pg-link" href="<?= pg_e(site_url('builder.php')) ?>">PC builder</a>, which adds a whole compatible build in one go. Prices in the cart are today's rates; nothing is reserved until you place the order.</p></li>
  <li><h2>Add a promo code</h2><p>If you have a code, type it in the cart. It is checked against the cart straight away, and again when you place the order. Current codes are listed on <a class="pg-link" href="<?= pg_e(site_url('deals.php')) ?>">deals</a>.</p></li>
  <li><h2>Sign in and check out</h2><p>Sign in or create an account, enter your delivery address and city, and choose cash on delivery or JazzCash. The delivery fee for your city appears as you type it.</p></li>
  <li><h2>Take our confirmation call</h2><p>For cash on delivery we call or WhatsApp you within working hours to confirm the order before it leaves the shop. Orders we cannot confirm within two days are cancelled.</p></li>
  <li><h2>Track it</h2><p>You get an order number on screen and by email. When the parcel is handed to the courier, the tracking number appears on your order page.</p></li>
</ol>
<h2 class="pg-h3">Buying several parts from different sellers</h2>
<p class="help-p">One order can hold parts from TechZone and from other shops on the marketplace. You pay once; each shop packs its own parts, so they may arrive in separate parcels on different days. Each parcel has its own tracking number on your order page.</p>
<h2 class="pg-h3">Changing or cancelling</h2>
<p class="help-p">Call or WhatsApp us before the order ships and we will change the address or cancel it at no cost. After it ships, refuse the parcel at the door or use a <a class="pg-link" href="<?= pg_e(site_url('help/returns.php')) ?>">return</a>.</p>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
