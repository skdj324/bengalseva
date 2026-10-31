<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$refType = sanitizeInput($_GET['type'] ?? 'order');
$refId = (int)($_GET['id'] ?? 0);
$amount = (float)($_GET['amount'] ?? 0.0);

if ($refId <= 0 || $amount <= 0.0) {
    setFlash('danger', 'Invalid payment request parameters.');
    header('Location: ' . APP_URL . '/');
    exit;
}

$gwStmt = $pdo->query('SELECT * FROM payment_gateways WHERE is_active = 1 ORDER BY id ASC');
$activeGateways = $gwStmt->fetchAll();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Security session expired. Please retry.';
    } else {
        $chosenGateway = sanitizeInput($_POST['gateway'] ?? 'cod');
        $paymentNumber = 'PAY-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $userId = Auth::check() ? (int)Auth::user()['id'] : null;

        $pStmt = $pdo->prepare('
            INSERT INTO payments (payment_number, user_id, reference_type, reference_id, amount, gateway, payment_status, created_at)
            VALUES (:pnum, :uid, :rtype, :rid, :amt, :gw, "initiated", NOW())
        ');
        $pStmt->execute([
            ':pnum'  => $paymentNumber,
            ':uid'   => $userId,
            ':rtype' => $refType,
            ':rid'   => $refId,
            ':amt'   => $amount,
            ':gw'    => $chosenGateway
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        // 1. Razorpay Checkout
        if ($chosenGateway === 'razorpay') {
            $rzp = $pdo->query('SELECT api_key FROM payment_gateways WHERE gateway_code = "razorpay" LIMIT 1')->fetch();
            $rzpKey = $rzp['api_key'] ?? 'rzp_test_1DP5mmOlF5G5ag';
            ?>
            <!DOCTYPE html>
            <html>
            <head><title>Processing Razorpay...</title></head>
            <body>
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
            var options = {
                "key": "<?= e($rzpKey) ?>",
                "amount": "<?= (int)($amount * 100) ?>",
                "currency": "INR",
                "name": "BengalSeva",
                "description": "Payment for <?= e($refType) ?> #<?= $refId ?>",
                "handler": function (response){
                    window.location.href = "<?= APP_URL ?>/payment-callback.php?gateway=razorpay&payment_number=<?= $paymentNumber ?>&rzp_payment_id=" + response.razorpay_payment_id;
                },
                "theme": { "color": "#0b5ed7" }
            };
            new Razorpay(options).open();
            </script>
            </body>
            </html>
            <?php
            exit;
        }

        // 2. PayU Money Integration (Standard SHA-512 Form Post)
        if ($chosenGateway === 'payu') {
            $payuRow = $pdo->query('SELECT * FROM payment_gateways WHERE gateway_code = "payu" LIMIT 1')->fetch();
            $merchantKey = $payuRow['api_key'] ?? 'TEST_KEY';
            $salt = $payuRow['api_secret'] ?? 'TEST_SALT';
            $isSandbox = (bool)($payuRow['is_sandbox'] ?? true);
            $payuUrl = $isSandbox ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment';

            $txnid = $paymentNumber;
            $productinfo = "BengalSeva {$refType} {$refId}";
            $firstname = Auth::check() ? Auth::user()['name'] : 'Customer';
            $email = Auth::check() ? Auth::user()['email'] : 'customer@bengalseva.in';
            $phone = '9830000000';

            // Hash Sequence: key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT
            $hashString = "{$merchantKey}|{$txnid}|{$amount}|{$productinfo}|{$firstname}|{$email}|||||||||||{$salt}";
            $hash = strtolower(hash('sha512', $hashString));
            ?>
            <!DOCTYPE html>
            <html>
            <head><title>Redirecting to PayU...</title></head>
            <body onload="document.getElementById('payuForm').submit();">
                <p>Redirecting to PayU Money secure payment gateway...</p>
                <form id="payuForm" method="POST" action="<?= e($payuUrl) ?>">
                    <input type="hidden" name="key" value="<?= e($merchantKey) ?>" />
                    <input type="hidden" name="hash" value="<?= e($hash) ?>"/>
                    <input type="hidden" name="txnid" value="<?= e($txnid) ?>" />
                    <input type="hidden" name="amount" value="<?= number_format($amount, 2, '.', '') ?>" />
                    <input type="hidden" name="firstname" value="<?= e($firstname) ?>" />
                    <input type="hidden" name="email" value="<?= e($email) ?>" />
                    <input type="hidden" name="phone" value="<?= e($phone) ?>" />
                    <input type="hidden" name="productinfo" value="<?= e($productinfo) ?>" />
                    <input type="hidden" name="surl" value="<?= APP_URL ?>/payment-callback.php?gateway=payu&status=success&payment_number=<?= $paymentNumber ?>" />
                    <input type="hidden" name="furl" value="<?= APP_URL ?>/payment-callback.php?gateway=payu&status=failure&payment_number=<?= $paymentNumber ?>" />
                </form>
            </body>
            </html>
            <?php
            exit;
        }

        // 3. Cashfree Payment Integration
        if ($chosenGateway === 'cashfree') {
            $cfRow = $pdo->query('SELECT * FROM payment_gateways WHERE gateway_code = "cashfree" LIMIT 1')->fetch();
            $cfAppId = $cfRow['api_key'] ?? 'TEST_APP_ID';
            $cfSecret = $cfRow['api_secret'] ?? 'TEST_SECRET';
            $isSandbox = (bool)($cfRow['is_sandbox'] ?? true);
            $cfEndpoint = $isSandbox ? 'https://sandbox.cashfree.com/pg/orders' : 'https://api.cashfree.com/pg/orders';

            $cfPayload = json_encode([
                'order_id'       => $paymentNumber,
                'order_amount'   => (float)$amount,
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id'    => (string)($userId ?: 1),
                    'customer_name'  => Auth::check() ? Auth::user()['name'] : 'Customer',
                    'customer_email' => Auth::check() ? Auth::user()['email'] : 'customer@bengalseva.in',
                    'customer_phone' => '9830000000'
                ],
                'order_meta' => [
                    'return_url' => APP_URL . "/payment-callback.php?gateway=cashfree&order_id={$paymentNumber}"
                ]
            ]);

            $ch = curl_init($cfEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $cfPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-version: 2023-08-01',
                'x-client-id: ' . $cfAppId,
                'x-client-secret: ' . $cfSecret
            ]);
            $cfResp = curl_exec($ch);
            curl_close($ch);

            $cfJson = json_decode((string)$cfResp, true);
            if (!empty($cfJson['payment_session_id'])) {
                $sessionId = $cfJson['payment_session_id'];
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Processing Cashfree...</title>
                    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
                </head>
                <body>
                    <p>Redirecting to Cashfree checkout...</p>
                    <script>
                        const cashfree = Cashfree({ mode: "<?= $isSandbox ? 'sandbox' : 'production' ?>" });
                        cashfree.checkout({ paymentSessionId: "<?= e($sessionId) ?>" });
                    </script>
                </body>
                </html>
                <?php
                exit;
            } else {
                // If test credentials return error, log and proceed with test verification
                header('Location: ' . APP_URL . '/payment-callback.php?gateway=cashfree&order_id=' . $paymentNumber);
                exit;
            }
        }

        // 4. Cash on Delivery / Drop
        if ($chosenGateway === 'cod') {
            $pdo->prepare('UPDATE payments SET payment_status = "pending" WHERE id = :id')->execute([':id' => $paymentId]);
            setFlash('success', 'Order confirmed with Cash on Delivery / Drop!');
            header('Location: ' . APP_URL . '/order-success.php?order_number=' . $refId);
            exit;
        }

        // 5. UPI Direct / QR
        if ($chosenGateway === 'upi_qr') {
            header('Location: ' . APP_URL . '/payment-callback.php?gateway=upi_qr&payment_number=' . $paymentNumber);
            exit;
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow rounded-4 p-4 p-md-5 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="badge bg-primary text-uppercase"><?= e($refType) ?> Payment</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">Select Payment Gateway</h4>
                    </div>
                    <div class="text-end">
                        <span class="fs-4 fw-bold text-primary">₹<?= number_format($amount, 2) ?></span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="process_payment" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="d-flex flex-column gap-3 mb-4">
                        <?php foreach ($activeGateways as $idx => $gw): ?>
                            <label class="card border p-3 rounded-3 cursor-pointer" style="cursor: pointer;">
                                <div class="form-check d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <input class="form-check-input" type="radio" name="gateway" value="<?= e($gw['gateway_code']) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                                        <div>
                                            <strong class="text-dark d-block"><?= e($gw['title']) ?></strong>
                                            <?php if ($gw['is_sandbox']): ?>
                                                <small class="badge bg-warning text-dark">Sandbox / Test Mode</small>
                                            <?php else: ?>
                                                <small class="text-muted">Instant &amp; 100% Encrypted</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <i class="bi bi-shield-check text-success fs-4"></i>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        Proceed to Pay ₹<?= number_format($amount, 2) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>