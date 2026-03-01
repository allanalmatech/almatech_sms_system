<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'modules/admin/payments/index.php');
}

require_post_csrf();

$topupId = (int)($_POST['topup_id'] ?? 0);
if ($topupId <= 0) {
    redirect(BASE_URL . 'modules/admin/payments/index.php');
}

$adminId = (int)$_SESSION['user']['id'];

$db->begin_transaction();
try {
    $stmt = $db->prepare('SELECT user_id, status FROM topups WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $topupId);
    $stmt->execute();
    $topup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$topup || $topup['status'] !== 'pending') {
        throw new RuntimeException('Top-up is not pending.');
    }

    $userId = (int)$topup['user_id'];
    $stmt = $db->prepare('UPDATE topups SET status="failed", approved_by=?, updated_at=NOW() WHERE id=?');
    $stmt->bind_param('ii', $adminId, $topupId);
    $stmt->execute();
    $stmt->close();

    $reference = 'TOPUP-' . $topupId;
    $stmt = $db->prepare('UPDATE wallet_transactions SET status="failed" WHERE user_id=? AND reference=? AND type="topup" AND status="pending"');
    $stmt->bind_param('is', $userId, $reference);
    $stmt->execute();
    $stmt->close();

    create_notification(
        $db,
        $userId,
        'Top-up rejected',
        'Your top-up request #' . $topupId . ' was rejected. Please contact support.',
        BASE_URL . 'modules/wallet/buy.php',
        'topup_failed'
    );

    audit_log($db, $adminId, 'wallet.topup.reject', 'topups', $topupId, ['user_id' => $userId]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
}

redirect(BASE_URL . 'modules/admin/payments/index.php');
