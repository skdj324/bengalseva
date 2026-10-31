<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit feedback.']);
    exit;
}

$user = Auth::user();
$vendorId = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
$entityType = sanitizeInput($_POST['entity_type'] ?? 'vendor');
$entityId = (int)($_POST['entity_id'] ?? 0);
$rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));
$reviewText = sanitizeInput($_POST['review_text'] ?? '');

if ($entityId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review target.']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO reviews (user_id, vendor_id, entity_type, entity_id, rating, review_text, created_at)
        VALUES (:uid, :vid, :etype, :eid, :rating, :rtext, NOW())
    ');
    $stmt->execute([
        ':uid'    => $user['id'],
        ':vid'    => $vendorId,
        ':etype'  => $entityType,
        ':eid'    => $entityId,
        ':rating' => $rating,
        ':rtext'  => $reviewText
    ]);

    // Update Vendor Aggregate Rating
    if ($vendorId) {
        $avgStmt = $pdo->prepare('SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE vendor_id = :vid');
        $avgStmt->execute([':vid' => $vendorId]);
        $stats = $avgStmt->fetch();

        if ($stats) {
            $up = $pdo->prepare('UPDATE vendor_profiles SET rating = :r, reviews_count = :rc WHERE id = :id');
            $up->execute([
                ':r'  => round((float)$stats['avg_r'], 2),
                ':rc' => (int)$stats['cnt'],
                ':id' => $vendorId
            ]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Thank you! Your verified rating has been published.']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}