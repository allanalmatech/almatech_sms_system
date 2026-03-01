<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$isAdmin = is_admin_role($_SESSION['user']['role_id'] ?? null);

if ($isAdmin) {
    $sql = 'SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at,
               u.username AS owner_username,
               (SELECT COUNT(*) FROM thread_messages tm
                 LEFT JOIN message_reads mr ON mr.message_id = tm.id AND mr.user_id = ?
                 WHERE tm.thread_id = t.id AND tm.from_user_id <> ? AND mr.message_id IS NULL
               ) AS unread_count,
               (SELECT MAX(created_at) FROM thread_messages WHERE thread_id = t.id) AS last_message_at
            FROM message_threads t
            INNER JOIN users u ON u.id = t.created_by
            ORDER BY COALESCE(last_message_at, t.created_at) DESC
            LIMIT 200';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $userId, $userId);
} else {
    $sql = 'SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at,
               u.username AS owner_username,
               (SELECT COUNT(*) FROM thread_messages tm
                 LEFT JOIN message_reads mr ON mr.message_id = tm.id AND mr.user_id = ?
                 WHERE tm.thread_id = t.id AND tm.from_user_id <> ? AND mr.message_id IS NULL
               ) AS unread_count,
               (SELECT MAX(created_at) FROM thread_messages WHERE thread_id = t.id) AS last_message_at
            FROM message_threads t
            INNER JOIN users u ON u.id = t.created_by
            INNER JOIN thread_participants tp ON tp.thread_id = t.id AND tp.user_id = ?
            ORDER BY COALESCE(last_message_at, t.created_at) DESC
            LIMIT 200';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iii', $userId, $userId, $userId);
}

$stmt->execute();
$threads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Inbox • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Support Inbox</h1>
      <a class="btn btn-primary" href="<?= BASE_URL ?>modules/messaging/compose.php"><i class="bi bi-plus-lg me-1"></i>New Ticket</a>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Unread</th><th>Updated</th></tr></thead>
          <tbody>
          <?php foreach ($threads as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td>
                <a href="<?= BASE_URL ?>modules/messaging/thread.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none">
                  <?= e((string)$t['subject']) ?>
                </a>
                <div class="small text-muted">Owner: <?= e((string)$t['owner_username']) ?></div>
              </td>
              <td><?= e((string)$t['category']) ?></td>
              <td><?= e((string)$t['priority']) ?></td>
              <td><?= e((string)$t['status']) ?></td>
              <td>
                <?php if ((int)$t['unread_count'] > 0): ?>
                  <span class="badge text-bg-danger"><?= (int)$t['unread_count'] ?></span>
                <?php else: ?>
                  <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
              <td><?= e((string)($t['last_message_at'] ?? $t['created_at'])) ?></td>
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
