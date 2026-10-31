<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Plan Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0.0);
    $validity = (int)($_POST['validity_days'] ?? 30);
    $credits = (int)($_POST['lead_credits'] ?? 50);
    $features = sanitizeInput($_POST['features'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');

    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));

    if ($name) {
        if ($planId > 0) {
            $stmt = $pdo->prepare('
                UPDATE membership_plans 
                SET name = :name, price = :price, validity_days = :val, lead_credits = :cred, features = :feat, status = :st
                WHERE id = :id
            ');
            $stmt->execute([
                ':name'  => $name,
                ':price' => $price,
                ':val'   => $validity,
                ':cred'  => $credits,
                ':feat'  => $features,
                ':st'    => $status,
                ':id'    => $planId
            ]);
            setFlash('success', "Plan '{$name}' updated successfully.");
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO membership_plans (name, slug, price, validity_days, lead_credits, features, status)
                VALUES (:name, :slug, :price, :val, :cred, :feat, :st)
            ');
            $stmt->execute([
                ':name'  => $name,
                ':slug'  => $slug . '-' . random_int(100, 999),
                ':price' => $price,
                ':val'   => $validity,
                ':cred'  => $credits,
                ':feat'  => $features,
                ':st'    => $status
            ]);
            setFlash('success', "New Plan '{$name}' created successfully.");
        }
        header('Location: plans.php');
        exit;
    }
}

// Handle Delete Plan
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM membership_plans WHERE id = :id');
    $stmt->execute([':id' => $delId]);
    setFlash('info', 'Membership plan removed.');
    header('Location: plans.php');
    exit;
}

$plans = $pdo->query('SELECT * FROM membership_plans ORDER BY price ASC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-award-fill text-primary me-2"></i> Partner &amp; Vendor Plans Master</h3>
            <small class="text-muted">Create subscription tiers, lead credit packages, and platform membership fees</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <div class="row g-4">
        <!-- Add / Edit Plan -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pencil-square me-1"></i> Add / Edit Membership Tier</h5>
                <form method="POST">
                    <input type="hidden" name="save_plan" value="1">
                    <input type="hidden" name="plan_id" id="formPlanId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Plan Name</label>
                        <input type="text" name="name" id="planName" class="form-control" placeholder="e.g. Gold Business Partner" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Price (₹)</label>
                            <input type="number" step="1" name="price" id="planPrice" class="form-control" placeholder="999" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Validity (Days)</label>
                            <input type="number" name="validity_days" id="planValidity" class="form-control" value="30" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Lead Credits Included</label>
                        <input type="number" name="lead_credits" id="planCredits" class="form-control" value="50" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Features (Comma-separated)</label>
                        <textarea name="features" id="planFeatures" class="form-control" rows="3" placeholder="Verified Badge, 50 Customer Leads, SMS Alerts"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select name="status" id="planStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Save Plan
                    </button>
                </form>
            </div>
        </div>

        <!-- Plan Cards List -->
        <div class="col-lg-8">
            <div class="row g-3">
                <?php foreach ($plans as $p): ?>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold text-dark mb-0"><?= e($p['name']) ?></h5>
                                    <span class="badge bg-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>"><?= strtoupper(e($p['status'])) ?></span>
                                </div>
                                <div class="display-6 fw-bold text-primary mb-2">
                                    ₹<?= number_format((float)$p['price'], 0) ?>
                                    <small class="fs-6 text-muted">/ <?= (int)$p['validity_days'] ?> Days</small>
                                </div>
                                <div class="badge bg-light text-dark border mb-3">
                                    <i class="bi bi-person-check-fill text-success me-1"></i> <?= (int)$p['lead_credits'] ?> Verified Leads Included
                                </div>
                                <p class="small text-secondary mb-3"><?= nl2br(e($p['features'] ?? '')) ?></p>
                            </div>
                            <div class="d-flex gap-2 pt-3 border-top">
                                <button class="btn btn-sm btn-outline-primary w-50 fw-bold" onclick="populatePlanEdit(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                                <a href="plans.php?delete_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger w-50 fw-bold" onclick="return confirm('Delete this subscription plan?')">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function populatePlanEdit(p) {
    document.getElementById('formPlanId').value = p.id;
    document.getElementById('planName').value = p.name;
    document.getElementById('planPrice').value = p.price;
    document.getElementById('planValidity').value = p.validity_days;
    document.getElementById('planCredits').value = p.lead_credits;
    document.getElementById('planFeatures').value = p.features || '';
    document.getElementById('planStatus').value = p.status;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>