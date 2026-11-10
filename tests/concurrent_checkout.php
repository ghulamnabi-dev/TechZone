<?php
/**
 * Concurrent checkout test: can more buyers than there is stock all succeed at once?
 *
 *   php tests/concurrent_checkout.php                 12 buyers, 3 units (the default)
 *   php tests/concurrent_checkout.php --buyers=30 --stock=5
 *   php tests/concurrent_checkout.php --keep           leave the test product and orders in place
 *
 * What it does
 *   1. Creates a throwaway product and gives it --stock units through adjust_inventory(),
 *      the same way staff receive stock.
 *   2. Starts --buyers separate PHP processes. Each opens its own database connection,
 *      waits for the same starting instant, then calls place_order() for one unit.
 *   3. Checks the outcome in the database, not in what the buyers reported:
 *        - exactly --stock orders exist for the product,
 *        - every other buyer was refused with a customer-safe message,
 *        - products.inventory is 0 and products.sold equals --stock,
 *        - the stock ledger adds up to the stock on hand.
 *   4. Removes the product and its orders again (unless --keep).
 *
 * It needs the schema and the demo seed (for customer rows) and the same database settings
 * the application uses: MYSQL_URL or DATABASE_URL, or DB_HOST / DB_USER / DB_PASS / DB_NAME.
 * With Docker:  docker compose exec web php tests/concurrent_checkout.php
 *
 * Exit code 0 when every check passes, 1 when one fails, 2 when the test could not run.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/orders.php';

/** A connection with the same options the application uses (core/database.php), without sessions or headers. */
function cc_connect(): PDO
{
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
    if ($url !== '' && (strpos($url, 'mysql://') === 0 || strpos($url, 'mysql2://') === 0)) {
        $p = parse_url($url);
        $host = $p['host'] ?? 'localhost';
        $user = isset($p['user']) ? rawurldecode($p['user']) : 'root';
        $pass = isset($p['pass']) ? rawurldecode($p['pass']) : '';
        $name = isset($p['path']) ? trim(rawurldecode($p['path']), '/') : 'techzone_db';
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
        if (!empty($p['port'])) {
            $dsn .= ';port=' . (int) $p['port'];
        }
    } else {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'techzone_db';
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    }
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => true,
    ]);
}

/** One buyer: connect, wait for the shared starting instant, try to buy one unit, report one JSON line. */
function cc_buyer(int $productId, int $customerId, float $startAt): int
{
    try {
        $conn = cc_connect();
        $wait = $startAt - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1000000));
        }
        $order = place_order($conn, $customerId, [['id' => $productId, 'quantity' => 1]], [
            'customer_name'  => 'Concurrency Test',
            'customer_email' => 'concurrency-test@example.invalid',
            'customer_phone' => '0000000000',
            'city'           => 'Lahore',
            'payment_method' => 'cash_on_delivery',
        ], null);
        echo json_encode(['result' => 'placed', 'order_id' => $order['order_id']]), "\n";
        return 0;
    } catch (CheckoutException $e) {
        echo json_encode(['result' => 'refused', 'message' => $e->getMessage()]), "\n";
        return 0;
    } catch (Throwable $e) {
        echo json_encode(['result' => 'error', 'message' => get_class($e) . ': ' . $e->getMessage()]), "\n";
        return 2;
    }
}

/* ---------------------------------------------------------------- arguments */

$buyers = 12;
$stock = 3;
$keep = false;
$buyer = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--buyers=(\d+)$/', $arg, $m)) {
        $buyers = (int) $m[1];
    } elseif (preg_match('/^--stock=(\d+)$/', $arg, $m)) {
        $stock = (int) $m[1];
    } elseif ($arg === '--keep') {
        $keep = true;
    } elseif (preg_match('/^--buyer=(\d+),(\d+),([0-9.]+)$/', $arg, $m)) {
        $buyer = [(int) $m[1], (int) $m[2], (float) $m[3]];   // internal: product, customer, start time
    } else {
        fwrite(STDERR, "Unknown argument: $arg\n");
        exit(2);
    }
}

if ($buyer !== null) {
    exit(cc_buyer($buyer[0], $buyer[1], $buyer[2]));
}

if ($buyers < 2 || $buyers > 200 || $stock < 1 || $stock >= $buyers) {
    fwrite(STDERR, "Use 2 to 200 buyers and a stock of at least 1 that is smaller than the number of buyers.\n");
    exit(2);
}

/* ------------------------------------------------------------------- set up */

