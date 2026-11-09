<?php
$pageTitle = 'Returns';
require __DIR__ . '/_top.php';
help_start('returns', 'Returns', 'You have 7 days from delivery to return a part. Tell us first; we will arrange collection or you can bring it to the shop.');
?>
<h2 class="pg-h3">You can return a part when</h2>
<ul class="help-list">
  <li>it is within 7 days of the delivery date on your order page;</li>
  <li>it is unused, in its original box with every accessory, manual and cable;</li>
  <li>the seal is intact, or the part arrived faulty or not as described;</li>
  <li>you have the order number. Each part needs its own reason.</li>
</ul>
<h2 class="pg-h3">We cannot accept</h2>
<ul class="help-list">
  <li>processors with bent pins, or parts with burn marks, liquid damage or a removed serial sticker;</li>
  <li>opened software, cleaning kits, thermal paste and cables cut to length;</li>
  <li>a part that works as described but did not suit your build. Check compatibility in the <a class="pg-link" href="<?= pg_e(site_url('builder.php')) ?>">builder</a> before you order.</li>
</ul>
<h2 class="pg-h3">How it works</h2>
<ol class="help-steps help-steps--compact">
  <li><h3>Tell us</h3><p>Call, WhatsApp or <a class="pg-link" href="<?= pg_e(site_url('contact.php')) ?>">write to us</a> with the order number and the reason.</p></li>
  <li><h3>Send it back</h3><p>In Lahore we collect for free. Elsewhere, send it by any courier; we refund the courier fee if the part was faulty.</p></li>
  <li><h3>We check it</h3><p>Our technician at Hafeez Centre tests the part within two working days of receiving it.</p></li>
  <li><h3>Refund or replacement</h3><p>You choose. Refunds go to your JazzCash account or bank within 5 working days; cash orders are refunded the same way.</p></li>
</ol>
<p class="help-p">A part that fails after the 7 days is covered by its <a class="pg-link" href="<?= pg_e(site_url('help/warranty.php')) ?>">warranty</a> instead.</p>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
