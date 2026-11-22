<?php
// shop/shop.php
session_start();
require_once '../db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['role']      ?? null;
$userName   = $_SESSION['full_name'] ?? null;
$userId     = $_SESSION['user_id']   ?? null;

$activeCategory = $_GET['cat'] ?? 'all';
$searchQuery    = trim($_GET['q'] ?? '');
$sortBy         = $_GET['sort'] ?? 'featured';

// Fetch categories from DB
$categories = [];
try {
    $catStmt    = $conn->query("SELECT * FROM categories ORDER BY sort_order ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Fetch products
$products = [];
try {
    $sql = "SELECT p.*, v.store_name as vendor_name
            FROM products p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            WHERE p.is_active = 1";

    $params = [];

    if ($activeCategory !== 'all') {
        $sql .= " AND p.category = ?";
        $params[] = $activeCategory;
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    $sortOptions = [
        'featured'   => 'p.is_featured DESC, p.created_at DESC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'newest'     => 'p.created_at DESC',
        'top_rated'  => 'p.rating DESC',
        'popular'    => 'p.sales_count DESC',
    ];
    $orderBy = $sortOptions[$sortBy] ?? $sortOptions['featured'];
    $sql .= " ORDER BY $orderBy";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Cart count
$cartCount = 0;
if ($isLoggedIn) {
    try {
        $cartStmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $cartStmt->execute([$userId]);
        $row       = $cartStmt->fetch();
        $cartCount = (int)($row['total'] ?? 0);
    } catch (PDOException $e) {
        $cartCount = 0;
    }
} else {
    $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>
    <?php
      if (!empty($searchQuery))         echo htmlspecialchars($searchQuery) . ' — Bazaar.pk';
      elseif ($activeCategory !== 'all') echo ucfirst(htmlspecialchars($activeCategory)) . ' — Bazaar.pk';
      else                               echo 'Shop — Bazaar.pk';
    ?>
  </title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"     rel="stylesheet">
  <link href="../css/styleshop.css" rel="stylesheet">
</head>
<body>

<!-- ============================================================
     DRAWER
     ============================================================ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()">
  <div class="drawer" onclick="event.stopPropagation()">

    <div class="drawer-header">
      <div class="drawer-logo">Bazaar.pk</div>
      <button class="drawer-close" onclick="closeDrawer()">
        <svg viewBox="0 0 24 24">
          <line x1="18" y1="6"  x2="6"  y2="18"/>
          <line x1="6"  y1="6"  x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="drawer-menu">

      <a href="shop.php" class="dm-item <?= $activeCategory === 'all'        ? 'active' : '' ?>"><span class="dm-icon">&#127968;</span>Home</a>
      <a href="shop.php?cat=mobiles" class="dm-item <?= $activeCategory === 'mobiles'    ? 'active' : '' ?>"><span class="dm-icon">&#128241;</span>Mobiles & tablets</a>
      <a href="shop.php?cat=electronics" class="dm-item <?= $activeCategory === 'electronics' ? 'active' : '' ?>"><span class="dm-icon">&#128187;</span>Electronics</a>
      <a href="shop.php?cat=fashion" class="dm-item <?= $activeCategory === 'fashion'    ? 'active' : '' ?>"><span class="dm-icon">&#128087;</span>Fashion</a>
      <a href="shop.php?cat=home" class="dm-item <?= $activeCategory === 'home'       ? 'active' : '' ?>"><span class="dm-icon">&#127968;</span>Home & living</a>
      <a href="shop.php?cat=sports" class="dm-item <?= $activeCategory === 'sports'     ? 'active' : '' ?>"><span class="dm-icon">&#9917;</span>Sports</a>
      <a href="shop.php?cat=books" class="dm-item <?= $activeCategory === 'books'      ? 'active' : '' ?>"><span class="dm-icon">&#128218;</span>Books</a>
      <a href="shop.php?cat=groceries" class="dm-item <?= $activeCategory === 'groceries'  ? 'active' : '' ?>"><span class="dm-icon">&#127874;</span>Groceries</a>
      <a href="shop.php?cat=automotive" class="dm-item <?= $activeCategory === 'automotive' ? 'active' : '' ?>"><span class="dm-icon">&#128661;</span>Automotive</a>

      <div class="dm-divider"></div>

      <a href="shop.php?filter=flash"  class="dm-item"><span class="dm-icon">&#128293;</span>Flash deals</a>
      <a href="shop.php?sort=top_rated" class="dm-item"><span class="dm-icon">&#11088;</span>Top rated</a>
      <a href="shop.php?sort=newest"   class="dm-item"><span class="dm-icon">&#128722;</span>New arrivals</a>

      <div class="dm-divider"></div>

      <!-- Track my order — customers go to orders, guests go to login -->
      <?php if ($isLoggedIn && $userRole === 'customer'): ?>
        <a href="../customer/orders.php" class="dm-item">
          <span class="dm-icon">&#128230;</span>Track my order
        </a>
      <?php else: ?>
        <a href="../login.php" class="dm-item">
          <span class="dm-icon">&#128230;</span>Track my order
        </a>
      <?php endif; ?>

      <a href="../" class="dm-item"><span class="dm-icon">&#10067;</span>Help & support</a>

      <div class="dm-divider"></div>

      <!-- Price range filter -->
      <div class="dm-filter-section">
        <div class="dm-filter-title">Price range (PKR)</div>
        <div class="pr-row">
          <input class="pr-input" type="number" placeholder="Min" id="priceMin" value="<?= htmlspecialchars($_GET['min'] ?? '') ?>">
          <input class="pr-input" type="number" placeholder="Max" id="priceMax" value="<?= htmlspecialchars($_GET['max'] ?? '') ?>">
          <button class="pr-btn" onclick="applyPriceFilter()">Go</button>
        </div>
      </div>

      <!-- Rating filter -->
      <div class="dm-filter-section">
        <div class="dm-filter-title">Customer rating</div>
        <a href="shop.php?<?= http_build_query(array_merge($_GET, ['rating' => 5])) ?>" class="star-row" style="text-decoration:none;">
          <div class="stars">
            <svg class="star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <span class="star-lbl">5 stars only</span>
        </a>
        <a href="shop.php?<?= http_build_query(array_merge($_GET, ['rating' => 4])) ?>" class="star-row" style="text-decoration:none;">
          <div class="stars">
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star-e" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <span class="star-lbl">4★ & above</span>
        </a>
        <a href="shop.php?<?= http_build_query(array_merge($_GET, ['rating' => 3])) ?>" class="star-row" style="text-decoration:none;">
          <div class="stars">
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star"   viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star-e" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <svg class="star-e" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <span class="star-lbl">3★ & above</span>
        </a>
      </div>

    </div><!-- /drawer-menu -->

    <!-- Drawer footer — shows different options per role -->
    <div class="drawer-footer">
      <div class="df-user">
        <div class="df-avatar">
          <?= $isLoggedIn ? strtoupper(substr($userName, 0, 1)) : 'G' ?>
        </div>
        <div>
          <div class="df-name">
            <?= $isLoggedIn ? htmlspecialchars($userName) : 'Browsing as guest' ?>
          </div>
          <div class="df-role">
            <?= $isLoggedIn ? ucfirst($userRole) . ' account' : 'Login to place orders' ?>
          </div>
        </div>
      </div>

      <?php if ($isLoggedIn && $userRole === 'customer'): ?>
        <div style="display:flex;gap:6px;margin-bottom:8px;">
          <a href="../customer/profile.php" class="df-btn"
             style="flex:1;text-align:center;text-decoration:none;">
            My profile
          </a>
          <a href="../customer/orders.php" class="df-btn"
             style="flex:1;text-align:center;text-decoration:none;">
            My orders
          </a>
        </div>
        <a href="../logout.php" class="df-btn"
           style="display:block;text-align:center;">Logout</a>

      <?php elseif ($isLoggedIn && $userRole === 'vendor'): ?>
        <a href="../vendor/vendor_panel.php" class="df-btn"
           style="display:block;text-align:center;text-decoration:none;margin-bottom:6px;">
          Go to vendor panel
        </a>
        <a href="../logout.php" class="df-btn"
           style="display:block;text-align:center;">Logout</a>

      <?php elseif ($isLoggedIn && $userRole === 'admin'): ?>
        <a href="../admin/admin_panel.php" class="df-btn"
           style="display:block;text-align:center;text-decoration:none;margin-bottom:6px;">
          Go to admin panel
        </a>
        <a href="../logout.php" class="df-btn"
           style="display:block;text-align:center;">Logout</a>

      <?php else: ?>
        <a href="../login.php" class="df-btn">Login / Sign up</a>
      <?php endif; ?>

    </div>

  </div>
</div><!-- /drawer-overlay -->


<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="shop-nav">

  <button class="nav-hamburger" onclick="openDrawer()">
    <div class="hline"></div>
    <div class="hline"></div>
    <div class="hline"></div>
  </button>

  <a href="shop.php" class="shop-logo">Bazaar<span>.pk</span></a>

  <form class="nav-search" action="shop.php" method="GET">
    <?php if ($activeCategory !== 'all'): ?>
      <input type="hidden" name="cat" value="<?= htmlspecialchars($activeCategory) ?>">
    <?php endif; ?>
    <div class="search-cat">
      All categories
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <input class="search-inp" type="text" name="q"
           placeholder="Search for products, brands, vendors..."
           value="<?= htmlspecialchars($searchQuery) ?>">
    <button type="submit" class="search-btn">
      <svg viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
    </button>
  </form>

  <div class="nav-right">

    <!-- Deliver to -->
    <div class="nav-deliver">
      <span class="flag">&#127477;&#127472;</span>
      <div class="deliver-lbl">
        <span class="deliver-top">Deliver to</span>
        <span class="deliver-city">Lahore</span>
      </div>
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>

    <!-- Cart -->
    <a href="../cart/cart.php" class="nav-cart">
      <svg viewBox="0 0 24 24">
        <circle cx="9" cy="21" r="1"/>
        <circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/>
      </svg>
      <span class="cart-lbl">Cart</span>
      <div class="cart-count" id="cartBadge"><?= $cartCount ?></div>
    </a>

    <!-- Role-based right side buttons -->
    <?php if ($isLoggedIn): ?>

      <?php if ($userRole === 'customer'): ?>
        <!-- Customer: show Hi [Name] → profile + My orders -->
        <a href="../customer/profile.php"
           style="font-size:11px;color:var(--txt2);text-decoration:none;
                  padding:6px 12px;border:1px solid var(--border);
                  border-radius:6px;transition:all 0.15s;white-space:nowrap;"
           onmouseover="this.style.color='var(--teal)';this.style.borderColor='var(--border2)'"
           onmouseout="this.style.color='var(--txt2)';this.style.borderColor='var(--border)'">
          Hi, <?= htmlspecialchars(explode(' ', $userName)[0]) ?> &#128100;
        </a>
        <a href="../customer/orders.php"
           style="font-size:11px;color:var(--txt2);text-decoration:none;
                  padding:6px 12px;border:1px solid var(--border);
                  border-radius:6px;transition:all 0.15s;white-space:nowrap;"
           onmouseover="this.style.color='var(--teal)';this.style.borderColor='var(--border2)'"
           onmouseout="this.style.color='var(--txt2)';this.style.borderColor='var(--border)'">
          My orders
        </a>

      <?php elseif ($userRole === 'vendor'): ?>
        <!-- Vendor: link to their panel -->
        <a href="../vendor/vendor_panel.php"
           style="font-size:11px;color:var(--teal);text-decoration:none;
                  padding:6px 12px;border:1px solid var(--border2);
                  border-radius:6px;white-space:nowrap;">
          Vendor panel
        </a>

      <?php elseif ($userRole === 'admin'): ?>
        <!-- Admin: link to admin panel -->
        <a href="../admin/admin_panel.php"
           style="font-size:11px;color:var(--red);text-decoration:none;
                  padding:6px 12px;border:1px solid rgba(248,113,113,0.3);
                  border-radius:6px;white-space:nowrap;">
          Admin panel
        </a>
      <?php endif; ?>

      <a href="../logout.php" class="nav-logout-ghost">Logout</a>

    <?php else: ?>
      <a href="../login.php" class="nav-login-ghost">Login</a>
    <?php endif; ?>

  </div>
</nav>


<!-- ============================================================
     CATEGORY BAR
     ============================================================ -->
<div class="cat-bar">
  <div class="cat-scroll">
    <?php
    $catTabs = [
      'all'        => ['icon' => '&#127968;', 'label' => 'All'],
      'mobiles'    => ['icon' => '&#128241;', 'label' => 'Mobiles'],
      'laptops'    => ['icon' => '&#128187;', 'label' => 'Laptops'],
      'fashion'    => ['icon' => '&#127995;', 'label' => 'Fashion'],
      'shoes'      => ['icon' => '&#128088;', 'label' => 'Shoes'],
      'home'       => ['icon' => '&#127968;', 'label' => 'Home'],
      'sports'     => ['icon' => '&#9917;',   'label' => 'Sports'],
      'groceries'  => ['icon' => '&#127874;', 'label' => 'Groceries'],
      'books'      => ['icon' => '&#128218;', 'label' => 'Books'],
      'automotive' => ['icon' => '&#128661;', 'label' => 'Automotive'],
      'beauty'     => ['icon' => '&#128138;', 'label' => 'Beauty'],
      'watches'    => ['icon' => '&#127981;', 'label' => 'Watches'],
    ];
    foreach ($catTabs as $key => $cat) {
      $isActive = ($activeCategory === $key) ? 'active' : '';
      $href     = ($key === 'all') ? 'shop.php' : "shop.php?cat=$key";
      echo "<a href='$href' class='cat-item $isActive'>
              <span class='cat-icon'>{$cat['icon']}</span>
              {$cat['label']}
            </a>";
    }
    ?>
  </div>
  <div class="cat-more" onclick="openDrawer()">
    More
    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
  </div>
</div>


<!-- ============================================================
     HERO BANNER
     ============================================================ -->
<div class="hero-banner">
  <div class="banner-main">
    <div style="position:relative;z-index:1;">
      <div class="banner-tag">&#128293; Flash sale — ends in <span id="countdown">02:14:38</span></div>
      <div class="banner-title">Up to 60% off<br>on Electronics</div>
      <div class="banner-sub">Latest mobiles, laptops & gadgets</div>
      <a href="shop.php?cat=electronics&filter=flash" class="banner-btn">Shop now</a>
    </div>
    <div class="banner-visual" style="position:absolute;top:0;right:0;bottom:0;width:45%;overflow:hidden;border-radius:0 10px 10px 0;">
      <img src="../uploads/banners/banner1.jpg"
           alt="Flash Sale"
           style="width:100%;height:100%;object-fit:cover;"
           onerror="this.style.display='none'">
    </div>
  </div>
  <div class="banner-side">
    <a href="shop.php?sort=newest" class="banner-small" style="text-decoration:none;position:relative;overflow:hidden;">
      <img src="../uploads/banners/banner2.jpg"
           style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0.18;border-radius:8px;"
           onerror="this.style.display='none'">
      <div class="bs-tag" style="position:relative;">&#127381; New arrivals</div>
      <div class="bs-title" style="position:relative;">Summer fashion 2025</div>
      <div class="bs-sub" style="position:relative;">Fresh drops from top vendors</div>
    </a>
    <a href="shop.php?sort=top_rated" class="banner-small" style="text-decoration:none;position:relative;overflow:hidden;">
      <img src="../uploads/banners/banner3.jpg"
           style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0.18;border-radius:8px;"
           onerror="this.style.display='none'">
      <div class="bs-tag" style="position:relative;">&#11088; Top rated</div>
      <div class="bs-title" style="position:relative;">Best sellers this week</div>
      <div class="bs-sub" style="position:relative;">Customers love these</div>
    </a>
  </div>
</div>


<!-- ============================================================
     FILTER BAR
     ============================================================ -->
<div class="filter-bar">
  <span class="filter-label">Filter:</span>

  <button class="filter-chip <?= isset($_GET['free_delivery']) ? 'active' : '' ?>"
          onclick="toggleFilter('free_delivery')">Free delivery</button>
  <button class="filter-chip <?= isset($_GET['in_stock'])     ? 'active' : '' ?>"
          onclick="toggleFilter('in_stock')">In stock</button>
  <button class="filter-chip <?= isset($_GET['on_sale'])      ? 'active' : '' ?>"
          onclick="toggleFilter('on_sale')">On sale</button>
  <button class="filter-chip <?= isset($_GET['verified'])     ? 'active' : '' ?>"
          onclick="toggleFilter('verified')">Verified vendor</button>
  <button class="filter-chip <?= (isset($_GET['rating']) && $_GET['rating'] >= 4) ? 'active' : '' ?>"
          onclick="toggleFilter('rating', 4)">4★ & above</button>

  <div class="filter-divider"></div>

  <select class="sort-select" onchange="applySort(this.value)">
    <option value="featured"   <?= $sortBy === 'featured'   ? 'selected' : '' ?>>Best match</option>
    <option value="price_asc"  <?= $sortBy === 'price_asc'  ? 'selected' : '' ?>>Price: low to high</option>
    <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
    <option value="newest"     <?= $sortBy === 'newest'     ? 'selected' : '' ?>>Newest first</option>
    <option value="top_rated"  <?= $sortBy === 'top_rated'  ? 'selected' : '' ?>>Top rated</option>
    <option value="popular"    <?= $sortBy === 'popular'    ? 'selected' : '' ?>>Most popular</option>
  </select>

  <span class="result-count"><?= count($products) ?> products</span>

  <div class="view-toggle">
    <button class="vt-btn active" id="gridBtn" onclick="setView('grid', this)">
      <svg viewBox="0 0 24 24">
        <rect x="3"  y="3"  width="7" height="7"/>
        <rect x="14" y="3"  width="7" height="7"/>
        <rect x="3"  y="14" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/>
      </svg>
    </button>
    <button class="vt-btn" id="listBtn" onclick="setView('list', this)">
      <svg viewBox="0 0 24 24">
        <line x1="8"  y1="6"  x2="21" y2="6"/>
        <line x1="8"  y1="12" x2="21" y2="12"/>
        <line x1="8"  y1="18" x2="21" y2="18"/>
        <line x1="3"  y1="6"  x2="3.01" y2="6"/>
        <line x1="3"  y1="12" x2="3.01" y2="12"/>
        <line x1="3"  y1="18" x2="3.01" y2="18"/>
      </svg>
    </button>
  </div>
</div>


<!-- ============================================================
     PRODUCTS GRID
     ============================================================ -->
<div class="products-area">
  <div class="section-head">
    <div class="section-title">
      <?php if (!empty($searchQuery)): ?>
        Results for <span>"<?= htmlspecialchars($searchQuery) ?>"</span>
      <?php elseif ($activeCategory !== 'all'): ?>
        <span><?= ucfirst(htmlspecialchars($activeCategory)) ?></span> products
      <?php else: ?>
        &#11088; <span>Featured</span> products
      <?php endif; ?>
    </div>
    <span class="section-see">See all →</span>
  </div>

  <div class="prod-grid" id="prodGrid">
    <?php if (empty($products)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--txt3);">
        <div style="font-size:48px;margin-bottom:16px;">&#128722;</div>
        <div style="font-size:16px;color:var(--txt2);margin-bottom:8px;">No products found</div>
        <div style="font-size:13px;">
          <?php if (!empty($searchQuery)): ?>
            No results for "<?= htmlspecialchars($searchQuery) ?>". Try a different search.
          <?php else: ?>
            Products will appear here once vendors start listing them.
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <?php foreach ($products as $product): ?>
        <div class="prod-card"
             onclick="window.location='../product/product.php?id=<?= $product['id'] ?>'">

          <div class="prod-img">
            <?php if (!empty($product['image'])): ?>
              <!-- FIXED PATH: ../uploads/products/ -->
              <img src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                   alt="<?= htmlspecialchars($product['title']) ?>"
                   style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <svg viewBox="0 0 24 24" style="width:40px;height:40px;stroke:var(--txt3);fill:none;stroke-width:1;">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
            <?php endif; ?>

            <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
              <?php $discountPct = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>
              <div class="prod-badge badge-sale">-<?= $discountPct ?>%</div>
            <?php elseif (!empty($product['is_new'])): ?>
              <div class="prod-badge badge-new">NEW</div>
            <?php elseif (!empty($product['is_hot'])): ?>
              <div class="prod-badge badge-hot">HOT</div>
            <?php endif; ?>

            <div class="prod-wish"
                 onclick="event.stopPropagation(); toggleWishlist(this, <?= $product['id'] ?>)">
              <svg viewBox="0 0 24 24">
                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
              </svg>
            </div>
          </div>

          <div class="prod-body">
            <div class="prod-vendor">
              <?= htmlspecialchars($product['vendor_name'] ?? 'Bazaar.pk') ?>
            </div>
            <div class="prod-name">
              <?= htmlspecialchars($product['title']) ?>
            </div>

            <div class="prod-stars">
              <?php
                $rating = round($product['rating'] ?? 0);
                for ($i = 1; $i <= 5; $i++) {
                  if ($i <= $rating)
                    echo '<svg class="ps" viewBox="0 0 24 24" width="11" height="11"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
                  else
                    echo '<svg class="ps-empty" viewBox="0 0 24 24" width="11" height="11"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
                }
              ?>
              <span class="prod-rev">(<?= (int)($product['review_count'] ?? 0) ?>)</span>
            </div>

            <div class="prod-price-row">
              <span class="prod-price">Rs <?= number_format($product['price']) ?></span>
              <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                <span class="prod-old">Rs <?= number_format($product['old_price']) ?></span>
                <span class="prod-discount">-<?= $discountPct ?>%</span>
              <?php endif; ?>
            </div>

            <button class="prod-atc"
                    onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>, this)">
              + Add to cart
            </button>
          </div>

        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Drawer
function openDrawer()  { document.getElementById('drawerOverlay').classList.add('open');    }
function closeDrawer() { document.getElementById('drawerOverlay').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

// Category tabs
document.querySelectorAll('.cat-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.cat-item').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
    });
});

// Sort
function applySort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    window.location.href = url.toString();
}

