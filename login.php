<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . APP_URL . '/portal/dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($token)) {
        $error = 'Security token expired. Please refresh the page.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in both email and password.';
    } else {
        if (Auth::attempt($pdo, $email, $password)) {
            setFlash('success', 'Welcome back to BengalSeva!');
            header('Location: ' . APP_URL . '/portal/dashboard.php');
            exit;
        } else {
            $error = __('invalid_creds');
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill text-primary display-4"></i>
                        <h4 class="fw-bold mt-2"><?= __('login') ?></h4>
                        <small class="text-muted">BengalSeva Unified Multi-Role Authentication</small>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold small"><?= __('email_address') ?></label>
                            <input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small"><?= __('password') ?></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> <?= __('login_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
