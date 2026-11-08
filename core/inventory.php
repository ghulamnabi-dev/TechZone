<?php

/**
 * Shared helpers for product inventory and variation pricing.
 *
 * These functions are used by both the marketing site and admin APIs
 * to keep inventory/variation logic consistent and easier to maintain.
 */

/**
 * Determine if the products table has an is_active column.
 * Result is cached per request for performance.
 */
function hasIsActiveColumn(PDO $conn): bool
{
    static $hasIsActive = null;

    if ($hasIsActive !== null) {
        return $hasIsActive;
    }

    try {
        $conn->query("SELECT is_active FROM products LIMIT 1");
        $hasIsActive = true;
    } catch (PDOException $e) {
        $hasIsActive = false;
    }

    return $hasIsActive;
}

/**
 * Get available inventory for a product, taking variations into account.
 *
 * Returns:
 * - int >= 0 : available quantity
 * - null     : product not found or inactive
 */
function getAvailableInventory(PDO $conn, int $productId, ?string $size = null, ?string $color = null): ?int
{
    $query = "SELECT inventory FROM products WHERE id = ?";
    if (hasIsActiveColumn($conn)) {
        $query .= " AND is_active = 1";
    }

    $stmt = $conn->prepare($query);
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return null;
    }

    $availableInventory = (int)$product['inventory'];

    // Size-based variation inventory
    if ($size) {
        try {
            $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'size' AND variation_value = ? AND is_active = 1");
            $stmt->execute([$productId, $size]);
            $sizeVariation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sizeVariation && isset($sizeVariation['inventory'])) {
                $variationInventory = (int)$sizeVariation['inventory'];
                $availableInventory = min($availableInventory, $variationInventory);
            }
        } catch (PDOException $e) {
            // Variations table might not exist; fall back to base inventory
        }
    }

    // Color-based variation inventory
    if ($color) {
        try {
            $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'color' AND variation_value = ? AND is_active = 1");
            $stmt->execute([$productId, $color]);
            $colorVariation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($colorVariation && isset($colorVariation['inventory'])) {
                $variationInventory = (int)$colorVariation['inventory'];
                $availableInventory = min($availableInventory, $variationInventory);
            }
        } catch (PDOException $e) {
            // Variations table might not exist; fall back to current inventory
        }
    }

    return $availableInventory;
}

/**
 * Calculate final price including any variation price modifiers.
 */
function calculateFinalPriceWithVariations(PDO $conn, int $productId, float $basePrice, ?string $size = null, ?string $color = null): float
{
    $priceModifier = 0.0;

    try {
        if ($size) {
            $stmt = $conn->prepare("SELECT price_modifier FROM product_variations WHERE product_id = ? AND variation_type = 'size' AND variation_value = ? AND is_active = 1");
            $stmt->execute([$productId, $size]);
            $sizeVariation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sizeVariation && isset($sizeVariation['price_modifier'])) {
                $priceModifier += (float)$sizeVariation['price_modifier'];
            }
        }

        if ($color) {
            $stmt = $conn->prepare("SELECT price_modifier FROM product_variations WHERE product_id = ? AND variation_type = 'color' AND variation_value = ? AND is_active = 1");
            $stmt->execute([$productId, $color]);
            $colorVariation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($colorVariation && isset($colorVariation['price_modifier'])) {
                $priceModifier += (float)$colorVariation['price_modifier'];
            }
        }
    } catch (PDOException $e) {
        // Variations table might not exist; use base price
    }

    return $basePrice + $priceModifier;
}


/* ==========================================================================
   Stock ledger (docs/ADMIN.md: "stock is only changed through this")
   ========================================================================== */

/** Reasons allowed by inventory_adjustments.reason. */
const INVENTORY_REASONS = ['received', 'sold', 'returned', 'damaged', 'correction', 'cancelled_order'];

/** Stock change refused (would go negative, unknown product). Message is safe to show. */
class InventoryException extends RuntimeException
{
}

