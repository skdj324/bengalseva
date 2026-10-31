<?php
declare(strict_types=1);

// Fetch footer settings
$brandSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$siteName = $brandSettings['site_name'] ?? APP_NAME;
$supportWa = $brandSettings['footer_support_whatsapp'] ?? '9830000000';
?>
</main>

<!-- Bottom Trust Metrics Bar (Balanced 4-Column Layout, No Odd Floating Buttons on Desktop) -->
<section class="trust-metrics-bar">
    <div class="container">
        <div class="row g-3 text-center text-md-start">
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                    <i class="bi bi-shield-fill-check fs-2 text-success"></i>
                    <div>
                        <div class="fw-bold small">100% Secure</div>
                        <small class="text-white-50" style="font-size:0.75rem;">Encrypted &amp; Safe</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                    <i class="bi bi-people-fill fs-2 text-info"></i>
                    <div>
                        <div class="fw-bold small">14,820+ Verified</div>
                        <small class="text-white-50" style="font-size:0.75rem;">Partners &amp; Pilots</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                    <i class="bi bi-geo-alt-fill fs-2 text-warning"></i>
                    <div>
                        <div class="fw-bold small">23 Districts</div>
                        <small class="text-white-50" style="font-size:0.75rem;">Across West Bengal</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                    <i class="bi bi-headset fs-2 text-primary"></i>
                    <div>
                        <div class="fw-bold small">24&times;7 Helpdesk</div>
                        <small class="text-white-50" style="font-size:0.75rem;">Call &amp; WhatsApp</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Semantic Footer with Clean 4-Column Structure -->
<footer class="bg-white py-5 border-top">
    <div class="container">
        <div class="row g-4">
            <!-- Col 1: About & Brand -->
            <div class="col-lg-4 col-md-6">
                <div class="fw-bold text-dark fs-5 mb-2"><?= e($siteName) ?></div>
                <p class="text-muted small mb-3">West Bengal's premier all-in-one marketplace connecting doorstep services, verified healthcare, mart grocery deliveries, Rapido bike taxis, and Bengal tourism homestays.</p>
                <div class="small text-muted">&copy; <?= date('Y') ?> <?= e($siteName) ?>. All Rights Reserved.</div>
            </div>

            <!-- Col 2: Services & Verticals -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold text-dark mb-3">Quick Links</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                    <li><a href="<?= APP_URL ?>/" class="text-decoration-none text-secondary">Home</a></li>
                    <li><a href="<?= APP_URL ?>/category.php?slug=home-services" class="text-decoration-none text-secondary">Doorstep Services</a></li>
                    <li><a href="<?= APP_URL ?>/shop.php" class="text-decoration-none text-secondary">Mart &amp; Grocery</a></li>
                    <li><a href="<?= APP_URL ?>/ride.php" class="text-decoration-none text-secondary">Bike Taxi &amp; Cab</a></li>
                    <li><a href="<?= APP_URL ?>/travel.php" class="text-decoration-none text-secondary">Bengal Tourism</a></li>
                    <li><a href="<?= APP_URL ?>/properties.php" class="text-decoration-none text-secondary">Properties &amp; Rentals</a></li>
                    <li><a href="<?= APP_URL ?>/jobs.php" class="text-decoration-none text-secondary">Jobs &amp; Careers</a></li>
                </ul>
            </div>

            <!-- Col 3: Help & Support (Moved from Header) -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-question-circle text-info me-1"></i> Help &amp; Support</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                    <li><a href="<?= APP_URL ?>/support.php" class="text-decoration-none text-primary fw-bold">Raise a Support Ticket</a></li>
                    <li><a href="https://wa.me/91<?= preg_replace('/\D/', '', (string)$supportWa) ?>" target="_blank" class="text-decoration-none text-success fw-bold"><i class="bi bi-whatsapp me-1"></i> WhatsApp Helpline</a></li>
                    <li><a href="<?= APP_URL ?>/portal/customer/dashboard.php" class="text-decoration-none text-secondary">Track My Orders &amp; Rides</a></li>
                    <li><a href="<?= APP_URL ?>/register-partner.php" class="text-decoration-none text-secondary">Partner Registration</a></li>
                    <li><a href="#" class="text-decoration-none text-secondary">Privacy &amp; Terms</a></li>
                </ul>
            </div>

            <!-- Col 4: Download App (Moved from Header) -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-phone text-warning me-1"></i> Download Our App</h6>
                <p class="text-muted small mb-3">Get BengalSeva on your smartphone for faster ride booking and instant grocery delivery.</p>
                <div class="d-flex flex-column gap-2">
                    <button class="btn btn-dark btn-sm fw-bold py-2 d-flex align-items-center justify-content-center gap-2 rounded-3 text-start" data-bs-toggle="modal" data-bs-target="#downloadAppModal">
                        <i class="bi bi-google-play fs-5"></i>
                        <div>
                            <small class="d-block text-white-50" style="font-size:0.65rem; line-height:1;">GET IT ON</small>
                            <span class="fs-6">Google Play</span>
                        </div>
                    </button>
                    <button class="btn btn-outline-dark btn-sm fw-bold py-2 d-flex align-items-center justify-content-center gap-2 rounded-3 text-start" data-bs-toggle="modal" data-bs-target="#downloadAppModal">
                        <i class="bi bi-apple fs-5"></i>
                        <div>
                            <small class="d-block text-muted" style="font-size:0.65rem; line-height:1;">DOWNLOAD ON</small>
                            <span class="fs-6">App Store</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- App Download Modal -->
<div class="modal fade" id="downloadAppModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-4 text-center">
            <i class="bi bi-phone-fill text-primary display-4 mb-2"></i>
            <h4 class="fw-bold">BengalSeva Mobile App</h4>
            <p class="text-muted small">Access 14,820+ verified West Bengal services, doctors, groceries, and bike taxis anytime from your phone.</p>
            <div class="d-flex justify-content-center gap-2 mt-2">
                <button class="btn btn-dark fw-bold px-4 py-2" onclick="alert('Android APK is compiling for launch!')">
                    <i class="bi bi-google-play me-1"></i> Google Play
                </button>
                <button class="btn btn-outline-dark fw-bold px-4 py-2" onclick="alert('iOS App is coming soon!')">
                    <i class="bi bi-apple me-1"></i> App Store
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Native Mobile App Bottom Navigation Bar (Injected, strictly hidden on desktop) -->
<?php require_once __DIR__ . '/mobile_bottom_nav.php'; ?>

<!-- Floating AI Support Chat Widget -->
<?php if (file_exists(__DIR__ . '/ai_chat_widget.php')) require_once __DIR__ . '/ai_chat_widget.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (file_exists(__DIR__ . '/pwa_prompt.php')) require_once __DIR__ . '/pwa_prompt.php'; ?>
</body>
</html>