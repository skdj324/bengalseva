<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/fee_calculator.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: ' . APP_URL . '/shop.php');
    exit;
}

$subtotal = 0.0;
$vendorId = 0;
foreach ($cart as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
    $vendorId = (int)$item['vendor_id'];
}

$discount = 0.0;
if (!empty($_SESSION['coupon']) && $subtotal > 0) {
    $discount = min($subtotal * 0.50, (float)$_SESSION['coupon']['max_discount']);
}
$netSubtotal = max(0.0, $subtotal - $discount);

// Apply Dynamic Admin Customer Platform Fee
$feeInfo = getCustomerPlatformFee($netSubtotal, 'order', $pdo);
$platformFee = $feeInfo['fee'];
$platformFeeLabel = $feeInfo['label'];
$finalTotal = $feeInfo['total'];

$districts = $pdo->query('SELECT id, name FROM districts WHERE status = "active" ORDER BY name ASC')->fetchAll();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Session security token expired. Please retry.';
    } else {
        $custName = sanitizeInput($_POST['customer_name'] ?? '');
        $custPhone = sanitizeInput($_POST['customer_phone'] ?? '');
        $custEmail = sanitizeInput($_POST['customer_email'] ?? '');
        $deliveryAddr = sanitizeInput($_POST['delivery_address'] ?? '');
        $districtId = (int)($_POST['district_id'] ?? 0);
        $city = sanitizeInput($_POST['city'] ?? '');
        $pincode = sanitizeInput($_POST['pincode'] ?? '');
        $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'cod');

        if (empty($custName) || empty($custPhone) || empty($deliveryAddr) || !$districtId) {
            $error = 'Please fill in all mandatory delivery details.';
        } else {
            try {
                $pdo->beginTransaction();

                $orderNumber = 'BS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $customerId = Auth::check() ? (int)Auth::user()['id'] : null;

                $oStmt = $pdo->prepare('
                    INSERT INTO orders (
                        order_number, customer_id, vendor_id, customer_name, customer_phone,
                        customer_email, delivery_address, district_id, city, pincode,
                        total_amount, discount_amount, final_amount, payment_method,
                        payment_status, order_status, created_at
                    ) VALUES (
                        :onum, :cid, :vid, :cname, :cphone,
                        :cemail, :addr, :did, :city, :pin,
                        :tot, :disc, :final, :pmethod,
                        "pending", "placed", NOW()
                    )
                ');
                $oStmt->execute([
                    ':onum'    => $orderNumber,
                    ':cid'     => $customerId,
                    ':vid'     => $vendorId,
                    ':cname'   => $custName,
                    ':cphone'  => $custPhone,
                    ':cemail'  => $custEmail,
                    ':addr'    => $deliveryAddr,
                    ':did'     => $districtId,
                    ':city'    => $city,
                    ':pin'     => $pincode,
                    ':tot'     => $subtotal,
                    ':disc'    => $discount,
                    ':final'   => $finalTotal,
                    ':pmethod' => $paymentMethod
                ]);
                $orderId = (int)$pdo->lastInsertId();

                $iStmt = $pdo->prepare('
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal, created_at)
                    VALUES (:oid, :pid, :pname, :qty, :uprice, :sub, NOW())
                ');

                foreach ($cart as $item) {
                    $itemSub = (float)$item['price'] * (int)$item['quantity'];
                    $iStmt->execute([
                        ':oid'    => $orderId,
                        ':pid'    => (int)$item['id'],
                        ':pname'  => $item['name'],
                        ':qty'    => (int)$item['quantity'],
                        ':uprice' => (float)$item['price'],
                        ':sub'    => $itemSub
                    ]);
                }

                logAudit($pdo, $customerId, 'ORDER_PLACED', 'orders', $orderId, "Order {$orderNumber} placed for ₹{$finalTotal} (Incl. Fee: ₹{$platformFee})");
                $pdo->commit();

                unset($_SESSION['cart'], $_SESSION['coupon']);

                if ($paymentMethod === 'online') {
                    header('Location: ' . APP_URL . "/pay.php?type=order&id={$orderId}&amount={$finalTotal}");
                } else {
                    header('Location: ' . APP_URL . '/order-success.php?order_number=' . urlencode($orderNumber));
                }
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'An error occurred while confirming your order.';
            }
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h4 class="fw-bold text-dark mb-4"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Delivery Information</h4>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="place_order" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= Auth::check() ? e(Auth::user()['name']) : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Mobile Number (10 Digits) *</label>
                            <input type="tel" name="customer_phone" class="form-control" pattern="[0-9]{10}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Email Address (Optional)</label>
                            <input type="email" name="customer_email" class="form-control" value="<?= Auth::check() ? e(Auth::user()['email']) : '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Complete Delivery Street Address *</label>
                            <textarea name="delivery_address" class="form-control" rows="2" placeholder="Apartment, building, street, landmark..." required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">District *</label>
                            <select name="district_id" class="form-select" required>
                                <option value="">Select District</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">City / Area</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Howrah / Kolkata">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">PIN Code</label>
                            <input type="text" name="pincode" class="form-control" maxlength="6">
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mt-4 mb-3"><i class="bi bi-credit-card-2-front me-2"></i> Payment Method</h5>
                    <div class="form-check mb-2 p-3 border rounded-3 bg-light">
                        <input class="form-check-input" type="radio" name="payment_method" id="pmCod" value="cod" checked>
                        <label class="form-check-label fw-bold text-dark ms-2" for="pmCod">
                            Cash on Delivery (COD) / UPI on Doorstep
                        </label>
                    </div>
                    <div class="form-check mb-2 p-3 border rounded-3 bg-light">
                        <input class="form-check-input" type="radio" name="payment_method" id="pmOnline" value="online">
                        <label class="form-check-label fw-bold text-dark ms-2" for="pmOnline">
                            Pay Online Now (PhonePe / Razorpay / Cashfree / PayU / UPI)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 mt-3 rounded-3">
                        Confirm &amp; Place Order (₹<?= number_format($finalTotal, 2) ?>)
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Side: Order Summary with Fee Breakdown -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3">Order Summary</h5>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($cart as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                            <div>
                                <span class="fw-semibold text-dark"><?= e($item['name']) ?></span>
                                <small class="text-muted d-block">&times; <?= (int)$item['quantity'] ?></small>
                            </div>
                            <span class="fw-bold text-dark">₹<?= number_format((float)$item['price'] * (int)$item['quantity'], 0) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Item Subtotal</span>
                    <span class="fw-bold">₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success fw-bold">
                        <span>Coupon Discount</span>
                        <span>-₹<?= number_format($discount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Platform Fee Breakdown -->
                <?php if ($platformFee > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-secondary">
                        <span><?= e($platformFeeLabel) ?></span>
                        <span class="fw-bold">+₹<?= number_format($platformFee, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Delivery Charge</span>
                    <span class="text-success fw-bold">FREE</span>
                </div>

                <div class="d-flex justify-content-between fs-5 fw-bold text-primary mt-2 pt-2 border-top">
                    <span>Grand Total Payable</span>
                    <span>₹<?= number_format($finalTotal, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>