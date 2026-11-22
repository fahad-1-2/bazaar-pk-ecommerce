<?php
// vendor/edit_product.php
// Vendors edit their existing product listings

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'my_products';

try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id=? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }

if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) { header("Location: my_products.php"); exit(); }

// Fetch product — verify it belongs to this vendor
try {
    $pStmt = $conn->prepare("SELECT * FROM products WHERE id=? AND vendor_id=? LIMIT 1");
    $pStmt->execute([$productId, $vendorId]);
    $product = $pStmt->fetch();
} catch (PDOException $e) { $product = null; }

if (!$product) { header("Location: my_products.php"); exit(); }

// Fetch existing variants
try {
    $vStmt2 = $conn->prepare("SELECT * FROM product_variants WHERE product_id=? ORDER BY variant_type");
    $vStmt2->execute([$productId]);
    $existingVariants = $vStmt2->fetchAll();
} catch (PDOException $e) { $existingVariants = []; }

// Parse existing specs
$existingSpecs = [];
if (!empty($product['specs'])) {
    $existingSpecs = json_decode($product['specs'], true) ?? [];
}

$error = ''; $success = '';

// ── Handle update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $oldPrice    = (float)($_POST['old_price'] ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $category    = trim($_POST['category']    ?? '');
    $isActive    = isset($_POST['is_active'])   ? 1 : 0;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
    $isNew       = isset($_POST['is_new'])      ? 1 : 0;
    $isHot       = isset($_POST['is_hot'])      ? 1 : 0;

    // Specs
    $specKeys   = $_POST['spec_key']   ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $specs = [];
    foreach ($specKeys as $i => $key) {
        $key = trim($key); $val = trim($specValues[$i] ?? '');
        if ($key && $val) $specs[$key] = $val;
    }
    $specsJson = !empty($specs) ? json_encode($specs) : null;

    if (empty($title))    { $error = "Title is required."; }
    elseif ($price <= 0)  { $error = "Price must be greater than 0."; }
    else {
        try {
            // Handle new image uploads
            $mainImage  = $product['image'];
            $imagesList = array_filter(explode(',', $product['images'] ?? ''));
            $uploadDir  = '../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            if (!empty($_FILES['images']['name'][0])) {
                $allowed = ['jpg','jpeg','png','webp'];
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (empty($_FILES['images']['name'][$i])) continue;
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    $filename = uniqid('prod_') . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $imagesList[] = $filename;
                        if ($mainImage === null) $mainImage = $filename;
                    }
                }
            }

            // Update product
            $conn->prepare("
                UPDATE products SET
                    title=?, description=?, price=?, old_price=?,
                    stock=?, category=?, image=?, images=?, specs=?,
                    is_active=?, is_featured=?, is_new=?, is_hot=?
                WHERE id=? AND vendor_id=?
            ")->execute([
                $title, $description, $price,
                $oldPrice > 0 ? $oldPrice : null,
                $stock, $category, $mainImage,
                implode(',', $imagesList),
                $specsJson, $isActive, $isFeatured, $isNew, $isHot,
                $productId, $vendorId
            ]);

            // Delete old variants and re-insert
            $conn->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$productId]);

            $variantTypes  = $_POST['variant_type']  ?? [];
            $variantValues = $_POST['variant_value'] ?? [];
            $variantPrices = $_POST['variant_price'] ?? [];
            $variantStocks = $_POST['variant_stock'] ?? [];
            foreach ($variantTypes as $i => $type) {
                $type = trim($type); $value = trim($variantValues[$i] ?? '');
                if (empty($type) || empty($value)) continue;
                $conn->prepare("
                    INSERT INTO product_variants (product_id, variant_type, variant_value, price_adjust, stock)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$productId, $type, $value, (float)($variantPrices[$i]??0), (int)($variantStocks[$i]??0)]);
            }

            $success = "Product updated successfully!";
            // Refresh product data
            $pStmt->execute([$productId, $vendorId]);
            $product = $pStmt->fetch();

        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
        }
    }
}

