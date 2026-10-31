<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/permissions.php';

Auth::requireRole(['super_admin', 'admin', 'manager', 'accountant', 'agent', 'support']);
$staffUser = Auth::user();
$userRole = (string)$staffUser['role'];

// Handle New CRM Follow-Up Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_crm_followup'])) {
    $eType = sanitizeInput($_POST['entity_type'] ?? 'order');
    $eId = (int)($_POST['entity_id'] ?? 0);
    $cName = sanitizeInput($_POST['customer_name'] ?? '');
    $cPhone = sanitizeInput($_POST['customer_phone'] ?? '');
    $channel = sanitizeInput($_POST['channel'] ?? 'call');
    $outcome = sanitizeInput($_POST['outcome'] ?? 'connected');
    $priority = sanitizeInput($_POST['priority'] ?? 'medium');
    $nextDate = !empty($_POST['next_followup_at']) ? sanitizeInput($_POST['next_followup_at']) : null;
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($eId > 0 && $notes) {
        $stmt = $pdo->prepare('
            INSERT INTO crm_followups (
                entity_type, entity_id, staff_user_id, staff_name, customer_name,
                customer_phone, channel, outcome, priority, next_followup_at, notes, created_at
            ) VALUES (
                :etype, :eid, :uid, :sname, :cname,
                :cphone, :channel, :outcome, :prio, :next_at, :notes, NOW()
            )
        ');
        $stmt->execute([
            ':etype'   => $eType,
            ':eid'     => $eId,
            ':uid'     => $staffUser['id'],
            ':sname'   => $staffUser['name'],
            ':cname'   => $cName,
            ':cphone'  => $cPhone,
            ':channel' => $channel,
            ':outcome' => $outcome,
            ':prio'    => $priority,
            ':next_at' => $nextDate,
            ':notes'   => $notes
        ]);

        logAudit($pdo, $staffUser['id'], 'CRM_LOG_SAVED', $eType, $eId, "{$staffUser['name']} logged {$channel} with outcome: {$outcome}");
        setFlash('success', 'Customer interaction and callback schedule saved successfully!');
        header('Location: crm_ledger.php?tab=' . urlencode($_POST['active_tab'] ?? 'lenden'));
        exit;
    }
}

$tab = sanitizeInput($_GET['tab'] ?? 'lenden');

// Calculate KPI Metric Figures
$todayDate = date('Y-m-d');
$dueFollowupsCount = (int)$pdo->query("SELECT COUNT(*) FROM crm_followups WHERE next_followup_at IS NOT NULL AND DATE(next_followup_at) = '{$todayDate}'")->fetchColumn();
$totalLendenVolume = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'success'")->fetchColumn();
$openOrdersCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('placed', 'confirmed', 'preparing')")->fetchColumn();
$activeTripsCount = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE status IN ('requested', 'accepted', 'in_trip')")->fetchColumn();

