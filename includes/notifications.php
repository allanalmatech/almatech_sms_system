<?php
declare(strict_types=1);

if (!function_exists('create_notification')) {
    function create_notification(
        mysqli $db,
        int $userId,
        string $title,
        string $body,
        ?string $url = null,
        ?string $type = null
    ): bool {
        $stmt = $db->prepare('INSERT INTO notifications (user_id, title, body, url, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
        $stmt->bind_param('issss', $userId, $title, $body, $url, $type);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('notification_unread_count')) {
    function notification_unread_count(mysqli $db, int $userId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('mark_notification_read')) {
    function mark_notification_read(mysqli $db, int $userId, int $notificationId): bool
    {
        $stmt = $db->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows > 0;
        $stmt->close();
        return $affected;
    }
}

if (!function_exists('mark_all_notifications_read')) {
    function mark_all_notifications_read(mysqli $db, int $userId): void
    {
        $stmt = $db->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}
