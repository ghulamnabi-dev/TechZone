<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/inventory.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request', 405);
}
enforce_csrf_json();

// NOTE: messages returned here are inserted with innerHTML by the storefront JS,
// so they must never contain user-supplied text.
$product_id = v_id($_POST['product_id'] ?? 0);
$quantity = v_int($_POST['quantity'] ?? 1, 1, 100);
$size = v_str($_POST['size'] ?? '', 100);
$color = v_str($_POST['color'] ?? '', 100);

if ($product_id === 0) {
    json_error('Invalid product', 422);
}
if ($quantity === null) {
    json_error('Quantity must be between 1 and 100', 422);
}
if ($size === null || $color === null) {
    json_error('Invalid product option', 422);
}
$size = $size === '' ? null : $size;
$color = $color === '' ? null : $color;

$query = "SELECT id, name, price, imagelink FROM products WHERE id = ?";
if (hasIsActiveColumn($conn)) {
    $query .= " AND is_active = 1";
}
$stmt = $conn->prepare($query);
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    json_error('Product not found or inactive', 404);
}

// Available inventory (base product and selected variations)
$availableInventory = getAvailableInventory($conn, $product_id, $size, $color);
if ($availableInventory === null || $availableInventory <= 0) {
    json_error('Product is out of stock', 409);
}

$finalPrice = calculateFinalPriceWithVariations($conn, $product_id, (float) $product['price'], $size, $color);

// Unique cart key per product + variation
$cartKey = (string) $product_id;
if ($size !== null) {
    $cartKey .= '_size_' . $size;
}
if ($color !== null) {
    $cartKey .= '_color_' . $color;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (count($_SESSION['cart']) >= 50 && !isset($_SESSION['cart'][$cartKey])) {
    json_error('Your cart is full', 409);
}

$currentQuantity = isset($_SESSION['cart'][$cartKey]) ? (int) $_SESSION['cart'][$cartKey]['quantity'] : 0;
$newQuantity = $currentQuantity + $quantity;

if ($newQuantity > $availableInventory) {
    $available = $availableInventory - $currentQuantity;
    json_error($available <= 0 ? 'Maximum available quantity already in cart' : 'Only ' . (int) $available . ' more available in stock', 409);
}

if (isset($_SESSION['cart'][$cartKey])) {
    $_SESSION['cart'][$cartKey]['quantity'] = $newQuantity;
} else {
    $_SESSION['cart'][$cartKey] = [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'price' => $finalPrice,
        'imagelink' => $product['imagelink'],
        'quantity' => $quantity,
        'size' => $size,
        'color' => $color,
        'inventory' => $availableInventory, // variation-specific, re-checked at checkout
    ];
}

json_response([
    'success' => true,
    'message' => 'Product added to cart',
    'cart_count' => count($_SESSION['cart']), // unique lines, not total quantity
]);
