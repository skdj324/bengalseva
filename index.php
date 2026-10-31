<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Handle Lead / Callback Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_lead') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        setFlash('danger', 'Security verification token invalid. Please try again.');
        header('Location: ' . APP_URL . '/');
        exit;
    }

    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
    $serviceName = sanitizeInput($_POST['service_name'] ?? 'General Service');
    $rawRequirement = sanitizeInput($_POST['requirement'] ?? '');
    $requirement = "[Service: {$serviceName}] " . $rawRequirement;

    if (!empty($fullName) && !empty($mobile) && !empty($rawRequirement)) {
        try {
            $leadStmt = $pdo->prepare('
                INSERT INTO leads (customer_id, category_id, vendor_id, full_name, mobile, requirement, district_id, status, created_at)
                VALUES (:customer_id, NULL, NULL, :full_name, :mobile, :requirement, :district_id, "new", NOW())
            ');
            $customerId = Auth::check() ? (int)Auth::user()['id'] : null;

            $leadStmt->execute([
                ':customer_id'  => $customerId,
                ':full_name'    => $fullName,
                ':mobile'       => $mobile,
                ':requirement'  => $requirement,
                ':district_id'  => $districtId
            ]);

            $leadId = (int)$pdo->lastInsertId();
            logAudit($pdo, $customerId, 'LEAD_SUBMITTED', 'leads', $leadId, "New lead created for {$serviceName}");
            setFlash('success', 'Your requirement has been submitted! Local verified partners will contact you shortly.');
        } catch (PDOException $e) {
            setFlash('danger', 'An error occurred while saving your request.');
        }
    } else {
        setFlash('warning', 'Please fill in all required fields.');
    }

    header('Location: ' . APP_URL . '/');
    exit;
}

$distStmt = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC');
$districts = $distStmt->fetchAll();

// Fetch Active Template from Settings
$tplStmt = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = "homepage_template" LIMIT 1');
$activeTemplate = $tplStmt->fetchColumn() ?: 'template_default';

require_once __DIR__ . '/templates/header.php';

