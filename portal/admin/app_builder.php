<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// 1. Handle Banner Creation / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $subtitle = sanitizeInput($_POST['subtitle'] ?? '');
    $badge = sanitizeInput($_POST['badge_text'] ?? 'OFFER');
    $target = sanitizeInput($_POST['target_url'] ?? '/shop.php');
    $gradient = sanitizeInput($_POST['bg_gradient'] ?? 'linear-gradient(135deg, #ff6a00 0%, #ea580c 100%)');

    $stmt = $pdo->prepare('
        INSERT INTO app_banners (title, subtitle, badge_text, target_url, bg_gradient, sort_order, status)
        VALUES (:t, :s, :b, :url, :grad, 1, "active")
    ');
    $stmt->execute([
        ':t'    => $title,
        ':s'    => $subtitle,
        ':b'    => $badge,
        ':url'  => $target,
        ':grad' => $gradient
    ]);
    logAudit($pdo, $user['id'], 'APP_BANNER_CREATED', 'app_banners', (int)$pdo->lastInsertId(), "Created banner: {$title}");
    setFlash('success', 'New App Promotional Banner added!');
    header('Location: app_builder.php');
    exit;
}

// 2. Handle Banner Delete
if (isset($_GET['delete_banner'])) {
    $delId = (int)$_GET['delete_banner'];
    $pdo->prepare('DELETE FROM app_banners WHERE id = :id')->execute([':id' => $delId]);
    setFlash('info', 'App banner removed.');
    header('Location: app_builder.php');
    exit;
}

