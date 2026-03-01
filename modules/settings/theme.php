<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

if (empty($_SESSION['user']['id'])) {
    redirect(BASE_URL . 'login.php');
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) {
    die('Database connection not available.');
}

$userId = (int)$_SESSION['user']['id'];

// Maintenance enforcement
$maintenanceEnabled = app_setting($db, 'maintenance_enabled', '0') === '1';
if ($maintenanceEnabled && !is_admin_role($_SESSION['user']['role_id'] ?? null)) {
    redirect(BASE_URL . 'maintenance.php');
}

// ===========================
// CSRF
// ===========================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// ===========================
// Handle Theme Update
// ===========================
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
        $messageType = 'danger';
    } else {

        $selectedTheme = trim((string)($_POST['theme'] ?? ''));

        $stmt = $db->prepare("SELECT theme_key FROM themes WHERE theme_key=? AND is_active=1 LIMIT 1");
        $stmt->bind_param("s", $selectedTheme);
        $stmt->execute();
        $valid = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($valid) {

            $stmt = $db->prepare("UPDATE users SET theme=? WHERE id=? LIMIT 1");
            $stmt->bind_param("si", $selectedTheme, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user']['theme'] = $selectedTheme;

            $message = "Theme updated successfully.";
            $messageType = 'success';

        } else {
            $message = "Invalid theme selected.";
            $messageType = 'danger';
        }
    }
}

// Load themes
$result = $db->query("
  SELECT theme_key, label, css_file, primary_color
  FROM themes
  WHERE is_active=1
  ORDER BY sort_order ASC
");
$themes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$currentTheme = $_SESSION['user']['theme'] ?? DEFAULT_THEME;

// Fallback preview colors
$fallbackColors = [
  '#2563eb', '#16a34a', '#dc2626', '#f59e0b',
  '#7c3aed', '#0ea5e9', '#ea580c', '#14b8a6',
  '#334155', '#111827'
];

$pageTitle = "Theme Settings";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> • AlmaTech SMS</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">

<?php
$themePath = 'assets/css/themes/' . $currentTheme . '.css';
if (!file_exists(BASE_PATH . $themePath)) {
    $themePath = 'assets/css/themes/' . DEFAULT_THEME . '.css';
}
?>
<link rel="stylesheet" href="<?= BASE_URL . $themePath ?>">
</head>

<body>

<?php include BASE_PATH . 'templates/sidebar.php'; ?>

<div class="app-shell">
<div class="container-fluid p-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Theme Settings</h1>
            <p class="text-muted mb-0">Customize your dashboard appearance.</p>
        </div>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="row g-4">
            <?php foreach ($themes as $i => $theme):
                $key = $theme['theme_key'];
                $label = $theme['label'];
                $active = ($key === $currentTheme);
                
                // Pick preview color per theme
                $previewColor = trim((string)($theme['primary_color'] ?? ''));
                if ($previewColor === '') {
                    $previewColor = $fallbackColors[$i % count($fallbackColors)];
                }
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <label class="w-100">
                    <div class="d-flex align-items-start gap-3">
                        <input type="radio" name="theme" value="<?= htmlspecialchars($key) ?>"
                               <?= $active ? 'checked' : '' ?> class="form-check-input mt-1">
                        
                        <div class="flex-grow-1">
                            <div class="card shadow-sm border-0 p-3 <?= $active ? 'border border-primary' : '' ?>" data-theme-card>
                                <div class="mb-3 rounded"
                                     style="height:70px; background: <?= htmlspecialchars($previewColor) ?>;">
                                </div>
                                
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="rounded-circle border" 
                                          style="width:14px;height:14px;background:<?= htmlspecialchars($previewColor) ?>;"></span>
                                    <small class="text-muted"><?= htmlspecialchars($previewColor) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <strong><?= htmlspecialchars($label) ?></strong>
                                    <?php if ($active): ?>
                                        <span class="badge bg-success">Current</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($key) ?></small>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <button class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i> Save Theme
            </button>
        </div>

    </form>

</div>
</div>

<?php include BASE_PATH . '/templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<script>
// Make BASE_URL available to JavaScript
window.BASE_URL = '<?= BASE_URL ?>';

document.querySelectorAll('label').forEach(label => {
    label.addEventListener('click', () => {
        document.querySelectorAll('[data-theme-card]').forEach(card =>
            card.classList.remove('border', 'border-primary')
        );
        const card = label.querySelector('[data-theme-card]');
        if (card) card.classList.add('border', 'border-primary');
    });
});
</script>

</body>
</html>
