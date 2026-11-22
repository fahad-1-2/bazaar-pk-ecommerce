<?php
// login.php
session_start();

// Already logged in? Send them to their panel
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin')       header("Location: admin/admin_panel.php");
    elseif ($role === 'vendor')  header("Location: vendor/vendor_panel.php");
    else                         header("Location: shop/shop.php");
    exit();
}

require_once 'db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";

    } else {
        // Look up the user by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify() checks the plain password against the stored hash
        // This is the pair to password_hash() we used in signup
        if ($user && password_verify($password, $user['password'])) {

            // ── Login successful ──
            // Store key info in session — available on every page
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Redirect based on role — user never sees this logic
            if ($user['role'] === 'admin') {
                header("Location: admin/admin_panel.php");
            } elseif ($user['role'] === 'vendor') {
                header("Location: vendor/vendor_panel.php");
            } else {
                header("Location: shop/shop.php");
            }
            exit();

        } else {
            // Wrong email or wrong password — don't tell them which
            // Saying "wrong password" helps hackers know the email exists
            $error = "Incorrect email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Bazaar.pk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-outer">

  <!-- ── LEFT BRAND PANEL ── -->
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
      Welcome back! Sign in to access your personalized dashboard and continue where you left off.
    </div>

    <div class="auth-feats">
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Customers → browse, order and track deliveries</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Vendors → manage your store, products and revenue</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">Admins → full platform control panel</div>
      </div>
      <div class="alf">
        <div class="alf-dot"></div>
        <div class="alf-txt">You are redirected automatically based on your role</div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT FORM PANEL ── -->
  <div class="auth-right">
    <div class="form-title">Welcome back</div>
    <div class="form-sub">
      Don't have an account? <a href="signup.php">Sign up free</a>
    </div>

    <?php if ($error): ?>
      <div class="err-msg" style="display:block;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST">

      <div class="form-group">
        <label class="form-label">Email address</label>
        <input class="form-input" type="email" name="email"
               placeholder="ali@example.com"
               value="<?= htmlspecialchars($email ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="form-input-wrap">
          <input class="form-input" type="password" name="password"
                 id="passField" placeholder="Your password"
                 style="padding-right:36px;" required>
          <span class="form-eye" onclick="togglePass()">
            <svg viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </span>
        </div>
      </div>

      <div class="forgot-link">
        <a href="#">Forgot password?</a>
      </div>

      <button type="submit" class="form-submit">Login to my account</button>
    </form>

    <div class="form-switch">
      Don't have an account? <a href="signup.php">Sign up free</a>
    </div>
  </div>

</div>

<script src="js/main.js"></script>
</body>
</html>