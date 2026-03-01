<?php
// maintenance.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// ---------------------------
// If maintenance is OFF, don't show this page
// ---------------------------
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
if (!$maintenanceEnabled) {
  if (!empty($_SESSION['user']['id'])) redirect(BASE_URL . 'dashboard.php');
  redirect(BASE_URL . 'login.php');
}

// Allow admin to bypass maintenance page
if (!empty($_SESSION['user']['id']) && is_admin_role($_SESSION['user']['role_id'] ?? null)) {
  redirect(BASE_URL . 'dashboard.php');
}

$message = app_setting(
  $db,
  'maintenance_message',
  'We are performing scheduled maintenance. Please try again later.'
);

// Optional: show contact info stored in settings (if you add them later)
$supportPhone = app_setting($db, 'support_phone', '');
$supportEmail = app_setting($db, 'support_email', '');

$brand = 'AlmaTech SMS';
$year  = date('Y');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance • <?= htmlspecialchars($brand) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body{background:#0b1220; color:#e7eefc;}
    .wrap{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
    .cardx{
      width: min(720px, 100%);
      border-radius: 18px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      padding: 28px;
      backdrop-filter: blur(8px);
    }
    .brand{font-weight:800; letter-spacing:.4px;}
    .muted{color: rgba(231,238,252,.75);}
    .pill{
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 14px; border-radius:999px;
      background: rgba(255,193,7,.15);
      border: 1px solid rgba(255,193,7,.35);
      color:#ffe7a1;
      font-weight:600;
    }
    .icon{
      width:46px;height:46px;border-radius:14px;
      display:grid;place-items:center;
      background: rgba(13,110,253,.18);
      border:1px solid rgba(13,110,253,.35);
      font-size:22px;
    }
    .btnx{
      border-radius: 12px;
      padding: 10px 14px;
    }
    .footer{font-size:.9rem; color: rgba(231,238,252,.6);}
    .hrx{border-color: rgba(255,255,255,.12);}
    a{color:#9ec5ff;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="cardx">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="icon">🛠️</div>
          <div>
            <div class="brand fs-4"><?= htmlspecialchars($brand) ?></div>
            <div class="muted">Bulk SMS • Phonebook • Wallet • Messaging • Notifications</div>
          </div>
        </div>
        <div class="pill">Maintenance Mode</div>
      </div>

      <hr class="hrx my-4">

      <h1 class="h4 mb-2">We’ll be back soon</h1>
      <p class="muted mb-4"><?= nl2br(htmlspecialchars($message)) ?></p>

      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary btnx" href="<?= BASE_URL ?>login.php">Try Login</a>
        <button class="btn btn-outline-light btnx" type="button" onclick="location.reload()">Refresh</button>
      </div>

      <?php if ($supportPhone !== '' || $supportEmail !== ''): ?>
        <hr class="hrx my-4">
        <div class="muted">
          <div class="fw-semibold mb-2">Need help?</div>
          <?php if ($supportPhone !== ''): ?>
            <div>Phone: <span class="text-white"><?= htmlspecialchars($supportPhone) ?></span></div>
          <?php endif; ?>
          <?php if ($supportEmail !== ''): ?>
            <div>Email: <span class="text-white"><?= htmlspecialchars($supportEmail) ?></span></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <hr class="hrx my-4">
      <div class="footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>Boxuganda, Kampala • Uganda</div>
        <div>© <?= $year ?> <?= htmlspecialchars($brand) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
