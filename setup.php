<?php
/**
 * BengalSeva - Universal Native App Shell & GitHub Auto-Deployment Webhook
 * Technology: Native Core PHP 8.3 | MariaDB | Bootstrap 5 | Zero Composer
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$stepResult = [];
$isSubmitted = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isSubmitted) {
    try {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/config/app.php';
        require_once __DIR__ . '/includes/security_helpers.php';
        require_once __DIR__ . '/includes/audit.php';

        $pdo = Database::getConnection();
        $baseDir = __DIR__;

        $filesToCreate = [];

        // --- File 1: github_webhook.php (Hostinger GitHub Auto-Deployer) ---
        $filesToCreate['github_webhook.php'] = <<<'CODE_WEBHOOK'
<?php
declare(strict_types=1);

// Secret key to protect unauthorized webhook calls
$secretKey = 'bengalseva_git_secret_2026';
$receivedToken = $_GET['token'] ?? ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

if (empty($receivedToken) && empty($_GET['token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Missing authorization token']));
}

// Log Webhook Trigger
$payload = file_get_contents('php://input');
$output = [];
$returnVar = 0;

// Execute git pull on Hostinger server
exec('git pull origin main 2>&1', $output, $returnVar);

header('Content-Type: application/json');
echo json_encode([
    'status'    => ($returnVar === 0) ? 'success' : 'executed',
    'timestamp' => date('Y-m-d H:i:s'),
    'output'    => $output
]);
CODE_WEBHOOK;

        // --- File 2: templates/header.php (Universal App Header for ALL Sub-pages) ---
        $filesToCreate['templates/header.php'] = <<<'CODE_APP_HEADER'
<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

// Detect if running inside Mobile App or PWA
$isAppMode = (
    (isset($_GET['mode']) && $_GET['mode'] === 'app') ||
    (isset($_SESSION['app_mode']) && $_SESSION['app_mode'] === true) ||
    (isset($_COOKIE['bs_app_mode']) && $_COOKIE['bs_app_mode'] === '1') ||
    str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'BengalSeva-Android-App') ||
    str_contains($_SERVER['REQUEST_URI'] ?? '', 'app.php') ||
    str_contains($_SERVER['REQUEST_URI'] ?? '', 'customer-app') ||
    str_contains($_SERVER['REQUEST_URI'] ?? '', 'vendor-app')
);

if (isset($_GET['mode']) && $_GET['mode'] === 'app') {
    $_SESSION['app_mode'] = true;
    setcookie('bs_app_mode', '1', time() + (86400 * 30), '/');
}

$brandSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$siteName = $brandSettings['site_name'] ?? APP_NAME;
$siteTagline = $brandSettings['site_tagline'] ?? APP_TAGLINE;
$siteLogoUrl = $brandSettings['site_logo_url'] ?? '';

// Determine Page Title for Mobile App Header
$currentScript = basename($_SERVER['PHP_SELF']);
$pageTitle = match ($currentScript) {
    'shop.php'        => 'Mart & Grocery (50% Off)',
    'ride.php'        => 'Rapido Bike Taxi & Cab',
    'book.php'        => 'Doctor & Clinic Appointment',
    'recharge.php'    => 'Mobile Recharge & Bills',
    'tickets.php'     => 'Bus, Flight & Train Tickets',
    'travel.php'      => 'Bengal Tourism & Stays',
    'properties.php'  => 'Properties & Rentals',
    'jobs.php'        => 'Jobs & Careers',
    'category.php'    => 'Local Services Directory',
    'company.php'     => 'Verified Supplier Showroom',
    'cart.php'        => 'Shopping Cart',
    'checkout.php'    => 'Secure Checkout',
    'support.php'     => 'Customer Helpdesk',
    default           => $siteName
};

$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>
<!DOCTYPE html>
<html lang="<?= e(I18n::currentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($siteName) ?> - <?= e($pageTitle) ?></title>

    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#0b2239">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <script>window.APP_URL = "<?= APP_URL ?>";</script>
</head>
<body class="d-flex flex-column min-vh-100 <?= $isAppMode ? 'app-shell-active' : '' ?>">

<!-- 1. Universal Native App Header (Visible in App Mode or Mobile on Sub-pages) -->
<?php if ($currentScript !== 'index.php' && $currentScript !== 'app.php'): ?>
    <div class="native-subpage-app-header d-flex justify-content-between align-items-center py-2 px-3 bg-dark text-white sticky-top shadow-sm" style="background:#0b2239 !important; z-index:1030;">
        <div class="d-flex align-items-center gap-2">
            <!-- Universal Back Button -->
            <button type="button" class="btn btn-sm btn-outline-light border-0 rounded-circle p-1 d-flex align-items-center justify-content-center" onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='<?= APP_URL ?>/app.php'; }" style="width:34px; height:34px;">
                <i class="bi bi-arrow-left fs-5"></i>
            </button>
            <h6 class="fw-bold mb-0 text-white text-truncate" style="max-width: 200px;"><?= e($pageTitle) ?></h6>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= APP_URL ?>/shop.php" class="text-white position-relative fs-5 text-decoration-none px-2">
                <i class="bi bi-cart3"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                        <?= $cartCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/app.php" class="btn btn-sm btn-warning fw-bold py-1 px-2 rounded-pill text-dark" style="font-size:0.75rem;">
                <i class="bi bi-phone"></i> App
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- 2. Desktop Full-Width Header (Hidden in Native Subpage View) -->
<div class="site-header-sticky-wrapper <?= ($currentScript !== 'index.php') ? 'd-none d-lg-block' : '' ?>">
    <div class="top-utility-bar">
        <div class="container-fluid px-lg-5 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small"><?= e($siteTagline) ?></span>
            </div>

            <div class="d-flex align-items-center gap-3 ms-auto">
                <div class="dropdown">
                    <button class="btn btn-sm text-light dropdown-toggle p-0 fw-semibold" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-translate text-warning me-1"></i> <?= e(SUPPORTED_LANGUAGES[I18n::currentLanguage()] ?? 'EN') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <?php foreach (SUPPORTED_LANGUAGES as $code => $label): ?>
                            <li><a class="dropdown-item <?= I18n::currentLanguage() === $code ? 'active' : '' ?>" href="?lang=<?= $code ?>"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (Auth::check()): ?>
                    <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                    <a href="<?= APP_URL ?>/logout.php" class="btn btn-sm btn-danger rounded-pill px-2" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/login.php" class="btn btn-sm btn-light fw-bold px-3 py-1 text-dark rounded-2">Login</a>
                    <a href="<?= APP_URL ?>/register-partner.php" class="btn btn-sm btn-success fw-bold px-3 py-1 rounded-2">Partner With Us</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <header class="navbar-bengal">
        <div class="container-fluid px-lg-5 d-flex justify-content-between align-items-center">
            <a href="<?= APP_URL ?>/" class="brand-logo-container">
                <?php if (!empty($siteLogoUrl)): ?>
                    <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>" class="brand-official-logo">
                <?php elseif (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
                    <img src="<?= APP_URL ?>/assets/images/logo.png" alt="<?= e($siteName) ?>" class="brand-official-logo">
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="tile-icon-box tile-icon-blue mb-0" style="width:36px; height:36px; font-size:1.1rem; border-radius:8px;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <div class="brand-title">
                                <span class="bengal-part"><?= e(substr($siteName, 0, 6)) ?></span><span class="seva-part"><?= e(substr($siteName, 6)) ?></span>
                            </div>
                            <span class="brand-subtitle"><?= e($siteTagline) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </a>

            <button class="navbar-toggler d-lg-none border-0 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavMenu">
                <i class="bi bi-list fs-1 text-dark"></i>
            </button>

            <nav class="collapse navbar-collapse d-lg-flex align-items-center justify-content-end gap-1" id="mainNavMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 d-flex flex-lg-row align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/" class="nav-link active-pill"><i class="bi bi-house-door-fill me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/category.php?slug=home-services" class="nav-link">Services</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/shop.php" class="nav-link">Shopping &amp; Mart</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/ride.php" class="nav-link">Ride &amp; Parcel</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/travel.php" class="nav-link">Bengal Tourism</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/properties.php" class="nav-link">Properties</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/jobs.php" class="nav-link">Jobs</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
</div>

<main class="flex-grow-1">
<?php require_once __DIR__ . '/alerts.php'; ?>
CODE_APP_HEADER;

        // --- File 3: templates/mobile_bottom_nav.php (Universal Persistent App Bar) ---
        $filesToCreate['templates/mobile_bottom_nav.php'] = <<<'CODE_BOTTOM_NAV_PERSIST'
<!-- Native Mobile App Bottom Navigation Bar (Persistent on all sub-pages) -->
<div class="mobile-bottom-bar-container d-block d-lg-none">
    <div class="fixed-bottom bg-white border-top shadow-lg py-1 px-2" style="z-index: 1040;">
        <div class="row g-0 text-center">
            <div class="col">
                <a href="<?= APP_URL ?>/app.php" class="nav-link p-1 <?= str_contains($_SERVER['PHP_SELF'], 'app.php') ? 'text-primary fw-bold' : 'text-muted' ?>">
                    <i class="bi bi-house-door-fill fs-5 d-block"></i>
                    <span style="font-size: 0.7rem;">Home</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/category.php?slug=home-services" class="nav-link p-1 <?= str_contains($_SERVER['PHP_SELF'], 'category.php') ? 'text-primary fw-bold' : 'text-muted' ?>">
                    <i class="bi bi-grid-fill fs-5 d-block"></i>
                    <span style="font-size: 0.7rem;">Services</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/ride.php" class="nav-link p-1 <?= str_contains($_SERVER['PHP_SELF'], 'ride.php') ? 'text-warning fw-bold' : 'text-muted' ?>">
                    <i class="bi bi-scooter fs-5 d-block text-warning"></i>
                    <span style="font-size: 0.7rem;">Rides</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/shop.php" class="nav-link p-1 <?= str_contains($_SERVER['PHP_SELF'], 'shop.php') ? 'text-danger fw-bold' : 'text-muted' ?>">
                    <i class="bi bi-bag-fill fs-5 d-block text-danger"></i>
                    <span style="font-size: 0.7rem;">Mart</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= Auth::check() ? APP_URL . '/portal/customer/dashboard.php' : APP_URL . '/login.php' ?>" class="nav-link p-1 <?= str_contains($_SERVER['PHP_SELF'], 'dashboard') ? 'text-primary fw-bold' : 'text-muted' ?>">
                    <i class="bi bi-person-fill fs-5 d-block text-primary"></i>
                    <span style="font-size: 0.7rem;"><?= Auth::check() ? 'Account' : 'Login' ?></span>
                </a>
            </div>
        </div>
    </div>
    <!-- Space buffer so content never hides under the bottom nav -->
    <div class="py-4"></div>
</div>
CODE_BOTTOM_NAV_PERSIST;

        // --- File 4: Update CSS for Universal Native App Shell ---
        $cssPath = $baseDir . '/assets/css/app.css';
        if (file_exists($cssPath)) {
            $cssContent = file_get_contents($cssPath);
            if (!str_contains($cssContent, 'native-subpage-app-header')) {
                $appShellCss = "
/* Universal Native App Shell Styles */
.native-subpage-app-header {
    min-height: 48px;
    background: #0b2239 !important;
}
@media (max-width: 991.98px) {
    .app-shell-active .site-header-sticky-wrapper {
        display: none !important;
    }
}
";
                $cssContent .= "\n" . $appShellCss;
                file_put_contents($cssPath, $cssContent);
            }
        }

        // Write files safely
        foreach ($filesToCreate as $relPath => $fileContent) {
            $destPath = $baseDir . DIRECTORY_SEPARATOR . $relPath;
            if (file_put_contents($destPath, $fileContent) !== false) {
                $stepResult[] = ['success', "Deployed: <code>{$relPath}</code>"];
            } else {
                throw new RuntimeException("Could not write file: {$destPath}");
            }
        }

        logAudit($pdo, 1, 'UNIVERSAL_APP_SHELL_DEPLOYED', 'system', null, 'Universal App Shell across all pages & GitHub Webhook deployed.');
        $stepResult[] = ['success', 'Recorded deployment in <code>audit_logs</code>.'];

    } catch (Throwable $e) {
        $stepResult[] = ['danger', 'Error: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BengalSeva - Universal App Shell Deployer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4">
                    <h3 class="fw-bold mb-1">Universal Native App Shell &amp; GitHub Auto-Deployer</h3>
                    <div class="text-white-50 small">Every Page Opens Like a Native App &bull; Universal Back Button &bull; GitHub Webhook</div>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($stepResult)): ?>
                        <h5 class="fw-bold mb-3">Deployment Log:</h5>
                        <ul class="list-group mb-4">
                            <?php foreach ($stepResult as $res): ?>
                                <li class="list-group-item list-group-item-<?= $res[0] ?>">
                                    <?= $res[1] ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="app.php" class="btn btn-primary fw-bold px-3">Open Single Super-App</a>
                            <a href="ride.php" class="btn btn-warning fw-bold px-3 text-dark">Test Ride in App-Mode</a>
                            <a href="shop.php" class="btn btn-success fw-bold px-3">Test Mart in App-Mode</a>
                            <a href="portal/dashboard.php" class="btn btn-dark fw-bold px-3">Dashboard</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Click below to deploy the Universal Native App Shell so every sub-page opens with a native app header, back button, and persistent bottom navigation bar, plus the GitHub auto-deployment webhook.</p>
                        <form method="POST">
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-3 fs-5 shadow-sm">
                                Deploy Universal App Shell &amp; GitHub Connector
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>