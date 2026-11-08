<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

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
    $stmt = $conn->prepare("
        SELECT id, title, discount_percentage, discount_amount, promo_code, start_date, end_date
        FROM promotions
        WHERE UPPER(promo_code) = ?
          AND is_active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date >= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$promo_code]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        $fails[] = $now;
        $_SESSION['promo_failures'] = array_values($fails);
        json_error('Invalid or expired promo code', 404);
    }

    $cart_total = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            if (isset($item['price'], $item['quantity'])) {
                $cart_total += (float) $item['price'] * (int) $item['quantity'];
            }
        }
    }

    $_SESSION['applied_promo_code'] = $promo_code;
    $_SESSION['applied_promo_data'] = $promotion;   // re-validated at checkout

    $discount = 0;
    if ($promotion['discount_percentage'] > 0) {
        $discount = ($cart_total * $promotion['discount_percentage']) / 100;
    } elseif ($promotion['discount_amount'] > 0) {
        $discount = min((float) $promotion['discount_amount'], $cart_total);
    }

    json_response([
        'success' => true,
        'message' => 'Promo code applied successfully!',
        'discount' => $discount,
        'discount_percentage' => $promotion['discount_percentage'],
        'discount_amount' => $promotion['discount_amount'],
        'promo_title' => $promotion['title'],
    ]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Error validating promo code. Please try again.'), 500);
}
