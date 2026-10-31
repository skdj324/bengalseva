<?php
declare(strict_types=1);

/**
 * BengalSeva Permission Engine
 * Verifies if a role has access to specific dashboard widgets and modules
 */
function hasPermission(string $role, string $permissionKey, ?PDO $pdo = null): bool
{
    // Super Admin and Admin always have 100% access to everything
    if ($role === 'super_admin' || $role === 'admin') {
        return true;
    }

    static $cache = [];
    $cacheKey = "{$role}:{$permissionKey}";

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if ($pdo === null) {
        $pdo = Database::getConnection();
    }

    try {
        $stmt = $pdo->prepare('SELECT is_allowed FROM role_permissions WHERE role = :role AND permission_key = :key LIMIT 1');
        $stmt->execute([':role' => $role, ':key' => $permissionKey]);
        $row = $stmt->fetch();

        $allowed = $row ? ((int)$row['is_allowed'] === 1) : false;
        $cache[$cacheKey] = $allowed;
        return $allowed;
    } catch (PDOException $e) {
        return false;
    }
}