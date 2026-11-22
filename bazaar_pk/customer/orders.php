<?php
// customer/orders.php
// Customer views all their order history and order status

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); exit();
}
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php"); exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

// ── Fetch orders ──
$orders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*,
               COUNT(oi.id) AS item_count,
               GROUP_CONCAT(p.title SEPARATOR ', ') AS items_summary
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p    ON oi.product_id = p.id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

// ── Single order detail view ──
$orderDetail = null;
$orderItems  = [];
if (isset($_GET['id'])) {
    $oid = (int)$_GET['id'];
    try {
        $dStmt = $conn->prepare("
            SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1
        ");
        $dStmt->execute([$oid, $userId]);
        $orderDetail = $dStmt->fetch();

        if ($orderDetail) {
            $iStmt = $conn->prepare("
                SELECT oi.*, p.title, p.image, v.store_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN vendors  v ON oi.vendor_id  = v.id
                WHERE oi.order_id = ?
            ");
            $iStmt->execute([$oid]);
            $orderItems = $iStmt->fetchAll();
        }
    } catch (PDOException $e) {}
}

// Status color map
$statusColors = [
    'pending'    => 'var(--amber)',
    'processing' => '#60a5fa',
    'shipped'    => '#60a5fa',
    'delivered'  => 'var(--green)',
    'cancelled'  => 'var(--red)',
];
$statusBg = [
    'pending'    => 'rgba(245,158,11,0.12)',
    'processing' => 'rgba(96,165,250,0.12)',
    'shipped'    => 'rgba(96,165,250,0.12)',
    'delivered'  => 'rgba(74,222,128,0.12)',
    'cancelled'  => 'rgba(248,113,113,0.12)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"     rel="stylesheet">
  <link href="../css/styleshop.css" rel="stylesheet">
  <style>
    .page-wrap { max-width: 860px; margin: 0 auto; padding: 24px 20px; }
    .page-title { font-size: 20px; font-weight: 700; color: var(--txt); margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .page-title a { font-size: 12px; color: var(--teal); text-decoration: none; font-weight: 400; }

    .order-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 12px; transition: border-color 0.15s; }
    .order-card:hover { border-color: var(--border2); }

    .oc-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 8px; }
    .oc-id { font-size: 13px; font-weight: 700; color: var(--teal); }
    .oc-date { font-size: 11px; color: var(--txt3); margin-top: 2px; }
    .oc-status { font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 4px; }

    .oc-body { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .oc-field-label { font-size: 10px; color: var(--txt3); margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.4px; }
    .oc-field-value { font-size: 12px; color: var(--txt); font-weight: 500; }

    .oc-items { font-size: 11px; color: var(--txt2); padding-top: 10px; border-top: 1px solid var(--border); margin-bottom: 10px; line-height: 1.6; }
    .oc-actions { display: flex; gap: 8px; }
    .oc-btn { padding: 6px 14px; border-radius: 5px; font-size: 11px; font-weight: 500; cursor: pointer; text-decoration: none; font-family: inherit; transition: all 0.15s; }
    .oc-btn-primary { background: var(--teal); color: #0f1117; border: none; }
    .oc-btn-primary:hover { background: var(--teal2); }
    .oc-btn-ghost { background: transparent; color: var(--txt2); border: 1px solid var(--border); }
    .oc-btn-ghost:hover { border-color: var(--border2); color: var(--txt); }

    .empty-state { text-align: center; padding: 60px 20px; background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; }
    .empty-icon { font-size: 52px; margin-bottom: 16px; opacity: 0.5; }

    /* Detail view */
    .detail-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin-bottom: 14px; }
    .detail-title { font-size: 13px; font-weight: 600; color: var(--txt); margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
    .detail-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
    .detail-item:last-child { border-bottom: none; }
    .di-img { width: 46px; height: 46px; border-radius: 7px; background: var(--bg3); border: 1px solid var(--border); overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .di-img img { width: 100%; height: 100%; object-fit: cover; }
    .di-name { font-size: 12px; font-weight: 500; color: var(--txt); }
    .di-vendor { font-size: 10px; color: var(--teal); margin-top: 2px; }
    .di-price { font-size: 13px; font-weight: 700; color: var(--teal); margin-left: auto; white-space: nowrap; }
    .di-qty { font-size: 10px; color: var(--txt3); text-align: right; }

    .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--txt2); }
    .info-value { color: var(--txt); font-weight: 500; }

    /* Track order steps */
    .track-steps { display: flex; align-items: center; margin-bottom: 20px; }
    .ts-step { display: flex; flex-direction: column; align-items: center; flex: 1; }
    .ts-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid var(--border); background: var(--bg3); color: var(--txt3); margin-bottom: 6px; }
    .ts-circle.done   { background: var(--teal); border-color: var(--teal); color: #0f1117; }
    .ts-circle.active { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
    .ts-label { font-size: 10px; color: var(--txt3); text-align: center; }
    .ts-label.done   { color: var(--teal); }
    .ts-label.active { color: var(--teal); font-weight: 600; }
    .ts-line { flex: 1; height: 2px; background: var(--border); margin: 0 4px; margin-bottom: 22px; }
    .ts-line.done { background: var(--teal); }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="shop-nav">
  <a href="../shop/shop.php" class="shop-logo">Bazaar<span>.pk</span></a>
  <div style="flex:1;"></div>
  <div class="nav-right">
    <a href="../cart/cart.php" class="nav-cart">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
      <span class="cart-lbl">Cart</span>
    </a>
    <a href="../logout.php" class="nav-logout-ghost">Logout</a>
  </div>
</nav>

<div class="page-wrap">

  <?php if ($orderDetail): ?>
    <!-- ── Single order detail ── -->
    <div class="page-title">
      <a href="orders.php">← My orders</a>
      Order #<?= str_pad($orderDetail['id'],5,'0',STR_PAD_LEFT) ?>
    </div>

    <!-- Track order steps -->
    <?php
    $steps = ['pending','processing','shipped','delivered'];
    $currentStep = array_search($orderDetail['status'], $steps);
    if ($orderDetail['status'] === 'cancelled') $currentStep = -1;
    ?>
    <?php if ($orderDetail['status'] !== 'cancelled'): ?>
      <div class="detail-card">
        <div class="detail-title">Order tracking</div>
        <div class="track-steps">
          <?php
          $stepLabels = ['Placed','Processing','Shipped','Delivered'];
          $stepIcons  = ['📋','⚙️','🚚','✅'];
          for ($i = 0; $i < count($steps); $i++):
            $isDone   = $currentStep > $i;
            $isActive = $currentStep === $i;
            $cls = $isDone ? 'done' : ($isActive ? 'active' : '');
          ?>
            <div class="ts-step">
              <div class="ts-circle <?= $cls ?>">
                <?= $isDone ? '✓' : ($i+1) ?>
              </div>
              <div class="ts-label <?= $cls ?>"><?= $stepLabels[$i] ?></div>
            </div>
            <?php if ($i < count($steps)-1): ?>
              <div class="ts-line <?= $isDone ? 'done' : '' ?>"></div>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php else: ?>
      <div style="background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2);border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:12px;color:var(--red);">
        &#10007; This order has been cancelled.
      </div>
    <?php endif; ?>

    <!-- Order items -->
    <div class="detail-card">
      <div class="detail-title">Items ordered (<?= count($orderItems) ?>)</div>
      <?php foreach ($orderItems as $item): ?>
        <div class="detail-item">
          <div class="di-img">
            <?php if (!empty($item['image'])): ?>
              <img src="../uploads/products/<?= htmlspecialchars($item['image']) ?>" alt="">
            <?php else: ?>
              &#128722;
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <div class="di-name"><?= htmlspecialchars($item['title']) ?></div>
            <div class="di-vendor"><?= htmlspecialchars($item['store_name']) ?></div>
          </div>
          <div>
            <div class="di-price">Rs <?= number_format($item['price_at_purchase'] * $item['quantity']) ?></div>
            <div class="di-qty">Rs <?= number_format($item['price_at_purchase']) ?> × <?= $item['quantity'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Order info -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="detail-card">
        <div class="detail-title">Delivery info</div>
        <div class="info-row"><span class="info-label">Address</span><span class="info-value" style="font-size:11px;text-align:right;max-width:200px;"><?= htmlspecialchars($orderDetail['address']) ?></span></div>
        <div class="info-row"><span class="info-label">Payment</span><span class="info-value"><?= ucfirst(str_replace('_',' ',$orderDetail['payment_method']??'cod')) ?></span></div>
        <div class="info-row"><span class="info-label">Placed on</span><span class="info-value"><?= date('d M Y', strtotime($orderDetail['created_at'])) ?></span></div>
      </div>
      <div class="detail-card">
        <div class="detail-title">Payment summary</div>
        <div class="info-row"><span class="info-label">Order total</span><span class="info-value" style="color:var(--teal);font-size:16px;font-weight:700;">Rs <?= number_format($orderDetail['total']) ?></span></div>
        <div class="info-row"><span class="info-label">Status</span>
          <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:4px;background:<?= $statusBg[$orderDetail['status']] ?? 'var(--bg3)' ?>;color:<?= $statusColors[$orderDetail['status']] ?? 'var(--txt)' ?>;">
            <?= ucfirst($orderDetail['status']) ?>
          </span>
        </div>
      </div>
    </div>

    <div style="margin-top:6px;">
      <a href="../shop/shop.php" class="oc-btn oc-btn-primary" style="display:inline-block;">Continue shopping</a>
    </div>

  <?php else: ?>
    <!-- ── Orders list ── -->
    <div class="page-title">
      My orders
      <a href="../shop/shop.php">← Continue shopping</a>
    </div>

    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <div class="empty-icon">&#128230;</div>
        <div style="font-size:16px;font-weight:600;color:var(--txt);margin-bottom:8px;">No orders yet</div>
        <div style="font-size:13px;color:var(--txt2);margin-bottom:20px;">You haven't placed any orders yet.</div>
        <a href="../shop/shop.php" style="display:inline-block;padding:10px 24px;background:var(--teal);color:#0f1117;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;">Start shopping</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <div class="order-card">
          <div class="oc-header">
            <div>
              <div class="oc-id">Order #<?= str_pad($order['id'],5,'0',STR_PAD_LEFT) ?></div>
              <div class="oc-date"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
            </div>
            <span class="oc-status"
                  style="background:<?= $statusBg[$order['status']] ?? 'var(--bg3)' ?>;color:<?= $statusColors[$order['status']] ?? 'var(--txt)' ?>;">
              <?= ucfirst($order['status']) ?>
            </span>
          </div>

          <div class="oc-body">
            <div>
              <div class="oc-field-label">Total</div>
              <div class="oc-field-value" style="color:var(--teal);font-size:15px;">Rs <?= number_format($order['total']) ?></div>
            </div>
            <div>
              <div class="oc-field-label">Items</div>
              <div class="oc-field-value"><?= $order['item_count'] ?> item(s)</div>
            </div>
            <div>
              <div class="oc-field-label">Payment</div>
              <div class="oc-field-value"><?= ucfirst(str_replace('_',' ',$order['payment_method']??'cod')) ?></div>
            </div>
          </div>

          <div class="oc-items">
            <?= htmlspecialchars(substr($order['items_summary'] ?? '—', 0, 100)) ?>
            <?= strlen($order['items_summary'] ?? '') > 100 ? '...' : '' ?>
          </div>

          <div class="oc-actions">
            <a href="orders.php?id=<?= $order['id'] ?>" class="oc-btn oc-btn-primary">View details</a>
            <?php if ($order['status'] === 'delivered'): ?>
              <a href="../shop/shop.php" class="oc-btn oc-btn-ghost">Buy again</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

            </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>