<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/bootstrap.php';

$action = $_REQUEST['action'] ?? '';

try {
    // 1. Customer Polls Live Status of Their Ride
    if ($action === 'poll_status') {
        $rideId = (int)($_GET['ride_id'] ?? 0);
        if ($rideId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ride ID']);
            exit;
        }

        $stmt = $pdo->prepare('
            SELECT r.*, u.full_name as captain_user_name, u.mobile as captain_mobile
            FROM rides r
            LEFT JOIN users u ON r.rider_id = u.id
            WHERE r.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $rideId]);
        $ride = $stmt->fetch();

        if (!$ride) {
            echo json_encode(['success' => false, 'message' => 'Ride not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'ride' => [
                'id'             => (int)$ride['id'],
                'status'         => $ride['status'],
                'service_type'   => $ride['service_type'],
                'fare'           => (float)$ride['estimated_fare'],
                'otp'            => $ride['otp_code'],
                'distance_km'    => (float)$ride['distance_km'],
                'pickup_address' => $ride['pickup_address'],
                'drop_address'   => $ride['drop_address'],
                'captain_name'   => $ride['captain_user_name'] ?? $ride['captain_name'] ?? 'Subir Mondal (Captain)',
                'captain_phone'  => $ride['captain_mobile'] ?? '9830111222',
                'vehicle_number' => $ride['vehicle_number'] ?? 'WB 02 AK 4589',
                'vehicle_model'  => $ride['vehicle_model'] ?? 'Bajaj Pulsar 150 (Yellow)',
                'captain_rating' => '4.9'
            ]
        ]);
        exit;
    }

    // 2. Captain Polls for Incoming Nearby Rides
    if ($action === 'captain_radar') {
        Auth::requireRole(['rider', 'super_admin', 'admin']);
        $stmt = $pdo->query('
            SELECT r.*, d.name as district_name 
            FROM rides r 
            LEFT JOIN districts d ON r.district_id = d.id 
            WHERE r.status = "requested" 
            ORDER BY r.id DESC 
            LIMIT 1
        ');
        $pendingRide = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'has_request' => (bool)$pendingRide,
            'ride' => $pendingRide ?: null
        ]);
        exit;
    }

    // 3. Captain Accepts Incoming Ride
    if ($action === 'accept_ride') {
        Auth::requireRole(['rider', 'super_admin', 'admin']);
        $rideId = (int)($_POST['ride_id'] ?? 0);
        $user = Auth::user();

        $stmt = $pdo->prepare('
            UPDATE rides 
            SET rider_id = :rid, status = "accepted", captain_name = :cname 
            WHERE id = :id AND status = "requested"
        ');
        $stmt->execute([
            ':rid'   => $user['id'],
            ':cname' => $user['name'],
            ':id'    => $rideId
        ]);

        if ($stmt->rowCount() > 0) {
            logAudit($pdo, $user['id'], 'RIDE_ACCEPTED', 'rides', $rideId, 'Captain accepted trip.');
            echo json_encode(['success' => true, 'message' => 'Trip accepted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ride already taken or cancelled.']);
        }
        exit;
    }

    // 4. Captain Verifies 4-Digit Passenger OTP to Start Trip
    if ($action === 'start_trip') {
        Auth::requireRole(['rider', 'super_admin', 'admin']);
        $rideId = (int)($_POST['ride_id'] ?? 0);
        $otp = trim($_POST['otp'] ?? '');

        $check = $pdo->prepare('SELECT otp_code FROM rides WHERE id = :id');
        $check->execute([':id' => $rideId]);
        $realOtp = (string)$check->fetchColumn();

        if ($realOtp === $otp) {
            $up = $pdo->prepare('UPDATE rides SET status = "in_trip" WHERE id = :id');
            $up->execute([':id' => $rideId]);
            echo json_encode(['success' => true, 'message' => 'OTP verified! Trip started.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Ask passenger for correct 4-digit code.']);
        }
        exit;
    }

    // 5. Captain Completes Trip and Collects Cash
    if ($action === 'complete_trip') {
        Auth::requireRole(['rider', 'super_admin', 'admin']);
        $rideId = (int)($_POST['ride_id'] ?? 0);

        $up = $pdo->prepare('UPDATE rides SET status = "completed", payment_status = "paid" WHERE id = :id');
        $up->execute([':id' => $rideId]);
        echo json_encode(['success' => true, 'message' => 'Trip completed. Cash collected.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}