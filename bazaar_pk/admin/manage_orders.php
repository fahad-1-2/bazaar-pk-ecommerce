<?php
// admin/manage_orders.php
// Admin views all platform orders, updates status, handles refunds

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userName    = $_SESSION['full_name'];
$currentPage = 'orders';
$flash       = ''; $flashType = '';

// ── Update order status ──
if (isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['new_status'] ?? '';
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        try {
            $conn->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $oid]);
            $flash = "Order #".str_pad($oid,5,'0',STR_PAD_LEFT)." status updated to ".ucfirst($status).".";
            $flashType = 'success';
        } catch (PDOException $e) { $flash = "Error updating status."; $flashType = 'error'; }
    }
}

// ── Filters ──
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page-1)*$perPage;

try {
    $sql    = "SELECT o.*, u.full_name AS customer, u.email AS customer_email,
                      COUNT(oi.id) AS item_count
               FROM orders o
               JOIN users u ON o.customer_id=u.id
               LEFT JOIN order_items oi ON o.id=oi.order_id
               WHERE 1=1";
    $params = [];

    if ($filterStatus !== '') { $sql .= " AND o.status=?"; $params[] = $filterStatus; }
    if ($search !== '')       { $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql .= " GROUP BY o.id, u.full_name, u.email";

    $cntStmt = $conn->prepare("SELECT COUNT(*) FROM ($sql) sub");
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount/$perPage);

    $sql .= " ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = []; $totalCount = 0; $totalPages = 0;
}

