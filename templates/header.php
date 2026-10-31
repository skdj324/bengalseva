<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$brandSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$siteName = $brandSettings['site_name'] ?? APP_NAME;
$siteTagline = $brandSettings['site_tagline'] ?? APP_TAGLINE;
$siteLogoUrl = $brandSettings['site_logo_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(I18n::currentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> - Justdial Style Local Search Engine</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <script>window.APP_URL = "<?= APP_URL ?>";</script>
    <link rel="manifest" href="https://searchkaro.midexa.in/manifest.json">
<meta name="theme-color" content="#0b2239">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Super Admin Simulation Notice Bar -->
<?php if (!empty($_SESSION['original_super_admin_id'])): ?>
    <div class="bg-warning text-dark py-1 px-3 d-flex justify-content-between align-items-center small fw-bold" id="simulating_role_banner">
        <span><i class="bi bi-eye-fill me-1"></i> SIMULATION MODE: Active Role <?= strtoupper(e($_SESSION['user_role'])) ?></span>
        <a href="<?= APP_URL ?>/portal/admin/role_switch.php?action=revert" class="btn btn-sm btn-dark py-0 px-2 fw-bold">
            <i class="bi bi-box-arrow-left me-1"></i> Return to Super Admin
        </a>
    </div>
<?php endif; ?>

<!-- Top Announcement Marquee (If Enabled) -->
<?php if (($brandSettings['top_announcement_enabled'] ?? '1') === '1' && !empty($brandSettings['top_announcement_text'])): ?>
    <div class="py-1 px-3 text-white text-center small fw-semibold" style="background: linear-gradient(90deg, #ea580c 0%, #0284c7 100%);">
        <i class="bi bi-megaphone-fill me-1"></i> <?= e($brandSettings['top_announcement_text']) ?>
    </div>
<?php endif; ?>

<!-- JUSTDIAL-STYLE EXPANSIVE DESKTOP HEADER -->
<div class="site-header-sticky-wrapper">
    <header class="navbar-bengal bg-white py-2 border-bottom">
        <div class="container-fluid px-lg-5 d-flex justify-content-between align-items-center">
            
            <!-- Left: Brand Logo & Tagline -->
            <a href="<?= APP_URL ?>/" class="brand-logo-container">
                <?php if (!empty($siteLogoUrl)): ?>
                    <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>" class="brand-official-logo">
                <?php elseif (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
                    <img src="<?= APP_URL ?>/assets/images/logo.png" alt="<?= e($siteName) ?>" class="brand-official-logo">
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="tile-icon-box tile-icon-blue mb-0" style="width:36px; height:36px; font-size:1.1rem; border-radius:8px;">
                            <i class="bi bi-search"></i>
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

            <!-- Right: Justdial Actions (We are Hiring, Free Listing, Language, Login) -->
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <!-- Free Listing Button (Justdial Style) -->
                <a href="<?= APP_URL ?>/register-partner.php" class="btn btn-outline-primary btn-sm fw-bold px-3 rounded-pill d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle-fill text-primary"></i> Free Listing
                </a>

                <!-- B2B / Shop Direct Link -->
                <a href="<?= APP_URL ?>/shop.php" class="btn btn-light btn-sm fw-bold text-dark border rounded-pill px-3 d-none d-md-inline-flex align-items-center gap-1">
                    <i class="bi bi-shop text-danger"></i> B2B &amp; Mart
                </a>

                <!-- Multilingual Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle px-2 py-1 fw-semibold" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-translate text-primary me-1"></i> <?= e(SUPPORTED_LANGUAGES[I18n::currentLanguage()] ?? 'EN') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <?php foreach (SUPPORTED_LANGUAGES as $code => $label): ?>
                            <li><a class="dropdown-item <?= I18n::currentLanguage() === $code ? 'active' : '' ?>" href="?lang=<?= $code ?>"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- User Auth -->
                <?php if (Auth::check()): ?>
                    <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                    <a href="<?= APP_URL ?>/logout.php" class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Logout" style="width:32px; height:32px;">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/login.php" class="btn btn-sm btn-primary fw-bold px-4 py-1 rounded-pill">
                        Login / Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
</div>

<main class="flex-grow-1">
<?php require_once __DIR__ . '/alerts.php'; ?>