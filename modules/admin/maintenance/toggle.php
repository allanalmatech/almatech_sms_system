<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'modules/admin/maintenance/index.php');
}

require_post_csrf();

$enabled = ((string)($_POST['enabled'] ?? '0') === '1') ? '1' : '0';

$stmt = $db->prepare('INSERT INTO app_settings (`key`,`value`) VALUES ("maintenance_enabled", ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
$stmt->bind_param('s', $enabled);
$stmt->execute();
$stmt->close();

audit_log($db, (int)$_SESSION['user']['id'], 'maintenance.toggle', 'app_settings', null, ['enabled' => $enabled]);

redirect(BASE_URL . 'modules/admin/maintenance/index.php');
