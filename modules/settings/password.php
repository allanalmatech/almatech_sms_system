<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password confirmation does not match.';
    } else {
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hash = (string)($row['password_hash'] ?? '');
        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();
            $stmt->close();

            audit_log($db, $userId, 'settings.password.update', 'users', $userId);
            $message = 'Password changed successfully.';
        }
    }
}

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
  <title>Password Settings • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Change Password</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm" style="max-width:720px;">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" name="new_password" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" name="confirm_password" minlength="8" required>
          </div>
          <button class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
