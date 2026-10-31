<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Handle Quantity Updates / Removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $newQty = (int)($_POST['quantity'] ?? 1);
        if ($newQty <= 0) {
            unset($_SESSION['cart'][$prodId]);
        } else {
            if (isset($_SESSION['cart'][$prodId])) {
                $_SESSION['cart'][$prodId]['quantity'] = $newQty;
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $prodId = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$prodId]);
        setFlash('info', 'Item removed from cart.');
    } elseif (isset($_POST['apply_coupon'])) {
        $coupon = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if ($coupon === 'BENGAL50') {
            $_SESSION['coupon'] = [
                'code' => 'BENGAL50',
                'percent' => 50,
                'max_discount' => 150.00
            ];
            setFlash('success', 'Coupon BENGAL50 applied! 50% discount applied (Up to ₹150).');
        } else {
            unset($_SESSION['coupon']);
            setFlash('danger', 'Invalid coupon code. Try using BENGAL50.');
        }
    }
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0.0;
foreach ($cart as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

$discount = 0.0;
if (!empty($_SESSION['coupon']) && $subtotal > 0) {
    $discount = min($subtotal * 0.50, (float)$_SESSION['coupon']['max_discount']);
}
$finalTotal = max(0.0, $subtotal - $discount);

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <h3 class="fw-bold text-dark mb-4"><i class="bi bi-cart-check-fill text-primary me-2"></i> Your Shopping Cart</h3>

    <?php if (empty($cart)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <i class="bi bi-cart-x text-muted display-3 mb-3"></i>
            <h4 class="fw-bold text-dark">Your shopping cart is empty!</h4>
            <p class="text-muted small">Explore daily groceries, fresh produce, and local restaurant items.</p>
            <div>
                <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary fw-bold px-4 py-2">
                    Browse BengalSeva Mart &rarr;
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Cart Items Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= e($item['image_url'] ?: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=100&q=80') ?>" style="width:50px; height:50px; object-fit:cover; border-radius:8px;">
                                                <div>
                                                    <div class="fw-bold text-dark"><?= e($item['name']) ?></div>
                                                    <small class="text-muted">Sold by: <?= e($item['vendor_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fw-semibold">&#8377;<?= number_format((float)$item['price'], 0) ?></td>
                                        <td style="width: 140px;">
                                            <form method="POST" class="d-flex align-items-center gap-1">
                                                <input type="hidden" name="update_qty" value="1">
                                                <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                                <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>" min="1" max="50" class="form-control form-control-sm text-center" onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td class="fw-bold text-primary">
                                            &#8377;<?= number_format((float)$item['price'] * (int)$item['quantity'], 0) ?>
                                        </td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="remove_item" value="1">
                                                <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Cart Summary & Promo Code -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-3">
                    <h5 class="fw-bold text-dark mb-3">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold">&#8377;<?= number_format($subtotal, 2) ?></span>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success fw-bold">
                            <span>Promo Discount (<?= e($_SESSION['coupon']['code']) ?>)</span>
                            <span>-&#8377;<?= number_format($discount, 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Estimated Delivery</span>
                        <span class="text-success fw-bold">FREE</span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between fs-5 fw-bold mb-4">
                        <span>Grand Total</span>
                        <span class="text-primary">&#8377;<?= number_format($finalTotal, 2) ?></span>
                    </div>

                    <!-- Promo Code Form -->
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="apply_coupon" value="1">
                        <label class="form-label small fw-semibold text-muted">Have a Promo Code?</label>
                        <div class="input-group">
                            <input type="text" name="coupon_code" class="form-control text-uppercase" placeholder="Try BENGAL50" value="<?= e($_SESSION['coupon']['code'] ?? '') ?>">
                            <button type="submit" class="btn btn-dark fw-bold">Apply</button>
                        </div>
                    </form>

                    <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Proceed to Checkout &rarr;
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
