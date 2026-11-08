<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/discounts.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

$promo_code = v_str($_POST['promo_code'] ?? '', 50);
if ($promo_code === null || $promo_code === '') {
    json_error('Please enter a promo code', 422);
}
$promo_code = strtoupper($promo_code);

// Brute-force guard: max 10 failed codes per session per 10 minutes
$now = time();
$fails = array_filter($_SESSION['promo_failures'] ?? [], static fn($t) => is_int($t) && $t > $now - 600);
if (count($fails) >= 10) {
    json_error('Too many invalid promo codes. Please try again later.', 429);
}

try {
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $lines = [];
    $cart_total = 0.0;
    foreach ($cart as $item) {
        if (isset($item['id'], $item['price'], $item['quantity'])) {
            $lines[] = ['product_id' => (int) $item['id'], 'price' => (float) $item['price'], 'quantity' => (int) $item['quantity']];
            $cart_total += (float) $item['price'] * (int) $item['quantity'];
        }
    }
    $city = '';
    if (isset($_SESSION['customer_id'])) {
        $st = $conn->prepare('SELECT city FROM customers WHERE id = ?');
        $st->execute([(int) $_SESSION['customer_id']]);
        $city = (string) ($st->fetchColumn() ?: '');
    }

    $result = evaluate_promo($conn, $promo_code, $lines, $cart_total, $city);

    if (!$result['ok']) {
        if ($result['promo'] === null) {
            // unknown / expired code: counts towards the brute-force guard
            $fails[] = $now;
            $_SESSION['promo_failures'] = array_values($fails);
            json_error($result['message'], 404);
        }
        // a real code that this cart doesn't qualify for (minimum, category): say why
        json_error($result['message'], 422);
    }

    $promotion = $result['promo'];
    $_SESSION['applied_promo_code'] = $result['code'];
    $_SESSION['applied_promo_data'] = $promotion;   // re-validated by the cart and at checkout

    json_response([
        'success' => true,
        'message' => $result['message'],
        'discount' => $result['discount'],
        'kind' => $result['kind'],
        'free_shipping' => $result['free_shipping'],
        'discount_percentage' => $promotion['discount_percentage'],
        'discount_amount' => $promotion['discount_amount'],
        'promo_title' => $promotion['title'],
    ]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Error validating promo code. Please try again.'), 500);
}
