<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$travelType = sanitizeInput($_GET['type'] ?? 'bus');
$error = null;
$bookedTicket = null;

// Handle Ticket Reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ticket'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Security session expired. Please retry.';
    } else {
        $bType = sanitizeInput($_POST['booking_type'] ?? 'bus');
        $from = sanitizeInput($_POST['from_city'] ?? '');
        $to = sanitizeInput($_POST['to_city'] ?? '');
        $date = sanitizeInput($_POST['travel_date'] ?? date('Y-m-d'));
        $carrier = sanitizeInput($_POST['carrier_name'] ?? 'SBSTC Express');
        $carrierNum = sanitizeInput($_POST['carrier_number'] ?? 'WB-101');
        $seatClass = sanitizeInput($_POST['seat_class'] ?? 'AC Volvo');
        $pName = sanitizeInput($_POST['passenger_name'] ?? '');
        $pPhone = sanitizeInput($_POST['passenger_phone'] ?? '');
        $seats = sanitizeInput($_POST['seat_numbers'] ?? 'A1, A2');
        $fare = (float)($_POST['total_fare'] ?? 550.0);

        if ($from && $to && $pName && $pPhone && $fare > 0) {
            $pnr = strtoupper(substr($bType, 0, 1)) . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $custId = Auth::check() ? (int)Auth::user()['id'] : null;

            $stmt = $pdo->prepare('
                INSERT INTO travel_bookings (
                    customer_id, booking_type, booking_ref, from_city, to_city,
                    travel_date, carrier_name, carrier_number, seat_class, passenger_name,
                    passenger_phone, seat_numbers, total_fare, status, payment_status, created_at
                ) VALUES (
                    :cid, :btype, :bref, :from, :to,
                    :date, :carrier, :cnum, :sclass, :pname,
                    :pphone, :seats, :fare, "confirmed", "paid", NOW()
                )
            ');
            $stmt->execute([
                ':cid'    => $custId,
                ':btype'  => $bType,
                ':bref'   => $pnr,
                ':from'   => $from,
                ':to'     => $to,
                ':date'   => $date,
                ':carrier'=> $carrier,
                ':cnum'   => $carrierNum,
                ':sclass' => $seatClass,
                ':pname'  => $pName,
                ':pphone' => $pPhone,
                ':seats'  => $seats,
                ':fare'   => $fare
            ]);

            logAudit($pdo, $custId, 'TRAVEL_TICKET_BOOKED', 'travel_bookings', (int)$pdo->lastInsertId(), "PNR {$pnr} ({$bType}) from {$from} to {$to}");
            $bookedTicket = [
                'pnr'     => $pnr,
                'type'    => $bType,
                'carrier' => $carrier,
                'from'    => $from,
                'to'      => $to,
                'date'    => $date,
                'name'    => $pName,
                'seats'   => $seats,
                'fare'    => $fare
            ];
            setFlash('success', "Ticket Confirmed! E-Pass PNR: {$pnr}");
        } else {
            $error = 'Please fill in all mandatory passenger and route details.';
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="py-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
    <div class="container text-white text-center py-2">
        <span class="badge bg-warning text-dark fw-bold mb-2 px-3 py-1">Bengal Transit &amp; Travel Booking</span>
        <h2 class="fw-bold mb-1">Bus, Flight &amp; Train Tickets</h2>
        <p class="text-white-50 small mb-0">Book direct tickets across West Bengal &bull; SBSTC Volvo Buses, Flights &amp; Trains</p>
    </div>
</div>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Travel Modes Nav -->
            <ul class="nav nav-pills justify-content-center gap-2 mb-4 bg-white p-2 rounded-4 shadow-sm border">
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $travelType === 'bus' ? 'active' : '' ?>" href="tickets.php?type=bus">
                        <i class="bi bi-bus-front me-1"></i> Bus Tickets
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $travelType === 'flight' ? 'active' : '' ?>" href="tickets.php?type=flight">
                        <i class="bi bi-airplane me-1"></i> Flights (CCU / Bagdogra)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold <?= $travelType === 'train' ? 'active' : '' ?>" href="tickets.php?type=train">
                        <i class="bi bi-train-front me-1"></i> Trains (HWH / Sealdah)
                    </a>
                </li>
            </ul>

            <?php if ($bookedTicket): ?>
                <!-- E-Ticket Confirmation Card -->
                <div class="card border-0 shadow rounded-4 p-4 p-md-5 bg-white text-center mb-4">
                    <i class="bi bi-ticket-perforated-fill text-success display-3 mb-2"></i>
                    <h3 class="fw-bold text-dark">E-Ticket Confirmed!</h3>
                    <p class="text-muted small">Show this digital boarding pass upon departure.</p>

                    <div class="p-4 bg-light rounded-4 text-start border my-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <span class="badge bg-primary text-uppercase"><?= e($bookedTicket['type']) ?> PASS</span>
                                <h5 class="fw-bold mb-0 text-dark mt-1"><?= e($bookedTicket['carrier']) ?></h5>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">PNR NUMBER</small>
                                <strong class="fs-5 font-monospace text-primary"><?= e($bookedTicket['pnr']) ?></strong>
                            </div>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">ROUTE</span>
                                <strong class="fs-6"><?= e($bookedTicket['from']) ?> &rarr; <?= e($bookedTicket['to']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">JOURNEY DATE</span>
                                <strong><?= e(date('d M Y', strtotime($bookedTicket['date']))) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">PASSENGER</span>
                                <strong><?= e($bookedTicket['name']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">SEAT NUMBER(S)</span>
                                <strong><?= e($bookedTicket['seats']) ?></strong>
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                <span>Total Paid:</span>
                                <span class="fs-5 fw-bold text-success">₹<?= number_format((float)$bookedTicket['fare'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <button onclick="window.print()" class="btn btn-primary fw-bold px-4">Print E-Ticket</button>
                        <a href="tickets.php" class="btn btn-outline-secondary fw-bold px-4">Book Another</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="book_ticket" value="1">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="booking_type" value="<?= e($travelType) ?>">

                        <!-- 1. Route Selector -->
                        <h5 class="fw-bold text-dark mb-3">1. Route &amp; Travel Date</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">From City / Station *</label>
                                <?php if ($travelType === 'bus'): ?>
                                    <select name="from_city" class="form-select form-select-lg" required>
                                        <option value="Kolkata (Esplanade / Babughat)">Kolkata (Esplanade / Babughat)</option>
                                        <option value="Siliguri (Tenzing Norgay Bus Terminus)">Siliguri (Tenzing Norgay)</option>
                                        <option value="Digha Bus Stand">Digha Bus Stand</option>
                                        <option value="Asansol City Bus Stand">Asansol</option>
                                    </select>
                                <?php elseif ($travelType === 'flight'): ?>
                                    <select name="from_city" class="form-select form-select-lg" required>
                                        <option value="Kolkata (CCU - Netaji Subhash Chandra Bose)">Kolkata (CCU)</option>
                                        <option value="Bagdogra / Siliguri (IXB)">Bagdogra / Siliguri (IXB)</option>
                                        <option value="Durgapur (RDP - Kazi Nazrul Islam)">Durgapur (RDP)</option>
                                    </select>
                                <?php else: ?>
                                    <select name="from_city" class="form-select form-select-lg" required>
                                        <option value="Howrah Junction (HWH)">Howrah Junction (HWH)</option>
                                        <option value="Sealdah Railway Station (SDAH)">Sealdah (SDAH)</option>
                                        <option value="Kolkata Chitpur (KOAA)">Kolkata Terminal (KOAA)</option>
                                        <option value="New Jalpaiguri (NJP)">New Jalpaiguri (NJP)</option>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Destination *</label>
                                <?php if ($travelType === 'bus'): ?>
                                    <select name="to_city" class="form-select form-select-lg" required>
                                        <option value="Siliguri">Siliguri (North Bengal)</option>
                                        <option value="Digha Sea Beach">Digha Sea Beach</option>
                                        <option value="Darjeeling Hills">Darjeeling Hills</option>
                                        <option value="Malda Town">Malda Town</option>
                                        <option value="Purulia">Purulia</option>
                                    </select>
                                <?php elseif ($travelType === 'flight'): ?>
                                    <select name="to_city" class="form-select form-select-lg" required>
                                        <option value="Delhi (DEL - Indira Gandhi Intl)">Delhi (DEL)</option>
                                        <option value="Mumbai (BOM - Chhatrapati Shivaji)">Mumbai (BOM)</option>
                                        <option value="Bengaluru (BLR - Kempegowda)">Bengaluru (BLR)</option>
                                        <option value="Hyderabad (HYD)">Hyderabad (HYD)</option>
                                    </select>
                                <?php else: ?>
                                    <select name="to_city" class="form-select form-select-lg" required>
                                        <option value="New Jalpaiguri (NJP - Siliguri)">New Jalpaiguri (NJP)</option>
                                        <option value="Digha Flag Station (DGHA)">Digha (DGHA)</option>
                                        <option value="Asansol Junction (ASN)">Asansol (ASN)</option>
                                        <option value="Malda Town (MLDT)">Malda Town (MLDT)</option>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Journey Date *</label>
                                <input type="date" name="travel_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Select Carrier / Operator</label>
                                <?php if ($travelType === 'bus'): ?>
                                    <select name="carrier_name" class="form-select" onchange="updateFare(this)">
                                        <option value="SBSTC Government AC Volvo" data-fare="580">SBSTC AC Volvo - ₹580</option>
                                        <option value="NBSTC North Bengal Express" data-fare="420">NBSTC Express - ₹420</option>
                                        <option value="Shyamoli Paribahan Luxury Sleeper" data-fare="850">Shyamoli Sleeper - ₹850</option>
                                        <option value="Greenline Multiaxle Scania" data-fare="950">Greenline Scania - ₹950</option>
                                    </select>
                                <?php elseif ($travelType === 'flight'): ?>
                                    <select name="carrier_name" class="form-select" onchange="updateFare(this)">
                                        <option value="IndiGo 6E-205" data-fare="3450">IndiGo 6E-205 - ₹3,450</option>
                                        <option value="Air India AI-765" data-fare="3890">Air India AI-765 - ₹3,890</option>
                                        <option value="SpiceJet SG-302" data-fare="3200">SpiceJet SG-302 - ₹3,200</option>
                                    </select>
                                <?php else: ?>
                                    <select name="carrier_name" class="form-select" onchange="updateFare(this)">
                                        <option value="Vande Bharat Express (22301)" data-fare="1565">Vande Bharat Express (CC) - ₹1,565</option>
                                        <option value="Darjeeling Mail (12343)" data-fare="425">Darjeeling Mail (Sleeper) - ₹425</option>
                                        <option value="Shatabdi Express (12041)" data-fare="1250">Shatabdi Express (AC Chair) - ₹1,250</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 2. Passenger Details -->
                        <h5 class="fw-bold text-dark mb-3">2. Passenger Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Passenger Full Name *</label>
                                <input type="text" name="passenger_name" class="form-control" value="<?= Auth::check() ? e(Auth::user()['name']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Mobile Number (SMS E-Ticket) *</label>
                                <input type="tel" name="passenger_phone" class="form-control" placeholder="10 Digits" pattern="[0-9]{10}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Seats</label>
                                <input type="text" name="seat_numbers" class="form-control" value="Seat 12 (Window)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Total Fare (₹)</label>
                                <input type="number" name="total_fare" id="ticketTotalFare" class="form-control fw-bold text-success fs-5" value="<?= $travelType === 'flight' ? '3450' : ($travelType === 'train' ? '1565' : '580') ?>" readonly>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm">
                            <i class="bi bi-ticket-perforated me-1"></i> Confirm &amp; Reserve Ticket
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateFare(select) {
    const selected = select.options[select.selectedIndex];
    const fare = selected.getAttribute('data-fare');
    if (fare) {
        document.getElementById('ticketTotalFare').value = fare;
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>