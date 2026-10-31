<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$orderNum = sanitizeInput($_GET['order_number'] ?? '');

$oStmt = $pdo->prepare('
    SELECT o.*, vp.business_name, d.name as district_name 
    FROM orders o
    LEFT JOIN vendor_profiles vp ON o.vendor_id = vp.id
    LEFT JOIN districts d ON o.district_id = d.id
    WHERE o.order_number = :onum 
    LIMIT 1
');
$oStmt->execute([':onum' => $orderNum]);
$order = $oStmt->fetch();

if (!$order) {
    header('Location: ' . APP_URL . '/shop.php');
    exit;
}

$iStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid');
$iStmt->execute([':oid' => $order['id']]);
$items = $iStmt->fetchAll();

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white text-center">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success display-3"></i>
                </div>
                <h3 class="fw-bold text-dark mb-1">Order Confirmed!</h3>
                <p class="text-muted small">Thank you for ordering on BengalSeva. Your order has been dispatched to the local fulfillment partner.</p>

                <div class="bg-light p-3 rounded-3 text-start my-3">
                    <div class="row g-2 small">
                        <div class="col-sm-6"><strong>Order Number:</strong> <?= e($order['order_number']) ?></div>
                        <div class="col-sm-6"><strong>Payment Mode:</strong> <?= strtoupper(e($order['payment_method'])) ?></div>
                        <div class="col-sm-6"><strong>Fulfillment Partner:</strong> <?= e($order['business_name']) ?></div>
                        <div class="col-sm-6"><strong>Status:</strong> <span class="badge bg-primary text-uppercase"><?= e($order['order_status']) ?></span></div>
                    </div>
                </div>

                <div class="text-start my-3">
                    <h6 class="fw-bold mb-2">Order Summary</h6>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($items as $it): ?>
                            <li class="list-group-item d-flex justify-content-between px-0 small">
                                <span><?= e($it['product_name']) ?> &times; <?= (int)$it['quantity'] ?></span>
                                <span class="fw-bold">&#8377;<?= number_format((float)$it['subtotal'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <li class="list-group-item d-flex justify-content-between px-0 fw-bold border-top">
                            <span>Total Paid / Payable</span>
                            <span class="text-primary">&#8377;<?= number_format((float)$order['final_amount'], 2) ?></span>
                        </li>
                    </ul>
                </div>

                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="<?= APP_URL ?>/shop.php" class="btn btn-outline-primary fw-bold px-4">Continue Shopping</a>
                    <?php if (Auth::check()): ?>
                        <a href="<?= APP_URL ?>/portal/customer/dashboard.php" class="btn btn-primary fw-bold px-4">Track in Portal</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
