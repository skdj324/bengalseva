<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['provider', 'vendor', 'super_admin', 'admin']);
$user = Auth::user();

// Resolve vendor profile
$vStmt = $pdo->prepare('SELECT * FROM vendor_profiles WHERE user_id = :uid LIMIT 1');
$vStmt->execute([':uid' => $user['id']]);
$profile = $vStmt->fetch();

// Handle Booking Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status'])) {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];

    if (in_array($newStatus, $allowed, true) && $bookingId > 0) {
        $up = $pdo->prepare('UPDATE bookings SET status = :st WHERE id = :bid');
        $up->execute([':st' => $newStatus, ':bid' => $bookingId]);
        logAudit($pdo, $user['id'], 'BOOKING_STATUS_UPDATE', 'bookings', $bookingId, "Booking status changed to {$newStatus}");
        setFlash('success', "Booking #{$bookingId} marked as " . strtoupper($newStatus));
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch Appointments for this provider
$bookings = [];
if ($profile) {
    $bStmt = $pdo->prepare('
        SELECT b.*, si.title as service_title
        FROM bookings b
        LEFT JOIN service_items si ON b.service_item_id = si.id
        WHERE b.vendor_id = :vid
        ORDER BY b.booking_date ASC, b.id DESC
    ');
    $bStmt->execute([':vid' => $profile['id']]);
    $bookings = $bStmt->fetchAll();
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1"><?= e($profile['business_name'] ?? $user['name']) ?></h3>
                <span class="badge bg-primary text-uppercase">Healthcare &amp; Specialist Appointment Queue</span>
            </div>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
        </div>
    </div>

    <!-- Booking Schedule Queue -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar2-check-fill text-primary me-2"></i> Confirmed &amp; Incoming Appointments (<?= count($bookings) ?>)</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Booking ID</th>
                        <th>Client / Patient</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Date &amp; Slot</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No client appointments booked yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td>#<?= (int)$b['id'] ?></td>
                                <td class="fw-bold text-dark"><?= e($b['customer_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($b['customer_phone']) ?>" class="text-decoration-none fw-semibold">
                                        <i class="bi bi-telephone-fill text-success me-1"></i> <?= e($b['customer_phone']) ?>
                                    </a>
                                </td>
                                <td><?= e($b['service_title'] ?? 'In-Clinic Service') ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e(date('d M Y', strtotime($b['booking_date']))) ?></div>
                                    <small class="text-muted"><?= e($b['time_slot']) ?></small>
                                </td>
                                <td class="fw-bold text-primary">&#8377;<?= number_format((float)$b['total_amount'], 0) ?></td>
                                <td>
                                    <span class="badge bg-<?= $b['status'] === 'confirmed' ? 'success' : ($b['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?> text-uppercase">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="update_booking_status" value="1">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Set Status</option>
                                            <option value="confirmed">Confirm</option>
                                            <option value="completed">Complete</option>
                                            <option value="cancelled">Cancel</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
