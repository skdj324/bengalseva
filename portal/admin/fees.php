<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_settings'])) {
    $enabled = isset($_POST['platform_fee_enabled']) ? '1' : '0';
    $feeType = sanitizeInput($_POST['platform_fee_type'] ?? 'fixed');
    $amount = (float)($_POST['platform_fee_amount'] ?? 5.0);
    $label = sanitizeInput($_POST['platform_fee_label'] ?? 'Platform Convenience Fee');

    $applyOrders = isset($_POST['fee_apply_orders']) ? '1' : '0';
    $applyRides = isset($_POST['fee_apply_rides']) ? '1' : '0';
    $applyBookings = isset($_POST['fee_apply_bookings']) ? '1' : '0';
    $applyRecharges = isset($_POST['fee_apply_recharges']) ? '1' : '0';

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'platform_fee_enabled', ':v' => $enabled]);
    $up->execute([':k' => 'platform_fee_type', ':v' => $feeType]);
    $up->execute([':k' => 'platform_fee_amount', ':v' => (string)$amount]);
    $up->execute([':k' => 'platform_fee_label', ':v' => $label]);
    $up->execute([':k' => 'fee_apply_orders', ':v' => $applyOrders]);
    $up->execute([':k' => 'fee_apply_rides', ':v' => $applyRides]);
    $up->execute([':k' => 'fee_apply_bookings', ':v' => $applyBookings]);
    $up->execute([':k' => 'fee_apply_recharges', ':v' => $applyRecharges]);

    logAudit($pdo, $user['id'], 'PLATFORM_FEES_UPDATED', 'settings', null, "Configured {$label}: {$amount} ({$feeType})");
    setFlash('success', 'Customer platform fee configuration saved successfully!');
    header('Location: fees.php');
    exit;
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-cash-coin text-success me-2"></i> Customer Extra Platform Fee Master</h3>
            <small class="text-muted">Configure convenience fees, handling charges, and platform surcharges added to customer bills</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <form method="POST">
                    <input type="hidden" name="save_fee_settings" value="1">

                    <!-- Master Activation Switch -->
                    <div class="p-3 bg-light rounded-4 mb-4 border border-2 <?= ($settings['platform_fee_enabled'] ?? '1') === '1' ? 'border-success' : 'border-secondary' ?>">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input fs-5" type="checkbox" name="platform_fee_enabled" id="feeSwitch" <?= ($settings['platform_fee_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fs-5 fw-bold text-dark ms-2" for="feeSwitch">
                                Enable Extra Platform / Convenience Fee from Customers
                            </label>
                        </div>
                        <small class="text-muted ms-4 d-block">When enabled, this charge is automatically added to the customer's grand total at checkout.</small>
                    </div>

                    <!-- Fee Structure Settings -->
                    <h5 class="fw-bold text-primary mb-3">Fee Amount &amp; Display Label</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Fee Calculation Type</label>
                            <select name="platform_fee_type" class="form-select" id="feeTypeSelect">
                                <option value="fixed" <?= ($settings['platform_fee_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Fixed Flat Amount (e.g. ₹5 / ₹10 per order)</option>
                                <option value="percent" <?= ($settings['platform_fee_type'] ?? 'fixed') === 'percent' ? 'selected' : '' ?>>Percentage % (e.g. 2% of bill)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Fee Amount (<span id="feeUnitSymbol">₹</span>)</label>
                            <input type="number" step="0.5" name="platform_fee_amount" class="form-control fw-bold fs-6 text-primary" value="<?= e($settings['platform_fee_amount'] ?? '5.00') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Customer Bill Label (What customer sees on invoice)</label>
                            <input type="text" name="platform_fee_label" class="form-control" value="<?= e($settings['platform_fee_label'] ?? 'Platform Convenience Fee') ?>" placeholder="e.g. Platform Fee, Technology &amp; Handling Charge" required>
                        </div>
                    </div>

                    <!-- Services Applicability Checkboxes -->
                    <h5 class="fw-bold text-primary mb-3">Apply Fee to Selected Services</h5>
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fee_apply_orders" id="appOrders" <?= ($settings['fee_apply_orders'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="appOrders">E-Commerce &amp; Mart Orders</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fee_apply_rides" id="appRides" <?= ($settings['fee_apply_rides'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="appRides">Rapido Rides &amp; Cabs</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fee_apply_bookings" id="appBook" <?= ($settings['fee_apply_bookings'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="appBook">Doctor &amp; Clinic Appointments</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fee_apply_recharges" id="appRech" <?= ($settings['fee_apply_recharges'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="appRech">Mobile Recharge &amp; Bills</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Platform Fee Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('feeTypeSelect').addEventListener('change', function() {
    document.getElementById('feeUnitSymbol').innerText = (this.value === 'percent') ? '%' : '₹';
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>