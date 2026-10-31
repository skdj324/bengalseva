<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$createdTicket = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Security session expired. Please retry.';
    } else {
        $name = sanitizeInput($_POST['customer_name'] ?? '');
        $phone = sanitizeInput($_POST['customer_phone'] ?? '');
        $cat = sanitizeInput($_POST['category'] ?? 'general');
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $desc = sanitizeInput($_POST['description'] ?? '');
        $priority = sanitizeInput($_POST['priority'] ?? 'medium');

        if ($name && $phone && $subject && $desc) {
            $ticketNum = 'TCK-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $userId = Auth::check() ? (int)Auth::user()['id'] : null;

            $stmt = $pdo->prepare('
                INSERT INTO support_tickets (ticket_number, user_id, customer_name, customer_phone, category, subject, description, priority, status, created_at)
                VALUES (:tnum, :uid, :name, :phone, :cat, :sub, :desc, :prio, "open", NOW())
            ');
            $stmt->execute([
                ':tnum'  => $ticketNum,
                ':uid'   => $userId,
                ':name'  => $name,
                ':phone' => $phone,
                ':cat'   => $cat,
                ':sub'   => $subject,
                ':desc'  => $desc,
                ':prio'  => $priority
            ]);

            logAudit($pdo, $userId, 'TICKET_SUBMITTED', 'support_tickets', (int)$pdo->lastInsertId(), "Ticket {$ticketNum} opened by {$name}");
            $createdTicket = $ticketNum;
            setFlash('success', "Your support request #{$ticketNum} has been registered! Our team will resolve it shortly.");
        } else {
            $error = 'Please fill in all mandatory fields.';
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <div class="tile-icon-box tile-icon-blue mx-auto mb-2" style="width:50px; height:50px; font-size:1.4rem;">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">BengalSeva Customer Helpdesk</h3>
                    <small class="text-muted">Need help with an order, ride, doctor appointment or refund? File a ticket.</small>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($createdTicket): ?>
                    <div class="alert alert-success text-center py-3 rounded-3 mb-4">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                        <h5 class="fw-bold mb-1">Ticket #<?= e($createdTicket) ?> Raised Successfully</h5>
                        <p class="small text-muted mb-0">Our dedicated support executive will contact you on your registered phone within 2 hours.</p>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="submit_ticket" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Your Name *</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= Auth::check() ? e(Auth::user()['name']) : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="tel" name="customer_phone" class="form-control" pattern="[0-9]{10}" placeholder="10 Digits" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Issue Category</label>
                            <select name="category" class="form-select">
                                <option value="order">Grocery / Mart Order Issue</option>
                                <option value="ride">Bike Taxi / Cab Trip Issue</option>
                                <option value="booking">Doctor / Clinic Booking</option>
                                <option value="refund">Payment &amp; Refund Inquiry</option>
                                <option value="general">General Support</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Urgency Level</label>
                            <select name="priority" class="form-select">
                                <option value="medium">Normal / Standard</option>
                                <option value="urgent">URGENT &bull; Need immediate resolution</option>
                                <option value="high">High Priority</option>
                                <option value="low">Low Priority</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject / Order ID *</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Delivery delayed for Order #BS-2603..." required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Describe Your Issue in Detail *</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Please provide clear details so our support team can help you faster..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        Submit Support Ticket &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>