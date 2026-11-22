<?php
// vendor/orders.php
// Vendor sees and manages all orders containing their products

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'orders';

try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }
if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

// ── Handle ship action ──
if (isset($_GET['action']) && $_GET['action'] === 'ship' && isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    try {
        $conn->prepare("
            UPDATE orders SET status = 'shipped'
            WHERE id = ? AND status = 'pending'
        ")->execute([$orderId]);
    } catch (PDOException $e) {}
    header("Location: orders.php?shipped=1"); exit();
}

// ── Filters ──
$filterStatus = $_GET['status'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;
$offset       = ($page - 1) * $perPage;

// ── Fetch orders ──
try {
    $sql = "
        SELECT o.id, o.status, o.created_at, o.address,
               u.full_name AS customer_name, u.email AS customer_email,
               SUM(oi.price_at_purchase * oi.quantity) AS order_total,
               COUNT(oi.id) AS item_count,
               GROUP_CONCAT(CONCAT(p.title, ' x', oi.quantity) SEPARATOR ', ') AS items_summary
        FROM orders o
        JOIN order_items oi ON o.id  = oi.order_id
        JOIN products    p  ON oi.product_id = p.id
        JOIN users       u  ON o.customer_id = u.id
        WHERE oi.vendor_id = ?
    ";
    $params = [$vendorId];

    if ($filterStatus !== '') {
        $sql .= " AND o.status = ?";
        $params[] = $filterStatus;
    }

    $sql .= " GROUP BY o.id, o.status, o.created_at, o.address, u.full_name, u.email";

    // Count
    $cntStmt = $conn->prepare("SELECT COUNT(*) FROM ($sql) AS sub");
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $sql .= " ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";
    $oStmt = $conn->prepare($sql);
    $oStmt->execute($params);
    $orders = $oStmt->fetchAll();

} catch (PDOException $e) {
    $orders     = [];
    $totalCount = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders — Bazaar.pk Vendor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>

<div class="vendor-wrap">
  <?php include 'sidebar.php'; ?>

  <div class="vendor-main">

    <div class="vendor-header">
      <div class="vh-title">Orders</div>
      <div class="vh-right">
        <div class="vh-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">

      <?php if (isset($_GET['shipped'])): ?>
        <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--green);">
          &#10003; Order marked as shipped!
        </div>
      <?php endif; ?>

      <!-- Status filter tabs -->
      <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        <?php
        $statuses = ['' => 'All', 'pending' => 'Pending', 'processing' => 'Processing',
                     'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
        foreach ($statuses as $val => $label):
        ?>
          <a href="orders.php?status=<?= $val ?>"
             style="padding:6px 14px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;
                    border:1px solid <?= $filterStatus === $val ? 'var(--teal)' : 'var(--border)' ?>;
                    color:<?= $filterStatus === $val ? 'var(--teal)' : 'var(--txt2)' ?>;
                    background:<?= $filterStatus === $val ? 'var(--teal-dim)' : 'transparent' ?>;">
            <?= $label ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div style="font-size:12px;color:var(--txt2);margin-bottom:14px;">
        <?= $totalCount ?> order(s) found
      </div>

      <?php if (empty($orders)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:48px;text-align:center;">
          <div style="font-size:40px;margin-bottom:14px;opacity:0.4;">&#128230;</div>
          <div style="font-size:14px;color:var(--txt);margin-bottom:8px;">No orders yet</div>
          <div style="font-size:12px;color:var(--txt2);">
            Orders will appear here when customers purchase your products.
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($orders as $order): ?>
          <div class="order-card">
            <div class="order-header">
              <div>
                <div class="order-id">
                  Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>
                </div>
                <div class="order-date">
                  <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
                </div>
              </div>
              <span class="order-status <?= $order['status'] ?>">
                <?= ucfirst($order['status']) ?>
              </span>
            </div>

            <div class="order-body">
              <div class="order-item">
                <div class="order-item-label">Customer</div>
                <div class="order-item-value"><?= htmlspecialchars($order['customer_name']) ?></div>
                <div style="font-size:10px;color:var(--txt3);"><?= htmlspecialchars($order['customer_email']) ?></div>
              </div>
              <div class="order-item">
                <div class="order-item-label">Order total</div>
                <div class="order-item-value" style="color:var(--teal);font-size:15px;font-weight:700;">
                  Rs <?= number_format($order['order_total']) ?>
                </div>
                <div style="font-size:10px;color:var(--txt3);"><?= $order['item_count'] ?> item(s)</div>
              </div>
              <div class="order-item">
                <div class="order-item-label">Delivery address</div>
                <div class="order-item-value" style="font-size:11px;">
                  <?= htmlspecialchars(substr($order['address'] ?? '—', 0, 60)) ?>
                </div>
              </div>
              <div class="order-item">
                <div class="order-item-label">Products</div>
                <div class="order-item-value" style="font-size:11px;color:var(--txt2);">
                  <?= htmlspecialchars(substr($order['items_summary'], 0, 80)) ?>
                  <?= strlen($order['items_summary']) > 80 ? '...' : '' ?>
                </div>
              </div>
            </div>

            <div class="order-actions">
              <?php if ($order['status'] === 'pending'): ?>
                <a href="orders.php?action=ship&id=<?= $order['id'] ?>"
                   class="order-btn ship"
                   onclick="return confirm('Mark this order as shipped?')">
                  Mark as shipped
                </a>
              <?php endif; ?>
              <span class="order-btn" style="cursor:default;">
                #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div style="display:flex;justify-content:center;gap:6px;margin-top:16px;">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
              <a href="?page=<?= $pg ?>&status=<?= urlencode($filterStatus) ?>"
                 style="padding:6px 12px;border:1px solid <?= $pg === $page ? 'var(--teal)' : 'var(--border)' ?>;border-radius:5px;font-size:12px;color:<?= $pg === $page ? 'var(--teal)' : 'var(--txt2)' ?>;text-decoration:none;background:<?= $pg === $page ? 'var(--teal-dim)' : 'transparent' ?>;">
                <?= $pg ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>