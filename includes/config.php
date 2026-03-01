<?php
// includes/config.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Dynamic BASE_URL Detection
|--------------------------------------------------------------------------
| Automatically detects:
| - http / https
| - domain
| - subfolder
*/

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

$protocol = $https ? "https://" : "http://";
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

/*
|--------------------------------------------------------------------------
| Secure Dynamic BASE_URL Detection
|--------------------------------------------------------------------------
*/

$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (($_SERVER['SERVER_PORT'] ?? 80) == 443);

$protocol = $isHttps ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

/*
|--------------------------------------------------------------------------
| Detect Subfolder Automatically
|--------------------------------------------------------------------------
| Example: /almatech_sms/modules/settings/theme.php -> /almatech_sms
|--------------------------------------------------------------------------
*/

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$scriptPath = trim($scriptPath, '/');

// Find the project root by looking for common project indicators
$pathParts = explode('/', $scriptPath);
$projectRoot = '';

// If we're in a subdirectory structure, find the root
if (count($pathParts) > 1) {
    // Assume the first part is the project folder (e.g., 'almatech_sms')
    $projectRoot = '/' . $pathParts[0];
} else {
    $projectRoot = '';
}

$basePath = $projectRoot;

// Special handling for localhost development
if ($host === 'localhost' && ($basePath === '' || $basePath === '/')) {
    // Try to detect from DOCUMENT_ROOT
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot && strpos($docRoot, 'almatech_sms') !== false) {
        $basePath = '/almatech_sms';
    }
}

define('BASE_URL', $protocol . $host . $basePath . '/');

// Root filesystem path
define('BASE_PATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);

// Default theme fallback
define('DEFAULT_THEME', 'theme_blue');