// Filter chips
function toggleFilter(key, value = '1') {
    const url = new URL(window.location.href);
    if (url.searchParams.has(key)) url.searchParams.delete(key);
    else url.searchParams.set(key, value);
    window.location.href = url.toString();
}

// Price filter
function applyPriceFilter() {
    const min = document.getElementById('priceMin').value;
    const max = document.getElementById('priceMax').value;
    const url = new URL(window.location.href);
    if (min) url.searchParams.set('min', min);
    if (max) url.searchParams.set('max', max);
    window.location.href = url.toString();
    closeDrawer();
}

// Grid / List view
function setView(type, btn) {
    document.querySelectorAll('.vt-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const grid = document.getElementById('prodGrid');
    grid.style.gridTemplateColumns = type === 'list'
        ? '1fr'
        : 'repeat(auto-fill, minmax(175px, 1fr))';
}

// Add to cart via AJAX
function addToCart(productId, btn) {
    btn.textContent = 'Adding...';
    btn.disabled    = true;

    fetch('../cart/add_to_cart.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('cartBadge');
            badge.textContent        = data.cart_count;
            badge.style.transform    = 'scale(1.5)';
            setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
            btn.textContent          = '✓ Added';
            btn.style.background     = 'var(--teal)';
            btn.style.color          = '#0f1117';
            setTimeout(() => {
                btn.textContent      = '+ Add to cart';
                btn.style.background = '';
                btn.style.color      = '';
                btn.disabled         = false;
            }, 1500);
        } else {
            if (data.redirect) window.location.href = data.redirect;
        }
    })
    .catch(() => {
        btn.textContent = '+ Add to cart';
        btn.disabled    = false;
    });
}

// Wishlist toggle (visual only)
function toggleWishlist(btn, productId) {
    btn.classList.toggle('wishlisted');
}

// Flash sale countdown
(function() {
    const target = new Date().getTime() + (2*60*60*1000) + (14*60*1000) + (38*1000);
    function update() {
        const diff = target - new Date().getTime();
        if (diff <= 0) { document.getElementById('countdown').textContent = '00:00:00'; return; }
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        document.getElementById('countdown').textContent =
            String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    }
    update();
    setInterval(update, 1000);
})();
</script>

</body>
</html>