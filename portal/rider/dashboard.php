<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole(['rider', 'super_admin', 'admin']);
$user = Auth::user();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-2"></i> Captain <?= e($user['name']) ?></h3>
                <small class="text-muted">BengalSeva Bike &amp; Cab Pilot Console</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="captainOnlineSwitch" checked onchange="toggleCaptainDuty(this)">
                    <label class="form-check-label fs-6 fw-bold text-success" id="dutyStatusLabel" for="captainOnlineSwitch">ONLINE (Receiving Trips)</label>
                </div>
                <a href="<?= APP_URL ?>/portal/dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Active Accepted Trip Box -->
    <div id="activeTripBox" class="d-none card border-0 shadow-lg rounded-4 p-4 bg-white mb-4 border-start border-warning border-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge bg-warning text-dark fs-6 px-3 py-2 text-uppercase" id="activeTripStatusBadge">PASSENGER PICKUP</span>
            <span class="fs-4 fw-bold text-primary" id="activeTripFare">&#8377;0</span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3">
                    <small class="text-success fw-bold d-block">PICKUP PASSENGER</small>
                    <div class="fw-bold text-dark" id="activePickupAddr">Loading...</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3">
                    <small class="text-danger fw-bold d-block">DROP DESTINATION</small>
                    <div class="fw-bold text-dark" id="activeDropAddr">Loading...</div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="fw-bold text-dark" id="activePassengerName">Passenger</span>
            </div>

            <div id="otpVerifySection" class="d-flex gap-2">
                <input type="text" id="inputTripOtp" class="form-control form-control-sm text-center fw-bold" placeholder="4-Digit OTP" maxlength="4" style="width:130px;">
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3" onclick="verifyTripOtp()">
                    Verify OTP &amp; Start
                </button>
            </div>

            <div id="completeTripSection" class="d-none">
                <button type="button" class="btn btn-success fw-bold px-4" onclick="finishTripAndCollectCash()">
                    <i class="bi bi-check2-circle me-1"></i> Complete Trip &amp; Collect Cash
                </button>
            </div>
        </div>
    </div>

    <!-- Radar Waiting State -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-broadcast text-warning me-2"></i> Nearby Trip Radar</h5>
            <span class="badge bg-success">Scanning Live</span>
        </div>
        <div class="card-body p-4 text-center" id="radarWaitingState">
            <div class="spinner-grow text-warning my-3" style="width: 3rem; height: 3rem;" role="status"></div>
            <h5 class="fw-bold text-dark mb-1">Listening for Passenger Rides...</h5>
            <p class="text-muted small">Keep this tab open. Incoming rides will ring an alert.</p>
        </div>

        <!-- Incoming Ride Alert Popup -->
        <div id="incomingRideCard" class="d-none p-4 bg-warning bg-opacity-10 border-top border-warning border-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-danger fs-6 px-3 py-1">NEW RIDE REQUEST</span>
                <span class="fs-3 fw-bold text-success" id="alertFare">&#8377;0</span>
            </div>
            <div class="row g-2 text-start mb-3">
                <div class="col-sm-6"><strong>Pickup:</strong> <span id="alertPickup">-</span></div>
                <div class="col-sm-6"><strong>Drop:</strong> <span id="alertDrop">-</span></div>
                <div class="col-12"><strong>Service:</strong> <span id="alertServiceType" class="text-uppercase badge bg-dark">Bike</span> &bull; Est. Distance: <span id="alertDistance">0 km</span></div>
            </div>
            <button type="button" class="btn btn-success btn-lg w-100 fw-bold" onclick="acceptIncomingRide()">
                <i class="bi bi-check-circle-fill me-2"></i> ACCEPT RIDE (Rapido)
            </button>
        </div>
    </div>
</div>

<script>
let isOnline = true;
let currentPendingRideId = 0;
let currentActiveRideId = 0;
let radarPollInterval = null;

