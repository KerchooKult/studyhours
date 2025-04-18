<?php
header("Content-Type: application/json"); // <— Move this to the top
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Check if active status is provided
$status = isset($data['active']) ? $data['active'] : false;

// Only update latitude and longitude if they are provided
if (isset($data['latitude'])) {
    $latitude = (float) $data['latitude'];
    $_SESSION['latitude'] = $latitude;
} else {
    $latitude = $_SESSION['latitude']; // Keep existing latitude if not provided
}

if (isset($data['longitude'])) {
    $longitude = (float) $data['longitude'];
    $_SESSION['longitude'] = $longitude;
} else {
    $longitude = $_SESSION['longitude']; // Keep existing longitude if not provided
}

// Update session status
$_SESSION['active'] = $status;

echo json_encode([
    'success' => true,
    'status' => $status,
    'student_id' => $_SESSION['student_id'],
    'latitude' => $latitude,
    'longitude' => $longitude
]);
?>
