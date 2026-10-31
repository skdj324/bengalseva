<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branding'])) {
    $siteName = sanitizeInput($_POST['site_name'] ?? 'BengalSeva');
    $siteTagline = sanitizeInput($_POST['site_tagline'] ?? '');
    $selectedTemplate = sanitizeInput($_POST['homepage_template'] ?? 'template_default');
    $announceText = sanitizeInput($_POST['top_announcement_text'] ?? '');
    $announceOn = isset($_POST['top_announcement_enabled']) ? '1' : '0';
    $supportWa = sanitizeInput($_POST['footer_support_whatsapp'] ?? '9830000000');

    // 1. Handle Logo Upload
    $logoUrl = null;
    if (!empty($_FILES['site_logo']['name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowedExt, true)) {
            $destLogoName = 'logo_' . time() . '.png';
            $destPath = __DIR__ . '/../../uploads/branding/' . $destLogoName;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $destPath)) {
                $logoUrl = APP_URL . '/uploads/branding/' . $destLogoName;
            }
        }
    }

    // 2. Handle Custom Notification Bell Audio File Upload
    if (!empty($_FILES['custom_bell_sound']['name']) && $_FILES['custom_bell_sound']['error'] === UPLOAD_ERR_OK) {
        $audioExt = strtolower(pathinfo($_FILES['custom_bell_sound']['name'], PATHINFO_EXTENSION));
        $allowedAudio = ['mp3', 'wav', 'ogg', 'm4a'];

        if (in_array($audioExt, $allowedAudio, true)) {
            $soundTarget = __DIR__ . '/../../assets/sounds/alert.mp3';
            if (move_uploaded_file($_FILES['custom_bell_sound']['tmp_name'], $soundTarget)) {
                logAudit($pdo, $user['id'], 'AUDIO_BELL_UPDATED', 'settings', null, 'Uploaded new alert.mp3 sound file.');
            }
        }
    }

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'site_name', ':v' => $siteName]);
    $up->execute([':k' => 'site_tagline', ':v' => $siteTagline]);
    $up->execute([':k' => 'homepage_template', ':v' => $selectedTemplate]);
    $up->execute([':k' => 'top_announcement_text', ':v' => $announceText]);
    $up->execute([':k' => 'top_announcement_enabled', ':v' => $announceOn]);
    $up->execute([':k' => 'footer_support_whatsapp', ':v' => $supportWa]);

    if ($logoUrl) {
        $up->execute([':k' => 'site_logo_url', ':v' => $logoUrl]);
    }

    setFlash('success', 'Settings updated! Custom logo, sound bell, and templates saved.');
    header('Location: branding.php');
    exit;
}

$brandSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-palette-fill text-primary me-2"></i> Branding &amp; Audio Bell Manager</h3>
            <small class="text-muted">Manage website logo, customize notification sound (alert.mp3), and switch homepage templates</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="save_branding" value="1">

                    <!-- Notification Bell Sound Uploader Section -->
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-bell-fill me-1"></i> Order &amp; Ride Audio Bell Sound</h5>
                    <div class="p-3 bg-light rounded-4 mb-4 border border-2 border-success border-opacity-50">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <strong class="d-block text-dark">Current Alert Sound File:</strong>
                                <code class="small text-primary">public_html/assets/sounds/alert.mp3</code>
                            </div>
                            <!-- Test Sound Button -->
                            <button type="button" class="btn btn-sm btn-success fw-bold px-3 d-flex align-items-center gap-1 shadow-sm" onclick="playCurrentAlertSound()">
                                <i class="bi bi-volume-up-fill fs-5"></i> Test Alert Sound
                            </button>
                        </div>
                        <label class="form-label small fw-semibold text-muted">Upload New Sound File (MP3 / WAV / OGG)</label>
                        <input type="file" name="custom_bell_sound" class="form-control" accept="audio/*">
                        <small class="text-muted mt-1 d-block">Aap file manager me jakar <code>assets/sounds/alert.mp3</code> ko bhi directly replace kar sakte hain.</small>
                    </div>

                    <!-- Template Switcher -->
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-layout-wtf me-1"></i> Choose Active Homepage Template Style</h5>
                    <div class="row g-3 mb-4">
                        <?php $activeTpl = $brandSettings['homepage_template'] ?? 'template_justdial'; ?>
                        
                        <div class="col-md-6">
                            <label class="card h-100 p-3 border rounded-3 cursor-pointer <?= $activeTpl === 'template_justdial' ? 'border-primary bg-light shadow-sm' : '' ?>" style="cursor: pointer;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="homepage_template" value="template_justdial" <?= $activeTpl === 'template_justdial' ? 'checked' : '' ?>>
                                    <strong class="d-block text-dark mt-1 fs-6"><i class="bi bi-search text-danger me-1"></i> Justdial Style (Desktop Portal)</strong>
                                    <small class="text-muted d-block mt-1">Dual search bar, 16 category icons strip, local directory &amp; best deal buttons.</small>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="card h-100 p-3 border rounded-3 cursor-pointer <?= $activeTpl === 'template_urbancompany' ? 'border-primary bg-light shadow-sm' : '' ?>" style="cursor: pointer;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="homepage_template" value="template_urbancompany" <?= $activeTpl === 'template_urbancompany' ? 'checked' : '' ?>>
                                    <strong class="d-block text-dark mt-1 fs-6"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Urban Company Style</strong>
                                    <small class="text-muted d-block mt-1">Doorstep home services, clean pastel app bubbles &amp; upfront pricing.</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Site Name & Logo -->
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-tag-fill me-1"></i> Website Name &amp; Logo</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Website Brand Name *</label>
                            <input type="text" name="site_name" class="form-control" value="<?= e($brandSettings['site_name'] ?? 'BengalSeva') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tagline</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= e($brandSettings['site_tagline'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Upload Logo (Auto-Resized to 40px)</label>
                            <input type="file" name="site_logo" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Branding, Audio Sound &amp; Template
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function playCurrentAlertSound() {
    const audio = new Audio('<?= APP_URL ?>/assets/sounds/alert.mp3?t=' + Date.now());
    audio.play().catch(err => {
        alert('Click anywhere on the screen first to allow browser audio playback.');
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>