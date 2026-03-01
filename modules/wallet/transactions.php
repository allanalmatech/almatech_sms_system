<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT id, type, sms_units, amount, currency, reference, status, created_at FROM wallet_transactions WHERE user_id=? AND (type LIKE ? OR reference LIKE ?) ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('iss', $userId, $like, $like);
} else {
    $stmt = $db->prepare('SELECT id, type, sms_units, amount, currency, reference, status, created_at FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Wallet Transactions • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Transactions</h1>
      <form class="d-flex" method="get">
        <input class="form-control me-2" name="q" value="<?= e($q) ?>" placeholder="Filter by type/reference">
        <button class="btn btn-outline-secondary">Filter</button>
      </form>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Type</th><th>Units</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= e((string)$t['type']) ?></td>
                <td><?= number_format((int)$t['sms_units']) ?></td>
                <td><?= number_format((int)$t['amount']) . ' ' . e((string)$t['currency']) ?></td>
                <td><?= e((string)$t['reference']) ?></td>
                <td><span class="badge text-bg-<?= $t['status'] === 'approved' ? 'success' : ($t['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e((string)$t['status']) ?></span></td>
                <td><?= e((string)$t['created_at']) ?></td>
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
