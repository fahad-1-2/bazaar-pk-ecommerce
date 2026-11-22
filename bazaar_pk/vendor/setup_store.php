<?php
// vendor/setup_store.php
// First time setup for new vendors — create their store profile
// Redirected here from vendor_panel.php if no vendor record exists

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];
$error    = '';
$success  = '';

// ── Handle form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $bio       = trim($_POST['bio']        ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $city      = trim($_POST['city']       ?? '');

    if (empty($storeName)) {
        $error = "Store name is required.";
    } elseif (strlen($storeName) < 3) {
        $error = "Store name must be at least 3 characters.";
    } else {
        try {
            // Check store name not taken
            $check = $conn->prepare("SELECT id FROM vendors WHERE store_name = ?");
            $check->execute([$storeName]);
            if ($check->fetch()) {
                $error = "This store name is already taken. Try another.";
            } else {
                // Handle logo upload
                $logo = null;
                if (!empty($_FILES['logo']['name'])) {
                    $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','webp'];
                    if (!in_array($ext, $allowed)) {
                        $error = "Logo must be JPG, PNG, or WebP.";
                    } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                        $error = "Logo must be under 2MB.";
                    } else {
                        $logo = uniqid('vendor_') . '.' . $ext;
                        $uploadDir = '../uploads/vendors/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logo);
                    }
                }

                if (empty($error)) {
                    // Insert vendor record
                    $ins = $conn->prepare("
                        INSERT INTO vendors (user_id, store_name, bio, logo, status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    $ins->execute([$userId, $storeName, $bio, $logo]);

                    // Redirect to dashboard
                    header("Location: vendor_panel.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup Your Store — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"       rel="stylesheet">
  <link href="../css/stylevendor.css" rel="stylesheet">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .setup-card { width: 100%; max-width: 500px; background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 32px; }
    .setup-logo { font-size: 22px; font-weight: 700; color: var(--teal); margin-bottom: 6px; }
    .setup-title { font-size: 18px; font-weight: 600; color: var(--txt); margin-bottom: 6px; }
    .setup-sub { font-size: 12px; color: var(--txt2); margin-bottom: 24px; }
    .err { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); color: var(--red); padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 16px; }
  </style>
</head>
<body>

<div class="setup-card">
  <div class="setup-logo">Bazaar.pk</div>
  <div class="setup-title">Set up your store</div>
  <div class="setup-sub">
    Welcome <?= htmlspecialchars($userName) ?>! Create your store profile to start selling.
  </div>

  <?php if ($error): ?>
    <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <div class="form-group">
      <label class="form-label">
        Store name <span style="color:var(--red);">*</span>
        <span class="form-label-sub"> — This will be your public shop name</span>
      </label>
      <input class="form-input" type="text" name="store_name"
             placeholder="e.g. TechZone PK"
             value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>"
             required>
    </div>

    <div class="form-group">
      <label class="form-label">Store description</label>
      <textarea class="form-textarea" name="bio"
                placeholder="Tell customers what your store is about..."
                style="min-height:80px;"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Store logo <span class="form-label-sub"> — Optional, max 2MB</span></label>
      <input class="form-input" type="file" name="logo" accept="image/*">
    </div>

    <div class="form-row" style="margin-bottom:14px;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Phone number</label>
        <input class="form-input" type="text" name="phone"
               placeholder="03XX XXXXXXX"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">City</label>
        <input class="form-input" type="text" name="city"
               placeholder="e.g. Lahore"
               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
      </div>
    </div>

    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:6px;padding:10px 14px;font-size:11px;color:var(--amber);margin-bottom:20px;">
      &#9888; Your store will be reviewed by our team before going live. This usually takes 1-2 business days.
    </div>

    <button type="submit" class="btn-primary" style="width:100%;">
      Create my store →
    </button>

    <div style="text-align:center;margin-top:14px;">
      <a href="../logout.php" style="font-size:11px;color:var(--txt3);text-decoration:none;">Sign out</a>
    </div>

  </form>
</div>

</body>
</html>