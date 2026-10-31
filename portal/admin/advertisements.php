<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle New Advertisement / Banner Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_advertisement'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $client = sanitizeInput($_POST['client_name'] ?? '');
    $phone = sanitizeInput($_POST['client_phone'] ?? '');
    $slot = sanitizeInput($_POST['slot_position'] ?? 'homepage_middle');
    $adType = sanitizeInput($_POST['ad_type'] ?? 'custom_banner');
    $targetUrl = sanitizeInput($_POST['target_url'] ?? '');
    $codeSnippet = $_POST['code_snippet'] ?? '';
    $status = sanitizeInput($_POST['status'] ?? 'active');

    // Handle Banner Image Upload
    $bannerImg = null;
    if (!empty($_FILES['banner_file']['name']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed, true)) {
            $destName = 'ad_' . time() . '.' . $ext;
            $destPath = __DIR__ . '/../../uploads/branding/' . $destName;
            if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $destPath)) {
                $bannerImg = APP_URL . '/uploads/branding/' . $destName;
            }
        }
    }

    if ($title) {
        $stmt = $pdo->prepare('
            INSERT INTO advertisements (title, client_name, client_phone, slot_position, ad_type, banner_image, target_url, code_snippet, status, created_at)
            VALUES (:t, :c, :p, :slot, :atype, :img, :url, :code, :st, NOW())
        ');
        $stmt->execute([
            ':t'     => $title,
            ':c'     => $client,
            ':p'     => $phone,
            ':slot'  => $slot,
            ':atype' => $adType,
            ':img'   => $bannerImg,
            ':url'   => $targetUrl,
            ':code'  => $codeSnippet,
            ':st'    => $status
        ]);

        logAudit($pdo, $user['id'], 'ADVERTISEMENT_CREATED', 'advertisements', (int)$pdo->lastInsertId(), "Created Ad: {$title} for client {$client}");
        setFlash('success', "Advertisement '{$title}' published successfully!");
        header('Location: advertisements.php');
        exit;
    }
}

// Handle Delete Ad
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $pdo->prepare('DELETE FROM advertisements WHERE id = :id')->execute([':id' => $delId]);
    setFlash('info', 'Advertisement removed.');
    header('Location: advertisements.php');
    exit;
}

$ads = $pdo->query('SELECT * FROM advertisements ORDER BY id DESC')->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-badge-ad-fill text-warning me-2"></i> Website Advertising &amp; Banners Manager</h3>
            <small class="text-muted">Earn revenue by placing client promotional banners or Google AdSense ad scripts</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4">
        <!-- Add Ad Form -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-plus-circle text-primary me-1"></i> Place New Advertisement</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="save_advertisement" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Campaign / Ad Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Apex Hospital 30% Health Checkup" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Client / Business Name</label>
                            <input type="text" name="client_name" class="form-control" placeholder="e.g. Apex Hospital">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Client Phone Number</label>
                            <input type="text" name="client_phone" class="form-control" placeholder="10 Digits">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Ad Slot Position</label>
                        <select name="slot_position" class="form-select">
                            <option value="homepage_middle">Homepage Middle Banner (High Traffic)</option>
                            <option value="header_top">Header Top Banner</option>
                            <option value="category_sidebar">Category &amp; Directory Sidebar</option>
                            <option value="footer_top">Footer Top Banner</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Ad Format Type</label>
                        <select name="ad_type" class="form-select" id="adTypeSelect" onchange="toggleAdFields(this.value)">
                            <option value="custom_banner">Custom Uploaded Banner Image + Link</option>
                            <option value="adsense_code">Google AdSense / HTML Script Code</option>
                        </select>
                    </div>

                    <!-- Custom Banner Fields -->
                    <div id="bannerUploadField" class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Upload Banner Image (JPG / PNG / WebP)</label>
                        <input type="file" name="banner_file" class="form-control" accept="image/*">
                        <div class="mt-2">
                            <label class="form-label small fw-semibold text-muted">Click Destination URL</label>
                            <input type="url" name="target_url" class="form-control" placeholder="https://clientwebsite.com or /category.php">
                        </div>
                    </div>

                    <!-- AdSense Code Snippet -->
                    <div id="adsenseCodeField" class="mb-3 d-none">
                        <label class="form-label small fw-semibold text-muted">Google AdSense / HTML Code</label>
                        <textarea name="code_snippet" class="form-control font-monospace" rows="4" placeholder="<script async src='https://pagead2.googlesyndication.com...'></script>"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active (Displaying on Website)</option>
                            <option value="inactive">Inactive / Expired</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-sm">
                        Publish Advertisement Live
                    </button>
                </form>
            </div>
        </div>

        <!-- Active Ads Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">Running Advertisements (<?= count($ads) ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Ad Campaign</th>
                                <th>Position</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ads)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No ads currently running. Place your first ad campaign on the left.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ads as $ad): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($ad['title']) ?></strong>
                                            <small class="text-muted">Client: <?= e($ad['client_name'] ?? 'Self') ?> (<?= e($ad['client_phone'] ?? '-') ?>)</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e(str_replace('_', ' ', $ad['slot_position'])) ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?= strtoupper(e($ad['ad_type'])) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $ad['status'] === 'active' ? 'success' : 'secondary' ?> text-uppercase">
                                                <?= e($ad['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="advertisements.php?delete_id=<?= (int)$ad['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete advertisement <?= e($ad['title']) ?>?')">
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
    </div>
</div>

<script>
function toggleAdFields(val) {
    if (val === 'adsense_code') {
        document.getElementById('bannerUploadField').classList.add('d-none');
        document.getElementById('adsenseCodeField').classList.remove('d-none');
    } else {
        document.getElementById('bannerUploadField').classList.remove('d-none');
        document.getElementById('adsenseCodeField').classList.add('d-none');
    }
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>