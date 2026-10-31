<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(SYSTEM_ROLES);
$user = Auth::user();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $mobile = sanitizeInput($_POST['mobile'] ?? '');

        if ($fullName) {
            $pdo->prepare('UPDATE users SET full_name = :n, mobile = :m, updated_at = NOW() WHERE id = :id')
                ->execute([':n' => $fullName, ':m' => $mobile, ':id' => $user['id']]);
            $_SESSION['user_name'] = $fullName;
            setFlash('success', 'Profile details updated successfully!');
            header('Location: profile.php');
            exit;
        }
    } elseif (isset($_POST['change_password'])) {
        $currPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        $chk = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $chk->execute([':id' => $user['id']]);
        $hash = $chk->fetchColumn();

        if (password_verify($currPass, (string)$hash)) {
            if (strlen($newPass) >= 6) {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute([':h' => $newHash, ':id' => $user['id']]);
                setFlash('success', 'Password changed successfully!');
            } else {
                $error = 'New password must be at least 6 characters.';
            }
        } else {
            $error = 'Current password does not match.';
        }
    }
}

// Fetch current user details
$uStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$uStmt->execute([':id' => $user['id']]);
$userData = $uStmt->fetch();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-3" style="width:60px; height:60px;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0"><?= e($userData['full_name']) ?></h4>
                        <small class="text-muted"><?= e($userData['email']) ?> &bull; <span class="badge bg-light text-dark border text-uppercase"><?= e($userData['role']) ?></span></small>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <!-- Profile Form -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="update_profile" value="1">
                    <h5 class="fw-bold text-dark mb-3">Personal Information</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($userData['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Number</label>
                            <input type="tel" name="mobile" class="form-control" value="<?= e($userData['mobile'] ?? '') ?>" placeholder="10 Digits">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Email (Cannot be changed)</label>
                            <input type="email" class="form-control bg-light" value="<?= e($userData['email']) ?>" readonly>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Update Profile Details</button>
                </form>

                <hr class="my-4">

                <!-- Password Change Form -->
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <h5 class="fw-bold text-dark mb-3">Security &amp; Password</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">New Password (Min 6 chars)</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark fw-bold px-4">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>