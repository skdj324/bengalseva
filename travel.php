<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$destFilter = sanitizeInput($_GET['destination'] ?? '');
$typeFilter = sanitizeInput($_GET['type'] ?? '');

$sql = '
    SELECT ta.*, d.name as district_name 
    FROM tours_accommodation ta 
    LEFT JOIN districts d ON ta.district_id = d.id 
    WHERE ta.status = "active"
';
$params = [];

if ($destFilter) {
    $sql .= ' AND ta.destination = :dest';
    $params[':dest'] = $destFilter;
}
if ($typeFilter) {
    $sql .= ' AND ta.package_type = :ptype';
    $params[':ptype'] = $typeFilter;
}
$sql .= ' ORDER BY ta.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stays = $stmt->fetchAll();

$destinations = ['Darjeeling', 'Digha', 'Sundarbans', 'Dooars', 'Bolpur', 'Kalimpong', 'Kurseong', 'Mandarmani'];

require_once __DIR__ . '/templates/header.php';
?>

<div class="bg-dark text-white py-5" style="background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);">
    <div class="container text-center py-2">
        <span class="badge bg-warning text-dark mb-2 fw-bold px-3 py-1">BengalSeva Tourism &amp; Homestays</span>
        <h1 class="display-6 fw-bold">Explore Beautiful Bengal</h1>
        <p class="lead opacity-75 mb-4">Authentic tea estate homestays, sea-facing beach cottages, and mangrove safari lodges</p>

        <!-- Quick Destination Pills -->
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <a href="travel.php" class="btn btn-sm btn-outline-light rounded-pill <?= empty($destFilter) ? 'active' : '' ?>">All Bengal</a>
            <?php foreach ($destinations as $dst): ?>
                <a href="travel.php?destination=<?= urlencode($dst) ?>" class="btn btn-sm btn-outline-light rounded-pill <?= $destFilter === $dst ? 'active' : '' ?>">
                    <?= e($dst) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container py-4 mb-5">
    <div class="row g-4">
        <?php if (empty($stays)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-compass text-muted display-4 mb-3"></i>
                <h5 class="fw-bold text-dark">No stays currently listed for this destination.</h5>
                <p class="text-muted small">Are you a homestay owner or resort host in West Bengal?</p>
                <a href="<?= APP_URL ?>/register-partner.php" class="btn btn-success fw-bold px-4">Register Your Homestay</a>
            </div>
        <?php else: ?>
            <?php foreach ($stays as $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white d-flex flex-column justify-content-between">
                        <div>
                            <div class="position-relative bg-light" style="height: 190px;">
                                <img src="<?= e($s['image_url'] ?: 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=500&q=80') ?>" style="width:100%; height:100%; object-fit:cover;">
                                <span class="badge bg-dark position-absolute top-0 start-0 m-2 text-uppercase">
                                    <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= e($s['destination']) ?>
                                </span>
                                <span class="badge bg-success position-absolute top-0 end-0 m-2 text-uppercase">
                                    <?= str_replace('_', ' ', e($s['package_type'])) ?>
                                </span>
                            </div>
                            <div class="p-3">
                                <h5 class="fw-bold text-dark mb-1"><?= e($s['title']) ?></h5>
                                <small class="text-muted d-block mb-2">District: <?= e($s['district_name'] ?? 'West Bengal') ?></small>
                                <p class="text-secondary small mb-3"><?= e($s['amenities'] ?? 'WiFi, Hot Water, Bengali Meals') ?></p>
                            </div>
                        </div>

                        <div class="p-3 pt-0 d-flex justify-content-between align-items-center border-top">
                            <div>
                                <span class="fs-5 fw-bold text-primary">₹<?= number_format((float)$s['price_per_night'], 0) ?></span>
                                <small class="text-muted">/ night</small>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm fw-bold px-3" onclick="openLeadModal('Book Stay: <?= e($s['title']) ?>', '<?= e($s['destination']) ?> Homestay', 'Booking enquiry for <?= e($s['title']) ?> in <?= e($s['destination']) ?>.')">
                                Check Dates &amp; Book
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>