<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'sample_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="personalized_sms_sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['phone', 'name', 'amount', 'var1', 'var2', 'var3', 'var4', 'var5']);
    fputcsv($out, ['256700000001', 'Alice', '25000', 'A', 'B', 'C', 'D', 'E']);
    fputcsv($out, ['+256700000002', 'Brian', '40000', 'X', 'Y', 'Z', '', '']);
    fclose($out);
    exit;
}

header('Content-Type: application/json');

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

if ($action === 'estimate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $message = trim((string)($_POST['message'] ?? ''));
    $recipientsRaw = (string)($_POST['recipients'] ?? '');

    $chunks = extract_phone_candidates($recipientsRaw);
    $valid = [];
    $invalid = 0;
    $duplicates = 0;
    $seen = [];

    foreach ($chunks as $chunk) {
        $phone = normalize_ug_phone($chunk);
        if ($phone === null) {
            $invalid++;
            continue;
        }
        if (isset($seen[$phone])) {
            $duplicates++;
            continue;
        }
        $seen[$phone] = true;
        $valid[] = $phone;
    }

    $segments = sms_segments($message);
    $units = count($valid) * $segments;

    echo json_encode([
        'ok' => true,
        'total' => count($chunks),
        'valid' => count($valid),
        'invalid' => $invalid,
        'duplicates' => $duplicates,
        'segments' => $segments,
        'units' => $units,
    ]);
    exit;
}

if ($action === 'process_due_queue') {
    $token = (string)($_GET['token'] ?? '');
    $cronToken = app_setting($db, 'queue_worker_token', '');
    if ($cronToken !== '' && !hash_equals($cronToken, $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid token']);
        exit;
    }

    $stmt = $db->prepare('SELECT id, user_id FROM sms_campaigns WHERE status="queued" AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT 100');
    $stmt->execute();
    $due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $processed = 0;
    foreach ($due as $campaign) {
        $campaignId = (int)$campaign['id'];
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('UPDATE sms_campaigns SET status="sent", updated_at=NOW() WHERE id=? AND status="queued"');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $changed = $stmt->affected_rows > 0;
            $stmt->close();

            if (!$changed) {
                $db->rollback();
                continue;
            }

            $stmt = $db->prepare('UPDATE sms_recipients SET status="sent", sent_at=NOW() WHERE campaign_id=? AND status="queued"');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE sms_logs SET status_label="Sent" WHERE campaign_id=?');
            $stmt->bind_param('i', $campaignId);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            $processed++;
        } catch (Throwable $e) {
            $db->rollback();
        }
    }

    echo json_encode(['ok' => true, 'processed' => $processed]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
