<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin', 'manager', 'accountant', 'agent', 'support']);

$filename = 'bengalseva_crm_logs_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Log ID', 'Created At', 'Staff Member', 'Entity Type', 'Reference ID',
    'Customer Name', 'Customer Phone', 'Channel', 'Call Outcome', 'Priority',
    'Next Follow-Up Scheduled', 'Staff Remarks / Notes'
]);

$stmt = $pdo->query('SELECT * FROM crm_followups ORDER BY id DESC LIMIT 2000');
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['created_at'],
        $row['staff_name'],
        strtoupper($row['entity_type']),
        $row['entity_id'],
        $row['customer_name'] ?? 'N/A',
        $row['customer_phone'] ?? 'N/A',
        strtoupper($row['channel']),
        ucwords(str_replace('_', ' ', $row['outcome'])),
        strtoupper($row['priority']),
        $row['next_followup_at'] ? date('d M Y, h:i A', strtotime($row['next_followup_at'])) : 'None',
        $row['notes']
    ]);
}

fclose($output);
exit;