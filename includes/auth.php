<?php
declare(strict_types=1);

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return null;
        }
        return $_SESSION['user'];
    }
}

if (!function_exists('auth_logout_now')) {
    function auth_logout_now(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        session_destroy();
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (empty($_SESSION['user']['id'])) {
            redirect(BASE_URL . 'login.php');
        }
    }
}

if (!function_exists('refresh_session_user')) {
    function refresh_session_user(mysqli $db): bool
    {
        if (empty($_SESSION['user']['id'])) {
            return false;
        }

        $userId = (int)$_SESSION['user']['id'];
        $stmt = $db->prepare(
            'SELECT id, username, role_id, status, disabled_reason, theme, business_name, business_logo, full_name, phone, email, sms_balance FROM users WHERE id=? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'role_id' => isset($user['role_id']) ? (int)$user['role_id'] : null,
            'status' => (string)$user['status'],
            'disabled_reason' => (string)($user['disabled_reason'] ?? ''),
            'theme' => (string)($user['theme'] ?: DEFAULT_THEME),
            'business_name' => (string)($user['business_name'] ?? ''),
            'business_logo' => (string)($user['business_logo'] ?? ''),
            'full_name' => (string)($user['full_name'] ?? ''),
            'phone' => (string)($user['phone'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'sms_balance' => (int)($user['sms_balance'] ?? 0),
        ];

        return true;
    }
}

if (!function_exists('enforce_active_user')) {
    function enforce_active_user(mysqli $db): void
    {
        if (empty($_SESSION['user']['id'])) {
            return;
        }

        if (!refresh_session_user($db)) {
            auth_logout_now();
            redirect(BASE_URL . 'login.php?disabled=' . urlencode('Account not found.'));
        }

        if (($_SESSION['user']['status'] ?? '') !== 'active') {
            $reason = trim((string)($_SESSION['user']['disabled_reason'] ?? 'Account disabled.'));
            auth_logout_now();
            redirect(BASE_URL . 'login.php?disabled=' . urlencode($reason));
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        require_login();
        if (!is_admin_role($_SESSION['user']['role_id'] ?? null)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}
