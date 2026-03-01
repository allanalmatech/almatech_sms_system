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
    $campaignId = (int)($_POST['campaign_id'] ?? 0);

    if ($campaignId <= 0) {
        $error = 'Invalid campaign selection.';
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT id, status, scheduled_at FROM sms_campaigns WHERE id=? AND user_id=? FOR UPDATE');
            $stmt->bind_param('ii', $campaignId, $userId);
            $stmt->execute();
            $campaign = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$campaign) {
                throw new RuntimeException('Campaign not found.');
            }
            if (($campaign['status'] ?? '') !== 'queued') {
                throw new RuntimeException('Campaign is not in queued status.');
            }

            $stmt = $db->prepare('UPDATE sms_campaigns SET status="sent", updated_at=NOW() WHERE id=?');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE sms_recipients SET status="sent", sent_at=NOW() WHERE campaign_id=? AND status="queued"');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE sms_logs SET status_label="Sent" WHERE campaign_id=?');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $stmt->close();

            create_notification($db, $userId, 'Scheduled campaign processed', 'Campaign #' . $campaignId . ' has been sent.', BASE_URL . 'modules/sms/sent.php', 'sms_scheduled_processed');
            audit_log($db, $userId, 'sms.queue.process', 'sms_campaigns', $campaignId);

            $db->commit();
            $message = 'Queued campaign processed successfully.';
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare('SELECT id, sender_id_text, total_recipients, total_sms_units, scheduled_at, status, created_at FROM sms_campaigns WHERE user_id=? AND status IN ("queued", "processing") ORDER BY scheduled_at ASC, id DESC LIMIT 200');
$stmt->bind_param('i', $userId);
$stmt->execute();
$campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>SMS Queue • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Scheduled Queue</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Sender</th><th>Recipients</th><th>Units</th><th>Schedule</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($campaigns as $c): ?>
              <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= e((string)$c['sender_id_text']) ?></td>
                <td><?= number_format((int)$c['total_recipients']) ?></td>
                <td><?= number_format((int)$c['total_sms_units']) ?></td>
                <td><?= e((string)$c['scheduled_at']) ?></td>
                <td><?= e((string)$c['status']) ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary">Process Now</button>
                  </form>
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
