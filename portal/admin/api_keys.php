<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['super_admin', 'admin']);
$user = Auth::user();

// Handle Saving API Keys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api_keys'])) {
    $gmap = trim($_POST['google_map_api_key'] ?? '');
    $gemini = trim($_POST['gemini_api_key'] ?? '');
    $model = sanitizeInput($_POST['gemini_model'] ?? 'gemini-1.5-flash');
    $aiActive = isset($_POST['ai_support_enabled']) ? '1' : '0';

    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $up->execute([':k' => 'google_map_api_key', ':v' => $gmap]);
    $up->execute([':k' => 'gemini_api_key', ':v' => $gemini]);
    $up->execute([':k' => 'gemini_model', ':v' => $model]);
    $up->execute([':k' => 'ai_support_enabled', ':v' => $aiActive]);

    logAudit($pdo, $user['id'], 'API_KEYS_UPDATED', 'settings', null, 'Updated Google Maps & Gemini AI configuration.');
    setFlash('success', 'API settings saved successfully! Keys are optional and system works seamlessly.');
    header('Location: api_keys.php');
    exit;
}

// Fetch current values
$currentSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-key-fill text-warning me-2"></i> Third-Party API Keys Master</h3>
            <small class="text-muted">Optional external integrations (Google Maps, Google Gemini AI Chatbot)</small>
        </div>
        <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Return to Dashboard</a>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check fs-4"></i>
                    <div>
                        <strong>Keys are 100% Optional:</strong> Agar aap in fields ko khali chhod dete hain, tab bhi BengalSeva ka mapping system (OpenStreetMap) aur Helpdesk bina kisi rukawat ke chalte rahenge.
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="save_api_keys" value="1">

                    <!-- Section 1: Google Maps API -->
                    <div class="p-3 bg-light rounded-4 mb-4 border">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-geo-alt-fill text-danger fs-4"></i>
                            <h5 class="fw-bold text-dark mb-0">Google Maps API (Optional)</h5>
                        </div>
                        <p class="text-muted small mb-3">Google Places Autocomplete aur satellite view ke liye. Agar blank chhodenge, to free OpenStreetMap chalega.</p>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Google Maps Browser API Key</label>
                            <input type="password" name="google_map_api_key" class="form-control" value="<?= e($currentSettings['google_map_api_key'] ?? '') ?>" placeholder="AIzaSy...">
                            <small class="text-muted">Google Cloud Console &rarr; Credentials &rarr; Maps JavaScript API.</small>
                        </div>
                    </div>

                    <!-- Section 2: Google Gemini AI -->
                    <div class="p-3 bg-light rounded-4 mb-4 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-robot text-primary fs-4"></i>
                                <h5 class="fw-bold text-dark mb-0">Google Gemini AI Support Chat (Optional)</h5>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ai_support_enabled" id="aiSwitch" <?= ($currentSettings['ai_support_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="aiSwitch">Enable AI Chat</label>
                            </div>
                        </div>
                        <p class="text-muted small mb-3">Website par aane wale customers ke sawalon ka 24x7 intelligent reply dene ke liye (Bengali, Hindi, English).</p>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Gemini API Key</label>
                            <input type="password" name="gemini_api_key" id="geminiApiKeyInput" class="form-control" value="<?= e($currentSettings['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy...">
                            <small class="text-muted">Get free API key from <a href="https://aistudio.google.com/app/apikey" target="_blank" class="fw-semibold">Google AI Studio</a>.</small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Gemini Model</label>
                            <select name="gemini_model" class="form-select">
                                <option value="gemini-1.5-flash" <?= ($currentSettings['gemini_model'] ?? '') === 'gemini-1.5-flash' ? 'selected' : '' ?>>gemini-1.5-flash (Fastest &amp; Free Tier Recommended)</option>
                                <option value="gemini-2.5-flash" <?= ($currentSettings['gemini_model'] ?? '') === 'gemini-2.5-flash' ? 'selected' : '' ?>>gemini-2.5-flash (Next-Gen High Performance)</option>
                                <option value="gemini-1.5-pro" <?= ($currentSettings['gemini_model'] ?? '') === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Deep Reasoning)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save API Credentials
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>