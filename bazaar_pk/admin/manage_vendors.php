<?php
// admin/manage_vendors.php
// Admin approves, bans, views all vendor accounts

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'vendors';
$flash       = '';
$flashType   = '';

// ── Actions ──
if (isset($_GET['approve'])) {
    $vid = (int)$_GET['approve'];
    try {
        $conn->prepare("UPDATE vendors SET status='approved' WHERE id=?")->execute([$vid]);
        $flash = "Vendor approved successfully!"; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error approving vendor."; $flashType = 'error'; }
}
if (isset($_GET['ban'])) {
    $vid = (int)$_GET['ban'];
    try {
        $conn->prepare("UPDATE vendors SET status='banned' WHERE id=?")->execute([$vid]);
        // Also deactivate all their products
        $conn->prepare("UPDATE products SET is_active=0 WHERE vendor_id=?")->execute([$vid]);
        $flash = "Vendor banned and products deactivated."; $flashType = 'warning';
    } catch (PDOException $e) { $flash = "Error banning vendor."; $flashType = 'error'; }
}
if (isset($_GET['unban'])) {
    $vid = (int)$_GET['unban'];
    try {
        $conn->prepare("UPDATE vendors SET status='approved' WHERE id=?")->execute([$vid]);
        $flash = "Vendor unbanned."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}
if (isset($_GET['delete'])) {
    $vid = (int)$_GET['delete'];
    try {
        // Deactivate all products first
        $conn->prepare("UPDATE products SET is_active=0 WHERE vendor_id=?")->execute([$vid]);
        $conn->prepare("DELETE FROM vendors WHERE id=?")->execute([$vid]);
        $flash = "Vendor deleted."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error deleting vendor."; $flashType = 'error'; }
}

// ── Filters ──
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

// ── Fetch vendors ──
try {
    $sql    = "SELECT v.*, u.full_name, u.email, u.created_at AS user_joined,
                      COUNT(DISTINCT p.id) AS product_count
               FROM vendors v
               JOIN users u ON v.user_id = u.id
               LEFT JOIN products p ON p.vendor_id = v.id
               WHERE 1=1";
    $params = [];

    if ($filterStatus !== '') { $sql .= " AND v.status=?"; $params[] = $filterStatus; }
    if ($search !== '')       { $sql .= " AND (v.store_name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql .= " GROUP BY v.id, u.full_name, u.email, u.created_at";

    $cntSql  = "SELECT COUNT(*) FROM ($sql) AS sub";
    $cntStmt = $conn->prepare($cntSql);
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $sql .= " ORDER BY v.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $vendors = $stmt->fetchAll();
} catch (PDOException $e) {
    $vendors = []; $totalCount = 0; $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Vendors — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left"><div class="ah-title">Manage vendors</div></div>
      <div class="ah-right">
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

      <!-- Status tabs -->
      <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'banned' => 'Banned'] as $val => $lbl): ?>
          <a href="manage_vendors.php?status=<?= $val ?>&q=<?= urlencode($search) ?>"
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
        <input class="af-input" type="text" name="q" placeholder="Search store name or email..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="af-btn af-btn-primary">Search</button>
        <a href="manage_vendors.php" class="af-btn af-btn-ghost" style="text-decoration:none;display:flex;align-items:center;">Reset</a>
      </form>

      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;"><?= $totalCount ?> vendor(s) found</div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Store</th>
              <th>Owner</th>
              <th>Email</th>
              <th>Products</th>
              <th>Rating</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($vendors)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--txt3);padding:28px;">No vendors found</td></tr>
            <?php else: ?>
              <?php foreach ($vendors as $v): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div class="tbl-avatar" style="background:var(--teal-dim);color:var(--teal);">
                        <?php if (!empty($v['logo'])): ?>
                          <img src="../uploads/vendors/<?= htmlspecialchars($v['logo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                          <?= strtoupper(substr($v['store_name'],0,1)) ?>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($v['store_name']) ?></div>
                        <div style="font-size:10px;color:var(--txt3);">ID: #<?= $v['id'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($v['full_name']) ?></td>
                  <td style="color:var(--txt2);font-size:11px;"><?= htmlspecialchars($v['email']) ?></td>
                  <td style="text-align:center;"><?= $v['product_count'] ?></td>
                  <td style="color:var(--amber);"><?= number_format($v['rating'],1) ?> &#11088;</td>
                  <td><span class="status-badge sb-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span></td>
                  <td style="color:var(--txt2);font-size:11px;"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
                  <td>
                    <div class="tbl-actions">
                      <?php if ($v['status'] === 'pending'): ?>
                        <a href="manage_vendors.php?approve=<?= $v['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn approve">Approve</a>
                        <a href="manage_vendors.php?ban=<?= $v['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn ban">Reject</a>
                      <?php elseif ($v['status'] === 'approved'): ?>
                        <a href="manage_vendors.php?ban=<?= $v['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn ban"
                           onclick="return confirm('Ban this vendor?')">Ban</a>
                      <?php elseif ($v['status'] === 'banned'): ?>
                        <a href="manage_vendors.php?unban=<?= $v['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn approve">Unban</a>
                      <?php endif; ?>
                      <a href="manage_vendors.php?delete=<?= $v['id'] ?>" class="tbl-btn delete"
                         onclick="return confirm('Delete this vendor permanently?')">Delete</a>
                    </div>
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
              <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                <a href="?page=<?= $pg ?>&status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>"
                   class="pg-btn <?= $pg===$page ? 'active' : '' ?>"><?= $pg ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>