<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$type = sanitizeInput($_GET['type'] ?? '');
$districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : null;

$sql = '
    SELECT pl.*, d.name as district_name 
    FROM property_listings pl
    JOIN districts d ON pl.district_id = d.id
    WHERE pl.status = "active"
';
$params = [];

if ($type) {
    $sql .= ' AND pl.property_type = :ptype';
    $params[':ptype'] = $type;
}
if ($districtId) {
    $sql .= ' AND pl.district_id = :did';
    $params[':did'] = $districtId;
}
$sql .= ' ORDER BY pl.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/templates/header.php';
?>

<div class="bg-white border-bottom py-4 mb-4">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-houses-fill text-success me-2"></i> West Bengal Property &amp; Rentals</h3>
            <small class="text-muted">Verified residential flats, independent homes, and commercial offices</small>
        </div>
        <button class="btn btn-primary fw-bold" onclick="openLeadModal('Post Property Requirement', 'Real Estate', 'Looking for flat/house for rent or purchase.')">
            <i class="bi bi-plus-circle me-1"></i> Post Requirement
        </button>
    </div>
</div>

<div class="container mb-5">
    <!-- Filter -->
    <div class="card border-0 shadow-sm p-3 mb-4 bg-white rounded-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">All Types (Rent &amp; Sale)</option>
                    <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>>For Rent</option>
                    <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For Sale</option>
                </select>
            </div>
            <div class="col-md-4">
                <select name="district_id" class="form-select">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100">Search Properties</button>
                <a href="properties.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Properties Grid -->
    <div class="row g-4">
        <?php if (empty($listings)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-building-slash text-muted display-4 mb-3"></i>
                <h5>No property listings found matching criteria.</h5>
            </div>
        <?php else: ?>
            <?php foreach ($listings as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                        <div class="position-relative bg-light" style="height: 180px;">
                            <img src="<?= e($p['image_url'] ?: 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=500&q=80') ?>" style="width:100%; height:100%; object-fit:cover;">
                            <span class="badge bg-<?= $p['property_type'] === 'rent' ? 'success' : 'primary' ?> position-absolute top-0 start-0 m-2 fw-bold text-uppercase">
                                For <?= e($p['property_type']) ?>
                            </span>
                        </div>
                        <div class="p-3 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><?= e($p['title']) ?></h5>
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($p['city']) ?>, <?= e($p['district_name']) ?>
                                </div>
                                <div class="d-flex gap-3 text-secondary small mb-3">
                                    <span><i class="bi bi-door-open me-1"></i> <?= (int)$p['bhk'] ?> BHK</span>
                                    <span><i class="bi bi-building me-1"></i> <?= ucfirst(str_replace('_', ' ', $p['category_type'])) ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fs-5 fw-bold text-primary">
                                    &#8377;<?= number_format((float)$p['price'], 0) ?><?= $p['property_type'] === 'rent' ? '/mo' : '' ?>
                                </span>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="openLeadModal('Inquire Property', '<?= e($p['title']) ?>', 'Interested in viewing this <?= (int)$p['bhk'] ?> BHK property in <?= e($p['city']) ?>.')">
                                    Contact Owner
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
