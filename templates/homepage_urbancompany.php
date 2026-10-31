<?php
// Urban Company Style Doorstep Services Template
$servicesStmt = $pdo->query('
    SELECT si.*, vp.business_name, vp.city, c.name as category_name 
    FROM service_items si 
    JOIN vendor_profiles vp ON si.vendor_id = vp.id 
    LEFT JOIN categories c ON si.category_id = c.id 
    WHERE si.status = "active" 
    ORDER BY si.id ASC LIMIT 6
');
$ucServices = $servicesStmt->fetchAll();
?>
<!-- Urban Company Mobile Hero -->
<div class="bg-white py-4 border-bottom">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-bold mb-2">
                    <i class="bi bi-patch-check-fill text-primary me-1"></i> BengalSeva Verified Doorstep Experts
                </span>
                <h1 class="display-6 fw-bold text-dark mb-2">Quality Home Services, On Demand</h1>
                <p class="text-secondary small mb-3">Trained technicians, transparent pricing &amp; 30-day service warranty across West Bengal</p>
                
                <!-- Search Box -->
                <form class="p-2 border rounded-pill shadow-sm bg-white d-flex align-items-center gap-2" method="GET" action="<?= APP_URL ?>/category.php">
                    <i class="bi bi-search text-muted ms-3 fs-5"></i>
                    <input type="text" name="q" class="form-control border-0 bg-transparent py-2" placeholder="Search AC service, electrician, salon, cleaning...">
                    <button type="submit" class="btn btn-dark fw-bold rounded-pill px-4 py-2">Search</button>
                </form>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=600&q=80" class="img-fluid rounded-4 shadow" style="max-height: 280px; object-fit: cover;">
            </div>
        </div>
    </div>
</div>

<!-- Urban Company 8 App Icon Bubbles -->
<div class="container py-4">
    <h5 class="fw-bold text-dark mb-3">What are you looking for?</h5>
    <div class="row g-3 text-center">
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=home-services" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #e0f2fe !important;">
                    <i class="bi bi-tools text-primary fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Repairs &amp; AC</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=beauty-salon" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #fce7f3 !important;">
                    <i class="bi bi-scissors text-danger fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Salon for Women</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=electrician" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #fef3c7 !important;">
                    <i class="bi bi-lightning-charge-fill text-warning fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Electrician</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=plumber" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #dcfce7 !important;">
                    <i class="bi bi-droplet-fill text-success fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Plumber</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/ride.php" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #ffedd5 !important;">
                    <i class="bi bi-scooter text-warning fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Bike &amp; Cab</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/shop.php" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #ede9fe !important;">
                    <i class="bi bi-cart3 text-primary fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Mart 45-Min</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/book.php" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #ccfbf1 !important;">
                    <i class="bi bi-hospital-fill text-info fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Doctor Clinic</small>
            </a>
        </div>
        <div class="col-3 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=packers-movers" class="text-decoration-none">
                <div class="p-3 rounded-4 bg-light shadow-sm mb-2 d-flex align-items-center justify-content-center mx-auto" style="width:65px; height:65px; background: #fee2e2 !important;">
                    <i class="bi bi-truck text-danger fs-3"></i>
                </div>
                <small class="fw-bold text-dark d-block" style="font-size:0.75rem;">Packers</small>
            </a>
        </div>
    </div>
</div>

<!-- Most Booked Services Cards with Transparent Pricing -->
<div class="container py-3 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Most Booked Services</h5>
        <a href="<?= APP_URL ?>/category.php?slug=home-services" class="text-primary fw-bold text-decoration-none small">See all &rarr;</a>
    </div>

    <div class="row g-3">
        <?php foreach ($ucServices as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border rounded-4 p-3 bg-white shadow-sm d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><?= e($s['title']) ?></h6>
                                <small class="text-muted"><i class="bi bi-star-fill text-warning"></i> 4.8 &bull; <?= (int)$s['duration_minutes'] ?> mins</small>
                            </div>
                            <span class="fs-5 fw-bold text-dark">₹<?= number_format((float)$s['price'], 0) ?></span>
                        </div>
                        <p class="text-secondary small mb-3"><?= e($s['description'] ?? 'Verified doorstep service with certified technician.') ?></p>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <small class="text-muted"><i class="bi bi-shield-check text-success me-1"></i> 30-Day Guarantee</small>
                        <a href="<?= APP_URL ?>/book.php?service_id=<?= (int)$s['id'] ?>&vendor_id=<?= (int)$s['vendor_id'] ?>" class="btn btn-outline-dark btn-sm fw-bold px-3 rounded-pill">
                            Book Now
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>