// 3. Handle App Theme & Active Modules Customization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_app_design'])) {
    $themeColor = sanitizeInput($_POST['app_theme_color'] ?? '#0b2239');
    $showPartner = isset($_POST['app_show_partner_switch']) ? '1' : '0';
    $modules = isset($_POST['modules']) ? implode(',', (array)$_POST['modules']) : 'rides,mart,doctors,bills,services';

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'app_theme_color', ':v' => $themeColor]);
    $up->execute([':k' => 'app_show_partner_switch', ':v' => $showPartner]);
    $up->execute([':k' => 'app_active_modules', ':v' => $modules]);

    logAudit($pdo, $user['id'], 'APP_DESIGN_SAVED', 'settings', null, "Configured App theme & active modules.");
    setFlash('success', 'Super App design and active features updated live!');
    header('Location: app_builder.php');
    exit;
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$banners = $pdo->query('SELECT * FROM app_banners ORDER BY sort_order ASC, id DESC')->fetchAll();
$activeModules = explode(',', $settings['app_active_modules'] ?? 'rides,mart,doctors,bills,services,tourism,properties,jobs');

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-phone-fill text-primary me-2"></i> Super-App Visual Builder &amp; Manager</h3>
            <small class="text-muted">Design the single unified mobile app live: colors, dynamic banners, and module toggles</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/app.php" target="_blank" class="btn btn-success fw-bold px-3">
                <i class="bi bi-play-circle-fill me-1"></i> Preview Live Super-App &nearr;
            </a>
            <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Section 1: App Theme Colors & Active Modules -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-palette-fill text-primary me-1"></i> App Design &amp; Feature Toggles</h5>
                <form method="POST">
                    <input type="hidden" name="save_app_design" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">App Header &amp; Theme Color</label>
                        <select name="app_theme_color" class="form-select">
                            <option value="#0b2239" <?= ($settings['app_theme_color'] ?? '') === '#0b2239' ? 'selected' : '' ?>>Deep Navy Corporate (#0b2239 - Default)</option>
                            <option value="#0d6efd" <?= ($settings['app_theme_color'] ?? '') === '#0d6efd' ? 'selected' : '' ?>>Royal Blue Modern (#0d6efd)</option>
                            <option value="#15803d" <?= ($settings['app_theme_color'] ?? '') === '#15803d' ? 'selected' : '' ?>>Emerald Green Eco (#15803d)</option>
                            <option value="#ea580c" <?= ($settings['app_theme_color'] ?? '') === '#ea580c' ? 'selected' : '' ?>>Justdial Vibrant Orange (#ea580c)</option>
                            <option value="#4c1d95" <?= ($settings['app_theme_color'] ?? '') === '#4c1d95' ? 'selected' : '' ?>>Royal Violet Premium (#4c1d95)</option>
                        </select>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="app_show_partner_switch" id="partSwitch" <?= ($settings['app_show_partner_switch'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-dark" for="partSwitch">Allow Vendors/Riders to Switch into Partner Mode Inside App</label>
                        </div>
                        <small class="text-muted">Keeps Customer and Vendor features in one unified app.</small>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Enable / Disable App Modules Live:</h6>
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="rides" id="mRides" <?= in_array('rides', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mRides">Bike Taxi &amp; Cabs</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="mart" id="mMart" <?= in_array('mart', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mMart">45-Min Mart &amp; Grocery</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="doctors" id="mDoc" <?= in_array('doctors', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mDoc">Doctor Appointments</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="bills" id="mBills" <?= in_array('bills', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mBills">Recharges &amp; Electricity</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="services" id="mServ" <?= in_array('services', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mServ">Home Care &amp; Repairs</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="tourism" id="mTour" <?= in_array('tourism', $activeModules, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="mTour">Bengal Tourism Stays</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        Save App Design Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Section 2: Promotional Banner Sliders Manager -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-images text-warning me-1"></i> Add Promotional Slider Banner</h5>
                <form method="POST">
                    <input type="hidden" name="save_banner" value="1">

                    <div class="mb-2">
                        <input type="text" name="title" class="form-control" placeholder="Banner Heading (e.g. 50% Off Mart)" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="subtitle" class="form-control" placeholder="Subtitle (e.g. Use Code BENGAL50)" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input type="text" name="badge_text" class="form-control" placeholder="Tag (e.g. SPECIAL OFFER)" value="SPECIAL OFFER">
                        </div>
                        <div class="col-6">
                            <input type="text" name="target_url" class="form-control" placeholder="Click Link" value="/shop.php">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Banner Color Style</label>
                        <select name="bg_gradient" class="form-select">
                            <option value="linear-gradient(135deg, #ff6a00 0%, #ea580c 100%)">Vibrant Orange (Offers)</option>
                            <option value="linear-gradient(135deg, #15803d 0%, #16a34a 100%)">Green Grass (Rides/Eco)</option>
                            <option value="linear-gradient(135deg, #0284c7 0%, #0369a1 100%)">Sky Blue (Healthcare)</option>
                            <option value="linear-gradient(135deg, #581c87 0%, #7e22ce 100%)">Purple Royal (Mart)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-warning fw-bold text-dark w-100 py-2 rounded-3">
                        + Add Banner Slider
                    </button>
                </form>
            </div>

            <!-- Existing Banners List -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <h6 class="fw-bold text-dark mb-2">Active In-App Banners (<?= count($banners) ?>)</h6>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($banners as $b): ?>
                        <div class="p-3 rounded-3 text-white d-flex justify-content-between align-items-center" style="background: <?= e($b['bg_gradient']) ?>;">
                            <div>
                                <span class="badge bg-white text-dark small mb-1"><?= e($b['badge_text']) ?></span>
                                <strong class="d-block text-white"><?= e($b['title']) ?></strong>
                                <small class="text-white-50"><?= e($b['subtitle']) ?> &bull; <code><?= e($b['target_url']) ?></code></small>
                            </div>
                            <a href="app_builder.php?delete_banner=<?= (int)$b['id'] ?>" class="btn btn-sm btn-light text-danger fw-bold rounded-circle p-1" style="width:30px; height:30px;" onclick="return confirm('Delete this banner?')">
                                &times;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>