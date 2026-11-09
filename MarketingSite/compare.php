<?php
/**
 * Compare 2–4 products side by side: image, price, weekly change, then every attribute as a row,
 * rows that differ marked. Ids come from the URL (?ids=55,56,57); with none, assets/js/pages.js
 * reads localStorage "tz.compare" (written by the product cards) and reloads with them.
 */
$pageTitle = 'Compare';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';

$ids = [];
foreach (explode(',', is_string($_GET['ids'] ?? null) ? substr($_GET['ids'], 0, 100) : '') as $v) {
    if (ctype_digit(trim($v)) && (int) $v > 0 && !in_array((int) $v, $ids, true)) {
        $ids[] = (int) $v;
    }
}
$ids = array_slice($ids, 0, 4);
$items = [];
if ($ids) {
    $byId = [];
    foreach (pg_products($conn, 'p.id IN (' . catalog_placeholders($ids) . ')', $ids) as $r) {
        $byId[(int) $r['id']] = $r;
    }
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $items[] = $byId[$id];
        }
    }
}
$sellers = sellers_by_id($conn);

// attribute rows: union of keys, in first-seen order
$rows = [];
foreach ($items as $i => $p) {
    foreach (spec_rows($p['attrs']) as $sr) {
        $rows[$sr['key']]['label'] = $sr['label'];
        $rows[$sr['key']]['values'][$i] = $sr['value'];
    }
}
$diffCount = 0;
foreach ($rows as $k => $r) {
    $vals = [];
    foreach (array_keys($items) as $i) {
        $vals[] = $r['values'][$i] ?? '';
    }
    $rows[$k]['diff'] = count(array_unique($vals)) > 1;
    $diffCount += $rows[$k]['diff'] ? 1 : 0;
}
$types = array_unique(array_map(static fn($p) => product_part_type($p), $items));
$minPrice = $items ? min(array_map(static fn($p) => (float) $p['price'], $items)) : 0;
$without = static fn(int $id) => site_url('compare.php?ids=' . implode(',', array_filter($ids, static fn($x) => $x !== $id)));
?>
<div class="cmp pg-page" id="compare" data-ids="<?= pg_e(implode(',', array_map(static fn($p) => (int) $p['id'], $items))) ?>">
  <div class="container">
    <header class="cmp-head">
      <div class="pg-head">
        <h1 class="pg-title">Compare</h1>
        <?php if (count($items) >= 2): ?>
          <p class="pg-lead"><?= count($items) ?> parts side by side. <?= $diffCount ?> of <?= count($rows) ?> specs differ<?= $diffCount ? ', marked in yellow' : '' ?>.<?php if (count($types) > 1): ?> These are different kinds of part, so many specs only apply to one of them.<?php endif; ?></p>
        <?php endif; ?>
      </div>
      <?php if (count($items) >= 2 && $rows): ?>
        <label class="bld-toggle cmp-only"><input type="checkbox" id="cmpOnlyDiff"> Show only differences</label>
      <?php endif; ?>
    </header>

    <?php if (count($items) < 2): ?>
      <div class="pg-empty" id="cmpEmpty">
        <h2><?= $items ? 'Add one more part to compare' : 'Nothing to compare yet' ?></h2>
        <p>Tick "Compare" on two to four product cards in the catalogue, then open the comparison from the bar at the bottom of the page. Parts of the same kind compare best: three graphics cards, or two monitors.</p>
        <a class="btn--primary" href="<?= pg_e(site_url('shop/products.php?category=12')) ?>">Browse graphics cards</a>
        <a class="btn--secondary" href="<?= pg_e(site_url('shop/products.php')) ?>">All products</a>
      </div>
    <?php else: ?>
      <div class="cmp-scroll" tabindex="0" role="region" aria-label="Comparison table, scrolls sideways on small screens">
        <table class="cmp-table" style="--cmp-cols: <?= count($items) ?>">
          <caption class="visually-hidden">Specifications of <?= pg_e(pg_list_and(array_column($items, 'name'))) ?></caption>
          <thead>
            <tr>
              <td class="cmp-corner"></td>
              <?php foreach ($items as $p): $url = site_url('shop/product-details.php?id=' . (int) $p['id']); ?>
                <th scope="col" class="cmp-prod">
                  <a class="cmp-prod__media" href="<?= pg_e($url) ?>" tabindex="-1" aria-hidden="true"><?= render_product_media($p, ['width' => 320, 'tile' => 'md']) ?></a>
                  <a class="cmp-prod__name" href="<?= pg_e($url) ?>"><?= pg_e($p['name']) ?></a>
                  <a class="cmp-prod__remove" href="<?= pg_e($without((int) $p['id'])) ?>" data-cmp-remove="<?= (int) $p['id'] ?>">Remove</a>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr class="cmp-row cmp-row--price<?= count(array_unique(array_map(static fn($p) => (float) $p['price'], $items))) > 1 ? ' is-diff' : '' ?>">
              <th scope="row">Today</th>
              <?php foreach ($items as $p): ?>
                <td><span class="cmp-price"><?= pg_e(format_pkr($p['price'])) ?></span><?php if ((float) $p['price'] === $minPrice && count($items) > 1): ?><span class="cmp-badge">Lowest</span><?php endif; ?></td>
              <?php endforeach; ?>
            </tr>
            <tr class="cmp-row">
              <th scope="row">Since last week</th>
              <?php foreach ($items as $p): ?><td><?= render_delta($p['change']) ?></td><?php endforeach; ?>
            </tr>
            <tr class="cmp-row">
              <th scope="row">Stock</th>
              <?php foreach ($items as $p): ?><td><?= stock_line((int) $p['inventory'], 'cmp-stock') ?></td><?php endforeach; ?>
            </tr>
            <tr class="cmp-row<?= count(array_unique(array_column($items, 'seller_id'))) > 1 ? ' is-diff' : '' ?>">
              <th scope="row">Sold by</th>
              <?php foreach ($items as $p): ?><td><?= pg_e($sellers[(int) $p['seller_id']]['name'] ?? 'TechZone') ?></td><?php endforeach; ?>
            </tr>
            <?php foreach ($rows as $k => $r): ?>
              <tr class="cmp-row<?= $r['diff'] ? ' is-diff' : ' is-same' ?>">
                <th scope="row"><?= pg_e($r['label']) ?><?php if ($r['diff']): ?><span class="visually-hidden"> (differs)</span><?php endif; ?></th>
                <?php foreach (array_keys($items) as $i): ?>
                  <td><?= isset($r['values'][$i]) ? pg_e($r['values'][$i]) : '<span class="cmp-na">Not listed</span>' ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            <tr class="cmp-row cmp-row--buy">
              <th scope="row"><span class="visually-hidden">Buy</span></th>
              <?php foreach ($items as $p): ?>
                <td><?= (int) $p['inventory'] > 0 ? cart_add_form((int) $p['id'], 'btn--primary btn--block', 'Add to cart') : '<span class="cmp-na">Out of stock</span>' ?></td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>window.TZ_COMPARE_PAGE = <?= json_encode(['url' => site_url('compare.php'), 'ids' => array_map(static fn($p) => (int) $p['id'], $items)]) ?>;</script>
<script src="<?= pg_e(marketing_script_url('pages.js')) ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