/**
 * Change stock by $delta and record it in inventory_adjustments. The ONLY way stock changes.
 *
 * - products.inventory always moves by $delta; when $variationId is given, that variation's
 *   inventory moves by the same delta (a variation's stock is part of the product's stock).
 * - Both UPDATEs are guarded ("inventory + delta >= 0"), so stock can never go negative even
 *   under concurrent writers; a refused change throws InventoryException.
 * - products.sold follows the ledger: 'sold' adds -delta, 'cancelled_order' / 'returned' take it back.
 * - Runs inside the caller's transaction (place_order, order_cancel …). Called outside one it
 *   opens and commits its own, so the three writes are always atomic.
 *
 * @param PDO         $conn
 * @param int         $productId
 * @param int|null    $variationId product_variations.id or null for product-level stock
 * @param int         $delta       positive = stock in, negative = stock out; 0 is a no-op
 * @param string      $reason      one of INVENTORY_REASONS
 * @param string|null $reference   "order #12", "PO 2026-11-04", free text (max 120)
 * @param int|null    $adminId     staff member responsible (null = customer / system)
 * @return int inventory_adjustments.id (0 when $delta is 0)
 * @throws InventoryException   when the change would make stock negative or the row is missing
 * @throws InvalidArgumentException for an unknown reason
 */
function adjust_inventory(PDO $conn, int $productId, ?int $variationId, int $delta, string $reason, ?string $reference, ?int $adminId): int
{
    if (!in_array($reason, INVENTORY_REASONS, true)) {
        throw new InvalidArgumentException('Unknown inventory reason: ' . $reason);
    }
    if ($delta === 0) {
        return 0;
    }

    $own = !$conn->inTransaction();
    if ($own) {
        $conn->beginTransaction();
    }
    try {
        $soldExpr = 'sold';
        if ($reason === 'sold') {
            $soldExpr = 'sold - ?';                 // delta is negative: sold grows
        } elseif ($reason === 'cancelled_order' || $reason === 'returned') {
            $soldExpr = 'GREATEST(sold - ?, 0)';    // delta is positive: sold shrinks
        }
        $params = [$delta];
        if ($soldExpr !== 'sold') {
            $params[] = $delta;
        }
        array_push($params, $productId, $delta);
        $st = $conn->prepare("UPDATE products SET inventory = inventory + ?, sold = $soldExpr WHERE id = ? AND inventory + ? >= 0");
        $st->execute($params);
        if ($st->rowCount() !== 1) {
            $chk = $conn->prepare('SELECT name, inventory FROM products WHERE id = ?');
            $chk->execute([$productId]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            throw new InventoryException($row
                ? "Only {$row['inventory']} left of '{$row['name']}'."
                : 'Product #' . $productId . ' no longer exists.');
        }

        if ($variationId !== null) {
            $st = $conn->prepare('UPDATE product_variations SET inventory = inventory + ? WHERE id = ? AND product_id = ? AND inventory + ? >= 0');
            $st->execute([$delta, $variationId, $productId, $delta]);
            if ($st->rowCount() !== 1) {
                $chk = $conn->prepare('SELECT variation_value, inventory FROM product_variations WHERE id = ? AND product_id = ?');
                $chk->execute([$variationId, $productId]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                throw new InventoryException($row
                    ? "Only {$row['inventory']} left of option '{$row['variation_value']}'."
                    : 'That product option no longer exists.');
            }
        }

        $st = $conn->prepare('INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$productId, $variationId, $delta, $reason, $reference !== null ? mb_substr($reference, 0, 120) : null, $adminId]);
        $id = (int) $conn->lastInsertId();

        if ($own) {
            $conn->commit();
        }
        return $id;
    } catch (Throwable $e) {
        if ($own && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

/**
 * Products at or below their low-stock threshold (per product, falling back to the
 * low_stock_threshold setting), excluding archived ones. Lowest stock first.
 *
 * @return array<int, array{id:string,name:string,sku:?string,inventory:string,threshold:string,imagelink:?string,status:string}>
 */
function low_stock_products(PDO $conn, int $limit = 50): array
{
    $fallback = 5;
    if (function_exists('setting')) {
        $fallback = (int) setting('low_stock_threshold', 5);
    }
    $limit = max(1, min(500, $limit));
    $st = $conn->prepare("SELECT id, name, sku, inventory, COALESCE(low_stock_threshold, ?) AS threshold, imagelink, status
        FROM products
        WHERE status <> 'archived' AND inventory <= COALESCE(low_stock_threshold, ?)
        ORDER BY inventory ASC, name ASC
        LIMIT $limit");
    $st->execute([$fallback, $fallback]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
