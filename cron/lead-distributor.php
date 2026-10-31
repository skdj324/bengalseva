<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

// Security: Allow CLI execution OR web execution with secret key
$isCli = (php_sapi_name() === 'cli');
$cronKeyStmt = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = "cron_secret_key" LIMIT 1');
$cronSecret = $cronKeyStmt->fetchColumn() ?: 'bengalseva_secret';

if (!$isCli && (($_GET['key'] ?? '') !== $cronSecret)) {
    http_response_code(403);
    die('Unauthorized cron access.');
}

$log = [];

// 1. Auto-assign unassigned customer leads to the highest-rated vendor in that district
$unassignedLeads = $pdo->query('
    SELECT l.id, l.district_id, l.category_id, l.full_name 
    FROM leads l 
    WHERE l.vendor_id IS NULL AND l.status = "new" AND l.created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    LIMIT 20
')->fetchAll();

$assignedCount = 0;
foreach ($unassignedLeads as $lead) {
    // Find best vendor
    $vStmt = $pdo->prepare('
        SELECT id, user_id FROM vendor_profiles 
        WHERE district_id = :did AND category_id = :cid AND status = "approved" 
        ORDER BY rating DESC, id ASC LIMIT 1
    ');
    $vStmt->execute([':did' => $lead['district_id'], ':cid' => $lead['category_id']]);
    $bestVendor = $vStmt->fetch();

    if ($bestVendor) {
        $pdo->prepare('UPDATE leads SET vendor_id = :vid, status = "assigned" WHERE id = :lid')
            ->execute([':vid' => $bestVendor['id'], ':lid' => $lead['id']]);

        // Notify Vendor
        $pdo->prepare('
            INSERT INTO notifications (user_id, title, message, link_url, created_at)
            VALUES (:uid, "New Lead Assigned!", :msg, "/portal/vendor/dashboard.php", NOW())
        ')->execute([
            ':uid' => $bestVendor['user_id'],
            ':msg' => "Customer inquiry for {$lead['full_name']} has been routed to you."
        ]);
        $assignedCount++;
    }
}
$log[] = "Auto-assigned {$assignedCount} pending leads.";

// 2. Auto-cancel abandoned ride requests older than 15 minutes
$rideCancelStmt = $pdo->exec('
    UPDATE rides 
    SET status = "cancelled" 
    WHERE status = "requested" AND created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
');
$log[] = "Auto-cancelled {$rideCancelStmt} unaccepted ride requests.";

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'status'    => 'success',
    'actions'   => $log
]);