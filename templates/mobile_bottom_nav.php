<!-- Native Mobile App Bottom Navigation Bar (STRICTLY HIDDEN ON DESKTOP) -->
<div class="mobile-bottom-bar-container d-block d-lg-none">
    <div class="fixed-bottom bg-white border-top shadow-lg py-1 px-2" style="z-index: 1030;">
        <div class="row g-0 text-center">
            <div class="col">
                <a href="<?= APP_URL ?>/" class="nav-link p-1 text-primary fw-bold">
                    <i class="bi bi-house-door-fill fs-5 d-block"></i>
                    <span style="font-size: 0.7rem;">Home</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/category.php?slug=home-services" class="nav-link p-1 text-muted">
                    <i class="bi bi-grid-fill fs-5 d-block"></i>
                    <span style="font-size: 0.7rem;">Services</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/ride.php" class="nav-link p-1 text-muted">
                    <i class="bi bi-scooter fs-5 d-block text-warning"></i>
                    <span style="font-size: 0.7rem;">Rides</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= APP_URL ?>/shop.php" class="nav-link p-1 text-muted">
                    <i class="bi bi-bag-fill fs-5 d-block text-danger"></i>
                    <span style="font-size: 0.7rem;">Mart</span>
                </a>
            </div>
            <div class="col">
                <a href="<?= Auth::check() ? APP_URL . '/portal/dashboard.php' : APP_URL . '/login.php' ?>" class="nav-link p-1 text-muted">
                    <i class="bi bi-person-fill fs-5 d-block text-primary"></i>
                    <span style="font-size: 0.7rem;"><?= Auth::check() ? 'Account' : 'Login' ?></span>
                </a>
            </div>
        </div>
    </div>
    <!-- Space buffer for mobile bottom nav -->
    <div class="py-4"></div>
</div>