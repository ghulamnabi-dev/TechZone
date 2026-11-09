<?php
/**
 * Printable invoice / packing slip for one order (A4).
 *   ?id=N&type=invoice|packing[&autoprint=1]
 * Standalone page (no admin shell); store details come from settings().
 * The packing slip leaves out every price and adds a checkbox per line.
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/orders.php';
checkLogin();
require_can('orders.view');

$id = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$type = ($_GET['type'] ?? 'invoice') === 'packing' ? 'packing' : 'invoice';
$auto = !empty($_GET['autoprint']);
$d = $id > 0 ? order_detail($conn, $id) : null;
if (!$d) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Order not found</title><p style="font:14px system-ui;padding:40px">Order not found.</p>';
    exit();
}
$o = $d['order'];
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$rs = static fn($v): string => 'Rs ' . number_format((float) $v, 0);
$store = [
    'name' => (string) setting('store_name', 'TechZone'),
    'address' => (string) setting('store_address', ''),
    'phone' => (string) setting('store_phone', ''),
    'email' => (string) setting('store_email', ''),
];
$isInvoice = $type === 'invoice';
$docTitle = $isInvoice ? 'Invoice' : 'Packing slip';
$units = array_sum(array_map(static fn($i) => (int) $i['quantity'], $d['items']));
$pay = order_payment_method_label($o['payment_method']) ?? '—';
$address = trim((string) ($d['customer']['address'] ?? ''));
$ship = $d['shipment'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($docTitle . ' · Order #' . $o['id'] . ' · ' . $store['name']) ?></title>
<link rel="preload" href="<?= $h(admin_url('assets/fonts/inter-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<style>
@font-face { font-family: "Inter"; font-weight: 400; src: url("<?= $h(admin_url('assets/fonts/inter-latin-400-normal.woff2')) ?>") format("woff2"); }
@font-face { font-family: "Inter"; font-weight: 600; src: url("<?= $h(admin_url('assets/fonts/inter-latin-600-normal.woff2')) ?>") format("woff2"); }
:root { --ink: #1a1d23; --ink-2: #5c6370; --ink-3: #8a909c; --line: #e1e3e8; --line-2: #eef0f3; --canvas: #f4f5f7; }
* { box-sizing: border-box; }
html, body { margin: 0; background: var(--canvas); color: var(--ink); font: 12.5px/1.5 Inter, system-ui, -apple-system, "Segoe UI", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.prt-bar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line); font-size: 13px; }
.prt-bar a, .prt-bar button { font: inherit; height: 32px; padding: 0 12px; border-radius: 6px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; }
.prt-bar button { background: var(--ink); border-color: var(--ink); color: #fff; font-weight: 600; }
.prt-bar .on { border-color: var(--ink); font-weight: 600; }
.prt-page { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 16mm 16mm 14mm; box-shadow: 0 1px 3px rgba(16,24,40,.12); display: flex; flex-direction: column; }
.prt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 14px; border-bottom: 2px solid var(--ink); }
.prt-brand { display: flex; gap: 10px; align-items: center; }
.prt-mark { width: 34px; height: 34px; border-radius: 8px; background: #ffd400; display: grid; place-items: center; font-weight: 700; font-size: 15px; }
.prt-store { font-size: 18px; font-weight: 600; letter-spacing: -.01em; }
.prt-store-meta { color: var(--ink-2); font-size: 11.5px; margin-top: 6px; line-height: 1.55; }
.prt-doc { text-align: right; }
.prt-doc h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.015em; }
.prt-doc .no { font-size: 14px; font-weight: 600; margin-top: 2px; }
.prt-doc dl { margin: 8px 0 0; display: grid; grid-template-columns: auto auto; gap: 2px 12px; justify-content: end; color: var(--ink-2); font-size: 11.5px; }
.prt-doc dt { text-align: right; }
.prt-doc dd { margin: 0; color: var(--ink); text-align: right; }
.prt-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 18px 0 20px; }
.prt-label { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-3); font-weight: 600; margin-bottom: 4px; }
.prt-parties p { margin: 0; line-height: 1.55; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-3); font-weight: 600; padding: 8px 6px; border-bottom: 1px solid var(--ink); }
td { padding: 9px 6px; border-bottom: 1px solid var(--line-2); vertical-align: top; }
.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.prt-sub { color: var(--ink-2); font-size: 11px; }
.prt-check { width: 16px; height: 16px; border: 1.5px solid var(--ink); border-radius: 3px; display: inline-block; }
.prt-totals { margin-left: auto; width: 46%; margin-top: 12px; }
.prt-totals div { display: flex; justify-content: space-between; padding: 4px 6px; }
.prt-totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 8px; font-size: 15px; font-weight: 600; }
.prt-status { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #fff4d6; color: #9a6700; }
.prt-status.paid { background: #e3f5ec; color: #0a7b4b; }
.prt-sign { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 40px; }
.prt-sign div { border-top: 1px solid var(--ink-3); padding-top: 6px; color: var(--ink-2); font-size: 11px; }
.prt-foot { margin-top: auto; padding-top: 18px; border-top: 1px solid var(--line); color: var(--ink-3); font-size: 11px; display: flex; justify-content: space-between; gap: 16px; }
@page { size: A4; margin: 0; }
@media print {
    html, body { background: #fff; }
    .prt-bar { display: none; }
    .prt-page { margin: 0; box-shadow: none; width: auto; min-height: 297mm; }
}
@media screen and (max-width: 820px) {
    .prt-page { width: auto; min-height: 0; margin: 0; padding: 20px 16px; box-shadow: none; }
    .prt-head { flex-direction: column; gap: 14px; }
    .prt-doc { text-align: left; }
    .prt-doc dl { justify-content: start; }
    .prt-doc dt, .prt-doc dd { text-align: left; }
    .prt-parties { grid-template-columns: 1fr; gap: 12px; }
    .prt-totals { width: 100%; }
}
</style>
</head>
<body>
<div class="prt-bar">
    <a class="<?= $isInvoice ? 'on' : '' ?>" href="<?= $h(admin_url('manage/order-print.php?id=' . (int) $o['id'] . '&type=invoice')) ?>">Invoice</a>
    <a class="<?= !$isInvoice ? 'on' : '' ?>" href="<?= $h(admin_url('manage/order-print.php?id=' . (int) $o['id'] . '&type=packing')) ?>">Packing slip</a>
    <button type="button" id="prtPrint">Print</button>
    <a href="<?= $h(admin_url('manage/order.php?id=' . (int) $o['id'])) ?>">Back to order</a>
</div>
<main class="prt-page">
    <header class="prt-head">
        <div>
            <div class="prt-brand"><span class="prt-mark" aria-hidden="true">TZ</span><span class="prt-store"><?= $h($store['name']) ?></span></div>
            <div class="prt-store-meta">
                <?= $h($store['address']) ?><br>
                <?= $h(implode(' · ', array_filter([$store['phone'], $store['email']]))) ?>
            </div>
        </div>
        <div class="prt-doc">
            <h1><?= $h($docTitle) ?></h1>
            <div class="no">Order #<?= (int) $o['id'] ?></div>
            <dl>
                <dt>Order date</dt><dd><?= $h(date('j M Y', strtotime((string) $o['order_date']))) ?></dd>
                <?php if ($isInvoice): ?>
                    <dt>Invoice date</dt><dd><?= $h(date('j M Y', strtotime((string) $conn->query('SELECT NOW()')->fetchColumn()))) ?></dd>
                <?php endif; ?>
                <?php if ($ship && $ship['shipped_at']): ?>
                    <dt>Shipped</dt><dd><?= $h(date('j M Y', strtotime((string) $ship['shipped_at']))) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </header>

    <section class="prt-parties">
        <div>
            <div class="prt-label"><?= $isInvoice ? 'Bill to' : 'Customer' ?></div>
            <p><strong><?= $h($o['customer_name']) ?></strong><br><?= $h($o['customer_email']) ?><br><?= $h($o['customer_phone']) ?></p>
        </div>
        <div>
            <div class="prt-label">Ship to</div>
            <p><?= $h($o['customer_name']) ?><br><?php if ($address !== ''): ?><?= nl2br($h($address)) ?><br><?php endif; ?><?= $h($o['city']) ?></p>
        </div>
        <div>
            <?php if ($isInvoice): ?>
                <div class="prt-label">Payment</div>
                <p><?= $h($pay) ?><br><span class="prt-status<?= $o['payment_status'] === 'paid' ? ' paid' : '' ?>"><?= $h(ucfirst((string) $o['payment_status'])) ?></span></p>
            <?php else: ?>
                <div class="prt-label">Shipping</div>
                <p><?= $h($d['zone']['name'] ?? 'Standard') ?><?php if ($ship && $ship['courier'] !== ''): ?><br><?= $h($ship['courier']) ?><?= $ship['tracking_number'] ? ' · ' . $h($ship['tracking_number']) : '' ?><?php endif; ?><br><?= $units ?> item<?= $units === 1 ? '' : 's' ?></p>
            <?php endif; ?>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <?php if (!$isInvoice): ?><th style="width:28px"><span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Packed</span></th><?php endif; ?>
                <th>Item</th>
                <th>SKU</th>
                <th class="num">Qty</th>
                <?php if ($isInvoice): ?><th class="num">Unit price</th><th class="num">Amount</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($d['items'] as $it):
                $var = array_filter([$it['size'] ? 'Size: ' . $it['size'] : null, $it['color'] ? 'Colour: ' . $it['color'] : null]); ?>
                <tr>
                    <?php if (!$isInvoice): ?><td><span class="prt-check" aria-hidden="true"></span></td><?php endif; ?>
                    <td><?= $h($it['product_name']) ?><?php if ($var): ?><div class="prt-sub"><?= $h(implode(' · ', $var)) ?></div><?php endif; ?></td>
                    <td class="prt-sub"><?= $h($it['sku'] ?? '') ?></td>
                    <td class="num"><?= (int) $it['quantity'] ?></td>
                    <?php if ($isInvoice): ?><td class="num"><?= $rs($it['price']) ?></td><td class="num"><?= $rs($it['subtotal']) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($isInvoice): ?>
        <div class="prt-totals">
            <div><span>Subtotal</span><span class="num"><?= $rs($o['subtotal']) ?></span></div>
            <div><span>Shipping<?= isset($d['zone']['name']) ? ' (' . $h($d['zone']['name']) . ')' : '' ?></span><span class="num"><?= (float) $o['shipping_fee'] > 0 ? $rs($o['shipping_fee']) : 'Free' ?></span></div>
            <?php if ((float) $o['discount_amount'] > 0): ?>
                <div><span>Discount<?= $o['promo_code'] ? ' (' . $h($o['promo_code']) . ')' : '' ?></span><span class="num">−<?= $rs($o['discount_amount']) ?></span></div>
            <?php endif; ?>
            <div class="grand"><span>Total</span><span class="num"><?= $rs($o['total']) ?></span></div>
        </div>
    <?php else: ?>
        <div class="prt-sign"><div>Packed by</div><div>Checked by</div></div>
    <?php endif; ?>

    <footer class="prt-foot">
        <span><?= $isInvoice ? 'Thank you for shopping with ' . $h($store['name']) . '. Prices in Pakistani rupees.' : 'Please check every item before sealing the parcel.' ?></span>
        <span>Order #<?= (int) $o['id'] ?></span>
    </footer>
</main>
<script>
document.getElementById('prtPrint').addEventListener('click', function () { window.print(); });
<?php if ($auto): ?>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 150); });<?php endif; ?>
</script>
</body>
</html>
