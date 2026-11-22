<?php
// checkout/checkout.php
// Customer fills delivery address, selects payment, places order

session_start();
require_once '../db.php';

// Must be logged in to checkout
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=checkout"); exit();
}
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php"); exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

// ── Fetch cart items ──
try {
    $cartStmt = $conn->prepare("
        SELECT c.id AS cart_id, c.quantity, c.variant,
               p.id AS product_id, p.title, p.price, p.image, p.stock,
               v.id AS vendor_id, v.store_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN vendors  v ON p.vendor_id  = v.id
        WHERE c.user_id = ? AND p.is_active = 1
        ORDER BY v.store_name, c.id
    ");
    $cartStmt->execute([$userId]);
    $cartItems = $cartStmt->fetchAll();
} catch (PDOException $e) { $cartItems = []; }

// Redirect if cart is empty
if (empty($cartItems)) {
    header("Location: ../cart/cart.php"); exit();
}

// ── Calculate totals ──
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$deliveryFee = $subtotal >= 2000 ? 0 : 150;
$discount    = 0;
$couponCode  = $_SESSION['coupon'] ?? null;
if ($couponCode === 'BAZAAR10') {
    $discount = round($subtotal * 0.10);
}
$grandTotal = $subtotal + $deliveryFee - $discount;

$error   = '';
$success = '';

// ── Handle order placement ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName      = trim($_POST['full_name']     ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $address       = trim($_POST['address']       ?? '');
    $city          = trim($_POST['city']          ?? '');
    $province      = trim($_POST['province']      ?? '');
    $paymentMethod = trim($_POST['payment_method']?? 'cod');

    // Validate
    if (empty($fullName) || empty($phone) || empty($address) || empty($city)) {
        $error = "Please fill in all required delivery fields.";
    } elseif (!preg_match('/^03[0-9]{9}$/', $phone)) {
        $error = "Please enter a valid Pakistani phone number (03XXXXXXXXX).";
    } else {
        try {
            // Begin transaction — either everything saves or nothing does
            $conn->beginTransaction();

            $fullAddress = $address . ', ' . $city . ', ' . $province;

            // Insert order
            $orderStmt = $conn->prepare("
                INSERT INTO orders
                    (customer_id, total, status, address, payment_method, created_at)
                VALUES (?, ?, 'pending', ?, ?, NOW())
            ");
            $orderStmt->execute([$userId, $grandTotal, $fullAddress, $paymentMethod]);
            $orderId = $conn->lastInsertId();

            // Insert order items + reduce stock
            foreach ($cartItems as $item) {
                // Check stock one more time before inserting
                $stockCheck = $conn->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE");
                $stockCheck->execute([$item['product_id']]);
                $currentStock = (int)$stockCheck->fetchColumn();

                if ($currentStock < $item['quantity']) {
                    $conn->rollBack();
                    $error = "Sorry, \"" . htmlspecialchars($item['title']) . "\" is now out of stock.";
                    break;
                }

                // Insert order item
                $conn->prepare("
                    INSERT INTO order_items
                        (order_id, product_id, vendor_id, quantity, price_at_purchase)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $orderId,
                    $item['product_id'],
                    $item['vendor_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                // Reduce stock and increase sales count
                $conn->prepare("
                    UPDATE products
                    SET stock       = stock - ?,
                        sales_count = sales_count + ?
                    WHERE id = ?
                ")->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }

            if (empty($error)) {
                // Clear cart
                $conn->prepare("DELETE FROM cart WHERE user_id=?")->execute([$userId]);
                // Clear coupon
                unset($_SESSION['coupon']);

                $conn->commit();

                // Redirect to success page
                header("Location: order_placed.php?id=$orderId");
                exit();
            }

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "An error occurred while placing your order. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout — Bazaar.pk</title>
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
    <div class="nav-deliver">
      <span class="flag">&#127477;&#127472;</span>
      <div class="deliver-lbl">
        <span class="deliver-top">Secure</span>
        <span class="deliver-city">Checkout</span>
      </div>
    </div>
    <a href="../logout.php" class="nav-logout-ghost">Logout</a>
  </div>
</nav>

<!-- BREADCRUMB -->
<div style="display:flex;align-items:center;gap:6px;padding:9px 20px;background:var(--bg2);border-bottom:1px solid var(--border);font-size:11px;">
  <a href="../shop/shop.php" style="color:var(--txt2);text-decoration:none;">Home</a>
  <span style="color:var(--txt3);">›</span>
  <a href="../cart/cart.php" style="color:var(--txt2);text-decoration:none;">Cart</a>
  <span style="color:var(--txt3);">›</span>
  <span style="color:var(--txt);">Checkout</span>
</div>

<!-- STEPS -->
<div class="checkout-steps">
  <div class="step">
    <div class="step-circle done">✓</div>
    <div class="step-label done">Cart</div>
  </div>
  <div class="step-line done"></div>
  <div class="step">
    <div class="step-circle active">2</div>
    <div class="step-label active">Checkout</div>
  </div>
  <div class="step-line"></div>
  <div class="step">
    <div class="step-circle">3</div>
    <div class="step-label">Confirmed</div>
  </div>
</div>

<!-- MAIN -->
<form method="POST" id="checkoutForm">
<div class="checkout-wrap">

  <!-- LEFT: Forms -->
  <div>

    <?php if ($error): ?>
      <div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.25);border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:12px;color:var(--red);">
        &#10007; <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Delivery address -->
    <div class="co-section">
      <div class="co-section-title">
        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Delivery address
      </div>

      <div class="co-row">
        <div class="co-form-group">
          <label class="co-label">Full name <span style="color:var(--red);">*</span></label>
          <input class="co-input" type="text" name="full_name"
                 value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>"
                 placeholder="Enter your full name" required>
        </div>
        <div class="co-form-group">
          <label class="co-label">Phone number <span style="color:var(--red);">*</span></label>
          <input class="co-input" type="text" name="phone"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 placeholder="03XX XXXXXXX" required maxlength="11">
        </div>
      </div>

      <div class="co-form-group">
        <label class="co-label">Street address <span style="color:var(--red);">*</span></label>
        <input class="co-input" type="text" name="address"
               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
               placeholder="House no, street, area" required>
      </div>

      <div class="co-row">
        <div class="co-form-group">
          <label class="co-label">City <span style="color:var(--red);">*</span></label>
          <select class="co-select" name="city" required>
            <option value="">Select city</option>
            <?php
            $cities = ['Lahore','Karachi','Islamabad','Rawalpindi','Faisalabad',
                       'Multan','Peshawar','Quetta','Sialkot','Gujranwala',
                       'Hyderabad','Abbottabad','Bahawalpur','Sargodha'];
            foreach ($cities as $c) {
                $sel = (($_POST['city'] ?? '') === $c) ? 'selected' : '';
                echo "<option value='$c' $sel>$c</option>";
            }
            ?>
          </select>
        </div>
        <div class="co-form-group">
          <label class="co-label">Province</label>
          <select class="co-select" name="province">
            <option value="">Select province</option>
            <?php
            $provinces = ['Punjab','Sindh','KPK','Balochistan','Gilgit-Baltistan','AJK'];
            foreach ($provinces as $p) {
                $sel = (($_POST['province'] ?? '') === $p) ? 'selected' : '';
                echo "<option value='$p' $sel>$p</option>";
            }
            ?>
          </select>
        </div>
      </div>

      <div class="co-form-group">
        <label class="co-label">Order notes <span style="font-size:10px;color:var(--txt3);">(optional)</span></label>
        <textarea class="co-textarea" name="notes"
                  placeholder="Any special delivery instructions..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
      </div>
    </div><!-- /delivery -->


    <!-- Payment method -->
    <div class="co-section">
      <div class="co-section-title">
        <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Payment method
      </div>

      <div class="payment-options">

        <div class="pay-option selected" id="pay_cod" onclick="selectPayment('cod')">
          <div class="pay-radio"></div>
          <div class="pay-icon-box">&#128181;</div>
          <div class="pay-info">
            <div class="pay-name">Cash on delivery (COD)</div>
            <div class="pay-desc">Pay when your order arrives at your door</div>
          </div>
        </div>

        <div class="pay-option" id="pay_easypaisa" onclick="selectPayment('easypaisa')">
          <div class="pay-radio"></div>
          <div class="pay-icon-box">&#128241;</div>
          <div class="pay-info">
            <div class="pay-name">EasyPaisa</div>
            <div class="pay-desc">Pay via EasyPaisa mobile wallet</div>
          </div>
        </div>

        <div class="pay-option" id="pay_jazzcash" onclick="selectPayment('jazzcash')">
          <div class="pay-radio"></div>
          <div class="pay-icon-box">&#128178;</div>
          <div class="pay-info">
            <div class="pay-name">JazzCash</div>
            <div class="pay-desc">Pay via JazzCash mobile wallet</div>
          </div>
        </div>

        <div class="pay-option" id="pay_bank" onclick="selectPayment('bank_transfer')">
          <div class="pay-radio"></div>
          <div class="pay-icon-box">&#127981;</div>
          <div class="pay-info">
            <div class="pay-name">Bank transfer</div>
            <div class="pay-desc">Direct bank transfer — details sent after order</div>
          </div>
        </div>

      </div>
      <input type="hidden" name="payment_method" id="paymentInput" value="cod">
    </div><!-- /payment -->

  </div><!-- /left -->


  <!-- RIGHT: Order summary -->
  <div>
    <div class="co-summary">
      <div class="cos-title">Order summary (<?= count($cartItems) ?> items)</div>

      <!-- Cart items -->
      <?php foreach ($cartItems as $item): ?>
        <div class="cos-item">
          <div class="cos-img">
            <?php if (!empty($item['image'])): ?>
              <img src="../uploads/products/<?= htmlspecialchars($item['image']) ?>" alt="">
            <?php else: ?>
              &#128722;
            <?php endif; ?>
          </div>
          <div class="cos-name">
            <?= htmlspecialchars(substr($item['title'],0,35)) ?>
            <?= strlen($item['title'])>35 ? '...' : '' ?>
            <?php if (!empty($item['variant'])): ?>
              <div class="cos-qty"><?= htmlspecialchars($item['variant']) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;">
            <div class="cos-price">Rs <?= number_format($item['price'] * $item['quantity']) ?></div>
            <div class="cos-qty">× <?= $item['quantity'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="cos-divider"></div>

      <div class="cos-row">
        <span class="cos-row-label">Subtotal</span>
        <span class="cos-row-value">Rs <?= number_format($subtotal) ?></span>
      </div>
      <div class="cos-row">
        <span class="cos-row-label">Delivery fee</span>
        <span class="cos-row-value green"><?= $deliveryFee === 0 ? 'Free' : 'Rs ' . number_format($deliveryFee) ?></span>
      </div>
      <?php if ($discount > 0): ?>
        <div class="cos-row">
          <span class="cos-row-label">Coupon (<?= $couponCode ?>)</span>
          <span class="cos-row-value green">- Rs <?= number_format($discount) ?></span>
        </div>
      <?php endif; ?>

      <div class="cos-divider"></div>

      <div class="cos-total-row">
        <span class="cos-total-label">Total</span>
        <span class="cos-total-value">Rs <?= number_format($grandTotal) ?></span>
      </div>

      <button type="submit" class="place-order-btn" id="placeOrderBtn">
        Place order — Rs <?= number_format($grandTotal) ?>
      </button>

      <div class="secure-checkout">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Secure & encrypted checkout
      </div>

      <div style="margin-top:12px;padding:10px 12px;background:var(--bg3);border-radius:7px;font-size:11px;color:var(--txt2);line-height:1.6;">
        &#128230; Estimated delivery: 2-3 business days<br>
        &#8635; Free 7-day returns<br>
        &#9888; Stock reserved for 10 minutes
      </div>
    </div>
  </div>

</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectPayment(method) {
    document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('selected'));
    document.getElementById('pay_' + method).classList.add('selected');
    document.getElementById('paymentInput').value = method;
}

// Phone number formatting
document.querySelector('[name="phone"]').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});

// Prevent double submit
document.getElementById('checkoutForm').addEventListener('submit', function() {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.textContent = 'Placing order...';
    setTimeout(() => {
        btn.disabled = false;
        btn.textContent = 'Place order — Rs <?= number_format($grandTotal) ?>';
    }, 8000);
});
</script>
</body>
</html>