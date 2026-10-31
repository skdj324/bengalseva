<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['vendor', 'super_admin', 'admin']);
$user = Auth::user();

// Fetch vendor profile
$stmt = $pdo->prepare('SELECT * FROM vendor_profiles WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid' => $user['id']]);
$profile = $stmt->fetch();

// Handle Lead Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead_status'])) {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $allowed = ['contacted', 'converted', 'closed', 'cancelled'];

    if (in_array($newStatus, $allowed, true) && $leadId > 0) {
        $up = $pdo->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $up->execute([':status' => $newStatus, ':id' => $leadId]);
        logAudit($pdo, $user['id'], 'LEAD_STATUS_UPDATE', 'leads', $leadId, "Status updated to {$newStatus}");
        setFlash('success', 'Lead status updated to ' . ucfirst($newStatus));
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch leads: leads directly assigned or leads matching this vendor\'s district & category
$leads = [];
if ($profile) {
    $leadStmt = $pdo->prepare('
        SELECT l.*, d.name as district_name 
        FROM leads l
        LEFT JOIN districts d ON l.district_id = d.id
        WHERE l.vendor_id = :vid 
           OR (l.vendor_id IS NULL AND l.district_id = :did AND l.category_id = :cid)
           OR (l.requirement LIKE :bname)
        ORDER BY l.id DESC
    ');
    $leadStmt->execute([
        ':vid'   => $profile['id'],
        ':did'   => $profile['district_id'],
        ':cid'   => $profile['category_id'],
        ':bname' => '%' . $profile['business_name'] . '%'
    ]);
    $leads = $leadStmt->fetchAll();
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <!-- Vendor Profile Alert / KYC Banner -->
    <?php if (!$profile): ?>
        <div class="alert alert-warning">No vendor profile attached to this account.</div>
    <?php elseif ($profile['status'] === 'pending'): ?>
        <div class="alert alert-warning d-flex align-items-center gap-3 rounded-3 shadow-sm">
            <i class="bi bi-hourglass-split fs-2"></i>
            <div>
                <strong>KYC Verification in Progress:</strong> Your profile is currently under review by the BengalSeva compliance team. Once approved, your business will appear publicly in the West Bengal directory.
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1"><?= e($profile['business_name'] ?? $user['name']) ?></h3>
                <span class="badge bg-<?= ($profile && $profile['status'] === 'approved') ? 'success' : 'warning text-dark' ?> text-uppercase">
                    KYC Status: <?= e($profile['status'] ?? 'pending') ?>
                </span>

            <!-- Live Mini-Site Share Link -->
            <div class="mt-3 pt-3 border-top d-flex gap-2 align-items-center flex-wrap">
                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Live Digital Showroom</span>
                <a href="https://searchkaro.midexa.in/company.php?slug=<?= urlencode($profile['slug'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">
                    <i class="bi bi-globe2 me-1"></i> View My Live Mini-Site &nearr;
                </a>
                <a href="https://wa.me/?text=<?= urlencode('Visit our official digital showroom: ' . APP_URL . '/company.php?slug=' . ($profile['slug'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-success fw-bold rounded-pill">
                    <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
                </a>
            </div>
            </div>
            <span class="text-muted small">Logged in as: <?= e($user['email']) ?></span>

            <!-- Live Mini-Site Share Link -->
            <div class="mt-3 pt-3 border-top d-flex gap-2 align-items-center flex-wrap">
                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Live Digital Showroom</span>
                <a href="https://searchkaro.midexa.in/company.php?slug=<?= urlencode($profile['slug'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">
                    <i class="bi bi-globe2 me-1"></i> View My Live Mini-Site &nearr;
                </a>
                <a href="https://wa.me/?text=<?= urlencode('Visit our official digital showroom: ' . APP_URL . '/company.php?slug=' . ($profile['slug'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-success fw-bold rounded-pill">
                    <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Leads Inbox -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-inbox-fill text-primary me-2"></i> Customer Leads Inbox (<?= count($leads) ?>)</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Lead ID</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>District</th>
                        <th>Requirement</th>
                        <th>Status</th>
                        <th>Received On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No customer inquiries received yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leads as $l): ?>
                            <tr>
                                <td>#<?= (int)$l['id'] ?></td>
                                <td class="fw-semibold text-dark"><?= e($l['full_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($l['mobile']) ?>" class="text-decoration-none fw-bold">
                                        <i class="bi bi-telephone-fill me-1 text-success"></i> <?= e($l['mobile']) ?>
                                    </a>
                                </td>
                                <td><?= e($l['district_name'] ?? 'West Bengal') ?></td>
                                <td class="small text-muted" style="max-width: 250px;"><?= e($l['requirement']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $l['status'] === 'new' ? 'primary' : ($l['status'] === 'converted' ? 'success' : 'secondary') ?>">
                                        <?= e(strtoupper($l['status'])) ?>
                                    </span>

            <!-- Live Mini-Site Share Link -->
            <div class="mt-3 pt-3 border-top d-flex gap-2 align-items-center flex-wrap">
                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Live Digital Showroom</span>
                <a href="https://searchkaro.midexa.in/company.php?slug=<?= urlencode($profile['slug'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">
                    <i class="bi bi-globe2 me-1"></i> View My Live Mini-Site &nearr;
                </a>
                <a href="https://wa.me/?text=<?= urlencode('Visit our official digital showroom: ' . APP_URL . '/company.php?slug=' . ($profile['slug'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-success fw-bold rounded-pill">
                    <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
                </a>
            </div>
                                </td>
                                <td class="small text-muted"><?= e(date('d M Y, h:i A', strtotime($l['created_at']))) ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="update_lead_status" value="1">
                                        <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Change Status</option>
                                            <option value="contacted">Contacted</option>
                                            <option value="converted">Converted</option>
                                            <option value="closed">Closed</option>
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
