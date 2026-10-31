<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/permissions.php';

Auth::requireRole(['super_admin', 'admin', 'accountant', 'manager']);
$user = Auth::user();

// 1. Handle "PAY TO ALL" Bulk Settlement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_to_all_pending'])) {
    $pendingStmt = $pdo->query('SELECT * FROM payout_requests WHERE status = "pending"');
    $pendingList = $pendingStmt->fetchAll();

    if (empty($pendingList)) {
        setFlash('info', 'There are no pending payout requests to settle.');
        header('Location: dashboard.php');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $batchUtr = 'BATCH-UTR-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $totalSettledAmt = 0.0;
        $settledCount = 0;

        $upPayout = $pdo->prepare('
            UPDATE payout_requests 
            SET status = "approved", bank_reference = :utr, processed_by = :pby, processed_at = NOW() 
            WHERE id = :id
        ');

        $deductWallet = $pdo->prepare('
            UPDATE wallets 
            SET balance = balance - :amt, total_withdrawn = total_withdrawn + :amt 
            WHERE user_id = :uid
        ');

        $insNotif = $pdo->prepare('
            INSERT INTO notifications (user_id, title, message, link_url, created_at)
            VALUES (:uid, "Payout Disbursed (Batch)", :msg, "/portal/vendor/wallet.php", NOW())
        ');

        foreach ($pendingList as $pReq) {
            $amt = (float)$pReq['amount'];
            $reqId = (int)$pReq['id'];
            $beneficiaryUid = (int)$pReq['user_id'];

            $upPayout->execute([':utr' => $batchUtr, ':pby' => $user['id'], ':id' => $reqId]);
            $deductWallet->execute([':amt' => $amt, ':uid' => $beneficiaryUid]);
            $insNotif->execute([
                ':uid' => $beneficiaryUid,
                ':msg' => "Your withdrawal of ₹{$amt} has been paid via batch disbursement. Ref: {$batchUtr}"
            ]);

            $totalSettledAmt += $amt;
            $settledCount++;
        }

        logAudit($pdo, $user['id'], 'BULK_PAYOUT_SETTLED', 'payout_requests', null, "Disbursed {$settledCount} payouts totaling ₹{$totalSettledAmt}. Batch: {$batchUtr}");
        $pdo->commit();

        setFlash('success', "PAY TO ALL EXECUTED! Successfully settled {$settledCount} withdrawal requests totaling ₹" . number_format($totalSettledAmt, 2) . " with Batch UTR: {$batchUtr}");
        header('Location: dashboard.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[Bulk Payout Error] ' . $e->getMessage());
        setFlash('danger', 'Error processing bulk payout: ' . $e->getMessage());
        header('Location: dashboard.php');
        exit;
    }
}

// 2. Handle Single Payout Approval / Settle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_payout'])) {
    $reqId = (int)($_POST['payout_id'] ?? 0);
    $action = sanitizeInput($_POST['action_type'] ?? 'approve');
    $utr = sanitizeInput($_POST['bank_reference'] ?? '');

    $chk = $pdo->prepare('SELECT * FROM payout_requests WHERE id = :id AND status = "pending"');
    $chk->execute([':id' => $reqId]);
    $pReq = $chk->fetch();

    if ($pReq) {
        if ($action === 'approve') {
            $up = $pdo->prepare('
                UPDATE payout_requests 
                SET status = "approved", bank_reference = :utr, processed_by = :pby, processed_at = NOW() 
                WHERE id = :id
            ');
            $up->execute([
                ':utr' => $utr ?: 'UTR-' . time(),
                ':pby' => $user['id'],
                ':id'  => $reqId
            ]);

            $pdo->prepare('
                UPDATE wallets 
                SET balance = balance - :amt, total_withdrawn = total_withdrawn + :amt 
                WHERE user_id = :uid
            ')->execute([':amt' => $pReq['amount'], ':uid' => $pReq['user_id']]);

            setFlash('success', "Payout #{$reqId} approved and marked as settled!");
        } else {
            $pdo->prepare('UPDATE payout_requests SET status = "rejected", processed_by = :pby, processed_at = NOW() WHERE id = :id')
                ->execute([':pby' => $user['id'], ':id' => $reqId]);
            setFlash('info', "Payout request #{$reqId} marked as rejected.");
        }
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch Payouts
$payouts = $pdo->query('
    SELECT pr.*, u.full_name as beneficiary_name, u.email as beneficiary_email, u.mobile as beneficiary_mobile 
    FROM payout_requests pr 
    JOIN users u ON pr.user_id = u.id 
    ORDER BY pr.id DESC LIMIT 100
')->fetchAll();

$pendingPayoutsTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE status = 'pending'")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM payout_requests WHERE status = 'pending'")->fetchColumn();
$settledPayoutsTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE status = 'approved'")->fetchColumn();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-bank text-success me-2"></i> Accountant Settlement Desk</h3>
            <small class="text-muted">Review, verify and disburse vendor &amp; rider withdrawal settlements</small>
        </div>
        <div class="d-flex gap-2">
            <!-- MASTER PAY TO ALL BUTTON -->
            <?php if ($pendingCount > 0): ?>
                <form method="POST" onsubmit="return confirm('PAY TO ALL CONFIRMATION: Are you sure you want to approve & disburse all <?= $pendingCount ?> pending payouts totaling ₹<?= number_format($pendingPayoutsTotal, 2) ?> in a single batch?');">
                    <input type="hidden" name="pay_to_all_pending" value="1">
                    <button type="submit" class="btn btn-danger fw-bold shadow-sm d-flex align-items-center gap-1">
                        <i class="bi bi-lightning-charge-fill"></i> Pay to All Pending (<?= $pendingCount ?>)
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= APP_URL ?>/portal/admin/fees.php" class="btn btn-outline-success fw-bold btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-cash-coin"></i> Customer Extra Fees
            </a>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <!-- Settlement Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-warning border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">PENDING WITHDRAWALS (<?= $pendingCount ?>)</div>
                        <div class="display-6 fw-bold text-dark">₹<?= number_format($pendingPayoutsTotal, 2) ?></div>
                    </div>
                    <i class="bi bi-hourglass-split fs-1 text-warning opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">TOTAL SETTLED DISBURSEMENTS</div>
                        <div class="display-6 fw-bold text-success">₹<?= number_format($settledPayoutsTotal, 2) ?></div>
                    </div>
                    <i class="bi bi-check-circle-fill fs-1 text-success opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Requests Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-wallet2 text-primary me-2"></i> Payout Settlement Queue</h5>
            <span class="badge bg-light text-dark border"><?= count($payouts) ?> Total Requests</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Req #</th>
                        <th>Beneficiary</th>
                        <th>Role</th>
                        <th>Amount</th>
                        <th>Payout Mode &amp; Details</th>
                        <th>Status</th>
                        <th>UTR / Reference</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payouts)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No withdrawal requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payouts as $pr): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$pr['id'] ?></td>
                                <td>
                                    <strong><?= e($pr['beneficiary_name']) ?></strong>
                                    <div class="small text-muted"><?= e($pr['beneficiary_mobile'] ?? '') ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border text-uppercase"><?= e($pr['role']) ?></span></td>
                                <td class="fw-bold text-primary fs-6">₹<?= number_format((float)$pr['amount'], 2) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= strtoupper(e($pr['payout_method'])) ?></div>
                                    <code class="small text-dark"><?= e($pr['payout_details']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $pr['status'] === 'approved' ? 'success' : ($pr['status'] === 'pending' ? 'warning text-dark' : 'danger') ?> text-uppercase">
                                        <?= e($pr['status']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted font-monospace"><?= e($pr['bank_reference'] ?? '-') ?></small></td>
                                <td class="small text-muted"><?= e(date('d M Y', strtotime($pr['created_at']))) ?></td>
                                <td>
                                    <?php if ($pr['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success fw-bold" onclick="openPayoutModal(<?= (int)$pr['id'] ?>, '<?= e($pr['beneficiary_name']) ?>', '<?= number_format((float)$pr['amount'], 2) ?>', '<?= e($pr['payout_details']) ?>')">
                                            <i class="bi bi-check-lg"></i> Settle
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="bi bi-check2-all text-success"></i> Settled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Settle Payout Modal -->
<div class="modal fade" id="payoutSettleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Process Single Settlement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="settle_payout" value="1">
                <input type="hidden" name="payout_id" id="modalPayoutId">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="text-muted small">BENEFICIARY</div>
                        <h5 class="fw-bold text-dark mb-1" id="modalBeneficiaryName">-</h5>
                        <div class="fs-4 fw-bold text-success" id="modalPayoutAmount">₹0.00</div>
                        <div class="small text-secondary mt-1">Target Account / UPI: <code id="modalPayoutTarget" class="text-dark fw-bold"></code></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Action</label>
                        <select name="action_type" class="form-select">
                            <option value="approve">Approve &bull; Money Transferred</option>
                            <option value="reject">Reject Request</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bank Reference / UTR Number</label>
                        <input type="text" name="bank_reference" class="form-control" placeholder="e.g. UTR249012398412" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Confirm Settlement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayoutModal(id, name, amt, target) {
    document.getElementById('modalPayoutId').value = id;
    document.getElementById('modalBeneficiaryName').innerText = name;
    document.getElementById('modalPayoutAmount').innerText = '₹' + amt;
    document.getElementById('modalPayoutTarget').innerText = target;
    new bootstrap.Modal(document.getElementById('payoutSettleModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>