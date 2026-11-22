<?php
// customer/profile.php
// Customer edits their name, email, password

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); exit();
}
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php"); exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];
$success  = ''; $error = '';

// Fetch user
try {
    $uStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();
} catch (PDOException $e) { $user = null; }

// ── Handle profile update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');

        if (empty($fullName) || empty($email)) {
            $error = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check email not taken by another user
                $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
                $check->execute([$email, $userId]);
                if ($check->fetch()) {
                    $error = "This email is already used by another account.";
                } else {
                    $conn->prepare("UPDATE users SET full_name=?, email=? WHERE id=?")
                         ->execute([$fullName, $email, $userId]);
                    $_SESSION['full_name'] = $fullName;
                    $userName = $fullName;
                    $success  = "Profile updated successfully!";
                    // Refresh user data
                    $uStmt->execute([$userId]);
                    $user = $uStmt->fetch();
                }
            } catch (PDOException $e) { $error = "Error updating profile."; }
        }
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password']     ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = "All password fields are required.";
        } elseif (!password_verify($currentPass, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($newPass) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($newPass !== $confirmPass) {
            $error = "New passwords do not match.";
        } else {
            try {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $userId]);
                $success = "Password changed successfully!";
            } catch (PDOException $e) { $error = "Error changing password."; }
        }
    }
}

// Order stats
$orderCount = 0; $totalSpent = 0;
try {
    $os = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS spent FROM orders WHERE customer_id=? AND status!='cancelled'");
    $os->execute([$userId]);
    $row = $os->fetch();
    $orderCount = $row['cnt']; $totalSpent = $row['spent'];
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"     rel="stylesheet">
  <link href="../css/styleshop.css" rel="stylesheet">
  <style>
    .page-wrap { max-width: 760px; margin: 0 auto; padding: 24px 20px; }
    .page-title { font-size: 20px; font-weight: 700; color: var(--txt); margin-bottom: 20px; }
    .profile-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 14px; }
    .pc-title { font-size: 13px; font-weight: 600; color: var(--txt); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
    .form-group { margin-bottom: 12px; }
    .form-label { display: block; font-size: 11px; font-weight: 500; color: var(--txt2); margin-bottom: 5px; }
    .form-input { width: 100%; padding: 9px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; font-size: 12px; color: var(--txt); outline: none; font-family: inherit; transition: border-color 0.15s; }
    .form-input:focus { border-color: var(--teal); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .btn-save { padding: 9px 22px; background: var(--teal); color: #0f1117; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.15s; }
    .btn-save:hover { background: var(--teal2); }
    .flash { padding: 10px 14px; border-radius: 7px; font-size: 12px; font-weight: 500; margin-bottom: 14px; }
    .flash.success { background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.25); color: var(--green); }
    .flash.error   { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.25); color: var(--red); }
  </style>
</head>
<body>

<nav class="shop-nav">
  <a href="../shop/shop.php" class="shop-logo">Bazaar<span>.pk</span></a>
  <div style="flex:1;"></div>
  <div class="nav-right">
    <a href="orders.php" style="font-size:11px;color:var(--txt2);text-decoration:none;padding:6px 12px;border:1px solid var(--border);border-radius:6px;">My orders</a>
    <a href="../logout.php" class="nav-logout-ghost">Logout</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="page-title">My profile</div>

  <?php if ($success): ?><div class="flash success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Stats row -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px;">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;">
      <div style="font-size:22px;font-weight:700;color:var(--teal);"><?= $orderCount ?></div>
      <div style="font-size:11px;color:var(--txt2);margin-top:4px;">Orders placed</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;">
      <div style="font-size:22px;font-weight:700;color:var(--teal);">Rs <?= number_format($totalSpent) ?></div>
      <div style="font-size:11px;color:var(--txt2);margin-top:4px;">Total spent</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;">
      <div style="font-size:22px;font-weight:700;color:var(--teal);"><?= date('M Y', strtotime($user['created_at'])) ?></div>
      <div style="font-size:11px;color:var(--txt2);margin-top:4px;">Member since</div>
    </div>
  </div>

  <!-- Update profile -->
  <div class="profile-card">
    <div class="pc-title">Personal information</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full name</label>
          <input class="form-input" type="text" name="full_name"
                 value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email address</label>
          <input class="form-input" type="email" name="email"
                 value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Account role</label>
        <input class="form-input" type="text" value="<?= ucfirst($user['role']) ?>" disabled
               style="opacity:0.5;cursor:not-allowed;">
      </div>
      <button type="submit" class="btn-save">Save changes</button>
    </form>
  </div>

  <!-- Change password -->
  <div class="profile-card">
    <div class="pc-title">Change password</div>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label class="form-label">Current password</label>
        <input class="form-input" type="password" name="current_password" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">New password</label>
          <input class="form-input" type="password" name="new_password" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm new password</label>
          <input class="form-input" type="password" name="confirm_password" required>
        </div>
      </div>
      <div style="font-size:11px;color:var(--txt3);margin-bottom:10px;">Password must be at least 8 characters.</div>
      <button type="submit" class="btn-save">Change password</button>
    </form>
  </div>

  <!-- Quick links -->
  <div class="profile-card">
    <div class="pc-title">Quick links</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="orders.php" style="padding:8px 16px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--txt2);text-decoration:none;transition:all 0.15s;">&#128230; My orders</a>
      <a href="../shop/shop.php" style="padding:8px 16px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--txt2);text-decoration:none;transition:all 0.15s;">&#128722; Browse shop</a>
      <a href="../cart/cart.php" style="padding:8px 16px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--txt2);text-decoration:none;transition:all 0.15s;">&#128717; My cart</a>
      <a href="../logout.php" style="padding:8px 16px;border:1px solid rgba(248,113,113,0.3);border-radius:6px;font-size:12px;color:var(--red);text-decoration:none;transition:all 0.15s;">&#8594; Logout</a>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>