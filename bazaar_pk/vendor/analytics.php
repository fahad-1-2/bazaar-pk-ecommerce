<?php
// vendor/analytics.php
// Vendor sees sales charts, top products, revenue breakdown

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'analytics';

try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id=? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }

if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

// ── Stats ──
$totalRevenue = 0; $totalOrders = 0; $totalProducts = 0;
$monthRevenue = 0; $weekRevenue  = 0;
try {
    $r = $conn->prepare("SELECT COALESCE(SUM(oi.price_at_purchase*oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? AND o.status!='cancelled'");
    $r->execute([$vendorId]); $totalRevenue = (float)$r->fetchColumn();

    $r2 = $conn->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id WHERE oi.vendor_id=?");
    $r2->execute([$vendorId]); $totalOrders = (int)$r2->fetchColumn();

    $r3 = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id=? AND is_active=1");
    $r3->execute([$vendorId]); $totalProducts = (int)$r3->fetchColumn();

    $r4 = $conn->prepare("SELECT COALESCE(SUM(oi.price_at_purchase*oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? AND o.status!='cancelled' AND MONTH(o.created_at)=MONTH(NOW()) AND YEAR(o.created_at)=YEAR(NOW())");
    $r4->execute([$vendorId]); $monthRevenue = (float)$r4->fetchColumn();

    $r5 = $conn->prepare("SELECT COALESCE(SUM(oi.price_at_purchase*oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? AND o.status!='cancelled' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $r5->execute([$vendorId]); $weekRevenue = (float)$r5->fetchColumn();
} catch (PDOException $e) {}

// ── Last 7 days revenue chart ──
$chartData = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day  = date('D d', strtotime("-$i days"));
        $rev  = $conn->prepare("SELECT COALESCE(SUM(oi.price_at_purchase*oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? AND DATE(o.created_at)=? AND o.status!='cancelled'");
        $rev->execute([$vendorId, $date]);
        $chartData[] = ['day' => $day, 'value' => (float)$rev->fetchColumn()];
    }
} catch (PDOException $e) { $chartData = []; }
$maxChart = max(array_column($chartData, 'value') ?: [1]);

