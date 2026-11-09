<?php
/** Visit us: the Hafeez Centre shop. Details come from Settings → Store. */
$pageTitle = 'Visit the shop';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pages.php';

$s = pg_store();
$week = pg_week_hours($s['hours_text']);
$phoneHref = 'tel:' . preg_replace('/[^\d+]/', '', $s['phone']);
$wa = pg_whatsapp_url($s['whatsapp'], 'Hello, I would like to visit the shop. ');
?>
<div class="store">
  <section class="store-top">
    <div class="container store-top__grid">
      <div class="store-card pg-band-signal">
        <h1 class="store-card__title">Visit the shop</h1>
        <p class="store-card__addr"><?= pg_e($s['address']) ?></p>
        <p class="store-card__today"><?= pg_e(pg_today_line($week)) ?></p>
        <dl class="store-card__contact">
          <div><dt>Phone</dt><dd><a href="<?= pg_e($phoneHref) ?>"><?= pg_e($s['phone']) ?></a></dd></div>
          <div><dt>WhatsApp</dt><dd><a href="<?= pg_e($wa) ?>" target="_blank" rel="noopener"><?= pg_e($s['whatsapp_text']) ?></a></dd></div>
          <div><dt>Email</dt><dd><a href="mailto:<?= pg_e($s['email']) ?>"><?= pg_e($s['email']) ?></a></dd></div>
        </dl>
        <a class="btn--primary" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode('Hafeez Centre, Main Boulevard Gulberg, Lahore') ?>" target="_blank" rel="noopener">Open in Google Maps</a>
      </div>
      <div class="store-mapwrap"><?= pg_store_map('store-map') ?></div>
    </div>
  </section>

  <div class="container store-body">
    <section class="store-hours" aria-labelledby="hours-title">
      <h2 class="pg-h3" id="hours-title">Opening hours</h2>
      <table class="store-hours__table">
        <caption class="visually-hidden">Opening hours this week</caption>
        <tbody>
          <?php foreach ($week as $d): ?>
            <tr<?= $d['today'] ? ' class="is-today" aria-current="date"' : '' ?>>
              <th scope="row"><?= pg_e($d['day']) ?><?= $d['today'] ? '<span class="store-hours__tag">Today</span>' : '' ?></th>
              <td><?= $d['hours'] ? pg_e($d['hours']) : 'Closed' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="store-note">Hafeez Centre is busiest after 6 pm and on Saturdays. Friday prayers close most shops from about 1 to 2:30 pm.</p>
    </section>

    <section class="store-how" aria-labelledby="how-title">
      <h2 class="pg-h3" id="how-title">How to get there</h2>
      <dl class="store-routes">
        <div><dt>From Liberty Market</dt><dd>Take Main Boulevard Gulberg towards Kalma Chowk. Hafeez Centre is the large plaza on Main Boulevard in Gulberg III, about five minutes by car or rickshaw. Ask for "Hafeez Centre"; every driver knows it.</dd></div>
        <div><dt>From Main Boulevard, coming from Kalma Chowk</dt><dd>Head towards Liberty along Main Boulevard; the plaza is on the boulevard before you reach the Liberty roundabout. If you pass Liberty, you have gone too far.</dd></div>
        <div><dt>Inside the plaza</dt><dd>Take the main stairs or the lift to the 2nd floor. We are shop 214, a few doors from the lift, with the yellow TechZone sign.</dd></div>
        <div><dt>Parking</dt><dd>Spaces in front of the plaza fill up by the evening. Come before 5 pm, use the paid parking around the block, or come by rickshaw or ride-hailing and get dropped at the main gate.</dd></div>
      </dl>
    </section>

    <section class="store-pickup" aria-labelledby="pickup-title">
      <h2 class="pg-h3" id="pickup-title">Collecting an order</h2>
      <p>Checkout does not have a pickup option yet, so please call ahead. Call <a class="pg-link" href="<?= pg_e($phoneHref) ?>"><?= pg_e($s['phone']) ?></a> or WhatsApp us with your order number; we keep the parts aside, test them in front of you if you like, and you pay at the counter in cash or by card.</p>
      <p class="pg-muted">Bring the CNIC of the person named on the order. For a PC we assemble, allow a day after you call.</p>
    </section>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
