<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$vendorId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT vp.*, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp
    LEFT JOIN categories c ON vp.category_id = c.id
    LEFT JOIN districts d ON vp.district_id = d.id
    WHERE vp.id = :id AND vp.status = "approved"
    LIMIT 1
');
$stmt->execute([':id' => $vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    setFlash('danger', 'Vendor profile is not available or pending KYC approval.');
    header('Location: ' . APP_URL . '/');
    exit;
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Left Column: Business Profile -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <span class="badge bg-success px-3 py-1 mb-2"><i class="bi bi-patch-check-fill me-1"></i> 100% VERIFIED PARTNER</span>
                        <h2 class="fw-bold text-dark mb-1"><?= e($vendor['business_name']) ?></h2>
                        <div class="text-primary fw-semibold"><?= e($vendor['category_name']) ?></div>
                    </div>
                    <div class="text-end">
                        <span class="fs-5 fw-bold text-warning"><i class="bi bi-star-fill"></i> <?= e((string)$vendor['rating']) ?></span>
                        <small class="text-muted d-block">(<?= (int)$vendor['reviews_count'] ?> Customer Reviews)</small>
                    </div>
                </div>

                <div class="border-top border-bottom py-3 my-3 text-secondary">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                            <?= e($vendor['address'] ? $vendor['address'] . ', ' : '') ?><?= e($vendor['city']) ?>, <?= e($vendor['district_name']) ?>
                        </div>
                        <div class="col-sm-6">
                            <i class="bi bi-shield-check text-success me-2"></i> Identity &amp; Location Verified by BengalSeva
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mt-3 mb-2">About the Business</h5>
                <p class="text-secondary lh-lg mb-4">
                    <?= nl2br(e($vendor['description'] ?: 'Professional and customer-focused service provider registered in West Bengal.')) ?>
                </p>

                <div class="bg-light p-3 rounded-3">
                    <h6 class="fw-bold text-dark mb-1">Service Coverage Area</h6>
                    <small class="text-muted"><?= e($vendor['district_name']) ?> &amp; neighboring blocks.</small>
                </div>
            </div>
        </div>

        <!-- Right Column: Direct Lead / Quote Request -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 90px;">
                <h5 class="fw-bold text-dark mb-1">Request Instant Quote</h5>
                <p class="text-muted small mb-3">Connect directly with <?= e($vendor['business_name']) ?></p>

                <form method="POST" action="<?= APP_URL ?>/index.php">
                    <input type="hidden" name="action" value="submit_lead">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="service_name" value="<?= e($vendor['business_name']) ?>">
                    <input type="hidden" name="district_id" value="<?= (int)$vendor['district_id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Your Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Your name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Phone (WhatsApp)</label>
                        <input type="tel" name="mobile" class="form-control" placeholder="10-digit number" pattern="[0-9]{10}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Service Requirement</label>
                        <textarea name="requirement" class="form-control" rows="3" placeholder="Describe the service required..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Send Direct Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