// ── Top 5 products by sales ──
$topProducts = [];
try {
    $tp = $conn->prepare("
        SELECT p.id, p.title, p.price, p.image,
               SUM(oi.quantity) AS units_sold,
               SUM(oi.price_at_purchase * oi.quantity) AS revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders   o ON oi.order_id   = o.id
        WHERE oi.vendor_id=? AND o.status!='cancelled'
        GROUP BY p.id, p.title, p.price, p.image
        ORDER BY units_sold DESC
        LIMIT 5
    ");
    $tp->execute([$vendorId]);
    $topProducts = $tp->fetchAll();
} catch (PDOException $e) { $topProducts = []; }

// ── Order status breakdown ──
$statusBreakdown = [];
try {
    $sb = $conn->prepare("
        SELECT o.status, COUNT(DISTINCT o.id) AS cnt
        FROM orders o JOIN order_items oi ON o.id=oi.order_id
        WHERE oi.vendor_id=?
        GROUP BY o.status
    ");
    $sb->execute([$vendorId]);
    $statusBreakdown = $sb->fetchAll();
} catch (PDOException $e) { $statusBreakdown = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics — Bazaar.pk Vendor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>
<div class="vendor-wrap">
  <?php include 'sidebar.php'; ?>
  <div class="vendor-main">

    <div class="vendor-header">
      <div class="vh-title">Analytics</div>
      <div class="vh-right">
        <div style="font-size:11px;color:var(--txt3);">Last updated: <?= date('d M Y, h:i A') ?></div>
        <div class="vh-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">

      <!-- ── Stat cards ── -->
      <div class="dashboard-grid">
        <div class="stat-card">
          <div class="stat-label">Total revenue</div>
          <div class="stat-num" style="color:var(--teal);">Rs <?= number_format($totalRevenue) ?></div>
          <div class="stat-sub">All time earnings</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">This month</div>
          <div class="stat-num" style="color:var(--green);">Rs <?= number_format($monthRevenue) ?></div>
          <div class="stat-sub">Current month revenue</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">This week</div>
          <div class="stat-num">Rs <?= number_format($weekRevenue) ?></div>
          <div class="stat-sub">Last 7 days revenue</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total orders</div>
          <div class="stat-num"><?= $totalOrders ?></div>
          <div class="stat-sub"><?= $totalProducts ?> active products</div>
        </div>
      </div>

      <!-- ── Revenue chart + Order status ── -->
      <div style="display:grid;grid-template-columns:1fr 260px;gap:16px;margin-bottom:24px;">

        <!-- Revenue chart -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:4px;">Revenue — last 7 days</div>
          <div style="font-size:11px;color:var(--txt3);margin-bottom:16px;">Daily sales in PKR</div>

          <?php if (!empty($chartData) && $maxChart > 0): ?>
            <div style="display:flex;align-items:flex-end;gap:6px;height:100px;">
              <?php foreach ($chartData as $bar):
                $h = $maxChart > 0 ? max(4, round(($bar['value']/$maxChart)*100)) : 4;
              ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <div style="font-size:9px;color:var(--txt3);text-align:center;">
                    <?= $bar['value'] > 0 ? 'Rs '.number_format($bar['value']/1000,0).'k' : '' ?>
                  </div>
                  <div style="width:100%;height:<?= $h ?>px;background:var(--teal-dim);border-radius:3px 3px 0 0;cursor:pointer;transition:background 0.15s;"
                       onmouseover="this.style.background='var(--teal)'"
                       onmouseout="this.style.background='var(--teal-dim)'"></div>
                  <div style="font-size:9px;color:var(--txt3);text-align:center;"><?= $bar['day'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="height:100px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--txt3);">
              No sales data yet — start selling to see your chart!
            </div>
          <?php endif; ?>
        </div>

        <!-- Order status breakdown -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:14px;">Order status</div>
          <?php if (empty($statusBreakdown)): ?>
            <div style="text-align:center;padding:20px;font-size:12px;color:var(--txt3);">No orders yet</div>
          <?php else: ?>
            <?php
            $statusColors = ['pending'=>'var(--amber)','processing'=>'#60a5fa','shipped'=>'#60a5fa','delivered'=>'var(--green)','cancelled'=>'var(--red)'];
            foreach ($statusBreakdown as $s):
            ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:8px;height:8px;border-radius:50%;background:<?= $statusColors[$s['status']] ?? 'var(--txt3)' ?>;"></div>
                  <span style="font-size:12px;color:var(--txt);"><?= ucfirst($s['status']) ?></span>
                </div>
                <span style="font-size:13px;font-weight:700;color:<?= $statusColors[$s['status']] ?? 'var(--txt)' ?>;"><?= $s['cnt'] ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>

      <!-- ── Top products ── -->
      <div class="section-header">
        <div class="sh-title">Top products by sales</div>
        <a href="my_products.php" style="font-size:12px;color:var(--teal);text-decoration:none;">View all →</a>
      </div>

      <?php if (empty($topProducts)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;font-size:12px;color:var(--txt3);">
          No sales data yet. Add products and start selling to see your top performers here.
        </div>
      <?php else: ?>
        <table class="products-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Product</th>
              <th>Price</th>
              <th>Units sold</th>
              <th>Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topProducts as $i => $p): ?>
              <tr>
                <td>
                  <span style="font-size:14px;font-weight:700;color:<?= $i===0 ? 'var(--amber)' : ($i===1 ? 'var(--txt2)' : 'var(--txt3)') ?>;">
                    #<?= $i+1 ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <div class="pt-img">
                      <?php if (!empty($p['image'])): ?>
                        <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="">
                      <?php else: ?>
                        &#128722;
                      <?php endif; ?>
                    </div>
                    <span class="pt-name"><?= htmlspecialchars(substr($p['title'],0,40)) ?><?= strlen($p['title'])>40 ? '...' : '' ?></span>
                  </div>
                </td>
                <td class="pt-price">Rs <?= number_format($p['price']) ?></td>
                <td style="font-size:13px;font-weight:600;color:var(--txt);"><?= number_format($p['units_sold']) ?> units</td>
                <td style="font-size:13px;font-weight:700;color:var(--teal);">Rs <?= number_format($p['revenue']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>