<?php
// dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// ------------------------------------------------------------  
// Authentication & Authorization
// ------------------------------------------------------------
if (empty($_SESSION['user']['id'])) {
    redirect(BASE_URL . 'login.php');
}

// Maintenance enforcement (admin allowed, others blocked)
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
if ($maintenanceEnabled && !is_admin_role($_SESSION['user']['role_id'] ?? null)) {
    redirect(BASE_URL . 'maintenance.php');
}

// ------------------------------------------------------------  
// Dashboard Data
// ------------------------------------------------------------
$user = $_SESSION['user'];

// Get SMS statistics for current user
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_sent,
        SUM(CASE WHEN status_label = 'Delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status_label = 'Failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as today_sent
    FROM sms_logs 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$smsStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent SMS messages
$stmt = $db->prepare("
    SELECT message_preview as message, recipients_preview as recipient, status_label as status, created_at
    FROM sms_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$recentMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get wallet balance
$walletBalance = $user['sms_balance'];

$pageTitle = "Dashboard";
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
  
  <!-- Base URL for JavaScript -->
  <script>
    const BASE_URL = "<?= BASE_URL ?>";
  </script>
</head>
<body>

<?php include __DIR__ . '/templates/sidebar.php'; ?>

<div class="app-shell">
  <div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</p>
      </div>
      <div class="text-end">
        <div class="text-muted small">SMS Balance</div>
        <div class="h4 mb-0 text-primary"><?= number_format($walletBalance) ?></div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                  <i class="bi bi-send text-primary fs-4"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <div class="text-muted small">Total Sent</div>
                <div class="h5 mb-0"><?= number_format($smsStats['total_sent'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-success bg-opacity-10 rounded-3 p-3">
                  <i class="bi bi-check-circle text-success fs-4"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <div class="text-muted small">Delivered</div>
                <div class="h5 mb-0"><?= number_format($smsStats['delivered'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                  <i class="bi bi-x-circle text-danger fs-4"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <div class="text-muted small">Failed</div>
                <div class="h5 mb-0"><?= number_format($smsStats['failed'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                  <i class="bi bi-calendar-day text-info fs-4"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <div class="text-muted small">Today</div>
                <div class="h5 mb-0"><?= number_format($smsStats['today_sent'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Messages -->
    <div class="row">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Recent Messages</h5>
              <a href="<?= BASE_URL ?>modules/sms/sent.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
          </div>
          <div class="card-body">
            <?php if (empty($recentMessages)): ?>
              <div class="text-center py-4 text-muted">
                <i class="bi bi-chat-dots fs-1 d-block mb-3"></i>
                No messages sent yet. <a href="<?= BASE_URL ?>modules/sms/compose.php">Send your first SMS</a>.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Recipient</th>
                      <th>Message</th>
                      <th>Status</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentMessages as $msg): ?>
                      <tr>
                        <td><?= htmlspecialchars($msg['recipient']) ?></td>
                        <td>
                          <span class="text-truncate d-block" style="max-width: 200px;" title="<?= htmlspecialchars($msg['message']) ?>">
                            <?= htmlspecialchars(substr($msg['message'], 0, 50)) ?><?= strlen($msg['message']) > 50 ? '...' : '' ?>
                          </span>
                        </td>
                        <td>
                          <?php
                          $statusClass = match ($msg['status']) {
                            'Delivered' => 'success',
                            'Sent' => 'primary', 
                            'Failed' => 'danger',
                            'Low balance' => 'warning',
                            default => 'secondary',
                          };
                          ?>
                          <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($msg['status']) ?></span>
                        </td>
                        <td class="text-muted small"><?= date('M j, H:i', strtotime($msg['created_at'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0">
            <h5 class="mb-0">Quick Actions</h5>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="<?= BASE_URL ?>modules/sms/compose.php" class="btn btn-primary">
                <i class="bi bi-send me-2"></i> Compose SMS
              </a>
              <a href="<?= BASE_URL ?>modules/phonebook/contacts.php" class="btn btn-outline-primary">
                <i class="bi bi-people me-2"></i> Manage Contacts
              </a>
              <a href="<?= BASE_URL ?>modules/wallet/buy.php" class="btn btn-outline-success">
                <i class="bi bi-cart-plus me-2"></i> Buy SMS Credits
              </a>
              <a href="<?= BASE_URL ?>modules/settings/profile.php" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-2"></i> Settings
              </a>
            </div>
          </div>
        </div>

        <!-- System Status -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-header bg-white border-0">
            <h5 class="mb-0">System Status</h5>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>SMS Gateway</span>
              <span class="badge bg-success">Online</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>Database</span>
              <span class="badge bg-success">Connected</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span>Maintenance</span>
              <span class="badge bg-<?= $maintenanceEnabled ? 'warning' : 'success' ?>">
                <?= $maintenanceEnabled ? 'On' : 'Off' ?>
              </span>
            </div>
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