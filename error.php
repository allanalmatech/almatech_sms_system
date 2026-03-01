<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$errorCode = $_GET['code'] ?? '404';
$errorMessages = [
    '403' => [
        'title' => 'Access Forbidden',
        'message' => 'You do not have permission to access this resource.',
        'description' => 'Please contact the administrator if you believe this is an error.'
    ],
    '404' => [
        'title' => 'Page Not Found',
        'message' => 'The page you are looking for could not be found.',
        'description' => 'Please check the URL and try again.'
    ],
    '500' => [
        'title' => 'Server Error',
        'message' => 'Something went wrong on our end.',
        'description' => 'Please try again later or contact the administrator.'
    ]
];

$error = $errorMessages[$errorCode] ?? $errorMessages['404'];
$pageTitle = $error['title'] . ' - Error';
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
</head>
<body>
    <?php include __DIR__ . '/templates/sidebar.php'; ?>

    <div class="app-shell">
        <div class="container-fluid p-4">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        </div>
                        
                        <h1 class="h2 mb-3"><?= htmlspecialchars($error['title']) ?></h1>
                        <p class="lead text-muted mb-4"><?= htmlspecialchars($error['message']) ?></p>
                        <p class="text-muted mb-4"><?= htmlspecialchars($error['description']) ?></p>
                        
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i> Dashboard
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i> Go Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
</body>
</html>
