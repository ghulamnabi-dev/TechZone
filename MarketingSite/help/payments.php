<?php
$pageTitle = 'Payments and installments';
require __DIR__ . '/_top.php';
help_start('payments', 'Payments and installments', 'Pay cash when the parcel arrives, or from your JazzCash account. Larger orders can be split into monthly bank installments at no extra cost.');
$opts = payment_options(0);
$codLimit = (float) setting('cod_limit', 0);
$plan = pg_installments();
$example = 150000;
?>
<h2 class="pg-h3">Cash on delivery</h2>
<p class="help-p"><?php if ($opts['cash_on_delivery']['enabled']): ?>Pay the courier in cash when the parcel arrives<?= $codLimit > 0 ? ', on orders up to ' . pg_e(format_pkr($codLimit)) : ', with no upper limit on the order value' ?>. Please keep the exact amount ready; couriers often do not carry change. We confirm every cash order by phone before it ships.<?php else: ?>Cash on delivery is paused right now. Please pay with JazzCash.<?php endif; ?></p>
<h2 class="pg-h3">JazzCash</h2>
<p class="help-p"><?php if ($opts['jazzcash']['enabled']): ?>Choose JazzCash at checkout and enter your JazzCash mobile number (03xx-xxxxxxx). You approve the payment on your phone; the order is confirmed as soon as it goes through.<?php else: ?>JazzCash payments are paused right now.<?php endif; ?></p>
<h2 class="pg-h3">Bank installments</h2>
<p class="help-p">Orders of <?= pg_e(format_pkr($plan['min_amount'])) ?> or more can be paid in monthly installments with a credit card from <?= pg_e(pg_list_and($plan['banks'])) ?>, at <?= $plan['markup_pct'] > 0 ? pg_e(rtrim(rtrim(number_format($plan['markup_pct'], 2), '0'), '.')) . '% markup' : '0% markup' ?>. Call us with your order and we will arrange it with your bank; the plan is set up on your card before we dispatch.</p>
<div class="help-table-wrap">
<table class="help-table">
  <caption>What a <?= pg_e(format_pkr($example)) ?> order costs a month</caption>
  <thead><tr><th scope="col">Plan</th><th scope="col" class="help-r">Per month</th><th scope="col" class="help-r">Total paid</th><th scope="col">Banks</th></tr></thead>
  <tbody>
  <?php foreach ($plan['months'] as $m): $total = $example * (1 + $plan['markup_pct'] / 100); ?>
    <tr>
      <th scope="row"><?= (int) $m ?> months</th>
      <td class="help-r pg-num help-fig"><?= pg_e(format_pkr(ceil($total / max(1, $m)))) ?></td>
      <td class="help-r pg-num help-fig"><?= pg_e(format_pkr($total)) ?></td>
      <td><?= pg_e(implode(', ', $plan['banks'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="help-note">Plans are read from our current settings. Your bank may charge its own processing fee; check with them before you choose a plan.</p>
<h2 class="pg-h3">Promo codes</h2>
<p class="help-p">Enter one code per order in your cart. Some codes need a minimum order or apply to one category; the cart tells you why a code does not apply. Codes cannot be added after the order is placed. Today's codes are on <a class="pg-link" href="<?= pg_e(site_url('deals.php')) ?>">deals</a>.</p>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
