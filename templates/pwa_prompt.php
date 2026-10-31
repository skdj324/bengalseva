<!-- PWA Mobile App Install Prompt Banner -->
<div id="pwaInstallBanner" class="d-none fixed-bottom p-3 bg-white border-top shadow-lg" style="z-index: 1060; border-top: 3px solid #ff6a00 !important;">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="BengalSeva App" style="width:42px; height:42px; object-fit:contain; border-radius:8px;" onerror="this.src='https://via.placeholder.com/42?text=BS'">
            <div>
                <strong class="d-block text-dark small" style="line-height:1.2;">Install BengalSeva Mobile App</strong>
                <small class="text-muted" style="font-size:0.75rem;">Faster bookings, instant grocery delivery &amp; bike taxis</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light border" onclick="dismissPwaPrompt()">Later</button>
            <button type="button" class="btn btn-sm btn-primary fw-bold px-3" onclick="triggerPwaInstall()">
                <i class="bi bi-download me-1"></i> Install App
            </button>
        </div>
    </div>
</div>

<script>
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Check if dismissed previously
    if (!localStorage.getItem('bengalseva_pwa_dismissed')) {
        document.getElementById('pwaInstallBanner').classList.remove('d-none');
    }
});

function triggerPwaInstall() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                document.getElementById('pwaInstallBanner').classList.add('d-none');
            }
            deferredPrompt = null;
        });
    }
}

function dismissPwaPrompt() {
    document.getElementById('pwaInstallBanner').classList.add('d-none');
    localStorage.setItem('bengalseva_pwa_dismissed', '1');
}

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= APP_URL ?>/sw.js')
            .then(reg => console.log('BengalSeva PWA Active'))
            .catch(err => console.log('SW Registration failed', err));
    });
}
</script>