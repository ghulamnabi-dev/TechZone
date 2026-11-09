<?php
/**
 * PC Builder (docs/STOREFRONT.md "Builder rules"). The page renders the eight slots and the summary;
 * assets/js/builder.js keeps the build in the URL (?b=cpu:46,mb:8,…) and talks to api/builder.php.
 */
$pageTitle = 'Build a PC';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';
require_once __DIR__ . '/../core/builder.php';

$build = builder_parse(is_string($_GET['b'] ?? null) ? $_GET['b'] : '');
$ev = builder_evaluate($conn, $build);
$partJson = static fn(array $p): array => product_json($p, ['attrs' => $p['attrs'] ?? [], 'warnings' => $p['warnings'] ?? []]);
$initial = $ev;
$initial['parts'] = array_map($partJson, $ev['parts']);
$initial['total_text'] = format_pkr($ev['total']);

$slots = builder_slots();
$nouns = ['cpu' => 'a processor', 'mb' => 'a motherboard', 'ram' => 'memory', 'gpu' => 'a graphics card',
          'storage' => 'storage', 'psu' => 'a power supply', 'case' => 'a case', 'cooler' => 'a cooler'];

// Starter builds: every one is checked against the rules here; a preset that stops fitting
// (a part delisted, stock gone) is simply not offered.
$presetDefs = [
    ['key' => 'budget', 'label' => 'Budget 1080p', 'b' => 'cpu:42,mb:7,ram:51,gpu:58,storage:72,psu:60,case:66',
     'note' => 'Core i5-12400F and RX 7600 on DDR4. Stock cooler.'],
    ['key' => '1440p', 'label' => '1440p gaming', 'b' => 'cpu:46,mb:8,ram:53,gpu:57,storage:73,psu:61,case:64,cooler:68',
     'note' => 'Ryzen 5 7600, RTX 4070 SUPER, 32 GB DDR5.'],
    ['key' => 'creator', 'label' => 'Creator', 'b' => 'cpu:47,mb:9,ram:54,gpu:59,storage:75,psu:63,case:65,cooler:70',
     'note' => 'Ryzen 7 7800X3D, 16 GB RX 7800 XT, 2 TB NVMe.'],
    ['key' => 'am4', 'label' => 'AM4 upgrade', 'b' => 'cpu:49,mb:50,ram:52,gpu:55,storage:72,psu:60,case:67',
     'note' => 'Ryzen 5 5600 on B550 with an RTX 4060. Stock cooler.'],
];
$presets = [];
foreach ($presetDefs as $pd) {
    $pev = builder_evaluate($conn, builder_parse($pd['b']));
    if ($pev['complete'] && !$pev['invalid'] && !$pev['out_of_stock']) {
        $presets[] = $pd + ['total' => $pev['total'], 'total_text' => format_pkr($pev['total'])];
    }
}

$sellerNames = [];
foreach (sellers_by_id($conn) as $sid => $s) {
    $sellerNames[$sid] = $s['name'];
}

