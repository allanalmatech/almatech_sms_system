<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'modules/admin/users/index.php');
}

require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $reason = 'Deactivated by admin';
    $stmt = $db->prepare('UPDATE users SET status="inactive", disabled_reason=?, disabled_at=NOW() WHERE id=?');
    $stmt->bind_param('si', $reason, $id);
    $stmt->execute();
    $stmt->close();

    audit_log($db, (int)$_SESSION['user']['id'], 'users.deactivate', 'users', $id, ['reason' => $reason]);
}

redirect(BASE_URL . 'modules/admin/users/index.php');
