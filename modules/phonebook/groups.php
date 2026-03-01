<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
enforce_active_user($db);
enforce_maintenance_mode($db);

$userId = (int)$_SESSION['user']['id'];
ensure_default_contact_group($db, $userId);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $name = trim((string)($_POST['name'] ?? ''));

    if ($name === '') {
        $error = 'Group name is required.';
    } else {
        $stmt = $db->prepare('INSERT INTO contact_groups (user_id, name, created_at) VALUES (?, ?, NOW())');
        $stmt->bind_param('is', $userId, $name);
        if ($stmt->execute()) {
            $message = 'Group created.';
            audit_log($db, $userId, 'phonebook.group.create', 'contact_groups', (int)$stmt->insert_id, ['name' => $name]);
        } else {
            $error = 'Could not create group. It may already exist.';
        }
        $stmt->close();
    }
}

$stmt = $db->prepare(
    'SELECT g.id, g.name, g.created_at, COUNT(gc.contact_id) AS contacts_count
     FROM contact_groups g
     LEFT JOIN group_contacts gc ON gc.group_id = g.id
     WHERE g.user_id = ?
     GROUP BY g.id
     ORDER BY g.name ASC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
  <title>Phonebook Groups • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0">Contact Groups</h1>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Create Group</h5>
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label class="form-label">Group Name</label>
                <input class="form-control" name="name" placeholder="e.g. Retail Customers" required>
              </div>
              <button class="btn btn-primary">Create Group</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th>Name</th><th>Contacts</th><th>Created</th></tr>
              </thead>
              <tbody>
              <?php foreach ($groups as $g): ?>
                <tr>
                  <td><?= e((string)$g['name']) ?></td>
                  <td><?= (int)$g['contacts_count'] ?></td>
                  <td><?= e((string)$g['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
