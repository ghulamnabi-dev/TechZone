<?php
/**
 * Orders: atomic placement (checkout) and the staff operations on an order.
 *
 * Placement — everything happens in ONE transaction:
 *   1. lock every product row (and selected size/color variation rows) with
 *      SELECT ... FOR UPDATE, in id order to avoid deadlocks,
 *   2. re-check stock against the total quantity requested per row
 *      (two cart lines of the same product are summed),
 *   3. re-price every line from the database (session prices are not trusted)
 *      and re-validate the promo code,
 *   4. price shipping from the customer's city (core/shipping.php) and the promo code from
 *      the discount engine (core/discounts.php),
 *   5. insert the order + order_items, take stock through adjust_inventory() ('sold', guarded),
 *      count the promo use and write the "placed" timeline event.
 * Any failure rolls everything back, so stock can never go negative or be oversold
 * by concurrent checkouts.
 *
 * Staff operations (order_confirm, order_record_payment, order_fulfil, order_ship, order_deliver,
 * order_cancel, order_add_note) each run in a transaction, write a timeline event and an
 * audit_log row, and re-derive the customer-facing `status` from the staff axes:
 *
 *   Cancelled  cancelled_at is set
 *   Delivered  a shipment has delivered_at
 *   Shipped    a shipment exists
 *   Processing confirmed (event) or any item fulfilled
 *   Pending    otherwise
 *
 * payment_status (pending/paid/refunded) and fulfillment_status (unfulfilled/partial/fulfilled)
 * are independent of it, as in Shopify.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/shipping.php';
require_once __DIR__ . '/discounts.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/audit.php';

/** Message is safe to show to the customer. */
class CheckoutException extends RuntimeException
{
}

/** A staff action that is not allowed in the order's current state. Message is safe to show. */
class OrderActionException extends RuntimeException
{
}

/** Customer-facing statuses (orders.status ENUM), in lifecycle order. */
const ORDER_STATUSES = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

/** Couriers offered when shipping (free text is accepted too). */
const ORDER_COURIERS = ['TCS', 'Leopards', 'M&P', 'PostEx', 'Trax', 'Pakistan Post'];

/** "'Name' (Size: X, Color: Y)" for customer-facing stock messages. */
function order_line_label(string $name, ?string $size, ?string $color): string
{
    $parts = [];
    if ($size !== null && $size !== '') {
        $parts[] = 'Size: ' . $size;
    }
    if ($color !== null && $color !== '') {
        $parts[] = 'Color: ' . $color;
    }
    return "'" . $name . "'" . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
}

/**
 * Place an order for the given session cart.
 *
 * @param array $contact customer_name, customer_email, customer_phone, city, payment_method
 * @return array{order_id:int,total:float,subtotal:float,shipping_fee:float,discount_amount:float}
 * @throws CheckoutException for stock/validation problems (safe to display)
 * @throws PDOException      for database failures (log, show a generic message)
 */
