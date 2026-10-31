<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Gateway Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateways'])) {
    $code = sanitizeInput($_POST['gateway_code'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isSandbox = isset($_POST['is_sandbox']) ? 1 : 0;
    $apiKey = trim($_POST['api_key'] ?? '');
    $apiSecret = trim($_POST['api_secret'] ?? '');
    $merchantId = trim($_POST['merchant_id'] ?? '');
    $saltKey = trim($_POST['salt_key'] ?? '');
    $saltIndex = trim($_POST['salt_index'] ?? '1');

    $stmt = $pdo->prepare('
        UPDATE payment_gateways 
        SET is_active = :act, is_sandbox = :sb, api_key = :key, api_secret = :sec, 
            merchant_id = :mid, salt_key = :skey, salt_index = :sidx 
        WHERE gateway_code = :code
    ');
    $stmt->execute([
        ':act'  => $isActive,
        ':sb'   => $isSandbox,
        ':key'  => $apiKey ?: null,
        ':sec'  => $apiSecret ?: null,
        ':mid'  => $merchantId ?: null,
        ':skey' => $saltKey ?: null,
        ':sidx' => $saltIndex,
        ':code' => $code
    ]);

    logAudit($pdo, $user['id'], 'PAYMENT_GATEWAY_CONFIG', 'payment_gateways', null, "Configured gateway: {$code}");
    setFlash('success', "Payment gateway '{$code}' updated successfully.");
    header('Location: gateways.php');
    exit;
}

$gateways = $pdo->query('SELECT * FROM payment_gateways ORDER BY id ASC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-credit-card-2-front-fill text-success me-2"></i> Payment Gateway Master</h3>
            <small class="text-muted">Manage Razorpay, PhonePe, Cashfree, PayU Money, UPI QR and Cash on Delivery</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <div class="row g-4">
        <?php foreach ($gateways as $gw): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-5 fw-bold text-dark"><?= e($gw['title']) ?></span>
                        <span class="badge bg-<?= $gw['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $gw['is_active'] ? 'ACTIVE' : 'DISABLED' ?>
                        </span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="save_gateways" value="1">
                        <input type="hidden" name="gateway_code" value="<?= e($gw['gateway_code']) ?>">

                        <div class="d-flex gap-4 mb-3 p-3 bg-light rounded-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="act_<?= e($gw['gateway_code']) ?>" <?= $gw['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="act_<?= e($gw['gateway_code']) ?>">Enable Gateway</label>
                            </div>
                            <?php if ($gw['gateway_code'] !== 'cod' && $gw['gateway_code'] !== 'upi_qr'): ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_sandbox" id="sb_<?= e($gw['gateway_code']) ?>" <?= $gw['is_sandbox'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold small text-warning" for="sb_<?= e($gw['gateway_code']) ?>">Sandbox / Test Mode</label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($gw['gateway_code'] === 'razorpay'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Razorpay Key ID</label>
                                <input type="text" name="api_key" class="form-control" value="<?= e($gw['api_key'] ?? '') ?>" placeholder="rzp_live_...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Razorpay Key Secret</label>
                                <input type="password" name="api_secret" class="form-control" value="<?= e($gw['api_secret'] ?? '') ?>" placeholder="Key Secret">
                            </div>

                        <?php elseif ($gw['gateway_code'] === 'phonepe'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">PhonePe Merchant ID</label>
                                <input type="text" name="merchant_id" class="form-control" value="<?= e($gw['merchant_id'] ?? '') ?>" placeholder="Merchant ID">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Salt Key</label>
                                <input type="password" name="salt_key" class="form-control" value="<?= e($gw['salt_key'] ?? '') ?>" placeholder="Salt Key">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Salt Index</label>
                                <input type="text" name="salt_index" class="form-control" value="<?= e($gw['salt_index'] ?? '1') ?>">
                            </div>

                        <?php elseif ($gw['gateway_code'] === 'cashfree'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Cashfree App ID (Client ID)</label>
                                <input type="text" name="api_key" class="form-control" value="<?= e($gw['api_key'] ?? '') ?>" placeholder="CF... / TEST_APP_ID">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Cashfree Secret Key</label>
                                <input type="password" name="api_secret" class="form-control" value="<?= e($gw['api_secret'] ?? '') ?>" placeholder="cfsk_...">
                            </div>

                        <?php elseif ($gw['gateway_code'] === 'payu'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">PayU Merchant Key</label>
                                <input type="text" name="api_key" class="form-control" value="<?= e($gw['api_key'] ?? '') ?>" placeholder="Merchant Key">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">PayU Merchant Salt</label>
                                <input type="password" name="api_secret" class="form-control" value="<?= e($gw['api_secret'] ?? '') ?>" placeholder="Merchant Salt">
                            </div>

                        <?php elseif ($gw['gateway_code'] === 'upi_qr'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Direct UPI ID / VPA</label>
                                <input type="text" name="api_key" class="form-control" value="<?= e($gw['api_key'] ?? 'bengalseva@upi') ?>">
                            </div>
                        <?php else: ?>
                            <p class="text-muted small">Cash on Delivery / Cash on Drop requires no external API keys.</p>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-dark w-100 fw-bold py-2 rounded-3 mt-2">
                            Save <?= e($gw['title']) ?> Settings
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>