// ── Single order detail ──
$orderDetail = null;
$orderItems  = [];
if (isset($_GET['id'])) {
    $oid = (int)$_GET['id'];
    try {
        $dStmt = $conn->prepare("
            SELECT o.*, u.full_name AS customer, u.email AS customer_email
            FROM orders o JOIN users u ON o.customer_id=u.id
            WHERE o.id=? LIMIT 1
        ");
        $dStmt->execute([$oid]);
        $orderDetail = $dStmt->fetch();

        $iStmt = $conn->prepare("
            SELECT oi.*, p.title, p.image, v.store_name
            FROM order_items oi
            JOIN products p ON oi.product_id=p.id
            JOIN vendors  v ON oi.vendor_id=v.id
            WHERE oi.order_id=?
        ");
        $iStmt->execute([$oid]);
        $orderItems = $iStmt->fetchAll();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Orders — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left">
        <div class="ah-title"><?= $orderDetail ? 'Order #'.str_pad($orderDetail['id'],5,'0',STR_PAD_LEFT) : 'Manage orders' ?></div>
      </div>
      <div class="ah-right">
        <?php if ($orderDetail): ?>
          <a href="manage_orders.php" class="af-btn af-btn-ghost" style="text-decoration:none;display:flex;align-items:center;height:36px;">← All orders</a>
        <?php endif; ?>
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

      <?php if ($orderDetail): ?>
        <!-- ── Single order detail view ── -->
        <div style="display:grid;grid-template-columns:1fr 280px;gap:16px;">
          <div>
            <!-- Order items -->
            <div class="detail-panel">
              <div class="dp-title">Order items</div>
              <?php foreach ($orderItems as $item): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
                  <div style="width:44px;height:44px;border-radius:7px;background:var(--bg3);border:1px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
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
                    <div style="font-size:10px;color:var(--txt3);">Rs <?= number_format($item['price_at_purchase']) ?> × <?= $item['quantity'] ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Customer info -->
            <div class="detail-panel">
              <div class="dp-title">Customer details</div>
              <div class="dp-row"><div class="dp-key">Name</div><div class="dp-value"><?= htmlspecialchars($orderDetail['customer']) ?></div></div>
              <div class="dp-row"><div class="dp-key">Email</div><div class="dp-value"><?= htmlspecialchars($orderDetail['customer_email']) ?></div></div>
              <div class="dp-row"><div class="dp-key">Delivery address</div><div class="dp-value"><?= nl2br(htmlspecialchars($orderDetail['address'] ?? '—')) ?></div></div>
              <div class="dp-row"><div class="dp-key">Payment method</div><div class="dp-value"><?= ucfirst(str_replace('_',' ',$orderDetail['payment_method'] ?? 'cod')) ?></div></div>
              <div class="dp-row"><div class="dp-key">Order placed</div><div class="dp-value"><?= date('d M Y, h:i A', strtotime($orderDetail['created_at'])) ?></div></div>
            </div>
          </div>

          <!-- Right: Status + total -->
          <div>
            <div class="detail-panel">
              <div class="dp-title">Order summary</div>
              <div style="font-size:24px;font-weight:700;color:var(--teal);margin-bottom:6px;">Rs <?= number_format($orderDetail['total']) ?></div>
              <div style="margin-bottom:16px;">
                <span class="status-badge sb-<?= $orderDetail['status'] ?>"><?= ucfirst($orderDetail['status']) ?></span>
              </div>

              <!-- Update status form -->
              <form method="POST">
                <input type="hidden" name="order_id" value="<?= $orderDetail['id'] ?>">
                <div style="font-size:11px;font-weight:600;color:var(--txt2);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Update status</div>
                <select name="new_status" class="af-select" style="width:100%;margin-bottom:8px;height:36px;">
                  <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $orderDetail['status']===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="af-btn af-btn-primary" style="width:100%;height:36px;">
                  Update status
                </button>
              </form>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- ── Orders list ── -->

        <!-- Status tabs -->
        <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
          <?php foreach (['' => 'All','pending' => 'Pending','processing' => 'Processing','shipped' => 'Shipped','delivered' => 'Delivered','cancelled' => 'Cancelled'] as $val => $lbl): ?>
            <a href="manage_orders.php?status=<?= $val ?>&q=<?= urlencode($search) ?>"
               style="padding:6px 14px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;
                      border:1px solid <?= $filterStatus===$val ? 'var(--teal)' : 'var(--border)' ?>;
                      color:<?= $filterStatus===$val ? 'var(--teal)' : 'var(--txt2)' ?>;
                      background:<?= $filterStatus===$val ? 'var(--teal-dim)' : 'transparent' ?>;">
              <?= $lbl ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Search -->
        <form method="GET" class="admin-filters">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
          <input class="af-input" type="text" name="q" placeholder="Search customer name or email..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="af-btn af-btn-primary">Search</button>
          <a href="manage_orders.php" class="af-btn af-btn-ghost" style="text-decoration:none;display:flex;align-items:center;">Reset</a>
        </form>

        <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;"><?= $totalCount ?> order(s) found</div>

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--txt3);padding:28px;">No orders found</td></tr>
              <?php else: ?>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td style="font-weight:700;color:var(--teal);">#<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></td>
                    <td>
                      <div style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($o['customer']) ?></div>
                      <div style="font-size:10px;color:var(--txt3);"><?= htmlspecialchars($o['customer_email']) ?></div>
                    </td>
                    <td style="text-align:center;color:var(--txt2);"><?= $o['item_count'] ?></td>
                    <td style="font-weight:600;color:var(--teal);">Rs <?= number_format($o['total']) ?></td>
                    <td style="font-size:11px;color:var(--txt2);"><?= ucfirst(str_replace('_',' ',$o['payment_method']??'cod')) ?></td>
                    <td>
                      <span class="status-badge sb-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
                    </td>
                    <td style="color:var(--txt2);font-size:11px;"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td>
                      <a href="manage_orders.php?id=<?= $o['id'] ?>" class="tbl-btn">View details</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
          <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
              <div class="pagination-info">Showing <?= ($offset+1) ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
              <div class="pagination-btns">
                <?php for ($pg=1;$pg<=$totalPages;$pg++): ?>
                  <a href="?page=<?= $pg ?>&status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>"
                     class="pg-btn <?= $pg===$page ? 'active' : '' ?>"><?= $pg ?></a>
                <?php endfor; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>