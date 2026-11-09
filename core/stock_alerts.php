<?php
/**
 * Back-in-stock alerts ("Notify me" on an out-of-stock product). Data only: no HTML, no mail.
 * A mailer (or the admin) reads stock_alerts_due() and marks rows with stock_alerts_mark_notified().
 */

require_once __DIR__ . '/errors.php';

/**
 * Subscribe an email to a product. Idempotent: subscribing again re-arms a notified alert.
 *
 * @param string $email already validated (v_email())
 * @return array{created:bool, in_stock:bool}  created = a new alert row; in_stock = no need to wait
 * @throws InvalidArgumentException when the product is not on sale
 */
function stock_alert_subscribe(PDO $conn, int $productId, string $email): array
{
    $st = $conn->prepare("SELECT inventory FROM products WHERE id = ? AND is_active = 1 AND status = 'active'");
    $st->execute([$productId]);
    $inv = $st->fetchColumn();
    if ($inv === false) {
        throw new InvalidArgumentException('That product is not available.');
    }
    $st = $conn->prepare('INSERT INTO stock_alerts (product_id, email) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE created_at = IF(notified_at IS NULL, created_at, NOW()), notified_at = NULL');   // left to right
    $st->execute([$productId, strtolower($email)]);
    return ['created' => $st->rowCount() === 1, 'in_stock' => (int) $inv > 0];
}

/**
 * Emails still waiting for a product.
 *
 * @return string[]
 */
function stock_alerts_pending(PDO $conn, int $productId): array
{
    $st = $conn->prepare('SELECT email FROM stock_alerts WHERE product_id = ? AND notified_at IS NULL ORDER BY created_at');
    $st->execute([$productId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Products that are back in stock and have people waiting.
 *
 * @return array<int, array{product_id:int, name:string, inventory:int, waiting:int}>
 */
function stock_alerts_due(PDO $conn): array
{
    $rows = $conn->query("SELECT p.id AS product_id, p.name, p.inventory, COUNT(a.id) AS waiting
        FROM stock_alerts a JOIN products p ON p.id = a.product_id
        WHERE a.notified_at IS NULL AND p.inventory > 0 AND p.is_active = 1
        GROUP BY p.id, p.name, p.inventory ORDER BY waiting DESC")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn($r) => ['product_id' => (int) $r['product_id'], 'name' => $r['name'], 'inventory' => (int) $r['inventory'], 'waiting' => (int) $r['waiting']], $rows);
}

/**
 * Mark every waiting alert for a product as sent.
 *
 * @return int rows marked
 */
function stock_alerts_mark_notified(PDO $conn, int $productId): int
{
    $st = $conn->prepare('UPDATE stock_alerts SET notified_at = NOW() WHERE product_id = ? AND notified_at IS NULL');
    $st->execute([$productId]);
    return $st->rowCount();
}
