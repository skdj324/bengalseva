<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'support', 'manager']);
$user = Auth::user();

// Handle Support Reply & Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $tId = (int)($_POST['ticket_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? 'in_progress');
    $reply = sanitizeInput($_POST['staff_reply'] ?? '');

    $stmt = $pdo->prepare('
        UPDATE support_tickets 
        SET status = :st, staff_reply = :reply, assigned_to = :aid, updated_at = NOW() 
        WHERE id = :id
    ');
    $stmt->execute([
        ':st'    => $status,
        ':reply' => $reply,
        ':aid'   => $user['id'],
        ':id'    => $tId
    ]);

    logAudit($pdo, $user['id'], 'TICKET_RESOLVED', 'support_tickets', $tId, "Status: {$status}, Reply: {$reply}");
    setFlash('success', "Ticket #{$tId} updated successfully!");
    header('Location: dashboard.php');
    exit;
}

$tickets = $pdo->query('SELECT * FROM support_tickets ORDER BY id DESC LIMIT 100')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-headset text-primary me-2"></i> Support Desk &amp; Customer Complaints</h3>
            <small class="text-muted">Investigate customer disputes, order delivery delays, and refund requests</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Customer Support Tickets (<?= count($tickets) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No open support tickets.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td class="fw-bold font-monospace">#<?= e($t['ticket_number']) ?></td>
                                <td><?= e($t['customer_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($t['customer_phone']) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 fw-bold">
                                        <i class="bi bi-telephone-fill text-success me-1"></i> <?= e($t['customer_phone']) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border text-uppercase"><?= e($t['category']) ?></span></td>
                                <td class="fw-semibold text-dark" style="max-width: 200px;"><?= e($t['subject']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $t['priority'] === 'urgent' ? 'danger' : ($t['priority'] === 'high' ? 'warning text-dark' : 'secondary') ?> text-uppercase">
                                        <?= e($t['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $t['status'] === 'resolved' ? 'success' : ($t['status'] === 'open' ? 'danger' : 'info') ?> text-uppercase">
                                        <?= e($t['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary fw-bold" onclick="openTicketModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)">
                                        Resolve &rarr;
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Ticket Resolution Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="ticketModalTitle">Resolve Customer Ticket</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="update_ticket" value="1">
                <input type="hidden" name="ticket_id" id="ticketId">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="row g-2">
                            <div class="col-sm-6"><strong>Customer:</strong> <span id="ticketCustName">-</span></div>
                            <div class="col-sm-6"><strong>Phone:</strong> <span id="ticketCustPhone">-</span></div>
                            <div class="col-12 mt-2"><strong>Issue Description:</strong></div>
                            <div class="col-12 text-secondary small" id="ticketDesc">-</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Update Ticket Status</label>
                            <select name="status" id="ticketStatusSelect" class="form-select">
                                <option value="in_progress">In Progress / Investigating</option>
                                <option value="resolved">Resolved &bull; Issue Fixed</option>
                                <option value="closed">Closed &bull; Ticket Completed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Staff Resolution Note / Reply to Customer *</label>
                            <textarea name="staff_reply" id="ticketStaffReply" class="form-control" rows="3" placeholder="Enter resolution explanation, refund reference, or investigation outcome..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Save Ticket Resolution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTicketModal(t) {
    document.getElementById('ticketId').value = t.id;
    document.getElementById('ticketModalTitle').innerText = 'Ticket #' + t.ticket_number + ': ' + t.subject;
    document.getElementById('ticketCustName').innerText = t.customer_name;
    document.getElementById('ticketCustPhone').innerText = t.customer_phone;
    document.getElementById('ticketDesc').innerText = t.description;
    document.getElementById('ticketStatusSelect').value = t.status === 'open' ? 'in_progress' : t.status;
    document.getElementById('ticketStaffReply').value = t.staff_reply || '';
    new bootstrap.Modal(document.getElementById('ticketModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>