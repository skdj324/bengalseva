<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'manager']);
$user = Auth::user();

// 1. Handle 1-Click Mini-Site Activation
if (isset($_GET['activate_minisite'])) {
    $actId = (int)$_GET['activate_minisite'];
    $pdo->prepare('UPDATE vendor_profiles SET has_minisite = 1, status = "approved" WHERE id = :id')->execute([':id' => $actId]);

    $vSlug = $pdo->query("SELECT slug FROM vendor_profiles WHERE id = {$actId}")->fetchColumn();
    logAudit($pdo, $user['id'], 'MINISITE_ACTIVATED', 'vendor_profiles', $actId, "Activated IndiaMART mini-website.");
    setFlash('success', 'Vendor Mini-Website created and launched successfully!');
    header('Location: ' . APP_URL . '/company.php?slug=' . urlencode((string)$vSlug));
    exit;
}

// 2. Handle Full Vendor & Mini-Site Specifications Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vendor_full'])) {
    $vId = (int)($_POST['vendor_id'] ?? 0);
    $bName = sanitizeInput($_POST['business_name'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);
    $distId = (int)($_POST['district_id'] ?? 0);
    $city = sanitizeInput($_POST['city'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $status = sanitizeInput($_POST['status'] ?? 'approved');
    $gstin = sanitizeInput($_POST['gstin'] ?? '19AAACB1234F1Z5');
    $estYear = sanitizeInput($_POST['established_year'] ?? '2018');
    $hasMini = isset($_POST['has_minisite']) ? 1 : 0;

    if ($vId > 0 && $bName) {
        $up = $pdo->prepare('
            UPDATE vendor_profiles 
            SET business_name = :bname, category_id = :cid, district_id = :did, city = :city, 
                mobile = :mob, email = :email, status = :st, gstin = :gst, 
                established_year = :eyear, has_minisite = :hmini, updated_at = NOW() 
            WHERE id = :id
        ');
        $up->execute([
            ':bname' => $bName,
            ':cid'   => $catId ?: null,
            ':did'   => $distId ?: null,
            ':city'  => $city,
            ':mob'   => $mobile,
            ':email' => $email,
            ':st'    => $status,
            ':gst'   => $gstin,
            ':eyear' => $estYear,
            ':hmini' => $hasMini,
            ':id'    => $vId
        ]);

        logAudit($pdo, $user['id'], 'VENDOR_FULL_EDIT', 'vendor_profiles', $vId, "Updated vendor & mini-site specs for {$bName}");
        setFlash('success', "Vendor profile and Mini-Site specifications saved!");
        header('Location: vendors.php');
        exit;
    }
}

// 3. Handle Vendor Delete
if (isset($_GET['delete_vendor_id'])) {
    $delId = (int)$_GET['delete_vendor_id'];
    $stmt = $pdo->prepare('DELETE FROM vendor_profiles WHERE id = :id');
    $stmt->execute([':id' => $delId]);
    logAudit($pdo, $user['id'], 'VENDOR_DELETED', 'vendor_profiles', $delId, 'Vendor deleted.');
    setFlash('info', 'Vendor profile deleted.');
    header('Location: vendors.php');
    exit;
}

$vendors = $pdo->query('
    SELECT vp.*, u.full_name as owner_name, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp
    LEFT JOIN users u ON vp.user_id = u.id
    LEFT JOIN categories c ON vp.category_id = c.id
    LEFT JOIN districts d ON vp.district_id = d.id
    ORDER BY vp.id DESC
')->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-building-check text-primary me-2"></i> Partner &amp; Vendor Management</h3>
            <small class="text-muted">1-Click generate IndiaMART style mini-websites, verify KYC documents, and manage company specs</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Business / Supplier</th>
                        <th>Category</th>
                        <th>District / City</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>IndiaMART Mini-Site</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): ?>
                        <tr>
                            <td>#<?= (int)$v['id'] ?></td>
                            <td>
                                <strong class="text-dark fs-6"><?= e($v['business_name']) ?></strong>
                                <div class="small text-muted">Owner: <?= e($v['owner_name']) ?> &bull; GSTIN: <?= e($v['gstin'] ?? 'Pending') ?></div>
                            </td>
                            <td><span class="badge bg-light text-primary border"><?= e($v['category_name']) ?></span></td>
                            <td><?= e($v['city'] ?? '') ?>, <?= e($v['district_name'] ?? 'WB') ?></td>
                            <td><?= e($v['mobile']) ?></td>
                            <td>
                                <span class="badge bg-<?= $v['status'] === 'approved' ? 'success' : 'warning text-dark' ?> text-uppercase">
                                    <?= e($v['status']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- 1-Click IndiaMART Mini-Site Action -->
                                <?php if (!empty($v['has_minisite'])): ?>
                                    <a href="<?= APP_URL ?>/company.php?slug=<?= urlencode($v['slug'] ?: (string)$v['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill d-inline-flex align-items-center gap-1 shadow-sm">
                                        <i class="bi bi-globe2 text-primary"></i> View Mini-Site &nearr;
                                    </a>
                                <?php else: ?>
                                    <a href="vendors.php?activate_minisite=<?= (int)$v['id'] ?>" class="btn btn-sm btn-success fw-bold px-3 rounded-pill d-inline-flex align-items-center gap-1 shadow-sm">
                                        <i class="bi bi-magic"></i> Create Mini-Site
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openVendorEditModal(<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="vendors.php?delete_vendor_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete vendor profile for <?= e($v['business_name']) ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vendor Edit & Mini-Site Modal -->
<div class="modal fade" id="vendorEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Vendor Profile &amp; Mini-Site Specs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_vendor_full" value="1">
                <input type="hidden" name="vendor_id" id="modalVendorId">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Business Name *</label>
                            <input type="text" name="business_name" id="modalVendorName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Primary Category</label>
                            <select name="category_id" id="modalVendorCat" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">District</label>
                            <select name="district_id" id="modalVendorDist" class="form-select" required>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">City / Area</label>
                            <input type="text" name="city" id="modalVendorCity" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone (WhatsApp)</label>
                            <input type="text" name="mobile" id="modalVendorMobile" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" id="modalVendorEmail" class="form-control">
                        </div>

                        <!-- Mini-Site Specifications -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-primary">GSTIN Number</label>
                            <input type="text" name="gstin" id="modalGstin" class="form-control" placeholder="19AAACB1234F1Z5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-primary">Year of Establishment</label>
                            <input type="text" name="established_year" id="modalEstYear" class="form-control" placeholder="2018">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">KYC / Listing Status</label>
                            <select name="status" id="modalVendorStatus" class="form-select">
                                <option value="approved">Approved &bull; Live in Directory</option>
                                <option value="pending">Pending KYC</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-3">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" name="has_minisite" id="modalHasMinisite" value="1">
                                <label class="form-check-label fs-6 fw-bold text-success" for="modalHasMinisite">Enable Live IndiaMART Mini-Site</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Profile &amp; Mini-Site</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openVendorEditModal(v) {
    document.getElementById('modalVendorId').value = v.id;
    document.getElementById('modalVendorName').value = v.business_name;
    document.getElementById('modalVendorCat').value = v.category_id || '';
    document.getElementById('modalVendorDist').value = v.district_id || '';
    document.getElementById('modalVendorCity').value = v.city || '';
    document.getElementById('modalVendorMobile').value = v.mobile || '';
    document.getElementById('modalVendorEmail').value = v.email || '';
    document.getElementById('modalVendorStatus').value = v.status;
    document.getElementById('modalGstin').value = v.gstin || '19AAACB1234F1Z5';
    document.getElementById('modalEstYear').value = v.established_year || '2018';
    document.getElementById('modalHasMinisite').checked = (parseInt(v.has_minisite) === 1);
    new bootstrap.Modal(document.getElementById('vendorEditModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>