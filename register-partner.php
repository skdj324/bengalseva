<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$success = null;

// Fetch Categories & Districts for registration form
$categories = $pdo->query('SELECT id, name FROM categories WHERE status = "active" ORDER BY name ASC')->fetchAll();
$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $mobile = sanitizeInput($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $businessName = sanitizeInput($_POST['business_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $city = sanitizeInput($_POST['city'] ?? '');
        $pincode = sanitizeInput($_POST['pincode'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        if (empty($fullName) || empty($email) || empty($mobile) || empty($password) || empty($businessName) || !$categoryId || !$districtId) {
            $error = 'Please fill in all mandatory fields marked with an asterisk (*).';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            // Check for duplicate email or mobile
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email OR mobile = :mobile LIMIT 1');
            $check->execute([':email' => $email, ':mobile' => $mobile]);
            if ($check->fetch()) {
                $error = 'An account with this email address or mobile number already exists.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Insert into `users` table with role 'vendor' and status 'active'
                    $userInsert = $pdo->prepare('
                        INSERT INTO users (full_name, email, mobile, password_hash, role, status, created_at)
                        VALUES (:name, :email, :mobile, :hash, "vendor", "active", NOW())
                    ');
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $userInsert->execute([
                        ':name'   => $fullName,
                        ':email'  => $email,
                        ':mobile' => $mobile,
                        ':hash'   => $hash
                    ]);
                    $userId = (int)$pdo->lastInsertId();

                    // Generate unique slug
                    $slugBase = preg_replace('/[^a-z0-9]+/i', '-', strtolower($businessName));
                    $slug = $slugBase . '-' . $userId;

                    // 2. Insert into `vendor_profiles` table with status 'pending' (Awaiting Admin KYC)
                    $vendorInsert = $pdo->prepare('
                        INSERT INTO vendor_profiles (
                            user_id, category_id, business_name, slug, description,
                            mobile, email, address, city, district_id, pincode, status, created_at
                        ) VALUES (
                            :user_id, :cat_id, :b_name, :slug, :desc,
                            :mob, :email, :addr, :city, :dist_id, :pin, "pending", NOW()
                        )
                    ');
                    $vendorInsert->execute([
                        ':user_id' => $userId,
                        ':cat_id'  => $categoryId,
                        ':b_name'  => $businessName,
                        ':slug'    => $slug,
                        ':desc'    => $description,
                        ':mob'     => $mobile,
                        ':email'   => $email,
                        ':addr'    => $address,
                        ':city'    => $city,
                        ':dist_id' => $districtId,
                        ':pin'     => $pincode
                    ]);

                    logAudit($pdo, $userId, 'VENDOR_REGISTERED', 'vendor_profiles', (int)$pdo->lastInsertId(), 'New business onboarded: ' . $businessName);

                    $pdo->commit();
                    setFlash('success', 'Registration submitted successfully! Your account is active. Please login to access your vendor dashboard.');
                    header('Location: ' . APP_URL . '/login.php');
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('[Vendor Register Error] ' . $e->getMessage());
                    $error = 'An error occurred during registration. Please try again.';
                }
            }
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-briefcase-fill text-warning fs-1"></i>
                        <div>
                            <h3 class="fw-bold mb-1">Join BengalSeva as a Verified Business</h3>
                            <div class="text-white-50 small">Grow your clientele across 23 districts of West Bengal</div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 mb-4"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="register-partner.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-person-fill me-1"></i> Owner & Account Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Owner Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Account Email Address *</label>
                                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Mobile Number (10 Digits) *</label>
                                <input type="tel" name="mobile" class="form-control" pattern="[0-9]{10}" required value="<?= e($_POST['mobile'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Account Password *</label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>
                        </div>

                        <hr class="my-4 text-muted">

                        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-building-fill me-1"></i> Business & Location Profile</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold small">Business / Shop / Professional Name *</label>
                                <input type="text" name="business_name" class="form-control" required value="<?= e($_POST['business_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Primary Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">District *</label>
                                <select name="district_id" class="form-select" required>
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $dist): ?>
                                        <option value="<?= (int)$dist['id'] ?>" <?= (isset($_POST['district_id']) && (int)$_POST['district_id'] === (int)$dist['id']) ? 'selected' : '' ?>>
                                            <?= e($dist['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">City / Town / Area *</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Salt Lake / Asansol" required value="<?= e($_POST['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Postal PIN Code</label>
                                <input type="text" name="pincode" class="form-control" maxlength="6" value="<?= e($_POST['pincode'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold small">Full Business Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Premises, street, landmark..." value="<?= e($_POST['address'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold small">Brief Description of Services Offered</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Tell customers about your experience, certifications, and service area..."><?= e($_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary fw-bold py-2 px-5 fs-5 rounded-3">
                            Register &amp; Open Partner Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
