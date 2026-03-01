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

if ($action === 'unread_count') {
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS c
         FROM thread_messages tm
         INNER JOIN thread_participants tp ON tp.thread_id = tm.thread_id AND tp.user_id = ?
         LEFT JOIN message_reads mr ON mr.message_id = tm.id AND mr.user_id = ?
         WHERE tm.from_user_id <> ? AND mr.message_id IS NULL'
    );
    $stmt->bind_param('iii', $userId, $userId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    echo json_encode(['ok' => true, 'unread' => (int)($row['c'] ?? 0)]);
    exit;
}

if ($action === 'mark_thread_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $threadId = (int)($_POST['thread_id'] ?? 0);
    if ($threadId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid thread id']);
        exit;
    }

    $stmt = $db->prepare('SELECT id FROM thread_messages WHERE thread_id=? AND from_user_id<>?');
    $stmt->bind_param('ii', $threadId, $userId);
    $stmt->execute();
    $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $db->prepare('INSERT IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())');
    foreach ($msgs as $m) {
        $mid = (int)$m['id'];
        $stmt->bind_param('ii', $mid, $userId);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
