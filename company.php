<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = sanitizeInput($_GET['slug'] ?? '');
$vendorId = (int)($_GET['id'] ?? 0);

if ($slug) {
    $vStmt = $pdo->prepare('
        SELECT vp.*, c.name as category_name, d.name as district_name, u.full_name as owner_name 
        FROM vendor_profiles vp 
        LEFT JOIN categories c ON vp.category_id = c.id 
        LEFT JOIN districts d ON vp.district_id = d.id 
        LEFT JOIN users u ON vp.user_id = u.id 
        WHERE vp.slug = :slug LIMIT 1
    ');
    $vStmt->execute([':slug' => $slug]);
} else {
    $vStmt = $pdo->prepare('
        SELECT vp.*, c.name as category_name, d.name as district_name, u.full_name as owner_name 
        FROM vendor_profiles vp 
        LEFT JOIN categories c ON vp.category_id = c.id 
        LEFT JOIN districts d ON vp.district_id = d.id 
        LEFT JOIN users u ON vp.user_id = u.id 
        WHERE vp.id = :id LIMIT 1
    ');
    $vStmt->execute([':id' => $vendorId]);
}

$vendor = $vStmt->fetch();

if (!$vendor) {
    setFlash('danger', 'Company showroom / mini-website not found.');
    header('Location: ' . APP_URL . '/');
    exit;
}

// Fetch products of this vendor
$pStmt = $pdo->prepare('SELECT * FROM products WHERE vendor_id = :vid AND status = "active" LIMIT 12');
$pStmt->execute([':vid' => $vendor['id']]);
$products = $pStmt->fetchAll();

// Fetch services of this vendor
$sStmt = $pdo->prepare('SELECT * FROM service_items WHERE vendor_id = :vid AND status = "active" LIMIT 12');
$sStmt->execute([':vid' => $vendor['id']]);
$services = $sStmt->fetchAll();

// Fetch reviews
$rStmt = $pdo->prepare('SELECT r.*, u.full_name as reviewer_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.vendor_id = :vid ORDER BY r.id DESC LIMIT 5');
$rStmt->execute([':vid' => $vendor['id']]);
$reviews = $rStmt->fetchAll();

$cleanPhone = preg_replace('/\D/', '', (string)$vendor['mobile']);

require_once __DIR__ . '/templates/header.php';
?>

<!-- IndiaMART Style Branded Company Header Bar -->
<div class="bg-white border-bottom shadow-sm">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Company Info & TrustSEAL Badge -->
            <div class="d-flex align-items-center gap-3">
                <div class="bg-light border rounded-3 p-2 text-center d-flex align-items-center justify-content-center" style="width:70px; height:70px;">
                    <i class="bi bi-building fs-1 text-primary"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h3 class="fw-bold text-dark mb-0"><?= e($vendor['business_name']) ?></h3>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 small">
                            <i class="bi bi-patch-check-fill me-1"></i> BengalTrust Verified
                        </span>
                    </div>
                    <div class="text-muted small mt-1">
                        <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($vendor['city'] ?? 'Kolkata') ?>, <?= e($vendor['district_name'] ?? 'West Bengal') ?></span>
                        <span class="mx-2">&bull;</span>
                        <span class="badge bg-light text-dark border"><?= e($vendor['category_name']) ?></span>
                        <span class="mx-2">&bull;</span>
                        <span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> <?= e((string)$vendor['rating']) ?> ★</span> (<?= (int)$vendor['reviews_count'] ?> Ratings)
                    </div>
                </div>
            </div>

            <!-- Instant Contact / Inquiry Buttons -->
            <div class="d-flex gap-2">
                <a href="tel:<?= e($vendor['mobile']) ?>" class="btn btn-outline-success fw-bold px-3 py-2 d-flex align-items-center gap-2">
                    <i class="bi bi-telephone-fill"></i> <span><?= e($vendor['mobile']) ?></span>
                </a>
                <a href="https://wa.me/91<?= $cleanPhone ?>?text=<?= urlencode('Hello ' . $vendor['business_name'] . ', I saw your mini-website on BengalSeva and want to inquire.') ?>" target="_blank" class="btn btn-success fw-bold px-3 py-2 d-flex align-items-center gap-2">
                    <i class="bi bi-whatsapp"></i> <span>WhatsApp</span>
                </a>
                <button type="button" class="btn btn-warning text-dark fw-bold px-4 py-2" onclick="openLeadModal('Send Business Inquiry to <?= e($vendor['business_name']) ?>', '<?= e($vendor['business_name']) ?>', 'Looking for quotation & product availability.')" style="background:#ff6a00; color:#fff !important; border:none;">
                    Send Inquiry &rarr;
                </button>
            </div>
        </div>
    </div>
</div>

<!-- IndiaMART Style Sticky Navigation Menu -->
<div class="bg-dark py-2 sticky-top shadow-sm" style="z-index: 1020;">
    <div class="container d-flex gap-3 overflow-x-auto text-nowrap">
        <a href="#aboutSection" class="text-white text-decoration-none small fw-semibold px-2 py-1 rounded hover-bg">About Us</a>
        <a href="#catalogSection" class="text-white text-decoration-none small fw-semibold px-2 py-1 rounded hover-bg">Products &amp; Catalog (<?= count($products) + count($services) ?>)</a>
        <a href="#profileSpecs" class="text-white text-decoration-none small fw-semibold px-2 py-1 rounded hover-bg">Company Profile &amp; GST</a>
        <a href="#reviewsSection" class="text-white text-decoration-none small fw-semibold px-2 py-1 rounded hover-bg">Customer Reviews</a>
        <a href="#contactSection" class="text-white text-decoration-none small fw-semibold px-2 py-1 rounded hover-bg">Contact &amp; Location</a>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- 1. About Us Section -->
            <div id="aboutSection" class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle-fill text-primary me-2"></i> About <?= e($vendor['business_name']) ?></h5>
                <p class="text-secondary lh-lg mb-3">
                    <?= nl2br(e($vendor['description'] ?: 'Registered verified supplier and business partner on BengalSeva. Committed to delivering premium quality products, timely fulfillment, and direct client support across West Bengal.')) ?>
                </p>
                <div class="p-3 bg-light rounded-3 d-flex flex-wrap gap-4 text-dark small">
                    <div><strong>Nature of Business:</strong> <?= e($vendor['business_type'] ?? 'Wholesaler / Retailer') ?></div>
                    <div><strong>Operating Since:</strong> <?= e($vendor['established_year'] ?? '2018') ?></div>
                    <div><strong>GSTIN:</strong> <code><?= e($vendor['gstin'] ?? '19AAACB1234F1Z5') ?></code></div>
                </div>
            </div>

            <!-- 2. Products & Services Catalog -->
            <div id="catalogSection" class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-grid-3x3-gap-fill text-warning me-2"></i> Products &amp; Services Catalog</h5>
                    <span class="badge bg-light text-dark border"><?= count($products) + count($services) ?> Available</span>
                </div>

                <div class="row g-3">
                    <?php if (empty($products) && empty($services)): ?>
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="bi bi-box-seam fs-1 text-muted d-block mb-2"></i>
                            General inquiries and services available. Click "Send Inquiry" to ask for pricing.
                        </div>
                    <?php endif; ?>

                    <!-- Products -->
                    <?php foreach ($products as $p): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border rounded-3 p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <img src="<?= e($p['image_url'] ?: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80') ?>" style="width:100%; height:140px; object-fit:cover; border-radius:8px;" class="mb-2">
                                    <h6 class="fw-bold text-dark mb-1"><?= e($p['name']) ?></h6>
                                    <div class="fs-5 fw-bold text-primary mb-2">
                                        ₹<?= number_format((float)($p['sale_price'] ?: $p['price']), 0) ?>
                                        <small class="text-muted fs-6 fw-normal">/ <?= e($p['unit']) ?></small>
                                    </div>
                                    <p class="text-secondary small mb-2 text-truncate-2"><?= e($p['description'] ?? '') ?></p>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 mt-2" onclick="openLeadModal('Price Inquiry: <?= e($p['name']) ?>', '<?= e($vendor['business_name']) ?>', 'Inquiry for <?= e($p['name']) ?>.')">
                                    <i class="bi bi-chat-quote-fill me-1"></i> Get Best Quote
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Services -->
                    <?php foreach ($services as $s): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border rounded-3 p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="badge bg-light text-primary border mb-2">Service Item</div>
                                    <h6 class="fw-bold text-dark mb-1"><?= e($s['title']) ?></h6>
                                    <div class="fs-5 fw-bold text-dark mb-2">₹<?= number_format((float)$s['price'], 0) ?></div>
                                    <p class="text-secondary small mb-2"><?= e($s['description'] ?? '') ?></p>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold w-100 mt-2" onclick="openLeadModal('Book Service: <?= e($s['title']) ?>', '<?= e($vendor['business_name']) ?>', 'Booking service: <?= e($s['title']) ?>.')">
                                    <i class="bi bi-calendar-check me-1"></i> Book / Inquire
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. Customer Reviews Section -->
            <div id="reviewsSection" class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Client Ratings &amp; Reviews</h5>
                    <span class="fs-5 fw-bold text-dark"><?= e((string)$vendor['rating']) ?> / 5.0</span>
                </div>

                <?php if (empty($reviews)): ?>
                    <p class="text-muted small mb-0">No written reviews yet. Be the first to review this verified business!</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="border-bottom pb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark small"><?= e($rev['reviewer_name']) ?></strong>
                                    <span class="badge bg-warning text-dark"><?= (int)$rev['rating'] ?> ★</span>
                                </div>
                                <p class="text-secondary small mb-1"><?= e($rev['review_text'] ?? 'Good service.') ?></p>
                                <small class="text-muted" style="font-size:0.7rem;"><?= e(date('d M Y', strtotime($rev['created_at']))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: IndiaMART Fast Lead Form & Specs -->
        <div class="col-lg-4">
            <!-- Direct Inquiry Card (Sticky) -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 sticky-top" style="top: 80px; z-index: 1010;">
                <h5 class="fw-bold text-dark mb-1">Send Instant Inquiry</h5>
                <small class="text-muted d-block mb-3">Connect directly with <?= e($vendor['business_name']) ?></small>

                <form method="POST" action="<?= APP_URL ?>/index.php">
                    <input type="hidden" name="action" value="submit_lead">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="service_name" value="<?= e($vendor['business_name']) ?>">
                    <input type="hidden" name="district_id" value="<?= (int)$vendor['district_id'] ?>">

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">Your Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Your name or company" value="<?= Auth::check() ? e(Auth::user()['name']) : '' ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">Mobile Number (WhatsApp) *</label>
                        <input type="tel" name="mobile" class="form-control" placeholder="10 Digits" pattern="[0-9]{10}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Requirement / Message *</label>
                        <textarea name="requirement" class="form-control" rows="3" placeholder="Describe product quantity, specifications, or service needed..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2 text-dark rounded-3 shadow-sm" style="background:#ff6a00; color:#fff !important; border:none;">
                        Send Inquiry to Supplier &rarr;
                    </button>
                </form>

                <hr class="my-3">

                <!-- Company Specifications Table -->
                <div id="profileSpecs">
                    <h6 class="fw-bold text-dark mb-2">Company Facts</h6>
                    <table class="table table-sm table-borderless small mb-0">
                        <tr>
                            <td class="text-muted">GSTIN:</td>
                            <td class="fw-bold text-end"><code><?= e($vendor['gstin'] ?? '19AAACB1234F1Z5') ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Established:</td>
                            <td class="fw-bold text-end"><?= e($vendor['established_year'] ?? '2018') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Employees:</td>
                            <td class="fw-bold text-end"><?= e($vendor['total_employees'] ?? '10-25 People') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Location:</td>
                            <td class="fw-bold text-end"><?= e($vendor['city'] ?? '') ?>, <?= e($vendor['district_name'] ?? 'WB') ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Digital Visiting Card Share -->
                <div class="mt-3 pt-3 border-top text-center">
                    <a href="https://wa.me/?text=<?= urlencode('Check out ' . $vendor['business_name'] . ' verified showroom: ' . APP_URL . '/company.php?slug=' . $vendor['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-success w-100 fw-bold">
                        <i class="bi bi-share-fill me-1"></i> Share Digital Visiting Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>