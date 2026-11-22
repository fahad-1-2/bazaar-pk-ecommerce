<?php
// product/product.php
// Shows full detail of a single product
// Accessible by guests, customers, vendors, admins

session_start();
require_once '../db.php';

// Session data
$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['role']      ?? null;
$userName   = $_SESSION['full_name'] ?? null;
$userId     = $_SESSION['user_id']   ?? null;

// Get product ID from URL — e.g. product.php?id=5
// If no ID given, send back to shop
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header("Location: ../shop/shop.php");
    exit();
}

// ── Fetch product from DB ──
// We join with vendors table to get store info in one query
try {
    $stmt = $conn->prepare("
        SELECT p.*,
               v.store_name,
               v.logo       AS vendor_logo,
               v.rating     AS vendor_rating,
               v.status     AS vendor_status,
               v.id         AS vendor_table_id,
               u.full_name  AS vendor_owner_name
        FROM products p
        LEFT JOIN vendors v ON p.vendor_id = v.id
        LEFT JOIN users   u ON v.user_id   = u.id
        WHERE p.id = ? AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    $product = null;
}

// If product not found, go back to shop
if (!$product) {
    header("Location: ../shop/shop.php?error=product_not_found");
    exit();
}

// ── Fetch product images ──
// images column stores comma-separated filenames
// e.g. "img1.jpg,img2.jpg,img3.jpg"
$images = [];
if (!empty($product['images'])) {
    $images = explode(',', $product['images']);
    $images = array_filter(array_map('trim', $images));
}
// Always include main image first if it exists
if (!empty($product['image']) && !in_array($product['image'], $images)) {
    array_unshift($images, $product['image']);
}

// ── Fetch variants (colors, sizes, storage) ──
$variants = [];
try {
    $vStmt = $conn->prepare("
        SELECT * FROM product_variants
        WHERE product_id = ?
        ORDER BY variant_type, variant_value
    ");
    $vStmt->execute([$productId]);
    $variantRows = $vStmt->fetchAll();

    // Group variants by type for easy rendering
    // e.g. $variants['color'] = [{value:'Navy', stock:5}, ...]
    foreach ($variantRows as $v) {
        $variants[$v['variant_type']][] = $v;
    }
} catch (PDOException $e) {
    $variants = [];
}

// ── Fetch reviews ──
try {
    $rStmt = $conn->prepare("
        SELECT r.*, u.full_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $rStmt->execute([$productId]);
    $reviews = $rStmt->fetchAll();
} catch (PDOException $e) {
    $reviews = [];
}

// ── Check if current user can review ──
$canReview       = false;
$alreadyReviewed = false;

if ($isLoggedIn && $userRole === 'customer') {
    try {
        // Check if they already left a review
        $arStmt = $conn->prepare("
            SELECT id FROM reviews
            WHERE user_id = ? AND product_id = ?
            LIMIT 1
        ");
        $arStmt->execute([$userId, $productId]);
        $alreadyReviewed = (bool)$arStmt->fetch();

        // For now allow any logged-in customer to review
        // Later we'll restrict to purchased + delivered only
        $canReview = !$alreadyReviewed;

    } catch (PDOException $e) {
        $canReview = false;
    }
}

// ── Handle review submission ──
$reviewError   = '';
$reviewSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$isLoggedIn) {
        $reviewError = "Please login to leave a review.";
    } elseif ($userRole !== 'customer') {
        $reviewError = "Only customers can leave reviews.";
    } elseif ($alreadyReviewed) {
        $reviewError = "You have already reviewed this product.";
    } else {
        $rating     = (int)($_POST['rating'] ?? 0);
        $reviewText = trim($_POST['review_text'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = "Please select a star rating.";
        } elseif (strlen($reviewText) < 10) {
            $reviewError = "Review must be at least 10 characters.";
        } else {
            try {
                // Insert the review
                $insStmt = $conn->prepare("
                    INSERT INTO reviews (product_id, user_id, rating, review_text)
                    VALUES (?, ?, ?, ?)
                ");
                $insStmt->execute([$productId, $userId, $rating, $reviewText]);

                // Update product average rating and review count
                $conn->prepare("
                    UPDATE products SET
                        rating       = (SELECT AVG(rating) FROM reviews WHERE product_id = ?),
                        review_count = (SELECT COUNT(*)    FROM reviews WHERE product_id = ?)
                    WHERE id = ?
                ")->execute([$productId, $productId, $productId]);

                $reviewSuccess    = "Your review has been submitted!";
                $alreadyReviewed  = true;
                $canReview        = false;

                // Re-fetch reviews to show the new one
                $rStmt->execute([$productId]);
                $reviews = $rStmt->fetchAll();

            } catch (PDOException $e) {
                $reviewError = "Could not submit review. Please try again.";
            }
        }
    }
}

// ── Fetch similar products ──
// Same category, excluding current product
try {
    $simStmt = $conn->prepare("
        SELECT p.id, p.title, p.price, p.image, v.store_name
        FROM products p
        LEFT JOIN vendors v ON p.vendor_id = v.id
        WHERE p.category = ?
          AND p.id != ?
          AND p.is_active = 1
        ORDER BY p.rating DESC
        LIMIT 6
    ");
    $simStmt->execute([$product['category'], $productId]);
    $similarProducts = $simStmt->fetchAll();
} catch (PDOException $e) {
    $similarProducts = [];
}

// ── Cart count for badge ──
$cartCount = 0;
if ($isLoggedIn) {
    try {
        $cStmt = $conn->prepare("
            SELECT SUM(quantity) FROM cart WHERE user_id = ?
        ");
        $cStmt->execute([$userId]);
        $cartCount = (int)$cStmt->fetchColumn();
    } catch (PDOException $e) {
        $cartCount = 0;
    }
} else {
    $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}

// ── Calculate discount percentage ──
$discountPct = 0;
if (!empty($product['old_price']) && $product['old_price'] > $product['price']) {
    $discountPct = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
}

// ── Stock level for progress bar ──
// Assume max stock display is 50 for the bar
$stockPct   = min(100, round(($product['stock'] / 50) * 100));
$stockClass = $product['stock'] > 10 ? 'green' : ($product['stock'] > 3 ? 'amber' : 'red');

// Page title
$pageTitle = htmlspecialchars($product['title']) . ' — Bazaar.pk';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Base styles (variables, navbar) -->
  <link href="../css/style.css"        rel="stylesheet">
  <!-- Shop navbar styles -->
  <link href="../css/styleshop.css"    rel="stylesheet">
  <!-- Product page styles -->
  <link href="../css/styleproduct.css" rel="stylesheet">
</head>
<body>

<!-- ============================================================
     NAVBAR — same as shop page
     ============================================================ -->
<nav class="shop-nav">
  <button class="nav-hamburger" onclick="openDrawer()">
    <div class="hline"></div>
    <div class="hline"></div>
    <div class="hline"></div>
  </button>

  <a href="../shop/shop.php" class="shop-logo">Bazaar<span>.pk</span></a>

  <form class="nav-search" action="../shop/shop.php" method="GET">
    <div class="search-cat">
      All categories
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <input class="search-inp" type="text" name="q" placeholder="Search products, brands, vendors...">
    <button type="submit" class="search-btn">
      <svg viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
    </button>
  </form>

  <div class="nav-right">
    <div class="nav-deliver">
      <span class="flag">&#127477;&#127472;</span>
      <div class="deliver-lbl">
        <span class="deliver-top">Deliver to</span>
        <span class="deliver-city">Lahore</span>
      </div>
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>

    <a href="../cart/cart.php" class="nav-cart">
      <svg viewBox="0 0 24 24">
        <circle cx="9" cy="21" r="1"/>
        <circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/>
      </svg>
      <span class="cart-lbl">Cart</span>
      <div class="cart-count" id="cartBadge"><?= $cartCount ?></div>
    </a>

    <?php if ($isLoggedIn): ?>
      <a href="../logout.php" class="nav-logout-ghost">Logout</a>
    <?php else: ?>
      <a href="../login.php"  class="nav-login-ghost">Login</a>
    <?php endif; ?>
  </div>
</nav>


<!-- ============================================================
     BREADCRUMB
     ============================================================ -->
<div class="breadcrumb">
  <a href="../shop/shop.php" class="bc-item">Home</a>
  <span class="bc-sep">›</span>
  <?php if (!empty($product['category'])): ?>
    <a href="../shop/shop.php?cat=<?= urlencode($product['category']) ?>" class="bc-item">
      <?= ucfirst(htmlspecialchars($product['category'])) ?>
    </a>
    <span class="bc-sep">›</span>
  <?php endif; ?>
  <span class="bc-item active">
    <?= htmlspecialchars(substr($product['title'], 0, 40)) ?>
    <?= strlen($product['title']) > 40 ? '...' : '' ?>
  </span>
</div>


<!-- ============================================================
     MAIN PRODUCT DETAIL
     ============================================================ -->
<div class="prod-detail">

  <!-- ── IMAGE SECTION ── -->
  <div class="img-section">

    <!-- Main image display -->
    <div class="main-img">
      <?php if (!empty($images)): ?>
        <!-- Real product image -->
        <img src="../uploads/products/<?= htmlspecialchars($images[0]) ?>"
             alt="<?= htmlspecialchars($product['title']) ?>"
             id="mainProductImg">
      <?php else: ?>
        <!-- Placeholder if no image uploaded yet -->
        <div class="img-placeholder">
          <svg viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
          </svg>
          <span>No image uploaded</span>
        </div>
      <?php endif; ?>

      <!-- Sale/New/Hot badge -->
      <?php if ($discountPct > 0): ?>
        <div class="img-badge badge-sale">-<?= $discountPct ?>% OFF</div>
      <?php elseif (!empty($product['is_new'])): ?>
        <div class="img-badge badge-new">NEW</div>
      <?php elseif (!empty($product['is_hot'])): ?>
        <div class="img-badge badge-hot">HOT</div>
      <?php endif; ?>

      <!-- Wishlist button -->
      <div class="img-wish" id="wishBtn"
           onclick="toggleWishlist(<?= $productId ?>)">
        <svg viewBox="0 0 24 24">
          <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
        </svg>
      </div>
    </div>

    <!-- Thumbnail strip -->
    <?php if (count($images) > 1): ?>
      <div class="thumb-strip">
        <?php foreach ($images as $i => $img): ?>
          <div class="thumb <?= $i === 0 ? 'active' : '' ?>"
               onclick="switchImage('../uploads/products/<?= htmlspecialchars($img) ?>', this)">
            <img src="../uploads/products/<?= htmlspecialchars($img) ?>"
                 alt="Product image <?= $i + 1 ?>">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div><!-- /img-section -->


  <!-- ── DETAILS SECTION ── -->
  <div class="detail-section">

    <!-- Vendor row -->
    <div class="prod-vendor-row">
      <a href="../shop/shop.php?vendor=<?= $product['vendor_table_id'] ?>"
         class="prod-vendor-name">
        <?= htmlspecialchars($product['store_name'] ?? 'Bazaar.pk') ?>
      </a>
      <?php if ($product['vendor_status'] === 'approved'): ?>
        <span class="verified-badge">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Verified seller
        </span>
      <?php endif; ?>
    </div>

    <!-- Product title -->
    <h1 class="prod-title"><?= htmlspecialchars($product['title']) ?></h1>

    <!-- Rating row -->
    <div class="rating-row">
      <div class="stars-big">
        <?php
          $rating = round($product['rating'] ?? 0);
          for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
              echo '<svg class="star-b" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
            } else {
              echo '<svg class="star-b-e" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
            }
          }
        ?>
      </div>
      <span class="rating-num"><?= number_format($product['rating'] ?? 0, 1) ?></span>
      <a class="rating-count" href="#reviews" onclick="switchTab('reviews')">
        <?= number_format($product['review_count'] ?? 0) ?> reviews
      </a>
      <span class="rating-sep">|</span>
      <span class="sold-count"><?= number_format($product['sales_count'] ?? 0) ?> sold</span>
    </div>

    <!-- Price block -->
    <div class="price-block">
      <span class="price-main">Rs <?= number_format($product['price']) ?></span>
      <?php if ($discountPct > 0): ?>
        <span class="price-old">Rs <?= number_format($product['old_price']) ?></span>
        <span class="price-save">
          Save Rs <?= number_format($product['old_price'] - $product['price']) ?>
        </span>
      <?php endif; ?>
    </div>

    <!-- Stock indicator -->
    <div class="stock-row">
      <?php if ($product['stock'] > 0): ?>
        <div class="stock-dot dot-<?= $stockClass ?>"></div>
        <span class="stock-txt" style="color:var(--<?= $stockClass ?>);">In stock</span>
        <?php if ($product['stock'] <= 15): ?>
          <span class="stock-left">Only <?= $product['stock'] ?> left</span>
          <div class="stock-bar">
            <div class="stock-fill fill-<?= $stockClass ?>"
                 style="width:<?= $stockPct ?>%"></div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="stock-dot dot-red"></div>
        <span class="stock-txt" style="color:var(--red);">Out of stock</span>
      <?php endif; ?>
    </div>

    <!-- Variants: Color, Size, Storage -->
    <?php if (!empty($variants)): ?>
      <div class="variants-section">
        <?php foreach ($variants as $type => $options): ?>
          <div class="var-label"><?= ucfirst(htmlspecialchars($type)) ?></div>

          <?php if ($type === 'color'): ?>
            <div class="color-opts">
              <?php foreach ($options as $i => $opt): ?>
                <div class="color-opt <?= $i === 0 ? 'active' : '' ?>"
                     style="background:<?= htmlspecialchars($opt['variant_value']) ?>;"
                     title="<?= htmlspecialchars($opt['variant_value']) ?>"
                     onclick="selectVariant('color', this, <?= (float)$opt['price_adjust'] ?>)">
                </div>
              <?php endforeach; ?>
            </div>

          <?php else: ?>
            <div class="size-opts">
              <?php foreach ($options as $i => $opt): ?>
                <button class="size-opt <?= $i === 0 ? 'active' : '' ?> <?= $opt['stock'] == 0 ? 'out-of-stock' : '' ?>"
                        onclick="selectVariant('<?= $type ?>', this, <?= (float)$opt['price_adjust'] ?>)"
                        <?= $opt['stock'] == 0 ? 'disabled' : '' ?>>
                  <?= htmlspecialchars($opt['variant_value']) ?>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Quantity + Add to cart + Buy now -->
    <div class="action-row">
      <div class="qty-control">
        <button class="qty-btn" onclick="changeQty(-1)">−</button>
        <div class="qty-val" id="qtyVal">1</div>
        <button class="qty-btn" onclick="changeQty(1)">+</button>
      </div>

      <?php if ($product['stock'] > 0): ?>
        <button class="btn-atc" onclick="addToCart(<?= $productId ?>)">
          + Add to cart
        </button>
        <button class="btn-buy"
                onclick="buyNow(<?= $productId ?>)">
          Buy now
        </button>
      <?php else: ?>
        <button class="btn-atc" disabled
                style="opacity:0.5;cursor:not-allowed;">
          Out of stock
        </button>
      <?php endif; ?>
    </div>

    <!-- Delivery info -->
    <div class="delivery-box">
      <div class="del-row">
        <div class="del-icon">
          <svg viewBox="0 0 24 24">
            <rect x="1" y="3" width="15" height="13"/>
            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
            <circle cx="5.5"  cy="18.5" r="2.5"/>
            <circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
        </div>
        <div class="del-body">
          <div class="del-title">Check delivery to your city</div>
          <div class="del-city-row">
            <input class="del-city-input" type="text" id="cityInput"
                   placeholder="e.g. Karachi, Lahore...">
            <button class="del-check-btn" onclick="checkDelivery()">Check</button>
          </div>
          <div class="del-result" id="delResult"></div>
        </div>
      </div>
      <div class="del-row">
        <div class="del-icon">
          <svg viewBox="0 0 24 24">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </div>
        <div class="del-body">
          <div class="del-title">Free delivery</div>
          <div class="del-sub">On orders above Rs 2,000</div>
        </div>
      </div>
      <div class="del-row">
        <div class="del-icon">
          <svg viewBox="0 0 24 24">
            <polyline points="1 4 1 10 7 10"/>
            <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
          </svg>
        </div>
        <div class="del-body">
          <div class="del-title">Easy 7-day returns</div>
          <div class="del-sub">No questions asked return policy</div>
        </div>
      </div>
    </div>

  </div><!-- /detail-section -->


  <!-- ── VENDOR CARD ── -->
  <div class="vendor-card">
    <div class="vc-logo">
      <?php if (!empty($product['vendor_logo'])): ?>
        <img src="../uploads/vendors/<?= htmlspecialchars($product['vendor_logo']) ?>"
             alt="<?= htmlspecialchars($product['store_name']) ?>">
      <?php else: ?>
        <div class="vc-logo-placeholder">
          <?= strtoupper(substr($product['store_name'] ?? 'B', 0, 1)) ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="vc-info">
      <div class="vc-name">
        <?= htmlspecialchars($product['store_name'] ?? 'Bazaar.pk') ?>
      </div>
      <div class="vc-meta">
        <span>&#11088; <?= number_format($product['vendor_rating'] ?? 0, 1) ?> seller rating</span>
        <span>&#128722; Visit store to see all products</span>
      </div>
    </div>
    <a href="../shop/shop.php?vendor=<?= $product['vendor_table_id'] ?>"
       class="vc-visit">
      Visit store
    </a>
  </div>


  <!-- ── TABS: Description / Specifications / Reviews ── -->
  <div class="tabs-section" id="reviews">
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab('desc',   this)">Description</button>
      <button class="tab-btn"        onclick="switchTab('specs',  this)">Specifications</button>
      <button class="tab-btn"        onclick="switchTab('reviews',this)">
        Reviews (<?= number_format($product['review_count'] ?? 0) ?>)
      </button>
    </div>

    <!-- Description tab -->
    <div class="tab-content active" id="tab-desc">
      <div class="desc-text">
        <?php if (!empty($product['description'])): ?>
          <?php
            // Convert newlines to paragraphs for clean display
            $paras = array_filter(explode("\n\n", $product['description']));
            foreach ($paras as $para) {
              echo '<p>' . nl2br(htmlspecialchars(trim($para))) . '</p>';
            }
          ?>
        <?php else: ?>
          <p style="color:var(--txt3);">No description provided for this product.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Specifications tab -->
    <div class="tab-content" id="tab-specs">
      <?php
        // Specs stored as JSON in a specs column
        // e.g. {"Brand":"Samsung","RAM":"8GB","OS":"Android 14"}
        $specs = [];
        if (!empty($product['specs'])) {
            $specs = json_decode($product['specs'], true) ?? [];
        }
      ?>
      <?php if (!empty($specs)): ?>
        <table class="specs-table">
          <?php foreach ($specs as $key => $val): ?>
            <tr>
              <td><?= htmlspecialchars($key) ?></td>
              <td><?= htmlspecialchars($val) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="color:var(--txt3);font-size:13px;">No specifications added yet.</p>
      <?php endif; ?>
    </div>

    <!-- Reviews tab -->
    <div class="tab-content" id="tab-reviews">

      <!-- Rating summary -->
      <?php if (!empty($reviews)): ?>
        <div class="review-summary">
          <div class="rs-big">
            <div class="rs-num"><?= number_format($product['rating'] ?? 0, 1) ?></div>
            <div class="rs-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg style="width:14px;height:14px;fill:<?= $i <= round($product['rating']) ? 'var(--amber)' : 'var(--bg3)' ?>;" viewBox="0 0 24 24">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              <?php endfor; ?>
            </div>
            <div class="rs-count"><?= number_format($product['review_count'] ?? 0) ?> reviews</div>
          </div>
          <div class="rs-bars">
            <?php
              // Calculate percentage for each star level
              $total = count($reviews);
              for ($star = 5; $star >= 1; $star--) {
                $count = count(array_filter($reviews, fn($r) => $r['rating'] == $star));
                $pct   = $total > 0 ? round(($count / $total) * 100) : 0;
                echo "<div class='rb-row'>
                        <span class='rb-lbl'>{$star}★</span>
                        <div class='rb-bar'>
                          <div class='rb-fill' style='width:{$pct}%'></div>
                        </div>
                        <span class='rb-pct'>{$pct}%</span>
                      </div>";
              }
            ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Review form or prompt -->
      <?php if ($canReview): ?>
        <div class="review-form">
          <div class="rf-title">Write your review</div>
          <?php if ($reviewError):   ?>
            <div style="color:var(--red);font-size:12px;margin-bottom:10px;"><?= $reviewError ?></div>
          <?php endif; ?>
          <?php if ($reviewSuccess): ?>
            <div style="color:var(--green);font-size:12px;margin-bottom:10px;"><?= $reviewSuccess ?></div>
          <?php endif; ?>
          <form method="POST" action="">
            <!-- Star rating selector -->
            <div class="rf-stars" id="starSelector">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="rf-star" viewBox="0 0 24 24"
                     onclick="selectStar(<?= $i ?>)"
                     onmouseover="hoverStar(<?= $i ?>)"
                     onmouseout="unhoverStar()">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="0">
            <textarea class="rf-textarea" name="review_text"
                      placeholder="Share your experience with this product..."
                      required minlength="10"></textarea>
            <button type="submit" name="submit_review" class="rf-submit">
              Submit review
            </button>
          </form>
        </div>

      <?php elseif ($alreadyReviewed): ?>
        <div class="review-login-prompt">
          You have already reviewed this product. Thank you!
        </div>

      <?php elseif (!$isLoggedIn): ?>
        <div class="review-login-prompt">
          <a href="../login.php">Login</a> to leave a review for this product.
        </div>

      <?php elseif ($userRole !== 'customer'): ?>
        <div class="review-login-prompt">
          Only customers can leave product reviews.
        </div>
      <?php endif; ?>

      <!-- All reviews -->
      <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review-card">
            <div class="rc-header">
              <div class="rc-avatar">
                <?= strtoupper(substr($review['full_name'], 0, 2)) ?>
              </div>
              <div>
                <div class="rc-name"><?= htmlspecialchars($review['full_name']) ?></div>
                <div class="rc-date">
                  <?= date('d M Y', strtotime($review['created_at'])) ?>
                </div>
              </div>
            </div>
            <div class="rc-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="rc-star" viewBox="0 0 24 24"
                     style="fill:<?= $i <= $review['rating'] ? 'var(--amber)' : 'var(--bg3)' ?>">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              <?php endfor; ?>
            </div>
            <div class="rc-text">
              <?= htmlspecialchars($review['review_text']) ?>
            </div>
            <span class="rc-helpful">Helpful?</span>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="no-reviews">
          No reviews yet — be the first to review this product!
        </div>
      <?php endif; ?>

    </div><!-- /tab-reviews -->
  </div><!-- /tabs-section -->


  <!-- ── SIMILAR PRODUCTS ── -->
  <?php if (!empty($similarProducts)): ?>
    <div class="similar-section">
      <div class="sim-header">
        <div class="sim-title">You might also <span>like</span></div>
        <a href="../shop/shop.php?cat=<?= urlencode($product['category']) ?>"
           class="sim-see">See all →</a>
      </div>
      <div class="sim-grid">
        <?php foreach ($similarProducts as $sim): ?>
          <a href="product.php?id=<?= $sim['id'] ?>" class="sim-card">
            <div class="sim-img">
              <?php if (!empty($sim['image'])): ?>
                <img src="../uploads/products/<?= htmlspecialchars($sim['image']) ?>"
                     alt="<?= htmlspecialchars($sim['title']) ?>">
              <?php else: ?>
                <span class="sim-img-placeholder">No image</span>
              <?php endif; ?>
            </div>
            <div class="sim-body">
              <div class="sim-name">
                <?= htmlspecialchars(substr($sim['title'], 0, 40)) ?>
                <?= strlen($sim['title']) > 40 ? '...' : '' ?>
              </div>
              <div class="sim-price">Rs <?= number_format($sim['price']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div><!-- /prod-detail -->


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── Image thumbnail switcher ──
function switchImage(src, thumb) {
    document.getElementById('mainProductImg').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

// ── Tab switcher ──
function switchTab(name, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    // Find the button — btn may be passed or we find it by name
    if (btn) {
        btn.classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn').forEach(b => {
            if (b.getAttribute('onclick')?.includes(name)) b.classList.add('active');
        });
    }
    const content = document.getElementById('tab-' + name);
    if (content) content.classList.add('active');
}

// ── Quantity control ──
let qty = 1;
const maxStock = <?= (int)$product['stock'] ?>;
function changeQty(delta) {
    qty = Math.max(1, Math.min(maxStock, qty + delta));
    document.getElementById('qtyVal').textContent = qty;
}

// ── Variant selector ──
let priceAdjust = 0;
const basePrice = <?= (float)$product['price'] ?>;
function selectVariant(type, el, adjust) {
    // Deselect siblings of same type
    el.closest(type === 'color' ? '.color-opts' : '.size-opts')
      .querySelectorAll('.color-opt, .size-opt')
      .forEach(o => o.classList.remove('active'));
    el.classList.add('active');

    // Update displayed price if variant has price adjustment
    priceAdjust = adjust;
    const newPrice = basePrice + priceAdjust;
    document.querySelector('.price-main').textContent =
        'Rs ' + Math.round(newPrice).toLocaleString();
}

// ── Wishlist toggle ──
function toggleWishlist(productId) {
    const btn = document.getElementById('wishBtn');
    btn.classList.toggle('wishlisted');

    fetch('../api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.redirect) window.location.href = data.redirect;
    })
    .catch(() => {});
}

// ── Add to cart ──
function addToCart(productId) {
    fetch('../cart/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('cartBadge');
            badge.textContent = data.cart_count;
            badge.style.transform = 'scale(1.5)';
            setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
            // Show quick feedback
            const btn = document.querySelector('.btn-atc');
            btn.textContent = '✓ Added!';
            btn.style.background = 'var(--teal)';
            btn.style.color = '#0f1117';
            setTimeout(() => {
                btn.textContent = '+ Add to cart';
                btn.style.background = '';
                btn.style.color = '';
            }, 1500);
        } else if (data.redirect) {
            window.location.href = data.redirect;
        }
    })
    .catch(() => {});
}

// ── Buy now — add to cart then go to cart ──
function buyNow(productId) {
    fetch('../cart/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '../cart/cart.php';
        } else if (data.redirect) {
            window.location.href = data.redirect;
        }
    });
}

