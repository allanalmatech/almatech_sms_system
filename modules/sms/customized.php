<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$messageOk = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $sender = trim((string)($_POST['sender_id'] ?? 'INFO'));
    $template = trim((string)($_POST['message_template'] ?? ''));
    $sendType = (string)($_POST['send_type'] ?? 'now');
    $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));

    if ($template === '') {
        $error = 'Message template is required.';
    } elseif (empty($_FILES['recipients_file']['name'])) {
        $error = 'Upload a CSV file with recipient rows.';
    } else {
        $tmp = (string)$_FILES['recipients_file']['tmp_name'];
        $fh = fopen($tmp, 'r');

        if ($fh === false) {
            $error = 'Failed to read uploaded file.';
        } else {
            $header = fgetcsv($fh) ?: [];
            $headerMap = [];
            foreach ($header as $idx => $col) {
                $headerMap[strtolower(trim((string)$col))] = $idx;
            }

            if (!isset($headerMap['phone'])) {
                fclose($fh);
                $error = 'CSV must include a phone column.';
            } else {
                $rows = [];
                $invalid = 0;
                $duplicates = 0;
                $seen = [];

                while (($line = fgetcsv($fh)) !== false) {
                    $phoneRaw = trim((string)($line[$headerMap['phone']] ?? ''));
                    $phone = normalize_ug_phone($phoneRaw);
                    if ($phone === null) {
                        $invalid++;
                        continue;
                    }
                    if (isset($seen[$phone])) {
                        $duplicates++;
                        continue;
                    }
                    $seen[$phone] = true;

                    $vars = [
                        'name' => (string)($line[$headerMap['name']] ?? ''),
                        'amount' => (string)($line[$headerMap['amount']] ?? ''),
                    ];
                    for ($i = 1; $i <= 5; $i++) {
                        $key = 'var' . $i;
                        $idx = $headerMap[$key] ?? null;
                        $vars[$key] = ($idx !== null && isset($line[$idx])) ? (string)$line[$idx] : '';
                    }

                    $personalized = $template;
                    foreach ($vars as $k => $v) {
                        $personalized = str_replace('@@' . $k . '@@', $v, $personalized);
                    }

                    $rows[] = [
                        'phone' => $phone,
                        'vars' => $vars,
                        'message' => $personalized,
                        'segments' => sms_segments($personalized),
                    ];
                }
                fclose($fh);

                $validCount = count($rows);
                if ($validCount === 0) {
                    $error = 'No valid recipients in file.';
                } else {
                    $totalUnits = 0;
                    foreach ($rows as $r) {
                        $totalUnits += (int)$r['segments'];
                    }

                    if ((int)($_SESSION['user']['sms_balance'] ?? 0) < $totalUnits) {
                        $error = 'Insufficient SMS balance. Required ' . number_format($totalUnits) . ' units.';
                    } else {
                        $db->begin_transaction();
                        try {
                            $campaignStatus = 'sent';
                            $scheduledAtValue = null;
                            $isScheduled = 0;

                            if ($sendType === 'schedule') {
                                if ($scheduleAt === '' || strtotime($scheduleAt) === false) {
                                    throw new RuntimeException('Provide a valid schedule date/time.');
                                }
                                $campaignStatus = 'queued';
                                $scheduledAtValue = date('Y-m-d H:i:s', strtotime($scheduleAt));
                                $isScheduled = 1;
                            }

                            $stmt = $db->prepare('UPDATE users SET sms_balance = sms_balance - ? WHERE id=? AND sms_balance >= ?');
                            $stmt->bind_param('iii', $totalUnits, $userId, $totalUnits);
                            $stmt->execute();
                            if ($stmt->affected_rows <= 0) {
                                throw new RuntimeException('Insufficient SMS balance.');
                            }
                            $stmt->close();

                            $segmentsPerSms = max(1, sms_segments($template));
                            $totalRecipients = $validCount + $invalid + $duplicates;

                            $stmt = $db->prepare('INSERT INTO sms_campaigns (user_id, type, sender_id_text, message, is_scheduled, scheduled_at, total_recipients, valid_recipients, invalid_recipients, duplicate_recipients, segments_per_sms, total_sms_units, cost_units, status, created_at) VALUES (?, "personalized", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                            $costUnits = $totalUnits;
                            $stmt->bind_param('issisiiiiiiis', $userId, $sender, $template, $isScheduled, $scheduledAtValue, $totalRecipients, $validCount, $invalid, $duplicates, $segmentsPerSms, $totalUnits, $costUnits, $campaignStatus);
                            $stmt->execute();
                            $campaignId = (int)$stmt->insert_id;
                            $stmt->close();

                            $statusPerRecipient = $isScheduled ? 'queued' : 'sent';
                            $sentAt = $isScheduled ? null : date('Y-m-d H:i:s');
                            $stmt = $db->prepare('INSERT INTO sms_recipients (campaign_id, phone_e164, variables_json, parts, status, sent_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                            foreach ($rows as $r) {
                                $json = json_encode($r['vars'], JSON_UNESCAPED_SLASHES);
                                $parts = (int)$r['segments'];
                                $stmt->bind_param('ississ', $campaignId, $r['phone'], $json, $parts, $statusPerRecipient, $sentAt);
                                $stmt->execute();
                            }
                            $stmt->close();

                            $previewRecipients = implode(',', array_map(function($r) { return $r['phone']; }, array_slice($rows, 0, 5)));
                            $statusLabel = $isScheduled ? 'Queued' : 'Sent';
                            $msgPreview = mb_substr($template, 0, 240, 'UTF-8');
                            $stmt = $db->prepare('INSERT INTO sms_logs (user_id, campaign_id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                            $stmt->bind_param('iisssis', $userId, $campaignId, $sender, $previewRecipients, $msgPreview, $totalUnits, $statusLabel);
                            $stmt->execute();
                            $stmt->close();

                            $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "debit_sms", ?, 0, "UGX", ?, "approved", NOW())');
                            $debit = -1 * $totalUnits;
                            $ref = 'SMS-P-' . $campaignId;
                            $stmt->bind_param('iis', $userId, $debit, $ref);
                            $stmt->execute();
                            $stmt->close();

                            audit_log($db, $userId, 'sms.personalized.send', 'sms_campaigns', $campaignId, [
                                'valid' => $validCount,
                                'invalid' => $invalid,
                                'duplicates' => $duplicates,
                                'total_units' => $totalUnits,
                            ]);

                            $db->commit();
                            refresh_session_user($db);
                            $messageOk = $isScheduled ? 'Personalized campaign scheduled.' : 'Personalized campaign sent.';
                        } catch (Throwable $e) {
                            $db->rollback();
                            $error = $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}

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
  <title>Personalized SMS • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Personalized SMS</h1>

    <?php if ($messageOk !== ''): ?><div class="alert alert-success"><?= e($messageOk) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="small text-muted">Expected CSV columns: <code>phone,name,amount,var1,var2,var3,var4,var5</code></div>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>modules/sms/api.php?action=sample_csv">Download Sample</a>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Sender ID</label><input class="form-control" name="sender_id" value="INFO" required></div>
            <div class="col-md-4">
              <label class="form-label">Send Type</label>
              <select class="form-select" name="send_type" id="sendType">
                <option value="now">Send Now</option>
                <option value="schedule">Schedule Later</option>
              </select>
            </div>
            <div class="col-md-4" id="scheduleWrap" style="display:none;">
              <label class="form-label">Schedule At</label>
              <input type="datetime-local" class="form-control" name="schedule_at">
            </div>
            <div class="col-12"><label class="form-label">Recipients CSV File</label><input type="file" class="form-control" name="recipients_file" accept=".csv,.txt" required></div>
            <div class="col-12">
              <label class="form-label">Message Template</label>
              <textarea class="form-control" name="message_template" rows="5" required placeholder="Hello @@name@@, you have paid @@amount@@."></textarea>
            </div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Submit Personalized Campaign</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
  const sendType = document.getElementById('sendType');
  const scheduleWrap = document.getElementById('scheduleWrap');
  sendType.addEventListener('change', () => {
    scheduleWrap.style.display = sendType.value === 'schedule' ? '' : 'none';
  });
</script>
</body>
</html>
