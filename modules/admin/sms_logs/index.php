<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT l.id, l.sender_id_text, l.recipients_preview, l.message_preview, l.sms_units, l.status_label, l.created_at, u.username FROM sms_logs l INNER JOIN users u ON u.id=l.user_id WHERE u.username LIKE ? OR l.sender_id_text LIKE ? OR l.message_preview LIKE ? ORDER BY l.id DESC LIMIT 500');
    $stmt->bind_param('sss', $like, $like, $like);
} else {
    $stmt = $db->prepare('SELECT l.id, l.sender_id_text, l.recipients_preview, l.message_preview, l.sms_units, l.status_label, l.created_at, u.username FROM sms_logs l INNER JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 500');
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Global SMS Logs • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Global SMS Logs</h1>
      <form class="d-flex" method="get">
        <input class="form-control me-2" name="q" value="<?= e($q) ?>" placeholder="Search logs">
        <button class="btn btn-outline-secondary">Search</button>
      </form>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>User</th><th>Sender</th><th>Recipients</th><th>Message</th><th>Units</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= (int)$l['id'] ?></td>
                <td><?= e((string)$l['username']) ?></td>
                <td><?= e((string)$l['sender_id_text']) ?></td>
                <td><?= e((string)$l['recipients_preview']) ?></td>
                <td><?= e((string)$l['message_preview']) ?></td>
                <td><?= number_format((int)$l['sms_units']) ?></td>
                <td><?= e((string)$l['status_label']) ?></td>
                <td><?= e((string)$l['created_at']) ?></td>
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
