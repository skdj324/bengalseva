<?php
// Template 3: E-Commerce, Grocery & Store Showcase
$pStmt = $pdo->query('
    SELECT p.*, vp.business_name 
    FROM products p 
    JOIN vendor_profiles vp ON p.vendor_id = vp.id 
    WHERE p.status = "active" 
    ORDER BY p.id DESC LIMIT 8
');
$martProducts = $pStmt->fetchAll();
?>
<div class="bg-primary text-white py-5" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
    <div class="container py-4 text-center">
        <span class="badge bg-warning text-dark px-3 py-1 fw-bold rounded-pill mb-2">BengalSeva Mart &amp; Daily Needs</span>
        <h1 class="display-5 fw-bold mb-3">Fresh Grocery &amp; Local Deliveries</h1>
        <p class="lead text-white-50 mb-4">Doorstep deliveries in 45 minutes across West Bengal with coupon BENGAL50</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= APP_URL ?>/shop.php" class="btn btn-warning btn-lg fw-bold px-4 text-dark">Shop Now (50% Off)</a>
            <a href="<?= APP_URL ?>/ride.php" class="btn btn-outline-light btn-lg fw-bold px-4">Book Bike Taxi</a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">Featured Products &amp; Groceries</h4>
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-outline-primary btn-sm fw-bold">View All Products &rarr;</a>
    </div>

    <div class="row g-3">
        <?php foreach ($martProducts as $p): ?>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-2 bg-white d-flex flex-column justify-content-between">
                    <div>
                        <img src="<?= e($p['image_url'] ?: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80') ?>" style="width:100%; height:140px; object-fit:cover; border-radius:10px;">
                        <h6 class="fw-bold text-dark mt-2 mb-1 text-truncate"><?= e($p['name']) ?></h6>
                        <small class="text-muted d-block mb-2"><?= e($p['unit']) ?></small>
                        <div class="fs-5 fw-bold text-primary">₹<?= number_format((float)($p['sale_price'] ?: $p['price']), 0) ?></div>
                    </div>
                    <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary btn-sm w-100 fw-bold rounded-2 mt-2">Add to Cart</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>