<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$message = '';
$error = '';

$minTopup = (int)app_setting($db, 'min_topup_amount', '15000');
if ($minTopup <= 0) {
    $minTopup = 15000;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $amount = (int)($_POST['amount'] ?? 0);
    $phone = trim((string)($_POST['phone_to_charge'] ?? ''));
    $provider = trim((string)($_POST['provider'] ?? 'manual'));

    if ($amount < $minTopup) {
        $error = 'Minimum top-up amount is ' . number_format($minTopup) . ' UGX.';
    } elseif (normalize_ug_phone($phone) === null) {
        $error = 'Enter a valid payer phone number.';
    } else {
        $smsUnits = (int)floor($amount / 17);
        $stmt = $db->prepare('INSERT INTO topups (user_id, phone_to_charge, amount, sms_units, provider, status, created_at) VALUES (?, ?, ?, ?, ?, "pending", NOW())');
        $stmt->bind_param('isiis', $userId, $phone, $amount, $smsUnits, $provider);
        $stmt->execute();
        $topupId = (int)$stmt->insert_id;
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "topup", ?, ?, "UGX", ?, "pending", NOW())');
        $ref = 'TOPUP-' . $topupId;
        $stmt->bind_param('iiis', $userId, $smsUnits, $amount, $ref);
        $stmt->execute();
        $stmt->close();

        audit_log($db, $userId, 'wallet.topup.request', 'topups', $topupId, [
            'amount' => $amount,
            'sms_units' => $smsUnits,
            'provider' => $provider,
        ]);
        $message = 'Top-up request submitted. Admin approval is required.';
    }
}

$stmt = $db->prepare('SELECT id, amount, sms_units, provider, status, created_at FROM topups WHERE user_id=? ORDER BY id DESC LIMIT 20');
$stmt->bind_param('i', $userId);
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
  <title>Buy SMS Credits • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Buy / Top Up SMS Credits</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">New Top-Up Request</h5>
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label class="form-label">Amount (UGX)</label>
                <input type="number" class="form-control" name="amount" min="<?= $minTopup ?>" required>
                <div class="form-text">Minimum <?= number_format($minTopup) ?> UGX</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Payer Phone Number</label>
                <input class="form-control" name="phone_to_charge" placeholder="2567xxxxxxx" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Provider</label>
                <select class="form-select" name="provider">
                  <option value="manual">Manual</option>
                  <option value="mtn">MTN</option>
                  <option value="airtel">Airtel</option>
                </select>
              </div>
              <button class="btn btn-primary">Submit Request</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white"><strong>Recent Requests</strong></div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>Amount</th><th>SMS Units</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
              <?php foreach ($topups as $t): ?>
                <tr>
                  <td><?= (int)$t['id'] ?></td>
                  <td><?= number_format((int)$t['amount']) ?> UGX</td>
                  <td><?= number_format((int)$t['sms_units']) ?></td>
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
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
