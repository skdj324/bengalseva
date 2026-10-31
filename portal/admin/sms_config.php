<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/messenger.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sms_settings'])) {
    $smsProvider = sanitizeInput($_POST['sms_provider'] ?? 'fast2sms');
    $smsApiKey = trim($_POST['sms_api_key'] ?? '');
    $smsSenderId = sanitizeInput($_POST['sms_sender_id'] ?? 'BNGSEV');

    $waProvider = sanitizeInput($_POST['whatsapp_provider'] ?? 'ultramsg');
    $waInstance = trim($_POST['whatsapp_instance_id'] ?? '');
    $waToken = trim($_POST['whatsapp_token'] ?? '');

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'sms_gateway_provider', ':v' => $smsProvider]);
    $up->execute([':k' => 'sms_api_key', ':v' => $smsApiKey]);
    $up->execute([':k' => 'sms_sender_id', ':v' => $smsSenderId]);
    $up->execute([':k' => 'whatsapp_gateway_provider', ':v' => $waProvider]);
    $up->execute([':k' => 'whatsapp_instance_id', ':v' => $waInstance]);
    $up->execute([':k' => 'whatsapp_token', ':v' => $waToken]);

    logAudit($pdo, $user['id'], 'SMS_CONFIG_UPDATED', 'settings', null, "Configured SMS provider: {$smsProvider}");
    setFlash('success', 'SMS & WhatsApp API credentials updated successfully.');
    header('Location: sms_config.php');
    exit;
}

// Handle Test SMS Dispatch
$testMsgStatus = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_sms'])) {
    $testPhone = sanitizeInput($_POST['test_mobile'] ?? '');
    if ($testPhone) {
        $res = Messenger::sendSms($testPhone, "BengalSeva: Your test verification code is 4920. Welcome to BengalSeva platform!", 'test_otp', $user['id']);
        if ($res) {
            setFlash('success', "Test SMS successfully dispatched to {$testPhone}!");
        } else {
            setFlash('danger', "Failed to dispatch test SMS. Please check your API key.");
        }
        header('Location: sms_config.php');
        exit;
    }
}

// Fetch current values
$settingsArr = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$logs = $pdo->query('SELECT * FROM communication_logs ORDER BY id DESC LIMIT 25')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-chat-dots-fill text-primary me-2"></i> SMS &amp; WhatsApp Notifications Gateway</h3>
            <small class="text-muted">Configure Indian DLT SMS (Fast2SMS, Msg91) and WhatsApp Cloud API for automated OTPs &amp; order alerts</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4">
        <!-- Gateway Configuration -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-gear-fill me-1"></i> Gateway API Credentials</h5>
                <form method="POST">
                    <input type="hidden" name="save_sms_settings" value="1">

                    <h6 class="fw-bold text-primary mt-2 mb-2"><i class="bi bi-phone me-1"></i> SMS Gateway (Transactional &amp; OTP)</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">SMS Provider</label>
                        <select name="sms_provider" class="form-select">
                            <option value="fast2sms" <?= ($settingsArr['sms_gateway_provider'] ?? '') === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS India (Quick OTP &amp; Bulk V2)</option>
                            <option value="msg91" <?= ($settingsArr['sms_gateway_provider'] ?? '') === 'msg91' ? 'selected' : '' ?>>Msg91 Enterprise</option>
                            <option value="textlocal" <?= ($settingsArr['sms_gateway_provider'] ?? '') === 'textlocal' ? 'selected' : '' ?>>Textlocal India</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">SMS API Key / Authorization Token</label>
                        <input type="password" name="sms_api_key" class="form-control" value="<?= e($settingsArr['sms_api_key'] ?? '') ?>" placeholder="Enter API Key">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Sender ID / DLT Header</label>
                        <input type="text" name="sms_sender_id" class="form-control" value="<?= e($settingsArr['sms_sender_id'] ?? 'BNGSEV') ?>" placeholder="e.g. BNGSEV">
                    </div>

                    <h6 class="fw-bold text-success mt-3 mb-2"><i class="bi bi-whatsapp me-1"></i> WhatsApp Gateway (Order Updates &amp; Receipts)</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">WhatsApp Provider</label>
                        <select name="whatsapp_provider" class="form-select">
                            <option value="ultramsg" <?= ($settingsArr['whatsapp_gateway_provider'] ?? '') === 'ultramsg' ? 'selected' : '' ?>>UltraMsg / Webhook API</option>
                            <option value="meta_cloud" <?= ($settingsArr['whatsapp_gateway_provider'] ?? '') === 'meta_cloud' ? 'selected' : '' ?>>Meta Official WhatsApp Cloud API</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Instance ID / Phone Number ID</label>
                        <input type="text" name="whatsapp_instance_id" class="form-control" value="<?= e($settingsArr['whatsapp_instance_id'] ?? '') ?>" placeholder="instance...">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Access Token</label>
                        <input type="password" name="whatsapp_token" class="form-control" value="<?= e($settingsArr['whatsapp_token'] ?? '') ?>" placeholder="Access Token">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Save Gateway Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Live SMS Testing Console -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-2"><i class="bi bi-send-check text-success me-1"></i> Live SMS Dispatch Test</h5>
                <p class="text-muted small mb-3">Send a real verification OTP message to verify your gateway integration.</p>

                <form method="POST">
                    <input type="hidden" name="send_test_sms" value="1">
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-light fw-bold">+91</span>
                        <input type="tel" name="test_mobile" class="form-control" placeholder="Enter 10-digit mobile number" pattern="[0-9]{10}" required>
                        <button type="submit" class="btn btn-success fw-bold px-3">Send Test SMS</button>
                    </div>
                </form>
            </div>

            <!-- Recent Dispatch Logs -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-1"></i> Recent Message Dispatch Logs</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Recipient</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">No messages sent yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><strong><?= e($l['recipient']) ?></strong></td>
                                        <td><span class="badge bg-light text-dark border text-uppercase"><?= e($l['channel']) ?></span></td>
                                        <td><span class="badge bg-<?= $l['status'] === 'sent' ? 'success' : 'danger' ?>"><?= strtoupper(e($l['status'])) ?></span></td>
                                        <td class="small text-muted"><?= e(date('h:i A', strtotime($l['created_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>