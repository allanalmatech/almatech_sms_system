<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_admin();
enforce_active_user($db);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect(BASE_URL . 'modules/admin/users/index.php');
}

$message = '';
$error = '';

$networks = [];
$pricingMap = [];

$netRes = $db->query('SELECT id, name, code, is_active FROM networks ORDER BY id ASC');
if ($netRes) {
    $networks = $netRes->fetch_all(MYSQLI_ASSOC);
}

$stmt = $db->prepare('SELECT network_id, price_per_sms FROM user_network_pricing WHERE user_id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$priceRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($priceRows as $pr) {
    $pricingMap[(int)$pr['network_id']] = (int)$pr['price_per_sms'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $roleId = (int)($_POST['role_id'] ?? 2);
    $status = (string)($_POST['status'] ?? 'active');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $error = 'Invalid status value.';
    } elseif (!in_array($roleId, [1, 2], true)) {
        $error = 'Invalid role value.';
    } else {
        $tariffs = $_POST['tariff'] ?? [];
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('UPDATE users SET full_name=?, email=?, phone=?, role_id=?, status=? WHERE id=?');
            $stmt->bind_param('sssisi', $fullName, $email, $phone, $roleId, $status, $id);
            $stmt->execute();
            $stmt->close();

            $upsert = $db->prepare(
                'INSERT INTO user_network_pricing (user_id, network_id, price_per_sms, currency, created_at, updated_at)
                 VALUES (?, ?, ?, "UGX", NOW(), NOW())
                 ON DUPLICATE KEY UPDATE price_per_sms=VALUES(price_per_sms), updated_at=NOW()'
            );

            foreach ($networks as $network) {
                $nid = (int)$network['id'];
                $rawPrice = $tariffs[$nid] ?? ($pricingMap[$nid] ?? 17);
                $price = (int)$rawPrice;
                if ($price < 0) {
                    throw new RuntimeException('Tariff values must be zero or greater.');
                }
                $upsert->bind_param('iii', $id, $nid, $price);
                $upsert->execute();
                $pricingMap[$nid] = $price;
            }
            $upsert->close();

            $db->commit();

            audit_log($db, (int)$_SESSION['user']['id'], 'users.update', 'users', $id, [
                'role_id' => $roleId,
                'status' => $status,
                'tariff_updated' => true,
            ]);
            $message = 'User and tariff settings updated.';
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare('SELECT id, username, full_name, email, phone, role_id, status, sms_balance FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    exit('User not found.');
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
  <title>Edit User • AlmaTech SMS</title>
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
      <h1 class="h3 mb-0">Edit User: <?= e((string)$user['username']) ?></h1>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>modules/admin/users/index.php">Back</a>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm" style="max-width:900px;">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" value="<?= e((string)$user['username']) ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e((string)$user['full_name']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= e((string)$user['email']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e((string)$user['phone']) ?>"></div>
            <div class="col-md-4">
              <label class="form-label">Role</label>
              <select class="form-select" name="role_id">
                <option value="1" <?= (int)$user['role_id'] === 1 ? 'selected' : '' ?>>Admin</option>
                <option value="2" <?= (int)$user['role_id'] !== 1 ? 'selected' : '' ?>>Client</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php foreach (['active','inactive','suspended'] as $status): ?>
                  <option value="<?= $status ?>" <?= $user['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">SMS Balance</label><input class="form-control" value="<?= number_format((int)$user['sms_balance']) ?>" disabled></div>

            <div class="col-12 mt-3">
              <div class="border rounded p-3 bg-light-subtle">
                <div class="fw-semibold mb-2">Tariff Settings (Price per SMS in UGX)</div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Network</th>
                        <th>Code</th>
                        <th style="width: 220px;">Price per SMS (UGX)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($networks as $network): ?>
                        <?php $nid = (int)$network['id']; ?>
                        <tr>
                          <td>
                            <?= e((string)$network['name']) ?>
                            <?php if ((int)$network['is_active'] !== 1): ?>
                              <span class="badge text-bg-secondary ms-1">Inactive</span>
                            <?php endif; ?>
                          </td>
                          <td><?= e((string)$network['code']) ?></td>
                          <td>
                            <input
                              type="number"
                              class="form-control"
                              name="tariff[<?= $nid ?>]"
                              min="0"
                              step="1"
                              value="<?= (int)($pricingMap[$nid] ?? 17) ?>"
                            >
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Save Changes</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
