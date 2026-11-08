<?php
/**
 * TechZone schema migration (CLI, idempotent).
 *
 *   DATABASE_URL=mysql://user:pass@host:port/techzone_db php database/migrate.php
 *
 * 1. Runs database/schema.sql (every CREATE TABLE IF NOT EXISTS: new tables appear, old ones are untouched).
 * 2. Adds each new column only when information_schema says it is missing, then backfills it from
 *    existing data (so the derived values are written exactly once, on the run that adds the column).
 * 3. Opens the stock ledger: any product whose SUM(inventory_adjustments.delta) differs from
 *    products.inventory gets one 'correction' row ("opening balance"), so the ledger identity holds.
 *
 * Safe to run any number of times; prints what it changed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/database.php';   // $conn (PDO, exceptions on)

/** @var PDO $conn */
$db = (string) $conn->query('SELECT DATABASE()')->fetchColumn();
$changes = 0;

/** Print a progress line. */
function say(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

/** True when $table.$column exists in the current database. */
function column_exists(PDO $conn, string $db, string $table, string $column): bool
{
    $st = $conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$db, $table, $column]);
    return (int) $st->fetchColumn() > 0;
}

/** True when an index named $index exists on $table. */
function index_exists(PDO $conn, string $db, string $table, string $index): bool
{
    $st = $conn->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$db, $table, $index]);
    return (int) $st->fetchColumn() > 0;
}

// ---------------------------------------------------------------------------
// 1. schema.sql (new tables)
// ---------------------------------------------------------------------------
$sql = (string) file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (preg_split('/;\s*(?:\R|$)/', $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt !== '') {
        $conn->exec($stmt);
    }
}
say('schema.sql applied (CREATE TABLE IF NOT EXISTS)');

// ---------------------------------------------------------------------------
// 2. columns: table => [column => [definition, backfill SQL (run once, when added)]]
// ---------------------------------------------------------------------------
$columns = [
    'admins' => [
        'role'          => ["ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff' AFTER password",
                            // the first account becomes the owner; later sign-ups stay staff
                            "UPDATE admins SET role = 'owner' WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM admins) x)"],
        'is_active'     => ['TINYINT(1) NOT NULL DEFAULT 1 AFTER role', null],
        'last_login_at' => ['DATETIME DEFAULT NULL AFTER is_active', null],
    ],
    'products' => [
        'status'              => ["ENUM('draft','active','archived') NOT NULL DEFAULT 'active' AFTER is_active",
                                  "UPDATE products SET status = IF(is_active = 1, 'active', 'draft')"],
        'sku'                 => ['VARCHAR(40) DEFAULT NULL AFTER status',
                                  "UPDATE products SET sku = CONCAT('TZ-', LPAD(id, 4, '0')) WHERE sku IS NULL"],
        'compare_at_price'    => ['DECIMAL(12,2) DEFAULT NULL AFTER sku', null],
        'cost_price'          => ['DECIMAL(12,2) DEFAULT NULL AFTER compare_at_price', null],
        'low_stock_threshold' => ['INT NOT NULL DEFAULT 5 AFTER cost_price', null],
        'seo_title'           => ['VARCHAR(200) DEFAULT NULL AFTER low_stock_threshold', null],
        'seo_description'     => ['VARCHAR(320) DEFAULT NULL AFTER seo_title', null],
    ],
    'orders' => [
        'subtotal'           => ['DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER city',
                                 'UPDATE orders o SET subtotal = (SELECT COALESCE(SUM(i.subtotal), 0) FROM order_items i WHERE i.order_id = o.id)'],
        // whatever the old total had on top of the items was shipping; anything below was a discount
        'shipping_fee'       => ['DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal',
                                 'UPDATE orders SET shipping_fee = GREATEST(0, total - subtotal)'],
        'discount_amount'    => ['DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER shipping_fee',
                                 'UPDATE orders SET discount_amount = GREATEST(0, subtotal - total)'],
        'payment_status'     => ["ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending' AFTER status",
                                 "UPDATE orders SET payment_status = IF(status = 'Delivered', 'paid', 'pending')"],
        'fulfillment_status' => ["ENUM('unfulfilled','partial','fulfilled') NOT NULL DEFAULT 'unfulfilled' AFTER payment_status",
                                 "UPDATE orders SET fulfillment_status = IF(status IN ('Shipped','Delivered'), 'fulfilled', 'unfulfilled')"],
        'shipping_zone_id'   => ['INT UNSIGNED DEFAULT NULL AFTER fulfillment_status', null],
        'cancel_reason'      => ['VARCHAR(255) DEFAULT NULL AFTER promo_code', null],
        'cancelled_at'       => ['DATETIME DEFAULT NULL AFTER cancel_reason',
                                 "UPDATE orders SET cancelled_at = updated_at WHERE status = 'Cancelled'"],
    ],
    'order_items' => [
        'fulfilled_qty' => ['INT NOT NULL DEFAULT 0 AFTER quantity',
                            "UPDATE order_items i JOIN orders o ON o.id = i.order_id SET i.fulfilled_qty = i.quantity WHERE o.status IN ('Shipped','Delivered')"],
    ],
    'promotions' => [
        'kind'        => ["ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent' AFTER promo_code",
                          "UPDATE promotions SET kind = IF(discount_percentage > 0, 'percent', 'fixed')"],
        'applies_to'  => ["ENUM('all','category','product') NOT NULL DEFAULT 'all' AFTER kind", null],
        'target_id'   => ['INT UNSIGNED DEFAULT NULL AFTER applies_to', null],
        'min_order'   => ['DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER target_id', null],
        'usage_limit' => ['INT UNSIGNED DEFAULT NULL AFTER min_order', null],
        'used_count'  => ['INT UNSIGNED NOT NULL DEFAULT 0 AFTER usage_limit',
                          'UPDATE promotions p SET used_count = (SELECT COUNT(*) FROM orders o WHERE o.promo_code IS NOT NULL AND UPPER(o.promo_code) = UPPER(p.promo_code))'],
        'starts_at'   => ['DATETIME DEFAULT NULL AFTER used_count',
                          'UPDATE promotions SET starts_at = TIMESTAMP(start_date) WHERE start_date IS NOT NULL'],
        'ends_at'     => ['DATETIME DEFAULT NULL AFTER starts_at',
                          "UPDATE promotions SET ends_at = TIMESTAMP(end_date, '23:59:59') WHERE end_date IS NOT NULL"],
    ],
];

