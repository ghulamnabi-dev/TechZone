<?php
/**
 * Wishlist. Signed-in customers: the `wishlists` table (customer_id). Guests: product ids in the
 * PHP session ($_SESSION['wishlist']), merged into the account on sign-in
 * (wishlist_merge_session(), called from MarketingSite/api/login.php). Data only: no HTML.
 */

require_once __DIR__ . '/errors.php';

/** Most products one wishlist keeps (guest or account). */
const WISHLIST_MAX = 100;

/** The signed-in customer's id, or 0 for a guest. @internal */
function wishlist_customer_id(): int
{
    return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
}

/** Guest list from the session (newest first). @internal */
function wishlist_session_ids(): array
{
    $ids = isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist']) ? $_SESSION['wishlist'] : [];
    return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
}

/**
 * Product ids on the current visitor's wishlist, newest first.
 *
 * @return int[]
 */
function wishlist_ids(PDO $conn): array
{
    $cid = wishlist_customer_id();
    if ($cid <= 0) {
        return wishlist_session_ids();
    }
    $st = $conn->prepare('SELECT product_id FROM wishlists WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
    $st->execute([$cid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** True when the product is on the visitor's wishlist. */
function wishlist_has(PDO $conn, int $productId): bool
{
    return in_array($productId, wishlist_ids($conn), true);
}

/**
 * Add or remove a product (toggle). Only visible products can be added; removing always works.
 *
 * @return array{saved:bool, count:int}  saved = on the list after the call
 * @throws InvalidArgumentException when adding a product that is not on sale, or the list is full
 */
function wishlist_toggle(PDO $conn, int $productId): array
{
    $saved = wishlist_has($conn, $productId);
    $saved ? wishlist_remove($conn, $productId) : wishlist_add($conn, $productId);
    return ['saved' => !$saved, 'count' => count(wishlist_ids($conn))];
}

/**
 * Add a product (no-op when already saved).
 *
 * @throws InvalidArgumentException product not on sale / list full
 */
function wishlist_add(PDO $conn, int $productId): void
{
    $st = $conn->prepare("SELECT COUNT(*) FROM products WHERE id = ? AND is_active = 1 AND status = 'active'");
    $st->execute([$productId]);
    if ((int) $st->fetchColumn() === 0) {
        throw new InvalidArgumentException('That product is not available.');
    }
    $ids = wishlist_ids($conn);
    if (in_array($productId, $ids, true)) {
        return;
    }
    if (count($ids) >= WISHLIST_MAX) {
        throw new InvalidArgumentException('Your wishlist is full. Remove something first.');
    }
    $cid = wishlist_customer_id();
    if ($cid <= 0) {
        array_unshift($ids, $productId);
        $_SESSION['wishlist'] = $ids;
        return;
    }
    $conn->prepare('INSERT IGNORE INTO wishlists (customer_id, session_id, product_id) VALUES (?, NULL, ?)')->execute([$cid, $productId]);
}

/** Remove a product (no-op when not saved). */
function wishlist_remove(PDO $conn, int $productId): void
{
    $cid = wishlist_customer_id();
    if ($cid <= 0) {
        $_SESSION['wishlist'] = array_values(array_diff(wishlist_session_ids(), [$productId]));
        return;
    }
    $conn->prepare('DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?')->execute([$cid, $productId]);
}

/**
 * Move the guest (session) wishlist into a customer's account; call right after sign-in.
 * Products no longer on sale are dropped; the account limit still applies.
 *
 * @return int products added to the account
 */
function wishlist_merge_session(PDO $conn, int $customerId): int
{
    $ids = wishlist_session_ids();
    unset($_SESSION['wishlist']);
    if ($customerId <= 0 || !$ids) {
        return 0;
    }
    try {
        $st = $conn->prepare('SELECT COUNT(*) FROM wishlists WHERE customer_id = ?');
        $st->execute([$customerId]);
        $room = WISHLIST_MAX - (int) $st->fetchColumn();
        $ins = $conn->prepare("INSERT IGNORE INTO wishlists (customer_id, session_id, product_id)
            SELECT ?, ?, p.id FROM products p WHERE p.id = ? AND p.is_active = 1 AND p.status = 'active'");
        $added = 0;
        foreach (array_reverse($ids) as $pid) {      // oldest first, so the newest ends up on top
            if ($room - $added <= 0) {
                break;
            }
            $ins->execute([$customerId, substr((string) session_id(), 0, 128), $pid]);
            $added += $ins->rowCount();
        }
        return $added;
    } catch (PDOException $e) {
        log_exception($e, 'wishlist-merge');   // never fail a sign-in over the wishlist
        return 0;
    }
}