try {
    $conn = cc_connect();
    $customers = array_map('intval', $conn->query('SELECT id FROM customers ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not reach the database: ' . $e->getMessage() . "\n");
    exit(2);
}
if (!$customers) {
    fwrite(STDERR, "No customers found. Load database/seed.sql first.\n");
    exit(2);
}

$tag = 'CC-' . strtoupper(bin2hex(random_bytes(4)));
$conn->prepare("INSERT INTO products (name, description, price, inventory, status, is_active, sku)
                VALUES (?, 'Created by tests/concurrent_checkout.php. Safe to delete.', 1000.00, 0, 'active', 1, ?)")
     ->execute(['Concurrency test item ' . $tag, $tag]);
$productId = (int) $conn->lastInsertId();
adjust_inventory($conn, $productId, null, $stock, 'received', 'concurrency test ' . $tag, null);

echo "Concurrent checkout test\n";
echo "  product #$productId ($tag) with $stock unit" . ($stock === 1 ? '' : 's') . ", $buyers buyers, one unit each\n";

/* ---------------------------------------------------------------------- run */

$startAt = microtime(true) + 2.0;   // long enough for every process to start and connect
$procs = [];
for ($i = 0; $i < $buyers; $i++) {
    $customerId = $customers[$i % count($customers)];
    $cmd = [PHP_BINARY, __FILE__, sprintf('--buyer=%d,%d,%.6F', $productId, $customerId, $startAt)];
    $pipes = [];
    $h = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($h)) {
        fwrite(STDERR, "Could not start buyer $i.\n");
        exit(2);
    }
    $procs[] = [$h, $pipes];
}

$placed = [];
$refused = [];
$errors = [];
foreach ($procs as [$h, $pipes]) {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($h);
    $line = json_decode(trim((string) $out), true);
    if (!is_array($line)) {
        $errors[] = 'no result from a buyer: ' . trim($out . ' ' . $err);
    } elseif ($line['result'] === 'placed') {
        $placed[] = (int) $line['order_id'];
    } elseif ($line['result'] === 'refused') {
        $refused[] = (string) $line['message'];
    } else {
        $errors[] = (string) $line['message'];
    }
}

/* -------------------------------------------------------------------- check */

$one = static function (string $sql, array $params) use ($conn) {
    $st = $conn->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
};
$product = $conn->prepare('SELECT inventory, sold FROM products WHERE id = ?');
$product->execute([$productId]);
$product = $product->fetch();
$ordersInDb = (int) $one('SELECT COUNT(DISTINCT order_id) FROM order_items WHERE product_id = ?', [$productId]);
$unitsInDb  = (int) $one('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = ?', [$productId]);
$ledger     = (int) $one('SELECT COALESCE(SUM(delta), 0) FROM inventory_adjustments WHERE product_id = ?', [$productId]);
$ledgerSold = (int) $one("SELECT COALESCE(SUM(delta), 0) FROM inventory_adjustments WHERE product_id = ? AND reason = 'sold'", [$productId]);

$checks = [
    ["no buyer hit an unexpected error",                    count($errors) === 0,                    count($errors) . ' error(s)'],
    ["exactly $stock buyers were told their order was placed", count($placed) === $stock,              count($placed) . ' placed'],
    ["the other " . ($buyers - $stock) . " were refused",   count($refused) === $buyers - $stock,    count($refused) . ' refused'],
    ["exactly $stock orders exist in the database",         $ordersInDb === $stock,                  "$ordersInDb order(s)"],
    ["they hold exactly $stock units between them",         $unitsInDb === $stock,                   "$unitsInDb unit(s)"],
    ["stock on hand is 0, never negative",                  (int) $product['inventory'] === 0,       'inventory ' . $product['inventory']],
    ["units sold is $stock",                                (int) $product['sold'] === $stock,       'sold ' . $product['sold']],
    ["the stock ledger adds up to the stock on hand",       $ledger === (int) $product['inventory'], "ledger $ledger"],
    ["the ledger records $stock units sold",                $ledgerSold === -$stock,                 "sold rows sum to $ledgerSold"],
];

$failed = 0;
foreach ($checks as [$label, $ok, $got]) {
    echo '  ' . ($ok ? 'ok  ' : 'FAIL') . "  $label" . ($ok ? '' : "  (got: $got)") . "\n";
    $failed += $ok ? 0 : 1;
}
foreach (array_unique($errors) as $e) {
    echo "  error: $e\n";
}
if ($refused) {
    echo '  a refused buyer saw: "' . $refused[0] . "\"\n";
}

/* ----------------------------------------------------------------- clean up */

if ($keep) {
    echo "  kept product #$productId and its orders (--keep)\n";
} else {
    $ids = $conn->prepare('SELECT DISTINCT order_id FROM order_items WHERE product_id = ?');
    $ids->execute([$productId]);
    $del = $conn->prepare('DELETE FROM orders WHERE id = ?');   // order_items and order_events cascade
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $orderId) {
        $del->execute([(int) $orderId]);
    }
    $conn->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);   // the ledger rows cascade
}

echo $failed === 0 ? "PASS\n" : "FAIL ($failed check" . ($failed === 1 ? '' : 's') . ")\n";
exit($failed === 0 ? 0 : 1);
