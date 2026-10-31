<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// Search OpenStreetMap Nominatim with deep village/hamlet/locality details in India/West Bengal
$nominatimUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q'              => $query,
    'format'         => 'json',
    'addressdetails' => 1,
    'countrycodes'   => 'in',
    'limit'          => 8
]);

$options = [
    'http' => [
        'method'     => 'GET',
        'header'     => "User-Agent: BengalSeva-Village-Locator/2.0 (support@bengalseva.in)\r\nAccept-Language: en,bn;q=0.9\r\n",
        'timeout'    => 4
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($nominatimUrl, false, $context);

$results = [];

if ($response !== false) {
    $data = json_decode($response, true);
    if (is_array($data)) {
        foreach ($data as $item) {
            $addr = $item['address'] ?? [];
            
            // Extract the most specific micro-location name (Village, Hamlet, Suburb, Town)
            $placeName = $addr['village'] 
                ?? $addr['hamlet'] 
                ?? $addr['suburb'] 
                ?? $addr['neighbourhood'] 
                ?? $addr['town'] 
                ?? $addr['residential']
                ?? $addr['city_district']
                ?? $addr['city'] 
                ?? $item['name']
                ?? $item['display_name'];

            // Identify place type
            $typeLabel = 'Locality';
            if (!empty($addr['village'])) $typeLabel = 'Village';
            elseif (!empty($addr['hamlet'])) $typeLabel = 'Gram Para';
            elseif (!empty($addr['town'])) $typeLabel = 'Town';
            elseif (!empty($addr['suburb'])) $typeLabel = 'Area';

            // District / Region
            $district = $addr['state_district'] ?? $addr['county'] ?? $addr['district'] ?? '';
            $state = $addr['state'] ?? 'West Bengal';

            $subtitleParts = array_filter([$district, $state]);
            $subtitle = implode(', ', $subtitleParts);

            $results[] = [
                'name'      => $placeName,
                'type'      => $typeLabel,
                'subtitle'  => $subtitle,
                'full_name' => "{$placeName}, {$subtitle}",
                'lat'       => (float)$item['lat'],
                'lon'       => (float)$item['lon']
            ];
        }
    }
}

// Fallback to Photon if Nominatim network is delayed
if (empty($results)) {
    $photonUrl = 'https://photon.komoot.io/api/?q=' . urlencode($query) . '&lat=22.57&lon=88.36&limit=6';
    $photonResp = @file_get_contents($photonUrl, false, $context);
    if ($photonResp !== false) {
        $pData = json_decode($photonResp, true);
        if (!empty($pData['features'])) {
            foreach ($pData['features'] as $feat) {
                $p = $feat['properties'] ?? [];
                $coords = $feat['geometry']['coordinates'] ?? [88.36, 22.57];
                $name = $p['name'] ?? 'Location';
                $sub = array_filter([$p['district'] ?? '', $p['city'] ?? '', $p['state'] ?? 'West Bengal']);

                $results[] = [
                    'name'      => $name,
                    'type'      => 'Location',
                    'subtitle'  => implode(', ', $sub),
                    'full_name' => $name . ', ' . implode(', ', $sub),
                    'lat'       => (float)$coords[1],
                    'lon'       => (float)$coords[0]
                ];
            }
        }
    }
}

echo json_encode(['success' => true, 'results' => $results]);