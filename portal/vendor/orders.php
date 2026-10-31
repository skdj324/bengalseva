<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['vendor', 'super_admin', 'admin']);
$user = Auth::user();

// Resolve vendor profile
$vStmt = $pdo->prepare('SELECT * FROM vendor_profiles WHERE user_id = :uid LIMIT 1');
$vStmt->execute([':uid' => $user['id']]);
$profile = $vStmt->fetch();

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $allowed = ['placed', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

    if (in_array($newStatus, $allowed, true) && $orderId > 0) {
        $up = $pdo->prepare('UPDATE orders SET order_status = :st WHERE id = :id');
        $up->execute([':st' => $newStatus, ':id' => $orderId]);
        logAudit($pdo, $user['id'], 'ORDER_STATUS_UPDATE', 'orders', $orderId, "Order status changed to {$newStatus}");
        setFlash('success', 'Order status updated to ' . strtoupper($newStatus));
        header('Location: orders.php');
        exit;
    }
}

// Fetch Orders for this vendor
$orders = [];
if ($profile) {
    $oStmt = $pdo->prepare('
        SELECT o.*, d.name as district_name 
        FROM orders o
        LEFT JOIN districts d ON o.district_id = d.id
        WHERE o.vendor_id = :vid 
        ORDER BY o.id DESC
    ');
    $oStmt->execute([':vid' => $profile['id']]);
    $orders = $oStmt->fetchAll();
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1"><?= e($profile['business_name'] ?? $user['name']) ?></h3>
                <span class="badge bg-success text-uppercase">Vendor Grocery &amp; Product Orders</span>
            </div>
            <a href="<?= APP_URL ?>/portal/vendor/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Leads</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam-fill text-primary me-2"></i> Incoming Orders (<?= count($orders) ?>)</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Delivery Address</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No orders received yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="fw-bold">#<?= e($o['order_number']) ?></td>
                                <td><?= e($o['customer_name']) ?></td>
                                <td><a href="tel:<?= e($o['customer_phone']) ?>" class="text-decoration-none fw-semibold"><?= e($o['customer_phone']) ?></a></td>
                                <td class="small text-muted" style="max-width: 220px;"><?= e($o['delivery_address']) ?>, <?= e($o['district_name']) ?></td>
                                <td class="fw-bold text-primary">&#8377;<?= number_format((float)$o['final_amount'], 2) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= strtoupper(e($o['payment_method'])) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $o['order_status'] === 'delivered' ? 'success' : ($o['order_status'] === 'placed' ? 'primary' : 'warning text-dark') ?> text-uppercase">
                                        <?= e($o['order_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="update_order_status" value="1">
                                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Change Status</option>
                                            <option value="confirmed">Confirm</option>
                                            <option value="preparing">Preparing</option>
                                            <option value="out_for_delivery">Out for Delivery</option>
                                            <option value="delivered">Delivered</option>
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