function place_order(PDO $conn, int $customerId, array $cart, array $contact, ?string $promoCode): array
{
    // Normalise cart lines
    $lines = [];
    foreach ($cart as $item) {
        $pid = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0 || $qty > 1000) {
            throw new CheckoutException('Your cart contains an invalid item. Please update your cart.');
        }
        $size = isset($item['size']) && $item['size'] !== '' ? (string) $item['size'] : null;
        $color = isset($item['color']) && $item['color'] !== '' ? (string) $item['color'] : null;
        $lines[] = ['product_id' => $pid, 'quantity' => $qty, 'size' => $size, 'color' => $color];
    }
    if (!$lines) {
        throw new CheckoutException('Your cart is empty.');
    }

    $own = !$conn->inTransaction();   // joins an outer transaction (tests, batch imports)
    if ($own) {
        $conn->beginTransaction();
    }
    try {
        // 1. Lock product rows in a stable order
        $productIds = array_values(array_unique(array_column($lines, 'product_id')));
        sort($productIds);
        $products = [];
        $lockProduct = $conn->prepare("SELECT id, name, price, inventory, is_active FROM products WHERE id = ? FOR UPDATE");
        foreach ($productIds as $pid) {
            $lockProduct->execute([$pid]);
            $row = $lockProduct->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $products[$pid] = $row;
            }
        }

        // 2. Lock variation rows, price each line, sum demand per locked row
        $lockVariation = $conn->prepare("SELECT id, inventory, price_modifier FROM product_variations
            WHERE product_id = ? AND variation_type = ? AND variation_value = ? AND is_active = 1 FOR UPDATE");
        $errors = [];
        $productDemand = [];
        $variationDemand = [];
        $variationStock = [];
        $subtotal = 0.0;

        foreach ($lines as $i => $line) {
            $pid = $line['product_id'];
            $product = $products[$pid] ?? null;
            $label = order_line_label($product['name'] ?? ('#' . $pid), $line['size'], $line['color']);
            if (!$product || (int) $product['is_active'] !== 1) {
                $errors[] = "Product {$label} is no longer available";
                continue;
            }

            $price = (float) $product['price'];
            $lines[$i]['variation_ids'] = [];
            foreach (['size' => $line['size'], 'color' => $line['color']] as $type => $value) {
                if ($value === null) {
                    continue;
                }
                $lockVariation->execute([$pid, $type, $value]);
                $variation = $lockVariation->fetch(PDO::FETCH_ASSOC);
                if ($variation) {
                    $vid = (int) $variation['id'];
                    $price += (float) $variation['price_modifier'];
                    $variationStock[$vid] = (int) $variation['inventory'];
                    $variationDemand[$vid] = ($variationDemand[$vid] ?? 0) + $line['quantity'];
                    $lines[$i]['variation_ids'][] = $vid;
                    $lines[$i]['labels'][$vid] = $label;
                }
            }

            $productDemand[$pid] = ($productDemand[$pid] ?? 0) + $line['quantity'];
            $lines[$i]['name'] = $product['name'];
            $lines[$i]['price'] = round($price, 2);
            $lines[$i]['label'] = $label;
            $subtotal += $lines[$i]['price'] * $line['quantity'];
        }

        // 3. Stock check against the locked rows
        foreach ($productDemand as $pid => $demand) {
            $stock = (int) $products[$pid]['inventory'];
            if ($stock < $demand) {
                $name = "'" . $products[$pid]['name'] . "'";
                $errors[] = $stock <= 0 ? "Product {$name} is out of stock" : "Only {$stock} available for {$name} (requested: {$demand})";
            }
        }
        foreach ($variationDemand as $vid => $demand) {
            if ($variationStock[$vid] < $demand) {
                $label = '';
                foreach ($lines as $line) {
                    if (isset($line['labels'][$vid])) {
                        $label = $line['labels'][$vid];
                        break;
                    }
                }
                $stock = $variationStock[$vid];
                $errors[] = $stock <= 0 ? "Product {$label} is out of stock" : "Only {$stock} available for {$label} (requested: {$demand})";
            }
        }
        if ($errors) {
            throw new CheckoutException('Inventory issues: ' . implode('. ', array_unique($errors)) . '. Please update your cart.');
        }

        // 4. Totals (server-side only): discount engine + shipping zone for the delivery city
        $city = (string) ($contact['city'] ?? '');
        $priced = array_map(static fn(array $l): array => ['product_id' => $l['product_id'], 'price' => $l['price'], 'quantity' => $l['quantity']], $lines);
        $promo = ($promoCode !== null && $promoCode !== '') ? evaluate_promo($conn, $promoCode, $priced, $subtotal, $city) : null;
        if ($promo !== null && !$promo['ok']) {
            $promo = null;   // an expired / ineligible code is dropped, never blocks the order
        }
        $discount = $promo ? min((float) $promo['discount'], $subtotal) : 0.0;
        $quote = shipping_quote($conn, $city, $subtotal);
        $shipping = ($promo && $promo['free_shipping']) ? 0.0 : (float) $quote['fee'];
        $subtotal = round($subtotal, 2);
        $total = max(0, round($subtotal - $discount + $shipping, 2));

        // 5. Write order, items, stock, promo use, timeline
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, customer_name, customer_email, customer_phone, city,
                subtotal, shipping_fee, discount_amount, total, status, payment_status, fulfillment_status, shipping_zone_id, payment_method, promo_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'pending', 'unfulfilled', ?, ?, ?)");
        $stmt->execute([
            $customerId,
            $contact['customer_name'],
            $contact['customer_email'],
            $contact['customer_phone'],
            $contact['city'],
            $subtotal,
            $shipping,
            round($discount, 2),
            $total,
            $quote['zone']['id'] ?? null,
            $contact['payment_method'],
            $promo['code'] ?? null,
        ]);
        $orderId = (int) $conn->lastInsertId();

        $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, size, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // A line with BOTH a size and a colour option: the ledger row carries the first option;
        // the second option's own counter is decremented here with the same guard.
        $decExtraVariation = $conn->prepare("UPDATE product_variations SET inventory = inventory - ? WHERE id = ? AND inventory >= ?");

        foreach ($lines as $line) {
            $qty = $line['quantity'];
            $insertItem->execute([$orderId, $line['product_id'], $line['name'], $line['price'], $qty, round($line['price'] * $qty, 2), $line['size'], $line['color']]);

            $vids = $line['variation_ids'];
            try {
                adjust_inventory($conn, $line['product_id'], $vids[0] ?? null, -$qty, 'sold', 'order #' . $orderId, null);
            } catch (InventoryException $e) {
                throw new CheckoutException('Product ' . $line['label'] . ' just sold out. Please update your cart.');
            }
            foreach (array_slice($vids, 1) as $vid) {
                $decExtraVariation->execute([$qty, $vid, $qty]);
                if ($decExtraVariation->rowCount() !== 1) {
                    throw new CheckoutException('Product ' . $line['label'] . ' just sold out. Please update your cart.');
                }
            }
        }

        if ($promo && !promo_record_use($conn, (int) $promo['promo']['id'])) {
            throw new CheckoutException('Promo code ' . $promo['code'] . ' has just reached its usage limit. Please remove it and try again.');
        }

        order_event($conn, $orderId, 'placed', [
            'total' => $total,
            'items' => array_sum(array_column($lines, 'quantity')),
            'payment_method' => $contact['payment_method'],
            'shipping_zone' => $quote['zone']['name'] ?? null,
            'promo_code' => $promo['code'] ?? null,
        ], 'customer', $customerId > 0 ? $customerId : null);

        if ($own) {
            $conn->commit();
        }
        return ['order_id' => $orderId, 'total' => $total, 'subtotal' => $subtotal, 'shipping_fee' => $shipping, 'discount_amount' => round($discount, 2)];
    } catch (Throwable $e) {
        if ($own && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}


/* ==========================================================================
   Staff operations
   ========================================================================== */

/**
 * Run $fn inside a transaction, joining the caller's when one is already open.
 *
 * @template T
 * @param callable():T $fn
 * @return T
 */
function order_tx(PDO $conn, callable $fn)
{
    if ($conn->inTransaction()) {
        return $fn();
    }
    $conn->beginTransaction();
    try {
        $out = $fn();
        $conn->commit();
        return $out;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

/**
 * Lock and return an order row (SELECT … FOR UPDATE).
 *
 * @throws OrderActionException when the order does not exist
 */
function order_lock(PDO $conn, int $orderId): array
{
    $st = $conn->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $st->execute([$orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new OrderActionException('Order #' . $orderId . ' was not found.');
    }
    return $order;
}

/** Current order row (no lock). */
function order_get(PDO $conn, int $orderId): ?array
{
    $st = $conn->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** The session admin id (or null) — the default actor for staff operations. */
function order_actor(?int $adminId): ?int
{
    if ($adminId !== null) {
        return $adminId;
    }
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

/**
 * Re-derive and store the customer-facing status from cancellation, shipments, confirmation
 * and fulfilment (see the file header). Returns the status now stored.
 */
function order_sync_status(PDO $conn, int $orderId): string
{
    $st = $conn->prepare("SELECT o.cancelled_at, o.fulfillment_status, o.status,
            (SELECT COUNT(*) FROM order_shipments s WHERE s.order_id = o.id) AS shipments,
            (SELECT COUNT(*) FROM order_shipments s WHERE s.order_id = o.id AND s.delivered_at IS NOT NULL) AS delivered,
            (SELECT COUNT(*) FROM order_events e WHERE e.order_id = o.id AND e.type = 'confirmed') AS confirmed
        FROM orders o WHERE o.id = ?");
    $st->execute([$orderId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        throw new OrderActionException('Order #' . $orderId . ' was not found.');
    }
    if ($r['cancelled_at'] !== null) {
        $status = 'Cancelled';
    } elseif ((int) $r['delivered'] > 0) {
        $status = 'Delivered';
    } elseif ((int) $r['shipments'] > 0) {
        $status = 'Shipped';
    } elseif ((int) $r['confirmed'] > 0 || $r['fulfillment_status'] !== 'unfulfilled') {
        $status = 'Processing';
    } else {
        $status = 'Pending';
    }
    if ($status !== $r['status']) {
        $conn->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    }
    return $status;
}

/** @throws OrderActionException when the order is cancelled */
function order_assert_open(array $order, string $verb): void
{
    if ($order['cancelled_at'] !== null || $order['status'] === 'Cancelled') {
        throw new OrderActionException("Order #{$order['id']} is cancelled, so it can't be $verb.");
    }
}

/**
 * Confirm a pending order (Pending -> Processing).
 *
 * @return array the updated order row
 * @throws OrderActionException when the order is cancelled or already confirmed
 */
function order_confirm(PDO $conn, int $orderId, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    return order_tx($conn, function () use ($conn, $orderId, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'confirmed');
        $st = $conn->prepare("SELECT COUNT(*) FROM order_events WHERE order_id = ? AND type = 'confirmed'");
        $st->execute([$orderId]);
        if ((int) $st->fetchColumn() > 0 || $order['status'] !== 'Pending') {
            throw new OrderActionException("Order #$orderId is already confirmed.");
        }
        order_event($conn, $orderId, 'confirmed', [], 'staff', $adminId);
        $status = order_sync_status($conn, $orderId);
        audit('order.confirm', 'order', $orderId, "Confirmed order #$orderId", ['status' => $order['status']], ['status' => $status]);
        return order_get($conn, $orderId);
    });
}

/**
 * Record that the order has been paid (payment_status pending -> paid).
 *
 * @param float|null  $amount defaults to the order total
 * @param string|null $method defaults to the order's payment_method
 * @return array the updated order row
 * @throws OrderActionException when cancelled or already paid
 */
function order_record_payment(PDO $conn, int $orderId, ?float $amount = null, ?string $method = null, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    return order_tx($conn, function () use ($conn, $orderId, $amount, $method, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'paid');
        if ($order['payment_status'] !== 'pending') {
            throw new OrderActionException("Payment for order #$orderId is already {$order['payment_status']}.");
        }
        $amount = $amount ?? (float) $order['total'];
        $method = $method ?? $order['payment_method'];
        $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
        order_event($conn, $orderId, 'payment_recorded', ['amount' => round($amount, 2), 'method' => $method], 'staff', $adminId);
        audit('order.payment', 'order', $orderId, 'Recorded payment of Rs ' . number_format($amount, 0) . " on order #$orderId",
            ['payment_status' => 'pending'], ['payment_status' => 'paid', 'amount' => round($amount, 2), 'method' => $method]);
        return order_get($conn, $orderId);
    });
}

/**
 * Mark items as fulfilled (picked and packed). Partial fulfilment is allowed.
 *
 * @param array<int,int>|null $items order_items.id => quantity to fulfil now; null = everything left
 * @return array the updated order row
 * @throws OrderActionException when cancelled, already fulfilled, or a quantity is invalid
 */
function order_fulfil(PDO $conn, int $orderId, ?array $items = null, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    return order_tx($conn, function () use ($conn, $orderId, $items, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'fulfilled');
        $st = $conn->prepare('SELECT id, product_name, quantity, fulfilled_qty FROM order_items WHERE order_id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $upd = $conn->prepare('UPDATE order_items SET fulfilled_qty = fulfilled_qty + ? WHERE id = ? AND order_id = ? AND fulfilled_qty + ? <= quantity');
        $done = [];
        foreach ($rows as $r) {
            $left = (int) $r['quantity'] - (int) $r['fulfilled_qty'];
            $want = $items === null ? $left : (int) ($items[(int) $r['id']] ?? $items[(string) $r['id']] ?? 0);
            if ($want === 0) {
                continue;
            }
            if ($want < 0 || $want > $left) {
                throw new OrderActionException("Only $left of '{$r['product_name']}' left to fulfil.");
            }
            $upd->execute([$want, $r['id'], $orderId, $want]);
            $done[] = ['item_id' => (int) $r['id'], 'name' => $r['product_name'], 'quantity' => $want];
        }
        if (!$done) {
            throw new OrderActionException("Nothing left to fulfil on order #$orderId.");
        }

        $st = $conn->prepare('SELECT SUM(quantity) AS q, SUM(fulfilled_qty) AS f FROM order_items WHERE order_id = ?');
        $st->execute([$orderId]);
        $sum = $st->fetch(PDO::FETCH_ASSOC);
        $state = (int) $sum['f'] >= (int) $sum['q'] ? 'fulfilled' : ((int) $sum['f'] > 0 ? 'partial' : 'unfulfilled');
        $conn->prepare('UPDATE orders SET fulfillment_status = ? WHERE id = ?')->execute([$state, $orderId]);

        order_event($conn, $orderId, 'fulfilled', ['items' => $done, 'partial' => $state !== 'fulfilled'], 'staff', $adminId);
        $status = order_sync_status($conn, $orderId);
        $units = array_sum(array_column($done, 'quantity'));
        audit('order.fulfil', 'order', $orderId, "Fulfilled $units item" . ($units === 1 ? '' : 's') . " on order #$orderId",
            ['fulfillment_status' => $order['fulfillment_status'], 'status' => $order['status']], ['fulfillment_status' => $state, 'status' => $status]);
        return order_get($conn, $orderId);
    });
}

/**
 * Hand the order to a courier. Fulfils anything not yet fulfilled, creates an order_shipments row.
 *
 * @param string      $courier  e.g. "TCS" ('' when not recorded, legacy status changes)
 * @param string|null $tracking courier tracking number
 * @return array the updated order row
 * @throws OrderActionException when cancelled, delivered or already shipped
 */
function order_ship(PDO $conn, int $orderId, string $courier, ?string $tracking = null, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    $courier = mb_substr(trim($courier), 0, 60);
    $tracking = $tracking !== null && trim($tracking) !== '' ? mb_substr(trim($tracking), 0, 80) : null;
    return order_tx($conn, function () use ($conn, $orderId, $courier, $tracking, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'shipped');
        if (in_array($order['status'], ['Shipped', 'Delivered'], true)) {
            throw new OrderActionException("Order #$orderId is already {$order['status']}.");
        }
        if ($order['fulfillment_status'] !== 'fulfilled') {
            order_fulfil($conn, $orderId, null, $adminId);
        }
        $conn->prepare('INSERT INTO order_shipments (order_id, courier, tracking_number, shipped_at) VALUES (?, ?, ?, NOW())')
            ->execute([$orderId, $courier, $tracking]);
        $shipmentId = (int) $conn->lastInsertId();
        order_event($conn, $orderId, 'shipped', ['shipment_id' => $shipmentId, 'courier' => $courier, 'tracking' => $tracking], 'staff', $adminId);
        $status = order_sync_status($conn, $orderId);
        audit('order.ship', 'order', $orderId, "Shipped order #$orderId" . ($courier !== '' ? " with $courier" : ''),
            ['status' => $order['status']], ['status' => $status, 'courier' => $courier, 'tracking' => $tracking]);
        return order_get($conn, $orderId);
    });
}

/**
 * Mark the shipped order as delivered. Cash-on-delivery orders are marked paid at the same
 * time (the courier collected the cash), with their own payment_recorded event.
 *
 * @return array the updated order row
 * @throws OrderActionException when cancelled or already delivered
 */
function order_deliver(PDO $conn, int $orderId, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    return order_tx($conn, function () use ($conn, $orderId, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'delivered');
        if ($order['status'] === 'Delivered') {
            throw new OrderActionException("Order #$orderId is already delivered.");
        }
        $st = $conn->prepare('SELECT id FROM order_shipments WHERE order_id = ? AND delivered_at IS NULL ORDER BY shipped_at DESC, id DESC LIMIT 1');
        $st->execute([$orderId]);
        $shipmentId = $st->fetchColumn();
        if ($shipmentId === false) {
            order_ship($conn, $orderId, '', null, $adminId);   // delivered by hand / courier not recorded
            $st->execute([$orderId]);
            $shipmentId = $st->fetchColumn();
        }
        $conn->prepare('UPDATE order_shipments SET delivered_at = NOW() WHERE id = ?')->execute([(int) $shipmentId]);
        order_event($conn, $orderId, 'delivered', ['shipment_id' => (int) $shipmentId], 'staff', $adminId);

        $fresh = order_get($conn, $orderId);
        if ($fresh['payment_status'] === 'pending' && $fresh['payment_method'] === 'cash_on_delivery') {
            order_record_payment($conn, $orderId, (float) $fresh['total'], 'cash_on_delivery', $adminId);
        }
        $status = order_sync_status($conn, $orderId);
        audit('order.deliver', 'order', $orderId, "Marked order #$orderId delivered", ['status' => $order['status']], ['status' => $status]);
        return order_get($conn, $orderId);
    });
}

/**
 * Cancel an order that has not shipped: restocks every line through adjust_inventory()
 * (reason cancelled_order), sets cancelled_at / cancel_reason and status Cancelled.
 *
 * @return array the updated order row (plus 'restocked' => units put back)
 * @throws OrderActionException when already cancelled, shipped or delivered
 */
function order_cancel(PDO $conn, int $orderId, string $reason, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    $reason = mb_substr(trim($reason), 0, 255);
    if ($reason === '') {
        throw new OrderActionException('Please give a reason for cancelling.');
    }
    return order_tx($conn, function () use ($conn, $orderId, $reason, $adminId): array {
        $order = order_lock($conn, $orderId);
        if ($order['cancelled_at'] !== null || $order['status'] === 'Cancelled') {
            throw new OrderActionException("Order #$orderId is already cancelled.");
        }
        if (in_array($order['status'], ['Shipped', 'Delivered'], true)) {
            throw new OrderActionException("Order #$orderId has already been {$order['status']}; it can't be cancelled.");
        }

        $st = $conn->prepare('SELECT id, product_id, quantity, size, color FROM order_items WHERE order_id = ?');
        $st->execute([$orderId]);
        $findVar = $conn->prepare('SELECT id FROM product_variations WHERE product_id = ? AND variation_type = ? AND variation_value = ? LIMIT 1');
        $restocked = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if ($item['product_id'] === null) {
                continue;
            }
            $vid = null;
            foreach (['size' => $item['size'], 'color' => $item['color']] as $type => $value) {
                if ($value !== null && $value !== '') {
                    $findVar->execute([(int) $item['product_id'], $type, $value]);
                    $found = $findVar->fetchColumn();
                    if ($found !== false) {
                        $vid = (int) $found;
                        break;
                    }
                }
            }
            adjust_inventory($conn, (int) $item['product_id'], $vid, (int) $item['quantity'], 'cancelled_order', 'order #' . $orderId, $adminId);
            $restocked += (int) $item['quantity'];
        }

        $conn->prepare("UPDATE orders SET cancelled_at = NOW(), cancel_reason = ?, status = 'Cancelled' WHERE id = ?")->execute([$reason, $orderId]);
        order_event($conn, $orderId, 'cancelled', ['reason' => $reason, 'restocked' => $restocked], 'staff', $adminId);
        audit('order.cancel', 'order', $orderId, "Cancelled order #$orderId ($restocked item" . ($restocked === 1 ? '' : 's') . ' restocked)',
            ['status' => $order['status']], ['status' => 'Cancelled', 'reason' => $reason]);
        $out = order_get($conn, $orderId);
        $out['restocked'] = $restocked;
        return $out;
    });
}

/**
 * Add an internal staff note to an order (order_notes; shown on the timeline, never to customers).
 *
 * @return int order_notes.id
 * @throws OrderActionException when the body is empty
 */
function order_add_note(PDO $conn, int $orderId, string $body, ?int $adminId = null): int
{
    $adminId = order_actor($adminId);
    $body = trim($body);
    if ($body === '') {
        throw new OrderActionException('A note needs some text.');
    }
    return order_tx($conn, function () use ($conn, $orderId, $body, $adminId): int {
        order_lock($conn, $orderId);
        $conn->prepare('INSERT INTO order_notes (order_id, admin_id, body) VALUES (?, ?, ?)')->execute([$orderId, $adminId, mb_substr($body, 0, 5000)]);
        $id = (int) $conn->lastInsertId();
        audit('order.note', 'order', $orderId, "Added a note to order #$orderId", null, ['note' => mb_substr($body, 0, 200)]);
        return $id;
    });
}

/**
 * Legacy "set status" (orders grid dropdown): walks the order forward through the real
 * operations so events, stock and payment stay consistent. Moving backwards is refused.
 *
 *   Processing -> confirm · Shipped -> confirm + ship · Delivered -> … + deliver · Cancelled -> cancel
 *
 * @return array the updated order row
 * @throws OrderActionException for a backwards move or a refused step
 */
function order_set_status(PDO $conn, int $orderId, string $target, ?int $adminId = null): array
{
    if (!in_array($target, ORDER_STATUSES, true)) {
        throw new OrderActionException('Unknown status: ' . $target);
    }
    $adminId = order_actor($adminId);
    return order_tx($conn, function () use ($conn, $orderId, $target, $adminId): array {
        $order = order_lock($conn, $orderId);
        if ($order['status'] === $target) {
            return $order;
        }
        if ($target === 'Cancelled') {
            return order_cancel($conn, $orderId, 'Cancelled from the orders list', $adminId);
        }
        $rank = array_flip(['Pending', 'Processing', 'Shipped', 'Delivered']);
        if ($order['status'] === 'Cancelled' || $rank[$target] < $rank[$order['status']]) {
            throw new OrderActionException("Order #$orderId is {$order['status']}; it can't go back to $target.");
        }
        if ($order['status'] === 'Pending') {
            order_confirm($conn, $orderId, $adminId);
        }
        if ($target === 'Processing') {
            return order_get($conn, $orderId);
        }
        if (order_get($conn, $orderId)['status'] !== 'Shipped') {
            order_ship($conn, $orderId, '', null, $adminId);
        }
        if ($target === 'Delivered') {
            order_deliver($conn, $orderId, $adminId);
        }
        return order_get($conn, $orderId);
    });
}
