<?php
// admin/manage_users.php
// Admin views, edits role, bans, deletes all users

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$userName    = $_SESSION['full_name'];
$currentPage = 'users';
$flash       = ''; $flashType = '';

// ── Actions ──
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== (int)$_SESSION['user_id']) {
        try {
            $conn->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            $flash = "User deleted."; $flashType = 'success';
        } catch (PDOException $e) { $flash = "Error deleting user."; $flashType = 'error'; }
    } else {
        $flash = "You cannot delete your own account."; $flashType = 'error';
    }
}
if (isset($_GET['make_admin'])) {
    $uid = (int)$_GET['make_admin'];
    try {
        $conn->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$uid]);
        $flash = "User promoted to admin."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}
if (isset($_GET['make_customer'])) {
    $uid = (int)$_GET['make_customer'];
    try {
        $conn->prepare("UPDATE users SET role='customer' WHERE id=?")->execute([$uid]);
        $flash = "Role changed to customer."; $flashType = 'success';
    } catch (PDOException $e) { $flash = "Error."; $flashType = 'error'; }
}

// ── Filters ──
$filterRole = $_GET['role'] ?? '';
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page-1) * $perPage;

try {
    $sql    = "SELECT * FROM users WHERE 1=1";
    $params = [];
    if ($filterRole !== '') { $sql .= " AND role=?"; $params[] = $filterRole; }
    if ($search !== '')     { $sql .= " AND (full_name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $cntStmt = $conn->prepare(str_replace("SELECT *","SELECT COUNT(*)",$sql));
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount/$perPage);

    $sql .= " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = []; $totalCount = 0; $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css"      rel="stylesheet">
  <link href="../css/styleadmin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <?php include 'admin_sidebar.php'; ?>
  <div class="admin-main">

    <div class="admin-header">
      <div class="ah-left"><div class="ah-title">Manage users</div></div>
      <div class="ah-right">
        <span class="ah-admin-badge">Admin</span>
        <div class="ah-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

      <!-- Role tabs -->
      <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach (['' => 'All', 'customer' => 'Customers', 'vendor' => 'Vendors', 'admin' => 'Admins'] as $val => $lbl): ?>
          <a href="manage_users.php?role=<?= $val ?>&q=<?= urlencode($search) ?>"
             style="padding:6px 14px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;
                    border:1px solid <?= $filterRole===$val ? 'var(--teal)' : 'var(--border)' ?>;
                    color:<?= $filterRole===$val ? 'var(--teal)' : 'var(--txt2)' ?>;
                    background:<?= $filterRole===$val ? 'var(--teal-dim)' : 'transparent' ?>;">
            <?= $lbl ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <form method="GET" class="admin-filters">
        <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>">
        <input class="af-input" type="text" name="q" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="af-btn af-btn-primary">Search</button>
        <a href="manage_users.php" class="af-btn af-btn-ghost" style="text-decoration:none;display:flex;align-items:center;">Reset</a>
      </form>

      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;"><?= $totalCount ?> user(s) found</div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--txt3);padding:28px;">No users found</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td style="color:var(--txt3);font-size:11px;"><?= $u['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div class="tbl-avatar" style="background:var(--teal-dim);color:var(--teal);">
                        <?= strtoupper(substr($u['full_name'],0,1)) ?>
                      </div>
                      <div>
                        <div style="font-size:12px;font-weight:500;color:var(--txt);"><?= htmlspecialchars($u['full_name']) ?></div>
                        <?php if ($u['id'] === (int)$_SESSION['user_id']): ?>
                          <div style="font-size:9px;color:var(--teal);">You</div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td style="color:var(--txt2);font-size:11px;"><?= htmlspecialchars($u['email']) ?></td>
                  <td><span class="status-badge sb-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                  <td style="color:var(--txt2);font-size:11px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div class="tbl-actions">
                      <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <?php if ($u['role'] !== 'admin'): ?>
                          <a href="manage_users.php?make_admin=<?= $u['id'] ?>&role=<?= urlencode($filterRole) ?>"
                             class="tbl-btn" onclick="return confirm('Make this user an admin?')">Make admin</a>
                        <?php endif; ?>
                        <?php if ($u['role'] !== 'customer'): ?>
                          <a href="manage_users.php?make_customer=<?= $u['id'] ?>&role=<?= urlencode($filterRole) ?>"
                             class="tbl-btn">Make customer</a>
                        <?php endif; ?>
                        <a href="manage_users.php?delete=<?= $u['id'] ?>&role=<?= urlencode($filterRole) ?>"
                           class="tbl-btn delete" onclick="return confirm('Delete this user permanently?')">Delete</a>
                      <?php else: ?>
                        <span style="font-size:10px;color:var(--txt3);">Current account</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
          <div class="admin-pagination">
            <div class="pagination-info">Showing <?= ($offset+1) ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
            <div class="pagination-btns">
              <?php for ($pg=1; $pg<=$totalPages; $pg++): ?>
                <a href="?page=<?= $pg ?>&role=<?= urlencode($filterRole) ?>&q=<?= urlencode($search) ?>"
                   class="pg-btn <?= $pg===$page ? 'active' : '' ?>"><?= $pg ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>