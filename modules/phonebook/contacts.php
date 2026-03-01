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
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $groupId = (int)($_POST['group_id'] ?? 0);
    $phone = normalize_ug_phone($phoneRaw);

    if (!$phone) {
        $error = 'Enter a valid Uganda phone number.';
    } else {
        $stmt = $db->prepare('INSERT INTO contacts (user_id, phone_e164, phone_raw, name, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('isss', $userId, $phone, $phoneRaw, $name);
        $ok = $stmt->execute();
        $contactId = (int)$stmt->insert_id;
        $stmt->close();

        if ($ok) {
            if ($groupId > 0) {
                $stmt = $db->prepare('INSERT IGNORE INTO group_contacts (group_id, contact_id, created_at) VALUES (?, ?, NOW())');
                $stmt->bind_param('ii', $groupId, $contactId);
                $stmt->execute();
                $stmt->close();
            }
            $message = 'Contact added.';
        } else {
            $error = 'Contact already exists for this number.';
        }
    }
}

$search = trim((string)($_GET['q'] ?? ''));

$groupsStmt = $db->prepare('SELECT id, name FROM contact_groups WHERE user_id=? ORDER BY name ASC');
$groupsStmt->bind_param('i', $userId);
$groupsStmt->execute();
$groups = $groupsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$groupsStmt->close();

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $db->prepare('SELECT id, name, phone_e164, created_at FROM contacts WHERE user_id=? AND (name LIKE ? OR phone_e164 LIKE ?) ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('iss', $userId, $like, $like);
} else {
    $stmt = $db->prepare('SELECT id, name, phone_e164, created_at FROM contacts WHERE user_id=? ORDER BY id DESC LIMIT 200');
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Contacts • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0">Contacts</h1>
      <form class="d-flex" method="get">
        <input class="form-control me-2" name="q" value="<?= e($search) ?>" placeholder="Search by name or phone">
        <button class="btn btn-outline-secondary">Search</button>
      </form>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Add Contact</h5>
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name"></div>
              <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" required></div>
              <div class="mb-3">
                <label class="form-label">Group</label>
                <select class="form-select" name="group_id">
                  <option value="0">No group</option>
                  <?php foreach ($groups as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= e((string)$g['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button class="btn btn-primary">Save Contact</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>Name</th><th>Phone</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($contacts as $c): ?>
                  <tr>
                    <td><?= e((string)$c['name']) ?></td>
                    <td><?= e((string)$c['phone_e164']) ?></td>
                    <td><?= e((string)$c['created_at']) ?></td>
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
