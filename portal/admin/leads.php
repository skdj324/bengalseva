<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'manager', 'agent']);
$user = Auth::user();

// Handle Lead Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_lead'])) {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $vendorId = (int)($_POST['vendor_id'] ?? 0);

    if ($leadId > 0 && $vendorId > 0) {
        $up = $pdo->prepare('UPDATE leads SET vendor_id = :vid, status = "assigned" WHERE id = :lid');
        $up->execute([':vid' => $vendorId, ':lid' => $leadId]);
        logAudit($pdo, $user['id'], 'LEAD_ASSIGNED', 'leads', $leadId, "Lead assigned to Vendor ID {$vendorId}");
        setFlash('success', "Lead #{$leadId} assigned successfully.");
        header('Location: leads.php');
        exit;
    }
}

// Fetch Leads
$leads = $pdo->query('
    SELECT l.*, d.name as district_name, vp.business_name as assigned_vendor_name
    FROM leads l
    LEFT JOIN districts d ON l.district_id = d.id
    LEFT JOIN vendor_profiles vp ON l.vendor_id = vp.id
    ORDER BY l.id DESC
')->fetchAll();

// Fetch Approved Vendors for assignment dropdown
$vendors = $pdo->query('SELECT id, business_name, city FROM vendor_profiles WHERE status = "approved" ORDER BY business_name ASC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0">Central Lead Dispatch Room</h3>
            <small class="text-muted">Monitor, verify, and route incoming customer service inquiries</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Lead ID</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>District</th>
                        <th>Requirement</th>
                        <th>Assigned Partner</th>
                        <th>Status</th>
                        <th>Assign To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No customer leads in queue.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leads as $l): ?>
                            <tr>
                                <td>#<?= (int)$l['id'] ?></td>
                                <td class="fw-bold text-dark"><?= e($l['full_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($l['mobile']) ?>" class="fw-semibold text-decoration-none">
                                        <?= e($l['mobile']) ?>
                                    </a>
                                </td>
                                <td><?= e($l['district_name'] ?? 'West Bengal') ?></td>
                                <td class="small text-muted" style="max-width: 260px;"><?= e($l['requirement']) ?></td>
                                <td>
                                    <?php if ($l['assigned_vendor_name']): ?>
                                        <span class="badge bg-success"><?= e($l['assigned_vendor_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $l['status'] === 'new' ? 'primary' : ($l['status'] === 'assigned' ? 'info' : 'secondary') ?> text-uppercase">
                                        <?= e($l['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="assign_lead" value="1">
                                        <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
                                        <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Select Partner</option>
                                            <?php foreach ($vendors as $v): ?>
                                                <option value="<?= (int)$v['id'] ?>" <?= ((int)$l['vendor_id'] === (int)$v['id']) ? 'selected' : '' ?>>
                                                    <?= e($v['business_name']) ?> (<?= e($v['city']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
