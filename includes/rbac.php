<?php
declare(strict_types=1);

if (!function_exists('has_permission')) {
    function has_permission(mysqli $db, int $userId, string $permKey): bool
    {
        if ($permKey === '') {
            return false;
        }

        if (is_admin_role((int)($_SESSION['user']['role_id'] ?? 0))) {
            return true;
        }

        $cacheKey = 'perm_' . $permKey;
        if (isset($_SESSION['_permission_cache'][$cacheKey])) {
            return (bool)$_SESSION['_permission_cache'][$cacheKey];
        }

        $stmt = $db->prepare(
            'SELECT 1 FROM users u INNER JOIN role_permissions rp ON rp.role_id = u.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE u.id = ? AND p.perm_key = ? LIMIT 1'
        );
        $stmt->bind_param('is', $userId, $permKey);
        $stmt->execute();
        $allowed = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();

        $_SESSION['_permission_cache'][$cacheKey] = $allowed;
        return $allowed;
    }
}

if (!function_exists('require_permission')) {
    function require_permission(mysqli $db, string $permKey): void
    {
        require_login();
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0 || !has_permission($db, $userId, $permKey)) {
            http_response_code(403);
            exit('Permission denied.');
        }
    }
}
