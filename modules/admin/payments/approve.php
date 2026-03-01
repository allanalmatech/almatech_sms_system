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
    $stmt = $db->prepare('SELECT id, user_id, amount, sms_units, status FROM topups WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $topupId);
    $stmt->execute();
    $topup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$topup || $topup['status'] !== 'pending') {
        throw new RuntimeException('Top-up is not pending.');
    }

    $userId = (int)$topup['user_id'];
    $smsUnits = (int)$topup['sms_units'];
    $amount = (int)$topup['amount'];

    $stmt = $db->prepare('UPDATE topups SET status="approved", approved_at=NOW(), approved_by=?, updated_at=NOW() WHERE id=?');
    $stmt->bind_param('ii', $adminId, $topupId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('UPDATE users SET sms_balance = sms_balance + ? WHERE id=?');
    $stmt->bind_param('ii', $smsUnits, $userId);
    $stmt->execute();
    $stmt->close();

    $reference = 'TOPUP-' . $topupId;
    $stmt = $db->prepare('UPDATE wallet_transactions SET status="approved", approved_at=NOW() WHERE user_id=? AND reference=? AND type="topup" AND status="pending"');
    $stmt->bind_param('is', $userId, $reference);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();

    if ($updated <= 0) {
        $stmt = $db->prepare('INSERT INTO wallet_transactions (user_id, type, sms_units, amount, currency, reference, status, created_at, approved_at) VALUES (?, "topup", ?, ?, "UGX", ?, "approved", NOW(), NOW())');
        $stmt->bind_param('iiis', $userId, $smsUnits, $amount, $reference);
        $stmt->execute();
        $stmt->close();
    }

    create_notification(
        $db,
        $userId,
        'Top-up approved',
        'Your top-up request #' . $topupId . ' has been approved. ' . number_format($smsUnits) . ' SMS units were added.',
        BASE_URL . 'modules/wallet/transactions.php',
        'topup_approved'
    );

    audit_log($db, $adminId, 'wallet.topup.approve', 'topups', $topupId, [
        'user_id' => $userId,
        'sms_units' => $smsUnits,
    ]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
}

redirect(BASE_URL . 'modules/admin/payments/index.php');
