<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT id, username, full_name, email, phone, role_id, status, sms_balance, created_at FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 300');
    $stmt->bind_param('sss', $like, $like, $like);
} else {
    $stmt = $db->prepare('SELECT id, username, full_name, email, phone, role_id, status, sms_balance, created_at FROM users ORDER BY id DESC LIMIT 300');
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$theme = $_SESSION['user']['theme'] ?? DEFAULT_THEME;
$themePath = 'assets/css/themes/' . $theme . '.css';
if (!file_exists(BASE_PATH . $themePath)) {
    $themePath = 'assets/css/themes/' . DEFAULT_THEME . '.css';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Users • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0">User Management</h1>
      <form class="d-flex" method="get">
        <input class="form-control me-2" name="q" value="<?= e($q) ?>" placeholder="Search users">
        <button class="btn btn-outline-secondary">Search</button>
      </form>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Status</th><th>Balance</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td>
                <strong><?= e((string)$u['username']) ?></strong>
                <div class="small text-muted"><?= e((string)($u['full_name'] ?? '')) ?><?= $u['email'] ? ' • ' . e((string)$u['email']) : '' ?></div>
              </td>
              <td><?= (int)$u['role_id'] === 1 ? 'Admin' : 'Client' ?></td>
              <td><span class="badge text-bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e((string)$u['status']) ?></span></td>
              <td><?= number_format((int)$u['sms_balance']) ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>modules/admin/users/edit.php?id=<?= (int)$u['id'] ?>">Edit</a>
                <?php if ($u['status'] === 'active'): ?>
                  <form method="post" action="<?= BASE_URL ?>modules/admin/users/deactivate.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="<?= BASE_URL ?>modules/admin/users/activate.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-success">Activate</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
