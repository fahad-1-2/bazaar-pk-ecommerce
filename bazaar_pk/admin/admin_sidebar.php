<?php
// admin/admin_sidebar.php
// Shared sidebar included in all admin panel pages
$currentPage = $currentPage ?? '';

// Count pending vendors for badge
$pendingVendors = 0;
try {
    $pvStmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE status = 'pending'");
    $pvStmt->execute();
    $pendingVendors = (int)$pvStmt->fetchColumn();
} catch (PDOException $e) {}
?>
<div class="admin-sidebar">

  <div class="as-logo">
    <div class="as-logo-icon">B</div>
    <div>
      <div class="as-logo-title">Bazaar.pk</div>
      <div class="as-logo-sub">Admin Panel</div>
    </div>
  </div>

  <div class="as-menu">

    <div class="as-section-label">Overview</div>

    <a href="admin_panel.php" class="as-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      <span>Dashboard</span>
    </a>

    <div class="as-section-label">Manage</div>

    <a href="manage_vendors.php" class="as-item <?= $currentPage === 'vendors' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Vendors</span>
      <?php if ($pendingVendors > 0): ?>
        <span class="as-badge"><?= $pendingVendors ?></span>
      <?php endif; ?>
    </a>

    <a href="manage_users.php" class="as-item <?= $currentPage === 'users' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Users</span>
    </a>

    <a href="manage_products.php" class="as-item <?= $currentPage === 'products' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      <span>Products</span>
    </a>

    <a href="manage_orders.php" class="as-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      <span>Orders</span>
    </a>

    <a href="manage_categories.php" class="as-item <?= $currentPage === 'categories' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      <span>Categories</span>
    </a>

    <div class="as-section-label">Reports</div>

    <a href="reports.php" class="as-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Revenue reports</span>
    </a>

    <div class="as-divider"></div>

    <a href="../shop/shop.php" class="as-item" target="_blank">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
      <span>View shop</span>
    </a>

    <a href="../logout.php" class="as-item" style="color:var(--red);">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Logout</span>
    </a>

  </div>
</div>