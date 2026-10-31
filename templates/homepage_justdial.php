<?php
// Justdial.com Clean Desktop Portal Engine (No Ride inside search bar)
$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

// Fetch verified businesses
$jdVendors = $pdo->query('
    SELECT vp.*, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp 
    LEFT JOIN categories c ON vp.category_id = c.id 
    LEFT JOIN districts d ON vp.district_id = d.id 
    WHERE vp.status = "approved" 
    ORDER BY vp.rating DESC, vp.reviews_count DESC LIMIT 8
')->fetchAll();

// Check for active homepage middle advertisement
$adStmt = $pdo->query('SELECT * FROM advertisements WHERE slot_position = "homepage_middle" AND status = "active" ORDER BY id DESC LIMIT 1');
$middleAd = $adStmt->fetch();
?>

<!-- 1. Justdial Big Search Hero (Clean: City Dropdown + Search Query + Mic + Search Button) -->
<section class="py-4 py-lg-5 text-white" style="background: linear-gradient(135deg, #0b2239 0%, #001529 100%); border-bottom: 3px solid #ff6a00;">
    <div class="container-fluid px-lg-5 text-center">
        <h1 class="display-6 fw-bold mb-2">Search Local Businesses &amp; Services</h1>
        <p class="text-white-50 small mb-4">Search across 23 Districts of West Bengal &bull; 14,820+ Verified Experts &bull; Instant Best Price</p>

        <!-- Clean Justdial Dual-Field Search Box (NO RIDE BUTTON INSIDE) -->
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">
                <form class="p-2 bg-white rounded-3 shadow-lg d-flex flex-column flex-md-row gap-2 text-start" method="GET" action="<?= APP_URL ?>/category.php">
                    <!-- City / Area Location Dropdown -->
                    <div class="d-flex align-items-center px-2 py-1 border-end-md" style="min-width: 250px;">
                        <i class="bi bi-geo-alt-fill text-danger fs-5 me-2"></i>
                        <select name="district_id" class="form-select border-0 fw-bold text-dark p-0" id="jdCitySelect">
                            <option value="">All West Bengal</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= ((int)$d['id'] === 12) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input Box -->
                    <div class="d-flex align-items-center px-2 py-1 flex-grow-1">
                        <i class="bi bi-search text-muted fs-5 me-2"></i>
                        <input type="text" name="q" id="jdSearchQueryInput" class="form-control border-0 p-0 fs-6" placeholder="Search for Restaurants, Doctors, AC Service, Electrician, Packers..." required>
                        <button type="button" class="btn btn-link text-muted p-0 ms-2" onclick="startVoiceSearch()" title="Voice Search">
                            <i class="bi bi-mic-fill fs-5 text-primary"></i>
                        </button>
                    </div>

                    <!-- Clean Orange Search Button -->
                    <button type="submit" class="btn btn-warning px-4 py-2 fw-bold text-dark rounded-2 d-flex align-items-center justify-content-center gap-1 shadow-sm" style="background:#ff6a00; border:none; color:#fff !important;">
                        <i class="bi bi-search"></i> <span>Search</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Trending Searches Tags -->
        <div class="d-flex justify-content-center flex-wrap gap-2 mt-3 pt-1">
            <span class="text-white-50 small me-1">Trending:</span>
            <a href="<?= APP_URL ?>/category.php?slug=home-services" class="badge bg-white bg-opacity-10 text-white text-decoration-none px-3 py-2 rounded-pill small">AC Service</a>
            <a href="<?= APP_URL ?>/category.php?slug=doctor-clinic" class="badge bg-white bg-opacity-10 text-white text-decoration-none px-3 py-2 rounded-pill small">Cardiologist</a>
            <a href="<?= APP_URL ?>/category.php?slug=electrician" class="badge bg-white bg-opacity-10 text-white text-decoration-none px-3 py-2 rounded-pill small">Electricians</a>
            <a href="<?= APP_URL ?>/category.php?slug=packers-movers" class="badge bg-white bg-opacity-10 text-white text-decoration-none px-3 py-2 rounded-pill small">Packers &amp; Movers</a>
            <a href="<?= APP_URL ?>/travel.php" class="badge bg-white bg-opacity-10 text-white text-decoration-none px-3 py-2 rounded-pill small">Darjeeling Stays</a>
        </div>
    </div>
</section>

<!-- 2. Justdial Category Icon Strip (Rides has its own clean tile here) -->
<section class="py-4 bg-white border-bottom">
    <div class="container-fluid px-lg-5">
        <h5 class="fw-bold text-dark mb-3">Popular Categories</h5>
        <div class="row g-2 text-center">
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=doctor-clinic" class="jd-category-tile">
                    <div class="jd-icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-heart-pulse-fill"></i></div>
                    <span class="jd-cat-name">Doctors</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=restaurants-food" class="jd-category-tile">
                    <div class="jd-icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-cup-hot-fill"></i></div>
                    <span class="jd-cat-name">Restaurants</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=home-services" class="jd-category-tile">
                    <div class="jd-icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-tools"></i></div>
                    <span class="jd-cat-name">Home Care</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/ride.php" class="jd-category-tile">
                    <div class="jd-icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-scooter text-dark"></i></div>
                    <span class="jd-cat-name">Bike Taxi (₹49)</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=beauty-salon" class="jd-category-tile">
                    <div class="jd-icon-circle bg-pink bg-opacity-10 text-danger"><i class="bi bi-scissors"></i></div>
                    <span class="jd-cat-name">Beauty Spa</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=real-estate" class="jd-category-tile">
                    <div class="jd-icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-building"></i></div>
                    <span class="jd-cat-name">Real Estate</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/category.php?slug=packers-movers" class="jd-category-tile">
                    <div class="jd-icon-circle bg-info bg-opacity-10 text-info"><i class="bi bi-truck"></i></div>
                    <span class="jd-cat-name">Packers</span>
                </a>
            </div>
            <div class="col-3 col-md-2 col-lg-1-5">
                <a href="<?= APP_URL ?>/shop.php" class="jd-category-tile">
                    <div class="jd-icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-basket3-fill"></i></div>
                    <span class="jd-cat-name">Mart &amp; Grocery</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 3. Active Advertisement Slot (If Admin published an Ad) -->
<?php if ($middleAd): ?>
<div class="container-fluid px-lg-5 my-4">
    <?php if ($middleAd['ad_type'] === 'adsense_code'): ?>
        <div class="text-center"><?= $middleAd['code_snippet'] ?></div>
    <?php elseif (!empty($middleAd['banner_image'])): ?>
        <div class="text-center rounded-4 overflow-hidden shadow-sm">
            <a href="<?= e($middleAd['target_url'] ?: '#') ?>" target="_blank">
                <img src="<?= e($middleAd['banner_image']) ?>" alt="<?= e($middleAd['title']) ?>" class="img-fluid w-100" style="max-height: 180px; object-fit: cover;">
            </a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 4. Verified Businesses Grid -->
<section class="py-4 py-lg-5">
    <div class="container-fluid px-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-0">Verified Local Businesses</h4>
                <small class="text-muted">Direct contact, ratings, verified phone numbers &amp; best deal quotations</small>
            </div>
            <a href="<?= APP_URL ?>/category.php?slug=home-services" class="btn btn-outline-primary btn-sm fw-bold px-3 rounded-pill">
                View All Businesses &rarr;
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($jdVendors as $v): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-column justify-content-between border-top border-primary border-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">
                                        <a href="<?= APP_URL ?>/company.php?slug=<?= urlencode($v['slug'] ?: (string)$v['id']) ?>" class="text-dark text-decoration-none"><?= e($v['business_name']) ?></a>
                                    </h5>
                                    <span class="badge bg-light text-primary border"><?= e($v['category_name']) ?></span>
                                </div>
                                <span class="badge bg-success fs-6"><i class="bi bi-star-fill text-warning me-1"></i> <?= e((string)$v['rating']) ?> ★</span>
                            </div>

                            <p class="text-secondary small mb-2 text-truncate-2"><?= e($v['description'] ?? 'Verified service provider.') ?></p>
                            
                            <div class="small text-muted mb-3">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($v['city'] ?? '') ?>, <?= e($v['district_name'] ?? 'West Bengal') ?>
                                <span class="badge bg-light text-success border ms-2"><i class="bi bi-patch-check-fill"></i> JD VERIFIED</span>
                            </div>
                        </div>

                        <div class="d-flex gap-2 pt-2 border-top">
                            <a href="tel:<?= e($v['mobile']) ?>" class="btn btn-success btn-sm w-50 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-1">
                                <i class="bi bi-telephone-fill"></i> Show Number
                            </a>
                            <button type="button" class="btn btn-warning text-dark btn-sm w-50 fw-bold rounded-3" onclick="openLeadModal('Get Best Deal: <?= e($v['business_name']) ?>', '<?= e($v['business_name']) ?>', 'Enquiring for best price and quotation.')" style="background:#ff6a00; color:#fff !important; border:none;">
                                Best Deal
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
.col-lg-1-5 { flex: 0 0 auto; width: 12.5%; }
@media (max-width: 991.98px) { .col-lg-1-5 { width: 25%; } }
.jd-category-tile { text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 10px 4px; border-radius: 12px; transition: transform 0.2s; }
.jd-category-tile:hover { transform: translateY(-3px); }
.jd-icon-circle { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 6px; }
.jd-cat-name { font-size: 0.8rem; font-weight: 700; color: #1e293b; }
</style>