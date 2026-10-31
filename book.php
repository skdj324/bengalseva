<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$vendorId = (int)($_GET['vendor_id'] ?? 0);
$serviceId = (int)($_GET['service_id'] ?? 0);

// Resolve vendor
$vStmt = $pdo->prepare('
    SELECT vp.*, c.name as category_name, d.name as district_name 
    FROM vendor_profiles vp
    LEFT JOIN categories c ON vp.category_id = c.id
    LEFT JOIN districts d ON vp.district_id = d.id
    WHERE vp.id = :id AND vp.status = "approved"
    LIMIT 1
');
$vStmt->execute([':id' => $vendorId]);
$vendor = $vStmt->fetch();

if (!$vendor) {
    // If no vendor passed, select the first approved healthcare/service provider
    $vFallback = $pdo->query('SELECT id FROM vendor_profiles WHERE status = "approved" ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($vFallback) {
        header('Location: book.php?vendor_id=' . $vFallback);
        exit;
    }
    setFlash('warning', 'No service provider is currently accepting direct online appointments.');
    header('Location: ' . APP_URL . '/');
    exit;
}

// Fetch active services of this vendor
$sStmt = $pdo->prepare('SELECT * FROM service_items WHERE vendor_id = :vid AND status = "active" ORDER BY id ASC');
$sStmt->execute([':vid' => $vendor['id']]);
$services = $sStmt->fetchAll();

// Handle Booking Form Submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Session security token expired. Please try again.';
    } else {
        $custName = sanitizeInput($_POST['customer_name'] ?? '');
        $custPhone = sanitizeInput($_POST['customer_phone'] ?? '');
        $custEmail = sanitizeInput($_POST['customer_email'] ?? '');
        $bookDate = sanitizeInput($_POST['booking_date'] ?? '');
        $timeSlot = sanitizeInput($_POST['time_slot'] ?? '');
        $selectedServiceId = (int)($_POST['service_item_id'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');

        if (empty($custName) || empty($custPhone) || empty($bookDate) || empty($timeSlot) || !$selectedServiceId) {
            $error = 'Please fill in your name, contact phone, appointment date, time slot, and service.';
        } elseif (strtotime($bookDate) < strtotime(date('Y-m-d'))) {
            $error = 'Please select a valid future appointment date.';
        } else {
            // Get price from selected service
            $priceStmt = $pdo->prepare('SELECT price FROM service_items WHERE id = :sid AND vendor_id = :vid LIMIT 1');
            $priceStmt->execute([':sid' => $selectedServiceId, ':vid' => $vendor['id']]);
            $totalAmount = (float)($priceStmt->fetchColumn() ?: 0.00);

            $customerId = Auth::check() ? (int)Auth::user()['id'] : null;

            try {
                $bookStmt = $pdo->prepare('
                    INSERT INTO bookings (
                        customer_id, vendor_id, service_item_id, booking_date, time_slot,
                        customer_name, customer_phone, customer_email, total_amount,
                        status, payment_status, notes, created_at
                    ) VALUES (
                        :cid, :vid, :sid, :bdate, :tslot,
                        :cname, :cphone, :cemail, :amt,
                        "pending", "pending", :notes, NOW()
                    )
                ');
                $bookStmt->execute([
                    ':cid'    => $customerId,
                    ':vid'    => $vendor['id'],
                    ':sid'    => $selectedServiceId,
                    ':bdate'  => $bookDate,
                    ':tslot'  => $timeSlot,
                    ':cname'  => $custName,
                    ':cphone' => $custPhone,
                    ':cemail' => $custEmail,
                    ':amt'    => $totalAmount,
                    ':notes'  => $notes
                ]);

                $bookingId = (int)$pdo->lastInsertId();
                logAudit($pdo, $customerId, 'BOOKING_CREATED', 'bookings', $bookingId, "Appointment scheduled with {$vendor['business_name']} for {$bookDate} ({$timeSlot})");

                setFlash('success', "Your appointment (#{$bookingId}) has been reserved! The provider will verify your slot shortly.");
                if (Auth::check()) {
                    header('Location: ' . APP_URL . '/portal/customer/dashboard.php');
                } else {
                    header('Location: ' . APP_URL . '/');
                }
                exit;

            } catch (PDOException $e) {
                error_log('[Booking Creation Error] ' . $e->getMessage());
                $error = 'An error occurred while confirming your appointment. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-primary text-white p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-white text-primary mb-1"><i class="bi bi-calendar-check-fill me-1"></i> Online Appointment</span>
                            <h3 class="fw-bold mb-0"><?= e($vendor['business_name']) ?></h3>
                            <small class="text-white-50"><i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= e($vendor['city']) ?>, <?= e($vendor['district_name']) ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success"><i class="bi bi-patch-check-fill"></i> VERIFIED PROVIDER</span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 mb-4"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="book.php?vendor_id=<?= (int)$vendor['id'] ?>" novalidate>
                        <input type="hidden" name="create_booking" value="1">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <!-- 1. Select Service Item -->
                        <h5 class="fw-bold text-dark mb-3">1. Select Consultation / Service</h5>
                        <div class="row g-3 mb-4">
                            <?php if (empty($services)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info py-2">General In-Clinic Consultation (Standard Slot).</div>
                                    <input type="hidden" name="service_item_id" value="1">
                                </div>
                            <?php else: ?>
                                <?php foreach ($services as $idx => $s): ?>
                                    <div class="col-md-6">
                                        <label class="card h-100 p-3 border rounded-3 cursor-pointer service-select-card" style="cursor: pointer;">
                                            <div class="form-check d-flex justify-content-between align-items-center">
                                                <div>
                                                    <input class="form-check-input me-2" type="radio" name="service_item_id" value="<?= (int)$s['id'] ?>" <?= ($serviceId === (int)$s['id'] || $idx === 0) ? 'checked' : '' ?> required>
                                                    <strong class="text-dark"><?= e($s['title']) ?></strong>
                                                    <div class="small text-muted"><?= (int)$s['duration_minutes'] ?> Mins Duration</div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="fs-5 fw-bold text-primary">&#8377;<?= number_format((float)$s['price'], 0) ?></span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Date and Time Slot -->
                        <h5 class="fw-bold text-dark mb-3">2. Choose Date &amp; Time Slot</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Appointment Date *</label>
                                <input type="date" name="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Preferred Time Slot *</label>
                                <select name="time_slot" class="form-select" required>
                                    <option value="">Select Slot</option>
                                    <optgroup label="Morning Slots">
                                        <option value="09:30 AM - 10:30 AM">09:30 AM - 10:30 AM</option>
                                        <option value="10:30 AM - 11:30 AM">10:30 AM - 11:30 AM</option>
                                        <option value="11:30 AM - 12:30 PM">11:30 AM - 12:30 PM</option>
                                    </optgroup>
                                    <optgroup label="Afternoon &amp; Evening Slots">
                                        <option value="03:00 PM - 04:00 PM">03:00 PM - 04:00 PM</option>
                                        <option value="04:30 PM - 05:30 PM">04:30 PM - 05:30 PM</option>
                                        <option value="06:00 PM - 07:00 PM">06:00 PM - 07:00 PM</option>
                                        <option value="07:30 PM - 08:30 PM">07:30 PM - 08:30 PM</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <!-- 3. Patient / Client Contact Info -->
                        <h5 class="fw-bold text-dark mb-3">3. Contact &amp; Patient Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Full Name *</label>
                                <input type="text" name="customer_name" class="form-control" placeholder="e.g. Anirban Mukherjee" value="<?= Auth::check() ? e(Auth::user()['name']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Mobile Number (WhatsApp Preferred) *</label>
                                <input type="tel" name="customer_phone" class="form-control" placeholder="10-digit number" pattern="[0-9]{10}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold small">Email Address (Optional)</label>
                                <input type="email" name="customer_email" class="form-control" placeholder="name@example.com" value="<?= Auth::check() ? e(Auth::user()['email']) : '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Health Concern or Specific Instructions</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Describe symptoms, prior reports, or preferences..."></textarea>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-3">
                                <i class="bi bi-check-circle-fill me-2"></i> Confirm &amp; Reserve Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
