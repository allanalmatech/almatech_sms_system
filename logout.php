<?php
// logout.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Destroy all session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Clear remember-me cookie if exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Redirect to login page
header("Location: " . BASE_URL . "login.php");
exit;