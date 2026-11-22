<?php
// cart/update_cart.php
// Called via AJAX when user changes qty in cart (logged-in users)

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$body   = json_decode(file_get_contents('php://input'), true);
$cartId = (int)($body['cart_id']  ?? 0);
$qty    = (int)($body['quantity'] ?? 1);
$userId = $_SESSION['user_id'];
$qty    = max(1, min(99, $qty));

try {
    $conn->prepare("
        UPDATE cart SET quantity = ?
        WHERE id = ? AND user_id = ?
    ")->execute([$qty, $cartId, $userId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}