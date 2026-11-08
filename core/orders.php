<?php
/**
 * Atomic order placement (checkout).
 *
 * Everything happens in ONE transaction:
 *   1. lock every product row (and selected size/color variation rows) with
 *      SELECT ... FOR UPDATE, in id order to avoid deadlocks,
 *   2. re-check stock against the total quantity requested per row
 *      (two cart lines of the same product are summed),
 *   3. re-price every line from the database (session prices are not trusted)
 *      and re-validate the promo code,
 *   4. insert the order + order_items and decrement stock with a guarded
 *      "inventory >= qty" UPDATE.
 * Any failure rolls everything back, so stock can never go negative or be oversold
 * by concurrent checkouts.
 */

require_once __DIR__ . '/errors.php';

/** Message is safe to show to the customer. */
class CheckoutException extends RuntimeException
{
}

const CHECKOUT_FREE_SHIPPING_OVER = 5000;
const CHECKOUT_SHIPPING_FEE = 500;

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
 * Discount for a promo code that is valid right now (0 when invalid/expired).
 */
function order_promo_discount(PDO $conn, ?string $promoCode, float $subtotal): array
{
    if ($promoCode === null || $promoCode === '') {
        return [0.0, null];
    }
    $stmt = $conn->prepare("SELECT promo_code, discount_percentage, discount_amount FROM promotions
        WHERE UPPER(promo_code) = UPPER(?) AND is_active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date >= CURDATE())
        LIMIT 1");
    $stmt->execute([$promoCode]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$promo) {
        return [0.0, null];
    }
    $discount = 0.0;
    if ((float) $promo['discount_percentage'] > 0) {
        $discount = $subtotal * (float) $promo['discount_percentage'] / 100;
    } elseif ((float) $promo['discount_amount'] > 0) {
        $discount = (float) $promo['discount_amount'];
    }
    return [min($discount, $subtotal), strtoupper($promoCode)];
}

/**
 * Place an order for the given session cart.
 *
 * @param array $contact customer_name, customer_email, customer_phone, city, payment_method
 * @return array{order_id:int,total:float}
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

    $conn->beginTransaction();
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

        // 4. Totals (server-side only)
        [$discount, $validPromo] = order_promo_discount($conn, $promoCode, $subtotal);
        $shipping = $subtotal > CHECKOUT_FREE_SHIPPING_OVER ? 0 : CHECKOUT_SHIPPING_FEE;
        $total = max(0, round($subtotal - $discount + $shipping, 2));

        // 5. Write order, items, stock
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, customer_name, customer_email, customer_phone, city, total, status, payment_method, promo_code)
                                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $stmt->execute([
            $customerId,
            $contact['customer_name'],
            $contact['customer_email'],
            $contact['customer_phone'],
            $contact['city'],
            $total,
            $contact['payment_method'],
            $validPromo,
        ]);
        $orderId = (int) $conn->lastInsertId();

        $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, size, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $decProduct = $conn->prepare("UPDATE products SET inventory = inventory - ?, sold = sold + ? WHERE id = ? AND inventory >= ?");
        $decVariation = $conn->prepare("UPDATE product_variations SET inventory = inventory - ? WHERE id = ? AND inventory >= ?");

        foreach ($lines as $line) {
            $qty = $line['quantity'];
            $insertItem->execute([$orderId, $line['product_id'], $line['name'], $line['price'], $qty, round($line['price'] * $qty, 2), $line['size'], $line['color']]);

            $decProduct->execute([$qty, $qty, $line['product_id'], $qty]);
            if ($decProduct->rowCount() !== 1) {
                throw new CheckoutException('Product ' . $line['label'] . ' just sold out. Please update your cart.');
            }
            foreach ($line['variation_ids'] as $vid) {
                $decVariation->execute([$qty, $vid, $qty]);
                if ($decVariation->rowCount() !== 1) {
                    throw new CheckoutException('Product ' . $line['label'] . ' just sold out. Please update your cart.');
                }
            }
        }

        $conn->commit();
        return ['order_id' => $orderId, 'total' => $total];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