// 1. Scheduled Callbacks Due Today
$dueReminders = $pdo->query("
    SELECT * FROM crm_followups 
    WHERE next_followup_at IS NOT NULL AND DATE(next_followup_at) = '{$todayDate}'
    ORDER BY next_followup_at ASC
")->fetchAll();

// 2. Lenden (Payments & Transactions)
$payments = $pdo->query('
    SELECT p.*, u.full_name as user_name, u.mobile as user_mobile 
    FROM payments p 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.id DESC LIMIT 100
')->fetchAll();

// 3. Mart & Grocery Orders
$orders = $pdo->query('
    SELECT o.*, vp.business_name 
    FROM orders o 
    LEFT JOIN vendor_profiles vp ON o.vendor_id = vp.id 
    ORDER BY o.id DESC LIMIT 100
')->fetchAll();

// 4. Trips & Rides
$rides = $pdo->query('
    SELECT r.*, d.name as district_name 
    FROM rides r 
    LEFT JOIN districts d ON r.district_id = d.id 
    ORDER BY r.id DESC LIMIT 100
')->fetchAll();

// 5. Doctor & Clinic Bookings
$bookings = $pdo->query('
    SELECT b.*, vp.business_name 
    FROM bookings b 
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id 
    ORDER BY b.id DESC LIMIT 100
')->fetchAll();

// 6. Recent Interaction History
$followups = $pdo->query('
    SELECT * FROM crm_followups ORDER BY id DESC LIMIT 50
')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4 px-md-5">
    <!-- Header with CSV Export & Status -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-shield-shaded text-danger me-2"></i> BengalSeva Enterprise Operations CRM</h3>
            <small class="text-muted">Multi-vertical follow-up console for orders, lenden settlements, rides &amp; scheduled callbacks</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/api/export-crm-csv.php" class="btn btn-outline-success fw-bold btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CRM Logs (CSV)
            </a>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
        </div>
    </div>

    <!-- KPI Metric Analytics Bar -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4 border-start border-warning border-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">TODAY'S DUE CALLS</div>
                        <div class="fs-3 fw-bold text-dark"><?= $dueFollowupsCount ?></div>
                    </div>
                    <i class="bi bi-bell-fill fs-2 text-warning opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4 border-start border-success border-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">TOTAL SETTLED LENDEN</div>
                        <div class="fs-3 fw-bold text-success">₹<?= number_format($totalLendenVolume, 2) ?></div>
                    </div>
                    <i class="bi bi-cash-stack fs-2 text-success opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4 border-start border-primary border-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">ACTIVE MART ORDERS</div>
                        <div class="fs-3 fw-bold text-primary"><?= $openOrdersCount ?></div>
                    </div>
                    <i class="bi bi-box-seam-fill fs-2 text-primary opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4 border-start border-danger border-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">ACTIVE RAPIDO TRIPS</div>
                        <div class="fs-3 fw-bold text-danger"><?= $activeTripsCount ?></div>
                    </div>
                    <i class="bi bi-scooter fs-2 text-danger opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduled Callbacks Alert Banner (If Any Due Today) -->
    <?php if (!empty($dueReminders)): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-alarm-fill fs-3 text-danger"></i>
                <div>
                    <strong>Action Required:</strong> You have <?= count($dueReminders) ?> customer callbacks scheduled for today!
                </div>
            </div>
            <button class="btn btn-sm btn-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#dueRemindersCollapse">
                View Scheduled List &darr;
            </button>
        </div>

        <div class="collapse mb-4" id="dueRemindersCollapse">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <h6 class="fw-bold text-dark mb-2">Today's Callback Schedule (<?= $todayDate ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Scheduled Time</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Reference</th>
                                <th>Priority</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dueReminders as $dr): ?>
                                <tr>
                                    <td class="fw-bold text-danger"><?= e(date('h:i A', strtotime($dr['next_followup_at']))) ?></td>
                                    <td class="fw-semibold text-dark"><?= e($dr['customer_name'] ?? 'Customer') ?></td>
                                    <td>
                                        <a href="tel:<?= e($dr['customer_phone']) ?>" class="btn btn-sm btn-outline-success py-0 px-2 fw-bold">
                                            <i class="bi bi-telephone-fill me-1"></i> Call
                                        </a>
                                        <a href="https://wa.me/91<?= preg_replace('/\D/', '', (string)$dr['customer_phone']) ?>?text=<?= urlencode('Hello ' . ($dr['customer_name'] ?? '') . ', this is regarding your request on BengalSeva.') ?>" target="_blank" class="btn btn-sm btn-success py-0 px-2 ms-1">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= strtoupper(e($dr['entity_type'])) ?> #<?= (int)$dr['entity_id'] ?></span></td>
                                    <td><span class="badge bg-<?= $dr['priority'] === 'urgent' ? 'danger' : 'warning text-dark' ?> text-uppercase"><?= e($dr['priority']) ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary py-0 px-2" onclick="openCRMModal('<?= e($dr['entity_type']) ?>', <?= (int)$dr['entity_id'] ?>, '<?= e($dr['customer_name'] ?? '') ?>', '<?= e($dr['customer_phone'] ?? '') ?>')">
                                            Log Outcome
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 shadow-sm border">
        <?php if (hasPermission($userRole, 'view_lenden', $pdo)): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $tab === 'lenden' ? 'active' : '' ?>" href="crm_ledger.php?tab=lenden">
                    <i class="bi bi-cash-stack me-1"></i> Lenden &amp; Payments (<?= count($payments) ?>)
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_orders', $pdo)): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $tab === 'orders' ? 'active' : '' ?>" href="crm_ledger.php?tab=orders">
                    <i class="bi bi-box-seam me-1"></i> Mart Orders (<?= count($orders) ?>)
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_rides', $pdo)): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $tab === 'rides' ? 'active' : '' ?>" href="crm_ledger.php?tab=rides">
                    <i class="bi bi-scooter me-1"></i> Rapido Trips (<?= count($rides) ?>)
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_bookings', $pdo)): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $tab === 'bookings' ? 'active' : '' ?>" href="crm_ledger.php?tab=bookings">
                    <i class="bi bi-hospital me-1"></i> Doctor Bookings (<?= count($bookings) ?>)
                </a>
            </li>
        <?php endif; ?>

        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab === 'history' ? 'active' : '' ?>" href="crm_ledger.php?tab=history">
                <i class="bi bi-clock-history me-1"></i> All Interaction History (<?= count($followups) ?>)
            </a>
        </li>
    </ul>

    <!-- Tab 1: Lenden / Financial Ledger -->
    <?php if ($tab === 'lenden' && hasPermission($userRole, 'view_lenden', $pdo)): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-receipt me-2 text-success"></i> Payments Ledger &amp; Gateways</h5>
                <span class="badge bg-light text-dark border">Auto-synced with Razorpay, PhonePe, Cashfree &amp; PayU</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Payment ID</th>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Gateway</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="fw-bold font-monospace">#<?= e($p['payment_number']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= strtoupper(e($p['reference_type'])) ?> #<?= (int)$p['reference_id'] ?></span></td>
                                <td>
                                    <strong><?= e($p['user_name'] ?? 'Direct Customer') ?></strong>
                                    <?php if (!empty($p['user_mobile'])): ?>
                                        <div class="small">
                                            <a href="tel:<?= e($p['user_mobile']) ?>" class="text-decoration-none"><i class="bi bi-telephone-fill text-success me-1"></i><?= e($p['user_mobile']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-primary fs-6">₹<?= number_format((float)$p['amount'], 2) ?></td>
                                <td><span class="badge bg-info text-dark font-monospace"><?= strtoupper(e($p['gateway'])) ?></span></td>
                                <td><small class="text-muted font-monospace"><?= e($p['transaction_id'] ?? 'Pending') ?></small></td>
                                <td>
                                    <span class="badge bg-<?= $p['payment_status'] === 'success' ? 'success' : ($p['payment_status'] === 'pending' ? 'warning text-dark' : 'danger') ?> text-uppercase">
                                        <?= e($p['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= e(date('d M Y, h:i A', strtotime($p['created_at']))) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-dark" onclick="openCRMModal('payment', <?= (int)$p['id'] ?>, '<?= e($p['user_name'] ?? 'Customer') ?>', '<?= e($p['user_mobile'] ?? '') ?>')">
                                        <i class="bi bi-headset me-1"></i> Follow-Up
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 2: Mart & Grocery Orders -->
    <?php if ($tab === 'orders' && hasPermission($userRole, 'view_orders', $pdo)): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-bag-check-fill text-danger me-2"></i> Product &amp; Mart Orders</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Contact / WhatsApp</th>
                            <th>Vendor</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>CRM Connect</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="fw-bold font-monospace">#<?= e($o['order_number']) ?></td>
                                <td class="fw-bold"><?= e($o['customer_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($o['customer_phone']) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 fw-bold">
                                        <i class="bi bi-telephone-fill text-success me-1"></i> Call
                                    </a>
                                    <a href="https://wa.me/91<?= preg_replace('/\D/', '', (string)$o['customer_phone']) ?>?text=<?= urlencode('Hello ' . $o['customer_name'] . ', update regarding your BengalSeva order #' . $o['order_number']) ?>" target="_blank" class="btn btn-sm btn-success py-0 px-2 ms-1">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </td>
                                <td><?= e($o['business_name'] ?? 'Local Vendor') ?></td>
                                <td class="fw-bold text-primary">₹<?= number_format((float)$o['final_amount'], 2) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= strtoupper(e($o['payment_method'])) ?></span></td>
                                <td><span class="badge bg-primary text-uppercase"><?= e($o['order_status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-danger fw-bold" onclick="openCRMModal('order', <?= (int)$o['id'] ?>, '<?= e($o['customer_name']) ?>', '<?= e($o['customer_phone']) ?>')">
                                        <i class="bi bi-pencil-square me-1"></i> Call Note
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 3: Rapido Trips -->
    <?php if ($tab === 'rides' && hasPermission($userRole, 'view_rides', $pdo)): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-scooter text-warning me-2"></i> Rapido Trips &amp; Deliveries</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Trip #</th>
                            <th>Passenger</th>
                            <th>Connect</th>
                            <th>Vehicle</th>
                            <th>Route</th>
                            <th>Fare</th>
                            <th>Trip Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rides as $r): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$r['id'] ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($r['customer_phone']) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 fw-bold">
                                        <i class="bi bi-telephone-fill text-success me-1"></i> Call
                                    </a>
                                    <a href="https://wa.me/91<?= preg_replace('/\D/', '', (string)$r['customer_phone']) ?>?text=<?= urlencode('Hello ' . $r['customer_name'] . ', regarding your BengalSeva ride #' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-success py-0 px-2 ms-1">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= strtoupper(str_replace('_', ' ', $r['service_type'])) ?></span></td>
                                <td class="small text-muted" style="max-width: 250px;"><strong><?= e($r['pickup_address']) ?></strong> &rarr; <?= e($r['drop_address']) ?></td>
                                <td class="fw-bold text-primary">₹<?= number_format((float)$r['estimated_fare'], 0) ?></td>
                                <td><span class="badge bg-<?= $r['status'] === 'completed' ? 'success' : 'warning text-dark' ?> text-uppercase"><?= e($r['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-warning text-dark fw-bold" onclick="openCRMModal('ride', <?= (int)$r['id'] ?>, '<?= e($r['customer_name']) ?>', '<?= e($r['customer_phone']) ?>')">
                                        <i class="bi bi-chat-left-text me-1"></i> Follow-Up
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 4: Doctor Bookings -->
    <?php if ($tab === 'bookings' && hasPermission($userRole, 'view_bookings', $pdo)): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hospital text-info me-2"></i> Doctor &amp; Clinic Appointments</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Booking #</th>
                            <th>Patient Name</th>
                            <th>Connect</th>
                            <th>Clinic / Doctor</th>
                            <th>Date &amp; Slot</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$b['id'] ?></td>
                                <td><?= e($b['customer_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($b['customer_phone']) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 fw-bold">
                                        <i class="bi bi-telephone-fill text-success me-1"></i> Call
                                    </a>
                                    <a href="https://wa.me/91<?= preg_replace('/\D/', '', (string)$b['customer_phone']) ?>?text=<?= urlencode('Hello ' . $b['customer_name'] . ', confirmation regarding your appointment with ' . ($b['business_name'] ?? 'Doctor')) ?>" target="_blank" class="btn btn-sm btn-success py-0 px-2 ms-1">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </td>
                                <td><?= e($b['business_name'] ?? 'Healthcare Clinic') ?></td>
                                <td><?= e(date('d M Y', strtotime($b['booking_date']))) ?> &bull; <small class="text-muted"><?= e($b['time_slot']) ?></small></td>
                                <td class="fw-bold text-primary">₹<?= number_format((float)$b['total_amount'], 2) ?></td>
                                <td><span class="badge bg-<?= $b['status'] === 'confirmed' ? 'success' : 'warning text-dark' ?> text-uppercase"><?= e($b['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white fw-bold" onclick="openCRMModal('booking', <?= (int)$b['id'] ?>, '<?= e($b['customer_name']) ?>', '<?= e($b['customer_phone']) ?>')">
                                        <i class="bi bi-pencil me-1"></i> Log Call
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 5: Interaction History & Call Logs -->
    <?php if ($tab === 'history'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-secondary me-2"></i> Master Staff Follow-Up Ledger</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Date / Time</th>
                            <th>Staff Member</th>
                            <th>Customer</th>
                            <th>Channel</th>
                            <th>Outcome</th>
                            <th>Priority</th>
                            <th>Next Callback</th>
                            <th>Staff Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups as $f): ?>
                            <tr>
                                <td class="small text-muted"><?= e(date('d M Y, h:i A', strtotime($f['created_at']))) ?></td>
                                <td class="fw-bold text-dark"><?= e($f['staff_name']) ?></td>
                                <td>
                                    <strong><?= e($f['customer_name'] ?? 'N/A') ?></strong>
                                    <div class="small text-muted"><?= e($f['customer_phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase">
                                        <i class="bi bi-<?= $f['channel'] === 'whatsapp' ? 'whatsapp text-success' : 'telephone-fill text-primary' ?> me-1"></i> <?= e($f['channel']) ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-secondary text-uppercase"><?= e(str_replace('_', ' ', $f['outcome'])) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $f['priority'] === 'urgent' ? 'danger' : ($f['priority'] === 'high' ? 'warning text-dark' : 'secondary') ?> text-uppercase">
                                        <?= e($f['priority']) ?>
                                    </span>
                                </td>
                                <td class="small fw-bold text-danger">
                                    <?= $f['next_followup_at'] ? e(date('d M, h:i A', strtotime($f['next_followup_at']))) : '<span class="text-muted fw-normal">None</span>' ?>
                                </td>
                                <td class="text-secondary small" style="max-width: 300px;"><?= e($f['notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Professional CRM Follow-up & Scheduling Modal -->
<div class="modal fade" id="crmActionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="crmModalTitle">Log Customer Interaction &amp; Schedule Callback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="save_crm_followup" value="1">
                <input type="hidden" name="active_tab" value="<?= e($tab) ?>">
                <input type="hidden" name="entity_type" id="modalEntityType">
                <input type="hidden" name="entity_id" id="modalEntityId">
                <input type="hidden" name="customer_name" id="modalCustomerName">
                <input type="hidden" name="customer_phone" id="modalCustomerPhone">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-muted small d-block">CUSTOMER</span>
                            <h6 class="fw-bold mb-0 text-dark" id="displayCustName">Customer Name</h6>
                            <span class="small text-muted" id="displayCustPhone">98300XXXXX</span>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="#" id="directCallBtn" class="btn btn-outline-success fw-bold btn-sm">
                                <i class="bi bi-telephone-fill me-1"></i> Call Now
                            </a>
                            <a href="#" id="directWaBtn" target="_blank" class="btn btn-success fw-bold btn-sm">
                                <i class="bi bi-whatsapp me-1"></i> WhatsApp
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Channel</label>
                            <select name="channel" class="form-select">
                                <option value="call">Phone Call</option>
                                <option value="whatsapp">WhatsApp Chat</option>
                                <option value="email">Email</option>
                                <option value="visit">In-Person Visit</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Call Outcome</label>
                            <select name="outcome" class="form-select">
                                <option value="connected_interested">Connected &bull; Interested</option>
                                <option value="callback_requested">Callback Requested</option>
                                <option value="busy_no_answer">Busy / No Answer</option>
                                <option value="deal_won">Deal Won / Confirmed</option>
                                <option value="issue_resolved">Issue Resolved</option>
                                <option value="wrong_number">Wrong Number / Invalid</option>
                                <option value="cancelled">Cancelled by Customer</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="medium">Medium Priority</option>
                                <option value="high">High Priority</option>
                                <option value="urgent">URGENT (Action Today)</option>
                                <option value="low">Low Priority</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-danger">Schedule Next Callback (Date &amp; Time)</label>
                            <input type="datetime-local" name="next_followup_at" class="form-control" min="<?= date('Y-m-d\TH:i') ?>">
                            <small class="text-muted">System will alert in "Today's Due Calls" on this date.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Conversation Notes / Remarks *</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Enter conversation details, delivery instructions, payment verification, or resolution..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Follow-Up &amp; Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCRMModal(type, id, name, phone) {
    document.getElementById('modalEntityType').value = type;
    document.getElementById('modalEntityId').value = id;
    document.getElementById('modalCustomerName').value = name || 'Customer';
    document.getElementById('modalCustomerPhone').value = phone || '';

    document.getElementById('displayCustName').innerText = name || 'Customer';
    document.getElementById('displayCustPhone').innerText = phone || 'No phone recorded';

    document.getElementById('directCallBtn').href = 'tel:' + (phone || '');
    const cleanPhone = (phone || '').replace(/\D/g, '');
    document.getElementById('directWaBtn').href = 'https://wa.me/91' + cleanPhone + '?text=' + encodeURIComponent('Hello ' + (name || '') + ', contacting you from BengalSeva regarding your request.');

    new bootstrap.Modal(document.getElementById('crmActionModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>