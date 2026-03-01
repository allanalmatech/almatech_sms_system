<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$messageOk = '';
$error = '';
$stats = null;

function read_recipients_from_uploaded_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [[], null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [[], 'File upload failed.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $name = (string)($file['name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [[], 'Invalid upload source.'];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['csv', 'txt', 'xlsx', 'xls'];
    if (!in_array($ext, $allowed, true)) {
        return [[], 'Unsupported file type. Use .csv, .xls, .xlsx, or .txt'];
    }

    $values = [];

    if ($ext === 'txt' || $ext === 'csv') {
        $raw = (string)file_get_contents($tmp);
        $values = extract_phone_candidates($raw);
        return [$values, null];
    }

    $extractFromRows = static function (array $rows): array {
        $out = [];
        if (count($rows) === 0) {
            return $out;
        }

        $headerMap = [];
        $firstRow = $rows[0] ?? [];
        foreach ($firstRow as $idx => $cell) {
            $key = strtolower(trim((string)$cell));
            if ($key !== '') {
                $headerMap[$key] = (int)$idx;
            }
        }

        $phoneKeys = ['phone', 'phone_number', 'mobile', 'msisdn', 'contact', 'number'];
        $phoneCol = null;
        foreach ($phoneKeys as $key) {
            if (isset($headerMap[$key])) {
                $phoneCol = (int)$headerMap[$key];
                break;
            }
        }

        if ($phoneCol !== null) {
            foreach (array_slice($rows, 1) as $row) {
                if (isset($row[$phoneCol])) {
                    $val = trim((string)$row[$phoneCol]);
                    if ($val !== '') {
                        $out[] = $val;
                    }
                }
            }
            return $out;
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $val = trim((string)$cell);
                if ($val !== '') {
                    $out[] = $val;
                }
            }
        }
        return $out;
    };

    if ($ext === 'xlsx') {
        $xlsxClass = 'Shuchkin\\SimpleXLSX';
        if (!class_exists($xlsxClass)) {
            return [[], 'XLSX parser not available.'];
        }
        $xlsx = $xlsxClass::parse($tmp);
        if (!$xlsx) {
            return [[], 'Could not parse .xlsx file.'];
        }
        $rows = $xlsx->rows();
        return [$extractFromRows($rows), null];
    }

    if ($ext === 'xls') {
        $xlsClass = 'Shuchkin\\SimpleXLS';
        if (!class_exists($xlsClass)) {
            return [[], 'XLS parser not available.'];
        }
        $xls = $xlsClass::parse($tmp);
        if (!$xls) {
            return [[], 'Could not parse .xls file.'];
        }
        $rows = $xls->rows();
        return [$extractFromRows($rows), null];
    }

    return [$values, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $sender = trim((string)($_POST['sender_id'] ?? 'INFO'));
    $body = trim((string)($_POST['message'] ?? ''));
    $recipientsRaw = (string)($_POST['recipients'] ?? '');
    $savedSource = (string)($_POST['saved_contacts_source'] ?? 'none');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $sendType = (string)($_POST['send_type'] ?? 'now');
    $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));

    $chunks = extract_phone_candidates($recipientsRaw);

    [$fileNumbers, $fileError] = read_recipients_from_uploaded_file($_FILES['recipients_file'] ?? []);
    if ($fileError !== null) {
        $error = $fileError;
    }
    $chunks = array_merge($chunks, $fileNumbers);

    if ($error === '' && $savedSource === 'all') {
        $stmt = $db->prepare('SELECT phone_e164 FROM contacts WHERE user_id=?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $chunks[] = (string)$row['phone_e164'];
        }
    } elseif ($error === '' && $savedSource === 'group') {
        if ($groupId <= 0) {
            $error = 'Select a contact group.';
        } else {
            $stmt = $db->prepare(
                'SELECT c.phone_e164
                 FROM group_contacts gc
                 INNER JOIN contacts c ON c.id = gc.contact_id
                 INNER JOIN contact_groups g ON g.id = gc.group_id
                 WHERE g.user_id=? AND g.id=?'
            );
            $stmt->bind_param('ii', $userId, $groupId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            foreach ($rows as $row) {
                $chunks[] = (string)$row['phone_e164'];
            }
        }
    }

    $valid = [];
    $invalid = 0;
    $duplicates = 0;
    $seen = [];

    foreach ($chunks as $chunk) {
        $normalized = normalize_ug_phone($chunk);
        if ($normalized === null) {
            $invalid++;
            continue;
        }
        if (isset($seen[$normalized])) {
            $duplicates++;
            continue;
        }
        $seen[$normalized] = true;
        $valid[] = $normalized;
    }

    $segments = sms_segments($body);
    $units = count($valid) * $segments;
    $stats = [
        'total' => count($chunks),
        'valid' => count($valid),
        'invalid' => $invalid,
        'duplicates' => $duplicates,
        'segments' => $segments,
        'units' => $units,
    ];

    if ($error !== '') {
        // keep source parsing error
    } elseif ($body === '') {
        $error = 'Message body is required.';
    } elseif ($segments <= 0) {
        $error = 'Message body is invalid.';
    } elseif (count($valid) === 0) {
        $error = 'No valid recipient numbers found.';
    } elseif ((int)($_SESSION['user']['sms_balance'] ?? 0) < $units) {
        $error = 'Insufficient SMS balance. Required ' . number_format($units) . ' units.';
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
            $stmt->bind_param('iii', $units, $userId, $units);
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                throw new RuntimeException('Insufficient SMS balance.');
            }
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO sms_campaigns (user_id, type, sender_id_text, message, is_scheduled, scheduled_at, total_recipients, valid_recipients, invalid_recipients, duplicate_recipients, segments_per_sms, total_sms_units, cost_units, status, created_at) VALUES (?, "bulk", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $totalRecipients = count($chunks);
            $validRecipients = count($valid);
            $costUnits = $units;
            $stmt->bind_param('issisiiiiiiis', $userId, $sender, $body, $isScheduled, $scheduledAtValue, $totalRecipients, $validRecipients, $invalid, $duplicates, $segments, $units, $costUnits, $campaignStatus);
            $stmt->execute();
            $campaignId = (int)$stmt->insert_id;
            $stmt->close();

            $statusPerRecipient = $isScheduled ? 'queued' : 'sent';
            $sentAt = $isScheduled ? null : date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO sms_recipients (campaign_id, phone_e164, variables_json, parts, status, sent_at, created_at) VALUES (?, ?, NULL, ?, ?, ?, NOW())');
            foreach ($valid as $phone) {
                $stmt->bind_param('iisss', $campaignId, $phone, $segments, $statusPerRecipient, $sentAt);
                $stmt->execute();
            }
            $stmt->close();

            $previewRecipients = implode(',', array_slice($valid, 0, 5));
            $previewMessage = mb_substr($body, 0, 240, 'UTF-8');
            $statusLabel = $isScheduled ? 'Queued' : 'Sent';
            $stmt = $db->prepare('INSERT INTO sms_logs (user_id, campaign_id, sender_id_text, recipients_preview, message_preview, sms_units, status_label, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('iisssis', $userId, $campaignId, $sender, $previewRecipients, $previewMessage, $units, $statusLabel);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at) VALUES (?, "debit_sms", ?, 0, "UGX", ?, "approved", NOW())');
            $debit = -1 * $units;
            $ref = 'SMS-' . $campaignId;
            $stmt->bind_param('iis', $userId, $debit, $ref);
            $stmt->execute();
            $stmt->close();

            create_notification(
                $db,
                $userId,
                $isScheduled ? 'Scheduled SMS campaign' : 'SMS sent',
                ($isScheduled ? 'Campaign queued: ' : 'Campaign sent: ') . number_format($validRecipients) . ' recipients.',
                BASE_URL . 'modules/sms/sent.php',
                $isScheduled ? 'sms_scheduled' : 'sms_sent'
            );

            audit_log($db, $userId, 'sms.compose.send', 'sms_campaigns', $campaignId, [
                'total' => $totalRecipients,
                'valid' => $validRecipients,
                'units' => $units,
                'scheduled' => $isScheduled,
            ]);

            $db->commit();
            refresh_session_user($db);
            $messageOk = $isScheduled ? 'Campaign scheduled successfully.' : 'Campaign sent successfully.';
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$selectedSavedSource = (string)($_POST['saved_contacts_source'] ?? 'none');
$selectedGroupId = (int)($_POST['group_id'] ?? 0);

$stmt = $db->prepare(
    'SELECT g.id, g.name, COUNT(gc.contact_id) AS contacts_count
     FROM contact_groups g
     LEFT JOIN group_contacts gc ON gc.group_id = g.id
     WHERE g.user_id=?
     GROUP BY g.id
     ORDER BY g.name ASC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$contactGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $db->prepare('SELECT COUNT(*) AS c FROM contacts WHERE user_id=?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$allContactsCount = (int)($countRow['c'] ?? 0);

$chargePerSms = 17;
$stmt = $db->prepare('SELECT price_per_sms FROM user_network_pricing WHERE user_id=? ORDER BY network_id ASC LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$priceRow = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
if (isset($priceRow['price_per_sms'])) {
    $chargePerSms = max(0, (int)$priceRow['price_per_sms']);
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
  <title>Compose SMS • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Compose Bulk SMS</h1>
    <?php if ($messageOk !== ''): ?><div class="alert alert-success"><?= e($messageOk) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if (is_array($stats)): ?>
      <div class="alert alert-info">
        Valid: <strong><?= (int)$stats['valid'] ?></strong> |
        Invalid: <strong><?= (int)$stats['invalid'] ?></strong> |
        Duplicates: <strong><?= (int)$stats['duplicates'] ?></strong> |
        Segments: <strong><?= (int)$stats['segments'] ?></strong> |
        Billable Units: <strong><?= (int)$stats['units'] ?></strong>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Sender ID</label>
              <input class="form-control" name="sender_id" value="INFO" maxlength="20" required>
            </div>
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
            <div class="col-12">
              <label class="form-label">Recipients (Manual Entry)</label>
              <textarea class="form-control" name="recipients" rows="6" placeholder="07XXXXXXXX, 04XXXXXXXX, 2567XXXXXXXX, +2564XXXXXXXX or one per line"><?= e((string)($_POST['recipients'] ?? '')) ?></textarea>
              <div class="form-text">Accepted Uganda prefixes: 07, 04, 02, 03, +2567, +2564, +2562, +2563. Separate by comma or new line.</div>
              <div class="small mt-2 text-danger" id="invalidContactsWrap" style="display:none;">
                Invalid contacts: <span id="invalidContactsList"></span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Recipients File</label>
              <input type="file" class="form-control" name="recipients_file" accept=".csv,.xls,.xlsx,.txt">
              <div class="form-text">Supported: .csv, .xls, .xlsx, .txt</div>
              <div class="form-text">Live units estimate counts manual + saved contacts. Uploaded-file recipients are counted on submit.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Saved Contacts Source</label>
              <select class="form-select" name="saved_contacts_source" id="savedContactsSource">
                <option value="none" <?= $selectedSavedSource === 'none' ? 'selected' : '' ?>>None</option>
                <option value="all" <?= $selectedSavedSource === 'all' ? 'selected' : '' ?>>All Contacts</option>
                <option value="group" <?= $selectedSavedSource === 'group' ? 'selected' : '' ?>>Specific Group</option>
              </select>
            </div>

            <div class="col-md-3" id="groupSelectWrap" style="display:none;">
              <label class="form-label">Contact Group</label>
              <select class="form-select" name="group_id" id="groupIdSelect">
                <option value="0">Select group</option>
                <?php foreach ($contactGroups as $group): ?>
                  <option value="<?= (int)$group['id'] ?>" data-count="<?= (int)$group['contacts_count'] ?>" <?= $selectedGroupId === (int)$group['id'] ? 'selected' : '' ?>>
                    <?= e((string)$group['name']) ?> (<?= (int)$group['contacts_count'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea id="messageBody" class="form-control" name="message" rows="5" maxlength="5000" required></textarea>
              <div class="small text-muted mt-2" id="smsLiveCounter">
                Segments: <strong id="segmentCounter">0</strong> |
                Char Count: <strong id="charCounter">0/160</strong> |
                Left: <strong id="leftCounter">160</strong> |
                Total Char: <strong id="totalCharCounter">0</strong> |
                Char Per Unit: <strong id="charPerUnitCounter">160</strong> |
                Est Units: <strong id="estUnitsCounter">0</strong> |
                Charge/SMS: <strong id="chargePerSmsCounter"><?= number_format($chargePerSms) ?> UGX</strong> |
                Est Total Cost: <strong id="estTotalCostCounter">0</strong>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Campaign</button>
          </div>
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

  const recipientsBox = document.querySelector('textarea[name="recipients"]');
  const savedSourceSelect = document.getElementById('savedContactsSource');
  const groupSelectWrap = document.getElementById('groupSelectWrap');
  const groupIdSelect = document.getElementById('groupIdSelect');
  const allContactsCount = <?= (int)$allContactsCount ?>;
  const chargePerSms = <?= (int)$chargePerSms ?>;
  const messageBox = document.getElementById('messageBody');
  const charCounter = document.getElementById('charCounter');
  const leftCounter = document.getElementById('leftCounter');
  const totalCharCounter = document.getElementById('totalCharCounter');
  const charPerUnitCounter = document.getElementById('charPerUnitCounter');
  const segmentCounter = document.getElementById('segmentCounter');
  const estUnitsCounter = document.getElementById('estUnitsCounter');
  const chargePerSmsCounter = document.getElementById('chargePerSmsCounter');
  const estTotalCostCounter = document.getElementById('estTotalCostCounter');
  const invalidContactsWrap = document.getElementById('invalidContactsWrap');
  const invalidContactsList = document.getElementById('invalidContactsList');

  function normalizeUgPhone(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;
    let v = raw.replace(/[\s\-()]/g, '');

    if (v.startsWith('+')) {
      v = '+' + v.slice(1).replace(/\D+/g, '');
    } else {
      v = v.replace(/\D+/g, '');
    }

    let m = v.match(/^\+?256([2347]\d{8})$/);
    if (m) return '+256' + m[1];
    m = v.match(/^0([2347]\d{8})$/);
    if (m) return '+256' + m[1];
    m = v.match(/^([2347]\d{8})$/);
    if (m) return '+256' + m[1];
    return null;
  }

  function getValidUniqueRecipientsCount() {
    const raw = recipientsBox ? recipientsBox.value : '';
    const parts = raw.split(/[\n,;\r]+/).map(s => s.trim()).filter(Boolean);
    const seen = new Set();
    for (const part of parts) {
      const n = normalizeUgPhone(part);
      if (n) seen.add(n);
    }

    let savedCount = 0;

    if (savedSourceSelect) {
      if (savedSourceSelect.value === 'all') {
        savedCount = allContactsCount;
      } else if (savedSourceSelect.value === 'group' && groupIdSelect) {
        const selected = groupIdSelect.options[groupIdSelect.selectedIndex];
        savedCount = Number(selected?.dataset?.count || 0);
      }
    }

    return seen.size + savedCount;
  }

  function getManualRecipientAnalysis() {
    const raw = recipientsBox ? recipientsBox.value : '';
    const parts = raw.split(/[\n,;\r]+/).map(s => s.trim()).filter(Boolean);
    const seenValid = new Set();
    const invalid = [];

    for (const part of parts) {
      const n = normalizeUgPhone(part);
      if (!n) {
        invalid.push(part);
        continue;
      }
      seenValid.add(n);
    }

    return {
      invalid,
      validCount: seenValid.size,
      totalEntered: parts.length
    };
  }

  function renderInvalidContacts() {
    if (!invalidContactsWrap || !invalidContactsList) return;

    const analysis = getManualRecipientAnalysis();
    if (analysis.invalid.length === 0) {
      invalidContactsWrap.style.display = 'none';
      invalidContactsList.textContent = '';
      return;
    }

    const limited = analysis.invalid.slice(0, 20);
    const html = limited
      .map(item => `<u>${String(item).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[s])}</u>`)
      .join(', ');

    const more = analysis.invalid.length > limited.length
      ? ` <span class="text-muted">(+${analysis.invalid.length - limited.length} more)</span>`
      : '';

    invalidContactsList.innerHTML = html + more;
    invalidContactsWrap.style.display = '';
  }

  function syncSavedContactsUi() {
    if (!savedSourceSelect || !groupSelectWrap) return;
    groupSelectWrap.style.display = savedSourceSelect.value === 'group' ? '' : 'none';
    updateCounter();
  }

  function smsMeta(text) {
    const msg = String(text || '').trim();
    if (!msg) {
      return { segments: 0, charPerUnit: 160, totalChars: 0, currentCount: 0, left: 160 };
    }
    const isGsmBasic = /^[\x20-\x7E\r\n\t]+$/.test(msg);
    const totalChars = [...msg].length;
    const charPerUnit = isGsmBasic ? 160 : 70;
    const segments = Math.max(1, Math.ceil(totalChars / charPerUnit));
    const usedInCurrentUnit = totalChars % charPerUnit;
    const currentCount = usedInCurrentUnit === 0 ? charPerUnit : usedInCurrentUnit;
    const left = totalChars === 0 ? charPerUnit : (charPerUnit - currentCount);
    return { segments, charPerUnit, totalChars, currentCount, left };
  }

  function updateCounter() {
    const msg = messageBox ? messageBox.value : '';
    const meta = smsMeta(msg);
    const estUnits = getValidUniqueRecipientsCount();
    const estTotalCost = estUnits * meta.segments * chargePerSms;

    if (segmentCounter) segmentCounter.textContent = String(meta.segments);
    if (charCounter) charCounter.textContent = `${meta.currentCount}/${meta.charPerUnit}`;
    if (leftCounter) leftCounter.textContent = String(meta.left);
    if (totalCharCounter) totalCharCounter.textContent = String(meta.totalChars);
    if (charPerUnitCounter) charPerUnitCounter.textContent = String(meta.charPerUnit);
    if (estUnitsCounter) estUnitsCounter.textContent = String(estUnits);
    if (chargePerSmsCounter) chargePerSmsCounter.textContent = `${chargePerSms} UGX`;
    if (estTotalCostCounter) estTotalCostCounter.textContent = `${estTotalCost.toLocaleString()} UGX`;
    renderInvalidContacts();
  }

  if (messageBox) messageBox.addEventListener('input', updateCounter);
  if (recipientsBox) recipientsBox.addEventListener('input', updateCounter);
  if (savedSourceSelect) savedSourceSelect.addEventListener('change', syncSavedContactsUi);
  if (groupIdSelect) groupIdSelect.addEventListener('change', updateCounter);
  syncSavedContactsUi();
  updateCounter();
</script>
</body>
</html>