$boot = [
    'api' => site_url('api/builder.php'),
    'page' => site_url('builder.php'),
    'cart' => site_url('shop/cart.php'),
    'slots' => array_map(static fn($k, $d) => ['key' => $k, 'label' => $d['label'], 'noun' => $nouns[$k], 'optional' => $d['optional']], array_keys($slots), $slots),
    'initial' => $initial,
    'presets' => array_map(static fn($p) => ['key' => $p['key'], 'label' => $p['label'], 'b' => $p['b']], $presets),
    'glyphs' => pg_glyph_map(),
    'sellers' => $sellerNames,
];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="bld" id="builder" data-state="<?= $ev['parts'] ? 'building' : 'empty' ?>">
  <div class="container">
    <header class="bld-head">
      <div class="bld-head__text">
        <h1 class="bld-head__title">Build a PC</h1>
        <p class="bld-head__lead">Totals at today's rates. Pick a processor and every list after it shows only parts that fit.</p>
      </div>
      <?php if ($presets): ?>
      <div class="bld-presets" role="group" aria-label="Starter builds">
        <span class="bld-presets__label">Start from</span>
        <?php foreach ($presets as $p): ?>
          <a class="chip bld-preset" href="<?= pg_e(site_url('builder.php?b=' . $p['b'])) ?>" data-build="<?= pg_e($p['b']) ?>" title="<?= pg_e($p['note']) ?>">
            <?= pg_e($p['label']) ?> <span class="bld-preset__sum"><?= pg_e($p['total_text']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </header>

    <div class="bld-grid">
      <section class="bld-parts" aria-labelledby="bld-parts-title">
        <h2 class="visually-hidden" id="bld-parts-title">Parts</h2>
        <div class="bld-parts__head" aria-hidden="true">
          <span>Part</span><span></span><span>Choice</span><span class="bld-num">Today</span><span></span>
        </div>
        <ol class="bld-slots" id="bldSlots">
          <?php foreach ($slots as $key => $def): ?>
            <li class="bld-slot is-empty" data-slot="<?= pg_e($key) ?>">
              <span class="bld-slot__label"><?= pg_e($def['label']) ?></span>
              <button type="button" class="bld-slot__choose" data-open="<?= pg_e($key) ?>">Choose <?= pg_e($nouns[$key]) ?></button>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="bld-rules" id="bldRules">
          <p><strong>How it fits together.</strong> The motherboard must match the processor's socket, memory must match the board, and the case must take the board, the card and the cooler.</p>
          <p>We add up the processor and graphics card power plus 75 W for the rest, and recommend a power supply with 40% headroom.</p>
        </div>
      </section>

      <aside class="bld-sum" id="bldSummary" aria-labelledby="bld-sum-title" aria-live="polite">
        <div class="bld-sum__inner">
          <h2 class="bld-sum__title" id="bld-sum-title">Your build</h2>
          <p class="bld-sum__count" id="bldCount">No parts yet</p>
          <div class="bld-sum__total">
            <span class="bld-sum__total-label">Total at today's rates</span>
            <span class="bld-sum__figure" id="bldTotal"><?= pg_e(format_pkr($ev['total'])) ?></span>
          </div>
          <div class="bld-power" id="bldPower">
            <div class="bld-power__row"><span>Estimated draw</span><span class="bld-num" id="bldDraw"><?= (int) $ev['wattage'] ?> W</span></div>
            <div class="bld-power__meter" aria-hidden="true"><span class="bld-power__fill" id="bldPowerFill"></span><span class="bld-power__rec" id="bldPowerRec"></span><span class="bld-power__psu" id="bldPowerPsu"></span></div>
            <div class="bld-power__row bld-power__row--sub"><span><span class="bld-power__key bld-power__key--rec" aria-hidden="true"></span>Recommended power supply</span><span class="bld-num" id="bldRec"><?= (int) $ev['recommended_psu_w'] ?> W</span></div>
            <div class="bld-power__row bld-power__row--sub bld-power__row--psu" id="bldPsuRow" hidden><span><span class="bld-power__key bld-power__key--psu" aria-hidden="true"></span>Your power supply</span><span class="bld-num" id="bldPsuW"></span></div>
          </div>
          <div class="bld-issues" id="bldIssues"></div>
          <div class="bld-sum__actions">
            <button type="button" class="btn--on-black btn--block" id="bldAdd" disabled>Add all to cart</button>
            <p class="bld-sum__msg" id="bldMsg" role="status"></p>
            <div class="bld-sum__row">
              <button type="button" class="bld-ghost" id="bldShare">Share build</button>
              <button type="button" class="bld-ghost" id="bldReset">Start over</button>
            </div>
            <label class="visually-hidden" for="bldShareUrl">Link to this build</label>
            <input class="bld-share-url" id="bldShareUrl" type="text" readonly hidden>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <div class="bld-picker" id="bldPicker" role="dialog" aria-modal="true" aria-labelledby="bldPickerTitle" hidden>
    <div class="bld-picker__backdrop" data-close></div>
    <div class="bld-picker__panel">
      <div class="bld-picker__head">
        <div>
          <h2 class="bld-picker__title" id="bldPickerTitle">Choose</h2>
          <p class="bld-picker__sub" id="bldPickerSub"></p>
        </div>
        <button type="button" class="bld-picker__close" data-close aria-label="Close"><?= tz_icon('close', 22) ?></button>
      </div>
      <div class="bld-picker__tools">
        <label class="visually-hidden" for="bldSearch">Search this list</label>
        <input type="search" class="field__input bld-picker__search" id="bldSearch" placeholder="Search by name or spec" autocomplete="off">
        <label class="visually-hidden" for="bldSort">Sort</label>
        <select class="field__input bld-picker__sort" id="bldSort">
          <option value="price_asc">Price, low to high</option>
          <option value="price_desc">Price, high to low</option>
        </select>
      </div>
      <div class="bld-picker__body" id="bldPickerBody"></div>
      <div class="bld-picker__foot" id="bldPickerFoot">
        <label class="bld-toggle"><input type="checkbox" id="bldShowRejected"> <span id="bldShowRejectedLabel">Show parts that don't fit</span></label>
      </div>
    </div>
  </div>
  <noscript><div class="container"><p class="bld-noscript">The builder needs JavaScript to check compatibility. You can still <a href="<?= pg_e(site_url('shop/products.php?category=2')) ?>">browse components</a>.</p></div></noscript>
</div>

<script>window.TZ_BUILDER = <?= json_encode($boot, $jsonFlags) ?>;</script>
<script src="<?= pg_e(marketing_script_url('builder.js')) ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
