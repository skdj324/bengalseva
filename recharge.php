<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$service = sanitizeInput($_GET['service'] ?? 'mobile');
$error = null;
$successData = null;

// Handle Bill Payment / Recharge Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_recharge'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Session security token expired. Please retry.';
    } else {
        $sType = sanitizeInput($_POST['service_type'] ?? 'mobile_prepaid');
        $operator = sanitizeInput($_POST['operator_name'] ?? 'Jio');
        $consumer = sanitizeInput($_POST['consumer_number'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0.0);
        $plan = sanitizeInput($_POST['plan_details'] ?? '');

        if (empty($consumer) || $amount <= 0.0) {
            $error = 'Please enter a valid mobile/consumer number and amount.';
        } else {
            $txRef = 'BBPS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $custId = Auth::check() ? (int)Auth::user()['id'] : null;

            $stmt = $pdo->prepare('
                INSERT INTO utility_bills (
                    customer_id, service_type, operator_name, consumer_number,
                    amount, plan_details, transaction_ref, status, payment_status, created_at
                ) VALUES (
                    :cid, :stype, :oper, :cons,
                    :amt, :plan, :ref, "successful", "paid", NOW()
                )
            ');
            $stmt->execute([
                ':cid'   => $custId,
                ':stype' => $sType,
                ':oper'  => $operator,
                ':cons'  => $consumer,
                ':amt'   => $amount,
                ':plan'  => $plan,
                ':ref'   => $txRef
            ]);

            logAudit($pdo, $custId, 'UTILITY_BILL_PAID', 'utility_bills', (int)$pdo->lastInsertId(), "Paid ₹{$amount} for {$operator} ({$consumer})");
            $successData = [
                'ref'    => $txRef,
                'oper'   => $operator,
                'num'    => $consumer,
                'amt'    => $amount,
                'type'   => $sType
            ];
            setFlash('success', "Transaction Successful! ₹{$amount} paid for {$operator}.");
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="py-4" style="background: linear-gradient(135deg, #0b2239 0%, #001529 100%);">
    <div class="container text-white text-center py-2">
        <span class="badge bg-warning text-dark fw-bold mb-2 px-3 py-1">Instant BBPS Utility Payments</span>
        <h2 class="fw-bold mb-1">Mobile Recharge &amp; Electricity Bills</h2>
        <p class="text-white-50 small mb-0">Recharge Jio, Airtel, Vi &bull; Pay WBSEDCL &amp; CESC Kolkata Electricity Bills in seconds</p>
    </div>
</div>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Services Navigation Pills -->
            <ul class="nav nav-pills justify-content-center gap-2 mb-4 bg-white p-2 rounded-4 shadow-sm border">
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $service === 'mobile' ? 'active' : '' ?>" href="recharge.php?service=mobile">
                        <i class="bi bi-phone me-1"></i> Mobile Recharge
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $service === 'electricity' ? 'active' : '' ?>" href="recharge.php?service=electricity">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Electricity Bill (WBSEDCL / CESC)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $service === 'dth' ? 'active' : '' ?>" href="recharge.php?service=dth">
                        <i class="bi bi-tv me-1"></i> DTH &amp; Cable
                    </a>
                </li>
            </ul>

            <?php if ($successData): ?>
                <div class="card border-0 shadow rounded-4 p-4 p-md-5 bg-white text-center mb-4">
                    <i class="bi bi-check-circle-fill text-success display-3 mb-2"></i>
                    <h3 class="fw-bold text-dark">Payment Successful!</h3>
                    <p class="text-muted small">Your recharge/bill payment has been processed instantly.</p>
                    <div class="p-3 bg-light rounded-3 text-start small mb-4">
                        <div><strong>Reference ID:</strong> <?= e($successData['ref']) ?></div>
                        <div><strong>Billed Operator:</strong> <?= e($successData['oper']) ?></div>
                        <div><strong>Account / Mobile:</strong> <?= e($successData['num']) ?></div>
                        <div class="fs-5 fw-bold text-success mt-2">Amount Paid: ₹<?= number_format((float)$successData['amt'], 2) ?></div>
                    </div>
                    <div>
                        <a href="recharge.php" class="btn btn-primary fw-bold px-4">Pay Another Bill</a>
                        <a href="<?= APP_URL ?>/" class="btn btn-outline-secondary fw-bold px-4">Return Home</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="submit_recharge" value="1">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <!-- Mobile Recharge Form -->
                        <?php if ($service === 'mobile'): ?>
                            <input type="hidden" name="service_type" value="mobile_prepaid">
                            <h5 class="fw-bold text-dark mb-3">Prepaid Mobile Recharge</h5>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">10-Digit Mobile Number *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">+91</span>
                                    <input type="tel" name="consumer_number" class="form-control form-control-lg" placeholder="Enter Mobile Number" pattern="[0-9]{10}" required>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Operator</label>
                                    <select name="operator_name" class="form-select" id="mobileOperator">
                                        <option value="Jio Telecom">Reliance Jio</option>
                                        <option value="Airtel">Bharti Airtel</option>
                                        <option value="Vi (Vodafone-Idea)">Vi (Vodafone-Idea)</option>
                                        <option value="BSNL">BSNL Prepaid</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Circle</label>
                                    <input type="text" class="form-control" value="West Bengal &amp; Kolkata" readonly>
                                </div>
                            </div>

                            <!-- Popular Plans Pills -->
                            <label class="form-label small fw-semibold text-muted mb-2">Select Popular Plan</label>
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-3">
                                    <label class="card p-2 border text-center rounded-3 cursor-pointer h-100" onclick="setPlan(299, '28 Days, 1.5GB/Day, Unlimited Calls')">
                                        <strong class="text-primary fs-5">₹299</strong>
                                        <small class="text-muted d-block" style="font-size:0.7rem;">1.5GB/Day &bull; 28D</small>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="card p-2 border text-center rounded-3 cursor-pointer h-100" onclick="setPlan(239, '24 Days, 1.5GB/Day, Unlimited')">
                                        <strong class="text-primary fs-5">₹239</strong>
                                        <small class="text-muted d-block" style="font-size:0.7rem;">1.5GB/Day &bull; 24D</small>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="card p-2 border text-center rounded-3 cursor-pointer h-100" onclick="setPlan(666, '84 Days, 1.5GB/Day, Unlimited')">
                                        <strong class="text-primary fs-5">₹666</strong>
                                        <small class="text-muted d-block" style="font-size:0.7rem;">1.5GB/Day &bull; 84D</small>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="card p-2 border text-center rounded-3 cursor-pointer h-100" onclick="setPlan(19, '1GB Data Booster')">
                                        <strong class="text-primary fs-5">₹19</strong>
                                        <small class="text-muted d-block" style="font-size:0.7rem;">1GB Data Pack</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted">Recharge Amount (₹) *</label>
                                <input type="number" name="amount" id="rechargeAmount" class="form-control form-control-lg fw-bold text-primary" placeholder="Enter amount or pick plan above" required>
                                <input type="hidden" name="plan_details" id="planDetails" value="Unlimited Plan">
                            </div>

                        <!-- Electricity Bill Form (WBSEDCL & CESC) -->
                        <?php elseif ($service === 'electricity'): ?>
                            <input type="hidden" name="service_type" value="electricity">
                            <h5 class="fw-bold text-dark mb-3">West Bengal Electricity Bill Payment</h5>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Electricity Board *</label>
                                <select name="operator_name" class="form-select form-select-lg" required>
                                    <option value="WBSEDCL (West Bengal State Electricity)">WBSEDCL - West Bengal State Electricity (All 23 Districts)</option>
                                    <option value="CESC Limited Kolkata">CESC Limited - Kolkata &amp; Howrah Metro</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Consumer ID / CA Number (9 Digits) *</label>
                                <input type="text" name="consumer_number" class="form-control form-control-lg font-monospace" placeholder="e.g. 102938475" required>
                                <small class="text-muted">Find this on your physical electricity bill or SMS notification.</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted">Bill Amount to Pay (₹) *</label>
                                <input type="number" step="1" name="amount" class="form-control form-control-lg fw-bold text-primary" placeholder="e.g. 1450" required>
                            </div>

                        <!-- DTH Form -->
                        <?php else: ?>
                            <input type="hidden" name="service_type" value="dth">
                            <h5 class="fw-bold text-dark mb-3">DTH &amp; Cable TV Recharge</h5>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">DTH Operator</label>
                                <select name="operator_name" class="form-select">
                                    <option value="Tata Play (Tata Sky)">Tata Play</option>
                                    <option value="Airtel Digital TV">Airtel Digital TV</option>
                                    <option value="Dish TV">Dish TV</option>
                                    <option value="Sun Direct">Sun Direct</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Subscriber ID / Smartcard Number</label>
                                <input type="text" name="consumer_number" class="form-control form-control-lg" placeholder="Enter Subscriber ID" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted">Recharge Amount (₹)</label>
                                <input type="number" name="amount" class="form-control form-control-lg" placeholder="Amount" required>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                            <i class="bi bi-shield-lock-fill me-1"></i> Proceed to Pay Securely
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function setPlan(amt, desc) {
    document.getElementById('rechargeAmount').value = amt;
    document.getElementById('planDetails').value = desc;
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>