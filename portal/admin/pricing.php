<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Add / Edit Fare Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fare'])) {
    $vType = sanitizeInput($_POST['vehicle_type'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $baseFare = (float)($_POST['base_fare'] ?? 25.0);
    $perKm = (float)($_POST['per_km_rate'] ?? 9.0);
    $minFare = (float)($_POST['min_fare'] ?? 25.0);
    $desc = sanitizeInput($_POST['description'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');

    if ($vType && $title) {
        $stmt = $pdo->prepare('
            INSERT INTO fare_settings (vehicle_type, title, base_fare, per_km_rate, min_fare, description, status)
            VALUES (:vtype, :title, :base, :perkm, :min, :desc, :status)
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title), base_fare = VALUES(base_fare),
                per_km_rate = VALUES(per_km_rate), min_fare = VALUES(min_fare),
                description = VALUES(description), status = VALUES(status)
        ');
        $stmt->execute([
            ':vtype' => $vType,
            ':title' => $title,
            ':base'  => $baseFare,
            ':perkm' => $perKm,
            ':min'   => $minFare,
            ':desc'  => $desc,
            ':status'=> $status
        ]);
        logAudit($pdo, $user['id'], 'FARE_PLAN_UPDATED', 'fare_settings', null, "Fare plan for {$vType} updated.");
        setFlash('success', "Pricing plan for '{$title}' saved successfully!");
        header('Location: pricing.php');
        exit;
    }
}

// Handle Delete Fare Plan
if (isset($_GET['delete_type'])) {
    $delType = sanitizeInput($_GET['delete_type']);
    $stmt = $pdo->prepare('DELETE FROM fare_settings WHERE vehicle_type = :vtype');
    $stmt->execute([':vtype' => $delType]);
    logAudit($pdo, $user['id'], 'FARE_PLAN_DELETED', 'fare_settings', null, "Deleted fare plan {$delType}");
    setFlash('info', 'Pricing plan deleted.');
    header('Location: pricing.php');
    exit;
}

$fares = $pdo->query('SELECT * FROM fare_settings ORDER BY id ASC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer text-warning me-2"></i> Ride Fare &amp; Pricing Master</h3>
            <small class="text-muted">Manage real-time per-km rates, base fares, and minimum trip charges</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4">
        <!-- Add / Edit Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pencil-square me-1"></i> Add / Update Plan</h5>
                <form method="POST">
                    <input type="hidden" name="save_fare" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Vehicle Identifier (Key)</label>
                        <select name="vehicle_type" id="formVType" class="form-select" required>
                            <option value="bike_taxi">bike_taxi (Bike Taxi)</option>
                            <option value="auto_toto">auto_toto (Auto / Toto)</option>
                            <option value="cab">cab (Mini Cab / AC Car)</option>
                            <option value="parcel">parcel (Parcel Courier)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Display Title</label>
                        <input type="text" name="title" id="formTitle" class="form-control" placeholder="e.g. Bike Taxi Express" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Base Fare (₹)</label>
                            <input type="number" step="0.5" name="base_fare" id="formBase" class="form-control" placeholder="25" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Per KM (₹)</label>
                            <input type="number" step="0.5" name="per_km_rate" id="formPerKm" class="form-control" placeholder="9" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Minimum Fare (₹)</label>
                        <input type="number" step="0.5" name="min_fare" id="formMin" class="form-control" placeholder="25" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Description / Highlight</label>
                        <input type="text" name="description" id="formDesc" class="form-control" placeholder="Fastest transit in city traffic">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select name="status" id="formStatus" class="form-select">
                            <option value="active">Active (Live in App)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Save Pricing Plan
                    </button>
                </form>
            </div>
        </div>

        <!-- Fares List Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark">Active Ride Plans</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Plan / Vehicle</th>
                                <th>Base Fare</th>
                                <th>Per KM Rate</th>
                                <th>Min Fare</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fares as $f): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($f['title']) ?></div>
                                        <small class="text-muted"><code><?= e($f['vehicle_type']) ?></code></small>
                                    </td>
                                    <td class="fw-semibold">₹<?= number_format((float)$f['base_fare'], 2) ?></td>
                                    <td class="fw-bold text-primary">₹<?= number_format((float)$f['per_km_rate'], 2) ?>/km</td>
                                    <td>₹<?= number_format((float)$f['min_fare'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $f['status'] === 'active' ? 'success' : 'secondary' ?> text-uppercase">
                                            <?= e($f['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="populateEdit(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="pricing.php?delete_type=<?= urlencode($f['vehicle_type']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this fare plan?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function populateEdit(f) {
    document.getElementById('formVType').value = f.vehicle_type;
    document.getElementById('formTitle').value = f.title;
    document.getElementById('formBase').value = f.base_fare;
    document.getElementById('formPerKm').value = f.per_km_rate;
    document.getElementById('formMin').value = f.min_fare;
    document.getElementById('formDesc').value = f.description || '';
    document.getElementById('formStatus').value = f.status;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>