<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $subject = trim((string)($_POST['subject'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'general'));
    $priority = trim((string)($_POST['priority'] ?? 'medium'));
    $body = trim((string)($_POST['body'] ?? ''));

    $validCategories = ['billing', 'sms', 'sender_id', 'technical', 'general'];
    $validPriorities = ['low', 'medium', 'high'];

    if ($subject === '' || $body === '') {
        $error = 'Subject and message body are required.';
    } elseif (!in_array($category, $validCategories, true) || !in_array($priority, $validPriorities, true)) {
        $error = 'Invalid category or priority.';
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('INSERT INTO message_threads (created_by, subject, status, category, priority, created_at) VALUES (?, ?, "open", ?, ?, NOW())');
            $stmt->bind_param('isss', $userId, $subject, $category, $priority);
            $stmt->execute();
            $threadId = (int)$stmt->insert_id;
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO thread_participants (thread_id, user_id, role, joined_at) VALUES (?, ?, "owner", NOW())');
            $stmt->bind_param('ii', $threadId, $userId);
            $stmt->execute();
            $stmt->close();

            $admins = $db->query('SELECT id FROM users WHERE role_id=1 AND status="active"');
            if ($admins) {
                $stmt = $db->prepare('INSERT IGNORE INTO thread_participants (thread_id, user_id, role, joined_at) VALUES (?, ?, "support", NOW())');
                while ($row = $admins->fetch_assoc()) {
                    $aid = (int)$row['id'];
                    $stmt->bind_param('ii', $threadId, $aid);
                    $stmt->execute();
                    if ($aid !== $userId) {
                        create_notification($db, $aid, 'New support ticket', $subject, BASE_URL . 'modules/messaging/thread.php?id=' . $threadId, 'new_ticket');
                    }
                }
                $stmt->close();
            }

            $stmt = $db->prepare('INSERT INTO thread_messages (thread_id, from_user_id, body, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('iis', $threadId, $userId, $body);
            $stmt->execute();
            $stmt->close();

            audit_log($db, $userId, 'messaging.thread.create', 'message_threads', $threadId, [
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
            ]);

            $db->commit();
            redirect(BASE_URL . 'modules/messaging/thread.php?id=' . $threadId);
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
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
  <title>New Support Ticket • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Open New Ticket</h1>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select class="form-select" name="category">
                <option value="general">General</option>
                <option value="billing">Billing</option>
                <option value="sms">SMS</option>
                <option value="sender_id">Sender ID</option>
                <option value="technical">Technical</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="6" required></textarea></div>
          <button class="btn btn-primary">Create Ticket</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
