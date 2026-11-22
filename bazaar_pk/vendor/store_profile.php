<?php
// vendor/store_profile.php
// Vendor edits their store name, bio, logo

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php"); exit();
}

$userId      = $_SESSION['user_id'];
$userName    = $_SESSION['full_name'];
$currentPage = 'store_profile';
$success = ''; $error = '';

try {
    $vStmt = $conn->prepare("SELECT * FROM vendors WHERE user_id=? LIMIT 1");
    $vStmt->execute([$userId]);
    $vendor = $vStmt->fetch();
} catch (PDOException $e) { $vendor = null; }

if (!$vendor) { header("Location: setup_store.php"); exit(); }
$vendorId = $vendor['id'];

// ── Handle update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $bio       = trim($_POST['bio']        ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $city      = trim($_POST['city']       ?? '');
    $website   = trim($_POST['website']    ?? '');

    if (empty($storeName)) {
        $error = "Store name is required.";
    } else {
        try {
            // Check name not taken by another vendor
            $check = $conn->prepare("SELECT id FROM vendors WHERE store_name=? AND id!=?");
            $check->execute([$storeName, $vendorId]);
            if ($check->fetch()) {
                $error = "This store name is already taken.";
            } else {
                $logo = $vendor['logo'];

                // Handle logo upload
                if (!empty($_FILES['logo']['name'])) {
                    $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','webp'];
                    if (!in_array($ext, $allowed)) {
                        $error = "Logo must be JPG, PNG, or WebP.";
                    } elseif ($_FILES['logo']['size'] > 2*1024*1024) {
                        $error = "Logo must be under 2MB.";
                    } else {
                        $uploadDir = '../uploads/vendors/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $newLogo = uniqid('vendor_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $newLogo)) {
                            // Delete old logo
                            if (!empty($logo) && file_exists($uploadDir . $logo)) {
                                unlink($uploadDir . $logo);
                            }
                            $logo = $newLogo;
                        }
                    }
                }

                if (empty($error)) {
                    $conn->prepare("
                        UPDATE vendors SET store_name=?, bio=?, logo=? WHERE id=?
                    ")->execute([$storeName, $bio, $logo, $vendorId]);
                    $success = "Store profile updated successfully!";
                    // Refresh
                    $vStmt->execute([$userId]);
                    $vendor = $vStmt->fetch();
                }
            }
        } catch (PDOException $e) { $error = "Error updating profile."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Store Profile — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
</head>
<body>
<div class="vendor-wrap">
  <?php include 'sidebar.php'; ?>
  <div class="vendor-main">

    <div class="vendor-header">
      <div class="vh-title">Store profile</div>
      <div class="vh-right">
        <div class="vh-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="vendor-content">
      <?php if ($success): ?><div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--green);">&#10003; <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.25);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:var(--red);">&#10007; <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 280px;gap:16px;align-items:start;">

        <form method="POST" enctype="multipart/form-data">

          <div class="form-section">
            <div class="form-section-title">Store information</div>

            <div class="form-group">
              <label class="form-label">Store name <span style="color:var(--red);">*</span></label>
              <input class="form-input" type="text" name="store_name"
                     value="<?= htmlspecialchars($vendor['store_name']) ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Store description / bio</label>
              <textarea class="form-textarea" name="bio"
                        placeholder="Tell customers what your store is about..."><?= htmlspecialchars($vendor['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">
                Store logo
                <span class="form-label-sub"> — JPG/PNG/WebP, max 2MB</span>
              </label>
              <?php if (!empty($vendor['logo'])): ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                  <div style="width:52px;height:52px;border-radius:10px;border:1px solid var(--border);overflow:hidden;">
                    <img src="../uploads/vendors/<?= htmlspecialchars($vendor['logo']) ?>"
                         style="width:100%;height:100%;object-fit:cover;">
                  </div>
                  <span style="font-size:11px;color:var(--txt2);">Current logo — upload new to replace</span>
                </div>
              <?php endif; ?>
              <input class="form-input" type="file" name="logo" accept="image/*">
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary">Save changes</button>
          </div>

        </form>

        <!-- Store preview card -->
        <div style="position:sticky;top:80px;">
          <div class="form-section">
            <div class="form-section-title">Store preview</div>
            <div style="background:var(--bg3);border-radius:10px;padding:16px;border:1px solid var(--border);">
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <div style="width:44px;height:44px;border-radius:9px;background:var(--teal-dim);border:1px solid var(--border2);display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:18px;font-weight:700;color:var(--teal);">
                  <?php if (!empty($vendor['logo'])): ?>
                    <img src="../uploads/vendors/<?= htmlspecialchars($vendor['logo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <?= strtoupper(substr($vendor['store_name'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:600;color:var(--txt);"><?= htmlspecialchars($vendor['store_name']) ?></div>
                  <div style="font-size:10px;color:var(--teal);">
                    <?php if ($vendor['status'] === 'approved'): ?>
                      ✓ Verified seller
                    <?php else: ?>
                      ⏳ Pending approval
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div style="font-size:11px;color:var(--txt2);line-height:1.6;">
                <?= htmlspecialchars(substr($vendor['bio'] ?? 'No description yet.', 0, 120)) ?>
              </div>
              <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:14px;">
                <div style="text-align:center;">
                  <div style="font-size:14px;font-weight:700;color:var(--teal);"><?= number_format($vendor['rating'],1) ?></div>
                  <div style="font-size:10px;color:var(--txt3);">Rating</div>
                </div>
                <div style="text-align:center;">
                  <div style="font-size:14px;font-weight:700;color:var(--txt);">
                    <?php
                    try {
                        $pc = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id=? AND is_active=1");
                        $pc->execute([$vendorId]);
                        echo $pc->fetchColumn();
                    } catch (PDOException $e) { echo 0; }
                    ?>
                  </div>
                  <div style="font-size:10px;color:var(--txt3);">Products</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Account status -->
          <div class="form-section" style="margin-top:0;">
            <div class="form-section-title">Account status</div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
              <span style="font-size:10px;font-weight:700;padding:4px 10px;border-radius:4px;
                background:<?= $vendor['status']==='approved' ? 'rgba(74,222,128,0.12)' : ($vendor['status']==='pending' ? 'rgba(245,158,11,0.12)' : 'rgba(248,113,113,0.12)') ?>;
                color:<?= $vendor['status']==='approved' ? 'var(--green)' : ($vendor['status']==='pending' ? 'var(--amber)' : 'var(--red)') ?>;">
                <?= ucfirst($vendor['status']) ?>
              </span>
            </div>
            <div style="font-size:11px;color:var(--txt2);line-height:1.6;">
              <?php if ($vendor['status'] === 'approved'): ?>
                Your store is live and products are visible to customers.
              <?php elseif ($vendor['status'] === 'pending'): ?>
                Your store is under review. Products will go live once approved by admin.
              <?php else: ?>
                Your store has been suspended. Contact support for assistance.
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>