// ── Delivery city checker ──
function checkDelivery() {
    const city   = document.getElementById('cityInput').value.trim();
    const result = document.getElementById('delResult');
    if (!city) return;

    // Simple city list for demo — in real app this hits an API
    const cities = {
        'lahore':    'Delivery in 1-2 days — Free',
        'karachi':   'Delivery in 2-3 days — Free',
        'islamabad': 'Delivery in 1-2 days — Free',
        'rawalpindi':'Delivery in 2-3 days — Rs 150',
        'peshawar':  'Delivery in 3-4 days — Rs 200',
        'quetta':    'Delivery in 4-5 days — Rs 250',
        'multan':    'Delivery in 2-3 days — Rs 150',
        'faisalabad':'Delivery in 2-3 days — Free',
    };

    const info = cities[city.toLowerCase()];
    result.style.display = 'block';
    result.style.color   = info ? 'var(--green)' : 'var(--red)';
    result.textContent   = info ?? 'Delivery not available to this city yet.';
}

// ── Star rating selector for review form ──
let selectedStar = 0;
function selectStar(n) {
    selectedStar = n;
    document.getElementById('ratingInput').value = n;
    paintStars(n);
}
function hoverStar(n)  { paintStars(n); }
function unhoverStar() { paintStars(selectedStar); }
function paintStars(n) {
    document.querySelectorAll('.rf-star').forEach((s, i) => {
        s.classList.toggle('selected', i < n);
    });
}

// ── Drawer (same as shop page) ──
function openDrawer()  {
    // Drawer HTML would be here in full implementation
    // For now just link to shop drawer
}
</script>

</body>
</html>