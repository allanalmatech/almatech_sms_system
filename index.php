<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// ---------------------------
// If not logged in → go login
// ---------------------------
if (empty($_SESSION['user']['id'])) {
  redirect(BASE_URL . 'login.php');
}

// ---------------------------
// Maintenance enforcement
// (Admin allowed; others redirected)
// ---------------------------
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
if ($maintenanceEnabled && !is_admin_role($_SESSION['user']['role_id'] ?? null)) {
  redirect(BASE_URL . 'maintenance.php');
}

// ---------------------------
// Activation enforcement
// If user got disabled while logged in
// ---------------------------
$stmt = $db->prepare("SELECT status, disabled_reason FROM users WHERE id=? LIMIT 1");
$uid = (int)$_SESSION['user']['id'];
$stmt->bind_param("i", $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u || ($u['status'] ?? '') !== 'active') {
  // force logout
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();

  $msg = $u && !empty($u['disabled_reason']) ? $u['disabled_reason'] : 'Account disabled.';
  redirect('login.php?disabled=' . urlencode($msg));
}

// ---------------------------
// Success → go dashboard
// ---------------------------
redirect('dashboard.php');
