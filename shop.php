<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$catSlug = sanitizeInput($_GET['category'] ?? '');
$searchQuery = sanitizeInput($_GET['q'] ?? '');
$districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : null;

// Handle Add To Cart Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $prodId = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    $pStmt = $pdo->prepare('SELECT p.*, vp.business_name FROM products p JOIN vendor_profiles vp ON p.vendor_id = vp.id WHERE p.id = :id AND p.status = "active" LIMIT 1');
    $pStmt->execute([':id' => $prodId]);
    $product = $pStmt->fetch();

    if ($product) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $currentPrice = $product['sale_price'] ? (float)$product['sale_price'] : (float)$product['price'];

        if (isset($_SESSION['cart'][$prodId])) {
            $_SESSION['cart'][$prodId]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$prodId] = [
                'id'            => $prodId,
                'name'          => $product['name'],
                'price'         => $currentPrice,
                'unit'          => $product['unit'],
                'vendor_id'     => (int)$product['vendor_id'],
                'vendor_name'   => $product['business_name'],
                'quantity'      => $qty,
                'image_url'     => $product['image_url']
            ];
        }

        setFlash('success', e($product['name']) . ' added to your shopping cart!');
        header('Location: shop.php' . ($catSlug ? '?category=' . urlencode($catSlug) : ''));
        exit;
    }
}

// Build Products Query
$sql = '
    SELECT p.*, c.name as category_name, vp.business_name, vp.city, d.name as district_name 
    FROM products p
    JOIN vendor_profiles vp ON p.vendor_id = vp.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN districts d ON vp.district_id = d.id
    WHERE p.status = "active" AND vp.status = "approved"
';
$params = [];

if ($catSlug) {
    $sql .= ' AND c.slug = :cslug';
    $params[':cslug'] = $catSlug;
}

if ($districtId) {
    $sql .= ' AND vp.district_id = :did';
    $params[':did'] = $districtId;
}

if ($searchQuery) {
    $sql .= ' AND (p.name LIKE :q OR p.description LIKE :q)';
    $params[':q'] = '%' . $searchQuery . '%';
}

$sql .= ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch Categories for sidebar/tabs
$categories = $pdo->query('SELECT id, name, slug FROM categories WHERE category_type IN ("product_sale", "hybrid", "lead_only") ORDER BY sort_order ASC')->fetchAll();

require_once __DIR__ . '/templates/header.php';
?>

<div class="bg-white border-bottom py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-bag-heart-fill text-danger me-2"></i> BengalSeva Mart &amp; Grocery</h3>
            <small class="text-muted">Fresh local produce, groceries &amp; daily staples across West Bengal</small>
        </div>
        <a href="<?= APP_URL ?>/cart.php" class="btn btn-primary fw-bold position-relative">
            <i class="bi bi-cart3 me-1"></i> View Cart
            <?php 
                $cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
                if ($cartCount > 0): 
            ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $cartCount ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="container mb-5">
    <!-- Filter and Search Row -->
    <div class="card border-0 shadow-sm p-3 mb-4 bg-white rounded-3">
        <form method="GET" action="shop.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search rice, tea, oil, snacks..." value="<?= e($searchQuery) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100">Filter</button>
                <a href="shop.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    <div class="row g-3">
        <?php if (empty($products)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
                    <i class="bi bi-basket3 text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold text-dark">No products match your search.</h5>
                    <p class="text-muted small">Try changing your search keywords or category filter.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden bg-white d-flex flex-column justify-content-between p-2">
                        <div>
                            <div class="position-relative rounded-2 overflow-hidden bg-light" style="height: 160px;">
                                <img src="<?= e($p['image_url'] ?: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80') ?>" alt="<?= e($p['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php if ($p['sale_price']): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-2 fw-bold">SAVE &#8377;<?= number_format((float)$p['price'] - (float)$p['sale_price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="p-2">
                                <small class="text-muted d-block text-truncate"><?= e($p['business_name']) ?> &bull; <?= e($p['city'] ?? 'WB') ?></small>
                                <h6 class="fw-bold text-dark mb-1 text-truncate mt-1"><?= e($p['name']) ?></h6>
                                <small class="text-muted d-block mb-2">Unit: <?= e($p['unit']) ?></small>
                                
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="fs-5 fw-bold text-primary">
                                        &#8377;<?= number_format($p['sale_price'] ? (float)$p['sale_price'] : (float)$p['price'], 0) ?>
                                    </span>
                                    <?php if ($p['sale_price']): ?>
                                        <del class="text-muted small">&#8377;<?= number_format((float)$p['price'], 0) ?></del>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="p-2 pt-0">
                            <form method="POST" action="shop.php">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-2">
                                    <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