function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.value = 880;
        osc.start();
        setTimeout(() => { osc.stop(); audioCtx.close(); }, 400);
    } catch(e) {}
}

function toggleCaptainDuty(checkbox) {
    isOnline = checkbox.checked;
    const label = document.getElementById('dutyStatusLabel');
    if (isOnline) {
        label.innerText = 'ONLINE (Receiving Trips)';
        label.className = 'form-check-label fs-6 fw-bold text-success';
    } else {
        label.innerText = 'OFFLINE';
        label.className = 'form-check-label fs-6 fw-bold text-muted';
        document.getElementById('incomingRideCard').classList.add('d-none');
        document.getElementById('radarWaitingState').classList.remove('d-none');
    }
}

function startCaptainRadar() {
    radarPollInterval = setInterval(() => {
        if (!isOnline || currentActiveRideId > 0) return;

        fetch('<?= APP_URL ?>/api/ride-engine.php?action=captain_radar')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.has_request && data.ride) {
                    const r = data.ride;
                    if (r.id !== currentPendingRideId) {
                        currentPendingRideId = r.id;
                        playBeep();

                        document.getElementById('alertFare').innerText = '₹' + r.estimated_fare;
                        document.getElementById('alertPickup').innerText = r.pickup_address;
                        document.getElementById('alertDrop').innerText = r.drop_address;
                        document.getElementById('alertServiceType').innerText = r.service_type.replace('_', ' ');
                        document.getElementById('alertDistance').innerText = r.distance_km + ' km';

                        document.getElementById('radarWaitingState').classList.add('d-none');
                        document.getElementById('incomingRideCard').classList.remove('d-none');
                    }
                } else {
                    document.getElementById('incomingRideCard').classList.add('d-none');
                    document.getElementById('radarWaitingState').classList.remove('d-none');
                    currentPendingRideId = 0;
                }
            })
            .catch(err => console.error(err));
    }, 2500);
}

function acceptIncomingRide() {
    if (!currentPendingRideId) return;

    const fd = new FormData();
    fd.append('ride_id', currentPendingRideId);

    fetch('<?= APP_URL ?>/api/ride-engine.php?action=accept_ride', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentActiveRideId = currentPendingRideId;
                document.getElementById('incomingRideCard').classList.add('d-none');
                document.getElementById('radarWaitingState').classList.add('d-none');

                document.getElementById('activeTripBox').classList.remove('d-none');
                document.getElementById('activeTripFare').innerText = document.getElementById('alertFare').innerText;
                document.getElementById('activePickupAddr').innerText = document.getElementById('alertPickup').innerText;
                document.getElementById('activeDropAddr').innerText = document.getElementById('alertDrop').innerText;
            } else {
                alert(data.message);
            }
        });
}

function verifyTripOtp() {
    const otp = document.getElementById('inputTripOtp').value.trim();
    if (!otp || otp.length < 4) {
        alert('Please enter 4-digit Passenger OTP');
        return;
    }

    const fd = new FormData();
    fd.append('ride_id', currentActiveRideId);
    fd.append('otp', otp);

    fetch('<?= APP_URL ?>/api/ride-engine.php?action=start_trip', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('activeTripStatusBadge').innerText = 'TRIP IN PROGRESS';
                document.getElementById('activeTripStatusBadge').className = 'badge bg-success fs-6 px-3 py-2 text-uppercase';
                document.getElementById('otpVerifySection').classList.add('d-none');
                document.getElementById('completeTripSection').classList.remove('d-none');
            } else {
                alert(data.message);
            }
        });
}

function finishTripAndCollectCash() {
    const fd = new FormData();
    fd.append('ride_id', currentActiveRideId);

    fetch('<?= APP_URL ?>/api/ride-engine.php?action=complete_trip', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Trip finished! Cash collected successfully.');
                currentActiveRideId = 0;
                currentPendingRideId = 0;
                document.getElementById('activeTripBox').classList.add('d-none');
                document.getElementById('radarWaitingState').classList.remove('d-none');
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    startCaptainRadar();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>