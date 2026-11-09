<?php
$pageTitle = 'Questions and answers';
require __DIR__ . '/_top.php';
help_start('faq', 'Questions and answers', 'The ten questions we hear most at the counter and on WhatsApp.');
$plan = pg_installments();
$faq = [
    ['Are your prices the same as in the shop?', 'Yes. The website shows the rate we sell at today, at the counter and online. Rates in Lahore\'s markets move with the dollar and with stock, so a price can change from one week to the next; the 7-day change next to each price shows which way it went.'],
    ['Is everything original with warranty?', 'Every part is new and sold with the official warranty of the brand\'s Pakistani distributor, unless the product page says otherwise. Marketplace shops are held to the same rule and are visited before they can list.'],
    ['How do I know parts work together?', 'Use the PC builder. It only lists boards that match the processor\'s socket, memory that matches the board, and cases that fit the board and graphics card, and it recommends a power supply for the build.'],
    ['Do you assemble the PC?', 'Yes, for builds bought together. Assembly, cable management and a stress test cost Rs 3,000 in Lahore; add a note when you order or call us. We ship assembled PCs in their case box with the parts\' boxes alongside.'],
    ['Can I pay when the parcel arrives?', 'Yes, cash on delivery is available across Pakistan. We call to confirm the order before it ships. You can also pay with JazzCash.'],
    ['Can I pay in installments?', 'Orders of ' . format_pkr($plan['min_amount']) . ' or more can be split over ' . pg_list_and(array_map(static fn($m) => $m . '', $plan['months'])) . ' months on a ' . pg_list_and($plan['banks']) . ' credit card, at ' . ($plan['markup_pct'] > 0 ? $plan['markup_pct'] . '%' : '0%') . ' markup.'],
    ['How long does delivery take?', 'One to two working days in Lahore, two to four in the rest of Punjab, and three to six elsewhere. The exact fee and time for your city show at checkout.'],
    ['Who is "Sold by" on a product?', 'The shop that holds the stock and packs it. It may be TechZone or another vetted shop from Hafeez Centre, Hall Road or Saddar. You still order, pay and return through TechZone.'],
    ['What if a part is dead on arrival?', 'Tell us within 7 days. We collect it in Lahore or refund your courier fee elsewhere, test it, and replace or refund it. After 7 days, it goes through the warranty.'],
    ['Can I get a price alert?', 'Save a part to your wishlist to watch its rate, or use "Notify me" on an out-of-stock part to get an email when it is back.'],
];
?>
<div class="help-faq">
  <?php foreach ($faq as [$q, $a]): ?>
    <details class="help-qa"><summary><?= pg_e($q) ?></summary><p><?= pg_e($a) ?></p></details>
  <?php endforeach; ?>
</div>
<?php help_end(); require __DIR__ . '/../includes/footer.php';
