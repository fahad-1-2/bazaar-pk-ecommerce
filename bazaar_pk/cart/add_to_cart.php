<?php
// cart/add_to_cart.php
// Handles AJAX requests to add products to cart
// Called from shop.php, product.php
// Returns JSON response

session_start();
require_once '../db.php';

// This file only accepts POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Always respond with JSON
header('Content-Type: application/json');

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = $_SESSION['user_id'] ?? null;

// Read JSON body sent from JS fetch()
$body      = file_get_contents('php://input');
$data      = json_decode($body, true);

// Get values — sanitize and validate
$productId = (int)($data['product_id'] ?? 0);
$quantity  = (int)($data['quantity']   ?? 1);
$variant   = trim($data['variant']     ?? '');

// Validate product ID
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Validate quantity
$quantity = max(1, min(99, $quantity));

// ── Check product exists and has stock ──
try {
    $pStmt = $conn->prepare("
        SELECT id, title, price, stock, is_active
        FROM products
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

if ($product['stock'] < $quantity) {
    echo json_encode([
        'success' => false,
        'message' => 'Not enough stock. Only ' . $product['stock'] . ' left.'
    ]);
    exit();
}

// ── Add to cart ──
if ($isLoggedIn) {
    // Logged-in user: save to DB cart table
    try {
        // Check if this product + variant combo already in cart
        $checkStmt = $conn->prepare("
            SELECT id, quantity FROM cart
            WHERE user_id = ? AND product_id = ? AND variant = ?
            LIMIT 1
        ");
        $checkStmt->execute([$userId, $productId, $variant]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Already in cart — update quantity
            $newQty = min($existing['quantity'] + $quantity, $product['stock']);
            $conn->prepare("
                UPDATE cart SET quantity = ?
                WHERE id = ?
            ")->execute([$newQty, $existing['id']]);
        } else {
            // New item — insert into cart
            $conn->prepare("
                INSERT INTO cart (user_id, product_id, quantity, variant)
                VALUES (?, ?, ?, ?)
            ")->execute([$userId, $productId, $quantity, $variant]);
        }

        // Get updated cart count for badge
        $countStmt = $conn->prepare("
            SELECT SUM(quantity) FROM cart WHERE user_id = ?
        ");
        $countStmt->execute([$userId]);
        $cartCount = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success'    => true,
            'message'    => $product['title'] . ' added to cart!',
            'cart_count' => $cartCount,
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Could not add to cart.']);
    }

} else {
    // Guest user: save to session cart
    // Session cart structure:
    // $_SESSION['cart'][$productId] = ['qty' => 2, 'variant' => 'Red/XL']

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = $productId; // use product ID as key

    if (isset($_SESSION['cart'][$key])) {
        // Already in session cart — add to qty
        $newQty = min($_SESSION['cart'][$key]['qty'] + $quantity, $product['stock']);
        $_SESSION['cart'][$key]['qty'] = $newQty;
    } else {
        // New item in session cart
        $_SESSION['cart'][$key] = [
            'qty'     => $quantity,
            'variant' => $variant,
        ];
    }

    $cartCount = count($_SESSION['cart']);

    echo json_encode([
        'success'    => true,
        'message'    => $product['title'] . ' added to cart!',
        'cart_count' => $cartCount,
    ]);
}