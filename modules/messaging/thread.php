<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$isAdmin = is_admin_role($_SESSION['user']['role_id'] ?? null);

$threadId = (int)($_GET['id'] ?? 0);
if ($threadId <= 0) {
    redirect(BASE_URL . 'modules/messaging/inbox.php');
}

$stmt = $db->prepare('SELECT t.id, t.created_by, t.subject, t.category, t.priority, t.status, t.created_at, u.username AS owner_username FROM message_threads t INNER JOIN users u ON u.id = t.created_by WHERE t.id=? LIMIT 1');
$stmt->bind_param('i', $threadId);
$stmt->execute();
$thread = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$thread) {
    http_response_code(404);
    exit('Thread not found.');
}

if (!$isAdmin) {
    $stmt = $db->prepare('SELECT 1 FROM thread_participants WHERE thread_id=? AND user_id=? LIMIT 1');
    $stmt->bind_param('ii', $threadId, $userId);
    $stmt->execute();
    $isParticipant = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$isParticipant) {
        http_response_code(403);
        exit('Access denied.');
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? 'reply');

    if ($action === 'reply') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            $error = 'Reply body is required.';
        } else {
            $stmt = $db->prepare('INSERT INTO thread_messages (thread_id, from_user_id, body, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('iis', $threadId, $userId, $body);
            $stmt->execute();
            $messageId = (int)$stmt->insert_id;
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())');
            $stmt->bind_param('ii', $messageId, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('UPDATE message_threads SET updated_at=NOW(), status=IF(status="closed","open",status) WHERE id=?');
            $stmt->bind_param('i', $threadId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('SELECT user_id FROM thread_participants WHERE thread_id=? AND user_id<>?');
            $stmt->bind_param('ii', $threadId, $userId);
            $stmt->execute();
            $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($participants as $p) {
                create_notification(
                    $db,
                    (int)$p['user_id'],
                    'New message in ticket #' . $threadId,
                    mb_substr($body, 0, 140, 'UTF-8'),
                    BASE_URL . 'modules/messaging/thread.php?id=' . $threadId,
                    'new_message'
                );
            }

            audit_log($db, $userId, 'messaging.thread.reply', 'message_threads', $threadId);
            $message = 'Reply posted.';
        }
    } elseif ($action === 'status' && $isAdmin) {
        $status = (string)($_POST['status'] ?? 'open');
        $allowed = ['open', 'pending', 'resolved', 'closed'];
        if (!in_array($status, $allowed, true)) {
            $error = 'Invalid status.';
        } else {
            $stmt = $db->prepare('UPDATE message_threads SET status=?, updated_at=NOW() WHERE id=?');
            $stmt->bind_param('si', $status, $threadId);
            $stmt->execute();
            $stmt->close();
            audit_log($db, $userId, 'messaging.thread.status', 'message_threads', $threadId, ['status' => $status]);
            $message = 'Thread status updated.';
            $thread['status'] = $status;
        }
    }
}

$stmt = $db->prepare('SELECT id, from_user_id, body, created_at FROM thread_messages WHERE thread_id=? ORDER BY id ASC');
$stmt->bind_param('i', $threadId);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $db->prepare('SELECT username, id FROM users WHERE id IN (SELECT user_id FROM thread_participants WHERE thread_id=?)');
$stmt->bind_param('i', $threadId);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$userMap = [];
foreach ($users as $u) {
    $userMap[(int)$u['id']] = (string)$u['username'];
}

if (!empty($messages)) {
    $markStmt = $db->prepare('INSERT IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())');
    foreach ($messages as $m) {
        if ((int)$m['from_user_id'] === $userId) {
            continue;
        }
        $mid = (int)$m['id'];
        $markStmt->bind_param('ii', $mid, $userId);
        $markStmt->execute();
    }
    $markStmt->close();
}

$theme = $_SESSION['user']['theme'] ?? DEFAULT_THEME;
$themePath = 'assets/css/themes/' . $theme . '.css';
if (!file_exists(BASE_PATH . $themePath)) {
    $themePath = 'assets/css/themes/' . DEFAULT_THEME . '.css';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Thread #<?= (int)$threadId ?> • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div>
        <h1 class="h3 mb-1">#<?= (int)$thread['id'] ?> - <?= e((string)$thread['subject']) ?></h1>
        <div class="text-muted">Category: <?= e((string)$thread['category']) ?> | Priority: <?= e((string)$thread['priority']) ?> | Status: <strong><?= e((string)$thread['status']) ?></strong></div>
      </div>
      <?php if ($isAdmin): ?>
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <select name="status" class="form-select">
            <?php foreach (['open','pending','resolved','closed'] as $status): ?>
              <option value="<?= $status ?>" <?= $thread['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary">Update</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <?php foreach ($messages as $m): ?>
          <?php $mine = (int)$m['from_user_id'] === $userId; ?>
          <div class="p-3 rounded mb-3 <?= $mine ? 'bg-primary-subtle border border-primary-subtle' : 'bg-light border' ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <strong><?= e($userMap[(int)$m['from_user_id']] ?? ('User ' . (int)$m['from_user_id'])) ?></strong>
              <small class="text-muted"><?= e((string)$m['created_at']) ?></small>
            </div>
            <div style="white-space:pre-wrap;"><?= e((string)$m['body']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply">
          <div class="mb-3"><label class="form-label">Reply</label><textarea class="form-control" name="body" rows="4" required></textarea></div>
          <button class="btn btn-primary">Send Reply</button>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>modules/messaging/inbox.php">Back to Inbox</a>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
