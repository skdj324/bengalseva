<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Homepage -->
    <url>
        <loc><?= APP_URL ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/shop.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/ride.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/travel.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/properties.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/jobs.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= APP_URL ?>/support.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Categories -->
    <?php
    $cats = $pdo->query('SELECT slug FROM categories WHERE status = "active"')->fetchAll();
    foreach ($cats as $c):
    ?>
    <url>
        <loc><?= APP_URL ?>/category.php?slug=<?= urlencode($c['slug']) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>

    <!-- Approved Vendors -->
    <?php
    $vendors = $pdo->query('SELECT id FROM vendor_profiles WHERE status = "approved"')->fetchAll();
    foreach ($vendors as $v):
    ?>
    <url>
        <loc><?= APP_URL ?>/vendor.php?id=<?= (int)$v['id'] ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>
</urlset>