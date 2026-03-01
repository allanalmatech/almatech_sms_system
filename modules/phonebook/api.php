<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'bulk_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $raw = (string)($_POST['numbers'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $chunks = extract_phone_candidates($raw);

    $added = 0;
    $invalid = 0;
    $duplicates = 0;

    foreach ($chunks as $chunk) {
        $phone = normalize_ug_phone($chunk);
        if (!$phone) {
            $invalid++;
            continue;
        }

        $stmt = $db->prepare('INSERT IGNORE INTO contacts (user_id, phone_e164, phone_raw, name, created_at) VALUES (?, ?, ?, NULL, NOW())');
        $stmt->bind_param('iss', $userId, $phone, $chunk);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $contactId = (int)$stmt->insert_id;
        $stmt->close();

        if ($affected > 0) {
            $added++;
            if ($groupId > 0 && $contactId > 0) {
                $stmt = $db->prepare('INSERT IGNORE INTO group_contacts (group_id, contact_id, created_at) VALUES (?, ?, NOW())');
                $stmt->bind_param('ii', $groupId, $contactId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $duplicates++;
        }
    }

    echo json_encode([
        'ok' => true,
        'added' => $added,
        'invalid' => $invalid,
        'duplicates' => $duplicates,
        'total' => count($chunks),
    ]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $contactId = (int)($_POST['contact_id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM contacts WHERE id=? AND user_id=?');
    $stmt->bind_param('ii', $contactId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    echo json_encode(['ok' => $ok]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
