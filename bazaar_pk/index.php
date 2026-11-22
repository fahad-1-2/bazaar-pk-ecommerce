<?php
// index.php — Landing page
// session_start() lets us check if someone is already logged in
session_start();

// If user is already logged in, skip this page and send them to their panel
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin')        header("Location: admin/admin_panel.php");
    elseif ($role === 'vendor')   header("Location: vendor/vendor_panel.php");
    else                          header("Location: shop/shop.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bazaar.pk — Pakistan's Multi-Vendor Marketplace</title>
  <!-- Bootstrap 5 CSS — gives us grid, utilities, responsiveness -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Our custom CSS on top of Bootstrap -->
  <link href="css/style.css" rel="stylesheet">
</head>
<body>

<!-- ========== NAVBAR ========== -->
<nav class="bz-nav">
  <div class="bz-nav-left">
    <div class="bz-logo">Bazaar<span>.pk</span></div>
    <a href="shop/shop.php" class="bz-browse-btn">&#9776; Browse store</a>
    <div class="bz-nav-links">
      <a href="shop/shop.php?cat=all">Categories</a>
      <a href="shop/shop.php?deals=1">Deals</a>
      <a href="signup.php?as=vendor">Sell</a>
    </div>
  </div>
  <div class="bz-nav-right">
    <a href="login.php" class="bz-btn-outline">Login</a>
    <a href="signup.php" class="bz-btn-fill">Sign up</a>
  </div>
</nav>

<!-- ========== HERO ========== -->
<section class="bz-hero">
  <div class="hero-label">Pakistan's #1 Multi-Vendor Marketplace</div>
  <h1 class="hero-title">Shop everything.<br><span>Sell anything.</span></h1>
  <p class="hero-sub">Browse thousands of products from verified vendors across Pakistan. No account needed to explore.</p>
  <div class="hero-btns">
    <a href="signup.php" class="hbtn-primary">Start shopping</a>
    <a href="signup.php?as=vendor" class="hbtn-outline">Become a vendor</a>
    <a href="shop/shop.php" class="hbtn-ghost">Browse as guest</a>
  </div>
  <p class="hero-note">No account needed to browse &nbsp;·&nbsp; Free to sign up &nbsp;·&nbsp; 500+ verified vendors</p>
  <div class="hero-stats">
    <div class="hstat"><div class="hstat-num">10,000+</div><div class="hstat-lbl">Products listed</div></div>
    <div class="hstat"><div class="hstat-num">500+</div><div class="hstat-lbl">Active vendors</div></div>
    <div class="hstat"><div class="hstat-num">50,000+</div><div class="hstat-lbl">Happy customers</div></div>
  </div>
</section>

<!-- ========== FEATURES ========== -->
<section class="bz-features">
  <div class="sec-label">Why Bazaar.pk</div>
  <h2 class="sec-title">Everything in one place</h2>
  <p class="sec-sub">Shop, sell, and grow — built for Pakistan</p>
  <div class="feat-grid">
    <div class="feat-card">
      <div class="feat-icon">&#128722;</div>
      <div class="feat-card-title">Millions of products</div>
      <div class="feat-card-desc">Fashion, electronics, home goods — all categories under one roof</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon">&#128187;</div>
      <div class="feat-card-title">Sell online easily</div>
      <div class="feat-card-desc">Open your store in minutes — full vendor dashboard included free</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon">&#128274;</div>
      <div class="feat-card-title">Secure payments</div>
      <div class="feat-card-desc">Every transaction is verified and protected end to end</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon">&#128666;</div>
      <div class="feat-card-title">Fast delivery</div>
      <div class="feat-card-desc">Reliable shipping to all major cities across Pakistan</div>
    </div>
  </div>
</section>

<!-- ========== HOW IT WORKS ========== -->
<section class="bz-how">
  <div class="sec-label">How it works</div>
  <h2 class="sec-title">Get started in 3 steps</h2>
  <div class="how-steps">
    <div class="how-step"><div class="how-num">1</div><div class="how-step-title">Browse freely</div><div class="how-step-desc">No login needed. Explore products, search and filter anything</div></div>
    <div class="how-connector"></div>
    <div class="how-step"><div class="how-num">2</div><div class="how-step-title">Create account</div><div class="how-step-desc">Sign up as a customer or vendor — completely free</div></div>
    <div class="how-connector"></div>
    <div class="how-step"><div class="how-num">3</div><div class="how-step-title">Buy or earn</div><div class="how-step-desc">Checkout securely or list products and get paid</div></div>
  </div>
</section>

<!-- ========== VENDOR CTA ========== -->
<section class="bz-vcta">
  <div class="vcta-box">
    <h2 class="vcta-title">Ready to start selling?</h2>
    <p class="vcta-sub">Join 500+ vendors already earning on Bazaar.pk. Your store setup is completely free.</p>
    <a href="signup.php?as=vendor" class="vcta-btn">Open your store today</a>
  </div>
</section>

<!-- ========== FOOTER ========== -->
<footer class="bz-footer">
  <div class="footer-logo">Bazaar.pk</div>
  <div class="footer-links">
    <a href="#">About</a>
    <a href="#">Privacy</a>
    <a href="#">Terms</a>
    <a href="#">Contact</a>
  </div>
  <div class="footer-copy">© 2025 Bazaar.pk — All rights reserved</div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>