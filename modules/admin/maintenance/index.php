<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $maintenanceMessage = trim((string)($_POST['maintenance_message'] ?? ''));
    if ($maintenanceMessage === '') {
        $error = 'Maintenance message is required.';
    } else {
        $stmt = $db->prepare('INSERT INTO app_settings (`key`,`value`) VALUES ("maintenance_message", ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        $stmt->bind_param('s', $maintenanceMessage);
        $stmt->execute();
        $stmt->close();

        audit_log($db, (int)$_SESSION['user']['id'], 'maintenance.message.update', 'app_settings', null);
        $message = 'Maintenance message updated.';
    }
}

$enabled = app_setting($db, 'maintenance_enabled', '0') === '1';
$maintenanceMessage = app_setting($db, 'maintenance_message', 'We are performing scheduled maintenance. Please try again later.');

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
  <title>Maintenance Settings • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Maintenance Mode</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="mb-2">Current status:</div>
            <div class="h5 mb-3"><span class="badge text-bg-<?= $enabled ? 'warning' : 'success' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></div>
            <form method="post" action="<?= BASE_URL ?>modules/admin/maintenance/toggle.php">
              <?= csrf_field() ?>
              <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
              <button class="btn btn-<?= $enabled ? 'success' : 'warning' ?> w-100">
                <?= $enabled ? 'Disable Maintenance' : 'Enable Maintenance' ?>
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Maintenance Message</h5>
            <form method="post">
              <?= csrf_field() ?>
              <textarea class="form-control mb-3" name="maintenance_message" rows="5" required><?= e($maintenanceMessage) ?></textarea>
              <button class="btn btn-primary">Save Message</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
