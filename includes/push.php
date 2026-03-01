<?php
declare(strict_types=1);

if (!function_exists('save_push_subscription')) {
    function save_push_subscription(
        mysqli $db,
        int $userId,
        string $endpoint,
        string $p256dh,
        string $auth,
        string $userAgent = ''
    ): bool {
        $stmt = $db->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, created_at, last_seen_at, is_active)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)
             ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), user_agent=VALUES(user_agent), last_seen_at=NOW(), is_active=1'
        );
        $stmt->bind_param('issss', $userId, $endpoint, $p256dh, $auth, $userAgent);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('disable_push_subscription')) {
    function disable_push_subscription(mysqli $db, int $userId, string $endpoint): bool
    {
        $stmt = $db->prepare('UPDATE push_subscriptions SET is_active=0 WHERE user_id=? AND endpoint=?');
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
