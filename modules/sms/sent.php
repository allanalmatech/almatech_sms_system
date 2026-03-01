<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$q = trim((string)($_GET['q'] ?? ''));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sms_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Sender', 'Recipients', 'Message', 'Units', 'Status', 'Created At']);

    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $db->prepare('SELECT id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at FROM sms_logs WHERE user_id=? AND (sender_id_text LIKE ? OR message_preview LIKE ?) ORDER BY id DESC LIMIT 1000');
        $stmt->bind_param('iss', $userId, $like, $like);
    } else {
        $stmt = $db->prepare('SELECT id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at FROM sms_logs WHERE user_id=? ORDER BY id DESC LIMIT 1000');
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['sender_id_text'], $r['recipients_preview'], $r['message_preview'], $r['sms_units'], $r['status_label'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at FROM sms_logs WHERE user_id=? AND (sender_id_text LIKE ? OR message_preview LIKE ?) ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('iss', $userId, $like, $like);
} else {
    $stmt = $db->prepare('SELECT id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at FROM sms_logs WHERE user_id=? ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('i', $userId);
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
  <title>Sent Messages • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Sent Messages</h1>
      <div class="d-flex gap-2">
        <form class="d-flex" method="get">
          <input class="form-control me-2" name="q" value="<?= e($q) ?>" placeholder="Search logs">
          <button class="btn btn-outline-secondary">Search</button>
        </form>
        <a class="btn btn-outline-primary" href="<?= BASE_URL ?>modules/sms/sent.php?export=csv<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Export CSV</a>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Sender</th><th>Recipients</th><th>Message</th><th>Units</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= (int)$l['id'] ?></td>
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
