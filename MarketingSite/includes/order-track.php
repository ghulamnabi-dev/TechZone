<?php
/**
 * Customer-facing order tracking block: 4-step progress (current step highlighted), a one-line
 * status summary and the customer timeline (core/events.php order_customer_timeline()).
 * Used by account/order-details.php and the public track.php.
 *
 * Expects: $trkOrder (orders row), $trkTimeline (order_customer_timeline()), $trkShipment (latest
 * order_shipments row or null). Shows no names, addresses, phone numbers or staff details.
 */
require_once __DIR__ . '/../../core/events.php';

$trkE = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$trkP = order_progress($trkOrder);
$trkStep = $trkP['step'];
$trkCancelled = $trkP['cancelled'];
if ($trkCancelled) {
    $trkHeadline = 'This order was cancelled';
    $trkSub = 'Nothing will be shipped. Contact us if you have questions.';
} elseif ($trkStep === 4) {
    $trkHeadline = 'Delivered';
    $trkSub = !empty($trkShipment['delivered_at']) ? 'Delivered on ' . date('j F Y', strtotime((string) $trkShipment['delivered_at'])) : 'Your order has been delivered.';
} elseif ($trkStep === 3) {
    $trkHeadline = 'On its way';
    $courier = trim((string) ($trkShipment['courier'] ?? ''));
    $trkSub = $courier !== '' ? 'Shipped with ' . $courier . (!empty($trkShipment['tracking_number']) ? ' — tracking ' . $trkShipment['tracking_number'] : '') : 'Your parcel is with the courier.';
} elseif ($trkStep === 2) {
    $trkHeadline = 'Confirmed — being prepared';
    $trkSub = 'We are packing your order. You will see the courier and tracking number here once it ships.';
} else {
    $trkHeadline = 'Order placed';
    $trkSub = 'We will confirm your order shortly.';
}
?>
<section class="trk<?= $trkCancelled ? ' trk--cancelled' : '' ?>" aria-labelledby="trkTitle">
    <div class="trk__head">
        <p class="trk__eyebrow">Track</p>
        <h2 class="trk__title" id="trkTitle"><?= $trkE($trkHeadline) ?></h2>
        <p class="trk__sub"><?= $trkE($trkSub) ?></p>
    </div>
    <?php if (!$trkCancelled): ?>
        <ol class="trk__steps" aria-label="Order progress">
            <?php foreach ($trkP['steps'] as $i => $label):
                $n = $i + 1;
                $cls = $n < $trkStep ? 'is-done' : ($n === $trkStep ? 'is-current' : ''); ?>
                <li class="<?= $cls ?>"<?= $n === $trkStep ? ' aria-current="step"' : '' ?>>
                    <span class="trk__dot" aria-hidden="true"><?= $n < $trkStep || ($n === $trkStep && $n === 4) ? '&#10003;' : $n ?></span>
                    <span class="trk__label"><?= $trkE($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if ($trkTimeline): ?>
        <h3 class="trk__h3">History</h3>
        <ol class="trk__timeline">
            <?php foreach (array_reverse($trkTimeline) as $t): ?>
                <li class="trk__event trk__event--<?= $trkE($t['type']) ?>">
                    <span class="trk__event-label"><?= $trkE($t['label']) ?></span>
                    <time class="trk__event-time" datetime="<?= $trkE(date('c', strtotime((string) $t['at']))) ?>"><?= $trkE(date('j M Y, g:i A', strtotime((string) $t['at']))) ?></time>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
