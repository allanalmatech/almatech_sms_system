<?php
declare(strict_types=1);

$user = $_SESSION['user'] ?? [];
$userId = (int)($user['id'] ?? 0);
$unreadCount = 0;
if ($userId > 0 && isset($db) && $db instanceof mysqli) {
    $unreadCount = notification_unread_count($db, $userId);
}
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= BASE_URL ?>dashboard.php">AlmaTech SMS</a>
    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="btn btn-outline-secondary position-relative" href="<?= BASE_URL ?>modules/notifications/index.php">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
          </span>
        <?php endif; ?>
      </a>
      <span class="small text-muted"><?= htmlspecialchars((string)($user['username'] ?? 'Guest')) ?></span>
    </div>
  </div>
</nav>
