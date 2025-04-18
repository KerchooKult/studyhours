<?php
session_start();

// Remove var_dump($_POST) as it will interfere with JSON response

require 'db.php';

if (isset($_POST['start_study']) && isset($_POST['latitude']) && isset($_POST['longitude'])) {
    $latitude = (float) $_POST['latitude'];
    $longitude = (float) $_POST['longitude'];
    
    // Predefined allowed location
    $allowed_locations = [
        ['lat' => 38.761066, 'lng' => -93.738739], // Example location
    ];

    $withinLocation = false;

    // Check if the provided latitude and longitude are within the allowed location
    foreach ($allowed_locations as $location) {
        $location_lat = (float) $location['lat'];
        $location_lng = (float) $location['lng'];

        // Use simple Euclidean distance (could use Haversine for more accuracy)
        $distance = sqrt(pow($latitude - $location_lat, 2) + pow($longitude - $location_lng, 2));

        if ($distance < 0.003) { // Within a small threshold
            $withinLocation = true;
            break;
        }
    }

    // print out the distance
    // where do i see the distance after its been dumped? 


    // Respond based on whether the location check passed
    if ($withinLocation) {
        $_SESSION['start_time'] = time();
        echo json_encode(['message' => 'Study session started successfully!']);
    } else {
        echo json_encode(['error' => 'You are not in an approved study location.']);
    }
} else {
    echo json_encode(['error' => 'Missing required parameters.']);
}
?>
