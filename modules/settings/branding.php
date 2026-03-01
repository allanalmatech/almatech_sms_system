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

    $businessName = trim((string)($_POST['business_name'] ?? ''));
    if ($businessName === '') {
        $error = 'Business name is required.';
    } else {
        $logoPath = null;
        if (!empty($_FILES['business_logo']['name'])) {
            $upload = $_FILES['business_logo'];
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Logo upload failed.';
            } else {
                $tmp = (string)$upload['tmp_name'];
                $size = (int)($upload['size'] ?? 0);
                $ext = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp'];

                if ($size > 2 * 1024 * 1024) {
                    $error = 'Logo must be 2MB or smaller.';
                } elseif (!in_array($ext, $allowed, true)) {
                    $error = 'Only PNG, JPG, JPEG, and WEBP logos are allowed.';
                } else {
                    $dir = BASE_PATH . 'assets/uploads/logos';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = 'logo_' . $userId . '_' . time() . '.' . $ext;
                    $target = $dir . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($tmp, $target)) {
                        $error = 'Could not save uploaded logo.';
                    } else {
                        $logoPath = 'assets/uploads/logos/' . $filename;
                    }
                }
            }
        }

        if ($error === '') {
            if ($logoPath !== null) {
                $stmt = $db->prepare('UPDATE users SET business_name=?, business_logo=? WHERE id=?');
                $stmt->bind_param('ssi', $businessName, $logoPath, $userId);
            } else {
                $stmt = $db->prepare('UPDATE users SET business_name=? WHERE id=?');
                $stmt->bind_param('si', $businessName, $userId);
            }
            $stmt->execute();
            $stmt->close();

            $_SESSION['user']['business_name'] = $businessName;
            if ($logoPath !== null) {
                $_SESSION['user']['business_logo'] = $logoPath;
            }

            audit_log($db, $userId, 'settings.branding.update', 'users', $userId, [
                'logo_updated' => $logoPath !== null,
            ]);
            $message = 'Branding details saved.';
        }
    }
}

$stmt = $db->prepare('SELECT business_name, business_logo FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$branding = $stmt->get_result()->fetch_assoc() ?: [];
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
  <title>Branding • AlmaTech SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
  <link rel="stylesheet" href="<?= BASE_URL . e($themePath) ?>">
</head>
<body>
<?php include BASE_PATH . 'templates/sidebar.php'; ?>
<div class="app-shell">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Branding</h1>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Business Name</label>
            <input class="form-control" name="business_name" required value="<?= e((string)($branding['business_name'] ?? '')) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Business Icon / Logo</label>
            <input type="file" class="form-control" name="business_logo" accept=".png,.jpg,.jpeg,.webp">
            <div class="form-text">Max size 2MB. Recommended square logo.</div>
          </div>

          <?php if (!empty($branding['business_logo'])): ?>
            <div class="mb-3">
              <div class="small text-muted mb-2">Current Logo</div>
              <img src="<?= BASE_URL . e((string)$branding['business_logo']) ?>" alt="Logo" style="width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid #ddd;">
            </div>
          <?php endif; ?>

          <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Branding</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
