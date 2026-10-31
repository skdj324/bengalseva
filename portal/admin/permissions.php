<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/permissions.php';

Auth::requireRole(['super_admin', 'admin']);
$currentUser = Auth::user();

$staffRoles = [
    'manager'    => 'Operations Manager',
    'accountant' => 'Accountant / Finance',
    'support'    => 'Customer Support Staff',
    'agent'      => 'Field / Verification Agent',
    'hr'         => 'Human Resources (HR)'
];

$selectedRole = sanitizeInput($_GET['role'] ?? 'manager');
if (!array_key_exists($selectedRole, $staffRoles)) {
    $selectedRole = 'manager';
}

$availablePermissions = [
    'Customer & Business Operations' => [
        'view_users'   => ['Manage Customers & Users', 'Allow viewing, searching, and editing customer profiles.'],
        'view_vendors' => ['Vendor KYC & Approvals', 'Allow verifying, approving, editing, or suspending vendor profiles.'],
        'view_leads'   => ['Lead Dispatch Console', 'Allow viewing customer inquiries and allocating leads to partners.']
    ],
    'Services, Orders & Trips' => [
        'view_orders'   => ['Mart & Grocery Orders', 'Allow tracking customer product orders, delivery status, and calling customers.'],
        'view_rides'    => ['Rapido Rides & Logistics', 'Allow viewing real-time bike taxi, cab, and parcel trip radar.'],
        'view_bookings' => ['Doctor & Clinic Appointments', 'Allow viewing patient appointment schedules and consultation fees.']
    ],
    'Finance & Staff Follow-Up CRM' => [
        'view_lenden'   => ['Lenden / Financial Ledger', 'Allow viewing all transaction amounts, payment gateways, and cash settlements.'],
        'add_followups' => ['Add CRM Follow-Up Notes', 'Allow staff to log customer conversation remarks and call outcomes.'],
        'view_audits'   => ['System Audit Records', 'Allow reviewing security and administrative activity logs.']
    ]
];

// Handle Saving Permissions for Selected Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    $submittedPerms = $_POST['perms'] ?? [];

    $upStmt = $pdo->prepare('
        INSERT INTO role_permissions (role, permission_key, is_allowed)
        VALUES (:role, :key, :allowed)
        ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed), updated_at = NOW()
    ');

    foreach ($availablePermissions as $group => $items) {
        foreach ($items as $key => $info) {
            $isAllowed = isset($submittedPerms[$key]) ? 1 : 0;
            $upStmt->execute([
                ':role'    => $selectedRole,
                ':key'     => $key,
                ':allowed' => $isAllowed
            ]);
        }
    }

    logAudit($pdo, $currentUser['id'], 'ROLE_PERMISSIONS_UPDATED', 'role_permissions', null, "Configured dashboard permissions for role: {$selectedRole}");
    setFlash('success', "Dashboard visibility permissions for '" . $staffRoles[$selectedRole] . "' updated successfully!");
    header("Location: permissions.php?role=" . urlencode($selectedRole));
    exit;
}

// Fetch Current Active Permissions for Selected Role
$stmt = $pdo->prepare('SELECT permission_key, is_allowed FROM role_permissions WHERE role = :role');
$stmt->execute([':role' => $selectedRole]);
$activePerms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-sliders text-primary me-2"></i> Staff Dashboard Permissions</h3>
            <small class="text-muted">Control exactly which cards, menus, orders, and financial data each staff role can see</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <!-- Role Selector Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 shadow-sm border">
        <?php foreach ($staffRoles as $code => $title): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $selectedRole === $code ? 'active' : '' ?>" href="permissions.php?role=<?= e($code) ?>">
                    <?= e($title) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Permissions Form -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-0">Role: <span class="text-primary"><?= e($staffRoles[$selectedRole]) ?></span></h4>
                <small class="text-muted">Toggle on the items this role is authorized to view on their dashboard &amp; portal</small>
            </div>
            <span class="badge bg-light text-dark border px-3 py-2 fs-6">Role Identifier: <code><?= e($selectedRole) ?></code></span>
        </div>

        <form method="POST">
            <input type="hidden" name="save_role_permissions" value="1">

            <?php foreach ($availablePermissions as $groupTitle => $permissions): ?>
                <h6 class="fw-bold text-uppercase text-secondary mt-3 mb-3" style="letter-spacing: 0.5px;">
                    <i class="bi bi-folder2-open me-2 text-primary"></i> <?= e($groupTitle) ?>
                </h6>

                <div class="row g-3 mb-4">
                    <?php foreach ($permissions as $key => $meta): ?>
                        <?php $isGranted = !empty($activePerms[$key]) && (int)$activePerms[$key] === 1; ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border rounded-3 p-3 <?= $isGranted ? 'bg-light border-primary' : 'bg-white' ?>">
                                <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0">
                                    <label class="form-check-label fw-bold text-dark cursor-pointer flex-grow-1" for="perm_<?= e($key) ?>">
                                        <?= e($meta[0]) ?>
                                    </label>
                                    <input class="form-check-input ms-2 fs-5" type="checkbox" name="perms[<?= e($key) ?>]" id="perm_<?= e($key) ?>" <?= $isGranted ? 'checked' : '' ?>>
                                </div>
                                <small class="text-muted d-block mt-2"><?= e($meta[1]) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 rounded-3 shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Save <?= e($staffRoles[$selectedRole]) ?> Permissions
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>