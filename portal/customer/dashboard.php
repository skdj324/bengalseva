<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['customer', 'super_admin', 'admin']);
$user = Auth::user();

// Fetch customer bookings
$bStmt = $pdo->prepare('
    SELECT b.*, vp.business_name, vp.city, si.title as service_title
    FROM bookings b
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
    LEFT JOIN service_items si ON b.service_item_id = si.id
    WHERE b.customer_id = :cid OR b.customer_email = :email
    ORDER BY b.id DESC
');
$bStmt->execute([':cid' => $user['id'], ':email' => $user['email']]);
$bookings = $bStmt->fetchAll();

// Fetch customer submitted leads
$lStmt = $pdo->prepare('
    SELECT l.*, d.name as district_name, vp.business_name as assigned_vendor
    FROM leads l
    LEFT JOIN districts d ON l.district_id = d.id
    LEFT JOIN vendor_profiles vp ON l.vendor_id = vp.id
    WHERE l.customer_id = :cid
    ORDER BY l.id DESC
');
$lStmt->execute([':cid' => $user['id']]);
$leads = $lStmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1">Hello, <?= e($user['name']) ?>!</h3>
                <span class="text-muted small"><i class="bi bi-envelope me-1"></i> <?= e($user['email']) ?> | Customer Member</span>
            </div>
            <a href="<?= APP_URL ?>/" class="btn btn-primary fw-bold btn-sm">
                <i class="bi bi-search me-1"></i> Explore Services
            </a>
        </div>
    </div>

    
    <!-- 3. Grocery & Product Orders Section -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-bag-check-fill text-danger me-2"></i> My Grocery &amp; Mart Orders</h5>
            <a href="https://searchkaro.midexa.in/shop.php" class="btn btn-outline-primary btn-sm fw-bold">Shop More</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Order #</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $userOrders = $pdo->prepare("SELECT * FROM orders WHERE customer_id = :cid OR customer_email = :email ORDER BY id DESC");
                        $userOrders->execute([":cid" => $user["id"], ":email" => $user["email"]]);
                        $custOrders = $userOrders->fetchAll();
                        if (empty($custOrders)):
                    ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No product or grocery orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($custOrders as $co): ?>
                            <tr>
                                <td class="fw-bold text-dark">#<?= e($co["order_number"]) ?></td>
                                <td class="fw-bold text-primary">&#8377;<?= number_format((float)$co["final_amount"], 2) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= strtoupper(e($co["payment_method"])) ?></span></td>
                                <td><span class="badge bg-primary text-uppercase"><?= e($co["order_status"]) ?></span></td>
                                <td class="small text-muted"><?= e(date("d M Y, h:i A", strtotime($co["created_at"]))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- 1. Appointments Section -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar-event-fill text-primary me-2"></i> My Scheduled Appointments (<?= count($bookings) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Booking ID</th>
                        <th>Provider / Clinic</th>
                        <th>Service</th>
                        <th>Date &amp; Time Slot</th>
                        <th>Fee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">You have not booked any appointments yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td>#<?= (int)$b['id'] ?></td>
                                <td class="fw-bold text-dark"><?= e($b['business_name'] ?? 'Provider') ?></td>
                                <td><?= e($b['service_title'] ?? 'In-Clinic Service') ?></td>
                                <td><?= e(date('d M Y', strtotime($b['booking_date']))) ?> &bull; <small class="text-muted"><?= e($b['time_slot']) ?></small></td>
                                <td class="fw-semibold text-primary">&#8377;<?= number_format((float)$b['total_amount'], 0) ?></td>
                                <td>
                                    <span class="badge bg-<?= $b['status'] === 'confirmed' ? 'success' : ($b['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?> text-uppercase">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. Leads & Inquiries Section -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-chat-left-dots-fill text-success me-2"></i> My Service Requests &amp; Quotes (<?= count($leads) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Lead ID</th>
                        <th>Requirement</th>
                        <th>District</th>
                        <th>Assigned Partner</th>
                        <th>Status</th>
                        <th>Submitted On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No custom service inquiries submitted.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leads as $l): ?>
                            <tr>
                                <td>#<?= (int)$l['id'] ?></td>
                                <td class="small text-muted" style="max-width: 280px;"><?= e($l['requirement']) ?></td>
                                <td><?= e($l['district_name'] ?? 'West Bengal') ?></td>
                                <td><?= e($l['assigned_vendor'] ?? 'Assigned to Local Pool') ?></td>
                                <td>
                                    <span class="badge bg-<?= $l['status'] === 'new' ? 'primary' : ($l['status'] === 'converted' ? 'success' : 'secondary') ?> text-uppercase">
                                        <?= e($l['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
