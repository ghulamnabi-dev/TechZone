<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/inventory.php';

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
$size = isset($_POST['size']) ? trim($_POST['size']) : null;
$color = isset($_POST['color']) ? trim($_POST['color']) : null;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Get product details from database (only active products if is_active column exists)
$query = "SELECT id, name, price, imagelink FROM products WHERE id = ?";
if (hasIsActiveColumn($conn)) {
    $query .= " AND is_active = 1";
}
$stmt = $conn->prepare($query);
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found or inactive']);
    exit();
}

// Compute available inventory (base + variations)
$availableInventory = getAvailableInventory($conn, $product_id, $size, $color);

// Check inventory availability (null = not found or inactive, 0 = out of stock)
if ($availableInventory === null || $availableInventory <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product is out of stock']);
    exit();
}

// Calculate final price with variations
$basePrice = (float)$product['price'];
$finalPrice = calculateFinalPriceWithVariations($conn, $product_id, $basePrice, $size, $color);

// Create unique cart key based on product ID and variations
$cartKey = $product_id;
if ($size) {
    $cartKey .= '_size_' . $size;
}
if ($color) {
    $cartKey .= '_color_' . $color;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if this exact variation is already in cart
$currentQuantity = 0;
if (isset($_SESSION['cart'][$cartKey])) {
    $currentQuantity = (int)$_SESSION['cart'][$cartKey]['quantity'];
}

$newQuantity = $currentQuantity + $quantity;

// Validate inventory - check if adding this quantity exceeds available inventory
if ($newQuantity > $availableInventory) {
    $available = $availableInventory - $currentQuantity;
    if ($available <= 0) {
        echo json_encode(['success' => false, 'message' => 'Maximum available quantity already in cart']);
    } else {
        echo json_encode(['success' => false, 'message' => "Only {$available} more available in stock"]);
    }
    exit();
}

// Update cart
if (isset($_SESSION['cart'][$cartKey])) {
    $_SESSION['cart'][$cartKey]['quantity'] = $newQuantity;
} else {
    $_SESSION['cart'][$cartKey] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $finalPrice,
        'imagelink' => $product['imagelink'],
        'quantity' => $quantity,
        'size' => $size,
        'color' => $color,
        'inventory' => $availableInventory // Store inventory for later validation (variation-specific)
    ];
}

// Count unique products (not total quantity)
$cart_count = count($_SESSION['cart']);

echo json_encode([
    'success' => true, 
    'message' => 'Product added to cart', 
    'cart_count' => $cart_count
]);
?>
