<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'mark_all') {
        mark_all_notifications_read($db, $userId);
        $message = 'All notifications marked as read.';
    } elseif ($action === 'mark_one') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            mark_notification_read($db, $userId, $notificationId);
            $message = 'Notification marked as read.';
        }
    }
}

$stmt = $db->prepare('SELECT id, title, body, url, type, is_read, created_at, read_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 200');
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Notifications • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Notifications</h1>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button class="btn btn-outline-primary">Mark All as Read</button>
      </form>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="list-group list-group-flush">
      <?php foreach ($notifications as $n): ?>
        <div class="list-group-item d-flex justify-content-between align-items-start <?= (int)$n['is_read'] === 0 ? 'bg-light' : '' ?>">
          <div class="me-3">
            <div class="fw-semibold"><?= e((string)$n['title']) ?></div>
            <div class="text-muted small mb-1"><?= e((string)$n['body']) ?></div>
            <div class="small text-muted"><?= e((string)$n['created_at']) ?><?= !empty($n['type']) ? ' • ' . e((string)$n['type']) : '' ?></div>
            <?php if (!empty($n['url'])): ?>
              <a class="small" href="<?= e((string)$n['url']) ?>">Open</a>
            <?php endif; ?>
          </div>
          <?php if ((int)$n['is_read'] === 0): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="mark_one">
              <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary">Mark Read</button>
            </form>
          <?php else: ?>
            <span class="badge text-bg-success">Read</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
