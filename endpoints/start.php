<?php
session_start(); // Start the session
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: chrome-extension://cpicngjcoodhlaiiebdilocdbpjjimjo");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}
require_once './internal/db.php';

function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    // Radius of Earth in kilometers
    $R = 6371; 

    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    // Differences in coordinates
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;

    // Haversine formula
    $a = sin($dlat / 2) * sin($dlat / 2) +
         cos($lat1) * cos($lat2) *
         sin($dlon / 2) * sin($dlon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    // Distance in kilometers
    $distance = $R * $c;

    return $distance;
}

return function($method, $params, $body) {
    if ($method !== 'POST') {
        http_response_code(405);
        return ['error' => 'Method not allowed.'];
    }

    if (!isset($body['latitude'], $body['longitude'], $body['studentid'])) {
        http_response_code(400);
        return ['error' => 'Missing required parameters.'];
    }

    $studentid = $body['studentid'];
    $latitude = (float) $body['latitude'];
    $longitude = (float) $body['longitude'];

    // Allowed location(s)
    $allowed_locations = [
        ['lat' => 38.761066, 'lng' => -93.738739], // Example location
    ];

    $withinLocation = true;
    foreach ($allowed_locations as $location) {
        $location_lat = (float) $location['lat'];
        $location_lng = (float) $location['lng'];

        // Calculate the distance using the Haversine formula
        $distance = haversine_distance($latitude, $longitude, $location_lat, $location_lng);

        // Check if the distance is less than 50 feet (~0.01524 km)
        if ($distance < 0.01524) {
            $withinLocation = true;
            break;
        }
    }

    if ($withinLocation) {
        $_SESSION['student_id'] = $studentid;
        $_SESSION['start_time'] = time();
        return ['message' => 'Study session started successfully!'];
    } else {
        http_response_code(403);
        return ['error' => 'You are not in an approved study location.'];
    }
};
