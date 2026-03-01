<?php
// templates/page_template.php
// Standard page template with sidebar
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// Authentication check (can be overridden per page)
if (empty($_SESSION['user']['id'])) {
    redirect(BASE_URL . 'login.php');
}

// Maintenance enforcement (admin allowed, others blocked)
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
if ($maintenanceEnabled && !is_admin_role($_SESSION['user']['role_id'] ?? null)) {
    redirect(BASE_URL . 'maintenance.php');
}

// Page variables (set these before including this template)
$pageTitle = $pageTitle ?? 'AlmaTech SMS';
$customHead = $customHead ?? '';
$user = $_SESSION['user'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> • AlmaTech SMS</title>

  <!-- Bootstrap 5 (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  
  <!-- Dynamic Theme CSS -->
  <?php
  $userTheme = $_SESSION['user']['theme'] ?? DEFAULT_THEME;
  $themePath = 'assets/css/themes/' . $userTheme . '.css';
  if (!file_exists(BASE_PATH . $themePath)) {
      $userTheme = DEFAULT_THEME;
      $themePath = 'assets/css/themes/' . $userTheme . '.css';
  }
  ?>
  <link rel="stylesheet" href="<?= BASE_URL ?><?= htmlspecialchars($themePath) ?>">
  
  <!-- Custom head content -->
  <?= $customHead ?>
  
  <!-- Base URL for JavaScript -->
  <script>
    const BASE_URL = "<?= BASE_URL ?>";
  </script>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="app-shell">
  <!-- Page content goes here -->
  <?= $pageContent ?? '' ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<script>
// Make BASE_URL available to JavaScript
window.BASE_URL = '<?= BASE_URL ?>';
</script>
</body>
</html>