foreach ($columns as $table => $cols) {
    foreach ($cols as $column => [$definition, $backfill]) {
        if (column_exists($conn, $db, $table, $column)) {
            continue;
        }
        $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $changes++;
        say("+ $table.$column");
        if ($backfill !== null) {
            $n = $conn->exec($backfill);
            say("  backfilled $n row(s)");
        }
    }
}

// COD orders are paid when the courier delivers them; that rule is already covered by
// "Delivered -> paid" above. Cancelled orders keep payment 'pending' (nothing was collected).

$indexes = [
    ['products', 'uq_products_sku', 'ADD UNIQUE KEY uq_products_sku (sku)'],
    ['products', 'idx_products_status', 'ADD KEY idx_products_status (status)'],
    ['orders', 'idx_orders_payment', 'ADD KEY idx_orders_payment (payment_status)'],
    ['orders', 'idx_orders_fulfillment', 'ADD KEY idx_orders_fulfillment (fulfillment_status)'],
];
foreach ($indexes as [$table, $name, $ddl]) {
    if (!index_exists($conn, $db, $table, $name)) {
        $conn->exec("ALTER TABLE `$table` $ddl");
        $changes++;
        say("+ index $table.$name");
    }
}

// ---------------------------------------------------------------------------
// 3. stock ledger opening balance
// ---------------------------------------------------------------------------
$n = $conn->exec("INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at)
    SELECT p.id, NULL, p.inventory - COALESCE(a.s, 0), 'correction', 'Opening balance (migration)', NULL, NOW()
    FROM products p
    LEFT JOIN (SELECT product_id, SUM(delta) AS s FROM inventory_adjustments GROUP BY product_id) a ON a.product_id = p.id
    WHERE p.inventory <> COALESCE(a.s, 0)");
if ($n > 0) {
    $changes++;
    say("ledger: $n opening-balance row(s)");
}

$bad = (int) $conn->query("SELECT COUNT(*) FROM products p
    LEFT JOIN (SELECT product_id, SUM(delta) AS s FROM inventory_adjustments GROUP BY product_id) a ON a.product_id = p.id
    WHERE p.inventory <> COALESCE(a.s, 0)")->fetchColumn();
say($changes === 0 ? 'Nothing to do: schema is up to date.' : "Done: $changes change(s).");
say('Inventory ledger identity: ' . ($bad === 0 ? 'OK' : "$bad product(s) out of balance"));
exit($bad === 0 ? 0 : 1);
