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
    $stmt = $db->prepare('UPDATE users SET status="active", disabled_reason=NULL, disabled_at=NULL WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    audit_log($db, (int)$_SESSION['user']['id'], 'users.activate', 'users', $id);
}

redirect(BASE_URL . 'modules/admin/users/index.php');
