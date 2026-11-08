<?php
/**
 * Discount engine (promotions.kind / applies_to / target_id / min_order / usage_limit / schedule).
 *
 * evaluate_promo() is pure (reads only) and used by the cart, the promo API and checkout;
 * promo_record_use() is the one write and runs inside place_order()'s transaction.
 *
 *   kind          percent        discount_percentage % of the eligible lines
 *                 fixed          discount_amount off, capped at the eligible lines
 *                 free_shipping  no line discount; the shipping fee is waived
 *   applies_to    all | category (target_id = categories.id) | product (target_id = products.id)
 *   min_order     cart subtotal (all lines) must reach it
 *   usage_limit   NULL = unlimited, else used_count must stay below it
 *   schedule      starts_at / ends_at (DATETIME); start_date / end_date are the fallback
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/shipping.php';

/**
 * Evaluate a promo code against a cart.
 *
 * @param PDO    $conn
 * @param string $code       as typed; matched case-insensitively
 * @param array  $cartLines  [{product_id|id, price, quantity}, …] (prices already include options)
 * @param float  $subtotal   sum of the lines
 * @param string $city       delivery city ('' when unknown) – values a free-shipping code
 * @return array{ok:bool, message:string, code:?string, promo:?array, kind:?string, discount:float,
 *               free_shipping:bool, shipping_waived:float, eligible_subtotal:float}
 */
function evaluate_promo(PDO $conn, string $code, array $cartLines, float $subtotal, string $city = ''): array
{
    $result = [
        'ok' => false, 'message' => '', 'code' => null, 'promo' => null, 'kind' => null,
        'discount' => 0.0, 'free_shipping' => false, 'shipping_waived' => 0.0, 'eligible_subtotal' => 0.0,
    ];
    $code = strtoupper(trim($code));
    if ($code === '') {
        $result['message'] = 'Please enter a promo code';
        return $result;
    }

    $st = $conn->prepare("SELECT id, title, promo_code, kind, applies_to, target_id, discount_percentage, discount_amount,
            min_order, usage_limit, used_count, starts_at, ends_at, start_date, end_date,
            (COALESCE(starts_at, TIMESTAMP(start_date)) IS NULL OR COALESCE(starts_at, TIMESTAMP(start_date)) <= NOW())
              AND (COALESCE(ends_at, TIMESTAMP(end_date, '23:59:59')) IS NULL OR COALESCE(ends_at, TIMESTAMP(end_date, '23:59:59')) >= NOW()) AS is_live
        FROM promotions
        WHERE UPPER(promo_code) = ? AND is_active = 1
        LIMIT 1");
    $st->execute([$code]);
    $promo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$promo) {
        $result['message'] = 'Invalid or expired promo code';
        return $result;
    }

    // schedule is checked by the database clock (same clock that stamps orders)
    if ((int) $promo['is_live'] !== 1) {
        $result['message'] = 'Invalid or expired promo code';
        return $result;
    }
    if ($promo['usage_limit'] !== null && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
        $result['message'] = 'This promo code has reached its usage limit';
        return $result;
    }

    $result['code'] = strtoupper($promo['promo_code']);
    $result['promo'] = $promo;
    $result['kind'] = $promo['kind'];

    $min = (float) $promo['min_order'];
    if ($min > 0 && $subtotal < $min) {
        $result['message'] = sprintf('Spend Rs %s more to use %s (minimum order Rs %s)',
            number_format($min - $subtotal, 0), $result['code'], number_format($min, 0));
        return $result;
    }

    // Which lines the code applies to
    $eligible = 0.0;
    $target = $promo['target_id'] !== null ? (int) $promo['target_id'] : null;
    if ($promo['applies_to'] === 'all' || $target === null) {
        $eligible = $subtotal;
    } else {
        $ids = [];
        foreach ($cartLines as $line) {
            $pid = (int) ($line['product_id'] ?? $line['id'] ?? 0);
            if ($pid > 0) {
                $ids[$pid] = true;
            }
        }
        $categoryOf = [];
        if ($ids && $promo['applies_to'] === 'category') {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $q = $conn->prepare("SELECT id, category_id FROM products WHERE id IN ($in)");
            $q->execute(array_keys($ids));
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $categoryOf[(int) $r['id']] = $r['category_id'] !== null ? (int) $r['category_id'] : null;
            }
        }
        foreach ($cartLines as $line) {
            $pid = (int) ($line['product_id'] ?? $line['id'] ?? 0);
            $hit = $promo['applies_to'] === 'product' ? $pid === $target : (($categoryOf[$pid] ?? null) === $target);
            if ($hit) {
                $eligible += (float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 0);
            }
        }
        if ($eligible <= 0) {
            $what = promo_target_name($conn, $promo['applies_to'], $target);
            $result['message'] = $result['code'] . ' only applies to ' . ($what ?? 'selected items') . ', and none are in your cart';
            return $result;
        }
    }
    $result['eligible_subtotal'] = round($eligible, 2);

    switch ($promo['kind']) {
        case 'percent':
            $result['discount'] = round($eligible * (float) $promo['discount_percentage'] / 100, 2);
            break;
        case 'fixed':
            $result['discount'] = round(min((float) $promo['discount_amount'], $eligible), 2);
            break;
        case 'free_shipping':
            $result['free_shipping'] = true;
            $result['shipping_waived'] = $city !== '' ? shipping_quote($conn, $city, $subtotal)['fee'] : 0.0;
            break;
    }
    $result['discount'] = min($result['discount'], $subtotal);
    $result['ok'] = true;
    $result['message'] = $promo['kind'] === 'free_shipping'
        ? 'Promo code applied: free shipping on this order'
        : 'Promo code applied successfully!';
    return $result;
}

/**
 * Display name of a promo target ("Monitors", "Samsung T7 1TB Portable SSD"), or null.
 */
function promo_target_name(PDO $conn, string $appliesTo, ?int $targetId): ?string
{
    if ($targetId === null || $appliesTo === 'all') {
        return null;
    }
    $table = $appliesTo === 'category' ? 'categories' : 'products';
    $st = $conn->prepare("SELECT name FROM $table WHERE id = ?");
    $st->execute([$targetId]);
    $name = $st->fetchColumn();
    return $name !== false ? (string) $name : null;
}

/**
 * Count one use of a promotion. Guarded so concurrent checkouts cannot exceed usage_limit.
 * Call inside the order transaction.
 *
 * @return bool false when the limit was reached in the meantime
 */
function promo_record_use(PDO $conn, int $promoId): bool
{
    $st = $conn->prepare('UPDATE promotions SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)');
    $st->execute([$promoId]);
    return $st->rowCount() === 1;
}
