<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_b2b_settings'])) {
    $mode = sanitizeInput($_POST['b2b_recharge_mode'] ?? 'automatic');
    $recProvider = sanitizeInput($_POST['b2b_recharge_provider'] ?? 'paysprint');
    $recKey = trim($_POST['b2b_recharge_api_key'] ?? '');
    $partnerId = trim($_POST['b2b_recharge_partner_id'] ?? '');

    $travProvider = sanitizeInput($_POST['b2b_travel_provider'] ?? 'redbus');
    $travKey = trim($_POST['b2b_travel_api_key'] ?? '');

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'b2b_recharge_mode', ':v' => $mode]);
    $up->execute([':k' => 'b2b_recharge_provider', ':v' => $recProvider]);
    $up->execute([':k' => 'b2b_recharge_api_key', ':v' => $recKey]);
    $up->execute([':k' => 'b2b_recharge_partner_id', ':v' => $partnerId]);
    $up->execute([':k' => 'b2b_travel_provider', ':v' => $travProvider]);
    $up->execute([':k' => 'b2b_travel_api_key', ':v' => $travKey]);

    logAudit($pdo, $user['id'], 'B2B_API_CONFIG_UPDATED', 'settings', null, "Configured B2B execution mode to: {$mode}");
    setFlash('success', 'B2B Automation settings saved! System will execute recharges in ' . strtoupper($mode) . ' mode.');
    header('Location: api_integrations.php');
    exit;
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$activeMode = $settings['b2b_recharge_mode'] ?? 'automatic';

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-robot text-primary me-2"></i> B2B API Automation Hub</h3>
            <small class="text-muted">Automate Mobile Recharge, WB Electricity (BBPS), and Bus/Flight Ticketing APIs without manual work</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <form method="POST">
                    <input type="hidden" name="save_b2b_settings" value="1">

                    <!-- Execution Mode: Automatic vs Manual -->
                    <div class="p-3 bg-light rounded-4 mb-4 border border-2 <?= $activeMode === 'automatic' ? 'border-success' : 'border-warning' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-cpu-fill text-success me-1"></i> System Execution Mode</h5>
                            <span class="badge bg-<?= $activeMode === 'automatic' ? 'success' : 'warning text-dark' ?> fs-6">
                                <?= strtoupper($activeMode) ?> DISPATCH
                            </span>
                        </div>
                        <p class="text-muted small mb-3">Admin ko manually karna hai ya system apne aap karega:</p>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="card p-3 border rounded-3 cursor-pointer h-100 <?= $activeMode === 'automatic' ? 'bg-white border-success shadow-sm' : '' ?>" style="cursor: pointer;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="b2b_recharge_mode" value="automatic" <?= $activeMode === 'automatic' ? 'checked' : '' ?>>
                                        <strong class="d-block text-success mt-1">100% AUTOMATIC (Recommended)</strong>
                                        <small class="text-muted d-block">Customer pays &rarr; API executes instantly in 2 seconds &rarr; Customer gets receipt automatically. (Zero Admin Work).</small>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="card p-3 border rounded-3 cursor-pointer h-100 <?= $activeMode === 'manual' ? 'bg-white border-warning shadow-sm' : '' ?>" style="cursor: pointer;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="b2b_recharge_mode" value="manual" <?= $activeMode === 'manual' ? 'checked' : '' ?>>
                                        <strong class="d-block text-dark mt-1">MANUAL QUEUE</strong>
                                        <small class="text-muted d-block">Admin/Staff reviews customer payment first, then manually executes the recharge or ticket.</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- B2B Provider 1: Recharge & BBPS Electricity Bills -->
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Mobile Recharge &amp; BBPS Electricity API</h5>
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">B2B Provider</label>
                            <select name="b2b_recharge_provider" class="form-select">
                                <option value="paysprint">PaySprint India (Full BBPS, WBSEDCL &amp; Mobile Recharges)</option>
                                <option value="roundpay">Roundpay B2B Recharge API</option>
                                <option value="mahagram">Mahagram / Ezetap BBPS Gateway</option>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-semibold text-muted">API Partner Key</label>
                                <input type="password" name="b2b_recharge_api_key" class="form-control" value="<?= e($settings['b2b_recharge_api_key'] ?? '') ?>" placeholder="Enter Provider API Key">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-semibold text-muted">Partner / Merchant ID</label>
                                <input type="text" name="b2b_recharge_partner_id" class="form-control" value="<?= e($settings['b2b_recharge_partner_id'] ?? '') ?>" placeholder="PARTNER_101">
                            </div>
                        </div>
                        <small class="text-muted">Supports Jio, Airtel, Vi, BSNL, WBSEDCL &amp; CESC Kolkata bills.</small>
                    </div>

                    <!-- B2B Provider 2: Bus & Flight Ticketing API -->
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-ticket-perforated-fill text-info me-1"></i> Bus &amp; Flight B2B Ticketing API</h5>
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Ticketing Provider</label>
                            <select name="b2b_travel_provider" class="form-select">
                                <option value="redbus">RedBus B2B Partner API (SBSTC, Volvo, Private Buses)</option>
                                <option value="abhibus">AbhiBus Operator API</option>
                                <option value="tbo">TBO (Travel Boutique Online) Flight API</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">API Access Token / Secret</label>
                            <input type="password" name="b2b_travel_api_key" class="form-control" value="<?= e($settings['b2b_travel_api_key'] ?? '') ?>" placeholder="Enter Travel API Token">
                        </div>
                        <small class="text-muted">Enables real-time PNR generation and live seat inventory.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save B2B Automation Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>