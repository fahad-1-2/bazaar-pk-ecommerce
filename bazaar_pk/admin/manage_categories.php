<?php
// admin/manage_categories.php
// Admin adds, edits, reorders product categories

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userName    = $_SESSION['full_name'];
$currentPage = 'categories';
$flash       = ''; $flashType = '';

// Default categories always shown even without DB table
$defaultCategories = [
    ['slug'=>'mobiles',    'name'=>'Mobiles & Tablets', 'icon'=>'📱'],
    ['slug'=>'laptops',    'name'=>'Laptops',           'icon'=>'💻'],
    ['slug'=>'electronics','name'=>'Electronics',       'icon'=>'🔌'],
    ['slug'=>'fashion',    'name'=>'Fashion',           'icon'=>'👗'],
    ['slug'=>'shoes',      'name'=>'Shoes',             'icon'=>'👟'],
    ['slug'=>'home',       'name'=>'Home & Living',     'icon'=>'🏠'],
    ['slug'=>'sports',     'name'=>'Sports',            'icon'=>'⚽'],
    ['slug'=>'groceries',  'name'=>'Groceries',         'icon'=>'🛒'],
    ['slug'=>'books',      'name'=>'Books',             'icon'=>'📚'],
    ['slug'=>'automotive', 'name'=>'Automotive',        'icon'=>'🚗'],
    ['slug'=>'beauty',     'name'=>'Beauty',            'icon'=>'💄'],
    ['slug'=>'watches',    'name'=>'Watches',           'icon'=>'⌚'],
];

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9_]/','',$_POST['slug']??'')));
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');

        if (empty($slug) || empty($name)) {
            $flash = "Slug and name are required."; $flashType = 'error';
        } else {
            try {
                $conn->prepare("
                    INSERT INTO categories (slug, name, icon, sort_order)
                    VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM categories AS c2))
                ")->execute([$slug, $name, $icon]);
                $flash = "Category added."; $flashType = 'success';
            } catch (PDOException $e) {
                $flash = "Error — slug may already exist."; $flashType = 'error';
            }
        }
    }

    if ($action === 'delete') {
        $cid = (int)($_POST['cat_id'] ?? 0);
        try {
            $conn->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
            $flash = "Category deleted."; $flashType = 'success';
        } catch (PDOException $e) { $flash = "Error deleting."; $flashType = 'error'; }
    }

    header("Location: manage_categories.php"); exit();
}

// ── Fetch DB categories ──
$dbCategories = [];
try {
    $dbCategories = $conn->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();
} catch (PDOException $e) { $dbCategories = []; }

// ── Product count per category ──
$catCounts = [];
try {
    $ccStmt = $conn->query("SELECT category, COUNT(*) AS cnt FROM products WHERE is_active=1 GROUP BY category");
    foreach ($ccStmt->fetchAll() as $row) {
        $catCounts[$row['category']] = $row['cnt'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Categories — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left"><div class="ah-title">Manage categories</div></div>
      <div class="ah-right">
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">

        <!-- Category list -->
        <div>
          <div class="admin-section-header">
            <div class="ash-title">All categories</div>
            <div style="font-size:11px;color:var(--txt3);"><?= count($defaultCategories) ?> built-in + <?= count($dbCategories) ?> custom</div>
          </div>

          <!-- Built-in categories -->
          <div style="font-size:11px;font-weight:600;color:var(--txt3);letter-spacing:0.5px;text-transform:uppercase;margin-bottom:8px;">Built-in categories</div>
          <div class="admin-table-wrap" style="margin-bottom:16px;">
            <table class="admin-table">
              <thead>
                <tr><th>Category</th><th>Slug</th><th>Products</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($defaultCategories as $cat): ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:18px;"><?= $cat['icon'] ?></span>
                        <span style="font-size:12px;font-weight:500;color:var(--txt);"><?= $cat['name'] ?></span>
                      </div>
                    </td>
                    <td><code style="font-size:11px;color:var(--teal);"><?= $cat['slug'] ?></code></td>
                    <td style="text-align:center;font-size:12px;color:var(--txt2);"><?= $catCounts[$cat['slug']] ?? 0 ?></td>
                    <td><span class="status-badge sb-active">Built-in</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Custom categories from DB -->
          <?php if (!empty($dbCategories)): ?>
            <div style="font-size:11px;font-weight:600;color:var(--txt3);letter-spacing:0.5px;text-transform:uppercase;margin-bottom:8px;">Custom categories</div>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr><th>Category</th><th>Slug</th><th>Products</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($dbCategories as $cat): ?>
                    <tr>
                      <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                          <span style="font-size:18px;"><?= htmlspecialchars($cat['icon'] ?? '📦') ?></span>
                          <span style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($cat['name']) ?></span>
                        </div>
                      </td>
                      <td><code style="font-size:11px;color:var(--teal);"><?= htmlspecialchars($cat['slug']) ?></code></td>
                      <td style="text-align:center;font-size:12px;color:var(--txt2);"><?= $catCounts[$cat['slug']] ?? 0 ?></td>
                      <td>
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                          <button type="submit" class="tbl-btn delete"
                                  onclick="return confirm('Delete this category?')">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Add category form -->
        <div style="position:sticky;top:80px;">
          <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px;">
            <div style="font-size:14px;font-weight:600;color:var(--txt);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);">
              Add custom category
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="add">

              <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:500;color:var(--txt);margin-bottom:5px;">
                  Category name <span style="color:var(--red);">*</span>
                </label>
                <input class="af-input" type="text" name="name" placeholder="e.g. Baby Products" style="width:100%;" required>
              </div>

              <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:500;color:var(--txt);margin-bottom:5px;">
                  Slug <span style="color:var(--red);">*</span>
                  <span style="font-size:10px;color:var(--txt3);font-weight:normal;"> — URL key, lowercase, no spaces</span>
                </label>
                <input class="af-input" type="text" name="slug" placeholder="e.g. baby_products" style="width:100%;" required>
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:500;color:var(--txt);margin-bottom:5px;">
                  Icon emoji <span style="font-size:10px;color:var(--txt3);font-weight:normal;"> — Optional</span>
                </label>
                <input class="af-input" type="text" name="icon" placeholder="e.g. 🍼" style="width:100%;">
              </div>

              <button type="submit" class="af-btn af-btn-primary" style="width:100%;height:38px;">
                Add category
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>