// Route to selected design
if ($activeTemplate === 'template_urbancompany') {
    require_once __DIR__ . '/templates/homepage_urbancompany.php';
} elseif ($activeTemplate === 'template_justdial') {
    require_once __DIR__ . '/templates/homepage_justdial.php';
} elseif ($activeTemplate === 'template_indiamart') {
    require_once __DIR__ . '/templates/homepage_indiamart.php';
} else {
    // Default Official Howrah Bridge Hero
?>
<!-- 1. Hero Section with Howrah Bridge Sunset Backdrop & Floating Search -->
<section class="hero-wrapper">
    <div class="container py-4">
        <div class="row align-items-center justify-content-between mb-4">
            <div class="col-lg-8">
                <span class="hero-pill-badge mb-3">Explore &bull; Connect &bull; Grow</span>
                <h1 class="hero-headline mb-2"><?= e($brandSettings['site_name'] ?? 'BengalSeva') ?></h1>
                <div class="text-white fw-bold mb-3" style="font-size: 1.85rem; text-shadow: 0 2px 6px rgba(0,0,0,0.5);">
                    <?= e($brandSettings['site_tagline'] ?? 'Your Local Services, All in One Place') ?>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end d-none d-lg-block">
                <div class="proud-badge">
                    <i class="bi bi-patch-check-fill text-primary fs-5"></i>
                    <span>Proudly Serving West Bengal</span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <form class="search-floating-card" method="GET" action="<?= APP_URL ?>/category.php">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center px-2">
                                <i class="bi bi-geo-alt-fill text-danger fs-5 me-2"></i>
                                <select class="form-select border-0 fw-semibold text-secondary" id="heroDistrictSelect" name="district_id">
                                    <option value="">All West Bengal</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-auto d-none d-md-block">
                            <div class="vr" style="height: 35px; color: #cbd5e1;"></div>
                        </div>
                        <div class="col-md">
                            <div class="d-flex align-items-center px-2">
                                <i class="bi bi-search text-muted fs-5 me-2"></i>
                                <input type="text" name="q" id="heroSearchInput" class="form-control border-0" placeholder="Search for Business, Services, Products...">
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-search-main w-100">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- 2. Colorful 8-Category Tile Grid -->
<section class="container py-4">
    <div class="row g-3">
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=business" class="category-card-tile">
                <div class="tile-icon-box tile-icon-blue"><i class="bi bi-shop"></i></div>
                <div class="tile-title">Business<br>Directory</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/category.php?slug=home-services" class="category-card-tile">
                <div class="tile-icon-box tile-icon-orange"><i class="bi bi-tools"></i></div>
                <div class="tile-title">Home<br>Services</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/shop.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-purple"><i class="bi bi-cart3"></i></div>
                <div class="tile-title">Ecommerce</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/shop.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-green"><i class="bi bi-hospital"></i></div>
                <div class="tile-title">Grocery &amp;<br>Medicine</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/shop.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-red"><i class="bi bi-cup-hot-fill"></i></div>
                <div class="tile-title">Food &amp;<br>Restaurant</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/ride.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-cyan"><i class="bi bi-scooter"></i></div>
                <div class="tile-title">Ride &amp;<br>Parcel</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/properties.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-emerald"><i class="bi bi-houses-fill"></i></div>
                <div class="tile-title">Property &amp;<br>Rentals</div>
            </a>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <a href="<?= APP_URL ?>/jobs.php" class="category-card-tile">
                <div class="tile-icon-box tile-icon-pink"><i class="bi bi-briefcase-fill"></i></div>
                <div class="tile-title">Education,<br>Jobs &amp; Travel</div>
            </a>
        </div>
    </div>
</section>

<!-- 3. Popular Services & Promos -->
<section class="container py-3 mb-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-star-fill text-primary"></i> Popular Services
                </h4>
                <a href="<?= APP_URL ?>/category.php?slug=home-services" class="text-primary fw-bold text-decoration-none small">View All &rarr;</a>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="service-vendor-card">
                        <div class="service-card-img-wrap">
                            <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=500&q=80">
                            <span class="verified-tag"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
                        </div>
                        <div class="service-vendor-body">
                            <div>
                                <h6 class="fw-bold mb-1 text-truncate">Apollo Gleneagles</h6>
                                <small class="text-muted d-block text-truncate">Diagnostics &amp; Polyclinic</small>
                                <div class="small mt-2"><span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> 4.9</span> <span class="text-muted">(1,420 Reviews)</span></div>
                                <div class="text-muted small mt-1 text-truncate"><i class="bi bi-geo-alt"></i> Salt Lake, Kolkata</div>
                            </div>
                            <div class="mt-3"><a href="<?= APP_URL ?>/book.php" class="btn btn-primary btn-sm w-100 fw-bold rounded-2">Book Appointment</a></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-vendor-card">
                        <div class="service-card-img-wrap">
                            <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=500&q=80">
                            <span class="verified-tag"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
                        </div>
                        <div class="service-vendor-body">
                            <div>
                                <h6 class="fw-bold mb-1 text-truncate">Sen &amp; Associates</h6>
                                <small class="text-muted d-block text-truncate">Chartered Accountants &amp; Tax</small>
                                <div class="small mt-2"><span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> 4.8</span> <span class="text-muted">(385 Reviews)</span></div>
                                <div class="text-muted small mt-1 text-truncate"><i class="bi bi-geo-alt"></i> BBD Bagh, Kolkata</div>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary btn-sm w-100 fw-bold rounded-2" onclick="openLeadModal('Request Callback', 'Sen & Associates CA', 'Enquiring about GST filing and business accounting.')">Request Callback</button></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-vendor-card">
                        <div class="service-card-img-wrap">
                            <img src="https://images.unsplash.com/photo-1509391365360-2e959784a276?auto=format&fit=crop&w=500&q=80">
                            <span class="verified-tag"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
                        </div>
                        <div class="service-vendor-body">
                            <div>
                                <h6 class="fw-bold mb-1 text-truncate">Himalayan Solar</h6>
                                <small class="text-muted d-block text-truncate">&amp; Inverter Engineering</small>
                                <div class="small mt-2"><span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> 4.7</span> <span class="text-muted">(290 Reviews)</span></div>
                                <div class="text-muted small mt-1 text-truncate"><i class="bi bi-geo-alt"></i> Siliguri</div>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary btn-sm w-100 fw-bold rounded-2" onclick="openLeadModal('Get Solar Quote', 'Himalayan Solar Engineering', 'Looking for residential rooftop solar plant installation.')">Get Quote</button></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="promo-purple-banner flex-grow-1 d-flex flex-column justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1">Get 50% OFF</h3>
                    <p class="text-white-50 small mb-3">on Your First Order</p>
                    <a href="<?= APP_URL ?>/shop.php" class="btn-yellow-cta">Shop Now &rarr;</a>
                </div>
                <div class="text-end mt-2"><i class="bi bi-cart4 text-white-50" style="font-size: 3.5rem;"></i></div>
            </div>
            <div class="promo-green-banner flex-grow-1 d-flex flex-column justify-content-between">
                <div>
                    <h4 class="fw-bold mb-1">Bike Taxi &amp; Cab</h4>
                    <p class="text-white-50 small mb-3">Starting from ₹49 across Bengal</p>
                    <a href="<?= APP_URL ?>/ride.php" class="btn-yellow-cta">Book Ride &rarr;</a>
                </div>
                <div class="text-end mt-2"><i class="bi bi-scooter text-white-50" style="font-size: 3.5rem;"></i></div>
            </div>
        </div>
    </div>
</section>
<?php
}

require_once __DIR__ . '/templates/footer.php';
?>