$categories = ['mobiles'=>'Mobiles & Tablets','laptops'=>'Laptops','electronics'=>'Electronics',
                'fashion'=>'Fashion','shoes'=>'Shoes','home'=>'Home & Living','sports'=>'Sports',
                'groceries'=>'Groceries','books'=>'Books','automotive'=>'Automotive','beauty'=>'Beauty','watches'=>'Watches'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Product — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>
<div class="vendor-wrap">
  <?php include 'sidebar.php'; ?>
  <div class="vendor-main">

    <div class="vendor-header">
      <div class="vh-title">Edit product</div>
      <div class="vh-right">
        <a href="my_products.php" style="font-size:12px;color:var(--txt2);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:6px;">← Back</a>
        <a href="../product/product.php?id=<?= $productId ?>" target="_blank" style="font-size:12px;color:var(--teal);text-decoration:none;padding:6px 14px;border:1px solid var(--border2);border-radius:6px;">View live →</a>
        <div class="vh-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">

      <?php if ($error):   ?><div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--red);">&#10007; <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--green);">&#10003; <?= htmlspecialchars($success) ?></div><?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">

          <div>
            <!-- Basic info -->
            <div class="form-section">
              <div class="form-section-title">Basic information</div>
              <div class="form-group">
                <label class="form-label">Title <span style="color:var(--red);">*</span></label>
                <input class="form-input" type="text" name="title" value="<?= htmlspecialchars($product['title']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:120px;"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
              </div>
              <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Category <span style="color:var(--red);">*</span></label>
                  <select class="form-select" name="category" required>
                    <?php foreach ($categories as $val => $lbl): ?>
                      <option value="<?= $val ?>" <?= $product['category']===$val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Stock</label>
                  <input class="form-input" type="number" name="stock" min="0" value="<?= $product['stock'] ?>">
                </div>
              </div>
            </div>

            <!-- Pricing -->
            <div class="form-section">
              <div class="form-section-title">Pricing</div>
              <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Price (Rs) <span style="color:var(--red);">*</span></label>
                  <input class="form-input" type="number" name="price" step="0.01" value="<?= $product['price'] ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Original price (Rs)</label>
                  <input class="form-input" type="number" name="old_price" step="0.01" value="<?= $product['old_price'] ?? '' ?>">
                </div>
              </div>
            </div>

            <!-- Add more images -->
            <div class="form-section">
              <div class="form-section-title">Add more images</div>
              <?php if (!empty($product['image'])): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                  <?php
                  $imgs = array_filter(array_merge([$product['image']], explode(',', $product['images']??'')));
                  foreach (array_unique($imgs) as $img):
                  ?>
                    <div style="width:70px;height:70px;border-radius:7px;border:1px solid var(--border);overflow:hidden;background:var(--bg3);">
                      <img src="../uploads/products/<?= htmlspecialchars(trim($img)) ?>" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="upload-area" onclick="document.getElementById('newImages').click()">
                <div class="upload-icon">&#128247;</div>
                <div class="upload-text">Click to add more images</div>
                <div class="upload-sub">New images will be added alongside existing ones</div>
              </div>
              <input type="file" id="newImages" name="images[]" multiple accept="image/*" style="display:none;" onchange="previewNew(this)">
              <div id="newPreview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
            </div>

            <!-- Specs -->
            <div class="form-section">
              <div class="form-section-title">Specifications</div>
              <div id="specsContainer">
                <?php foreach ($existingSpecs as $key => $val): ?>
                  <div class="form-row" style="margin-bottom:8px;" id="specRow_<?= md5($key) ?>">
                    <div class="form-group" style="margin-bottom:0;">
                      <input class="form-input" type="text" name="spec_key[]" value="<?= htmlspecialchars($key) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;display:flex;gap:6px;">
                      <input class="form-input" type="text" name="spec_value[]" value="<?= htmlspecialchars($val) ?>" style="flex:1;">
                      <button type="button" onclick="this.closest('.form-row').remove()"
                              style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;flex-shrink:0;">×</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn-secondary" style="font-size:11px;padding:7px 14px;" onclick="addSpec()">+ Add spec</button>
            </div>

            <!-- Variants -->
            <div class="form-section">
              <div class="form-section-title">Variants</div>
              <div id="variantsContainer">
                <?php foreach ($existingVariants as $v): ?>
                  <div class="variant-group">
                    <div style="display:grid;grid-template-columns:1fr 1fr 80px 80px auto;gap:8px;align-items:center;">
                      <input class="form-input" type="text" name="variant_type[]" value="<?= htmlspecialchars($v['variant_type']) ?>" placeholder="Type">
                      <input class="form-input" type="text" name="variant_value[]" value="<?= htmlspecialchars($v['variant_value']) ?>" placeholder="Value">
                      <input class="form-input" type="number" name="variant_price[]" value="<?= $v['price_adjust'] ?>" placeholder="+/-Rs">
                      <input class="form-input" type="number" name="variant_stock[]" value="<?= $v['stock'] ?>" placeholder="Stock">
                      <button type="button" onclick="this.closest('.variant-group').remove()"
                              style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;">×</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn-secondary" style="font-size:11px;padding:7px 14px;" onclick="addVariant()">+ Add variant</button>
            </div>
          </div>

          <!-- Right column -->
          <div style="position:sticky;top:80px;">
            <div class="form-section">
              <div class="form-section-title">Publishing</div>
              <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <?php
                $checks = ['is_active'=>'Active — visible to customers','is_featured'=>'Featured product','is_new'=>'New arrival','is_hot'=>'Hot / trending'];
                foreach ($checks as $name => $label):
                ?>
                  <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;color:var(--txt);">
                    <input type="checkbox" name="<?= $name ?>" value="1"
                           <?= $product[$name] ? 'checked' : '' ?>
                           style="accent-color:var(--teal);width:15px;height:15px;">
                    <?= $label ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="form-actions" style="flex-direction:column;">
                <button type="submit" class="btn-primary" style="width:100%;">Save changes</button>
                <a href="my_products.php" class="btn-secondary" style="width:100%;text-align:center;text-decoration:none;padding:10px 24px;">Cancel</a>
              </div>
            </div>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewNew(input) {
    const preview = document.getElementById('newPreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.style.cssText = 'width:70px;height:70px;border-radius:7px;border:1px solid var(--teal);overflow:hidden;';
            div.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

let specCount = 100;
function addSpec() {
    specCount++;
    const row = document.createElement('div');
    row.className = 'form-row'; row.style.marginBottom = '8px'; row.id = 'spec_' + specCount;
    row.innerHTML = `<div class="form-group" style="margin-bottom:0;"><input class="form-input" type="text" name="spec_key[]" placeholder="Key"></div>
        <div class="form-group" style="margin-bottom:0;display:flex;gap:6px;">
          <input class="form-input" type="text" name="spec_value[]" placeholder="Value" style="flex:1;">
          <button type="button" onclick="document.getElementById('spec_${specCount}').remove()"
                  style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;flex-shrink:0;">×</button>
        </div>`;
    document.getElementById('specsContainer').appendChild(row);
}

let variantCount = 100;
function addVariant() {
    variantCount++;
    const row = document.createElement('div');
    row.className = 'variant-group'; row.id = 'variant_' + variantCount;
    row.innerHTML = `<div style="display:grid;grid-template-columns:1fr 1fr 80px 80px auto;gap:8px;align-items:center;">
        <input class="form-input" type="text" name="variant_type[]" placeholder="Type">
        <input class="form-input" type="text" name="variant_value[]" placeholder="Value">
        <input class="form-input" type="number" name="variant_price[]" placeholder="+/-Rs">
        <input class="form-input" type="number" name="variant_stock[]" placeholder="Stock">
        <button type="button" onclick="document.getElementById('variant_${variantCount}').remove()"
                style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;">×</button>
      </div>`;
    document.getElementById('variantsContainer').appendChild(row);
}
</script>
</body>
</html>