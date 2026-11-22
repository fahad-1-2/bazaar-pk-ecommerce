<?php
// cart/cart.php
// Shows the user's cart — guests use session cart, logged-in users use DB cart

session_start();
require_once '../db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['role']      ?? null;
$userName   = $_SESSION['full_name'] ?? null;
$userId     = $_SESSION['user_id']   ?? null;

// ── Handle POST actions (update qty, remove item) ──
// These come from form submissions or AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $cartId    = (int)($_POST['cart_id']    ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = (int)($_POST['quantity']   ?? 1);

    if ($action === 'update_qty' && $isLoggedIn && $cartId > 0) {
        // Update quantity in DB cart
        // Minimum 1, maximum 99
        $quantity = max(1, min(99, $quantity));
        try {
            $conn->prepare("
                UPDATE cart SET quantity = ?
                WHERE id = ? AND user_id = ?
            ")->execute([$quantity, $cartId, $userId]);
        } catch (PDOException $e) {}

    } elseif ($action === 'remove' && $isLoggedIn && $cartId > 0) {
        // Remove item from DB cart
        try {
            $conn->prepare("
                DELETE FROM cart WHERE id = ? AND user_id = ?
            ")->execute([$cartId, $userId]);
        } catch (PDOException $e) {}

    } elseif ($action === 'clear' && $isLoggedIn) {
        // Remove ALL items from DB cart
        try {
            $conn->prepare("
                DELETE FROM cart WHERE user_id = ?
            ")->execute([$userId]);
        } catch (PDOException $e) {}

    } elseif ($action === 'remove_guest' && !$isLoggedIn) {
        // Remove from session cart (guest)
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }

    } elseif ($action === 'clear_guest' && !$isLoggedIn) {
        // Clear whole session cart
        $_SESSION['cart'] = [];
    }

    // Redirect back to cart to avoid form resubmission on refresh
    header("Location: cart.php");
    exit();
}

// ── Fetch cart items ──
$cartItems   = [];
$cartByVendor = []; // grouped by vendor for display
$subtotal    = 0;
$totalItems  = 0;

if ($isLoggedIn) {
    // Logged-in users: fetch from DB cart table
    // We join products and vendors to get all info in one query
    try {
        $stmt = $conn->prepare("
            SELECT c.id        AS cart_id,
                   c.quantity,
                   c.variant,
                   p.id        AS product_id,
                   p.title,
                   p.price,
                   p.old_price,
                   p.image,
                   p.stock,
                   v.id        AS vendor_id,
                   v.store_name,
                   v.logo      AS vendor_logo
            FROM cart c
            JOIN products p ON c.product_id = p.id
            JOIN vendors  v ON p.vendor_id  = v.id
            WHERE c.user_id = ?
              AND p.is_active = 1
            ORDER BY v.store_name, c.id
        ");
        $stmt->execute([$userId]);
        $cartItems = $stmt->fetchAll();

    } catch (PDOException $e) {
        // cart table doesn't exist yet — empty array
        $cartItems = [];
    }

} else {
    // Guest users: build from session cart
    // $_SESSION['cart'] = [ product_id => ['qty'=>1, 'variant'=>'Red/XL'] ]
    if (!empty($_SESSION['cart'])) {
        $productIds = array_keys($_SESSION['cart']);

        if (!empty($productIds)) {
            // Build placeholders for IN clause safely
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            try {
                $stmt = $conn->prepare("
                    SELECT p.id        AS product_id,
                           p.title,
                           p.price,
                           p.old_price,
                           p.image,
                           p.stock,
                           v.id        AS vendor_id,
                           v.store_name,
                           v.logo      AS vendor_logo
                    FROM products p
                    JOIN vendors v ON p.vendor_id = v.id
                    WHERE p.id IN ($placeholders)
                      AND p.is_active = 1
                ");
                $stmt->execute($productIds);
                $products = $stmt->fetchAll();

                // Merge with session data (qty, variant)
                foreach ($products as $p) {
                    $sessionItem = $_SESSION['cart'][$p['product_id']];
                    $cartItems[] = array_merge($p, [
                        'cart_id'  => 'guest_' . $p['product_id'],
                        'quantity' => $sessionItem['qty'] ?? 1,
                        'variant'  => $sessionItem['variant'] ?? '',
                    ]);
                }
            } catch (PDOException $e) {
                $cartItems = [];
            }
        }
    }
}

// ── Group items by vendor and calculate totals ──
foreach ($cartItems as $item) {
    $vendorId   = $item['vendor_id'];
    $itemTotal  = $item['price'] * $item['quantity'];
    $subtotal  += $itemTotal;
    $totalItems += $item['quantity'];

    if (!isset($cartByVendor[$vendorId])) {
        $cartByVendor[$vendorId] = [
            'store_name'  => $item['store_name'],
            'vendor_logo' => $item['vendor_logo'],
            'vendor_id'   => $vendorId,
            'items'       => [],
        ];
    }
    $cartByVendor[$vendorId]['items'][] = $item;
}

// ── Calculate delivery fee ──
// Free above Rs 2000, else Rs 150
$deliveryFee = $subtotal >= 2000 ? 0 : 150;
$grandTotal  = $subtotal + $deliveryFee;

// ── Discount from coupon (stored in session) ──
$discount   = 0;
$couponCode = $_SESSION['coupon'] ?? null;
if ($couponCode) {
    // In production you'd check a coupons table in DB
    // For now a hardcoded demo coupon
    if ($couponCode === 'BAZAAR10') {
        $discount   = round($subtotal * 0.10);
        $grandTotal = $grandTotal - $discount;
    }
}

// ── Handle coupon via POST ──
if (isset($_POST['apply_coupon'])) {
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if ($code === 'BAZAAR10') {
        $_SESSION['coupon'] = $code;
        $couponSuccess = "Coupon applied! 10% discount added.";
    } else {
        unset($_SESSION['coupon']);
        $couponError = "Invalid coupon code.";
    }
}

// Cart badge count
$cartCount = $totalItems;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Cart (<?= $totalItems ?>) — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleshop.css"  rel="stylesheet">
  <link href="../css/stylecart.css"  rel="stylesheet">
</head>
<body>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="shop-nav">
  <button class="nav-hamburger">
    <div class="hline"></div>
    <div class="hline"></div>
    <div class="hline"></div>
  </button>

  <a href="../shop/shop.php" class="shop-logo">Bazaar<span>.pk</span></a>

  <form class="nav-search" action="../shop/shop.php" method="GET">
    <div class="search-cat">
      All
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <input class="search-inp" type="text" name="q" placeholder="Search products...">
    <button type="submit" class="search-btn">
      <svg viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
    </button>
  </form>

  <div class="nav-right">
    <a href="cart.php" class="nav-cart">
      <svg viewBox="0 0 24 24">
        <circle cx="9" cy="21" r="1"/>
        <circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/>
      </svg>
      <span class="cart-lbl">Cart</span>
      <div class="cart-count"><?= $cartCount ?></div>
    </a>

    <?php if ($isLoggedIn): ?>
      <a href="../logout.php" class="nav-logout-ghost">Logout</a>
    <?php else: ?>
      <a href="../login.php"  class="nav-login-ghost">Login</a>
    <?php endif; ?>
  </div>
</nav>


<!-- ============================================================
     BREADCRUMB
     ============================================================ -->
<div class="breadcrumb">
  <a href="../shop/shop.php" class="bc-item">Home</a>
  <span class="bc-sep">›</span>
  <span class="bc-item active">My cart</span>
</div>


<!-- ============================================================
     FLASH MESSAGE (if any)
     ============================================================ -->
<?php if (isset($couponSuccess)): ?>
  <div class="flash-msg success"><?= htmlspecialchars($couponSuccess) ?></div>
<?php endif; ?>
<?php if (isset($couponError)): ?>
  <div class="flash-msg error"><?= htmlspecialchars($couponError) ?></div>
<?php endif; ?>


<!-- ============================================================
     MAIN CART LAYOUT
     ============================================================ -->
<div class="cart-wrap">

  <!-- ── LEFT: Cart items ── -->
  <div id="cartLeft">

    <div class="cart-header">
      <div class="cart-title">
        My cart
        <span id="totalItemsLabel">(<?= $totalItems ?> items)</span>
      </div>
      <?php if (!empty($cartItems)): ?>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action"
                 value="<?= $isLoggedIn ? 'clear' : 'clear_guest' ?>">
          <button type="submit" class="cart-clear"
                  onclick="return confirm('Remove all items from cart?')">
            Clear all
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($cartItems)): ?>

      <!-- Select all bar -->
      <div class="select-bar">
        <div class="check-box checked" id="selectAllBox"
             onclick="toggleSelectAll()">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span class="select-lbl">Select all items</span>
        <span class="select-count" id="selectedLabel">
          <?= $totalItems ?> selected
        </span>
      </div>

      <!-- Items grouped by vendor -->
      <?php foreach ($cartByVendor as $vendorId => $group): ?>
        <div class="vendor-group">

          <!-- Vendor header -->
          <div class="vg-header">
            <div class="check-box checked" onclick="toggleVendorGroup(this)">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="vg-logo">
              <?php if (!empty($group['vendor_logo'])): ?>
                <img src="../uploads/vendors/<?= htmlspecialchars($group['vendor_logo']) ?>"
                     alt="">
              <?php else: ?>
                <?= strtoupper(substr($group['store_name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div class="vg-name"><?= htmlspecialchars($group['store_name']) ?></div>
            <span class="vg-count"><?= count($group['items']) ?> item(s)</span>
          </div>

          <!-- Items in this vendor group -->
          <div class="vg-body">
            <?php foreach ($group['items'] as $item):
              $discountPct = 0;
              if (!empty($item['old_price']) && $item['old_price'] > $item['price']) {
                $discountPct = round((($item['old_price'] - $item['price']) / $item['old_price']) * 100);
              }
              $itemTotal = $item['price'] * $item['quantity'];
            ?>
              <div class="cart-item"
                   id="cartItem_<?= $item['cart_id'] ?>"
                   data-price="<?= $item['price'] ?>"
                   data-cart-id="<?= $item['cart_id'] ?>">

                <div class="ci-row">

                  <!-- Checkbox -->
                  <div class="ci-check">
                    <div class="check-box checked"
                         onclick="toggleItemCheck(this, '<?= $item['cart_id'] ?>')">
                      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                  </div>

                  <!-- Product image -->
                  <a href="../product/product.php?id=<?= $item['product_id'] ?>"
                     class="ci-img">
                    <?php if (!empty($item['image'])): ?>
                      <img src="../uploads/products/<?= htmlspecialchars($item['image']) ?>"
                           alt="<?= htmlspecialchars($item['title']) ?>">
                    <?php else: ?>
                      <span class="ci-img-placeholder">No img</span>
                    <?php endif; ?>
                  </a>

                  <!-- Item details -->
                  <div class="ci-body">

                    <div class="ci-vendor">
                      <?= htmlspecialchars($item['store_name']) ?>
                    </div>

                    <a href="../product/product.php?id=<?= $item['product_id'] ?>"
                       class="ci-name" style="text-decoration:none;">
                      <?= htmlspecialchars($item['title']) ?>
                    </a>

                    <?php if (!empty($item['variant'])): ?>
                      <div class="ci-variant">
                        <?= htmlspecialchars($item['variant']) ?>
                      </div>
                    <?php endif; ?>

                    <div class="ci-price-row">
                      <span class="ci-price"
                            id="price_<?= $item['cart_id'] ?>">
                        Rs <?= number_format($itemTotal) ?>
                      </span>
                      <?php if ($discountPct > 0): ?>
                        <span class="ci-old">
                          Rs <?= number_format($item['old_price'] * $item['quantity']) ?>
                        </span>
                        <span class="ci-save">-<?= $discountPct ?>%</span>
                      <?php endif; ?>
                    </div>

                    <div class="ci-actions">

                      <!-- Quantity control -->
                      <div class="qty-ctrl">
                        <button class="qty-b"
                                onclick="updateQty('<?= $item['cart_id'] ?>', <?= $item['price'] ?>, -1, <?= $item['stock'] ?>)">
                          −
                        </button>
                        <div class="qty-v"
                             id="qty_<?= $item['cart_id'] ?>">
                          <?= $item['quantity'] ?>
                        </div>
                        <button class="qty-b"
                                onclick="updateQty('<?= $item['cart_id'] ?>', <?= $item['price'] ?>, 1, <?= $item['stock'] ?>)">
                          +
                        </button>
                      </div>

                      <!-- Save for later -->
                      <button class="ci-btn"
                              onclick="saveForLater('<?= $item['cart_id'] ?>')">
                        <svg viewBox="0 0 24 24">
                          <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                        </svg>
                        Save
                      </button>

                      <!-- Remove button -->
                      <form method="POST" style="display:inline;"
                            id="removeForm_<?= $item['cart_id'] ?>">
                        <input type="hidden" name="action"
                               value="<?= $isLoggedIn ? 'remove' : 'remove_guest' ?>">
                        <input type="hidden" name="cart_id"
                               value="<?= $item['cart_id'] ?>">
                        <input type="hidden" name="product_id"
                               value="<?= $item['product_id'] ?>">
                        <button type="button" class="ci-btn remove"
                                onclick="removeItem('<?= $item['cart_id'] ?>')">
                          <svg viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                          </svg>
                          Remove
                        </button>
                      </form>

                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>

    <!-- Empty cart state -->
    <div class="empty-cart <?= empty($cartItems) ? 'visible' : '' ?>"
         id="emptyCart">
      <div class="empty-icon">&#128722;</div>
      <div class="empty-title">Your cart is empty</div>
      <div class="empty-sub">
        Looks like you haven't added anything yet.
      </div>
      <a href="../shop/shop.php" class="empty-btn">
        Start shopping
      </a>
    </div>

  </div><!-- /cartLeft -->


  <!-- ── RIGHT: Order summary ── -->
  <?php if (!empty($cartItems)): ?>
    <div>
      <div class="order-summary">
        <div class="os-title">Order summary</div>

        <div class="os-row">
          <span class="os-lbl">
            Subtotal
            (<span id="summaryItemCount"><?= $totalItems ?></span> items)
          </span>
          <span class="os-val" id="summarySubtotal">
            Rs <?= number_format($subtotal) ?>
          </span>
        </div>

        <div class="os-row">
          <span class="os-lbl">Delivery fee</span>
          <span class="os-val green" id="summaryDelivery">
            <?= $deliveryFee === 0 ? 'Free' : 'Rs ' . number_format($deliveryFee) ?>
          </span>
        </div>

        <?php if ($discount > 0): ?>
          <div class="os-row">
            <span class="os-lbl">Coupon (<?= $couponCode ?>)</span>
            <span class="os-val green">- Rs <?= number_format($discount) ?></span>
          </div>
        <?php endif; ?>

        <div class="os-divider"></div>

        <div class="os-total-row">
          <span class="os-total-lbl">Total</span>
          <span class="os-total-val" id="summaryTotal">
            Rs <?= number_format($grandTotal) ?>
          </span>
        </div>

        <!-- Coupon code form -->
        <form method="POST">
          <div class="coupon-row">
            <input class="coupon-inp" type="text" name="coupon_code"
                   placeholder="Coupon code"
                   value="<?= htmlspecialchars($couponCode ?? '') ?>">
            <button type="submit" name="apply_coupon" class="coupon-btn">
              Apply
            </button>
          </div>
          <?php if (isset($couponSuccess)): ?>
            <div class="coupon-msg success"><?= htmlspecialchars($couponSuccess) ?></div>
          <?php elseif (isset($couponError)): ?>
            <div class="coupon-msg error"><?= htmlspecialchars($couponError) ?></div>
          <?php endif; ?>
        </form>

        <!-- Checkout button -->
        <?php if ($isLoggedIn): ?>
          <a href="../checkout/checkout.php">
            <button class="checkout-btn">
              Proceed to checkout →
            </button>
          </a>
        <?php else: ?>
          <!-- Guest must login before checkout -->
          <a href="../login.php?redirect=cart">
            <button class="checkout-btn">
              Login to checkout →
            </button>
          </a>
          <div style="font-size:11px;color:var(--txt3);text-align:center;margin-bottom:10px;">
            You need to login to place an order
          </div>
        <?php endif; ?>

        <!-- Payment methods -->
        <div class="payment-icons">
          <span class="pay-tag">Cash on delivery</span>
          <span class="pay-tag">Bank transfer</span>
          <span class="pay-tag">EasyPaisa</span>
        </div>

        <div class="secure-note">
          <svg viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          Secure checkout — your data is protected
        </div>

        <a href="../shop/shop.php" class="continue-link">
          ← Continue shopping
        </a>

        <!-- Delivery info -->
        <div class="del-estimate">
          <div class="de-row">
            <svg viewBox="0 0 24 24">
              <rect x="1" y="3" width="15" height="13"/>
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
              <circle cx="5.5" cy="18.5" r="2.5"/>
              <circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
            Estimated delivery: 2-3 business days
          </div>
          <div class="de-row">
            <svg viewBox="0 0 24 24">
              <polyline points="1 4 1 10 7 10"/>
              <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
            </svg>
            Free returns within 7 days
          </div>
          <div class="de-row">
            <svg viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <?= $subtotal >= 2000 ? 'Free delivery on your order!' : 'Add Rs ' . number_format(2000 - $subtotal) . ' more for free delivery' ?>
          </div>
        </div>

      </div>
    </div>
  <?php endif; ?>

</div><!-- /cart-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Live quantity update via AJAX ──
// Updates qty display and price without page reload
const itemQtys   = {};  // cart_id => current qty
const itemPrices = {};  // cart_id => unit price

// Initialize from PHP data
<?php foreach ($cartItems as $item): ?>
  itemQtys['<?= $item['cart_id'] ?>']   = <?= $item['quantity'] ?>;
  itemPrices['<?= $item['cart_id'] ?>'] = <?= $item['price'] ?>;
<?php endforeach; ?>

function updateQty(cartId, unitPrice, delta, maxStock) {
    const current = itemQtys[cartId] ?? 1;
    const newQty  = Math.max(1, Math.min(maxStock, current + delta));
    if (newQty === current) return;

    itemQtys[cartId] = newQty;

    // Update displayed qty
    document.getElementById('qty_' + cartId).textContent = newQty;

    // Update displayed price for this item
    const newItemTotal = unitPrice * newQty;
    document.getElementById('price_' + cartId).textContent =
        'Rs ' + Math.round(newItemTotal).toLocaleString();

    // Recalculate summary
    recalculateSummary();

    // Send to server via AJAX (fire and forget)
    // Only for logged-in users (guest qty update handled client-side only)
    <?php if ($isLoggedIn): ?>
    fetch('update_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_id: cartId, quantity: newQty })
    });
    <?php else: ?>
    // Update session cart for guest via AJAX
    fetch('update_guest_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_id: cartId, quantity: newQty })
    });
    <?php endif; ?>
}

function recalculateSummary() {
    let subtotal = 0;
    let count    = 0;
    for (const id in itemQtys) {
        const el = document.getElementById('cartItem_' + id);
        if (!el || el.style.display === 'none') continue;
        subtotal += (itemPrices[id] ?? 0) * itemQtys[id];
        count    += itemQtys[id];
    }
    const delivery   = subtotal >= 2000 ? 0 : 150;
    const total      = subtotal + delivery;

    const subEl = document.getElementById('summarySubtotal');
    const totEl = document.getElementById('summaryTotal');
    const delEl = document.getElementById('summaryDelivery');
    const cntEl = document.getElementById('summaryItemCount');
    const lblEl = document.getElementById('totalItemsLabel');

    if (subEl) subEl.textContent = 'Rs ' + Math.round(subtotal).toLocaleString();
    if (totEl) totEl.textContent = 'Rs ' + Math.round(total).toLocaleString();
    if (delEl) delEl.textContent = delivery === 0 ? 'Free' : 'Rs 150';
    if (cntEl) cntEl.textContent = count;
    if (lblEl) lblEl.textContent = '(' + count + ' items)';
}

// ── Remove item (animated) ──
function removeItem(cartId) {
    const el = document.getElementById('cartItem_' + cartId);
    if (!el) return;

    // Animate out
    el.classList.add('removing');

    setTimeout(() => {
        el.style.display = 'none';
        delete itemQtys[cartId];
        delete itemPrices[cartId];

        // Check if all items removed
        const visibleItems = Object.keys(itemQtys).filter(id => {
            const el2 = document.getElementById('cartItem_' + id);
            return el2 && el2.style.display !== 'none';
        });

        if (visibleItems.length === 0) {
            document.getElementById('emptyCart').classList.add('visible');
        }

        recalculateSummary();

        // Submit the remove form to server
        document.getElementById('removeForm_' + cartId)?.submit();

    }, 280);
}

// ── Checkbox logic ──
function toggleSelectAll() {
    const allBox = document.getElementById('selectAllBox');
    allBox.classList.toggle('checked');
    const isChecked = allBox.classList.contains('checked');
    document.querySelectorAll('.check-box:not(#selectAllBox)').forEach(b => {
        b.classList.toggle('checked', isChecked);
    });
    updateSelectedLabel();
}

function toggleVendorGroup(box) {
    box.classList.toggle('checked');
    // Toggle all item checkboxes in same vendor group
    const group = box.closest('.vendor-group');
    group.querySelectorAll('.ci-check .check-box').forEach(b => {
        b.classList.toggle('checked', box.classList.contains('checked'));
    });
    updateSelectedLabel();
}

function toggleItemCheck(box, cartId) {
    box.classList.toggle('checked');
    updateSelectedLabel();
}

function updateSelectedLabel() {
    const total    = document.querySelectorAll('.ci-check .check-box').length;
    const selected = document.querySelectorAll('.ci-check .check-box.checked').length;
    const lbl = document.getElementById('selectedLabel');
    if (lbl) lbl.textContent = selected + ' selected';
}

// ── Save for later (placeholder) ──
function saveForLater(cartId) {
    alert('Save for later — coming soon!\nThis will move the item to your wishlist.');
}
</script>
</body>
</html>