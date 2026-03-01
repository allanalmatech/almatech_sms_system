<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$sent = (int)($_GET['sent'] ?? 0);

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
  <title>Broadcast Message • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Broadcast Announcement</h1>

    <?php if ($sent > 0): ?>
      <div class="alert alert-success">Broadcast sent to <?= number_format($sent) ?> users.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="max-width:900px;">
      <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>modules/admin/broadcast/send.php">
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="120"></div>
          <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="6" required></textarea></div>
          <div class="mb-3"><label class="form-label">Link (optional)</label><input class="form-control" name="url" placeholder="<?= BASE_URL ?>dashboard.php"></div>
          <button class="btn btn-primary">Send Broadcast</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
