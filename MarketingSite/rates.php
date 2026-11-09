<?php
/**
 * Today's rates: the full board. Every product on sale with today's price, the 7-day change,
 * a 12-week sparkline and the 12-week low / high. Filter by category, sort by change or price.
 * Plain GET links, so it works without JavaScript and every view can be shared.
 */
$pageTitle = "Today's rates";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';

$sorts = [
    'drop' => 'Biggest drop',
    'rise' => 'Biggest rise',
    'price_asc' => 'Price, low to high',
    'price_desc' => 'Price, high to low',
];
$sort = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'drop';
$catSlug = is_string($_GET['cat'] ?? null) ? $_GET['cat'] : '';

$rows = pg_products($conn);
$series = price_series_many($conn, array_map(static fn($r) => (int) $r['id'], $rows));

// chips: categories that have products, components first in builder order
$order = ['cpu' => 1, 'motherboard' => 2, 'memory' => 3, 'gpu' => 4, 'storage' => 5, 'psu' => 6, 'case' => 7, 'cooler' => 8];
$cats = [];
foreach ($rows as $r) {
    $slug = (string) $r['category_slug'];
    if (!isset($cats[$slug])) {
        $cats[$slug] = ['slug' => $slug, 'name' => $r['category_name'], 'n' => 0, 'rank' => $order[$r['part_type']] ?? 20];
    }
    $cats[$slug]['n']++;
}
uasort($cats, static fn($a, $b) => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']]);
if ($catSlug !== '' && !isset($cats[$catSlug])) {
    $catSlug = '';
}
if ($catSlug !== '') {
    $rows = array_values(array_filter($rows, static fn($r) => $r['category_slug'] === $catSlug));
}

$pct = static fn($r) => (float) ($r['change']['pct'] ?? 0);
usort($rows, static function ($a, $b) use ($sort, $pct) {
    return match ($sort) {
        'drop' => [$pct($a), $a['name']] <=> [$pct($b), $b['name']],
        'rise' => [$pct($b), $a['name']] <=> [$pct($a), $b['name']],
        'price_asc' => [(float) $a['price'], $a['name']] <=> [(float) $b['price'], $b['name']],
        'price_desc' => [(float) $b['price'], $a['name']] <=> [(float) $a['price'], $b['name']],
    };
});

$updated = $conn->query('SELECT MAX(recorded_at) FROM price_history')->fetchColumn();
$updatedText = $updated ? (new DateTimeImmutable($updated))->format('j F Y') : '';
$drops = count(array_filter($rows, static fn($r) => ($r['change']['direction'] ?? '') === 'drop'));
$rises = count(array_filter($rows, static fn($r) => ($r['change']['direction'] ?? '') === 'rise'));

$link = static function (array $over) use ($sort, $catSlug): string {
    $q = array_filter(['cat' => $catSlug, 'sort' => $sort] + [], static fn($v) => $v !== '');
    $q = array_filter(array_merge($q, $over), static fn($v) => $v !== '' && $v !== null);
    if (($q['sort'] ?? '') === 'drop') {
        unset($q['sort']);
    }
    return site_url('rates.php' . ($q ? '?' . http_build_query($q) : ''));
};
?>
<div class="rates">
  <section class="rates-board pg-band-black" aria-labelledby="rates-title">
    <div class="container">
      <header class="rates-head">
        <div>
          <h1 class="rates-head__title" id="rates-title">Today's rates</h1>
          <p class="rates-head__sub">Hafeez Centre and Hall Road, in Pakistani rupees<?php if ($updatedText): ?>. Last change recorded <?= pg_e($updatedText) ?><?php endif; ?>.</p>
        </div>
        <dl class="rates-head__stats">
          <div><dt>Parts on the board</dt><dd class="pg-num"><?= count($rows) ?></dd></div>
          <div><dt>Cheaper than last week</dt><dd class="pg-num rates-stat--drop"><?= $drops ?></dd></div>
          <div><dt>Dearer than last week</dt><dd class="pg-num rates-stat--rise"><?= $rises ?></dd></div>
        </dl>
      </header>

      <div class="rates-tools">
        <nav class="rates-chips" aria-label="Category">
          <a class="rates-chip" href="<?= pg_e($link(['cat' => ''])) ?>"<?= $catSlug === '' ? ' aria-current="true"' : '' ?>>All</a>
          <?php foreach ($cats as $c): ?>
            <a class="rates-chip" href="<?= pg_e($link(['cat' => $c['slug']])) ?>"<?= $catSlug === $c['slug'] ? ' aria-current="true"' : '' ?>><?= pg_e($c['name']) ?></a>
          <?php endforeach; ?>
        </nav>
        <form class="rates-sort" method="get" action="<?= pg_e(site_url('rates.php')) ?>">
          <?php if ($catSlug !== ''): ?><input type="hidden" name="cat" value="<?= pg_e($catSlug) ?>"><?php endif; ?>
          <label for="ratesSort">Sort</label>
          <select id="ratesSort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => $label): ?>
              <option value="<?= pg_e($k) ?>"<?= $k === $sort ? ' selected' : '' ?>><?= pg_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="bld-ghost">Sort</button></noscript>
        </form>
      </div>

      <?php if (!$rows): ?>
        <p class="rates-empty">No parts in this category are on sale today. <a href="<?= pg_e($link(['cat' => ''])) ?>">See every rate</a>.</p>
      <?php else: ?>
      <table class="rates-table">
        <caption class="visually-hidden">Rates<?= $catSlug !== '' ? ' for ' . pg_e($cats[$catSlug]['name']) : '' ?>, sorted by <?= pg_e(strtolower($sorts[$sort])) ?></caption>
        <thead>
          <tr>
            <th scope="col">Part</th>
            <th scope="col" class="rates-r">Today</th>
            <th scope="col" class="rates-r">7 days</th>
            <th scope="col" class="rates-spark">12 weeks</th>
            <th scope="col" class="rates-r rates-lh">Low</th>
            <th scope="col" class="rates-r rates-lh">High</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $s = $series[(int) $r['id']] ?? [];
              $range = price_range($s);
              $url = site_url('shop/product-details.php?id=' . (int) $r['id']);
          ?>
          <tr>
            <th scope="row" class="rates-name">
              <a href="<?= pg_e($url) ?>"><?= pg_e($r['name']) ?></a>
              <span class="rates-spec"><?= pg_e(implode(', ', array_merge([$r['category_name']], $r['key_specs']))) ?><?php if ((int) $r['inventory'] <= 0): ?>, <span class="rates-out">out of stock</span><?php endif; ?></span>
            </th>
            <td class="rates-r rates-price"><?= pg_e(number_format((float) $r['price'])) ?></td>
            <td class="rates-r"><?= render_delta($r['change']) ?></td>
            <td class="rates-spark"><?= render_sparkline($s) ?></td>
            <td class="rates-r rates-lh pg-num"><?= $range['low'] !== null ? pg_e(number_format($range['low'])) : '' ?></td>
            <td class="rates-r rates-lh pg-num"><?= $range['high'] !== null ? pg_e(number_format($range['high'])) : '' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <p class="rates-note">Prices are updated as they change at the shop. The 7-day figure compares today's rate with the rate a week ago; low and high cover the last 12 weeks.</p>
    </div>
  </section>

  <section class="container rates-after">
    <div>
      <h2 class="pg-h3">Building a whole PC?</h2>
      <p class="pg-muted">The builder totals every part at these rates and checks that they fit.</p>
    </div>
    <a class="btn--primary" href="<?= pg_e(site_url('builder.php')) ?>">Open the builder</a>
    <a class="btn--secondary" href="<?= pg_e(site_url('deals.php')) ?>">Price drops this week</a>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
