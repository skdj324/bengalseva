<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['vendor', 'provider', 'rider', 'super_admin', 'admin']);
$user = Auth::user();

// Ensure Wallet Record Exists
$wStmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = :uid LIMIT 1');
$wStmt->execute([':uid' => $user['id']]);
$wallet = $wStmt->fetch();

if (!$wallet) {
    $pdo->prepare('INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES (:uid, 1250.00, 1250.00, 0.00)')->execute([':uid' => $user['id']]);
    $wallet = ['balance' => 1250.00, 'total_earned' => 1250.00, 'total_withdrawn' => 0.00];
}

// Handle Payout Request
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = (float)($_POST['amount'] ?? 0.0);
    $method = sanitizeInput($_POST['payout_method'] ?? 'upi');
    $details = sanitizeInput($_POST['payout_details'] ?? '');

    if ($amount < 200.0) {
        $error = 'Minimum withdrawal amount is ₹200.';
    } elseif ($amount > (float)$wallet['balance']) {
        $error = 'Insufficient wallet balance.';
    } elseif (empty($details)) {
        $error = 'Please provide valid UPI ID or Bank Account Details.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO payout_requests (user_id, role, amount, payout_method, payout_details, status, created_at)
            VALUES (:uid, :role, :amt, :method, :details, "pending", NOW())
        ');
        $stmt->execute([
            ':uid'     => $user['id'],
            ':role'    => $user['role'],
            ':amt'     => $amount,
            ':method'  => $method,
            ':details' => $details
        ]);

        setFlash('success', "Withdrawal request for ₹{$amount} submitted! Company accountant will disburse within 24 hours.");
        header('Location: wallet.php');
        exit;
    }
}

// Fetch Withdrawal History
$pStmt = $pdo->prepare('SELECT * FROM payout_requests WHERE user_id = :uid ORDER BY id DESC');
$pStmt->execute([':uid' => $user['id']]);
$myPayouts = $pStmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-wallet2 text-success me-2"></i> Partner Earnings &amp; Wallet</h3>
            <small class="text-muted">Track your live trip/order commissions and withdraw funds directly</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <!-- Balance Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-success border-4">
                <div class="text-muted small fw-semibold">AVAILABLE BALANCE</div>
                <div class="display-6 fw-bold text-success">₹<?= number_format((float)$wallet['balance'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-primary border-4">
                <div class="text-muted small fw-semibold">LIFETIME EARNINGS</div>
                <div class="display-6 fw-bold text-dark">₹<?= number_format((float)$wallet['total_earned'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-info border-4">
                <div class="text-muted small fw-semibold">TOTAL WITHDRAWN</div>
                <div class="display-6 fw-bold text-secondary">₹<?= number_format((float)$wallet['total_withdrawn'], 2) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Withdrawal Form -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-up-right-circle text-primary me-2"></i> Request Payout Withdrawal</h5>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="request_withdrawal" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Withdrawal Amount (₹)</label>
                        <input type="number" step="10" name="amount" class="form-control" max="<?= (float)$wallet['balance'] ?>" placeholder="Min ₹200" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Payout Method</label>
                        <select name="payout_method" class="form-select">
                            <option value="upi">Instant UPI (GPay / PhonePe / Paytm)</option>
                            <option value="bank_transfer">Direct Bank NEFT / IMPS</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">UPI ID or Bank Details (A/C No &amp; IFSC)</label>
                        <input type="text" name="payout_details" class="form-control" placeholder="e.g. 98300XXXXX@upi or SBIN0001234..." required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Submit Withdrawal Request
                    </button>
                </form>
            </div>
        </div>

        <!-- Withdrawal History Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark">Payout History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>ID</th>
                                <th>Amount</th>
                                <th>Target</th>
                                <th>Status</th>
                                <th>UTR Reference</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myPayouts)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No withdrawal history yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myPayouts as $mp): ?>
                                    <tr>
                                        <td>#<?= (int)$mp['id'] ?></td>
                                        <td class="fw-bold text-dark">₹<?= number_format((float)$mp['amount'], 2) ?></td>
                                        <td><code class="small text-dark"><?= e($mp['payout_details']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $mp['status'] === 'approved' ? 'success' : ($mp['status'] === 'pending' ? 'warning text-dark' : 'danger') ?> text-uppercase">
                                                <?= e($mp['status']) ?>
                                            </span>
                                        </td>
                                        <td><small class="text-muted font-monospace"><?= e($mp['bank_reference'] ?? 'Pending') ?></small></td>
                                        <td class="small text-muted"><?= e(date('d M Y', strtotime($mp['created_at']))) ?></td>
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