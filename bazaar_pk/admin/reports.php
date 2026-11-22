<?php
// admin/reports.php
// Admin views platform revenue reports, top vendors, top products

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userName    = $_SESSION['full_name'];
$currentPage = 'reports';

// ── Platform totals ──
$totalRevenue = 0; $totalOrders = 0; $totalUsers = 0;
$monthRevenue = 0; $weekRevenue  = 0; $todayRevenue = 0;
try {
    $totalRevenue = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
    $totalOrders  = (int)$conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalUsers   = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $monthRevenue = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
    $weekRevenue  = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
    $todayRevenue = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND DATE(created_at)=CURDATE()")->fetchColumn();
} catch (PDOException $e) {}

// ── Last 30 days chart ──
$chartData = [];
try {
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day  = date('d M', strtotime("-$i days"));
        $rev  = $conn->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=? AND status!='cancelled'");
        $rev->execute([$date]);
        $chartData[] = ['day' => $day, 'value' => (float)$rev->fetchColumn()];
    }
} catch (PDOException $e) { $chartData = []; }
$maxChart = max(array_column($chartData, 'value') ?: [1]);

// ── Top vendors by revenue ──
$topVendors = [];
try {
    $tv = $conn->query("
        SELECT v.id, v.store_name, v.logo,
               COUNT(DISTINCT o.id)                        AS order_count,
               SUM(oi.price_at_purchase * oi.quantity)     AS revenue,
               COUNT(DISTINCT p.id)                        AS product_count
        FROM vendors v
        JOIN order_items oi ON oi.vendor_id  = v.id
        JOIN orders      o  ON oi.order_id   = o.id
        JOIN products    p  ON p.vendor_id   = v.id
        WHERE o.status != 'cancelled'
        GROUP BY v.id, v.store_name, v.logo
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $topVendors = $tv->fetchAll();
} catch (PDOException $e) { $topVendors = []; }

// ── Top products by revenue ──
$topProducts = [];
try {
    $tp = $conn->query("
        SELECT p.id, p.title, p.price, p.image,
               v.store_name,
               SUM(oi.quantity)                            AS units_sold,
               SUM(oi.price_at_purchase * oi.quantity)     AS revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN vendors  v ON p.vendor_id   = v.id
        JOIN orders   o ON oi.order_id   = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.id, p.title, p.price, p.image, v.store_name
        ORDER BY revenue DESC
        LIMIT 8
    ");
    $topProducts = $tp->fetchAll();
} catch (PDOException $e) { $topProducts = []; }

// ── Orders by status ──
$ordersByStatus = [];
try {
    $obs = $conn->query("SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total FROM orders GROUP BY status");
    foreach ($obs->fetchAll() as $row) {
        $ordersByStatus[$row['status']] = $row;
    }
} catch (PDOException $e) {}

// ── Revenue by category ──
$revenueByCategory = [];
try {
    $rbc = $conn->query("
        SELECT p.category,
               SUM(oi.price_at_purchase * oi.quantity) AS revenue,
               SUM(oi.quantity)                         AS units
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders   o ON oi.order_id   = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.category
        ORDER BY revenue DESC
        LIMIT 8
    ");
    $revenueByCategory = $rbc->fetchAll();
} catch (PDOException $e) { $revenueByCategory = []; }

$maxCatRevenue = !empty($revenueByCategory) ? max(array_column($revenueByCategory, 'revenue')) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revenue Reports — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left"><div class="ah-title">Revenue reports</div></div>
      <div class="ah-right">
        <div style="font-size:11px;color:var(--txt3);">Generated: <?= date('d M Y, h:i A') ?></div>
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">

      <!-- ── Revenue stat cards ── -->
      <div class="admin-stats">
        <div class="admin-stat" style="--accent-color:var(--teal-dim);">
          <div class="as-label">Total revenue</div>
          <div class="as-num" style="color:var(--teal);">Rs <?= number_format($totalRevenue) ?></div>
          <div class="as-sub">All time platform sales</div>
        </div>
        <div class="admin-stat" style="--accent-color:rgba(74,222,128,0.1);">
          <div class="as-label">This month</div>
          <div class="as-num" style="color:var(--green);">Rs <?= number_format($monthRevenue) ?></div>
          <div class="as-sub"><?= date('F Y') ?></div>
        </div>
        <div class="admin-stat" style="--accent-color:rgba(96,165,250,0.1);">
          <div class="as-label">This week</div>
          <div class="as-num">Rs <?= number_format($weekRevenue) ?></div>
          <div class="as-sub">Last 7 days</div>
        </div>
        <div class="admin-stat" style="--accent-color:rgba(245,158,11,0.1);">
          <div class="as-label">Today</div>
          <div class="as-num" style="color:var(--amber);">Rs <?= number_format($todayRevenue) ?></div>
          <div class="as-sub"><?= date('d M Y') ?></div>
        </div>
      </div>

      <!-- ── 30 day chart ── -->
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:20px;">
        <div style="font-size:15px;font-weight:600;color:var(--txt);margin-bottom:4px;">Revenue — last 30 days</div>
        <div style="font-size:11px;color:var(--txt3);margin-bottom:20px;">Daily revenue in PKR</div>

        <?php if (!empty($chartData)): ?>
          <div style="display:flex;align-items:flex-end;gap:3px;height:120px;overflow:hidden;">
            <?php foreach ($chartData as $i => $bar):
              $h = $maxChart > 0 ? max(3, round(($bar['value']/$maxChart)*120)) : 3;
              $showLabel = ($i % 5 === 0);
            ?>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0;">
                <div style="width:100%;height:<?= $h ?>px;background:var(--teal-dim);border-radius:2px 2px 0 0;cursor:pointer;transition:background 0.15s;"
                     title="<?= $bar['day'] ?>: Rs <?= number_format($bar['value']) ?>"
                     onmouseover="this.style.background='var(--teal)';this.style.opacity='0.9'"
                     onmouseout="this.style.background='var(--teal-dim)';this.style.opacity='1'"></div>
                <?php if ($showLabel): ?>
                  <div style="font-size:8px;color:var(--txt3);writing-mode:vertical-rl;text-orientation:mixed;transform:rotate(180deg);height:28px;overflow:hidden;">
                    <?= $bar['day'] ?>
                  </div>
                <?php else: ?>
                  <div style="height:28px;"></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:11px;color:var(--txt3);margin-top:8px;text-align:center;">
            Hover over bars to see daily revenue — Total: Rs <?= number_format(array_sum(array_column($chartData,'value'))) ?>
          </div>
        <?php else: ?>
          <div style="height:120px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--txt3);">
            No revenue data yet
          </div>
        <?php endif; ?>
      </div>

      <!-- ── Two columns: Order status + Category revenue ── -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

        <!-- Order status breakdown -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:14px;">Orders by status</div>
          <?php
          $allStatuses = ['pending','processing','shipped','delivered','cancelled'];
          $statusColors = ['pending'=>'var(--amber)','processing'=>'#60a5fa','shipped'=>'#60a5fa','delivered'=>'var(--green)','cancelled'=>'var(--red)'];
          foreach ($allStatuses as $s):
            $row = $ordersByStatus[$s] ?? ['cnt'=>0,'total'=>0];
          ?>
            <div style="margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;">
                <span style="color:var(--txt);font-weight:500;"><?= ucfirst($s) ?></span>
                <span style="color:<?= $statusColors[$s] ?>;font-weight:600;"><?= $row['cnt'] ?> orders — Rs <?= number_format($row['total']) ?></span>
              </div>
              <div style="height:5px;background:var(--bg3);border-radius:3px;overflow:hidden;">
                <?php $pct = $totalOrders > 0 ? round(($row['cnt']/$totalOrders)*100) : 0; ?>
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $statusColors[$s] ?>;border-radius:3px;transition:width 0.5s;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Revenue by category -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:14px;">Revenue by category</div>
          <?php if (empty($revenueByCategory)): ?>
            <div style="text-align:center;padding:20px;font-size:12px;color:var(--txt3);">No sales data yet</div>
          <?php else: ?>
            <?php foreach ($revenueByCategory as $cat):
              $pct = $maxCatRevenue > 0 ? round(($cat['revenue']/$maxCatRevenue)*100) : 0;
            ?>
              <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;">
                  <span style="color:var(--txt);font-weight:500;"><?= ucfirst($cat['category'] ?? 'Unknown') ?></span>
                  <span style="color:var(--teal);font-weight:600;">Rs <?= number_format($cat['revenue']) ?></span>
                </div>
                <div style="height:5px;background:var(--bg3);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:<?= $pct ?>%;background:var(--teal);border-radius:3px;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>

      <!-- ── Top vendors ── -->
      <div class="admin-section-header">
        <div class="ash-title">Top vendors by revenue</div>
        <a href="manage_vendors.php" style="font-size:12px;color:var(--teal);text-decoration:none;">View all →</a>
      </div>

      <?php if (empty($topVendors)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;font-size:12px;color:var(--txt3);margin-bottom:20px;">
          No vendor sales data yet
        </div>
      <?php else: ?>
        <div class="admin-table-wrap" style="margin-bottom:20px;">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Vendor</th>
                <th>Products</th>
                <th>Orders</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topVendors as $i => $v): ?>
                <tr>
                  <td>
                    <span style="font-size:16px;font-weight:700;color:<?= $i===0?'var(--amber)':($i===1?'var(--txt2)':'var(--txt3)') ?>;">
                      #<?= $i+1 ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div class="tbl-avatar" style="background:var(--teal-dim);color:var(--teal);overflow:hidden;">
                        <?php if (!empty($v['logo'])): ?>
                          <img src="../uploads/vendors/<?= htmlspecialchars($v['logo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                          <?= strtoupper(substr($v['store_name'],0,1)) ?>
                        <?php endif; ?>
                      </div>
                      <span style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($v['store_name']) ?></span>
                    </div>
                  </td>
                  <td style="color:var(--txt2);"><?= $v['product_count'] ?></td>
                  <td style="color:var(--txt2);"><?= $v['order_count'] ?></td>
                  <td style="font-weight:700;color:var(--teal);font-size:13px;">Rs <?= number_format($v['revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- ── Top products ── -->
      <div class="admin-section-header">
        <div class="ash-title">Top products by revenue</div>
        <a href="manage_products.php" style="font-size:12px;color:var(--teal);text-decoration:none;">View all →</a>
      </div>

      <?php if (empty($topProducts)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;font-size:12px;color:var(--txt3);">
          No product sales data yet
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Product</th>
                <th>Vendor</th>
                <th>Price</th>
                <th>Units sold</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topProducts as $i => $p): ?>
                <tr>
                  <td>
                    <span style="font-size:14px;font-weight:700;color:<?= $i===0?'var(--amber)':($i===1?'var(--txt2)':'var(--txt3)') ?>;">
                      #<?= $i+1 ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;min-width:180px;">
                      <div style="width:34px;height:34px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                        <?php if (!empty($p['image'])): ?>
                          <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                          &#128722;
                        <?php endif; ?>
                      </div>
                      <span style="font-size:11px;font-weight:500;color:var(--txt);"><?= htmlspecialchars(substr($p['title'],0,35)) ?><?= strlen($p['title'])>35?'...':'' ?></span>
                    </div>
                  </td>
                  <td style="font-size:11px;color:var(--teal);"><?= htmlspecialchars($p['store_name']) ?></td>
                  <td style="font-weight:600;color:var(--teal);">Rs <?= number_format($p['price']) ?></td>
                  <td style="color:var(--txt2);"><?= number_format($p['units_sold']) ?></td>
                  <td style="font-weight:700;color:var(--teal);font-size:13px;">Rs <?= number_format($p['revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>