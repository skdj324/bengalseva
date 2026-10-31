<?php
// IndiaMART B2B / Wholesale Supplier Showcase Style
$imProducts = $pdo->query('
    SELECT p.*, vp.business_name, vp.city, d.name as district_name 
    FROM products p 
    JOIN vendor_profiles vp ON p.vendor_id = vp.id 
    LEFT JOIN districts d ON vp.district_id = d.id 
    WHERE p.status = "active" 
    ORDER BY p.id DESC LIMIT 8
')->fetchAll();
?>
<!-- IndiaMART B2B Header & Instant Quotation Box -->
<div class="py-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
    <div class="container text-white py-3">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark fw-bold mb-2">B2B &amp; Direct Marketplace</span>
                <h2 class="fw-bold mb-2">Connect with Verified West Bengal Suppliers</h2>
                <p class="text-white-50 small mb-4">Direct manufacturer prices, bulk quantity quotes, and trusted suppliers across 23 districts</p>
                <div class="d-flex gap-3 small">
                    <div><i class="bi bi-shield-fill-check text-warning fs-5 me-1"></i> 100% Verified Sellers</div>
                    <div><i class="bi bi-lightning-fill text-warning fs-5 me-1"></i> Fast Quotations</div>
                </div>
            </div>

            <!-- Instant Buy Requirement Form (IndiaMART Style) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 p-4 bg-white text-dark">
                    <h5 class="fw-bold text-dark mb-1">Post Your Requirement</h5>
                    <small class="text-muted d-block mb-3">Get multiple quotes from verified suppliers in 15 mins</small>
                    <form method="POST" action="<?= APP_URL ?>/index.php">
                        <input type="hidden" name="action" value="submit_lead">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="service_name" value="B2B Bulk Quotation">

                        <div class="mb-2">
                            <input type="text" name="full_name" class="form-control" placeholder="Your Name or Company" required>
                        </div>
                        <div class="mb-2">
                            <input type="tel" name="mobile" class="form-control" placeholder="10-Digit Mobile Number" pattern="[0-9]{10}" required>
                        </div>
                        <div class="mb-3">
                            <textarea name="requirement" class="form-control" rows="2" placeholder="Enter product or service name & quantity (e.g. 10 Bags Gobindobhog rice)..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold py-2 text-dark rounded-3">
                            Get Instant Quotations &rarr;
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- IndiaMART Product Showcase Grid -->
<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Products &amp; Bulk Supplies</h5>
            <small class="text-muted">Direct from West Bengal producers and retailers</small>
        </div>
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-outline-primary btn-sm fw-bold">View Catalog &rarr;</a>
    </div>

    <div class="row g-3">
        <?php foreach ($imProducts as $p): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100 border rounded-3 p-3 bg-white shadow-sm d-flex flex-column justify-content-between">
                    <div>
                        <div class="position-relative mb-2">
                            <img src="<?= e($p['image_url'] ?: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80') ?>" style="width:100%; height:130px; object-fit:cover; border-radius:8px;">
                            <span class="badge bg-success position-absolute top-0 end-0 m-1 font-monospace" style="font-size:0.65rem;">VERIFIED</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 text-truncate"><?= e($p['name']) ?></h6>
                        <div class="fs-5 fw-bold text-primary mb-1">
                            ₹<?= number_format((float)($p['sale_price'] ?: $p['price']), 0) ?>
                            <small class="text-muted fs-6 fw-normal">/ <?= e($p['unit']) ?></small>
                        </div>
                        <small class="text-muted d-block text-truncate mb-2"><i class="bi bi-building me-1"></i> <?= e($p['business_name']) ?></small>
                        <small class="text-secondary d-block"><i class="bi bi-geo-alt me-1 text-danger"></i> <?= e($p['city'] ?? '') ?>, <?= e($p['district_name'] ?? 'WB') ?></small>
                    </div>

                    <div class="mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-primary btn-sm w-100 fw-bold" onclick="openLeadModal('Get Supplier Price', '<?= e($p['name']) ?>', 'Inquiry for <?= e($p['name']) ?> by <?= e($p['business_name']) ?>.')">
                            Contact Supplier
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>