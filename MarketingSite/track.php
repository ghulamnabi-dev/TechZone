<?php
/**
 * Public "Track your order": order number + the phone number used at checkout.
 * Shows the 4-step progress and the customer-facing timeline when both match.
 *
 * - CSRF-protected POST form.
 * - Throttled like logins (core/auth.php, scope "track": 5 misses per 15 minutes per IP and
 *   per session), with one generic message for "no such order" and "wrong phone".
 * - Shows no personal data beyond the delivery city.
 */
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/config/customer_auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/events.php';

const TRACK_SCOPE = 'track';
const TRACK_FAILED = "We couldn't find an order with that number and phone. Check both and try again.";

/** Last 10 digits of a Pakistani phone number (0300-1234567, +92 300 1234567 → 3001234567). */
function track_phone_key(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
}

$error = '';
$found = null;
$orderNo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNo = trim((string) ($_POST['order'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    try {
        require_csrf_token();
        $csrfOk = true;
    } catch (RuntimeException $e) {
        $csrfOk = false;
    }
    $locked = $csrfOk ? login_lock_remaining($conn, TRACK_SCOPE) : 0;
    $id = (int) ltrim($orderNo, "# \t");
    if (!$csrfOk) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif ($locked > 0) {
        $minutes = max(1, (int) ceil($locked / 60));
        $error = 'Too many attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
    } elseif ($id <= 0 || strlen(track_phone_key($phone)) < 10) {
        $error = 'Enter your order number (for example 1234) and the phone number you used at checkout.';
    } else {
        try {
            $st = $conn->prepare('SELECT id, customer_phone, city, status, order_date, cancelled_at FROM orders WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && hash_equals(track_phone_key((string) $row['customer_phone']), track_phone_key($phone))) {
                $st = $conn->prepare('SELECT courier, tracking_number, shipped_at, delivered_at FROM order_shipments WHERE order_id = ? ORDER BY shipped_at DESC, id DESC LIMIT 1');
                $st->execute([$id]);
                $st2 = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = ?');
                $st2->execute([$id]);
                $found = [
                    'order' => $row,
                    'shipment' => $st->fetch(PDO::FETCH_ASSOC) ?: null,
                    'timeline' => order_customer_timeline($conn, $id),
                    'units' => (int) $st2->fetchColumn(),
                ];
            } else {
                login_register_failure($conn, TRACK_SCOPE);
                $error = TRACK_FAILED;
            }
        } catch (PDOException $e) {
            log_exception($e, 'track');
            $error = 'Tracking is unavailable right now. Please try again in a moment.';
        }
    }
}

$pageTitle = 'Track your order';
require_once __DIR__ . '/includes/header.php';
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="container pg-page pg-track">
    <div class="pg-track__grid">
        <div class="pg-track__form">
            <header class="pg-head">
                <h1 class="pg-title">Track your order</h1>
                <p class="pg-lead">Enter your order number and the phone number you used at checkout.</p>
            </header>
            <?php if ($error): ?>
                <div class="alert alert-danger pg-alert pg-alert--error" role="alert"><?= $h($error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $h(site_url('track.php')) ?>" class="pg-form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="trkOrder">Order number</label>
                    <input type="text" class="field__input" id="trkOrder" name="order" inputmode="numeric" autocomplete="off" maxlength="12" placeholder="For example 1234" value="<?= $h($orderNo) ?>" required>
                </div>
                <div class="field">
                    <label class="field__label" for="trkPhone">Phone number</label>
                    <input type="tel" class="field__input" id="trkPhone" name="phone" autocomplete="tel" maxlength="20" placeholder="03xx-xxxxxxx" required>
                    <span class="field__hint">The number on your order confirmation.</span>
                </div>
                <button type="submit" class="btn--primary btn--block">Track order</button>
            </form>
            <p class="pg-track__acct">Have an account? <a class="pg-link" href="<?= $h(site_url('account/orders.php')) ?>">See all your orders</a>.</p>
        </div>
        <div class="pg-track__result">
            <?php if ($found):
                $trkOrder = $found['order'];
                $trkShipment = $found['shipment'];
                $trkTimeline = $found['timeline']; ?>
                <div class="pg-track__head">
                    <h2 class="pg-h2">Order #<?= (int) $trkOrder['id'] ?></h2>
                    <span class="acct-pill acct-pill--<?= $h(strtolower((string) $trkOrder['status'])) ?>"><?= $h($trkOrder['status']) ?></span>
                </div>
                <p class="pg-muted pg-track__meta">Placed <?= $h(date('j F Y', strtotime((string) $trkOrder['order_date']))) ?>, <?= (int) $found['units'] ?> item<?= $found['units'] === 1 ? '' : 's' ?>, delivering to <?= $h($trkOrder['city']) ?></p>
                <?php include __DIR__ . '/includes/order-track.php'; ?>
            <?php else: ?>
                <div class="pg-track__empty">
                    <ol class="pg-track__steps" aria-hidden="true"><li>Placed</li><li>Confirmed</li><li>Shipped</li><li>Delivered</li></ol>
                    <p class="pg-track__q">Where's my parcel?</p>
                    <p class="pg-muted">Once your order ships you'll see the courier and tracking number here, and every step until it reaches your door. Lahore orders usually arrive in 1 to 2 working days.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
