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

    $toUsername = trim((string)($_POST['to_username'] ?? ''));
    $units = (int)($_POST['sms_units'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));

    if ($toUsername === '' || $units <= 0) {
        $error = 'Recipient username and valid SMS units are required.';
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT id FROM users WHERE username=? AND status="active" LIMIT 1');
            $stmt->bind_param('s', $toUsername);
            $stmt->execute();
            $receiver = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $toUserId = (int)($receiver['id'] ?? 0);
            if ($toUserId <= 0 || $toUserId === $userId) {
                throw new RuntimeException('Invalid recipient user.');
            }

            $stmt = $db->prepare('SELECT sms_balance FROM users WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $sender = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $senderBal = (int)($sender['sms_balance'] ?? 0);
            if ($senderBal < $units) {
                throw new RuntimeException('Insufficient SMS balance for transfer.');
            }

            $stmt = $db->prepare('UPDATE users SET sms_balance = sms_balance - ? WHERE id=?');
            $stmt->bind_param('ii', $units, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE users SET sms_balance = sms_balance + ? WHERE id=?');
            $stmt->bind_param('ii', $units, $toUserId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO credit_transfers (from_user_id, to_user_id, sms_units, note, status, created_at) VALUES (?, ?, ?, ?, "completed", NOW())');
            $stmt->bind_param('iiis', $userId, $toUserId, $units, $note);
            $stmt->execute();
            $transferId = (int)$stmt->insert_id;
            $stmt->close();

            $outRef = 'TR-' . $transferId . '-OUT';
            $inRef = 'TR-' . $transferId . '-IN';

            $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "transfer_out", ?, 0, "UGX", ?, "approved", NOW())');
            $negUnits = -1 * $units;
            $stmt->bind_param('iis', $userId, $negUnits, $outRef);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "transfer_in", ?, 0, "UGX", ?, "approved", NOW())');
            $stmt->bind_param('iis', $toUserId, $units, $inRef);
            $stmt->execute();
            $stmt->close();

            create_notification($db, $toUserId, 'SMS units received', 'You received ' . number_format($units) . ' SMS units from ' . $_SESSION['user']['username'] . '.', BASE_URL . 'modules/wallet/transactions.php', 'transfer_in');

            audit_log($db, $userId, 'wallet.transfer.create', 'credit_transfers', $transferId, [
                'to_user_id' => $toUserId,
                'sms_units' => $units,
            ]);

            $db->commit();
            refresh_session_user($db);
            $message = 'Transfer successful.';
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare('SELECT t.id, u.username AS to_username, t.sms_units, t.note, t.created_at FROM credit_transfers t INNER JOIN users u ON u.id = t.to_user_id WHERE t.from_user_id = ? ORDER BY t.id DESC LIMIT 25');
$stmt->bind_param('i', $userId);
$stmt->execute();
$transfers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Me2U Transfer • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Me2U Credit Transfer</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3"><label class="form-label">Recipient Username</label><input class="form-control" name="to_username" required></div>
              <div class="mb-3"><label class="form-label">SMS Units</label><input type="number" min="1" class="form-control" name="sms_units" required></div>
              <div class="mb-3"><label class="form-label">Note</label><textarea class="form-control" name="note" rows="2"></textarea></div>
              <button class="btn btn-primary">Transfer Units</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>To</th><th>Units</th><th>Note</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($transfers as $tr): ?>
                  <tr>
                    <td><?= (int)$tr['id'] ?></td>
                    <td><?= e((string)$tr['to_username']) ?></td>
                    <td><?= number_format((int)$tr['sms_units']) ?></td>
                    <td><?= e((string)$tr['note']) ?></td>
                    <td><?= e((string)$tr['created_at']) ?></td>
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
