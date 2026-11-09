<?php
/**
 * PC builder API (core/builder.php).
 *
 * GET  ?b=cpu:46,mb:8,…[&slot=ram][&rejected=1][&candidates=0]
 *      → {success, build, slots:[{key,label,optional,selected,candidates:[…], rejected?:[…]}], evaluation}
 *      Candidates are listed for every slot, or only for ?slot= when given; candidates=0 lists none
 *      (evaluation only). rejected=1 adds the parts that don't fit, each with `reasons`.
 * POST action=add_to_cart, b=cpu:46,…   (CSRF; throttled)
 *      Adds every part of a build with no blocking issue to the session cart in one request.
 *      → {success, message, cart_count, added:[ids]}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/inventory.php';
require_once __DIR__ . '/../../core/builder.php';
require_once __DIR__ . '/../../core/throttle.php';

json_headers();

$method = $_SERVER['REQUEST_METHOD'];

/** Compact part for JSON (attrs included so the page can explain compatibility). */
$partJson = static fn(array $p): array => product_json($p, ['attrs' => $p['attrs'] ?? [], 'warnings' => $p['warnings'] ?? []]);

/** Issue/evaluation for JSON (parts reduced to their JSON shape). */
$evaluationJson = static function (array $ev) use ($partJson): array {
    $ev['parts'] = array_map($partJson, $ev['parts']);
    $ev['total_text'] = format_pkr($ev['total']);
    return $ev;
};

try {
    if ($method === 'GET') {
        $build = builder_parse(is_string($_GET['b'] ?? null) ? $_GET['b'] : '');
        $only = is_string($_GET['slot'] ?? null) ? $_GET['slot'] : '';
        $withCandidates = ($_GET['candidates'] ?? '1') !== '0';
        $withRejected = !empty($_GET['rejected']);
        $slots = builder_slots();
        if ($only !== '' && !isset($slots[$only])) {
            json_error('Unknown slot', 422);
        }
        $ev = builder_evaluate($conn, $build);
        $out = [];
        foreach ($slots as $key => $def) {
            $row = ['key' => $key, 'label' => $def['label'], 'optional' => $def['optional'],
                    'selected' => isset($ev['parts'][$key]) ? $partJson($ev['parts'][$key]) : null];
            if ($withCandidates && ($only === '' || $only === $key)) {
                $row['candidates'] = array_map($partJson, builder_candidates($conn, $build, $key));
                if ($withRejected) {
                    $row['rejected'] = array_map(static fn($p) => $partJson($p) + ['reasons' => $p['reasons']], builder_rejected($conn, $build, $key));
                }
            }
            $out[] = $row;
        }
        json_response(['success' => true, 'build' => $ev['build'], 'slots' => $out, 'evaluation' => $evaluationJson($ev)]);
    }

    if ($method !== 'POST') {
        json_error('Invalid request method', 405);
    }
    enforce_csrf_json();
    $in = $_POST ?: request_json();
    if (($in['action'] ?? '') !== 'add_to_cart') {
        json_error('Unknown action', 422);
    }
    if (!throttle_allow($conn, 'builder_cart', 20, 600)) {
        json_error('Too many requests. Please wait a few minutes and try again.', 429);
    }
    $build = builder_parse(is_string($in['b'] ?? null) ? $in['b'] : '');
    if (!$build) {
        json_error('Pick at least one part first.', 422);
    }
    $ev = builder_evaluate($conn, $build);
    if ($ev['invalid']) {
        json_error('Some parts in this build are no longer available. Please pick them again.', 409);
    }
    if (!$ev['ok']) {
        json_error('This build has a compatibility problem. Fix the parts marked in red first.', 409);
    }
    if ($ev['out_of_stock']) {
        json_error('Some parts are out of stock: ' . implode(', ', array_map(static fn($s) => builder_slots()[$s]['label'], $ev['out_of_stock'])) . '.', 409);
    }

    // same cart line shape as api/add_to_cart.php (one unit of each part, no options)
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $lines = [];
    foreach ($ev['parts'] as $p) {
        $id = (int) $p['id'];
        $key = (string) $id;
        $available = getAvailableInventory($conn, $id);
        $current = isset($_SESSION['cart'][$key]) ? (int) $_SESSION['cart'][$key]['quantity'] : 0;
        if ($available === null || $current + 1 > $available) {
            json_error('Not enough stock for ' . $p['name'] . ' (you may already have it in your cart).', 409);
        }
        $lines[$key] = ['id' => $id, 'name' => $p['name'], 'price' => (float) $p['price'], 'imagelink' => $p['imagelink'],
                        'quantity' => $current + 1, 'size' => null, 'color' => null, 'inventory' => $available];
    }
    if (count(array_diff_key($lines, $_SESSION['cart'])) + count($_SESSION['cart']) > 50) {
        json_error('Your cart is full', 409);
    }
    foreach ($lines as $key => $line) {
        $_SESSION['cart'][$key] = $line;   // all or nothing: written only after every check passed
    }
    json_response(['success' => true, 'message' => count($lines) . ' parts added to your cart',
                   'cart_count' => count($_SESSION['cart']), 'added' => array_map('intval', array_keys($lines))]);
} catch (PDOException $e) {
    log_exception($e, 'builder');
    json_error('Something went wrong. Please try again.', 500);
}
