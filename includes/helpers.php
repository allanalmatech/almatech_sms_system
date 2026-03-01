<?php
// includes/helpers.php
declare(strict_types=1);

// ------------------------------------------------------------
// Application Helper Functions
// ------------------------------------------------------------

function app_setting(mysqli $db, string $key, string $default = ''): string {
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE `key`=? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (string)$res['value'] : $default;
}

function is_admin_role(?int $role_id): bool {
    // seed.sql uses role_id=1 for admin
    return (int)$role_id === 1;
}

function redirect(string $to): void {
    header("Location: $to");
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['csrf_token'];
}

function require_post_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('normalize_ug_phone')) {
    function normalize_ug_phone(string $raw): ?string
    {
        $value = preg_replace('/\s+/', '', trim($raw));
        if ($value === '') {
            return null;
        }

        $value = str_replace(['-', '(', ')'], '', $value);
        if (str_starts_with($value, '+')) {
            $value = '+' . preg_replace('/\D+/', '', substr($value, 1));
        } else {
            $value = preg_replace('/\D+/', '', $value);
        }

        if (preg_match('/^\+?256([2347]\d{8})$/', $value, $m)) {
            return '+256' . $m[1];
        }

        if (preg_match('/^0([2347]\d{8})$/', $value, $m)) {
            return '+256' . $m[1];
        }

        if (preg_match('/^([2347]\d{8})$/', $value, $m)) {
            return '+256' . $m[1];
        }

        return null;
    }
}

if (!function_exists('extract_phone_candidates')) {
    function extract_phone_candidates(string $input): array
    {
        $chunks = preg_split('/[\r\n,;]+/', $input) ?: [];
        $numbers = [];
        foreach ($chunks as $chunk) {
            $clean = trim($chunk);
            if ($clean !== '') {
                $numbers[] = $clean;
            }
        }
        return $numbers;
    }
}

if (!function_exists('sms_segments')) {
    function sms_segments(string $message): int
    {
        $message = trim($message);
        if ($message === '') {
            return 0;
        }
        $isGsmBasic = preg_match('/^[\x20-\x7E\r\n\t]+$/', $message) === 1;
        $len = mb_strlen($message, 'UTF-8');

        if ($isGsmBasic) {
            return (int)max(1, (int)ceil($len / 160));
        }
        return (int)max(1, (int)ceil($len / 70));
    }
}

if (!function_exists('audit_log')) {
    function audit_log(
        mysqli $db,
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip, user_agent, details_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ississs', $userId, $action, $entity, $entityId, $ip, $ua, $json);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ensure_default_contact_group')) {
    function ensure_default_contact_group(mysqli $db, int $userId): void
    {
        $stmt = $db->prepare('INSERT IGNORE INTO contact_groups (user_id, name, created_at) VALUES (?, "All", NOW())');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}
