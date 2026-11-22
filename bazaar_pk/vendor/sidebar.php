<?php
// vendor/sidebar.php
// Shared sidebar included in all vendor panel pages
// $currentPage variable set in each page to highlight active item
$currentPage = $currentPage ?? '';
?>
<div class="vendor-sidebar">
  <div class="vs-logo">
    <div class="vs-logo-icon">B</div>
    <div class="vs-logo-text">Bazaar.pk<br><span style="font-size:10px;color:var(--txt3);font-weight:400;">Vendor Panel</span></div>
  </div>

  <div class="vs-menu">

    <a href="vendor_panel.php" class="vs-menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#9783;</span>
      <span>Dashboard</span>
    </a>

    <a href="add_product.php" class="vs-menu-item <?= $currentPage === 'add_product' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#43;</span>
      <span>Add product</span>
    </a>

    <a href="my_products.php" class="vs-menu-item <?= $currentPage === 'my_products' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#9783;</span>
      <span>My products</span>
    </a>

    <a href="orders.php" class="vs-menu-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#128230;</span>
      <span>Orders</span>
    </a>

    <a href="store_profile.php" class="vs-menu-item <?= $currentPage === 'store_profile' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#127968;</span>
      <span>Store profile</span>
    </a>

    <a href="analytics.php" class="vs-menu-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
      <span class="vs-menu-icon">&#128200;</span>
      <span>Analytics</span>
    </a>

    <div class="vs-menu-divider"></div>

    <a href="../shop/shop.php" class="vs-menu-item" target="_blank">
      <span class="vs-menu-icon">&#128722;</span>
      <span>View shop</span>
    </a>

    <a href="../logout.php" class="vs-menu-item" style="color:var(--red);">
      <span class="vs-menu-icon">&#8594;</span>
      <span>Logout</span>
    </a>

  </div>
</div>