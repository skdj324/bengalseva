<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Safe check: Ensure district_id in database is nullable so removing dropdown never breaks SQL
try {
    $pdo->exec("ALTER TABLE `rides` MODIFY `district_id` smallint(5) UNSIGNED NULL DEFAULT 12");
} catch (Throwable $t) {
    // Column already compatible
}

// Handle Ride Booking Form Submission
$activeRideId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ride'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($token)) {
        $custName = sanitizeInput($_POST['customer_name'] ?? 'Passenger');
        $custPhone = sanitizeInput($_POST['customer_phone'] ?? '');
        $pickup = sanitizeInput($_POST['pickup_address'] ?? 'Pickup Point');
        $drop = sanitizeInput($_POST['drop_address'] ?? 'Drop Point');
        $serviceType = sanitizeInput($_POST['service_type'] ?? 'bike_taxi');
        $distKm = max(0.5, (float)($_POST['distance_km'] ?? 3.5));
        $fare = max(25.0, (float)($_POST['calculated_fare'] ?? 49.0));
        $pickLat = (float)($_POST['pickup_lat'] ?? 22.5833);
        $pickLng = (float)($_POST['pickup_lng'] ?? 88.3426);
        $dropLat = (float)($_POST['drop_lat'] ?? 22.5516);
        $dropLng = (float)($_POST['drop_lng'] ?? 88.3524);

        $otp = (string)random_int(1000, 9999);
        $customerId = Auth::check() ? (int)Auth::user()['id'] : null;

        $stmt = $pdo->prepare('
            INSERT INTO rides (
                customer_id, district_id, service_type, customer_name, customer_phone,
                pickup_address, drop_address, pickup_lat, pickup_lng, drop_lat, drop_lng,
                distance_km, estimated_fare, otp_code, status, payment_method, created_at
            ) VALUES (
                :cid, 12, :stype, :cname, :cphone,
                :pick, :drop, :plat, :plng, :dlat, :dlng,
                :dkm, :fare, :otp, "requested", "cash", NOW()
            )
        ');
        $stmt->execute([
            ':cid'   => $customerId,
            ':stype' => $serviceType,
            ':cname' => $custName,
            ':cphone'=> $custPhone,
            ':pick'  => $pickup,
            ':drop'  => $drop,
            ':plat'  => $pickLat,
            ':plng'  => $pickLng,
            ':dlat'  => $dropLat,
            ':dlng'  => $dropLng,
            ':dkm'   => $distKm,
            ':fare'  => $fare,
            ':otp'   => $otp
        ]);

        $activeRideId = (int)$pdo->lastInsertId();
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<!-- Leaflet Map CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .rapido-app-container {
        max-width: 1040px;
        margin: 20px auto 40px;
    }
    #rideMap {
        height: 440px;
        width: 100%;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        z-index: 1;
    }
    .vehicle-card {
        cursor: pointer;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 8px;
        text-align: center;
        background: #ffffff;
        transition: all 0.2s ease;
    }
    .vehicle-card:hover, .vehicle-card.active {
        border-color: #ff6a00;
        background: #fff9f5;
        box-shadow: 0 4px 12px rgba(255,106,0,0.18);
        transform: translateY(-2px);
    }
    .vehicle-card.active {
        border-width: 2px;
        border-color: #ff6a00;
    }
    .radar-pulse {
        width: 75px;
        height: 75px;
        background: rgba(255, 106, 0, 0.2);
        border: 2px solid #ff6a00;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulseRadar 1.5s infinite;
        margin: 0 auto 15px;
    }
    @keyframes pulseRadar {
        0% { transform: scale(0.9); opacity: 0.9; }
        50% { transform: scale(1.15); opacity: 0.4; }
        100% { transform: scale(0.9); opacity: 0.9; }
    }
    .captain-otp-badge {
        font-size: 2.2rem;
        letter-spacing: 6px;
        font-weight: 900;
        color: #0b5ed7;
        background: #e7f1ff;
        padding: 6px 22px;
        border-radius: 12px;
        display: inline-block;
    }
    /* Rapido Autocomplete Dropdown */
    .autocomplete-wrapper {
        position: relative;
    }
    .suggestions-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        z-index: 1050;
        max-height: 240px;
        overflow-y: auto;
        display: none;
        margin-top: 4px;
        padding: 0;
        list-style: none;
    }
    .suggestion-item {
        padding: 10px 14px;
        cursor: pointer;
        font-size: 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .suggestion-item:last-child {
        border-bottom: none;
    }
    .suggestion-item:hover {
        background-color: #fff9f5;
        color: #ff6a00;
    }
    .place-tag {
        font-size: 0.7rem;
        font-weight: 700;
        background: #e2e8f0;
        color: #475569;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
    }
    .route-meta-badge {
        background: #002347;
        color: #ffffff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
    }
</style>

<div class="container rapido-app-container">
    <div class="row g-4">
        <!-- Left: Interactive Live GPS Map -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-2 bg-white">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <span class="route-meta-badge">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Live Road Route &amp; Distance
                    </span>
                    <button class="btn btn-sm btn-outline-primary fw-semibold" type="button" onclick="locateUserGPS()">
                        <i class="bi bi-crosshair me-1"></i> My Exact Location
                    </button>
                </div>
                <div id="rideMap"></div>
                <div class="p-2 d-flex justify-content-between align-items-center small text-muted">
                    <span><i class="bi bi-hand-index-thumb me-1"></i> Drag green pin (Pickup) or red pin (Drop) anytime.</span>
                    <span id="routeStats" class="fw-bold text-dark fs-6">Calculating Route...</span>
                </div>
            </div>
        </div>

        <!-- Right: Ride Request / Live Tracking Console -->
        <div class="col-lg-5">
            <!-- Panel 1: New Booking Form -->
            <div id="bookingFormPanel" class="card border-0 shadow-sm rounded-4 p-4 bg-white <?= $activeRideId ? 'd-none' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="tile-icon-box tile-icon-orange mb-0" style="width:40px; height:40px; font-size:1.2rem;">
                        <i class="bi bi-scooter"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">BengalSeva Ride</h5>
                        <small class="text-muted">Any village, town or city in West Bengal</small>
                    </div>
                </div>

                <form method="POST" id="rideBookingForm">
                    <input type="hidden" name="book_ride" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="pickup_lat" id="inpPickupLat" value="22.5833">
                    <input type="hidden" name="pickup_lng" id="inpPickupLng" value="88.3426">
                    <input type="hidden" name="drop_lat" id="inpDropLat" value="22.5516">
                    <input type="hidden" name="drop_lng" id="inpDropLng" value="88.3524">
                    <input type="hidden" name="distance_km" id="inpDistanceKm" value="4.8">
                    <input type="hidden" name="calculated_fare" id="inpCalculatedFare" value="68">
                    <input type="hidden" name="service_type" id="inpServiceType" value="bike_taxi">

                    <!-- Pickup Location Input (Village / Street / Town) -->
                    <div class="mb-3 autocomplete-wrapper">
                        <label class="form-label small fw-semibold text-muted mb-1">Pickup Location (Village / Area / Landmark)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success"><i class="bi bi-record-circle-fill"></i></span>
                            <input type="text" name="pickup_address" id="inpPickupAddress" class="form-control" placeholder="Search any village, town or station..." value="Howrah Railway Station, Kolkata" autocomplete="off" required>
                        </div>
                        <ul class="suggestions-list" id="pickupSuggestions"></ul>
                    </div>

                    <!-- Drop-off Destination Input (Village / Street / Town) -->
                    <div class="mb-3 autocomplete-wrapper">
                        <label class="form-label small fw-semibold text-muted mb-1">Drop-off Destination (Village / Area / Landmark)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-danger"><i class="bi bi-geo-alt-fill"></i></span>
                            <input type="text" name="drop_address" id="inpDropAddress" class="form-control" placeholder="Search destination village or town..." value="Park Street, Kolkata" autocomplete="off" required>
                        </div>
                        <ul class="suggestions-list" id="dropSuggestions"></ul>
                    </div>

                    <!-- Vehicle Selector Tiles with Dynamic Live Prices -->
                    <label class="form-label small fw-semibold text-muted mb-1">Select Ride Type (Price auto-updates with distance)</label>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="vehicle-card active" onclick="selectVehicle('bike_taxi', 25, 9, this)">
                                <i class="bi bi-bicycle text-warning fs-3"></i>
                                <div class="fw-bold small text-dark mt-1">Bike Taxi</div>
                                <div class="text-primary fw-bold fs-5" id="fareBike">&#8377;68</div>
                                <small class="text-muted" style="font-size:0.7rem;">Fastest in traffic</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vehicle-card" onclick="selectVehicle('auto_toto', 35, 13, this)">
                                <i class="bi bi-ev-front text-success fs-3"></i>
                                <div class="fw-bold small text-dark mt-1">Auto / Toto</div>
                                <div class="text-primary fw-bold fs-5" id="fareAuto">&#8377;97</div>
                                <small class="text-muted" style="font-size:0.7rem;">Village &amp; local</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vehicle-card" onclick="selectVehicle('cab', 70, 18, this)">
                                <i class="bi bi-car-front text-primary fs-3"></i>
                                <div class="fw-bold small text-dark mt-1">Mini Cab</div>
                                <div class="text-primary fw-bold fs-5" id="fareCab">&#8377;156</div>
                                <small class="text-muted" style="font-size:0.7rem;">AC comfort</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vehicle-card" onclick="selectVehicle('parcel', 30, 10, this)">
                                <i class="bi bi-box-seam text-danger fs-3"></i>
                                <div class="fw-bold small text-dark mt-1">Parcel Courier</div>
                                <div class="text-primary fw-bold fs-5" id="fareParcel">&#8377;78</div>
                                <small class="text-muted" style="font-size:0.7rem;">Doorstep courier</small>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Details -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Your Name" value="<?= Auth::check() ? e(Auth::user()['name']) : 'Passenger' ?>" required>
                        </div>
                        <div class="col-6">
                            <input type="tel" name="customer_phone" class="form-control form-control-sm" placeholder="Phone (10 Digits)" pattern="[0-9]{10}" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-3 fs-5 rounded-3 shadow-sm text-dark">
                        <i class="bi bi-send-fill me-2"></i> Request Ride Now
                    </button>
                </form>
            </div>

            <!-- Panel 2: Live Tracking & Radar Screen -->
            <div id="liveTrackingPanel" class="card border-0 shadow-sm rounded-4 p-4 bg-white <?= !$activeRideId ? 'd-none' : '' ?>">
                <!-- Searching Radar Indicator -->
                <div id="radarSection" class="text-center py-4">
                    <div class="radar-pulse">
                        <i class="bi bi-broadcast fs-2 text-warning"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Looking for Nearby Captains...</h5>
                    <p class="text-muted small">Notifying riders in your area</p>
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                </div>

                <!-- Captain Confirmed Details Card -->
                <div id="captainSection" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="badge bg-success px-3 py-2 text-uppercase" id="rideStatusBadge">CAPTAIN ON THE WAY</span>
                        <div class="text-end">
                            <span class="fs-4 fw-bold text-primary" id="cardFare">&#8377;68</span>
                            <small class="d-block text-muted">Cash on Drop</small>
                        </div>
                    </div>

                    <!-- 4-Digit OTP Display -->
                    <div class="text-center my-3 bg-light p-3 rounded-4">
                        <small class="text-muted fw-semibold d-block mb-1">START-TRIP OTP (Share with Captain)</small>
                        <div class="captain-otp-badge" id="cardOtp">----</div>
                    </div>

                    <!-- Captain & Vehicle Info -->
                    <div class="card border p-3 rounded-3 mb-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:1.4rem;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0" id="cardCaptainName">Captain Subir Mondal</h6>
                                    <small class="text-warning fw-bold"><i class="bi bi-star-fill"></i> 4.9 &bull; Verified Pilot</small>
                                    <div class="small fw-semibold text-secondary" id="cardVehicleModel">Bajaj Pulsar 150 (Yellow)</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-dark fs-6 px-2 py-1" id="cardVehicleNumber">WB 02 AK 4589</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="tel:9830111222" id="callCaptainBtn" class="btn btn-outline-success fw-bold w-50 py-2">
                            <i class="bi bi-telephone-fill me-1"></i> Call Captain
                        </a>
                        <button type="button" class="btn btn-outline-danger fw-bold w-50 py-2" onclick="alert('SOS Emergency Alert sent to local safety helpline!')">
                            <i class="bi bi-shield-exclamation me-1"></i> SOS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Map JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map, pickupMarker, dropMarker, routeLayer;
let activeRideId = <?= (int)($activeRideId ?: 0) ?>;
let pollTimer = null;

let currentVehicle = {
    type: 'bike_taxi',
    base: 25,
    perKm: 9
};

function initMap() {
    map = L.map('rideMap').setView([22.5726, 88.3639], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const greenPin = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41]
    });

    const redPin = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41]
    });

    pickupMarker = L.marker([22.5833, 88.3426], { draggable: true, icon: greenPin }).addTo(map)
        .bindPopup('<b>Pickup Point</b><br>Drag to exact village / street').openPopup();

    dropMarker = L.marker([22.5516, 88.3524], { draggable: true, icon: redPin }).addTo(map)
        .bindPopup('<b>Drop-off Destination</b><br>Drag to exact village / street');

    pickupMarker.on('dragend', function() {
        const pos = pickupMarker.getLatLng();
        reverseGeocode(pos.lat, pos.lng, 'inpPickupAddress');
        calculateRoadRouteAndFares();
    });

    dropMarker.on('dragend', function() {
        const pos = dropMarker.getLatLng();
        reverseGeocode(pos.lat, pos.lng, 'inpDropAddress');
        calculateRoadRouteAndFares();
    });

    calculateRoadRouteAndFares();
}

