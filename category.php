<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = sanitizeInput($_GET['slug'] ?? '');
$districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : null;

// Fetch Category info
$catStmt = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
$catStmt->execute([':slug' => $slug]);
$category = $catStmt->fetch();

if (!$category) {
    setFlash('warning', 'Category not found. Showing all verified businesses.');
}

// Build Vendor Query
$sql = '
    SELECT vp.*, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp
    LEFT JOIN categories c ON vp.category_id = c.id
    LEFT JOIN districts d ON vp.district_id = d.id
    WHERE vp.status = "approved"
';
$params = [];

if ($category) {
    $sql .= ' AND vp.category_id = :cat_id';
    $params[':cat_id'] = $category['id'];
}

if ($districtId) {
    $sql .= ' AND vp.district_id = :dist_id';
    $params[':dist_id'] = $districtId;
}

$sql .= ' ORDER BY vp.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

// All districts for filter dropdown
$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/templates/header.php';
?>

<div class="bg-white border-bottom py-4 mb-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/">Home</a></li>
                <li class="breadcrumb-item active"><?= $category ? e($category['name']) : 'All Categories' ?></li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-0"><?= $category ? e($category['name']) : 'Verified Service Providers' ?></h2>
                <small class="text-muted">Verified professionals and businesses across West Bengal</small>
            </div>
            <button class="btn btn-primary fw-bold" onclick="openLeadModal('Post Requirement', '<?= $category ? e($category['name']) : 'General' ?>', 'I need assistance with <?= $category ? e($category['name']) : 'service' ?>')">
                <i class="bi bi-send-fill me-1"></i> Post Requirement
            </button>
        </div>
    </div>
</div>

<div class="container py-2 mb-5">
    <!-- District Filter -->
    <div class="card border-0 shadow-sm p-3 mb-4 rounded-3 bg-white">
        <form method="GET" action="category.php" class="row g-2 align-items-center">
            <input type="hidden" name="slug" value="<?= e($slug) ?>">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Filter by District</label>
                <select name="district_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All West Bengal Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 align-self-end">
                <a href="category.php?slug=<?= e($slug) ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <!-- Vendor Cards -->
    <div class="row g-4">
        <?php if (empty($vendors)): ?>
            <div class="col-12">
                <div class="card border-0 p-5 text-center bg-white rounded-4 shadow-sm">
                    <i class="bi bi-search text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold text-dark">No approved providers listed in this district yet.</h5>
                    <p class="text-muted small">Are you a professional or business owner in this region?</p>
                    <div>
                        <a href="<?= APP_URL ?>/register-partner.php" class="btn btn-success fw-bold px-4">
                            Register as First Partner in this Category
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($vendors as $v): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="service-vendor-card">
                        <div class="service-card-img-wrap bg-light d-flex align-items-center justify-content-center">
                            <div class="text-center text-primary">
                                <i class="bi bi-building fs-1"></i>
                            </div>
                            <span class="verified-tag"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
                        </div>
                        <div class="service-vendor-body">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><?= e($v['business_name']) ?></h5>
                                <div class="badge bg-light text-primary border mb-2"><?= e($v['category_name']) ?></div>
                                <p class="text-muted small mb-2 text-truncate-2"><?= e($v['description'] ?? 'Verified local service provider on BengalSeva.') ?></p>
                                <div class="text-muted small">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($v['city'] ?? '') ?>, <?= e($v['district_name'] ?? 'West Bengal') ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <a href="<?= APP_URL ?>/vendor.php?id=<?= (int)$v['id'] ?>" class="btn btn-outline-primary btn-sm w-50 fw-semibold">
                                    View Details
                                </a>
                                <button type="button" class="btn btn-primary btn-sm w-50 fw-bold" onclick="openLeadModal('Request Callback', '<?= e($v['business_name']) ?>', 'Contacting provider for service inquiries.')">
                                    Contact
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
