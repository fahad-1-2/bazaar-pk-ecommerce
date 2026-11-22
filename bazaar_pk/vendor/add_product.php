<?php
// vendor/add_product.php
// Vendors add new products with images, variants, specs

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];
$currentPage = 'add_product';

// Fetch vendor
try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }

if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

$error   = '';
$success = '';

// ── Handle product submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $oldPrice    = (float)($_POST['old_price'] ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $category    = trim($_POST['category']    ?? '');
    $isActive    = isset($_POST['is_active'])  ? 1 : 0;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
    $isNew       = isset($_POST['is_new'])     ? 1 : 0;
    $isHot       = isset($_POST['is_hot'])     ? 1 : 0;

    // Build specs JSON from dynamic fields
    $specKeys   = $_POST['spec_key']   ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $specs = [];
    foreach ($specKeys as $i => $key) {
        $key = trim($key);
        $val = trim($specValues[$i] ?? '');
        if ($key !== '' && $val !== '') {
            $specs[$key] = $val;
        }
    }
    $specsJson = !empty($specs) ? json_encode($specs) : null;

    // Validate
    if (empty($title)) {
        $error = "Product title is required.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } elseif ($stock < 0) {
        $error = "Stock cannot be negative.";
    } elseif (empty($category)) {
        $error = "Please select a category.";
    } else {
        try {
            // ── Handle image uploads ──
            $mainImage   = null;
            $imagesList  = [];
            $uploadDir   = '../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            if (!empty($_FILES['images']['name'][0])) {
                $allowed = ['jpg','jpeg','png','webp'];
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (empty($_FILES['images']['name'][$i])) continue;
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    if ($_FILES['images']['size'][$i] > 3 * 1024 * 1024) continue;

                    $filename = uniqid('prod_') . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $imagesList[] = $filename;
                        if ($mainImage === null) $mainImage = $filename;
                    }
                }
            }

            $imagesStr = implode(',', $imagesList);

            // ── Insert product ──
            $ins = $conn->prepare("
                INSERT INTO products
                    (vendor_id, title, description, price, old_price, stock,
                     category, image, images, specs, is_active, is_featured,
                     is_new, is_hot, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $vendorId, $title, $description, $price,
                $oldPrice > 0 ? $oldPrice : null,
                $stock, $category, $mainImage, $imagesStr,
                $specsJson, $isActive, $isFeatured, $isNew, $isHot
            ]);
            $newProductId = $conn->lastInsertId();

            // ── Insert variants ──
            $variantTypes  = $_POST['variant_type']  ?? [];
            $variantValues = $_POST['variant_value'] ?? [];
            $variantPrices = $_POST['variant_price'] ?? [];
            $variantStocks = $_POST['variant_stock'] ?? [];

            foreach ($variantTypes as $i => $type) {
                $type  = trim($type);
                $value = trim($variantValues[$i] ?? '');
                if (empty($type) || empty($value)) continue;

                $conn->prepare("
                    INSERT INTO product_variants
                        (product_id, variant_type, variant_value, price_adjust, stock)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $newProductId,
                    $type,
                    $value,
                    (float)($variantPrices[$i] ?? 0),
                    (int)($variantStocks[$i]   ?? 0)
                ]);
            }

            $success = "Product added successfully!";
            // Clear form by redirecting to my_products
            header("Location: my_products.php?added=1");
            exit();

        } catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
    }
}

