<?php
declare(strict_types=1);

/**
 * Log activity directly to existing `audit_logs` table
 */
function logAudit(
    PDO $pdo,
    ?int $userId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null
): void {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare('
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, details, created_at)
            VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :details, NOW())
        ');
        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => substr($action, 0, 120),
            ':entity_type' => $entityType ? substr($entityType, 0, 80) : null,
            ':entity_id'   => $entityId,
            ':ip_address'  => substr($ipAddress, 0, 45),
            ':details'     => $details
        ]);
    } catch (PDOException $e) {
        error_log('[Audit Log Failure] ' . $e->getMessage());
    }
}
