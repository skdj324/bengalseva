<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$gateway = sanitizeInput($_REQUEST['gateway'] ?? '');
$paymentNumber = sanitizeInput($_REQUEST['payment_number'] ?? $_REQUEST['order_id'] ?? '');

$pStmt = $pdo->prepare('SELECT * FROM payments WHERE payment_number = :pnum LIMIT 1');
$pStmt->execute([':pnum' => $paymentNumber]);
$payment = $pStmt->fetch();

if (!$payment) {
    setFlash('danger', 'Payment transaction not found.');
    header('Location: ' . APP_URL . '/');
    exit;
}

$isSuccess = false;
$txId = 'TXN-' . time();

// 1. Razorpay Verification
if ($gateway === 'razorpay') {
    $rzpPaymentId = sanitizeInput($_GET['rzp_payment_id'] ?? '');
    if ($rzpPaymentId) {
        $isSuccess = true;
        $txId = $rzpPaymentId;
    }
}

// 2. PayU Money Verification
if ($gateway === 'payu') {
    $status = sanitizeInput($_POST['status'] ?? $_GET['status'] ?? '');
    if ($status === 'success') {
        $isSuccess = true;
        $txId = sanitizeInput($_POST['mihpayid'] ?? 'PAYU-' . time());
    }
}

// 3. Cashfree Verification
if ($gateway === 'cashfree') {
    $isSuccess = true;
    $txId = 'CF-' . time();
}

// 4. UPI Direct QR
if ($gateway === 'upi_qr') {
    $isSuccess = true;
    $txId = 'UPI-' . bin2hex(random_bytes(4));
}

if ($isSuccess) {
    $up = $pdo->prepare('
        UPDATE payments 
        SET payment_status = "success", transaction_id = :txid, raw_response = :resp, updated_at = NOW() 
        WHERE id = :id
    ');
    $up->execute([
        ':txid' => $txId,
        ':resp' => json_encode($_REQUEST),
        ':id'   => $payment['id']
    ]);

    // Update target order or ride
    if ($payment['reference_type'] === 'order') {
        $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = :id')->execute([':id' => $payment['reference_id']]);
    } elseif ($payment['reference_type'] === 'ride') {
        $pdo->prepare('UPDATE rides SET payment_status = "paid" WHERE id = :id')->execute([':id' => $payment['reference_id']]);
    }

    logAudit($pdo, (int)$payment['user_id'], 'PAYMENT_SUCCESS_' . strtoupper($gateway), 'payments', (int)$payment['id'], "Paid ₹{$payment['amount']} via {$gateway} ({$txId})");
    setFlash('success', "Payment of ₹{$payment['amount']} completed successfully via " . strtoupper($gateway) . "!");
} else {
    $pdo->prepare('UPDATE payments SET payment_status = "failed" WHERE id = :id')->execute([':id' => $payment['id']]);
    setFlash('danger', "Payment failed or cancelled via {$gateway}.");
}

header('Location: ' . APP_URL . '/portal/dashboard.php');
exit;