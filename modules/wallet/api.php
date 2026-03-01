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
$action = (string)($_GET['action'] ?? 'balance');

if ($action === 'balance') {
    $stmt = $db->prepare('SELECT sms_balance FROM users WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    echo json_encode(['ok' => true, 'balance' => (int)($row['sms_balance'] ?? 0)]);
    exit;
}

if ($action === 'recent_transactions') {
    $stmt = $db->prepare('SELECT id, type, sms_units, amount, status, created_at FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 10');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['ok' => true, 'transactions' => $rows]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
