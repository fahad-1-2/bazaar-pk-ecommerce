<?php
session_start();

// Redirect already logged-in users to their panel
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin')      header("Location: admin/admin_panel.php");
    elseif ($role === 'vendor') header("Location: vendor/vendor_panel.php");
    else                        header("Location: shop/shop.php");
    exit();
}

require_once 'db.php';

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Grab and clean form values
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $role      = trim($_POST['role']      ?? 'customer');
    $cnic      = trim($_POST['cnic']      ?? '');

    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "All fields are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";

    } elseif (!in_array($role, ['customer', 'vendor'])) {
        $error = "Invalid role selected.";

    } elseif (!empty($cnic) && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic)) {
        $error = "CNIC format must be XXXXX-XXXXXXX-X (e.g. 35202-1234567-1)";

    } else {
        // Check if email already registered
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "An account with this email already exists.";
        } else {
            // Hash password using bcrypt
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user including CNIC
            $insert = $conn->prepare(
                "INSERT INTO users (full_name, email, password, role, cnic)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insert->execute([$full_name, $email, $hashed, $role, $cnic]);

            // Auto login after signup
            $_SESSION['user_id']   = $conn->lastInsertId();
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role']      = $role;

            // Redirect to correct panel
            if ($role === 'vendor') {
                header("Location: vendor/vendor_panel.php");
            } else {
                header("Location: shop/shop.php");
            }
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign up — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-outer">

  <!-- LEFT PANEL -->
  <div class="auth-left">

    <div class="auth-top-btns">
      <a href="index.php" class="btn-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to home
      </a>
      <a href="shop/shop.php" class="btn-guest">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Browse as guest
      </a>
    </div>

    <div class="auth-divider"></div>

    <div class="auth-logo">Bazaar.pk</div>
    <div class="auth-tagline">
      Pakistan's trusted multi-vendor marketplace. Join thousands of buyers and sellers today.
    </div>

    <div class="auth-feats">
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Free to join — no credit card required</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Vendors get a full dashboard to manage products and orders</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Customers can track orders and save wishlists</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Secure, verified platform — your data is always safe</div>
      </div>
    </div>
  </div>

  <!-- RIGHT FORM PANEL -->
  <div class="auth-right">
    <div class="form-title">Create your account</div>
    <div class="form-sub">
      Already have an account? <a href="login.php">Login here</a>
    </div>

    <?php if ($error): ?>
      <div class="err-msg" style="display:block;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" id="signupForm">

      <div class="form-group">
        <label class="form-label">Full name</label>
        <input class="form-input" type="text" name="full_name"
               placeholder="Ali Hassan"
               value="<?= htmlspecialchars($full_name ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email address</label>
        <input class="form-input" type="email" name="email"
               placeholder="ali@example.com"
               value="<?= htmlspecialchars($email ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">CNIC number
          <span style="font-size:10px;color:var(--txt3);font-weight:400;"> — Optional</span>
        </label>
        <input class="form-input" type="text" name="cnic"
               placeholder="XXXXX-XXXXXXX-X"
               maxlength="15"
               value="<?= htmlspecialchars($_POST['cnic'] ?? '') ?>"
               id="cnicField">
        <div style="font-size:10px;color:var(--txt3);margin-top:4px;">
          Format: 35202-1234567-1 — dashes added automatically
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="form-input-wrap">
          <input class="form-input" type="password" name="password"
                 id="passField" placeholder="Min. 8 characters"
                 style="padding-right:36px;" required>
          <span class="form-eye" onclick="togglePass()">
            <svg viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">I want to join as</label>
        <input type="hidden" name="role" id="roleInput" value="customer">
        <div class="role-toggle">
          <button type="button" class="role-btn selected" id="btnCustomer"
                  onclick="selectRole('customer')">
            <span class="role-icon">&#128100;</span>
            Customer
          </button>
          <button type="button" class="role-btn" id="btnVendor"
                  onclick="selectRole('vendor')">
            <span class="role-icon">&#127978;</span>
            Vendor / Seller
          </button>
        </div>
      </div>

      <button type="submit" class="form-submit">Create my account</button>
    </form>

    <div class="form-switch">
      Already have an account? <a href="login.php">Login here</a>
    </div>
  </div>

</div>

<script src="js/main.js"></script>
<script>
// Auto format CNIC as user types — adds dashes automatically
document.getElementById('cnicField').addEventListener('input', function() {
    let val = this.value.replace(/\D/g, '');
    if (val.length > 5)  val = val.slice(0,5)  + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0,13) + '-' + val.slice(13);
    this.value = val.slice(0, 15);
});
</script>

</body>
</html>