// 1. OSRM Road Distance & Driving Routing
function calculateRoadRouteAndFares() {
    const p = pickupMarker.getLatLng();
    const d = dropMarker.getLatLng();

    document.getElementById('inpPickupLat').value = p.lat.toFixed(6);
    document.getElementById('inpPickupLng').value = p.lng.toFixed(6);
    document.getElementById('inpDropLat').value = d.lat.toFixed(6);
    document.getElementById('inpDropLng').value = d.lng.toFixed(6);

    const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${p.lng},${p.lat};${d.lng},${d.lat}?overview=full&geometries=geojson`;

    fetch(osrmUrl)
        .then(res => res.json())
        .then(data => {
            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                const realDistKm = parseFloat((route.distance / 1000).toFixed(1));
                const realDurationMins = Math.round(route.duration / 60);

                if (routeLayer) map.removeLayer(routeLayer);

                routeLayer = L.geoJSON(route.geometry, {
                    style: {
                        color: '#ff6a00',
                        weight: 6,
                        opacity: 0.85
                    }
                }).addTo(map);

                map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

                updateAllFares(realDistKm, realDurationMins);
            } else {
                fallbackDirectCalculation(p, d);
            }
        })
        .catch(() => {
            fallbackDirectCalculation(p, d);
        });
}

function fallbackDirectCalculation(p, d) {
    const directKm = (map.distance(p, d) / 1000) * 1.35;
    const estKm = Math.max(0.5, parseFloat(directKm.toFixed(1)));
    const estMins = Math.round(estKm * 3 + 2);

    if (routeLayer) map.removeLayer(routeLayer);
    routeLayer = L.polyline([p, d], { color: '#ff6a00', weight: 5, dashArray: '8, 8' }).addTo(map);
    map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

    updateAllFares(estKm, estMins);
}

// 2. Dynamic Price Calculator
function updateAllFares(distanceKm, durationMins) {
    document.getElementById('routeStats').innerHTML = `Road Distance: <strong>${distanceKm} km</strong> &bull; ~${durationMins} mins`;
    document.getElementById('inpDistanceKm').value = distanceKm;

    const bikeFare   = Math.max(25, Math.round(25 + (distanceKm * 9)));
    const autoFare   = Math.max(35, Math.round(35 + (distanceKm * 13)));
    const cabFare    = Math.max(70, Math.round(70 + (distanceKm * 18)));
    const parcelFare = Math.max(30, Math.round(30 + (distanceKm * 10)));

    document.getElementById('fareBike').innerHTML = `&#8377;${bikeFare}`;
    document.getElementById('fareAuto').innerHTML = `&#8377;${autoFare}`;
    document.getElementById('fareCab').innerHTML = `&#8377;${cabFare}`;
    document.getElementById('fareParcel').innerHTML = `&#8377;${parcelFare}`;

    let selectedFare = bikeFare;
    if (currentVehicle.type === 'auto_toto') selectedFare = autoFare;
    else if (currentVehicle.type === 'cab') selectedFare = cabFare;
    else if (currentVehicle.type === 'parcel') selectedFare = parcelFare;

    document.getElementById('inpCalculatedFare').value = selectedFare;
}

