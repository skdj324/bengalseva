<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'manager', 'hr']);
$currentUser = Auth::user();

// Handle User Edit / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'customer');
    $status = sanitizeInput($_POST['status'] ?? 'active');

    if ($userId > 0 && $fullName && $email) {
        $stmt = $pdo->prepare('
            UPDATE users 
            SET full_name = :name, email = :email, mobile = :mob, role = :role, status = :st, updated_at = NOW() 
            WHERE id = :id
        ');
        $stmt->execute([
            ':name' => $fullName,
            ':email'=> $email,
            ':mob'  => $mobile ?: null,
            ':role' => $role,
            ':st'   => $status,
            ':id'   => $userId
        ]);

        logAudit($pdo, $currentUser['id'], 'USER_UPDATED', 'users', $userId, "Updated profile for {$fullName} ({$email})");
        setFlash('success', "User '{$fullName}' updated successfully.");
        header('Location: users.php');
        exit;
    }
}

// Handle User Delete
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    if ($delId === 1 || $delId === (int)$currentUser['id']) {
        setFlash('danger', 'Super Admin account cannot be deleted.');
    } else {
        $delUser = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id');
        $delUser->execute([':id' => $delId]);
        $uInfo = $delUser->fetch();

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $delId]);
        logAudit($pdo, $currentUser['id'], 'USER_DELETED', 'users', $delId, "Deleted user: " . ($uInfo['email'] ?? ''));
        setFlash('info', 'User deleted successfully.');
    }
    header('Location: users.php');
    exit;
}

// Search and Filter
$search = sanitizeInput($_GET['q'] ?? '');
$filterRole = sanitizeInput($_GET['role'] ?? '');

$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($search) {
    $sql .= ' AND (full_name LIKE :q OR email LIKE :q OR mobile LIKE :q)';
    $params[':q'] = "%{$search}%";
}
if ($filterRole) {
    $sql .= ' AND role = :role';
    $params[':role'] = $filterRole;
}
$sql .= ' ORDER BY id DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i> User &amp; Customer Management</h3>
            <small class="text-muted">Edit profiles, change roles, suspend accounts, and view registered members</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card border-0 shadow-sm p-3 mb-4 bg-white rounded-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, email, or mobile..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles (Customers, Riders, Staff)</option>
                    <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor" <?= $filterRole === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                    <option value="rider" <?= $filterRole === 'rider' ? 'selected' : '' ?>>Rider / Captain</option>
                    <option value="manager" <?= $filterRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="agent" <?= $filterRole === 'agent' ? 'selected' : '' ?>>Agent</option>
                    <option value="accountant" <?= $filterRole === 'accountant' ? 'selected' : '' ?>>Accountant</option>
                    <option value="support" <?= $filterRole === 'support' ? 'selected' : '' ?>>Support</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100">Filter</button>
                <a href="users.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users found matching query.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= (int)$u['id'] ?></td>
                                <td class="fw-bold text-dark"><?= e($u['full_name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e($u['mobile'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['role'] === 'super_admin' ? 'dark' : ($u['role'] === 'customer' ? 'info' : 'primary') ?> text-uppercase">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'warning text-dark' : 'danger') ?> text-uppercase">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openUserEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ((int)$u['id'] !== 1 && (int)$u['id'] !== (int)$currentUser['id']): ?>
                                        <a href="users.php?delete_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user account <?= e($u['full_name']) ?>?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Edit Modal -->
<div class="modal fade" id="userEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number</label>
                        <input type="text" name="mobile" id="editMobile" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role</label>
                        <select name="role" id="editRole" class="form-select">
                            <option value="customer">Customer</option>
                            <option value="vendor">Vendor</option>
                            <option value="provider">Provider</option>
                            <option value="rider">Rider / Captain</option>
                            <option value="manager">Manager</option>
                            <option value="agent">Agent</option>
                            <option value="accountant">Accountant</option>
                            <option value="support">Support</option>
                            <option value="hr">HR</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editFullName').value = user.full_name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editMobile').value = user.mobile || '';
    document.getElementById('editRole').value = user.role;
    document.getElementById('editStatus').value = user.status;
    new bootstrap.Modal(document.getElementById('userEditModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>