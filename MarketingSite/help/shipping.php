<?php
$pageTitle = 'Shipping and delivery';
require __DIR__ . '/_top.php';
help_start('shipping', 'Shipping and delivery', 'We deliver anywhere in Pakistan with TCS, Leopards and M&P. Fees depend on your city and are waived over a set order value.');
$zones = shipping_zones($conn);
?>
<div class="help-table-wrap">
<table class="help-table">
  <caption class="visually-hidden">Delivery zones, fees and times</caption>
  <thead><tr><th scope="col">Zone</th><th scope="col" class="help-r">Fee</th><th scope="col" class="help-r">Free over</th><th scope="col" class="help-r">Arrives in</th></tr></thead>
  <tbody>
  <?php foreach ($zones as $z): if (!$z['rate']) continue; ?>
    <tr>
      <th scope="row"><?= pg_e($z['name']) ?><span class="help-cities"><?= $z['is_default'] ? 'Every other city, including ' : '' ?><?= pg_e(implode(', ', array_slice($z['cities'], 0, 8))) ?><?= count($z['cities']) > 8 ? ' and ' . (count($z['cities']) - 8) . ' more' : '' ?></span></th>
      <td class="help-r pg-num help-fig"><?= pg_e(format_pkr($z['rate']['amount'])) ?></td>
      <td class="help-r pg-num help-fig"><?= $z['rate']['free_over'] !== null ? pg_e(format_pkr($z['rate']['free_over'])) : '—' ?></td>
      <td class="help-r"><?= pg_e(str_replace('–', '–', (string) $z['rate']['eta'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="help-note">Fees and times are read live from our delivery settings. The fee for your city is shown at checkout before you pay.</p>
<h2 class="pg-h3">When your order leaves</h2>
<p class="help-p">Orders confirmed before 4 pm, Monday to Saturday, are packed and handed to the courier the same day. Parts from other marketplace shops are collected from their counters the next working day. Sunday and public holiday orders leave on the next working day.</p>
<h2 class="pg-h3">Receiving the parcel</h2>
<p class="help-p">Check the box for crushed corners or a broken seal before you pay the courier. If anything looks wrong, refuse the parcel and call us; it comes back to us at no cost to you. Graphics cards, processors and monitors are packed in double boxes.</p>
<h2 class="pg-h3">Picking up from the shop</h2>
<p class="help-p">There is no pickup option at checkout yet. If you would rather collect from Hafeez Centre, call us first with your order number; we keep the parts aside and you pay at the counter. See <a class="pg-link" href="<?= pg_e(site_url('store.php')) ?>">directions and hours</a>.</p>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
