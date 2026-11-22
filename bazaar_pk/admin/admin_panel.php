<?php
// admin/admin_panel.php
// Main admin dashboard — platform overview stats
// Only accessible by role = admin

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'dashboard';

// ── Platform stats ──
try {
    $totalUsers    = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalVendors  = (int)$conn->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $pendingVendors= (int)$conn->query("SELECT COUNT(*) FROM vendors WHERE status='pending'")->fetchColumn();
    $totalProducts = (int)$conn->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $totalOrders   = 0; $totalRevenue = 0; $todayOrders = 0; $monthRevenue = 0;
    try {
        $totalOrders   = (int)$conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $totalRevenue  = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
        $todayOrders   = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $monthRevenue  = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status!='cancelled'")->fetchColumn();
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    $totalUsers=$totalVendors=$pendingVendors=$totalProducts=0;
    $totalOrders=$totalRevenue=$todayOrders=$monthRevenue=0;
}

// ── Recent orders ──
$recentOrders = [];
try {
    $roStmt = $conn->query("
        SELECT o.id,o.status,o.total,o.created_at,u.full_name AS customer
        FROM orders o JOIN users u ON o.customer_id=u.id
        ORDER BY o.created_at DESC LIMIT 8
    ");
    $recentOrders = $roStmt->fetchAll();
} catch (PDOException $e) {}

// ── Recent users ──
$recentUsers = [];
try {
    $ruStmt = $conn->query("
        SELECT id,full_name,email,role,created_at
        FROM users ORDER BY created_at DESC LIMIT 6
    ");
    $recentUsers = $ruStmt->fetchAll();
} catch (PDOException $e) {}

// ── Pending vendors ──
$pendingVendorList = [];
try {
    $pvStmt = $conn->query("
        SELECT v.*,u.full_name,u.email
        FROM vendors v JOIN users u ON v.user_id=u.id
        WHERE v.status='pending' LIMIT 5
    ");
    $pendingVendorList = $pvStmt->fetchAll();
} catch (PDOException $e) {}

// ── Revenue last 7 days for mini chart ──
$chartData = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day  = date('D', strtotime("-$i days"));
        $rev  = $conn->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=? AND status!='cancelled'");
        $rev->execute([$date]);
        $chartData[] = ['day' => $day, 'value' => (float)$rev->fetchColumn()];
    }
} catch (PDOException $e) {
    $chartData = [];
}
$maxChart = max(array_column($chartData, 'value') ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/styleadmin.css"  rel="stylesheet">
</head>
<body>
<div class="admin-wrap">

  <?php include 'admin_sidebar.php'; ?>

  <div class="admin-main">

    <!-- Header -->
    <div class="admin-header">
      <div class="ah-left">
        <div class="ah-title">Dashboard</div>
      </div>
      <div class="ah-right">
        <?php if ($pendingVendors > 0): ?>
          <a href="manage_vendors.php?status=pending"
             style="font-size:11px;color:var(--amber);text-decoration:none;padding:5px 12px;border:1px solid rgba(245,158,11,0.3);border-radius:5px;">
            &#9888; <?= $pendingVendors ?> vendor(s) awaiting approval
          </a>
        <?php endif; ?>
        <div class="ah-notif">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <?php if ($pendingVendors > 0): ?><div class="ah-notif-dot"></div><?php endif; ?>
        </div>
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
        <a href="../logout.php" style="font-size:11px;color:var(--txt3);text-decoration:none;padding:6px 12px;border:1px solid var(--border);border-radius:6px;">Logout</a>
      </div>
    </div>

    <div class="admin-content">

      <!-- ── Stat cards ── -->
      <div class="admin-stats">

        <div class="admin-stat" style="--accent-color:var(--teal-dim);">
          <div class="as-label">Total revenue</div>
          <div class="as-num" style="color:var(--teal);">Rs <?= number_format($totalRevenue) ?></div>
          <div class="as-sub">All time sales</div>
          <div class="as-trend up">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Rs <?= number_format($monthRevenue) ?> this month
          </div>
        </div>

        <div class="admin-stat" style="--accent-color:rgba(74,222,128,0.1);">
          <div class="as-label">Total orders</div>
          <div class="as-num"><?= number_format($totalOrders) ?></div>
          <div class="as-sub">All time orders</div>
          <div class="as-trend up">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            <?= $todayOrders ?> orders today
          </div>
        </div>

        <div class="admin-stat" style="--accent-color:rgba(245,158,11,0.1);">
          <div class="as-label">Total users</div>
          <div class="as-num"><?= number_format($totalUsers) ?></div>
          <div class="as-sub"><?= $totalVendors ?> vendors registered</div>
          <?php if ($pendingVendors > 0): ?>
            <div class="as-trend" style="color:var(--amber);">
              &#9888; <?= $pendingVendors ?> pending approval
            </div>
          <?php endif; ?>
        </div>

        <div class="admin-stat" style="--accent-color:rgba(96,165,250,0.1);">
          <div class="as-label">Active products</div>
          <div class="as-num"><?= number_format($totalProducts) ?></div>
          <div class="as-sub">Live listings on shop</div>
        </div>

      </div>

      <!-- ── Revenue chart + Pending vendors ── -->
      <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:24px;">

        <!-- Revenue chart -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:4px;">Revenue — last 7 days</div>
          <div style="font-size:11px;color:var(--txt3);margin-bottom:16px;">Daily revenue in PKR</div>
          <?php if (!empty($chartData)): ?>
            <div style="display:flex;align-items:flex-end;gap:8px;height:80px;">
              <?php foreach ($chartData as $bar):
                $h = $maxChart > 0 ? max(6, round(($bar['value']/$maxChart)*80)) : 6;
              ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <div style="font-size:9px;color:var(--txt3);">
                    <?= $bar['value'] > 0 ? number_format($bar['value']/1000,0).'k' : '' ?>
                  </div>
                  <div style="width:100%;height:<?= $h ?>px;background:var(--teal-dim);border-radius:3px 3px 0 0;transition:background 0.15s;"
                       onmouseover="this.style.background='var(--teal)'"
                       onmouseout="this.style.background='var(--teal-dim)'"></div>
                  <div style="font-size:9px;color:var(--txt3);"><?= $bar['day'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="height:80px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--txt3);">
              No revenue data yet
            </div>
          <?php endif; ?>
        </div>

        <!-- Pending vendors -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
          <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:14px;">
            Pending approvals
            <?php if ($pendingVendors > 0): ?>
              <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,0.15);color:var(--amber);margin-left:6px;"><?= $pendingVendors ?></span>
            <?php endif; ?>
          </div>
          <?php if (empty($pendingVendorList)): ?>
            <div style="text-align:center;padding:20px;font-size:12px;color:var(--green);">&#10003; No pending approvals</div>
          <?php else: ?>
            <?php foreach ($pendingVendorList as $v): ?>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">
                <div class="tbl-avatar" style="background:var(--teal-dim);color:var(--teal);">
                  <?= strtoupper(substr($v['store_name'],0,1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="font-size:12px;font-weight:500;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($v['store_name']) ?></div>
                  <div style="font-size:10px;color:var(--txt3);"><?= htmlspecialchars($v['email']) ?></div>
                </div>
                <div style="display:flex;gap:4px;">
                  <a href="manage_vendors.php?approve=<?= $v['id'] ?>" class="tbl-btn approve">Approve</a>
                  <a href="manage_vendors.php?ban=<?= $v['id'] ?>" class="tbl-btn ban">Reject</a>
                </div>
              </div>
            <?php endforeach; ?>
            <a href="manage_vendors.php?status=pending" style="display:block;text-align:center;font-size:11px;color:var(--teal);text-decoration:none;margin-top:12px;">View all pending →</a>
          <?php endif; ?>
        </div>

      </div>

      <!-- ── Recent orders ── -->
      <div class="admin-section-header">
        <div class="ash-title">Recent orders</div>
        <a href="manage_orders.php" style="font-size:12px;color:var(--teal);text-decoration:none;">View all →</a>
      </div>

      <?php if (empty($recentOrders)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;font-size:12px;color:var(--txt3);margin-bottom:24px;">
          No orders yet — they will appear here once customers start purchasing.
        </div>
      <?php else: ?>
        <div class="admin-table-wrap" style="margin-bottom:24px;">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td style="font-weight:600;color:var(--teal);">#<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></td>
                  <td><?= htmlspecialchars($o['customer']) ?></td>
                  <td style="font-weight:600;">Rs <?= number_format($o['total']) ?></td>
                  <td><span class="status-badge sb-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                  <td style="color:var(--txt2);"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                  <td><a href="manage_orders.php?id=<?= $o['id'] ?>" class="tbl-btn">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- ── Recent users ── -->
      <div class="admin-section-header">
        <div class="ash-title">Recent users</div>
        <a href="manage_users.php" style="font-size:12px;color:var(--teal);text-decoration:none;">View all →</a>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentUsers)): ?>
              <tr><td colspan="5" style="text-align:center;color:var(--txt3);padding:24px;">No users yet</td></tr>
            <?php else: ?>
              <?php foreach ($recentUsers as $u): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div class="tbl-avatar" style="background:var(--teal-dim);color:var(--teal);">
                        <?= strtoupper(substr($u['full_name'],0,1)) ?>
                      </div>
                      <?= htmlspecialchars($u['full_name']) ?>
                    </div>
                  </td>
                  <td style="color:var(--txt2);"><?= htmlspecialchars($u['email']) ?></td>
                  <td><span class="status-badge sb-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                  <td style="color:var(--txt2);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div class="tbl-actions">
                      <a href="manage_users.php?id=<?= $u['id'] ?>" class="tbl-btn">View</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>