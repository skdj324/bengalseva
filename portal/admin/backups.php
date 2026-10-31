<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();
$backupDir = __DIR__ . '/../../backups';

// 1. Handle Download Backup File
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filePath = $backupDir . '/' . $file;

    if (file_exists($filePath) && str_ends_with($file, '.sql')) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $file);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        setFlash('danger', 'Backup file not found.');
        header('Location: backups.php');
        exit;
    }
}

// 2. Handle Delete Backup File
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filePath = $backupDir . '/' . $file;

    if (file_exists($filePath) && str_ends_with($file, '.sql')) {
        unlink($filePath);
        logAudit($pdo, $user['id'], 'BACKUP_DELETED', 'backups', null, "Deleted backup file: {$file}");
        setFlash('info', "Backup file '{$file}' deleted.");
    }
    header('Location: backups.php');
    exit;
}

// 3. Handle Create New SQL Backup Now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $fileName = 'bengalseva_backup_' . date('Y_m_d_His') . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    $sqlDump = "-- BengalSeva Automated MariaDB Database Backup\n";
    $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Created By: " . $user['name'] . "\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_NUM);
        $sqlDump .= "-- Structure for table `{$t}`\n";
        $sqlDump .= "DROP TABLE IF EXISTS `{$t}`;\n";
        $sqlDump .= $createStmt[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $sqlDump .= "-- Data for table `{$t}`\n";
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                $vals = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string)$v);
                }, array_values($row));
                $sqlDump .= "INSERT INTO `{$t}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    if (file_put_contents($filePath, $sqlDump) !== false) {
        logAudit($pdo, $user['id'], 'BACKUP_CREATED', 'backups', null, "Created backup: {$fileName}");
        setFlash('success', "New database backup '{$fileName}' successfully generated!");
    } else {
        setFlash('danger', 'Failed to write backup file. Check directory permissions.');
    }
    header('Location: backups.php');
    exit;
}

// Scan backups directory for list
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $f) {
        if (str_ends_with($f, '.sql')) {
            $fullP = $backupDir . '/' . $f;
            $backupFiles[] = [
                'name' => $f,
                'size' => round(filesize($fullP) / 1024 / 1024, 2),
                'date' => date('d M Y, h:i A', filemtime($fullP))
            ];
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-database-fill-check text-primary me-2"></i> SQL Database Backup Manager</h3>
            <small class="text-muted">Generate instant MariaDB SQL dumps, download offline backups, and manage storage</small>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="create_backup" value="1">
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create SQL Backup Now
                </button>
            </form>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <!-- Backups List -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Generated SQL Backups (<?= count($backupFiles) ?>)</h5>
            <small class="text-muted"><i class="bi bi-shield-lock-fill text-success me-1"></i> Protected in private directory</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Backup Filename</th>
                        <th>File Size</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backupFiles)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No database backup files exist yet. Click "Create SQL Backup Now" above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $bf): ?>
                            <tr>
                                <td class="fw-bold font-monospace">
                                    <i class="bi bi-filetype-sql text-primary me-2 fs-5"></i> <?= e($bf['name']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e((string)$bf['size']) ?> MB</span></td>
                                <td class="small text-muted"><?= e($bf['date']) ?></td>
                                <td>
                                    <a href="backups.php?download=<?= urlencode($bf['name']) ?>" class="btn btn-sm btn-primary fw-bold me-1">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                    <a href="backups.php?delete=<?= urlencode($bf['name']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete backup file <?= e($bf['name']) ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>