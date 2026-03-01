<?php
// login.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// ------------------------------------------------------------
// Already logged in?
// ------------------------------------------------------------
if (!empty($_SESSION['user']['id'])) {
  redirect(BASE_URL . 'dashboard.php');
}

// ------------------------------------------------------------
// Maintenance enforcement (blocks clients, allows admin)
// ------------------------------------------------------------
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
$maintenanceMessage = app_setting($db, 'maintenance_message', 'We are performing maintenance.');

if ($maintenanceEnabled) {
  // We don't know role yet (not logged in), so we allow login attempt
  // BUT if the user is NOT admin after login, we will block them.
  // Also show a banner on the login page.
}

// ------------------------------------------------------------
// Handle login
// ------------------------------------------------------------
$errors = [];
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$remember = (string)($_POST['remember'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_post_csrf();

  if ($username === '' || $password === '') {
    $errors[] = 'Username and password are required.';
  } else {
    $stmt = $db->prepare("
      SELECT
        id, username, password_hash, role_id, status,
        disabled_reason, theme, business_name, business_logo,
        full_name, phone, email, sms_balance
      FROM users
      WHERE username = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
      $errors[] = 'Invalid username or password.';
    } else {
      // Activation enforcement
      if ($user['status'] !== 'active') {
        $reason = trim((string)($user['disabled_reason'] ?? ''));
        $errors[] = $reason !== ''
          ? "Account is disabled: {$reason}"
          : "Account is disabled. Contact support.";
      } else {
        // Maintenance enforcement post-login (allow admin only)
        $roleId = isset($user['role_id']) ? (int)$user['role_id'] : null;
        if ($maintenanceEnabled && !is_admin_role($roleId)) {
          // Do NOT log them in; show maintenance message.
          $errors[] = $maintenanceMessage;
        } else {
          // Success: create session
          session_regenerate_id(true);

          $_SESSION['user'] = [
            'id'            => (int)$user['id'],
            'username'      => (string)$user['username'],
            'role_id'       => $roleId,
            'theme'         => (string)($user['theme'] ?: 'theme_blue'),
            'business_name' => (string)($user['business_name'] ?? ''),
            'business_logo' => (string)($user['business_logo'] ?? ''),
            'full_name'     => (string)($user['full_name'] ?? ''),
            'phone'         => (string)($user['phone'] ?? ''),
            'email'         => (string)($user['email'] ?? ''),
            'sms_balance'   => (int)($user['sms_balance'] ?? 0),
          ];

          // Update last login metadata
          $ip = $_SERVER['REMOTE_ADDR'] ?? null;
          $stmt = $db->prepare("UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?");
          $stmt->bind_param("si", $ip, $_SESSION['user']['id']);
          $stmt->execute();
          $stmt->close();

          // Optional: remember-me cookie (simple version)
          // NOTE: For production, use a remember_tokens table and rotate tokens.
          if ($remember) {
            setcookie('remember_user', (string)$_SESSION['user']['username'], [
              'expires'  => time() + (86400 * 30),
              'path'     => '/',
              'secure'   => !empty($_SERVER['HTTPS']),
              'httponly' => true,
              'samesite' => 'Lax'
            ]);
          }

          redirect(BASE_URL . 'dashboard.php');
        }
      }
    }
  }
}

// Prefill username from cookie (optional)
if ($username === '' && !empty($_COOKIE['remember_user'])) {
  $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$_COOKIE['remember_user']);
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login • AlmaTech SMS</title>

  <!-- Bootstrap 5 (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body{background:#f6f8fb;}
    .card{border:0; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08);}
    .brand{font-weight:800; letter-spacing:.5px;}
    .hint{font-size:.9rem; color:#6c757d;}
    .form-control{border-radius:12px;}
    .btn{border-radius:12px;}
    .badge-soft{background:rgba(13,110,253,.12); color:#0d6efd;}
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-5">
        <div class="text-center mb-4">
          <div class="brand fs-3">AlmaTech SMS</div>
          <div class="hint">Bulk SMS • Phonebook • Wallet • Messaging • Notifications</div>
        </div>

        <div class="card p-4 p-md-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-bold fs-5">Sign in</div>
            <span class="badge badge-soft px-3 py-2">Secure</span>
          </div>

          <?php if ($maintenanceEnabled): ?>
            <div class="alert alert-warning">
              <div class="fw-bold">Maintenance Mode</div>
              <div><?= htmlspecialchars($maintenanceMessage) ?></div>
              <div class="small mt-2">Only admins can access the dashboard during maintenance.</div>
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                  <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="username"
                     value="<?= htmlspecialchars($username) ?>" placeholder="e.g. almatech" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="password"
                     placeholder="Enter your password" required>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
              </div>
              <a class="small text-decoration-none" href="#" onclick="alert('Add a reset password flow later.');return false;">Forgot password?</a>
            </div>

            <button class="btn btn-primary w-100 py-2" type="submit">Login</button>

            <div class="mt-3 hint">
              If your account is inactive, contact support/admin to activate it.
            </div>
          </form>
        </div>

        <!-- Test Credentials Section -->
        <div class="card mt-3 p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold small">🔑 Test Accounts</div>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleTestAccounts()">Show/Hide</button>
          </div>
          
          <div id="testAccounts" style="display: none;">
            <div class="row g-2">
              <!-- Admin Account -->
              <div class="col-12">
                <div class="card border-secondary">
                  <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <span class="badge bg-danger mb-1">Admin</span>
                        <div class="small">
                          <strong>Username:</strong> admin<br>
                          <strong>Password:</strong> Admin@123
                        </div>
                      </div>
                      <div>
                        <button class="btn btn-sm btn-primary" onclick="fillCredentials('admin', 'Admin@123')">
                          📋 Use Admin
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Client Account -->
              <div class="col-12">
                <div class="card border-secondary">
                  <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <span class="badge bg-primary mb-1">Client</span>
                        <div class="small">
                          <strong>Username:</strong> almatech<br>
                          <strong>Password:</strong> Alma@123
                        </div>
                      </div>
                      <div>
                        <button class="btn btn-sm btn-success" onclick="fillCredentials('almatech', 'Alma@123')">
                          📋 Use Client
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="small text-muted mt-2">
              💡 Click the buttons to auto-fill the login form
            </div>
          </div>
        </div>

        <div class="text-center mt-4 small text-muted">
          Alma Tech Labs Inc, Uganda • © Copyright <?= date('Y') ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Toggle test accounts visibility
    function toggleTestAccounts() {
      const testAccounts = document.getElementById('testAccounts');
      testAccounts.style.display = testAccounts.style.display === 'none' ? 'block' : 'none';
    }
    
    // Fill credentials into form
    function fillCredentials(username, password) {
      document.querySelector('input[name="username"]').value = username;
      document.querySelector('input[name="password"]').value = password;
      
      // Visual feedback
      const inputs = document.querySelectorAll('input[name="username"], input[name="password"]');
      inputs.forEach(input => {
        input.classList.add('is-valid');
        setTimeout(() => input.classList.remove('is-valid'), 2000);
      });
      
      // Show success message
      const form = document.querySelector('form');
      const alert = document.createElement('div');
      alert.className = 'alert alert-success alert-dismissible fade show mt-3';
      alert.innerHTML = `
        ✅ Credentials filled! You can now click Login.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      form.appendChild(alert);
      
      // Auto-dismiss after 3 seconds
      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove();
        }
      }, 3000);
    }
    
    // Auto-show test accounts on page load for development
    document.addEventListener('DOMContentLoaded', function() {
      // Only show on localhost or if URL contains ?test=true
      if (window.location.hostname === 'localhost' || window.location.search.includes('test=true')) {
        document.getElementById('testAccounts').style.display = 'block';
      }
    });
  </script>

  <?php include __DIR__ . '/templates/footer.php'; ?>
</body>
</html>
