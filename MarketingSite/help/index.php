<?php
$pageTitle = 'Help centre';
require __DIR__ . '/_top.php';
help_start('index', 'Help centre', 'Answers about ordering, delivery, paying, returns and warranty at TechZone. Most questions are answered here in a minute.');
$store = pg_store();
$topics = [
    ['how-to-order', 'How to order', 'Cart, checkout and the confirmation call before we dispatch.'],
    ['shipping', 'Shipping and delivery', 'Fees and delivery times for Lahore, Punjab and the rest of Pakistan.'],
    ['payments', 'Payments and installments', 'Cash on delivery, JazzCash and 0% bank installment plans.'],
    ['returns', 'Returns', 'Seven days to return a part, and what we need to accept it.'],
    ['warranty', 'Warranty and claims', 'Official warranty, how claims work, and the claim form.'],
    ['faq', 'Questions and answers', 'Ten things buyers ask us most often.'],
];
?>
<ul class="help-topics">
  <?php foreach ($topics as [$k, $t, $d]): ?>
    <li><a class="help-topic" href="<?= pg_e(site_url('help/' . $k . '.php')) ?>"><span class="help-topic__t"><?= pg_e($t) ?></span><span class="help-topic__d"><?= pg_e($d) ?></span></a></li>
  <?php endforeach; ?>
</ul>
<p class="help-p">Looking for an order? <a class="pg-link" href="<?= pg_e(site_url('track.php')) ?>">Track it</a> with your order number and email, or see every order in <a class="pg-link" href="<?= pg_e(site_url('account/orders.php')) ?>">your account</a>.</p>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
