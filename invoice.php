<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$type = sanitizeInput($_GET['type'] ?? 'order');
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid invoice request.');
}

// Fetch Order or Booking Details
if ($type === 'order') {
    $stmt = $pdo->prepare('
        SELECT o.*, vp.business_name, vp.city, d.name as district_name 
        FROM orders o 
        LEFT JOIN vendor_profiles vp ON o.vendor_id = vp.id 
        LEFT JOIN districts d ON o.district_id = d.id 
        WHERE o.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $invoiceData = $stmt->fetch();

    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
    $itemsStmt->execute([':id' => $id]);
    $items = $itemsStmt->fetchAll();
} else {
    $stmt = $pdo->prepare('
        SELECT b.*, b.customer_phone, vp.business_name, vp.city, si.title as service_title, si.price 
        FROM bookings b 
        LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id 
        LEFT JOIN service_items si ON b.service_item_id = si.id 
        WHERE b.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $invoiceData = $stmt->fetch();
    $items = [];
}

if (!$invoiceData) {
    die('Invoice record not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice - BengalSeva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: system-ui, sans-serif; }
        .invoice-card { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        @media print {
            body { background: #fff; }
            .invoice-card { box-shadow: none; margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="invoice-card">
        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4">
            <div>
                <h3 class="fw-bold mb-0 text-primary">Bengal<span class="text-warning">Seva</span></h3>
                <small class="text-muted">West Bengal Premier Multi-Vertical Marketplace</small>
            </div>
            <div class="text-end">
                <h5 class="fw-bold mb-0">TAX INVOICE</h5>
                <small class="text-muted">Date: <?= date('d M Y') ?></small>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6">
                <small class="text-muted d-block fw-bold">BILLED TO:</small>
                <div class="fw-bold text-dark fs-6"><?= e($invoiceData['customer_name']) ?></div>
                <div class="small text-muted"><?= e($invoiceData['customer_phone'] ?? '') ?></div>
                <div class="small text-secondary"><?= e($invoiceData['delivery_address'] ?? 'West Bengal') ?></div>
            </div>
            <div class="col-6 text-end">
                <small class="text-muted d-block fw-bold">SUPPLIED BY:</small>
                <div class="fw-bold text-dark fs-6"><?= e($invoiceData['business_name'] ?? 'BengalSeva Partner') ?></div>
                <div class="small text-muted"><?= e($invoiceData['city'] ?? 'Kolkata') ?>, West Bengal</div>
                <div class="small text-muted">GSTIN: 19AAACB1234F1Z5</div>
            </div>
        </div>

        <table class="table table-bordered align-middle mb-4">
            <thead class="table-light small">
                <tr>
                    <th>Item / Service Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($type === 'order'): ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['product_name']) ?></td>
                            <td class="text-center"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end">₹<?= number_format((float)$it['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float)$it['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><?= e($invoiceData['service_title'] ?? 'Medical Consultation') ?></td>
                        <td class="text-center">1</td>
                        <td class="text-end">₹<?= number_format((float)$invoiceData['total_amount'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format((float)$invoiceData['total_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Grand Total Paid:</th>
                    <th class="text-end fs-5 text-primary">₹<?= number_format((float)($invoiceData['final_amount'] ?? $invoiceData['total_amount']), 2) ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <small class="text-muted">This is a computer-generated tax invoice. No signature required.</small>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-primary fw-bold px-4">
                    Print / Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>