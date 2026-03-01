<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $code = strtoupper(trim((string)($_POST['voucher_code'] ?? '')));

    if ($code === '') {
        $error = 'Voucher code is required.';
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT id, sms_units, status, expires_at FROM vouchers WHERE code=? FOR UPDATE');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $voucher = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$voucher) {
                throw new RuntimeException('Voucher not found.');
            }

            if ($voucher['status'] !== 'unused') {
                throw new RuntimeException('Voucher has already been used or revoked.');
            }

            if (!empty($voucher['expires_at']) && strtotime((string)$voucher['expires_at']) < time()) {
                throw new RuntimeException('Voucher is expired.');
            }

            $units = (int)$voucher['sms_units'];

            $stmt = $db->prepare('UPDATE vouchers SET status="used", used_at=NOW(), used_by=? WHERE id=?');
            $voucherId = (int)$voucher['id'];
            $stmt->bind_param('ii', $userId, $voucherId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE users SET sms_balance = sms_balance + ? WHERE id=?');
            $stmt->bind_param('ii', $units, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "voucher", ?, 0, "UGX", ?, "approved", NOW())');
            $stmt->bind_param('iis', $userId, $units, $code);
            $stmt->execute();
            $stmt->close();

            audit_log($db, $userId, 'wallet.voucher.redeem', 'vouchers', (int)$voucher['id'], [
                'code' => $code,
                'sms_units' => $units,
            ]);

            $db->commit();
            refresh_session_user($db);
            $message = 'Voucher redeemed successfully. Added ' . number_format($units) . ' SMS units.';
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare('SELECT code, sms_units, status, used_at, expires_at FROM vouchers WHERE used_by=? ORDER BY used_at DESC, id DESC LIMIT 50');
$stmt->bind_param('i', $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Vouchers • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Load Voucher</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label class="form-label">Voucher Code</label>
                <input class="form-control" name="voucher_code" required placeholder="ALMA-XXXX-XXXX">
              </div>
              <button class="btn btn-primary">Redeem Voucher</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Code</th><th>Units</th><th>Status</th><th>Used At</th><th>Expires At</th></tr></thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                  <tr>
                    <td><?= e((string)$h['code']) ?></td>
                    <td><?= number_format((int)$h['sms_units']) ?></td>
                    <td><?= e((string)$h['status']) ?></td>
                    <td><?= e((string)$h['used_at']) ?></td>
                    <td><?= e((string)$h['expires_at']) ?></td>
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