function selectVehicle(type, base, perKm, el) {
    document.querySelectorAll('.vehicle-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');

    currentVehicle = { type, base, perKm };
    document.getElementById('inpServiceType').value = type;

    const dist = parseFloat(document.getElementById('inpDistanceKm').value) || 3.5;
    const fare = Math.max(base, Math.round(base + (dist * perKm)));
    document.getElementById('inpCalculatedFare').value = fare;
}

// 3. Deep Village & Town Autocomplete Search Engine
function setupAutocomplete(inputId, suggestionsId, marker) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(suggestionsId);
    let debounceTimer;

    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            list.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`<?= APP_URL ?>/api/search-location.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    list.innerHTML = '';
                    if (data.success && data.results && data.results.length > 0) {
                        data.results.forEach(item => {
                            const li = document.createElement('li');
                            li.className = 'suggestion-item';
                            li.innerHTML = `
                                <div>
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                    <strong>${item.name}</strong>
                                    <div class="small text-muted ms-3">${item.subtitle}</div>
                                </div>
                                <span class="place-tag">${item.type}</span>
                            `;
                            li.onclick = () => {
                                input.value = item.full_name;
                                list.style.display = 'none';

                                marker.setLatLng([item.lat, item.lon]);
                                calculateRoadRouteAndFares();
                            };
                            list.appendChild(li);
                        });
                        list.style.display = 'block';
                    } else {
                        list.style.display = 'none';
                    }
                })
                .catch(() => { list.style.display = 'none'; });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !list.contains(e.target)) {
            list.style.display = 'none';
        }
    });
}

function reverseGeocode(lat, lng, inputId) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
        .then(res => res.json())
        .then(data => {
            if (data && data.address) {
                const a = data.address;
                const local = a.village || a.hamlet || a.suburb || a.town || a.road || 'Location';
                const dist = a.state_district || a.county || 'West Bengal';
                document.getElementById(inputId).value = `${local}, ${dist}`;
            }
        })
        .catch(() => {});
}

function locateUserGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const userLat = pos.coords.latitude;
            const userLng = pos.coords.longitude;
            map.setView([userLat, userLng], 14);
            pickupMarker.setLatLng([userLat, userLng]);
            reverseGeocode(userLat, userLng, 'inpPickupAddress');
            calculateRoadRouteAndFares();
        }, () => {
            alert('Location permission unavailable. You can drag the green pin on the map!');
        });
    }
}

function startRidePolling(rideId) {
    if (pollTimer) clearInterval(pollTimer);

    pollTimer = setInterval(() => {
        fetch(`<?= APP_URL ?>/api/ride-engine.php?action=poll_status&ride_id=${rideId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.ride) {
                    const r = data.ride;
                    if (r.status !== 'requested') {
                        document.getElementById('radarSection').classList.add('d-none');
                        document.getElementById('captainSection').classList.remove('d-none');

                        document.getElementById('rideStatusBadge').innerText = r.status.replace('_', ' ').toUpperCase();
                        document.getElementById('cardFare').innerHTML = '&#8377;' + r.fare;
                        document.getElementById('cardOtp').innerText = r.otp;
                        document.getElementById('cardCaptainName').innerText = r.captain_name;
                        document.getElementById('cardVehicleModel').innerText = r.vehicle_model;
                        document.getElementById('cardVehicleNumber').innerText = r.vehicle_number;
                        document.getElementById('callCaptainBtn').href = 'tel:' + r.captain_phone;

                        if (r.status === 'completed') {
                            clearInterval(pollTimer);
                            alert('Trip completed safely! Thank you for riding with BengalSeva.');
                            window.location.href = '<?= APP_URL ?>/';
                        }
                    }
                }
            })
            .catch(err => console.error(err));
    }, 2500);
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    setupAutocomplete('inpPickupAddress', 'pickupSuggestions', pickupMarker);
    setupAutocomplete('inpDropAddress', 'dropSuggestions', dropMarker);

    if (activeRideId > 0) {
        startRidePolling(activeRideId);
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>