<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!Auth::check()) {
    echo json_encode(['success' => false]);
    exit;
}

$user = Auth::user();
$notifId = (int)($_POST['id'] ?? 0);

if ($notifId > 0) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid')
        ->execute([':id' => $notifId, ':uid' => $user['id']]);
} else {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid')
        ->execute([':uid' => $user['id']]);
}

echo json_encode(['success' => true]);