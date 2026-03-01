<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'modules/admin/broadcast/index.php');
}

require_post_csrf();

$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));
$url = trim((string)($_POST['url'] ?? ''));

if ($title === '' || $body === '') {
    redirect(BASE_URL . 'modules/admin/broadcast/index.php');
}

$result = $db->query('SELECT id FROM users WHERE status="active" AND role_id <> 1');
$count = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $uid = (int)$row['id'];
        if (create_notification($db, $uid, $title, $body, $url !== '' ? $url : null, 'broadcast')) {
            $count++;
        }
    }
}

audit_log($db, (int)$_SESSION['user']['id'], 'broadcast.send', 'notifications', null, [
    'title' => $title,
    'recipients' => $count,
]);

redirect(BASE_URL . 'modules/admin/broadcast/index.php?sent=' . $count);