$categories = [
    'mobiles'    => 'Mobiles & Tablets',
    'laptops'    => 'Laptops',
    'electronics'=> 'Electronics',
    'fashion'    => 'Fashion',
    'shoes'      => 'Shoes',
    'home'       => 'Home & Living',
    'sports'     => 'Sports',
    'groceries'  => 'Groceries',
    'books'      => 'Books',
    'automotive' => 'Automotive',
    'beauty'     => 'Beauty',
    'watches'    => 'Watches',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Product — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>

<div class="vendor-wrap">

  <?php include 'sidebar.php'; ?>

  <div class="vendor-main">

    <!-- Header -->
    <div class="vendor-header">
      <div class="vh-title">Add new product</div>
      <div class="vh-right">
        <a href="my_products.php" style="font-size:12px;color:var(--txt2);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:6px;">← Back to products</a>
        <div class="vh-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">

      <?php if ($error): ?>
        <div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--red);">
          &#10007; <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">

        <!-- ── Layout: 2 columns ── -->
        <div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">

          <!-- LEFT COLUMN -->
          <div>

            <!-- Basic info -->
            <div class="form-section">
              <div class="form-section-title">Basic information</div>

              <div class="form-group">
                <label class="form-label">
                  Product title <span style="color:var(--red);">*</span>
                </label>
                <input class="form-input" type="text" name="title"
                       placeholder="e.g. Samsung Galaxy A55 5G — 256GB Navy"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       required>
              </div>

              <div class="form-group">
                <label class="form-label">
                  Description
                  <span class="form-label-sub"> — Tell customers about this product</span>
                </label>
                <textarea class="form-textarea" name="description"
                          placeholder="Describe your product in detail..."
                          style="min-height:120px;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>

              <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">
                    Category <span style="color:var(--red);">*</span>
                  </label>
                  <select class="form-select" name="category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $val => $label): ?>
                      <option value="<?= $val ?>"
                        <?= (($_POST['category'] ?? '') === $val) ? 'selected' : '' ?>>
                        <?= $label ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">
                    Stock quantity <span style="color:var(--red);">*</span>
                  </label>
                  <input class="form-input" type="number" name="stock"
                         min="0" placeholder="0"
                         value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>"
                         required>
                </div>
              </div>
            </div><!-- /form-section -->


            <!-- Pricing -->
            <div class="form-section">
              <div class="form-section-title">Pricing</div>
              <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">
                    Selling price (Rs) <span style="color:var(--red);">*</span>
                  </label>
                  <input class="form-input" type="number" name="price"
                         min="1" step="0.01" placeholder="0.00"
                         value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                         required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">
                    Original price (Rs)
                    <span class="form-label-sub"> — Shown as strikethrough</span>
                  </label>
                  <input class="form-input" type="number" name="old_price"
                         min="0" step="0.01" placeholder="0.00 (optional)"
                         value="<?= htmlspecialchars($_POST['old_price'] ?? '') ?>">
                </div>
              </div>
              <div style="font-size:11px;color:var(--txt3);margin-top:10px;">
                If original price is higher than selling price, a discount % will automatically show on the product.
              </div>
            </div>


            <!-- Images -->
            <div class="form-section">
              <div class="form-section-title">Product images</div>
              <div class="upload-area" onclick="document.getElementById('imageInput').click()">
                <div class="upload-icon">&#128247;</div>
                <div class="upload-text">Click to upload product images</div>
                <div class="upload-sub">JPG, PNG, WebP — max 3MB each — first image becomes main image</div>
              </div>
              <input type="file" id="imageInput" name="images[]"
                     multiple accept="image/*"
                     style="display:none;"
                     onchange="previewImages(this)">
              <div class="image-preview" id="imagePreview"></div>
            </div>


            <!-- Specifications -->
            <div class="form-section">
              <div class="form-section-title">
                Specifications
                <span style="font-size:10px;color:var(--txt3);font-weight:normal;"> — Optional: Brand, RAM, Battery etc.</span>
              </div>
              <div id="specsContainer">
                <!-- Dynamic spec rows added by JS -->
              </div>
              <button type="button" class="btn-secondary" style="font-size:11px;padding:7px 14px;" onclick="addSpec()">
                + Add specification
              </button>
            </div>


            <!-- Variants -->
            <div class="form-section">
              <div class="form-section-title">
                Variants
                <span style="font-size:10px;color:var(--txt3);font-weight:normal;"> — Optional: Colors, sizes, storage</span>
              </div>
              <div id="variantsContainer">
                <!-- Dynamic variant rows added by JS -->
              </div>
              <button type="button" class="btn-secondary" style="font-size:11px;padding:7px 14px;" onclick="addVariant()">
                + Add variant
              </button>
            </div>

          </div><!-- /left col -->


          <!-- RIGHT COLUMN: status & submit -->
          <div style="position:sticky;top:80px;">

            <div class="form-section">
              <div class="form-section-title">Publishing</div>

              <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;color:var(--txt);">
                  <input type="checkbox" name="is_active" value="1"
                         <?= isset($_POST['is_active']) ? 'checked' : 'checked' ?>
                         style="accent-color:var(--teal);width:15px;height:15px;">
                  Active — visible to customers
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;color:var(--txt);">
                  <input type="checkbox" name="is_featured" value="1"
                         style="accent-color:var(--teal);width:15px;height:15px;">
                  Featured — shown at top of shop
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;color:var(--txt);">
                  <input type="checkbox" name="is_new" value="1"
                         style="accent-color:var(--teal);width:15px;height:15px;">
                  Mark as new arrival
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;color:var(--txt);">
                  <input type="checkbox" name="is_hot" value="1"
                         style="accent-color:var(--teal);width:15px;height:15px;">
                  Mark as hot / trending
                </label>
              </div>

              <div class="form-actions" style="flex-direction:column;">
                <button type="submit" class="btn-primary" style="width:100%;">
                  Publish product
                </button>
                <button type="button" class="btn-secondary" style="width:100%;"
                        onclick="window.location='my_products.php'">
                  Cancel
                </button>
              </div>

              <div style="font-size:10px;color:var(--txt3);text-align:center;margin-top:10px;line-height:1.6;">
                Products are reviewed and go live immediately if your store is approved.
              </div>
            </div>

            <!-- Preview card -->
            <div class="form-section" style="margin-top:0;">
              <div class="form-section-title">Live preview</div>
              <div style="background:var(--bg3);border-radius:8px;overflow:hidden;border:1px solid var(--border);">
                <div style="height:120px;background:var(--bg2);display:flex;align-items:center;justify-content:center;border-bottom:1px solid var(--border);" id="previewImg">
                  <span style="font-size:36px;opacity:0.3;">&#128247;</span>
                </div>
                <div style="padding:10px;">
                  <div style="font-size:10px;color:var(--teal);margin-bottom:3px;">
                    <?= htmlspecialchars($vendor['store_name']) ?>
                  </div>
                  <div id="previewTitle" style="font-size:12px;color:var(--txt);font-weight:500;margin-bottom:5px;">
                    Product title will appear here
                  </div>
                  <div id="previewPrice" style="font-size:14px;color:var(--teal);font-weight:700;">
                    Rs 0
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /right col -->
        </div><!-- /grid -->
      </form>

    </div><!-- /vendor-content -->
  </div><!-- /vendor-main -->
