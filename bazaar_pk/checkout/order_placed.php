<?php
// checkout/order_placed.php
// Success page shown after order is successfully placed

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); exit();
}

$userId  = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header("Location: ../shop/shop.php"); exit();
}

// ── Fetch order details ──
try {
    $oStmt = $conn->prepare("
        SELECT o.*, u.full_name, u.email
        FROM orders o JOIN users u ON o.customer_id=u.id
        WHERE o.id=? AND o.customer_id=? LIMIT 1
    ");
    $oStmt->execute([$orderId, $userId]);
    $order = $oStmt->fetch();
} catch (PDOException $e) { $order = null; }

if (!$order) {
    header("Location: ../shop/shop.php"); exit();
}

// ── Fetch order items ──
try {
    $iStmt = $conn->prepare("
        SELECT oi.*, p.title, p.image, v.store_name
        FROM order_items oi
        JOIN products p ON oi.product_id=p.id
        JOIN vendors  v ON oi.vendor_id=v.id
        WHERE oi.order_id=?
    ");
    $iStmt->execute([$orderId]);
    $orderItems = $iStmt->fetchAll();
} catch (PDOException $e) { $orderItems = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"         rel="stylesheet">
  <link href="../css/styleshop.css"     rel="stylesheet">
  <link href="../css/stylecheckout.css" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
<nav class="shop-nav">
  <a href="../shop/shop.php" class="shop-logo">Bazaar<span>.pk</span></a>
  <div style="flex:1;"></div>
  <div class="nav-right">
    <a href="../logout.php" class="nav-logout-ghost">Logout</a>
  </div>
</nav>

<!-- STEPS -->
<div class="checkout-steps">
  <div class="step">
    <div class="step-circle done">✓</div>
    <div class="step-label done">Cart</div>
  </div>
  <div class="step-line done"></div>
  <div class="step">
    <div class="step-circle done">✓</div>
    <div class="step-label done">Checkout</div>
  </div>
  <div class="step-line done"></div>
  <div class="step">
    <div class="step-circle active">3</div>
    <div class="step-label active">Confirmed</div>
  </div>
</div>

<!-- SUCCESS CONTENT -->
<div class="success-wrap">

  <!-- Animated checkmark -->
  <div class="success-icon" id="successIcon">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  </div>

  <div class="success-title">Order placed successfully!</div>
  <div class="success-sub">
    Thank you, <strong><?= htmlspecialchars($order['full_name']) ?></strong>!<br>
    Your order <strong>#<?= str_pad($orderId, 5, '0', STR_PAD_LEFT) ?></strong> has been received.
    <?php if ($order['payment_method'] === 'cod'): ?>
      Our delivery team will call you before delivering.
    <?php else: ?>
      Payment confirmation will be sent to your email.
    <?php endif; ?>
  </div>

  <!-- Order info card -->
  <div class="order-info-card">
    <div class="oic-row">
      <span class="oic-label">Order number</span>
      <span class="oic-value" style="color:var(--teal);font-weight:700;">#<?= str_pad($orderId,5,'0',STR_PAD_LEFT) ?></span>
    </div>
    <div class="oic-row">
      <span class="oic-label">Order total</span>
      <span class="oic-value" style="color:var(--teal);">Rs <?= number_format($order['total']) ?></span>
    </div>
    <div class="oic-row">
      <span class="oic-label">Payment method</span>
      <span class="oic-value"><?= ucfirst(str_replace('_',' ',$order['payment_method'])) ?></span>
    </div>
    <div class="oic-row">
      <span class="oic-label">Status</span>
      <span class="oic-value">
        <span style="background:rgba(245,158,11,0.12);color:var(--amber);font-size:11px;font-weight:600;padding:3px 8px;border-radius:4px;">
          Pending
        </span>
      </span>
    </div>
    <div class="oic-row">
      <span class="oic-label">Delivery address</span>
      <span class="oic-value" style="font-size:11px;"><?= htmlspecialchars($order['address']) ?></span>
    </div>
    <div class="oic-row">
      <span class="oic-label">Estimated delivery</span>
      <span class="oic-value"><?= date('d M Y', strtotime('+3 days')) ?> – <?= date('d M Y', strtotime('+5 days')) ?></span>
    </div>
  </div>

  <!-- Items ordered -->
  <?php if (!empty($orderItems)): ?>
    <div class="order-info-card" style="text-align:left;margin-bottom:20px;">
      <div style="font-size:13px;font-weight:600;color:var(--txt);margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border);">
        Items ordered
      </div>
      <?php foreach ($orderItems as $item): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">
          <div style="width:40px;height:40px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
            <?php if (!empty($item['image'])): ?>
              <img src="../uploads/products/<?= htmlspecialchars($item['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              &#128722;
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <div style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($item['title']) ?></div>
            <div style="font-size:10px;color:var(--teal);"><?= htmlspecialchars($item['store_name']) ?></div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:12px;font-weight:600;color:var(--teal);">Rs <?= number_format($item['price_at_purchase']*$item['quantity']) ?></div>
            <div style="font-size:10px;color:var(--txt3);">× <?= $item['quantity'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- COD note -->
  <?php if ($order['payment_method'] === 'cod'): ?>
    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:var(--amber);text-align:left;">
      &#128181; <strong>Cash on Delivery:</strong> Please keep Rs <?= number_format($order['total']) ?> ready at the time of delivery. Our rider will collect payment.
    </div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="success-actions">
    <a href="../shop/shop.php" class="sa-btn-primary">Continue shopping</a>
    <a href="../customer/orders.php" class="sa-btn-ghost">My orders</a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animate the success icon on load
window.addEventListener('load', () => {
    const icon = document.getElementById('successIcon');
    icon.style.transform = 'scale(0)';
    icon.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
    setTimeout(() => { icon.style.transform = 'scale(1)'; }, 100);
});
</script>
</body>
</html>