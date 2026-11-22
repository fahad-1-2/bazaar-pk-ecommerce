<?php
// admin/manage_products.php
// Admin moderates all product listings across all vendors

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userName    = $_SESSION['full_name'];
$currentPage = 'products';
$flash       = ''; $flashType = '';

// ── Actions ──
if (isset($_GET['activate'])) {
    $pid = (int)$_GET['activate'];
    try {
        $conn->prepare("UPDATE products SET is_active=1 WHERE id=?")->execute([$pid]);
        $flash = "Product activated."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}
if (isset($_GET['deactivate'])) {
    $pid = (int)$_GET['deactivate'];
    try {
        $conn->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$pid]);
        $flash = "Product deactivated."; $flashType = 'warning';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    try {
        $conn->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$pid]);
        $conn->prepare("DELETE FROM reviews WHERE product_id=?")->execute([$pid]);
        $conn->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
        $flash = "Product deleted permanently."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error deleting product."; $flashType = 'error'; }
}
if (isset($_GET['feature'])) {
    $pid = (int)$_GET['feature'];
    try {
        $conn->prepare("UPDATE products SET is_featured=NOT is_featured WHERE id=?")->execute([$pid]);
        $flash = "Featured status toggled."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}

// ── Filters ──
$filterCat    = $_GET['cat']    ?? '';
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page-1) * $perPage;

try {
    $sql    = "SELECT p.*, v.store_name FROM products p LEFT JOIN vendors v ON p.vendor_id=v.id WHERE 1=1";
    $params = [];

    if ($filterCat !== '')      { $sql .= " AND p.category=?";    $params[] = $filterCat; }
    if ($filterStatus === 'active')   { $sql .= " AND p.is_active=1"; }
    elseif ($filterStatus === 'inactive') { $sql .= " AND p.is_active=0"; }
    if ($search !== '')         { $sql .= " AND (p.title LIKE ? OR v.store_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $cntStmt = $conn->prepare(str_replace("SELECT p.*, v.store_name","SELECT COUNT(*)",$sql));
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount/$perPage);

    $sql .= " ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = []; $totalCount = 0; $totalPages = 0;
}

$categories = ['mobiles','laptops','electronics','fashion','shoes','home','sports','groceries','books','automotive','beauty','watches'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Products — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left"><div class="ah-title">Manage products</div></div>
      <div class="ah-right">
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

      <!-- Status tabs -->
      <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $lbl): ?>
          <a href="manage_products.php?status=<?= $val ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($filterCat) ?>"
             style="padding:6px 14px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;
                    border:1px solid <?= $filterStatus===$val ? 'var(--teal)' : 'var(--border)' ?>;
                    color:<?= $filterStatus===$val ? 'var(--teal)' : 'var(--txt2)' ?>;
                    background:<?= $filterStatus===$val ? 'var(--teal-dim)' : 'transparent' ?>;">
            <?= $lbl ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Search & filters -->
      <form method="GET" class="admin-filters">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <input class="af-input" type="text" name="q" placeholder="Search title or vendor..." value="<?= htmlspecialchars($search) ?>">
        <select class="af-select" name="cat">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= $filterCat===$cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="af-btn af-btn-primary">Search</button>
        <a href="manage_products.php" class="af-btn af-btn-ghost" style="text-decoration:none;display:flex;align-items:center;">Reset</a>
      </form>

      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;"><?= $totalCount ?> product(s) found</div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Vendor</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Sales</th>
              <th>Rating</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--txt3);padding:28px;">No products found</td></tr>
            <?php else: ?>
              <?php foreach ($products as $p):
                $status = $p['is_active'] ? 'active' : 'inactive';
              ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;min-width:180px;">
                      <div style="width:36px;height:36px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        <?php if (!empty($p['image'])): ?>
                          <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                          <span style="font-size:14px;">&#128722;</span>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div style="font-size:11px;font-weight:500;color:var(--txt);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                          <?= htmlspecialchars($p['title']) ?>
                        </div>
                        <?php if ($p['is_featured']): ?>
                          <span style="font-size:9px;color:var(--amber);">&#11088; Featured</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td style="font-size:11px;color:var(--teal);"><?= htmlspecialchars($p['store_name'] ?? '—') ?></td>
                  <td style="font-size:11px;color:var(--txt2);"><?= ucfirst($p['category'] ?? '—') ?></td>
                  <td style="font-weight:600;color:var(--teal);">Rs <?= number_format($p['price']) ?></td>
                  <td style="font-size:11px;color:<?= $p['stock']==0 ? 'var(--red)' : ($p['stock']<=5 ? 'var(--amber)' : 'var(--txt2)') ?>;">
                    <?= $p['stock'] ?>
                  </td>
                  <td style="font-size:11px;color:var(--txt2);"><?= number_format($p['sales_count']??0) ?></td>
                  <td style="font-size:11px;color:var(--amber);"><?= number_format($p['rating']??0,1) ?> &#11088;</td>
                  <td><span class="status-badge sb-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                  <td>
                    <div class="tbl-actions">
                      <a href="../product/product.php?id=<?= $p['id'] ?>" class="tbl-btn" target="_blank">View</a>
                      <?php if ($p['is_active']): ?>
                        <a href="manage_products.php?deactivate=<?= $p['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn ban">Deactivate</a>
                      <?php else: ?>
                        <a href="manage_products.php?activate=<?= $p['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn approve">Activate</a>
                      <?php endif; ?>
                      <a href="manage_products.php?feature=<?= $p['id'] ?>&status=<?= urlencode($filterStatus) ?>" class="tbl-btn">
                        <?= $p['is_featured'] ? 'Unfeature' : 'Feature' ?>
                      </a>
                      <a href="manage_products.php?delete=<?= $p['id'] ?>&status=<?= urlencode($filterStatus) ?>"
                         class="tbl-btn delete" onclick="return confirm('Delete this product permanently?')">Delete</a>
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
              <?php for ($pg=1;$pg<=$totalPages;$pg++): ?>
                <a href="?page=<?= $pg ?>&status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($filterCat) ?>"
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