<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

// Only Super Admin or someone currently simulating can access
$originalAdminId = $_SESSION['original_super_admin_id'] ?? null;
$currentRole = $_SESSION['user_role'] ?? '';

if ($currentRole !== 'super_admin' && !$originalAdminId) {
    http_response_code(403);
    die('Unauthorized access.');
}

// Action 1: Revert back to Super Admin
if (isset($_GET['action']) && $_GET['action'] === 'revert' && $originalAdminId) {
    $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $originalAdminId]);
    $adminUser = $stmt->fetch();

    if ($adminUser) {
        $_SESSION['user_id'] = (int)$adminUser['id'];
        $_SESSION['user_name'] = $adminUser['full_name'];
        $_SESSION['user_email'] = $adminUser['email'];
        $_SESSION['user_role'] = $adminUser['role'];
        unset($_SESSION['original_super_admin_id']);

        setFlash('success', 'Switched back to Super Admin mode.');
        header('Location: ' . APP_URL . '/portal/dashboard.php');
        exit;
    }
}

// Action 2: Simulate another role
if (isset($_GET['switch_to'])) {
    $targetRole = sanitizeInput($_GET['switch_to']);

    // Find a real user with this role, or simulate the persona
    $uStmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE role = :r AND status = "active" LIMIT 1');
    $uStmt->execute([':r' => $targetRole]);
    $targetUser = $uStmt->fetch();

    if (!$originalAdminId) {
        $_SESSION['original_super_admin_id'] = (int)$_SESSION['user_id'];
    }

    if ($targetUser) {
        $_SESSION['user_id'] = (int)$targetUser['id'];
        $_SESSION['user_name'] = $targetUser['full_name'];
        $_SESSION['user_email'] = $targetUser['email'];
        $_SESSION['user_role'] = $targetUser['role'];
    } else {
        // Fallback simulation session
        $_SESSION['user_name'] = 'Simulated ' . ucfirst($targetRole);
        $_SESSION['user_role'] = $targetRole;
    }

    setFlash('info', "Now simulating dashboard as role: " . strtoupper($targetRole));

    // Redirect to specialized portal if applicable
    if ($targetRole === 'rider') {
        header('Location: ' . APP_URL . '/portal/rider/dashboard.php');
    } elseif ($targetRole === 'vendor') {
        header('Location: ' . APP_URL . '/portal/vendor/dashboard.php');
    } elseif ($targetRole === 'accountant') {
        header('Location: ' . APP_URL . '/portal/accountant/dashboard.php');
    } elseif ($targetRole === 'support') {
        header('Location: ' . APP_URL . '/portal/support/dashboard.php');
    } elseif ($targetRole === 'hr') {
        header('Location: ' . APP_URL . '/portal/hr/dashboard.php');
    } elseif ($targetRole === 'agent') {
        header('Location: ' . APP_URL . '/portal/agent/dashboard.php');
    } elseif ($targetRole === 'customer') {
        header('Location: ' . APP_URL . '/portal/customer/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/portal/dashboard.php');
    }
    exit;
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white text-center">
                <i class="bi bi-person-bounding-box text-primary display-4 mb-2"></i>
                <h3 class="fw-bold text-dark">Super Admin Role Simulator</h3>
                <p class="text-muted small mb-4">Preview and audit the exact portal experience of any BengalSeva user role with 1-click.</p>

                <div class="row g-3 text-start">
                    <?php
                    $simRoles = [
                        'rider'      => ['Rider / Captain', 'View trip radar, verify passenger OTP & collect cash.', 'scooter', 'warning text-dark'],
                        'vendor'     => ['Vendor / Partner', 'Inspect customer leads, mart orders & wallet balance.', 'shop', 'success'],
                        'accountant' => ['Accountant', 'Review withdrawal requests & settle bank disbursements.', 'bank', 'info text-dark'],
                        'support'    => ['Support Executive', 'View complaints & reply to customer help tickets.', 'headset', 'primary'],
                        'hr'         => ['HR Manager', 'Onboard delivery pilots, telecallers & staff.', 'people', 'danger'],
                        'agent'      => ['Field Ground Agent', 'Onboard offline shopkeepers & earn commissions.', 'briefcase', 'dark'],
                        'customer'   => ['Customer Member', 'Track scheduled appointments, mart orders & rides.', 'person', 'secondary']
                    ];
                    foreach ($simRoles as $rCode => $rMeta):
                    ?>
                        <div class="col-md-6">
                            <div class="card border p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold mb-0 text-dark"><?= e($rMeta[0]) ?></h6>
                                        <i class="bi bi-<?= e($rMeta[2]) ?> fs-5"></i>
                                    </div>
                                    <small class="text-muted d-block mb-3"><?= e($rMeta[1]) ?></small>
                                </div>
                                <a href="role_switch.php?switch_to=<?= e($rCode) ?>" class="btn btn-sm btn-outline-dark fw-bold">
                                    Simulate <?= e($rCode) ?> &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>