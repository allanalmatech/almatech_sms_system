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
    echo json_encode(['ok' => true, 'unread' => notification_unread_count($db, $userId)]);
    exit;
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);
    echo json_encode(['ok' => mark_notification_read($db, $userId, $id)]);
    exit;
}

if ($action === 'mark_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    mark_all_notifications_read($db, $userId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'save_push_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $payload = json_decode((string)($_POST['subscription'] ?? '{}'), true);
    $endpoint = (string)($payload['endpoint'] ?? '');
    $keys = $payload['keys'] ?? [];
    $p256dh = (string)($keys['p256dh'] ?? '');
    $auth = (string)($keys['auth'] ?? '');

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid subscription payload']);
        exit;
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ok = save_push_subscription($db, $userId, $endpoint, $p256dh, $auth, $ua);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($action === 'remove_push_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $endpoint = (string)($_POST['endpoint'] ?? '');
    echo json_encode(['ok' => disable_push_subscription($db, $userId, $endpoint)]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
