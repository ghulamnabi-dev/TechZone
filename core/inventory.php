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


