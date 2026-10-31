<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'hr', 'manager']);
$user = Auth::user();

// Handle User Account Status Changes from HR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hr_action'])) {
    $targetUid = (int)($_POST['user_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? 'active');

    if ($targetUid > 1) {
        $pdo->prepare('UPDATE users SET status = :st WHERE id = :id')->execute([':st' => $status, ':id' => $targetUid]);
        logAudit($pdo, $user['id'], 'HR_USER_STATUS_CHANGE', 'users', $targetUid, "HR set status to {$status}");
        setFlash('success', 'User account status updated successfully.');
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch staff and riders
$staffList = $pdo->query('
    SELECT * FROM users 
    WHERE role IN ("rider", "agent", "support", "accountant", "manager") 
    ORDER BY id DESC
')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-people text-primary me-2"></i> Human Resources &amp; Fleet Hiring</h3>
            <small class="text-muted">Onboard delivery pilots, support agents, accountants, and company staff</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/portal/admin/users.php" class="btn btn-outline-primary btn-sm fw-bold">Manage All Users</a>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Company Personnel &amp; Rider Pilots (<?= count($staffList) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Designation / Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $st): ?>
                        <tr>
                            <td>#<?= (int)$st['id'] ?></td>
                            <td class="fw-bold text-dark"><?= e($st['full_name']) ?></td>
                            <td><?= e($st['email']) ?></td>
                            <td><?= e($st['mobile'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-light text-primary border text-uppercase"><?= e($st['role']) ?></span></td>
                            <td>
                                <span class="badge bg-<?= $st['status'] === 'active' ? 'success' : ($st['status'] === 'pending' ? 'warning text-dark' : 'danger') ?> text-uppercase">
                                    <?= e($st['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="hr_action" value="1">
                                    <input type="hidden" name="user_id" value="<?= (int)$st['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Set Status</option>
                                        <option value="active">Activate (Hired)</option>
                                        <option value="suspended">Suspend</option>
                                        <option value="blocked">Block</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>