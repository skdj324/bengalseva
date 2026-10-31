<?php
// Template 2: Clean High-Speed Services Portal
$catStmt = $pdo->query('SELECT id, name, slug, category_type FROM categories WHERE status = "active" ORDER BY sort_order ASC LIMIT 12');
$cleanCats = $catStmt->fetchAll();
?>
<div class="bg-white py-5 border-bottom">
    <div class="container text-center py-4">
        <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold mb-3">West Bengal Services on Demand</span>
        <h1 class="display-5 fw-bold text-dark mb-3">Book Local Pros &amp; Rides Instantly</h1>
        <p class="lead text-muted mb-4">Electricians, Doctors, Cabs, Groceries &amp; Homestays across all 23 districts</p>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <form class="p-2 border rounded-4 shadow-sm bg-white d-flex gap-2" method="GET" action="<?= APP_URL ?>/">
                    <input type="text" name="q" class="form-control border-0 py-2" placeholder="What service do you need? (e.g. Plumber, Doctor, Ride)..." required>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3">Search</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <h4 class="fw-bold text-dark mb-4 text-center">Popular Categories</h4>
    <div class="row g-3">
        <?php foreach ($cleanCats as $c): ?>
            <div class="col-6 col-md-3">
                <a href="<?= APP_URL ?>/category.php?slug=<?= urlencode($c['slug']) ?>" class="card h-100 p-3 text-center border rounded-4 text-decoration-none bg-white shadow-sm hover-shadow">
                    <div class="fs-2 text-primary mb-2"><i class="bi bi-grid-fill"></i></div>
                    <div class="fw-bold text-dark"><?= e($c['name']) ?></div>
                    <small class="text-muted text-uppercase" style="font-size:0.7rem;"><?= e(str_replace('_', ' ', $c['category_type'])) ?></small>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>