</div><!-- /vendor-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Image preview ──
function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'image-item';
            div.innerHTML = `<img src="${e.target.result}" alt="">
                             <button type="button" class="image-remove"
                                     onclick="this.parentNode.remove()">×</button>`;
            preview.appendChild(div);
            // Set first image as preview
            if (i === 0) {
                document.getElementById('previewImg').innerHTML =
                    `<img src="${e.target.result}" style="width:100%;height:120px;object-fit:cover;">`;
            }
        };
        reader.readAsDataURL(file);
    });
}

// ── Live preview updates ──
document.querySelector('[name="title"]')?.addEventListener('input', e => {
    document.getElementById('previewTitle').textContent = e.target.value || 'Product title will appear here';
});
document.querySelector('[name="price"]')?.addEventListener('input', e => {
    document.getElementById('previewPrice').textContent = 'Rs ' + (parseFloat(e.target.value) || 0).toLocaleString();
});

// ── Add spec row ──
let specCount = 0;
function addSpec() {
    specCount++;
    const row = document.createElement('div');
    row.className = 'form-row';
    row.style.marginBottom = '8px';
    row.id = 'spec_' + specCount;
    row.innerHTML = `
        <div class="form-group" style="margin-bottom:0;">
            <input class="form-input" type="text" name="spec_key[]"
                   placeholder="e.g. Brand, RAM, Battery">
        </div>
        <div class="form-group" style="margin-bottom:0;display:flex;gap:6px;">
            <input class="form-input" type="text" name="spec_value[]"
                   placeholder="e.g. Samsung, 8GB, 5000mAh" style="flex:1;">
            <button type="button" onclick="document.getElementById('spec_${specCount}').remove()"
                    style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;flex-shrink:0;">×</button>
        </div>`;
    document.getElementById('specsContainer').appendChild(row);
}

// ── Add variant row ──
let variantCount = 0;
function addVariant() {
    variantCount++;
    const row = document.createElement('div');
    row.className = 'variant-group';
    row.id = 'variant_' + variantCount;
    row.innerHTML = `
        <div class="variant-label">Variant ${variantCount}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 80px 80px auto;gap:8px;align-items:center;">
            <input class="form-input" type="text" name="variant_type[]" placeholder="Type (color/size)">
            <input class="form-input" type="text" name="variant_value[]" placeholder="Value (Red/XL)">
            <input class="form-input" type="number" name="variant_price[]" placeholder="+/- Rs" step="0.01">
            <input class="form-input" type="number" name="variant_stock[]" placeholder="Stock" min="0">
            <button type="button"
                    onclick="document.getElementById('variant_${variantCount}').remove()"
                    style="width:32px;height:38px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:5px;color:var(--red);cursor:pointer;font-size:16px;">×</button>
        </div>`;
    document.getElementById('variantsContainer').appendChild(row);
}

// Add one spec and one variant row by default on load
addSpec();
addVariant();

// ── Drag and drop on upload area ──
const uploadArea = document.querySelector('.upload-area');
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    document.getElementById('imageInput').files = e.dataTransfer.files;
    previewImages(document.getElementById('imageInput'));
});
</script>

</body>
</html>