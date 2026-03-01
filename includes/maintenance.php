<?php
declare(strict_types=1);

if (!function_exists('is_maintenance_enabled')) {
    function is_maintenance_enabled(mysqli $db): bool
    {
        return app_setting($db, 'maintenance_enabled', '0') === '1';
    }
}

if (!function_exists('enforce_maintenance_mode')) {
    function enforce_maintenance_mode(mysqli $db): void
    {
        if (!is_maintenance_enabled($db)) {
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        if (substr($path, -strlen('/maintenance.php')) === '/maintenance.php') {
            return;
        }

        if (!empty($_SESSION['user']['id']) && is_admin_role($_SESSION['user']['role_id'] ?? null)) {
            return;
        }

        redirect(BASE_URL . 'maintenance.php');
    }
}
