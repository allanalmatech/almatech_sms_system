<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$stmt = $db->prepare('SELECT t.id, t.user_id, u.username, t.phone_to_charge, t.amount, t.sms_units, t.provider, t.status, t.created_at FROM topups t INNER JOIN users u ON u.id=t.user_id ORDER BY t.status="pending" DESC, t.id DESC LIMIT 300');
$stmt->execute();
$topups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Payment Approvals • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Top-Up Approvals</h1>
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>User</th><th>Payer Phone</th><th>Amount</th><th>Units</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($topups as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><?= e((string)$t['username']) ?></td>
              <td><?= e((string)$t['phone_to_charge']) ?></td>
              <td><?= number_format((int)$t['amount']) ?> UGX</td>
              <td><?= number_format((int)$t['sms_units']) ?></td>
              <td><span class="badge text-bg-<?= $t['status'] === 'approved' ? 'success' : ($t['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e((string)$t['status']) ?></span></td>
              <td><?= e((string)$t['created_at']) ?></td>
              <td>
                <?php if ($t['status'] === 'pending'): ?>
                  <form method="post" action="<?= BASE_URL ?>modules/admin/payments/approve.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="topup_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-success">Approve</button>
                  </form>
                  <form method="post" action="<?= BASE_URL ?>modules/admin/payments/reject.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="topup_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">No actions</span>
                <?php endif; ?>
              </td>
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
