<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'agent', 'manager']);
$agentUser = Auth::user();

// Fetch vendors in this agent's area
$merchants = $pdo->query('
    SELECT vp.*, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp 
    LEFT JOIN categories c ON vp.category_id = c.id 
    LEFT JOIN districts d ON vp.district_id = d.id 
    ORDER BY vp.id DESC LIMIT 50
')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-briefcase-fill text-warning me-2"></i> Field Agent Ground Console</h3>
            <small class="text-muted">Agent <?= e($agentUser['name']) ?> &bull; Onboard local merchants, shopkeepers and service pros</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/register-partner.php" target="_blank" class="btn btn-primary fw-bold btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Onboard New Merchant
            </a>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <!-- Onboarded Merchants Overview -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Merchant Onboarding Queue (<?= count($merchants) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Business Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>KYC Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($merchants as $m): ?>
                        <tr>
                            <td>#<?= (int)$m['id'] ?></td>
                            <td class="fw-bold text-dark"><?= e($m['business_name']) ?></td>
                            <td><span class="badge bg-light text-primary border"><?= e($m['category_name']) ?></span></td>
                            <td><?= e($m['city'] ?? '') ?>, <?= e($m['district_name'] ?? 'WB') ?></td>
                            <td><?= e($m['mobile']) ?></td>
                            <td>
                                <span class="badge bg-<?= $m['status'] === 'approved' ? 'success' : 'warning text-dark' ?> text-uppercase">
                                    <?= e($m['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>