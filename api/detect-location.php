<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/bootstrap.php';

$lat = isset($_REQUEST['lat']) && $_REQUEST['lat'] !== '' ? (float)$_REQUEST['lat'] : null;
$lng = isset($_REQUEST['lng']) && $_REQUEST['lng'] !== '' ? (float)$_REQUEST['lng'] : null;

$districtName = 'Kolkata';
$districtId = 12; // Default Kolkata
$detectedCity = 'Kolkata Metro';
$method = 'ip';

// 23 Official Districts of West Bengal Coordinates
$wbDistricts = [
    1  => ['name' => 'Alipurduar', 'lat' => 26.4919, 'lng' => 89.5271],
    2  => ['name' => 'Bankura', 'lat' => 23.2322, 'lng' => 87.0673],
    3  => ['name' => 'Birbhum', 'lat' => 23.9056, 'lng' => 87.5246],
    4  => ['name' => 'Cooch Behar', 'lat' => 26.3239, 'lng' => 89.4449],
    5  => ['name' => 'Dakshin Dinajpur', 'lat' => 25.2215, 'lng' => 88.7667],
    6  => ['name' => 'Darjeeling', 'lat' => 27.0410, 'lng' => 88.2663],
    7  => ['name' => 'Hooghly', 'lat' => 22.9034, 'lng' => 88.3968],
    8  => ['name' => 'Howrah', 'lat' => 22.5958, 'lng' => 88.2636],
    9  => ['name' => 'Jalpaiguri', 'lat' => 26.5414, 'lng' => 88.7196],
    10 => ['name' => 'Jhargram', 'lat' => 22.4538, 'lng' => 86.9922],
    11 => ['name' => 'Kalimpong', 'lat' => 27.0667, 'lng' => 88.4667],
    12 => ['name' => 'Kolkata', 'lat' => 22.5726, 'lng' => 88.3639],
    13 => ['name' => 'Malda', 'lat' => 25.0108, 'lng' => 88.1411],
    14 => ['name' => 'Murshidabad', 'lat' => 24.1818, 'lng' => 88.2709],
    15 => ['name' => 'Nadia', 'lat' => 23.4710, 'lng' => 88.5565],
    16 => ['name' => 'North 24 Parganas', 'lat' => 22.7214, 'lng' => 88.4800],
    17 => ['name' => 'Paschim Bardhaman', 'lat' => 23.6889, 'lng' => 86.9661],
    18 => ['name' => 'Paschim Medinipur', 'lat' => 22.4257, 'lng' => 87.3199],
    19 => ['name' => 'Purba Bardhaman', 'lat' => 23.2324, 'lng' => 87.8615],
    20 => ['name' => 'Purba Medinipur', 'lat' => 21.9360, 'lng' => 87.7766],
    21 => ['name' => 'Purulia', 'lat' => 23.3322, 'lng' => 86.3652],
    22 => ['name' => 'South 24 Parganas', 'lat' => 22.1648, 'lng' => 88.4334],
    23 => ['name' => 'Uttar Dinajpur', 'lat' => 25.6253, 'lng' => 88.1256]
];

function getDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// 1. Try High Accuracy GPS if Lat/Lng sent by browser
if ($lat !== null && $lng !== null && $lat > 0) {
    $method = 'gps';
    $minDist = PHP_FLOAT_MAX;

    foreach ($wbDistricts as $id => $d) {
        $dist = getDistanceKm($lat, $lng, (float)$d['lat'], (float)$d['lng']);
        if ($dist < $minDist) {
            $minDist = $dist;
            $districtId = $id;
            $districtName = $d['name'];
        }
    }

    // Try reverse geocoding village / town name
    $osmUrl = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=14&addressdetails=1";
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: BengalSeva-Live-Detector/2.0 (contact@bengalseva.in)\r\n",
            'timeout' => 3
        ]
    ];
    $ctx = stream_context_create($opts);
    $geoResp = @file_get_contents($osmUrl, false, $ctx);
    if ($geoResp !== false) {
        $geoJson = json_decode($geoResp, true);
        if (!empty($geoJson['address'])) {
            $a = $geoJson['address'];
            $detectedCity = $a['village'] ?? $a['hamlet'] ?? $a['town'] ?? $a['suburb'] ?? $a['city'] ?? $districtName;
        }
    }
} else {
    // 2. Fallback: Detect District via User IP Address
    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? '127.0.0.1';

    if ($clientIp !== '127.0.0.1' && $clientIp !== '::1') {
        $ipUrl = "http://ip-api.com/json/{$clientIp}?fields=status,city,regionName,lat,lon";
        $ipResp = @file_get_contents($ipUrl, false, stream_context_create(['http' => ['timeout' => 3]]));
        if ($ipResp !== false) {
            $ipData = json_decode($ipResp, true);
            if (!empty($ipData['status']) && $ipData['status'] === 'success') {
                $city = $ipData['city'] ?? '';
                $ipLat = (float)($ipData['lat'] ?? 0);
                $ipLng = (float)($ipData['lon'] ?? 0);

                if ($ipLat > 0 && $ipLng > 0) {
                    $minDist = PHP_FLOAT_MAX;
                    foreach ($wbDistricts as $id => $d) {
                        $dist = getDistanceKm($ipLat, $ipLng, (float)$d['lat'], (float)$d['lng']);
                        if ($dist < $minDist) {
                            $minDist = $dist;
                            $districtId = $id;
                            $districtName = $d['name'];
                        }
                    }
                    $detectedCity = $city ?: $districtName;
                }
            }
        }
    }
}

// Save in Session and Cookie
$_SESSION['detected_district_id'] = $districtId;
$_SESSION['detected_district_name'] = $districtName;
setcookie('bs_district_id', (string)$districtId, time() + (86400 * 30), '/');
setcookie('bs_district_name', $districtName, time() + (86400 * 30), '/');

echo json_encode([
    'success'       => true,
    'method'        => $method,
    'district_id'   => $districtId,
    'district_name' => $districtName,
    'detected_city' => $detectedCity,
    'display'       => "West Bengal | {$detectedCity}"
]);