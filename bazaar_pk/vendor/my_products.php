<?php
// vendor/my_products.php
// Vendors manage all their product listings — edit, delete, toggle active

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'my_products';

// Fetch vendor
try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }

if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

// ── Handle toggle active ──
if (isset($_GET['toggle'])) {
    $pid = (int)$_GET['toggle'];
    try {
        $conn->prepare("
            UPDATE products SET is_active = NOT is_active
            WHERE id = ? AND vendor_id = ?
        ")->execute([$pid, $vendorId]);
    } catch (PDOException $e) {}
    header("Location: my_products.php"); exit();
}

// ── Handle delete ──
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    try {
        // Fetch image to delete file
        $imgStmt = $conn->prepare("SELECT image, images FROM products WHERE id = ? AND vendor_id = ?");
        $imgStmt->execute([$pid, $vendorId]);
        $imgRow = $imgStmt->fetch();
        if ($imgRow) {
            // Delete images from uploads
            $allImgs = array_filter(array_merge(
                [$imgRow['image']],
                explode(',', $imgRow['images'] ?? '')
            ));
            foreach ($allImgs as $img) {
                $path = '../uploads/products/' . basename($img);
                if (file_exists($path)) unlink($path);
            }
            // Delete variants
            $conn->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$pid]);
            // Delete product
            $conn->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?")->execute([$pid, $vendorId]);
        }
    } catch (PDOException $e) {}
    header("Location: my_products.php?deleted=1"); exit();
}

// ── Filters ──
$filterCat    = $_GET['cat']    ?? '';
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

// ── Fetch products ──
try {
    $sql    = "SELECT * FROM products WHERE vendor_id = ?";
    $params = [$vendorId];

    if ($filterCat !== '') {
        $sql .= " AND category = ?";
        $params[] = $filterCat;
    }
    if ($filterStatus === 'active') {
        $sql .= " AND is_active = 1";
    } elseif ($filterStatus === 'inactive') {
        $sql .= " AND is_active = 0";
    }
    if ($search !== '') {
        $sql .= " AND title LIKE ?";
        $params[] = "%$search%";
    }

    // Count total for pagination
    $countStmt = $conn->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $sql));
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $sql .= " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
    $pStmt = $conn->prepare($sql);
    $pStmt->execute($params);
    $products = $pStmt->fetchAll();

} catch (PDOException $e) {
    $products   = [];
    $totalCount = 0;
    $totalPages = 0;
}

$categories = [
    'mobiles','laptops','electronics','fashion','shoes',
    'home','sports','groceries','books','automotive','beauty','watches'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Products — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>

<div class="vendor-wrap">
  <?php include 'sidebar.php'; ?>

  <div class="vendor-main">

    <div class="vendor-header">
      <div class="vh-title">My products</div>
      <div class="vh-right">
        <a href="add_product.php" class="sh-action-btn">+ Add product</a>
        <div class="vh-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">

      <!-- Flash messages -->
      <?php if (isset($_GET['added'])): ?>
        <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--green);">
          &#10003; Product added successfully!
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?>
        <div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--red);">
          Product deleted.
        </div>
      <?php endif; ?>

      <!-- Search and filter bar -->
      <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
          <input class="form-input" type="text" name="q"
                 placeholder="Search products..."
                 value="<?= htmlspecialchars($search) ?>"
                 style="flex:1;">
          <select class="form-select" name="cat" style="width:140px;">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= $filterCat === $cat ? 'selected' : '' ?>>
                <?= ucfirst($cat) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select class="form-select" name="status" style="width:110px;">
            <option value="">All status</option>
            <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
          <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:12px;">Search</button>
          <a href="my_products.php" class="btn-secondary" style="padding:8px 14px;font-size:12px;text-decoration:none;">Reset</a>
        </form>
      </div>

      <!-- Summary row -->
      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;">
        Showing <strong style="color:var(--txt);"><?= count($products) ?></strong>
        of <strong style="color:var(--txt);"><?= $totalCount ?></strong> products
      </div>

      <!-- Products table -->
      <?php if (empty($products)): ?>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:48px;text-align:center;">
          <div style="font-size:40px;margin-bottom:14px;opacity:0.4;">&#128722;</div>
          <div style="font-size:14px;color:var(--txt);margin-bottom:8px;">No products found</div>
          <div style="font-size:12px;color:var(--txt2);margin-bottom:20px;">
            <?= !empty($search) ? "No results for \"$search\"" : "You haven't added any products yet." ?>
          </div>
          <a href="add_product.php" class="sh-action-btn">Add your first product</a>
        </div>
      <?php else: ?>
        <table class="products-table">
          <thead>
            <tr>
              <th>Product</th>
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
            <?php foreach ($products as $p):
              $discPct = (!empty($p['old_price']) && $p['old_price'] > $p['price'])
                ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100) : 0;
              $stockClass = $p['stock'] > 10 ? '' : ($p['stock'] > 0 ? 'low' : 'out');
              $status     = $p['is_active'] ? 'active' : 'inactive';
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;min-width:200px;">
                    <div class="pt-img">
                      <?php if (!empty($p['image'])): ?>
                        <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="">
                      <?php else: ?>
                        &#128722;
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="pt-name" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($p['title']) ?>
                      </div>
                      <div style="font-size:10px;color:var(--txt3);">ID: #<?= $p['id'] ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:11px;color:var(--txt2);"><?= ucfirst($p['category'] ?? '—') ?></td>
                <td>
                  <div class="pt-price">Rs <?= number_format($p['price']) ?></div>
                  <?php if ($discPct > 0): ?>
                    <div style="font-size:10px;color:var(--green);">-<?= $discPct ?>% off</div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="pt-stock <?= $stockClass ?>">
                    <?= $p['stock'] == 0 ? 'Out of stock' : $p['stock'] . ' units' ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--txt2);"><?= number_format($p['sales_count'] ?? 0) ?></td>
                <td style="font-size:12px;color:var(--amber);">
                  <?= number_format($p['rating'] ?? 0, 1) ?> &#11088;
                  <div style="font-size:10px;color:var(--txt3);">(<?= $p['review_count'] ?? 0 ?> reviews)</div>
                </td>
                <td><span class="pt-status <?= $status ?>"><?= ucfirst($status) ?></span></td>
                <td>
                  <div class="pt-actions">
                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="pt-btn">Edit</a>
                    <a href="../product/product.php?id=<?= $p['id'] ?>" class="pt-btn" target="_blank">View</a>
                    <a href="my_products.php?toggle=<?= $p['id'] ?>" class="pt-btn">
                      <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </a>
                    <a href="my_products.php?delete=<?= $p['id'] ?>" class="pt-btn delete"
                       onclick="return confirm('Delete this product permanently?')">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div style="display:flex;justify-content:center;gap:6px;margin-top:16px;">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
              <a href="?page=<?= $pg ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($filterCat) ?>&status=<?= $filterStatus ?>"
                 style="padding:6px 12px;border:1px solid <?= $pg === $page ? 'var(--teal)' : 'var(--border)' ?>;border-radius:5px;font-size:12px;color:<?= $pg === $page ? 'var(--teal)' : 'var(--txt2)' ?>;text-decoration:none;background:<?= $pg === $page ? 'var(--teal-dim)' : 'transparent' ?>;">
                <?= $pg ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div><!-- /vendor-content -->
  </div><!-- /vendor-main -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>