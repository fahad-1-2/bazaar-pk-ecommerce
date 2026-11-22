<?php
// vendor/vendor_panel.php
// Main vendor dashboard — shows stats, recent orders, recent products
// Only accessible by users with role = vendor

session_start();
require_once '../db.php';

// ── Auth guard — vendor only ──
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] !== 'vendor') {
    header("Location: ../index.php");
    exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

// ── Fetch vendor profile ──
// One user can have one vendor store
try {
    $vStmt = $conn->prepare("
        SELECT * FROM vendors WHERE user_id = ? LIMIT 1
    ");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) {
    $vendor = null;
}

// If vendor profile not set up yet, redirect to setup
if (!$vendor) {
    header("Location: setup_store.php");
    exit();
}

$vendorId = $vendor['id'];

// ── Dashboard stats ──
try {
    // Total products
    $totalProducts = $conn->prepare("
        SELECT COUNT(*) FROM products WHERE vendor_id = ?
    ");
    $totalProducts->execute([$vendorId]);
    $totalProducts = (int)$totalProducts->fetchColumn();

    // Active products
    $activeProducts = $conn->prepare("
        SELECT COUNT(*) FROM products WHERE vendor_id = ? AND is_active = 1
    ");
    $activeProducts->execute([$vendorId]);
    $activeProducts = (int)$activeProducts->fetchColumn();

    // Total orders (orders containing this vendor's products)
    $totalOrders = $conn->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.vendor_id = ?
    ");
    $totalOrders->execute([$vendorId]);
    $totalOrders = (int)$totalOrders->fetchColumn();

    // Total revenue
    $totalRevenue = $conn->prepare("
        SELECT COALESCE(SUM(oi.price_at_purchase * oi.quantity), 0)
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.vendor_id = ?
          AND o.status != 'cancelled'
    ");
    $totalRevenue->execute([$vendorId]);
    $totalRevenue = (float)$totalRevenue->fetchColumn();

    // Pending orders
    $pendingOrders = $conn->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.vendor_id = ?
          AND o.status = 'pending'
    ");
    $pendingOrders->execute([$vendorId]);
    $pendingOrders = (int)$pendingOrders->fetchColumn();

    // This month revenue
    $monthRevenue = $conn->prepare("
        SELECT COALESCE(SUM(oi.price_at_purchase * oi.quantity), 0)
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.vendor_id = ?
          AND o.status != 'cancelled'
          AND MONTH(o.created_at) = MONTH(NOW())
          AND YEAR(o.created_at)  = YEAR(NOW())
    ");
    $monthRevenue->execute([$vendorId]);
    $monthRevenue = (float)$monthRevenue->fetchColumn();

} catch (PDOException $e) {
    // orders table may not exist yet
    $totalProducts  = 0;
    $activeProducts = 0;
    $totalOrders    = 0;
    $totalRevenue   = 0;
    $pendingOrders  = 0;
    $monthRevenue   = 0;
}

// ── Recent orders (last 5) ──
try {
    $recentOrders = $conn->prepare("
        SELECT o.id, o.status, o.created_at,
               u.full_name AS customer_name,
               SUM(oi.price_at_purchase * oi.quantity) AS order_total,
               COUNT(oi.id) AS item_count
        FROM orders o
        JOIN order_items oi ON o.id  = oi.order_id
        JOIN users u        ON o.customer_id = u.id
        WHERE oi.vendor_id = ?
        GROUP BY o.id, o.status, o.created_at, u.full_name
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrders->execute([$vendorId]);
    $recentOrders = $recentOrders->fetchAll();
} catch (PDOException $e) {
    $recentOrders = [];
}

// ── Recent products (last 5) ──
try {
    $recentProducts = $conn->prepare("
        SELECT * FROM products
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentProducts->execute([$vendorId]);
    $recentProducts = $recentProducts->fetchAll();
} catch (PDOException $e) {
    $recentProducts = [];
}

// ── Low stock alert (stock <= 5) ──
try {
    $lowStock = $conn->prepare("
        SELECT id, title, stock FROM products
        WHERE vendor_id = ? AND stock <= 5 AND is_active = 1
        ORDER BY stock ASC
        LIMIT 5
    ");
    $lowStock->execute([$vendorId]);
    $lowStock = $lowStock->fetchAll();
} catch (PDOException $e) {
    $lowStock = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Dashboard — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>

<div class="vendor-wrap">

  <!-- ============================================================
       SIDEBAR
       ============================================================ -->
  <?php include 'sidebar.php'; ?>

  <!-- ============================================================
       MAIN CONTENT
       ============================================================ -->
  <div class="vendor-main">

    <!-- Header -->
    <div class="vendor-header">
      <div class="vh-title">Dashboard</div>
      <div class="vh-right">
        <div class="vh-profile">
          Welcome back, <span class="vh-profile-name"><?= htmlspecialchars($userName) ?></span>
        </div>
        <div class="vh-avatar">
          <?= strtoupper(substr($userName, 0, 1)) ?>
        </div>
        <a href="../logout.php" style="font-size:11px;color:var(--txt3);text-decoration:none;padding:6px 12px;border:1px solid var(--border);border-radius:6px;transition:all 0.15s;">Logout</a>
      </div>
    </div>

    <!-- Content -->
    <div class="vendor-content">

      <!-- ── Store status alert ── -->
      <?php if ($vendor['status'] === 'pending'): ?>
        <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:var(--amber);">
          &#9888; Your store is pending approval. Products won't be visible to customers until admin approves your account.
        </div>
      <?php elseif ($vendor['status'] === 'banned'): ?>
        <div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:var(--red);">
          &#10007; Your store has been suspended. Contact support for more information.
        </div>
      <?php endif; ?>

      <!-- ── Stats grid ── -->
      <div class="dashboard-grid">

        <div class="stat-card">
          <div class="stat-label">Total revenue</div>
          <div class="stat-num" style="color:var(--teal);">
            Rs <?= number_format($totalRevenue) ?>
          </div>
          <div class="stat-sub">All time earnings</div>
          <div class="stat-trend">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Rs <?= number_format($monthRevenue) ?> this month
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Total orders</div>
          <div class="stat-num"><?= $totalOrders ?></div>
          <div class="stat-sub">All time orders</div>
          <?php if ($pendingOrders > 0): ?>
            <div class="stat-trend" style="color:var(--amber);">
              &#9888; <?= $pendingOrders ?> pending action
            </div>
          <?php endif; ?>
        </div>

        <div class="stat-card">
          <div class="stat-label">Products listed</div>
          <div class="stat-num"><?= $totalProducts ?></div>
          <div class="stat-sub"><?= $activeProducts ?> active listings</div>
          <div class="stat-trend">
            <a href="add_product.php" style="color:var(--teal);text-decoration:none;">+ Add new product</a>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Store rating</div>
          <div class="stat-num" style="color:var(--amber);">
            <?= number_format($vendor['rating'] ?? 0, 1) ?>
            <span style="font-size:16px;">&#11088;</span>
          </div>
          <div class="stat-sub">Based on customer reviews</div>
        </div>

      </div><!-- /dashboard-grid -->


      <!-- ── Two column row: Recent orders + Low stock ── -->
      <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:24px;">

        <!-- Recent orders -->
        <div>
          <div class="section-header">
            <div class="sh-title">Recent orders</div>
            <a href="orders.php" class="sh-action" style="color:var(--teal);text-decoration:none;font-size:12px;">View all →</a>
          </div>

          <?php if (empty($recentOrders)): ?>
            <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;">
              <div style="font-size:32px;margin-bottom:12px;opacity:0.5;">&#128230;</div>
              <div style="font-size:13px;color:var(--txt2);">No orders yet</div>
              <div style="font-size:11px;color:var(--txt3);margin-top:4px;">Orders will appear here when customers buy your products</div>
            </div>
          <?php else: ?>
            <?php foreach ($recentOrders as $order): ?>
              <div class="order-card">
                <div class="order-header">
                  <div>
                    <div class="order-id">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></div>
                    <div class="order-date"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                  </div>
                  <span class="order-status <?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                  </span>
                </div>
                <div class="order-body">
                  <div class="order-item">
                    <div class="order-item-label">Customer</div>
                    <div class="order-item-value"><?= htmlspecialchars($order['customer_name']) ?></div>
                  </div>
                  <div class="order-item">
                    <div class="order-item-label">Amount</div>
                    <div class="order-item-value" style="color:var(--teal);">Rs <?= number_format($order['order_total']) ?></div>
                  </div>
                </div>
                <div class="order-actions">
                  <a href="orders.php?id=<?= $order['id'] ?>" class="order-btn">View details</a>
                  <?php if ($order['status'] === 'pending'): ?>
                    <a href="orders.php?action=ship&id=<?= $order['id'] ?>" class="order-btn ship">Mark as shipped</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Low stock alert -->
        <div>
          <div class="section-header">
            <div class="sh-title">Low stock &#9888;</div>
            <a href="my_products.php" style="color:var(--teal);text-decoration:none;font-size:12px;">Manage →</a>
          </div>

          <?php if (empty($lowStock)): ?>
            <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:24px;text-align:center;">
              <div style="font-size:13px;color:var(--green);">&#10003; All products well stocked</div>
            </div>
          <?php else: ?>
            <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;overflow:hidden;">
              <?php foreach ($lowStock as $item): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border);">
                  <div style="font-size:12px;color:var(--txt);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($item['title']) ?>
                  </div>
                  <span style="font-size:11px;font-weight:600;color:<?= $item['stock'] == 0 ? 'var(--red)' : 'var(--amber)' ?>;">
                    <?= $item['stock'] == 0 ? 'Out of stock' : $item['stock'] . ' left' ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Quick add product -->
          <a href="add_product.php" class="sh-action-btn" style="display:block;text-align:center;margin-top:12px;">
            + Add new product
          </a>
        </div>

      </div>


      <!-- ── Recent products ── -->
      <div class="section-header">
        <div class="sh-title">Recent products</div>
        <a href="my_products.php" class="sh-action-btn">View all products</a>
      </div>

      <?php if (empty($recentProducts)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:40px;text-align:center;">
          <div style="font-size:40px;margin-bottom:14px;opacity:0.5;">&#128722;</div>
          <div style="font-size:14px;color:var(--txt);margin-bottom:8px;">No products yet</div>
          <div style="font-size:12px;color:var(--txt2);margin-bottom:20px;">Start adding products to your store</div>
          <a href="add_product.php" class="sh-action-btn">Add your first product</a>
        </div>
      <?php else: ?>
        <table class="products-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentProducts as $p):
              $discPct = (!empty($p['old_price']) && $p['old_price'] > $p['price'])
                ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100) : 0;
              $stockClass = $p['stock'] > 10 ? '' : ($p['stock'] > 0 ? 'low' : 'out');
              $status = $p['is_active'] ? 'active' : 'inactive';
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="pt-img">
                      <?php if (!empty($p['image'])): ?>
                        <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="">
                      <?php else: ?>
                        &#128722;
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="pt-name"><?= htmlspecialchars(substr($p['title'], 0, 35)) ?><?= strlen($p['title']) > 35 ? '...' : '' ?></div>
                      <div style="font-size:10px;color:var(--txt3);"><?= ucfirst(htmlspecialchars($p['category'] ?? '—')) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="pt-price">Rs <?= number_format($p['price']) ?></div>
                  <?php if ($discPct > 0): ?>
                    <div style="font-size:10px;color:var(--txt3);text-decoration:line-through;">Rs <?= number_format($p['old_price']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="pt-stock <?= $stockClass ?>">
                    <?= $p['stock'] == 0 ? 'Out of stock' : $p['stock'] . ' units' ?>
                  </span>
                </td>
                <td>
                  <span class="pt-status <?= $status ?>"><?= ucfirst($status) ?></span>
                </td>
                <td>
                  <div class="pt-actions">
                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="pt-btn">Edit</a>
                    <a href="../product/product.php?id=<?= $p['id'] ?>" class="pt-btn" target="_blank">View</a>
                    <a href="my_products.php?toggle=<?= $p['id'] ?>" class="pt-btn">
                      <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div><!-- /vendor-content -->
  </div><!-- /vendor-main -->
</div><!-- /vendor-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>