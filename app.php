<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$appSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$themeColor = $appSettings['app_theme_color'] ?? '#0b2239';
$activeModules = explode(',', $appSettings['app_active_modules'] ?? 'rides,mart,doctors,bills,services,tourism,properties,jobs');
$showPartnerSwitch = ($appSettings['app_show_partner_switch'] ?? '1') === '1';

$user = Auth::user();
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

// Active Ride Check
$activeRide = null;
if (Auth::check()) {
    $rStmt = $pdo->prepare('SELECT * FROM rides WHERE customer_id = :cid AND status IN ("requested", "accepted", "in_trip") ORDER BY id DESC LIMIT 1');
    $rStmt->execute([':cid' => $user['id']]);
    $activeRide = $rStmt->fetch();
}

// Fetch Dynamic App Banners & Sponsored Ads
$banners = $pdo->query('SELECT * FROM app_banners WHERE status = "active" ORDER BY sort_order ASC, id DESC LIMIT 6')->fetchAll();

// Vendor Profile (If Partner)
$vendorProfile = null;
if (Auth::check()) {
    $vStmt = $pdo->prepare('SELECT * FROM vendor_profiles WHERE user_id = :uid LIMIT 1');
    $vStmt->execute([':uid' => $user['id']]);
    $vendorProfile = $vStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($appSettings['site_name'] ?? 'BengalSeva') ?> Super App</title>
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: system-ui, -apple-system, sans-serif;
            padding-bottom: 75px;
            user-select: none;
        }
        .super-app-header {
            background: <?= e($themeColor) ?>;
            color: #ffffff;
            padding: 14px 16px;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.18);
        }
        .app-category-bubble {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .app-icon-circle {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
            margin-bottom: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            transition: transform 0.15s ease;
        }
        .app-icon-circle:active { transform: scale(0.92); }
        .app-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.06);
            z-index: 1040;
            padding: 6px 10px;
        }
        .bottom-nav-item {
            text-decoration: none;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .bottom-nav-item.active { color: #0d6efd; font-weight: 700; }
        .bottom-nav-item i { font-size: 1.25rem; margin-bottom: 1px; }
        .app-banner-slider {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-bottom: 4px;
        }
        .app-banner-slider::-webkit-scrollbar { display: none; }
        .app-banner-card {
            flex: 0 0 88%;
            scroll-snap-align: start;
            border-radius: 18px;
            padding: 18px;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        }
    </style>
</head>
<body>

<!-- 1. Super App Header (With Live Auto GPS Location) -->
<div class="super-app-header">
    <div class="d-flex justify-content-between align-items-center">
        <!-- Live GPS Location -->
        <div class="d-flex align-items-center gap-2" onclick="detectUserLocation(true)" style="cursor: pointer;">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                <i class="bi bi-geo-alt-fill text-warning"></i>
            </div>
            <div>
                <small class="text-white-50 d-block" style="font-size: 0.7rem; line-height:1;">LIVE LOCATION</small>
                <span id="currentLocationLabel" class="text-white small fw-bold text-truncate d-block" style="max-width: 170px;">
                    📍 Locating...
                </span>
            </div>
        </div>

        <!-- Right Actions: Partner Switcher / Cart / Profile -->
        <div class="d-flex align-items-center gap-2">
            <?php if ($showPartnerSwitch): ?>
                <?php if ($vendorProfile): ?>
                    <button class="btn btn-sm btn-warning fw-bold py-1 px-2 rounded-pill small text-dark d-flex align-items-center gap-1 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#partnerDeskDrawer">
                        <i class="bi bi-briefcase-fill"></i> <span style="font-size:0.75rem;">Partner Desk</span>
                    </button>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/register-partner.php" class="btn btn-sm btn-outline-light fw-bold py-1 px-2 rounded-pill small" style="font-size:0.72rem;">
                        Partner
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?= APP_URL ?>/shop.php" class="text-white position-relative fs-5 text-decoration-none px-1">
                <i class="bi bi-cart3"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                        <?= $cartCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php if (Auth::check()): ?>
                <a href="<?= APP_URL ?>/portal/customer/dashboard.php" class="text-white fs-5 text-decoration-none">
                    <i class="bi bi-person-circle"></i>
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login.php" class="btn btn-sm btn-light fw-bold py-1 px-3 rounded-pill text-dark" style="font-size:0.75rem;">
                    Login
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clean In-App Search Bar (NO RIDE BUTTON INSIDE) -->
    <div class="mt-3">
        <form action="<?= APP_URL ?>/category.php" method="GET">
            <div class="input-group bg-white rounded-pill p-1 shadow-sm">
                <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-0 bg-transparent py-1 small" placeholder="Search services, doctors, groceries, bike taxis..." required>
                <button type="submit" class="btn btn-primary rounded-pill px-3 py-1 fw-bold small">
                    Search
                </button>
            </div>
        </form>
    </div>
</div>

<div class="container py-3">
    <!-- Partner Workspace Drawer (If Partner) -->
    <?php if ($vendorProfile): ?>
        <div class="collapse mb-3" id="partnerDeskDrawer">
            <div class="card border-0 shadow-lg rounded-4 p-3 bg-dark text-white">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">
                    <div>
                        <span class="badge bg-success small">PARTNER DESK</span>
                        <h6 class="fw-bold mb-0 text-white mt-1"><?= e($vendorProfile['business_name']) ?></h6>
                    </div>
                    <a href="<?= APP_URL ?>/vendor-app.php" class="btn btn-sm btn-light fw-bold py-1 px-3 rounded-pill">
                        Open Full Desk &rarr;
                    </a>
                </div>
                <div class="row g-2 text-center small">
                    <div class="col-4">
                        <a href="<?= APP_URL ?>/portal/vendor/dashboard.php" class="text-white text-decoration-none p-2 bg-secondary bg-opacity-25 rounded-3 d-block">
                            <i class="bi bi-inbox-fill text-warning fs-5 d-block"></i> Leads
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="<?= APP_URL ?>/portal/vendor/orders.php" class="text-white text-decoration-none p-2 bg-secondary bg-opacity-25 rounded-3 d-block">
                            <i class="bi bi-box-seam text-info fs-5 d-block"></i> Orders
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="<?= APP_URL ?>/portal/vendor/wallet.php" class="text-white text-decoration-none p-2 bg-secondary bg-opacity-25 rounded-3 d-block">
                            <i class="bi bi-wallet2 text-success fs-5 d-block"></i> Wallet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Live Ride Tracking Card -->
    <?php if ($activeRide): ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3 border-start border-warning border-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge bg-warning text-dark text-uppercase"><i class="bi bi-broadcast me-1"></i> <?= e($activeRide['status']) ?></span>
                <span class="fw-bold text-primary">₹<?= number_format((float)$activeRide['estimated_fare'], 0) ?></span>
            </div>
            <div class="small text-dark fw-bold text-truncate"><?= e($activeRide['pickup_address']) ?> &rarr; <?= e($activeRide['drop_address']) ?></div>
            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                <small class="text-muted">OTP: <strong><?= e($activeRide['otp_code']) ?></strong></small>
                <a href="<?= APP_URL ?>/ride.php" class="btn btn-sm btn-primary py-0 px-2 fw-bold">Live Screen &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. Dynamic Promotional Banner Sliders (Live From Admin) -->
    <?php if (!empty($banners)): ?>
        <div class="app-banner-slider mb-4">
            <?php foreach ($banners as $b): ?>
                <div class="app-banner-card" style="background: <?= e($b['bg_gradient']) ?>;">
                    <span class="badge bg-white text-dark fw-bold px-2 py-1 mb-1" style="font-size:0.65rem;"><?= e($b['badge_text']) ?></span>
                    <h5 class="fw-bold mb-1 text-white"><?= e($b['title']) ?></h5>
                    <p class="small text-white-50 mb-2"><?= e($b['subtitle']) ?></p>
                    <a href="<?= APP_URL . e($b['target_url']) ?>" class="btn btn-light btn-sm fw-bold px-3 py-1 rounded-pill text-dark" style="font-size:0.75rem;">
                        Claim Offer &rarr;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 3. Dynamic Service Bubbles (Live From Admin Feature Toggles) -->
    <h6 class="fw-bold text-dark mb-3 px-1">All Services on BengalSeva</h6>
    <div class="row g-3 mb-4">
        <?php if (in_array('rides', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/ride.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-warning bg-opacity-25 text-warning">
                        <i class="bi bi-scooter text-dark"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Bike Taxi</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('mart', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/shop.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-success bg-opacity-25 text-success">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Mart 45-Min</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('doctors', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/book.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-danger bg-opacity-25 text-danger">
                        <i class="bi bi-hospital"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Doctors</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('bills', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/recharge.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-primary bg-opacity-25 text-primary">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Recharge/Bills</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('services', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/category.php?slug=home-services" class="app-category-bubble">
                    <div class="app-icon-circle bg-info bg-opacity-25 text-info">
                        <i class="bi bi-tools text-primary"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Home Care</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('tourism', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/travel.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-success bg-opacity-25 text-success">
                        <i class="bi bi-compass"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Homestays</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('properties', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/properties.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-warning bg-opacity-25 text-dark">
                        <i class="bi bi-building"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Rentals</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if (in_array('jobs', $activeModules, true)): ?>
            <div class="col-3">
                <a href="<?= APP_URL ?>/jobs.php" class="app-category-bubble">
                    <div class="app-icon-circle bg-secondary bg-opacity-25 text-dark">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <small class="fw-bold text-dark" style="font-size:0.72rem;">Jobs</small>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Transit Booking -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4 d-flex flex-row justify-content-between align-items-center">
        <div>
            <span class="badge bg-primary mb-1">TRAVEL TICKETS</span>
            <h6 class="fw-bold text-dark mb-0">Bus, Flight &amp; Train Tickets</h6>
            <small class="text-muted">SBSTC Volvo, IndiGo &amp; Vande Bharat</small>
        </div>
        <a href="<?= APP_URL ?>/tickets.php" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
            Book Tickets &rarr;
        </a>
    </div>
</div>

<!-- 4. Universal Single-App Bottom Navigation Bar -->
<div class="app-bottom-nav">
    <div class="row g-0 text-center">
        <div class="col">
            <a href="<?= APP_URL ?>/app.php" class="bottom-nav-item active">
                <i class="bi bi-house-door-fill"></i>
                <span>Explore</span>
            </a>
        </div>
        <div class="col">
            <a href="<?= APP_URL ?>/category.php?slug=home-services" class="bottom-nav-item">
                <i class="bi bi-grid-fill"></i>
                <span>Services</span>
            </a>
        </div>
        <div class="col">
            <a href="<?= APP_URL ?>/ride.php" class="bottom-nav-item">
                <i class="bi bi-scooter text-warning"></i>
                <span>Rides</span>
            </a>
        </div>
        <div class="col">
            <a href="<?= APP_URL ?>/shop.php" class="bottom-nav-item">
                <i class="bi bi-bag-fill text-danger"></i>
                <span>Mart</span>
            </a>
        </div>
        <div class="col">
            <a href="<?= Auth::check() ? APP_URL . '/portal/dashboard.php' : APP_URL . '/login.php' ?>" class="bottom-nav-item">
                <i class="bi bi-person-fill text-primary"></i>
                <span><?= Auth::check() ? 'Account' : 'Login' ?></span>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>