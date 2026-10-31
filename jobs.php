<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : null;

$sql = '
    SELECT jp.*, d.name as district_name 
    FROM job_postings jp
    JOIN districts d ON jp.district_id = d.id
    WHERE jp.status = "active"
';
$params = [];
if ($districtId) {
    $sql .= ' AND jp.district_id = :did';
    $params[':did'] = $districtId;
}
$sql .= ' ORDER BY jp.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/templates/header.php';
?>

<div class="bg-white border-bottom py-4 mb-4">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-briefcase-fill text-primary me-2"></i> BengalSeva Jobs &amp; Careers</h3>
            <small class="text-muted">Verified job vacancies &amp; coaching institutes across West Bengal</small>
        </div>
        <button class="btn btn-primary fw-bold" onclick="openLeadModal('Post Resume / Candidate Requirement', 'Jobs & Coaching', 'Looking for job opportunity in West Bengal.')">
            <i class="bi bi-person-badge me-1"></i> Drop Your Resume
        </button>
    </div>
</div>

<div class="container mb-5">
    <!-- District Filter -->
    <div class="card border-0 shadow-sm p-3 mb-4 bg-white rounded-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-8">
                <select name="district_id" class="form-select">
                    <option value="">All West Bengal Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100">Filter Jobs</button>
                <a href="jobs.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Jobs List -->
    <div class="row g-3">
        <?php if (empty($jobs)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-briefcase text-muted display-4 mb-3"></i>
                <h5>No current vacancies listed for this region.</h5>
            </div>
        <?php else: ?>
            <?php foreach ($jobs as $j): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1"><?= e($j['title']) ?></h5>
                                    <div class="text-primary fw-semibold small"><?= e($j['company_name']) ?></div>
                                </div>
                                <span class="badge bg-light text-primary border text-uppercase"><?= str_replace('_', ' ', $j['job_type']) ?></span>
                            </div>

                            <p class="text-secondary small mb-3">
                                <?= e($j['description']) ?>
                            </p>

                            <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                                <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($j['city']) ?>, <?= e($j['district_name']) ?></span>
                                <span class="text-success fw-bold"><i class="bi bi-cash me-1"></i> <?= e($j['salary_range']) ?></span>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="btn btn-primary w-100 fw-bold rounded-2" onclick="openLeadModal('Apply for Job: <?= e($j['title']) ?>', '<?= e($j['company_name']) ?>', 'Submitting application for <?= e($j['title']) ?> vacancy.')">
                                Quick Apply Now &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
