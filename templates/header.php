<?php
// templates/header.php
declare(strict_types=1);

// Dynamic theme loading
$userTheme = $_SESSION['user']['theme'] ?? DEFAULT_THEME;
$themePath = 'assets/css/themes/' . $userTheme . '.css';

// Fallback to default theme if user theme doesn't exist
if (!file_exists(BASE_PATH . $themePath)) {
    $userTheme = DEFAULT_THEME;
    $themePath = 'assets/css/themes/' . $userTheme . '.css';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'AlmaTech SMS') ?></title>

  <!-- Bootstrap 5 (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  
  <!-- Dynamic Theme CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?><?= htmlspecialchars($themePath) ?>">
  
  <!-- Custom head content -->
  <?php if (isset($customHead)): ?>
    <?= $customHead ?>
  <?php endif; ?>
  
  <!-- Base URL for JavaScript -->
  <script>
    const BASE_URL = "<?= BASE_URL ?>";
  </script>
</head>
<body>
  <!-- Navigation or header content can be added here -->
  <div class="app-container">